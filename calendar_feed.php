<?php
/**
 * Calendar Feed Generator - iCal/ICS Format
 * Generates live calendar subscription feeds for maintenance events
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

// Set headers for iCal format
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="maintenance-calendar.ics"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Get parameters
$token = $_GET['token'] ?? '';
$format = $_GET['format'] ?? 'ics';
$userId = $_GET['user'] ?? null;
$priority = $_GET['priority'] ?? null;
$daysAhead = (int)($_GET['days'] ?? 365); // Default to 1 year ahead

if (!$token) {
    http_response_code(400);
    echo "Error: Missing subscription token";
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Handle special public token
    if ($token === 'public') {
        $permissions = ['type' => 'public', 'user_id' => null, 'permissions' => []];
    } else {
        // Validate token and get permissions
        $permissions = validateSubscriptionToken($pdo, $token);
        if (!$permissions) {
            http_response_code(403);
            echo "Error: Invalid or expired subscription token";
            exit;
        }
    }
    
    // Build query based on permissions and filters
    $query = "
        SELECT 
            e.*,
            u1.full_name as created_by_name,
            u2.full_name as assigned_to_name
        FROM maintenance_events e
        LEFT JOIN users u1 ON e.created_by = u1.id
        LEFT JOIN users u2 ON e.assigned_to = u2.id
        WHERE e.is_active = 1
        AND e.start_datetime >= CURDATE()
        AND e.start_datetime <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ";
    
    $params = [$daysAhead];
    
    // Apply filters based on permissions
    if ($permissions['type'] === 'user' && $permissions['user_id']) {
        $query .= " AND (e.assigned_to = ? OR e.created_by = ?)";
        $params[] = $permissions['user_id'];
        $params[] = $permissions['user_id'];
    }
    
    // Apply additional filters
    if ($userId && $permissions['type'] === 'admin') {
        $query .= " AND e.assigned_to = ?";
        $params[] = $userId;
    }
    
    if ($priority) {
        $query .= " AND e.priority = ?";
        $params[] = strtoupper($priority);
    }
    
    $query .= " ORDER BY e.start_datetime ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate iCal content
    echo generateICalendar($events, $permissions);
    
} catch (Exception $e) {
    error_log("Calendar feed error: " . $e->getMessage());
    http_response_code(500);
    echo "Error: Unable to generate calendar feed";
}

function validateSubscriptionToken($pdo, $token) {
    try {
        $stmt = $pdo->prepare("
            SELECT token_type, user_id, permissions, expires_at, is_active
            FROM calendar_subscriptions 
            WHERE token = ? AND is_active = 1
        ");
        $stmt->execute([$token]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            return false;
        }
        
        // Check expiration
        if ($subscription['expires_at'] && strtotime($subscription['expires_at']) < time()) {
            return false;
        }
        
        return [
            'type' => $subscription['token_type'],
            'user_id' => $subscription['user_id'],
            'permissions' => json_decode($subscription['permissions'] ?? '{}', true)
        ];
        
    } catch (Exception $e) {
        // If table doesn't exist yet, create it
        createSubscriptionTable($pdo);
        return false;
    }
}

function createSubscriptionTable($pdo) {
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

function generateICalendar($events, $permissions) {
    $output = [];
    
    // iCal header
    $output[] = "BEGIN:VCALENDAR";
    $output[] = "VERSION:2.0";
    $output[] = "PRODID:-//PSC Issues//Maintenance Calendar//EN";
    $output[] = "CALSCALE:GREGORIAN";
    $output[] = "METHOD:PUBLISH";
    $output[] = "X-WR-CALNAME:PSC Maintenance Schedule";
    $output[] = "X-WR-CALDESC:Scheduled maintenance events for Presswick Sailing Club";
    $output[] = "X-WR-TIMEZONE:Europe/London";
    
    // Add timezone definition
    $output[] = "BEGIN:VTIMEZONE";
    $output[] = "TZID:Europe/London";
    $output[] = "BEGIN:DAYLIGHT";
    $output[] = "TZOFFSETFROM:+0000";
    $output[] = "TZOFFSETTO:+0100";
    $output[] = "TZNAME:BST";
    $output[] = "DTSTART:19700329T010000";
    $output[] = "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU";
    $output[] = "END:DAYLIGHT";
    $output[] = "BEGIN:STANDARD";
    $output[] = "TZOFFSETFROM:+0100";
    $output[] = "TZOFFSETTO:+0000";
    $output[] = "TZNAME:GMT";
    $output[] = "DTSTART:19701025T020000";
    $output[] = "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU";
    $output[] = "END:STANDARD";
    $output[] = "END:VTIMEZONE";
    
    // Add events
    foreach ($events as $event) {
        $output = array_merge($output, generateEventLines($event));
    }
    
    // iCal footer
    $output[] = "END:VCALENDAR";
    
    return implode("\r\n", $output);
}

function generateEventLines($event) {
    $lines = [];
    
    $lines[] = "BEGIN:VEVENT";
    
    // Unique identifier
    $uid = "maintenance-" . $event['id'] . "@" . $_SERVER['HTTP_HOST'];
    $lines[] = "UID:" . $uid;
    
    // Creation and modification times
    $now = gmdate('Ymd\THis\Z');
    $lines[] = "DTSTAMP:" . $now;
    $lines[] = "CREATED:" . formatICalDateTime($event['created_at']);
    $lines[] = "LAST-MODIFIED:" . formatICalDateTime($event['updated_at']);
    
    // Event times
    $startDateTime = new DateTime($event['start_datetime']);
    if ($event['all_day']) {
        $lines[] = "DTSTART;VALUE=DATE:" . $startDateTime->format('Ymd');
        if ($event['end_datetime']) {
            $endDateTime = new DateTime($event['end_datetime']);
            $endDateTime->add(new DateInterval('P1D')); // Add one day for all-day events
            $lines[] = "DTEND;VALUE=DATE:" . $endDateTime->format('Ymd');
        }
    } else {
        $lines[] = "DTSTART;TZID=Europe/London:" . $startDateTime->format('Ymd\THis');
        if ($event['end_datetime']) {
            $endDateTime = new DateTime($event['end_datetime']);
            $lines[] = "DTEND;TZID=Europe/London:" . $endDateTime->format('Ymd\THis');
        }
    }
    
    // Event details
    $lines[] = "SUMMARY:" . escapeICalText($event['title']);
    
    if ($event['description']) {
        $description = "Priority: " . $event['priority'] . "\n";
        $description .= "Status: " . $event['status'] . "\n";
        if ($event['assigned_to_name']) {
            $description .= "Assigned to: " . $event['assigned_to_name'] . "\n";
        }
        $description .= "\n" . $event['description'];
        $lines[] = "DESCRIPTION:" . escapeICalText($description);
    }
    
    if ($event['location']) {
        $lines[] = "LOCATION:" . escapeICalText($event['location']);
    }
    
    // Priority mapping
    $priorityMap = [
        'LOW' => 9,
        'MEDIUM' => 5,
        'HIGH' => 3,
        'URGENT' => 1
    ];
    $priority = $priorityMap[$event['priority']] ?? 5;
    $lines[] = "PRIORITY:" . $priority;
    
    // Status mapping
    $statusMap = [
        'SCHEDULED' => 'TENTATIVE',
        'IN_PROGRESS' => 'CONFIRMED',
        'COMPLETED' => 'CONFIRMED',
        'CANCELLED' => 'CANCELLED'
    ];
    $status = $statusMap[$event['status']] ?? 'TENTATIVE';
    $lines[] = "STATUS:" . $status;
    
    // Categories
    $categories = ["MAINTENANCE"];
    if ($event['priority'] === 'URGENT') {
        $categories[] = "URGENT";
    }
    $lines[] = "CATEGORIES:" . implode(',', $categories);
    
    // Attachments (if any)
    if ($event['attachments']) {
        $attachments = json_decode($event['attachments'], true);
        if ($attachments) {
            $baseUrl = getBaseUrl();
            foreach ($attachments as $attachment) {
                $attachmentUrl = $baseUrl . '/uploads/' . $attachment;
                $lines[] = "ATTACH:" . $attachmentUrl;
            }
        }
    }
    
    // Recurrence rules (if recurring)
    if ($event['is_recurring'] && $event['recurrence_type'] && !$event['parent_event_id']) {
        $rrule = generateRecurrenceRule($event);
        if ($rrule) {
            $lines[] = "RRULE:" . $rrule;
        }
    }
    
    $lines[] = "END:VEVENT";
    
    return $lines;
}

function generateRecurrenceRule($event) {
    $freq = $event['recurrence_type'];
    $interval = $event['recurrence_interval'] ?: 1;
    
    $rrule = "FREQ=" . $freq;
    
    if ($interval > 1) {
        $rrule .= ";INTERVAL=" . $interval;
    }
    
    if ($event['recurrence_end_date']) {
        $endDate = new DateTime($event['recurrence_end_date']);
        $rrule .= ";UNTIL=" . $endDate->format('Ymd\THis\Z');
    }
    
    return $rrule;
}

function formatICalDateTime($datetime) {
    return gmdate('Ymd\THis\Z', strtotime($datetime));
}

function escapeICalText($text) {
    // Escape special characters for iCal format
    $text = str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $text);
    return $text;
}
?>