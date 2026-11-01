<?php
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$id = $_GET["id"] ?? ($_POST["id"] ?? "");
if (!ctype_digit((string)$id)) {
    header("Location: list.php");
    exit;
}

// Load the product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION["success"] = "Product not found.";
    header("Location: list.php");
    exit;
}

$errors = [];
$name            = $product["name"];
$sku             = $product["sku"];
$category_id     = $product["category_id"];
$quantity        = $product["quantity"];
$price           = $product["price"];
$supplier        = $product["supplier"];
$low_stock_limit = $product["low_stock_limit"];

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name            = trim($_POST["name"] ?? "");
    $sku             = strtoupper(trim($_POST["sku"] ?? ""));
    $category_id     = $_POST["category_id"] ?? "";
    $quantity        = $_POST["quantity"] ?? "";
    $price           = $_POST["price"] ?? "";
    $supplier        = trim($_POST["supplier"] ?? "");
    $low_stock_limit = $_POST["low_stock_limit"] ?? "";

    // Validation
    if (strlen($name) < 2 || strlen($name) > 150) {
        $errors[] = "Product name must be between 2 and 150 characters.";
    }
    if (!preg_match("/^[A-Z0-9\-]{2,50}$/", $sku)) {
        $errors[] = "SKU must be 2-50 characters: letters, numbers and dashes only.";
    }
    if ($category_id === "" || !ctype_digit((string)$category_id)) {
        $errors[] = "Please select a category.";
    }
    if (!ctype_digit((string)$quantity)) {
        $errors[] = "Quantity must be a whole number (0 or more).";
    }
    if (!is_numeric($price) || $price < 0) {
        $errors[] = "Price must be a number (0 or more).";
    }
    if (!ctype_digit((string)$low_stock_limit)) {
        $errors[] = "Low stock limit must be a whole number (0 or more).";
    }

    // SKU must be unique (ignoring this product itself)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $stmt->execute([$sku, $id]);
        if ($stmt->fetch()) {
            $errors[] = "This SKU is used by another product.";
        }
    }

    // Optional new image
    $imageName = $product["image"];
    $newImage  = false;
    if (empty($errors) && isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $errors[] = "Image upload failed. Please try again.";
        } elseif ($_FILES["image"]["size"] > 2 * 1024 * 1024) {
            $errors[] = "Image must be smaller than 2 MB.";
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES["image"]["tmp_name"]);
            $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

            if (!isset($allowed[$mime])) {
                $errors[] = "Only JPG, PNG or WEBP images are allowed.";
            } else {
                $newName = bin2hex(random_bytes(8)) . "." . $allowed[$mime];
                if (move_uploaded_file($_FILES["image"]["tmp_name"], "../assets/uploads/" . $newName)) {
                    $imageName = $newName;
                    $newImage  = true;
                } else {
                    $errors[] = "Could not save the image.";
                }
            }
        }
    }

    // Update product
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE products
                 SET name = ?, sku = ?, category_id = ?, quantity = ?, price = ?,
                     supplier = ?, low_stock_limit = ?, image = ?
                 WHERE id = ?"
            );
            $stmt->execute([$name, $sku, $category_id, $quantity, $price, $supplier, $low_stock_limit, $imageName, $id]);

            // Remove the old image file if it was replaced
            if ($newImage && $product["image"]) {
                $oldFile = "../assets/uploads/" . basename($product["image"]);
                if (is_file($oldFile)) {
                    unlink($oldFile);
                }
            }

            $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, product_name) VALUES (?, 'updated', ?)");
            $log->execute([$_SESSION["user_id"], $name]);

            $_SESSION["success"] = "Product updated successfully.";
            header("Location: list.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Could not update the product. Please try again.";
        }
    }
}

require_once "../includes/header.php";
?>

<div class="card form-wide">
    <h2>Edit Product</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>
            <div class="form-group">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($sku) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat["id"] ?>" <?= $category_id == $cat["id"] ? "selected" : "" ?>>
                            <?= htmlspecialchars($cat["name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="supplier">Supplier</label>
                <input type="text" id="supplier" name="supplier" value="<?= htmlspecialchars((string)$supplier) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" min="0" value="<?= htmlspecialchars((string)$quantity) ?>" required>
            </div>
            <div class="form-group">
                <label for="price">Price</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="<?= htmlspecialchars((string)$price) ?>" required>
            </div>
            <div class="form-group">
                <label for="low_stock_limit">Low Stock Limit</label>
                <input type="number" id="low_stock_limit" name="low_stock_limit" min="0" value="<?= htmlspecialchars((string)$low_stock_limit) ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="image">Product Image (leave empty to keep the current one)</label>
            <?php if ($product["image"]): ?>
                <img class="thumb" style="margin-bottom:8px; width:80px; height:80px;"
                     src="../assets/uploads/<?= htmlspecialchars($product["image"]) ?>" alt="">
            <?php endif; ?>
            <input type="file" id="image" name="image" accept="image/png, image/jpeg, image/webp">
        </div>

        <button type="submit" class="btn">Update Product</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once "../includes/footer.php"; ?>