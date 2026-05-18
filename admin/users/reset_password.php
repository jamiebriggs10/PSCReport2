<?php
/**
 * Reset User Password (Admin Only)
 * Presswick Sailing Club Issue Reporting System
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../config/database.php';

// Require admin authentication
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . getFullUrl('admin/dashboard.php'));
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Invalid request.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . getFullUrl('admin/dashboard.php'));
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['flash_message'] = 'Invalid user ID.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . getFullUrl('admin/dashboard.php'));
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get user details and verify they exist and are not admin
    $stmt = $pdo->prepare("SELECT id, full_name, username, role FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['flash_message'] = 'User not found.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . getFullUrl('admin/dashboard.php'));
        exit;
    }
    
    // Only allow resetting USER passwords, not ADMIN passwords
    if ($user['role'] === 'ADMIN') {
        $_SESSION['flash_message'] = 'Cannot reset password for admin users.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . getFullUrl('admin/dashboard.php'));
        exit;
    }
    
    // Generate new default password and hash
    $newPassword = generateDefaultPassword($userId);
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password and set must_change_password flag
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = ?, must_change_password = 1, updated_at = NOW() 
        WHERE id = ?
    ");
    
    if ($stmt->execute([$newPasswordHash, $userId])) {
        // Store reset info in session for popup display
        $_SESSION['password_reset'] = [
            'user_id' => $userId,
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'new_password' => $newPassword
        ];
        $_SESSION['flash_message'] = "Password reset for {$user['full_name']}";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to reset password.';
        $_SESSION['flash_type'] = 'danger';
    }
    
} catch (PDOException $e) {
    error_log("Password reset error: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Database error occurred while resetting password.';
    $_SESSION['flash_type'] = 'danger';
}

header('Location: ' . getFullUrl('admin/users_list.php'));
exit;
?>