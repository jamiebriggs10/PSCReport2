<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

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

$action = $_POST['action'] ?? 'save';

try {
    $pdo = getDbConnection();
    
    if ($action === 'remove') {
        // Remove email recipient
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM email_notification_preferences WHERE email = ?");
        $stmt->execute([$email]);
        
        echo json_encode([
            'success' => true, 
            'message' => "Email recipient '{$email}' has been removed"
        ]);
        
    } elseif ($action === 'test') {
        // Test email functionality (from old API)
        $recipients = getNotificationRecipients($pdo);
        if (!$recipients) {
            echo json_encode(['success' => false, 'message' => 'No recipients configured.']);
            exit;
        }
        $dummy = [
            'id' => 0,
            'title' => 'Test Notification',
            'details' => 'This is a test email from the PSC Issues system.',
            'urgency_tags' => 'Safety-Critical',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $opts = ['bypassFilters' => true];
        $sent = sendProblemNotification($dummy, $pdo, $opts);
        echo json_encode([
            'success' => $sent,
            'message' => $sent ? 'Test email attempted (check inboxes).' : 'Failed to send test email',
            'detail' => $sent ? null : ($opts['__last_error'] ?? 'Unknown failure')
        ]);
        
    } else {
        // Save or update email preferences
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $editMode = filter_input(INPUT_POST, 'edit_mode', FILTER_VALIDATE_BOOLEAN);
        $problemCategories = $_POST['problem_categories'] ?? [];
        $urgencyLevels = $_POST['urgency_levels'] ?? [];
        
        if (!$email) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Validate problem categories (ensure they exist and are active)
        $validCategories = [];
        if (!empty($problemCategories)) {
            $placeholders = str_repeat('?,', count($problemCategories) - 1) . '?';
            $stmt = $pdo->prepare("SELECT id FROM problem_categories WHERE id IN ({$placeholders}) AND is_active = 1");
            $stmt->execute($problemCategories);
            $validCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Validate urgency levels (ensure they exist and are active)
        $validUrgencies = [];
        if (!empty($urgencyLevels)) {
            $placeholders = str_repeat('?,', count($urgencyLevels) - 1) . '?';
            $stmt = $pdo->prepare("SELECT name FROM urgency_levels WHERE name IN ({$placeholders}) AND is_active = 1");
            $stmt->execute($urgencyLevels);
            $validUrgencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Convert to JSON for storage
        $categoriesJson = json_encode($validCategories);
        $urgenciesJson = json_encode($validUrgencies);
        
        if ($editMode) {
            // Update existing email preferences
            $stmt = $pdo->prepare("
                UPDATE email_notification_preferences 
                SET problem_categories = ?, urgency_levels = ?, updated_at = NOW() 
                WHERE email = ?
            ");
            $stmt->execute([$categoriesJson, $urgenciesJson, $email]);
            
            if ($stmt->rowCount() === 0) {
                echo json_encode(['success' => false, 'message' => 'Email recipient not found']);
                exit;
            }
            
            $message = "Email preferences updated for '{$email}'";
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT email FROM email_notification_preferences WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email address already exists']);
                exit;
            }
            
            // Insert new email preferences
            $stmt = $pdo->prepare("
                INSERT INTO email_notification_preferences (email, problem_categories, urgency_levels) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $categoriesJson, $urgenciesJson]);
            
            $message = "Email recipient '{$email}' has been added";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'email' => $email,
            'categories_count' => count($validCategories),
            'urgencies_count' => count($validUrgencies)
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Email preferences API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
