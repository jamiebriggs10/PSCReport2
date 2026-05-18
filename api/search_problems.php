<?php
/**
 * Live search endpoint — returns JSON list of problems matching the query.
 * Mirrors the visibility rules in index.php (admin vs. non-admin).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'auth required']);
    exit;
}

$user = getCurrentUser();
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$limit = (int)($_GET['limit'] ?? 8);
$limit = max(1, min($limit, 20));

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['query' => $q, 'results' => [], 'total' => 0]);
    exit;
}

try {
    $pdo = getDbConnection();

    $where = [];
    $params = [];

    if ($status === 'OPEN' || $status === 'RESOLVED') {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    // Term-based search (AND across whitespace-separated terms)
    $terms = preg_split('/\s+/', $q);
    foreach ($terms as $term) {
        if ($term === '') continue;
        $where[] = "(p.title LIKE ? OR p.details LIKE ? OR u.full_name LIKE ? OR pc.name LIKE ? OR p.urgency_tags LIKE ?)";
        $like = '%' . $term . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    // Visibility: non-admin can see public categories + their own admin-only items
    if (!isAdmin()) {
        $where[] = "(pc.admin_only = 0 OR pc.admin_only IS NULL OR p.reported_by = ?)";
        $params[] = $user['id'];
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "
        SELECT
            p.id,
            p.title,
            p.status,
            p.urgency_tags,
            p.created_at,
            u.full_name AS reporter_name,
            pc.name AS category_name,
            pc.color AS category_color
        FROM problems p
        LEFT JOIN users u ON p.reported_by = u.id
        LEFT JOIN problem_categories pc ON p.problem_category_id = pc.id
        {$whereClause}
        ORDER BY
            CASE WHEN p.title LIKE ? THEN 0 ELSE 1 END,
            p.status = 'OPEN' DESC,
            p.created_at DESC
        LIMIT {$limit}
    ";
    $params[] = $q . '%';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $r) {
        $urgency = parseUrgencyTags($r['urgency_tags']);
        $primaryUrgency = is_array($urgency) && !empty($urgency) ? (string)$urgency[0] : '';
        $results[] = [
            'id'             => (int)$r['id'],
            'title'          => $r['title'],
            'status'         => $r['status'],
            'reporter'       => $r['reporter_name'] ?: '',
            'category'       => $r['category_name'] ?: '',
            'category_color' => $r['category_color'] ?: '',
            'urgency'        => $primaryUrgency,
            'created_at'     => $r['created_at'],
            'relative_time'  => getRelativeTime($r['created_at']),
            'url'            => 'problems/view.php?id=' . (int)$r['id'],
        ];
    }

    echo json_encode([
        'query'   => $q,
        'results' => $results,
        'total'   => count($results),
    ]);
} catch (Throwable $e) {
    error_log('search_problems API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'search failed']);
}
