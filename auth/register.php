<?php
session_start();
require_once "../config/db.php";

// Already logged in? Go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$errors = [];
$name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    // Validation
    if ($name === "" || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if (!preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors[] = "Password must contain at least one letter and one number.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Check if email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "This email is already registered.";
            }
        } catch (PDOException $e) {
            $errors[] = "Something went wrong. Please try again.";
        }
    }

    // Save the user
    if (empty($errors)) {
        try {
            // First registered user becomes admin, the rest are staff
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $role = ($count == 0) ? "admin" : "staff";

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed, $role]);

            $_SESSION["success"] = "Registration successful! Please log in.";
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Could not create account. Please try again.";
        }
    }
}

require_once "../includes/header.php";
?>

<div class="card form-card">
    <h2>Create Account</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <small>At least 8 characters, with a letter and a number.</small>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn btn-full">Register</button>
    </form>

    <p style="margin-top:14px; text-align:center;">
        Already have an account? <a href="login.php">Login</a>
    </p>
</div>

<?php require_once "../includes/footer.php"; ?>