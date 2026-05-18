<?php
require_once 'includes/auth.php';
require_once 'includes/utils.php';

header('Content-Type: application/json');

// Ensure user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDbConnection();
$isAdmin = isAdmin();
$currentUserId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'list':
            handleListEvents();
            break;
        case 'save':
            handleSaveEvent();
            break;
        case 'delete':
            handleDeleteEvent();
            break;
        case 'resolve':
            handleResolveEvent();
            break;
        default:
            throw new Exception('Invalid action');
    }
    } catch (Exception $e) {
        error_log("Maintenance API Error: " . $e->getMessage());
        error_log("Maintenance API Error - POST data: " . print_r($_POST, true));
        error_log("Maintenance API Error - FILES data: " . print_r($_FILES, true));
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }function handleListEvents() {
    global $pdo, $isAdmin, $currentUserId;
    
    try {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        // Get events within date range
        $sql = "
            SELECT 
                e.*,
                u1.full_name as assigned_to_name,
                u2.full_name as created_by_name,
                u3.full_name as resolved_by_name
            FROM maintenance_events e
            LEFT JOIN users u1 ON e.assigned_to = u1.id
            LEFT JOIN users u2 ON e.created_by = u2.id
            LEFT JOIN users u3 ON e.resolved_by = u3.id
            WHERE (
                DATE(e.start_datetime) BETWEEN ? AND ?
                OR DATE(e.end_datetime) BETWEEN ? AND ?
                OR (DATE(e.start_datetime) <= ? AND DATE(e.end_datetime) >= ?)
            )
            ORDER BY e.start_datetime ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add permission flags to each event
        foreach ($events as &$event) {
            $event['can_edit'] = $isAdmin || ($event['created_by'] == $currentUserId);
            $event['can_delete'] = $isAdmin || ($event['created_by'] == $currentUserId);
        }
        
        echo json_encode([
            'success' => true,
            'events' => $events
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to load events: ' . $e->getMessage());
    }
}

function handleSaveEvent() {
    global $pdo, $isAdmin, $currentUserId;
    
    try {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $eventId = $_POST['event_id'] ?? null;
        $editMode = ($_POST['edit_mode'] ?? '0') === '1';
        
        // Check permissions for editing
        if ($editMode && $eventId) {
            $stmt = $pdo->prepare("SELECT created_by FROM maintenance_events WHERE id = ?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                throw new Exception('Event not found');
            }
            
            if (!$isAdmin && $event['created_by'] != $currentUserId) {
                throw new Exception('You can only edit events that you created');
            }
        }
        
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        
        if (empty($title)) {
            throw new Exception('Event title is required');
        }
        
        if (empty($startDate)) {
            throw new Exception('Start date is required');
        }
        
        // Process form data
        $data = [
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'start_date' => $startDate,
            'start_time' => !empty($_POST['start_time']) ? trim($_POST['start_time']) : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'end_time' => !empty($_POST['end_time']) ? trim($_POST['end_time']) : null,
            'all_day' => (($_POST['all_day'] ?? '0') === '1') ? 1 : 0,
            'priority' => $_POST['priority'] ?? 'MEDIUM',
            'status' => 'SCHEDULED', // Default status for new events
            'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
            'location' => trim($_POST['location'] ?? ''),
            'is_recurring' => (($_POST['is_recurring'] ?? '0') === '1') ? 1 : 0,
            'recurrence_type' => !empty($_POST['recurrence_type']) ? $_POST['recurrence_type'] : null,
            'recurrence_interval' => !empty($_POST['recurrence_interval']) ? (int)$_POST['recurrence_interval'] : null,
            'recurrence_end_date' => !empty($_POST['recurrence_end_date']) ? $_POST['recurrence_end_date'] : null
        ];
        
        // Create datetime strings
        $startDateTime = createDateTime($data['start_date'], $data['start_time'], $data['all_day']);
        $endDateTime = null;
        
        if (!empty($data['end_date'])) {
            $endDateTime = createDateTime($data['end_date'], $data['end_time'], $data['all_day']);
        }
        
                // Handle file uploads using the standard system
        $attachments = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            require_once 'includes/upload.php';
            try {
                $uploadResult = handleMaintenanceFileUploads($_FILES['attachments']);
                $attachments = $uploadResult['files'];
                
                if (!empty($uploadResult['errors'])) {
                    error_log('Maintenance file upload errors: ' . implode(', ', $uploadResult['errors']));
                    // Continue with saving the event even if some files failed
                }
            } catch (Exception $uploadError) {
                error_log('Maintenance file upload error: ' . $uploadError->getMessage());
                // Continue with saving the event even if uploads fail
            }
        }
        
        if ($editMode && $eventId) {
            // Update existing event
            $sql = "
                UPDATE maintenance_events SET
                    title = ?, description = ?, start_datetime = ?, end_datetime = ?,
                    all_day = ?, priority = ?, assigned_to = ?, location = ?,
                    is_recurring = ?, recurrence_type = ?, recurrence_interval = ?,
                    recurrence_end_date = ?, attachments = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['title'], $data['description'], $startDateTime, $endDateTime,
                $data['all_day'], $data['priority'], $data['assigned_to'], $data['location'],
                $data['is_recurring'], $data['recurrence_type'], $data['recurrence_interval'],
                $data['recurrence_end_date'], json_encode($attachments), $eventId
            ]);
            
            $message = 'Event updated successfully!';
            
        } else {
            // Create new event
            $sql = "
                INSERT INTO maintenance_events (
                    title, description, start_datetime, end_datetime, all_day,
                    priority, status, assigned_to, location, is_recurring,
                    recurrence_type, recurrence_interval, recurrence_end_date,
                    attachments, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['title'], $data['description'], $startDateTime, $endDateTime,
                $data['all_day'], $data['priority'], $data['status'], $data['assigned_to'],
                $data['location'], $data['is_recurring'], $data['recurrence_type'],
                $data['recurrence_interval'], $data['recurrence_end_date'],
                json_encode($attachments), $currentUserId
            ]);
            
            $eventId = $pdo->lastInsertId();
            $message = 'Event created successfully!';
            
            // Generate recurring events if needed
            if ($data['is_recurring']) {
                generateRecurringEvents($pdo, $eventId, $data);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'event_id' => $eventId
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to save event: ' . $e->getMessage());
    }
}

