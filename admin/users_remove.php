<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Require admin access
requireAuth();
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$adminPassword = filter_input(INPUT_POST, 'admin_password', FILTER_UNSAFE_RAW);

if (!$userId || !$adminPassword) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Prevent self-removal
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot remove your own account']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Verify admin password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'ADMIN'");
    $stmt->execute([$_SESSION['user_id']]);
    $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adminData || !password_verify($adminPassword, $adminData['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
        exit;
    }
    
    // Check if target user exists and get their details
    $stmt = $pdo->prepare("SELECT id, full_name, username, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Prevent removing admin users (only non-admin users can be removed)
    if ($targetUser['role'] === 'ADMIN') {
        echo json_encode(['success' => false, 'message' => 'Cannot remove admin users']);
        exit;
    }
    
    // Check if user has any problems associated (as reporter or in resolution history)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) FROM problems p 
                          LEFT JOIN resolutions r ON p.id = r.problem_id 
                          WHERE p.reported_by = ? OR r.action_by = ?");
    $stmt->execute([$userId, $userId]);
    $problemCount = $stmt->fetchColumn();
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // If user has problems, we should update them to show the user was removed
        // but keep the data for audit purposes
        if ($problemCount > 0) {
            // Check if user is already inactive
            if ($targetUser['is_active'] == 0) {
                echo json_encode(['success' => false, 'message' => 'User is already inactive']);
                exit;
            }
            
            // Option 1: Mark user as removed (deactivate) instead of deleting
            // Only add (Removed) suffix if it doesn't already exist
            $newFullName = $targetUser['full_name'];
            if (!str_ends_with($newFullName, '(Removed)')) {
                $newFullName = $newFullName . ' (Removed)';
            }
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_active = 0, 
                    full_name = ?,
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newFullName, $userId]);
            
            $action = 'deactivated';
        } else {
            // No problems associated, safe to delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $action = 'deleted';
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "User '{$targetUser['full_name']}' has been successfully {$action}",
            'action' => $action,
            'user_name' => $targetUser['full_name']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("User removal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>