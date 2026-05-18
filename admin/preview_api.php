<?php
require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../config/database.php';
requireAdmin();
header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT id, title, status, urgency_tags, created_at 
        FROM problems 
        WHERE status = 'OPEN' 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $problems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [];
    foreach ($problems as $problem) {
        $urgencyTags = parseUrgencyTags($problem['urgency_tags']);
        $response[] = [
            'id' => (int)$problem['id'],
            'title' => $problem['title'],
            'status' => $problem['status'],
            'urgency_tags' => $urgencyTags,
            'created_at' => $problem['created_at'],
            'relative_time' => getRelativeTime($problem['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $response,
        'count' => count($response)
    ]);
    
} catch (PDOException $e) {
    error_log("Preview API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}