<?php
require_once "../includes/auth_check.php";
require_once "../config/db.php";

// Message from add/edit/delete
$success = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);

// Read filters from the URL
$search   = trim($_GET["q"] ?? "");
$category = $_GET["category"] ?? "";
$status   = $_GET["status"] ?? "";
$page     = max(1, (int)($_GET["page"] ?? 1));
$perPage  = 8;
$offset   = ($page - 1) * $perPage;

// Build the WHERE clause
$where  = [];
$params = [];

if ($search !== "") {
    $where[]  = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category !== "" && ctype_digit((string)$category)) {
    $where[]  = "p.category_id = ?";
    $params[] = $category;
}
if ($status === "out") {
    $where[] = "p.quantity = 0";
} elseif ($status === "low") {
    $where[] = "p.quantity > 0 AND p.quantity <= p.low_stock_limit";
} elseif ($status === "in") {
    $where[] = "p.quantity > p.low_stock_limit";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Total rows (for pagination)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $countStmt->execute($params);
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // Rows for the current page
    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            $whereSql
            ORDER BY p.id DESC
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);

    $i = 1;
    foreach ($params as $value) {
        $stmt->bindValue($i++, $value);
    }
    $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($i,   $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $categories = [];
    $totalRows = 0;
    $totalPages = 1;
    $loadError = "Could not load products. Please try again.";
}

// Keeps the filters when clicking page numbers
function pageLink($p) {
    $query = $_GET;
    $query["page"] = $p;
    return "?" . http_build_query($query);
}

require_once "../includes/header.php";
?>

<div class="page-header">
    <h2>Products</h2>
    <a href="add.php" class="btn">+ Add Product</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($loadError)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($loadError) ?></div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="" class="filter-bar">
        <input type="text" name="q" placeholder="Search by name or SKU..." value="<?= htmlspecialchars($search) ?>">

        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat["id"] ?>" <?= $category == $cat["id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat["name"]) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">All Stock Status</option>
            <option value="in"  <?= $status === "in"  ? "selected" : "" ?>>In Stock</option>
            <option value="low" <?= $status === "low" ? "selected" : "" ?>>Low Stock</option>
            <option value="out" <?= $status === "out" ? "selected" : "" ?>>Out of Stock</option>
        </select>

        <button type="submit" class="btn">Filter</button>
        <a href="list.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="8" class="empty">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <?php
                    if ($p["quantity"] == 0) {
                        $badge = '<span class="badge badge-out">Out of Stock</span>';
                    } elseif ($p["quantity"] <= $p["low_stock_limit"]) {
                        $badge = '<span class="badge badge-low">Low Stock</span>';
                    } else {
                        $badge = '<span class="badge badge-in">In Stock</span>';
                    }
                    ?>
                    <tr>
                        <td>
                            <?php if ($p["image"]): ?>
                                <img class="thumb" src="../assets/uploads/<?= htmlspecialchars($p["image"]) ?>" alt="">
                            <?php else: ?>
                                <div class="thumb thumb-empty">📦</div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p["name"]) ?></td>
                        <td><?= htmlspecialchars($p["sku"]) ?></td>
                        <td><?= htmlspecialchars($p["category_name"] ?? "—") ?></td>
                        <td><?= (int)$p["quantity"] ?></td>
                        <td><?= number_format($p["price"], 2) ?></td>
                        <td><?= $badge ?></td>
                        <td class="actions">
                            <a href="edit.php?id=<?= (int)$p["id"] ?>" class="btn btn-small">Edit</a>
                            <?php if (isAdmin()): ?>
                                <form method="POST" action="delete.php" class="inline-form"
                                      onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                    <input type="hidden" name="id" value="<?= (int)$p["id"] ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="result-count"><?= $totalRows ?> product(s) found</p>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= pageLink($page - 1) ?>">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="<?= pageLink($i) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= pageLink($page + 1) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>