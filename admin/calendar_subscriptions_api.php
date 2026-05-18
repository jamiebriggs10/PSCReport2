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

$action = $_POST['action'] ?? 'create';

try {
    $pdo = getDbConnection();
    
    // Ensure table exists
    createSubscriptionTableIfNotExists($pdo);
    
    if ($action === 'create') {
        handleCreateSubscription($pdo);
    } elseif ($action === 'list') {
        handleListSubscriptions($pdo);
    } elseif ($action === 'delete') {
        handleDeleteSubscription($pdo);
    } elseif ($action === 'toggle') {
        handleToggleSubscription($pdo);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Subscription API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function createSubscriptionTableIfNotExists($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS `calendar_subscriptions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `token_type` ENUM('admin', 'user', 'public') DEFAULT 'public',
            `user_id` INT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `permissions` TEXT NULL COMMENT 'JSON object with filter permissions',
            `created_by` INT NOT NULL,
            `expires_at` DATETIME NULL,
            `last_accessed` DATETIME NULL,
            `access_count` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            
            INDEX `idx_token` (`token`),
            INDEX `idx_active` (`is_active`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
}

function handleCreateSubscription($pdo) {
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW));
    $tokenType = filter_input(INPUT_POST, 'token_type', FILTER_UNSAFE_RAW);
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $permissions = $_POST['permissions'] ?? '{}';
    $expiresAt = filter_input(INPUT_POST, 'expires_at', FILTER_UNSAFE_RAW);
    
    // Validation
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Subscription name is required']);
        return;
    }
    
    if (!in_array($tokenType, ['admin', 'user', 'public'])) {
        $tokenType = 'public';
    }
    
    // Generate unique token
    $token = generateSecureToken();
    
    // Validate permissions JSON
    if (!json_decode($permissions)) {
        $permissions = '{}';
    }
    
    // Insert subscription
    $stmt = $pdo->prepare("
        INSERT INTO calendar_subscriptions 
        (token, token_type, user_id, name, description, permissions, created_by, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $token,
        $tokenType,
        $userId,
        $name,
        $description,
        $permissions,
        $_SESSION['user_id'],
        $expiresAt ?: null
    ]);
    
    $subscriptionId = $pdo->lastInsertId();
    
    // Generate URLs
    $baseUrl = getBaseUrl();
    $feedUrl = $baseUrl . '/calendar_feed.php?token=' . $token;
    $webcalUrl = str_replace(['http://', 'https://'], 'webcal://', $feedUrl);
    
    echo json_encode([
        'success' => true,
        'message' => "Calendar subscription '{$name}' created successfully",
        'subscription' => [
            'id' => $subscriptionId,
            'token' => $token,
            'name' => $name,
            'feed_url' => $feedUrl,
            'webcal_url' => $webcalUrl
        ]
    ]);
}

function handleListSubscriptions($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            cs.*,
            u.full_name as user_name,
            creator.full_name as created_by_name
        FROM calendar_subscriptions cs
        LEFT JOIN users u ON cs.user_id = u.id
        LEFT JOIN users creator ON cs.created_by = creator.id
        ORDER BY cs.created_at DESC
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add URLs to each subscription
    $baseUrl = getBaseUrl();
    foreach ($subscriptions as &$subscription) {
        $feedUrl = $baseUrl . '/calendar_feed.php?token=' . $subscription['token'];
        $subscription['feed_url'] = $feedUrl;
        $subscription['webcal_url'] = str_replace(['http://', 'https://'], 'webcal://', $feedUrl);
        $subscription['permissions'] = json_decode($subscription['permissions'] ?? '{}', true);
    }
    
    echo json_encode(['success' => true, 'subscriptions' => $subscriptions]);
}

function handleDeleteSubscription($pdo) {
    $subscriptionId = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
    
    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
        return;
    }
    
    // Get subscription details
    $stmt = $pdo->prepare("SELECT name FROM calendar_subscriptions WHERE id = ?");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        echo json_encode(['success' => false, 'message' => 'Subscription not found']);
        return;
    }
    
    // Delete subscription
    $stmt = $pdo->prepare("DELETE FROM calendar_subscriptions WHERE id = ?");
    $stmt->execute([$subscriptionId]);
    
    echo json_encode([
        'success' => true,
        'message' => "Calendar subscription '{$subscription['name']}' deleted successfully"
    ]);
}

function handleToggleSubscription($pdo) {
    $subscriptionId = filter_input(INPUT_POST, 'subscription_id', FILTER_VALIDATE_INT);
    
    if (!$subscriptionId) {
        echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
        return;
    }
    
    // Get current status
    $stmt = $pdo->prepare("SELECT name, is_active FROM calendar_subscriptions WHERE id = ?");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscription) {
        echo json_encode(['success' => false, 'message' => 'Subscription not found']);
        return;
    }
    
    // Toggle status
    $newStatus = $subscription['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE calendar_subscriptions SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $subscriptionId]);
    
    $statusText = $newStatus ? 'activated' : 'deactivated';
    echo json_encode([
        'success' => true,
        'message' => "Calendar subscription '{$subscription['name']}' {$statusText} successfully",
        'is_active' => $newStatus
    ]);
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}
?>