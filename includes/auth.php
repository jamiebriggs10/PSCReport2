<?php
/**
 * Session Management Utilities
 * Presswick Sailing Club Issue Reporting System
 */

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure URL helpers and DB connection utilities are available
require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'ADMIN';
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_name'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role'],
        'must_change_password' => $_SESSION['must_change_password'] ?? false
    ];
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . getRelativeUrl('login.php'));
        exit;
    }
}

/**
 * Require admin privileges
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: ' . getRelativeUrl('index.php'));
        exit;
    }
}

/**
 * Check if password change is required
 */
function checkPasswordChangeRequired() {
    if (isLoggedIn() && isset($_SESSION['must_change_password']) && $_SESSION['must_change_password']) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'change_password.php' && $current_page !== 'logout.php') {
            header('Location: ' . getRelativeUrl('change_password.php?required=1'));
            exit;
        }
    }
}

/**
 * Login user
 */
function loginUser($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['must_change_password'] = $user['must_change_password'];
    
    // Update last login time
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
}

/**
 * Logout user
 */
function logoutUser() {
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate default password for new users
 */
function generateDefaultPassword($userId) {
    return "PSC{$userId}";
}
?>