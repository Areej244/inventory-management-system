<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base = "/inventory-system";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
        <link rel="stylesheet" href="<?= $base ?>/assets/css/theme.css?v=<?= time() ?>">
</head>
<body>
<nav class="navbar">
    <a class="brand" href="<?= $base ?>/dashboard.php">📦 InventoryPro</a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">☰</button>

    <div class="nav-links" id="navLinks">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $base ?>/dashboard.php">Dashboard</a>
            <a href="<?= $base ?>/products/list.php">Products</a>
            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <a href="<?= $base ?>/admin/users.php">Users</a>
            <?php endif; ?>
            <a href="<?= $base ?>/auth/logout.php">Logout (<?= htmlspecialchars($_SESSION['user_name']) ?>)</a>
        <?php else: ?>
            <a href="<?= $base ?>/auth/login.php">Login</a>
            <a href="<?= $base ?>/auth/register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>
<main class="container">