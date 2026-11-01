<?php
require_once "../includes/auth_check.php";
require_once "../config/db.php";

// Only admins can open this page
if (!isAdmin()) {
    $_SESSION["success"] = "You do not have permission to view that page.";
    header("Location: ../dashboard.php");
    exit;
}

$message = $_SESSION["success"] ?? "";
unset($_SESSION["success"]);
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $userId = $_POST["user_id"] ?? "";

    if (!ctype_digit((string)$userId)) {
        $errors[] = "Invalid user.";
    } elseif ((int)$userId === (int)$_SESSION["user_id"]) {
        // Stops the last admin from locking themselves out
        $errors[] = "You cannot change or delete your own account here.";
    } else {
        try {
            if ($action === "role") {
                $role = $_POST["role"] ?? "";
                if (!in_array($role, ["admin", "staff"], true)) {
                    $errors[] = "Invalid role.";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$role, $userId]);
                    $message = "User role updated.";
                }
            } elseif ($action === "delete") {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = "User deleted.";
            }
        } catch (PDOException $e) {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}

try {
    $users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $errors[] = "Could not load users.";
}

require_once "../includes/header.php";
?>

<div class="page-header">
    <h2>User Management</h2>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $isSelf = ((int)$u["id"] === (int)$_SESSION["user_id"]); ?>
                <tr>
                    <td><?= htmlspecialchars($u["name"]) ?><?= $isSelf ? " (you)" : "" ?></td>
                    <td><?= htmlspecialchars($u["email"]) ?></td>
                    <td>
                        <span class="badge <?= $u["role"] === "admin" ? "badge-updated" : "badge-in" ?>">
                            <?= htmlspecialchars(ucfirst($u["role"])) ?>
                        </span>
                    </td>
                    <td><?= date("d M Y", strtotime($u["created_at"])) ?></td>
                    <td class="actions">
                        <?php if ($isSelf): ?>
                            <span class="muted">—</span>
                        <?php else: ?>
                            <form method="POST" action="" class="inline-form role-form">
                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="user_id" value="<?= (int)$u["id"] ?>">
                                <select name="role">
                                    <option value="staff" <?= $u["role"] === "staff" ? "selected" : "" ?>>Staff</option>
                                    <option value="admin" <?= $u["role"] === "admin" ? "selected" : "" ?>>Admin</option>
                                </select>
                                <button type="submit" class="btn btn-small">Save</button>
                            </form>

                            <form method="POST" action="" class="inline-form"
                                  onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= (int)$u["id"] ?>">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>