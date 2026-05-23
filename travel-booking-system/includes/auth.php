<?php
// ============================================
// AUTHENTICATION HELPERS
// ============================================
session_start();

require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: " . SITE_URL . "/dashboard.php");
        exit();
    }
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $conn = getDBConnection();
    $id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Generate transaction ID
function generateTransactionID() {
    return 'TXN-' . strtoupper(bin2hex(random_bytes(5))) . '-' . time();
}

// Format price
function formatPrice($amount) {
    return CURRENCY . number_format($amount, 2);
}
?>
