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

// Remember-me cookie config
define('REMEMBER_COOKIE_NAME', 'psc_remember');
define('REMEMBER_COOKIE_LIFETIME', 90 * 24 * 60 * 60); // 90 days

// Try to re-establish session from remember-me cookie before any auth checks run
if (!isset($_SESSION['user_id']) && isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
    tryRememberLogin();
}

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

    issueRememberCookie((int)$user['id']);
}

/**
 * Logout user
 */
function logoutUser() {
    clearRememberCookie();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/**
 * Issue a persistent "remember me" cookie + DB token
 */
function issueRememberCookie($userId) {
    $selector  = bin2hex(random_bytes(12)); // 24 hex chars
    $validator = bin2hex(random_bytes(32)); // 64 hex chars
    $tokenHash = hash('sha256', $validator);
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_COOKIE_LIFETIME);

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $selector, $tokenHash, $expiresAt]);

    setcookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, [
        'expires'  => time() + REMEMBER_COOKIE_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Clear the remember-me cookie and delete its DB row
 */
function clearRememberCookie() {
    if (!isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return;
    }
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) === 2 && ctype_xdigit($parts[0])) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->execute([$parts[0]]);
        } catch (Exception $e) {
            // ignore
        }
    }
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/**
 * Attempt to log the user in from a remember-me cookie.
 * On success, re-establishes the session and rotates the token.
 * On any failure (expired, malformed, tampered), clears the cookie.
 */
function tryRememberLogin() {
    $raw = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
        clearRememberCookie();
        return;
    }
    [$selector, $validator] = $parts;

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, user_id, token_hash, expires_at FROM auth_tokens WHERE selector = ?");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row) {
            clearRememberCookie();
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
            clearRememberCookie();
            return;
        }

        $candidate = hash('sha256', $validator);
        if (!hash_equals($row['token_hash'], $candidate)) {
            // Selector matched but validator did not — possible theft. Wipe all of this user's tokens.
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$row['user_id']]);
            clearRememberCookie();
            return;
        }

        $userStmt = $pdo->prepare("SELECT id, full_name, username, role, must_change_password, is_active FROM users WHERE id = ?");
        $userStmt->execute([$row['user_id']]);
        $user = $userStmt->fetch();
        if (!$user || !$user['is_active']) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$row['user_id']]);
            clearRememberCookie();
            return;
        }

        // Establish session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['must_change_password'] = $user['must_change_password'];

        // Rotate token: delete the used one, issue a fresh cookie+row
        $pdo->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
        issueRememberCookie((int)$user['id']);
    } catch (Exception $e) {
        clearRememberCookie();
    }
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