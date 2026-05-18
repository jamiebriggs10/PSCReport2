<?php
/**
 * Users List API Endpoint (Admin Only)
 * Returns JSON data for user management
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Require admin authentication
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get all users with basic stats
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.full_name,
            u.username,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at,
            u.last_login_at,
            u.must_change_password,
            COUNT(p.id) as problems_reported,
            COUNT(CASE WHEN p.status = 'OPEN' THEN 1 END) as open_problems,
            COUNT(CASE WHEN p.status = 'RESOLVED' THEN 1 END) as resolved_problems
        FROM users u
        LEFT JOIN problems p ON u.id = p.reported_by
        GROUP BY u.id
        ORDER BY u.full_name
    ");
    
    $users = $stmt->fetchAll();
    
    // Process users for JSON response
    $response = [];
    foreach ($users as $user) {
        $response[] = [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active'],
            'must_change_password' => (bool)$user['must_change_password'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'last_login_at' => $user['last_login_at'],
            'last_login_relative' => $user['last_login_at'] ? getRelativeTime($user['last_login_at']) : 'Never',
            'problems_reported' => (int)$user['problems_reported'],
            'open_problems' => (int)$user['open_problems'],
            'resolved_problems' => (int)$user['resolved_problems'],
            'can_reset_password' => $user['role'] === 'USER'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $response,
        'count' => count($response)
    ]);
    
} catch (PDOException $e) {
    error_log("Users API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>