function handleDeleteEvent() {
    global $pdo, $isAdmin, $currentUserId;
    
    try {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $eventId = $_POST['event_id'] ?? null;
        
        if (!$eventId) {
            throw new Exception('Event ID is required');
        }
        
        // Check if event exists and get creator
        $stmt = $pdo->prepare("SELECT created_by, title FROM maintenance_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            throw new Exception('Event not found');
        }
        
        // Check permissions
        if (!$isAdmin && $event['created_by'] != $currentUserId) {
            throw new Exception('You can only delete events that you created');
        }
        
        // Delete the event
        $stmt = $pdo->prepare("DELETE FROM maintenance_events WHERE id = ?");
        $stmt->execute([$eventId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Event deleted successfully!'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to delete event: ' . $e->getMessage());
    }
}

function handleResolveEvent() {
    global $pdo, $isAdmin, $currentUserId;
    
    try {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        $eventId = $_POST['event_id'] ?? null;
        
        if (!$eventId) {
            throw new Exception('Event ID is required');
        }
        
        // Check if event exists and get details
        $stmt = $pdo->prepare("SELECT created_by, assigned_to, title, status, resolved_at FROM maintenance_events WHERE id = ?");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            throw new Exception('Event not found');
        }
        
        // Check if already completed
        if ($event['resolved_at']) {
            throw new Exception('Task is already complete');
        }
        
        // Check permissions - creator, assignee, or admin can complete
        $canResolve = $isAdmin || 
                     ($event['created_by'] == $currentUserId) || 
                     ($event['assigned_to'] == $currentUserId);
        
        if (!$canResolve) {
            throw new Exception('You do not have permission to complete this task');
        }
        
        // Process completion data
        $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
        
        // Handle file uploads for completion using the standard system
        $resolutionAttachments = [];
        if (isset($_FILES['resolution_files']) && !empty($_FILES['resolution_files']['name'][0])) {
            require_once 'includes/upload.php';
            try {
                $uploadResult = handleMaintenanceFileUploads($_FILES['resolution_files']);
                $resolutionAttachments = $uploadResult['files'];
                
                if (!empty($uploadResult['errors'])) {
                    error_log('Maintenance completion file upload errors: ' . implode(', ', $uploadResult['errors']));
                    // Continue with completing the task even if some files failed
                }
            } catch (Exception $uploadError) {
                error_log('Maintenance completion file upload error: ' . $uploadError->getMessage());
                // Continue with completing the task even if uploads fail
            }
        }
        
        // Update the event with completion data
        $sql = "
            UPDATE maintenance_events SET
                status = 'COMPLETED',
                resolved_at = NOW(),
                resolved_by = ?,
                resolution_notes = ?,
                resolution_attachments = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $currentUserId,
            $resolutionNotes,
            json_encode($resolutionAttachments),
            $eventId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task marked as complete successfully!'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to complete task: ' . $e->getMessage());
    }
}

