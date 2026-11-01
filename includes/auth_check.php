<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in? Send to login page
if (!isset($_SESSION["user_id"])) {
    header("Location: /inventory-system/auth/login.php");
    exit;
}

// Helper: is the current user an admin?
function isAdmin() {
    return isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "admin";
}