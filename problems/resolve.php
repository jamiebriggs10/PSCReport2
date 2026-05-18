<?php
/**
 * Resolve Problem Endpoint
 * Presswick Sailing Club Issue Reporting System
 */

require_once '../includes/auth.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . getFullUrl());
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Invalid request.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . getFullUrl());
    exit;
}

$problemId = (int)($_POST['problem_id'] ?? 0);
$user = getCurrentUser();

if ($problemId <= 0) {
    $_SESSION['flash_message'] = 'Invalid problem ID.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . getFullUrl());
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get problem details to check permissions
    $stmt = $pdo->prepare("
        SELECT id, title, status, reported_by 
        FROM problems 
        WHERE id = ?
    ");
    $stmt->execute([$problemId]);
    $problem = $stmt->fetch();
    
    if (!$problem) {
        $_SESSION['flash_message'] = 'Problem not found.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . getFullUrl());
        exit;
    }
    
    if ($problem['status'] === 'RESOLVED') {
        $_SESSION['flash_message'] = 'Problem is already resolved.';
        $_SESSION['flash_type'] = 'info';
        header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
        exit;
    }
    
    // Check permissions - admin or original reporter
    $canResolve = isAdmin() || ($problem['reported_by'] == $user['id']);
    
    if (!$canResolve) {
        $_SESSION['flash_message'] = 'You do not have permission to resolve this problem.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
        exit;
    }
    
    // Resolve the problem
    $stmt = $pdo->prepare("
        UPDATE problems 
        SET status = 'RESOLVED', resolved_by = ?, resolved_at = NOW(), updated_at = NOW() 
        WHERE id = ? AND status = 'OPEN'
    ");
    
    if ($stmt->execute([$user['id'], $problemId])) {
        $_SESSION['flash_message'] = 'Problem marked as resolved successfully.';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to resolve problem.';
        $_SESSION['flash_type'] = 'danger';
    }
    
} catch (PDOException $e) {
    error_log("Problem resolve error: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Database error occurred while resolving problem.';
    $_SESSION['flash_type'] = 'danger';
}

// Redirect back to problem view
header("Location: " . getFullUrl("problems/view.php?id={$problemId}"));
exit;
?>