function createDateTime($date, $time, $allDay) {
    if ($allDay || empty($time) || $time === '') {
        return $date . ' 00:00:00';
    }
    // Ensure time is in HH:MM format
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $date . ' ' . $time . ':00';
    }
    // If time format is invalid, default to midnight
    return $date . ' 00:00:00';
}

function generateRecurringEvents($pdo, $parentId, $data) {
    if (!$data['is_recurring'] || !$data['recurrence_type']) {
        return;
    }
    
    $startDate = new DateTime($data['start_date']);
    $endLimit = $data['recurrence_end_date'] ? new DateTime($data['recurrence_end_date']) : null;
    $interval = (int)($data['recurrence_interval'] ?? 1);
    $maxEvents = 50; // Limit recurring events
    $generatedCount = 0;
    
    $currentDate = clone $startDate;
    
    while ($generatedCount < $maxEvents) {
        // Add interval based on recurrence type
        switch ($data['recurrence_type']) {
            case 'DAILY':
                $currentDate->add(new DateInterval("P{$interval}D"));
                break;
            case 'WEEKLY':
                $currentDate->add(new DateInterval("P" . ($interval * 7) . "D"));
                break;
            case 'MONTHLY':
                $currentDate->add(new DateInterval("P{$interval}M"));
                break;
            case 'YEARLY':
                $currentDate->add(new DateInterval("P{$interval}Y"));
                break;
            default:
                return;
        }
        
        // Check if we've reached the end date
        if ($endLimit && $currentDate > $endLimit) {
            break;
        }
        
        // Calculate end datetime for recurring event
        $recurringStartDateTime = createDateTime(
            $currentDate->format('Y-m-d'), 
            $data['start_time'], 
            $data['all_day']
        );
        
        $recurringEndDateTime = null;
        if (!empty($data['end_date'])) {
            $endDate = clone $currentDate;
            if (!empty($data['end_date']) && $data['end_date'] !== $data['start_date']) {
                // Calculate the difference and apply it
                $originalStart = new DateTime($data['start_date']);
                $originalEnd = new DateTime($data['end_date']);
                $dateDiff = $originalStart->diff($originalEnd);
                $endDate->add($dateDiff);
            }
            
            $recurringEndDateTime = createDateTime(
                $endDate->format('Y-m-d'),
                $data['end_time'],
                $data['all_day']
            );
        }
        
        // Insert recurring event
        $sql = "
            INSERT INTO maintenance_events (
                title, description, start_datetime, end_datetime, all_day,
                priority, status, assigned_to, location, is_recurring,
                recurrence_type, recurrence_interval, recurrence_end_date,
                parent_event_id, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, NULL, ?, ?, NOW(), NOW())
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['title'], $data['description'], $recurringStartDateTime, $recurringEndDateTime,
            $data['all_day'], $data['priority'], $data['status'], $data['assigned_to'],
            $data['location'], $parentId, $_SESSION['user_id']
        ]);
        
        $generatedCount++;
    }
}
?>