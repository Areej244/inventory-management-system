<?php
require_once "../includes/auth_check.php";
require_once "../config/db.php";

// Only POST requests are accepted (the Delete button in the list is a POST form)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: list.php");
    exit;
}

// Only admins may delete
if (!isAdmin()) {
    $_SESSION["success"] = "You do not have permission to delete products.";
    header("Location: list.php");
    exit;
}

$id = $_POST["id"] ?? "";
if (!ctype_digit((string)$id)) {
    header("Location: list.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name, image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product) {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

        // Delete the image file too
        if ($product["image"]) {
            $file = "../assets/uploads/" . basename($product["image"]);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, product_name) VALUES (?, 'deleted', ?)");
        $log->execute([$_SESSION["user_id"], $product["name"]]);

        $_SESSION["success"] = "Product deleted successfully.";
    } else {
        $_SESSION["success"] = "Product not found.";
    }
} catch (PDOException $e) {
    $_SESSION["success"] = "Could not delete the product. Please try again.";
}

header("Location: list.php");
exit;