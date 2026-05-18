<?php
/**
 * Problems List API Endpoint
 * Returns JSON data for problems with filtering
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get filters from query parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'urgency' => $_GET['urgency'] ?? '',
    'reporter' => $_GET['reporter'] ?? '',
    'sort' => $_GET['sort'] ?? 'newest',
    'limit' => min((int)($_GET['limit'] ?? 50), 100) // Max 100 results
];

try {
    $pdo = getDbConnection();
    
    // Build WHERE clause for filters
    $filterData = buildProblemFilters($filters);
    $whereClause = !empty($filterData['where']) ? 'WHERE ' . implode(' AND ', $filterData['where']) : '';
    
    // Get sort order
    $orderBy = buildProblemSort($filters['sort']);
    
    // Get problems with user information
    $sql = "
        SELECT 
            p.id,
            p.title,
            p.details,
            p.status,
            p.created_at,
            p.updated_at,
            p.urgency_tags,
            p.image_urls,
            u.full_name as reporter_name,
            latest_resolution.action_at as resolved_at,
            latest_resolution.action_by_name as resolver_name
        FROM problems p
        LEFT JOIN users u ON p.reported_by = u.id
        LEFT JOIN (
            SELECT 
                r1.problem_id,
                r1.action_at,
                u.full_name as action_by_name
            FROM resolutions r1
            LEFT JOIN users u ON r1.action_by = u.id
            WHERE r1.action = 'RESOLVE' 
            AND r1.action_at = (
                SELECT MAX(r2.action_at) 
                FROM resolutions r2 
                WHERE r2.problem_id = r1.problem_id 
                AND r2.action = 'RESOLVE'
            )
        ) latest_resolution ON p.id = latest_resolution.problem_id
        {$whereClause}
        ORDER BY {$orderBy}
        LIMIT ?
    ";
    
    $params = array_merge($filterData['params'], [$filters['limit']]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $problems = $stmt->fetchAll();
    
    // Process problems for JSON response
    $response = [];
    foreach ($problems as $problem) {
        $urgencyTags = parseUrgencyTags($problem['urgency_tags']);
        $attachments = getProblemAttachments($problem['id'], $problem['image_urls']);
        $thumbnail = null;
        if (!empty($attachments)) {
            foreach ($attachments as $att) {
                if (!empty($att['is_image']) && $att['is_image']) { $thumbnail = $att['url']; break; }
            }
        }
        
        $response[] = [
            'id' => (int)$problem['id'],
            'title' => $problem['title'],
            'details' => $problem['details'],
            'status' => $problem['status'],
            'created_at' => $problem['created_at'],
            'updated_at' => $problem['updated_at'],
            'resolved_at' => $problem['resolved_at'],
            'urgency_tags' => $urgencyTags,
            'reporter_name' => $problem['reporter_name'],
            'resolver_name' => $problem['resolver_name'],
            'attachments_count' => count($attachments),
            'thumbnail' => $thumbnail,
            'relative_time' => getRelativeTime($problem['created_at']),
            'formatted_date' => formatDate($problem['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $response,
        'count' => count($response),
        'filters' => $filters
    ]);
    
} catch (PDOException $e) {
    error_log("Problems API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>