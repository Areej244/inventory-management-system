<?php
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$errors = [];
$name = $sku = $supplier = "";
$category_id = "";
$quantity = 0;
$price = "0.00";
$low_stock_limit = 5;

// Load categories for the dropdown
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

    // Check SKU is unique
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        if ($stmt->fetch()) {
            $errors[] = "This SKU already exists.";
        }
    }

    // Optional image upload
    $imageName = null;
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
                // Random filename so users can't overwrite files or run scripts
                $imageName = bin2hex(random_bytes(8)) . "." . $allowed[$mime];
                $target = "../assets/uploads/" . $imageName;
                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target)) {
                    $errors[] = "Could not save the image.";
                    $imageName = null;
                }
            }
        }
    }

    // Save product
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products (name, sku, category_id, quantity, price, supplier, low_stock_limit, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $sku, $category_id, $quantity, $price, $supplier, $low_stock_limit, $imageName]);

            // Record in activity log
            $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, product_name) VALUES (?, 'added', ?)");
            $log->execute([$_SESSION["user_id"], $name]);

            $_SESSION["success"] = "Product added successfully.";
            header("Location: list.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Could not save the product. Please try again.";
        }
    }
}

require_once "../includes/header.php";
?>

<div class="card form-wide">
    <h2>Add Product</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
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
                <input type="text" id="supplier" name="supplier" value="<?= htmlspecialchars($supplier) ?>">
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
            <label for="image">Product Image (optional, max 2 MB)</label>
            <input type="file" id="image" name="image" accept="image/png, image/jpeg, image/webp">
        </div>

        <button type="submit" class="btn">Save Product</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once "../includes/footer.php"; ?>