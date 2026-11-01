<?php
require_once "includes/auth_check.php";
require_once "config/db.php";

try {
    // Stat cards
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    $stockValue = (float)$pdo->query("SELECT COALESCE(SUM(quantity * price), 0) FROM products")->fetchColumn();

    $lowStock = (int)$pdo->query(
        "SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= low_stock_limit"
    )->fetchColumn();

    $outOfStock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();

    $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

    // Products that need restocking (low or out of stock)
    $restock = $pdo->query(
        "SELECT name, sku, quantity, low_stock_limit
         FROM products
         WHERE quantity <= low_stock_limit
         ORDER BY quantity ASC
         LIMIT 5"
    )->fetchAll();

    // Recent activity
    $activities = $pdo->query(
        "SELECT a.action, a.product_name, a.created_at, u.name AS user_name
         FROM activity_log a
         LEFT JOIN users u ON a.user_id = u.id
         ORDER BY a.id DESC
         LIMIT 8"
    )->fetchAll();
} catch (PDOException $e) {
    $totalProducts = $lowStock = $outOfStock = $totalCategories = 0;
    $stockValue = 0;
    $restock = [];
    $activities = [];
    $loadError = "Could not load dashboard data. Please try again.";
}

require_once "includes/header.php";
?>

<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p class="muted">Welcome, <?= htmlspecialchars($_SESSION["user_name"]) ?> (<?= htmlspecialchars($_SESSION["user_role"]) ?>)</p>
    </div>
    <a href="products/add.php" class="btn">+ Add Product</a>
</div>

<?php if (!empty($loadError)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($loadError) ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card stat-blue">
        <span class="stat-label">Total Products</span>
        <span class="stat-number"><?= $totalProducts ?></span>
    </div>
    <div class="stat-card stat-green">
        <span class="stat-label">Total Stock Value</span>
       <span class="stat-number"><?= number_format($stockValue) ?></span>
    </div>
    <div class="stat-card stat-yellow">
        <span class="stat-label">Low Stock</span>
        <span class="stat-number"><?= $lowStock ?></span>
    </div>
    <div class="stat-card stat-red">
        <span class="stat-label">Out of Stock</span>
        <span class="stat-number"><?= $outOfStock ?></span>
    </div>
    <div class="stat-card stat-purple">
        <span class="stat-label">Categories</span>
        <span class="stat-number"><?= $totalCategories ?></span>
    </div>
</div>

<div class="dash-grid">
    <div class="card">
        <h3>Needs Restocking</h3>
        <?php if (empty($restock)): ?>
            <p class="muted">All products are well stocked. 🎉</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>SKU</th><th>Qty</th><th>Limit</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($restock as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r["name"]) ?></td>
                            <td><?= htmlspecialchars($r["sku"]) ?></td>
                            <td>
                                <span class="badge <?= $r["quantity"] == 0 ? 'badge-out' : 'badge-low' ?>">
                                    <?= (int)$r["quantity"] ?>
                                </span>
                            </td>
                            <td><?= (int)$r["low_stock_limit"] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:12px;"><a href="products/list.php?status=low">View all low stock &raquo;</a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Activity</h3>
        <?php if (empty($activities)): ?>
            <p class="muted">No activity yet.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($activities as $a): ?>
                    <li>
                        <span class="badge badge-<?= htmlspecialchars($a["action"]) ?>">
                            <?= htmlspecialchars(ucfirst($a["action"])) ?>
                        </span>
                        <div>
                            <strong><?= htmlspecialchars($a["product_name"]) ?></strong>
                            <small>
                                by <?= htmlspecialchars($a["user_name"] ?? "Unknown user") ?>
                                &middot; <?= date("d M Y, h:i A", strtotime($a["created_at"])) ?>
                            </small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>