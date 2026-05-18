<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/upload.php';

header('Content-Type: application/json');

// Require admin access
requireAuth();
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    $pdo = getDbConnection();
    
    if ($action === 'list') {
        handleListEvents($pdo);
    } elseif ($action === 'save') {
        handleSaveEvent($pdo);
    } elseif ($action === 'delete') {
        handleDeleteEvent($pdo);
    } elseif ($action === 'get') {
        handleGetEvent($pdo);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Maintenance API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

function handleListEvents($pdo) {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    $query = "
        SELECT 
            e.*,
            u1.full_name as created_by_name,
            u2.full_name as assigned_to_name
        FROM maintenance_events e
        LEFT JOIN users u1 ON e.created_by = u1.id
        LEFT JOIN users u2 ON e.assigned_to = u2.id
        WHERE e.is_active = 1
    ";
    
    $params = [];
    
    if ($startDate && $endDate) {
        $query .= " AND DATE(e.start_datetime) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }
    
    $query .= " ORDER BY e.start_datetime ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process attachments JSON
    foreach ($events as &$event) {
        $event['attachments'] = json_decode($event['attachments'] ?? '[]', true) ?: [];
    }
    
    echo json_encode(['success' => true, 'events' => $events]);
}

function handleSaveEvent($pdo) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $editMode = filter_input(INPUT_POST, 'edit_mode', FILTER_VALIDATE_INT);
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW));
    $startDate = filter_input(INPUT_POST, 'start_date', FILTER_UNSAFE_RAW);
    $startTime = filter_input(INPUT_POST, 'start_time', FILTER_UNSAFE_RAW);
    $endDate = filter_input(INPUT_POST, 'end_date', FILTER_UNSAFE_RAW);
    $endTime = filter_input(INPUT_POST, 'end_time', FILTER_UNSAFE_RAW);
    $allDay = filter_input(INPUT_POST, 'all_day', FILTER_VALIDATE_INT) ? 1 : 0;
    $location = trim(filter_input(INPUT_POST, 'location', FILTER_UNSAFE_RAW));
    $priority = filter_input(INPUT_POST, 'priority', FILTER_UNSAFE_RAW);
    $assignedTo = filter_input(INPUT_POST, 'assigned_to', FILTER_VALIDATE_INT);
    $isRecurring = filter_input(INPUT_POST, 'is_recurring', FILTER_VALIDATE_INT) ? 1 : 0;
    $recurrenceType = filter_input(INPUT_POST, 'recurrence_type', FILTER_UNSAFE_RAW);
    $recurrenceInterval = filter_input(INPUT_POST, 'recurrence_interval', FILTER_VALIDATE_INT) ?: 1;
    $recurrenceEndDate = filter_input(INPUT_POST, 'recurrence_end_date', FILTER_UNSAFE_RAW);
    
    // Validation
    if (!$title || !$startDate) {
        echo json_encode(['success' => false, 'message' => 'Title and start date are required']);
        return;
    }
    
    if (!in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'])) {
        $priority = 'MEDIUM';
    }
    
    if ($isRecurring && !in_array($recurrenceType, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid recurrence type']);
        return;
    }
    
    // Build datetime strings
    $startDateTime = $startDate . ($allDay || !$startTime ? ' 00:00:00' : ' ' . $startTime . ':00');
    $endDateTime = null;
    
    if ($endDate) {
        $endDateTime = $endDate . ($allDay || !$endTime ? ' 23:59:59' : ' ' . $endTime . ':00');
    }
    
    // Handle file uploads
    $attachments = [];
    $existingAttachments = [];
    
    if ($editMode && $eventId) {
        // Get existing attachments
        $stmt = $pdo->prepare("SELECT attachments FROM maintenance_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $existingAttachments = json_decode($existing['attachments'] ?? '[]', true) ?: [];
        $attachments = $existingAttachments; // Start with existing files
    }
    
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['name'] as $key => $name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $uploadFile = [
                    'name' => $_FILES['attachments']['name'][$key],
                    'type' => $_FILES['attachments']['type'][$key],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                    'error' => $_FILES['attachments']['error'][$key],
                    'size' => $_FILES['attachments']['size'][$key]
                ];
                
                $result = handleFileUpload($uploadFile);
                if ($result['success']) {
                    $attachments[] = $result['filename'];
                } else {
                    echo json_encode(['success' => false, 'message' => 'File upload failed: ' . $result['message']]);
                    return;
                }
            }
        }
    }
    
    $attachmentsJson = json_encode($attachments);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        if ($editMode && $eventId) {
            // Update existing event
            $stmt = $pdo->prepare("
                UPDATE maintenance_events 
                SET title = ?, description = ?, start_datetime = ?, end_datetime = ?, 
                    all_day = ?, location = ?, priority = ?, assigned_to = ?,
                    is_recurring = ?, recurrence_type = ?, recurrence_interval = ?, 
                    recurrence_end_date = ?, attachments = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            
            $stmt->execute([
                $title, $description, $startDateTime, $endDateTime,
                $allDay, $location, $priority, $assignedTo ?: null,
                $isRecurring, $isRecurring ? $recurrenceType : null, 
                $isRecurring ? $recurrenceInterval : null,
                $isRecurring && $recurrenceEndDate ? $recurrenceEndDate : null,
                $attachmentsJson, $eventId, $_SESSION['user_id']
            ]);
            
            $message = "Event '{$title}' updated successfully";
            
        } else {
            // Create new event
            $stmt = $pdo->prepare("
                INSERT INTO maintenance_events 
                (title, description, start_datetime, end_datetime, all_day, location, 
                 priority, assigned_to, is_recurring, recurrence_type, recurrence_interval, 
                 recurrence_end_date, attachments, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $description, $startDateTime, $endDateTime,
                $allDay, $location, $priority, $assignedTo ?: null,
                $isRecurring, $isRecurring ? $recurrenceType : null,
                $isRecurring ? $recurrenceInterval : null,
                $isRecurring && $recurrenceEndDate ? $recurrenceEndDate : null,
                $attachmentsJson, $_SESSION['user_id']
            ]);
            
            $eventId = $pdo->lastInsertId();
            $message = "Event '{$title}' created successfully";
            
            // Handle recurring events
            if ($isRecurring) {
                createRecurringEvents($pdo, $eventId, $startDateTime, $endDateTime, $recurrenceType, $recurrenceInterval, $recurrenceEndDate);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'event_id' => $eventId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createRecurringEvents($pdo, $parentId, $startDateTime, $endDateTime, $type, $interval, $endDate) {
    $start = new DateTime($startDateTime);
    $end = $endDateTime ? new DateTime($endDateTime) : null;
    $endRecurrence = $endDate ? new DateTime($endDate) : null;
    
    $current = clone $start;
    $eventsCreated = 0;
    $maxEvents = 100; // Prevent infinite loops
    
    while ($eventsCreated < $maxEvents) {
        // Add interval based on type
        switch ($type) {
            case 'DAILY':
                $current->add(new DateInterval("P{$interval}D"));
                break;
            case 'WEEKLY':
                $current->add(new DateInterval("P{$interval}W"));
                break;
            case 'MONTHLY':
                $current->add(new DateInterval("P{$interval}M"));
                break;
            case 'YEARLY':
                $current->add(new DateInterval("P{$interval}Y"));
                break;
        }
        
        // Check if we've reached the end date
        if ($endRecurrence && $current > $endRecurrence) {
            break;
        }
        
        // Stop if we're more than 2 years in the future
        $twoYearsFromNow = new DateTime('+2 years');
        if ($current > $twoYearsFromNow) {
            break;
        }
        
        $newEnd = null;
        if ($end) {
            $newEnd = clone $end;
            $diff = $start->diff($current);
            $newEnd->add($diff);
        }
        
        // Get parent event data
        $stmt = $pdo->prepare("
            SELECT title, description, all_day, location, priority, assigned_to, created_by
            FROM maintenance_events WHERE id = ?
        ");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert recurring event
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_events 
            (title, description, start_datetime, end_datetime, all_day, location, 
             priority, assigned_to, parent_event_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $parent['title'],
            $parent['description'],
            $current->format('Y-m-d H:i:s'),
            $newEnd ? $newEnd->format('Y-m-d H:i:s') : null,
            $parent['all_day'],
            $parent['location'],
            $parent['priority'],
            $parent['assigned_to'],
            $parentId,
            $parent['created_by']
        ]);
        
        $eventsCreated++;
    }
}

function handleDeleteEvent($pdo) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    
    if (!$eventId) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        return;
    }
    
    // Get event details first
    $stmt = $pdo->prepare("
        SELECT title, attachments, created_by 
        FROM maintenance_events 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        return;
    }
    
    // Check permissions - only creator or admin can delete
    if ($event['created_by'] != $_SESSION['user_id'] && !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    // Soft delete the event
    $stmt = $pdo->prepare("UPDATE maintenance_events SET is_active = 0 WHERE id = ?");
    $stmt->execute([$eventId]);
    
    // Also soft delete any recurring child events
    $stmt = $pdo->prepare("UPDATE maintenance_events SET is_active = 0 WHERE parent_event_id = ?");
    $stmt->execute([$eventId]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Event '{$event['title']}' deleted successfully"
    ]);
}

function handleGetEvent($pdo) {
    $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
    
    if (!$eventId) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            u1.full_name as created_by_name,
            u2.full_name as assigned_to_name
        FROM maintenance_events e
        LEFT JOIN users u1 ON e.created_by = u1.id
        LEFT JOIN users u2 ON e.assigned_to = u2.id
        WHERE e.id = ? AND e.is_active = 1
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        return;
    }
    
    // Process attachments JSON
    $event['attachments'] = json_decode($event['attachments'] ?? '[]', true) ?: [];
    
    echo json_encode(['success' => true, 'event' => $event]);
}
?>