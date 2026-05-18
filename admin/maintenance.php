<?php
require_once '../includes/auth.php';
require_once '../includes/utils.php';
requireAdmin();

$pdo = getDbConnection();
$errors = [];
$messages = [];

// Get users for assignment dropdown
try {
    $stmt = $pdo->query("
        SELECT id, full_name, username, role
        FROM users 
        WHERE is_active = 1 
        ORDER BY role DESC, full_name ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
    $users = [];
}

$pageTitle = 'Maintenance Calendar';
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
.calendar-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.calendar-nav button {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
}

.calendar-nav button:hover {
    background: #5a6268;
}

.calendar-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: #dee2e6;
}

.calendar-day-header {
    background: #e9ecef;
    padding: 1rem;
    text-align: center;
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.calendar-day {
    background: white;
    min-height: 120px;
    padding: 0.5rem;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s;
}

.calendar-day:hover {
    background: #f8f9fa;
}

.calendar-day.other-month {
    background: #f8f9fa;
    color: #adb5bd;
}

.calendar-day.selected {
    background: #e3f2fd;
}

.day-number {
    font-weight: 600;
    font-size: 0.9rem;
    color: #495057;
    margin-bottom: 0.25rem;
}

.calendar-day.other-month .day-number {
    color: #adb5bd;
}

.calendar-event {
    background: #007bff;
    color: white;
    padding: 0.25rem 0.5rem;
    margin: 0.125rem 0;
    border-radius: 3px;
    font-size: 0.75rem;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: background-color 0.2s;
}

.calendar-event:hover {
    background: #0056b3;
}

.calendar-event.priority-low { background: #28a745; }
.calendar-event.priority-medium { background: #ffc107; color: #212529; }
.calendar-event.priority-high { background: #fd7e14; }
.calendar-event.priority-urgent { background: #dc3545; }

.calendar-event.status-completed { 
    background: #6c757d; 
    text-decoration: line-through;
}

.add-event-btn {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 60px;
    height: 60px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
    transition: all 0.3s;
    z-index: 1000;
}

.add-event-btn:hover {
    background: #0056b3;
    transform: scale(1.1);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.25rem;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.file-list {
    margin-top: 1rem;
}

.file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 0.5rem;
}

.file-remove {
    background: #dc3545;
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    cursor: pointer;
    font-size: 0.75rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.alert {
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-radius: 4px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.view-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.view-btn {
    padding: 0.375rem 0.75rem;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    color: #495057;
    font-size: 0.875rem;
}

.view-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.view-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.subscription-info {
    max-height: 70vh;
    overflow-y: auto;
}

.subscription-options {
    margin: 1.5rem 0;
}

.subscription-option {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.subscription-option h4 {
    margin: 0 0 0.5rem 0;
    color: #495057;
    font-size: 1rem;
}

.subscription-option p {
    margin: 0 0 1rem 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.url-input-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.url-input-group input {
    flex: 1;
    font-family: monospace;
    font-size: 0.8rem;
}

.setup-instructions {
    margin-top: 2rem;
    border-top: 1px solid #dee2e6;
    padding-top: 1.5rem;
}

.setup-instructions h4 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.instruction-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.instruction-tab {
    padding: 0.5rem 1rem;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: background-color 0.2s;
}

.instruction-tab:hover {
    background: #dee2e6;
}

.instruction-tab.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.instruction-content {
    display: none;
    background: #f8f9fa;
    border-radius: 4px;
    padding: 1rem;
}

.instruction-content.active {
    display: block;
}

.instruction-content ol {
    margin: 0;
    padding-left: 1.5rem;
}

.instruction-content li {
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.instruction-content a {
    color: #007bff;
    text-decoration: none;
}

.instruction-content a:hover {
    text-decoration: underline;
}

.subscription-notes {
    margin-top: 1.5rem;
    background: #e8f4f8;
    border: 1px solid #bee5eb;
    border-radius: 4px;
    padding: 1rem;
}

.subscription-notes h5 {
    margin: 0 0 0.5rem 0;
    color: #0c5460;
    font-size: 0.9rem;
}

.subscription-notes ul {
    margin: 0;
    padding-left: 1.5rem;
}

.subscription-notes li {
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    color: #0c5460;
    line-height: 1.4;
}

@media (max-width: 768px) {
    .calendar-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .calendar-day {
        min-height: 80px;
        font-size: 0.8rem;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Maintenance Calendar</h1>
                <p class="page-description">Schedule and manage maintenance events and tasks</p>
            </div>
            <div class="header-actions">
                <div class="view-controls">
                    <a href="#" class="view-btn active" data-view="month">Month</a>
                    <a href="#" class="view-btn" data-view="week">Week</a>
                    <a href="#" class="view-btn" data-view="day">Day</a>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="showSubscriptionModal()">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                    </svg>
                    Subscribe to Calendar
                </button>
                <a href="dashboard.php" class="btn btn-outline btn-sm">
                    ← Dashboard
                </a>
            </div>
        </div>

        <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><?= h($m) ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= h($e) ?></div>
        <?php endforeach; ?>

        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <button type="button" onclick="navigateCalendar('prev')" title="Previous Month">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" onclick="navigateCalendar('today')" title="Today">
                        Today
                    </button>
                    <button type="button" onclick="navigateCalendar('next')" title="Next Month">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <h2 class="calendar-title" id="calendarTitle">
                    <!-- Will be populated by JavaScript -->
                </h2>
            </div>
            
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar will be populated by JavaScript -->
            </div>
        </div>

        <!-- Floating Add Button -->
        <button type="button" class="add-event-btn" onclick="showAddEventModal()" title="Add Maintenance Event">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</main>

<!-- Add/Edit Event Modal -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Maintenance Event</h3>
            <button type="button" class="modal-close" onclick="closeEventModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="eventForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" id="eventId" name="event_id" value="">
                <input type="hidden" id="editMode" name="edit_mode" value="0">

                <div class="form-group">
                    <label for="eventTitle" class="form-label">Event Title *</label>
                    <input 
                        type="text" 
                        id="eventTitle" 
                        name="title" 
                        class="form-control" 
                        placeholder="e.g., Engine maintenance, Dock repair"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="eventDescription" class="form-label">Description</label>
                    <textarea 
                        id="eventDescription" 
                        name="description" 
                        class="form-control" 
                        rows="3"
                        placeholder="Additional details about the maintenance event..."
                    ></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate" class="form-label">Start Date *</label>
                        <input 
                            type="date" 
                            id="startDate" 
                            name="start_date" 
                            class="form-control" 
                            required
                        >
                    </div>
                    <div class="form-group">
                        <label for="startTime" class="form-label">Start Time</label>
                        <input 
                            type="time" 
                            id="startTime" 
                            name="start_time" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="endDate" class="form-label">End Date</label>
                        <input 
                            type="date" 
                            id="endDate" 
                            name="end_date" 
                            class="form-control"
                        >
                    </div>
                    <div class="form-group">
                        <label for="endTime" class="form-label">End Time</label>
                        <input 
                            type="time" 
                            id="endTime" 
                            name="end_time" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="allDay" name="all_day" value="1">
                        <label for="allDay" class="form-label" style="margin-bottom: 0;">All Day Event</label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="priority" class="form-label">Priority</label>
                        <select id="priority" name="priority" class="form-control">
                            <option value="LOW">Low</option>
                            <option value="MEDIUM" selected>Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assignedTo" class="form-label">Assigned To</label>
                        <select id="assignedTo" name="assigned_to" class="form-control">
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= h($user['full_name']) ?> (<?= h($user['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="location" class="form-label">Location</label>
                    <input 
                        type="text" 
                        id="location" 
                        name="location" 
                        class="form-control" 
                        placeholder="e.g., Main dock, Engine room, Boat #123"
                    >
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="isRecurring" name="is_recurring" value="1">
                        <label for="isRecurring" class="form-label" style="margin-bottom: 0;">Recurring Event</label>
                    </div>
                </div>

                <div id="recurrenceOptions" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="recurrenceType" class="form-label">Repeat</label>
                            <select id="recurrenceType" name="recurrence_type" class="form-control">
                                <option value="DAILY">Daily</option>
                                <option value="WEEKLY">Weekly</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="YEARLY">Yearly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="recurrenceInterval" class="form-label">Every</label>
                            <input 
                                type="number" 
                                id="recurrenceInterval" 
                                name="recurrence_interval" 
                                class="form-control" 
                                min="1" 
                                value="1"
                            >
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="recurrenceEndDate" class="form-label">End Repeat (Optional)</label>
                        <input 
                            type="date" 
                            id="recurrenceEndDate" 
                            name="recurrence_end_date" 
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Attachments</label>
                    <div class="file-upload" onclick="document.getElementById('attachments').click()">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #6c757d; margin-bottom: 0.5rem;"></i>
                            <p>Click to upload files or drag and drop</p>
                            <p style="font-size: 0.8rem; color: #6c757d;">Supported: Images, PDFs, Documents (Max 5MB each)</p>
                        </div>
                    </div>
                    <input 
                        type="file" 
                        id="attachments" 
                        name="attachments[]" 
                        multiple 
                        style="display: none;"
                        accept="image/*,.pdf,.doc,.docx,.txt"
                    >
                    <div class="file-preview"></div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="saveEventBtn" onclick="saveEvent()">Save Event</button>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div id="eventDetailsModal" class="modal">
    <div class="modal-content event-details-enhanced">
        <div class="modal-header event-details-header">
            <h3 id="eventDetailsTitle">Event Details</h3>
            <button type="button" class="modal-close" onclick="closeEventDetailsModal()">&times;</button>
        </div>
        <div class="modal-body event-details-body" id="eventDetailsContent">
            <!-- Event details will be populated here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEventDetailsModal()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" id="editEventBtn" onclick="editEvent()">Edit</button>
        </div>
    </div>
</div>

<!-- Calendar Subscription Modal -->
<div id="subscriptionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Subscribe to Maintenance Calendar</h3>
            <button type="button" class="modal-close" onclick="closeSubscriptionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="subscription-info">
                <p>Subscribe to the maintenance calendar to automatically receive updates in your personal calendar app. 
                Your calendar will sync with new events, changes, and cancellations automatically.</p>
                
                <div class="subscription-options">
                    <div class="subscription-option">
                        <h4>Quick Setup (Recommended)</h4>
                        <p>Click the button below to automatically open your default calendar app and add the subscription:</p>
                        <button type="button" class="btn btn-primary" onclick="subscribeToCalendar()" id="quickSubscribeBtn">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                            </svg>
                            Add to My Calendar
                        </button>
                    </div>
                    
                    <div class="subscription-option">
                        <h4>Manual Setup</h4>
                        <p>Copy this URL and add it manually in your calendar app:</p>
                        <div class="url-input-group">
                            <input type="text" id="subscriptionUrl" class="form-control" readonly>
                            <button type="button" class="btn btn-secondary" onclick="copySubscriptionUrl()">Copy</button>
                        </div>
                    </div>
                </div>
                
                <div class="setup-instructions">
                    <h4>Setup Instructions by Platform</h4>
                    
                    <div class="instruction-tabs">
                        <button type="button" class="instruction-tab active" onclick="showInstructions('google')">Google Calendar</button>
                        <button type="button" class="instruction-tab" onclick="showInstructions('outlook')">Outlook</button>
                        <button type="button" class="instruction-tab" onclick="showInstructions('apple')">Apple Calendar</button>
                        <button type="button" class="instruction-tab" onclick="showInstructions('other')">Other Apps</button>
                    </div>
                    
                    <div id="instructions-google" class="instruction-content active">
                        <ol>
                            <li>Open <a href="https://calendar.google.com" target="_blank">Google Calendar</a></li>
                            <li>Click the "+" next to "Other calendars" on the left</li>
                            <li>Select "From URL"</li>
                            <li>Paste the subscription URL above</li>
                            <li>Click "Add calendar"</li>
                        </ol>
                    </div>
                    
                    <div id="instructions-outlook" class="instruction-content">
                        <ol>
                            <li>Open Outlook Calendar</li>
                            <li>Go to "Add calendar" or "Subscribe to calendar"</li>
                            <li>Choose "From internet"</li>
                            <li>Paste the subscription URL</li>
                            <li>Give it a name and click "Import"</li>
                        </ol>
                    </div>
                    
                    <div id="instructions-apple" class="instruction-content">
                        <ol>
                            <li>Open Calendar app on Mac/iPhone</li>
                            <li>Go to File > New Calendar Subscription (Mac) or Settings > Calendar > Add Account (iPhone)</li>
                            <li>Paste the subscription URL</li>
                            <li>Click "Subscribe" and customize settings</li>
                        </ol>
                    </div>
                    
                    <div id="instructions-other" class="instruction-content">
                        <ol>
                            <li>Look for "Add calendar", "Subscribe", or "Import calendar" option</li>
                            <li>Choose "From URL" or "iCal subscription"</li>
                            <li>Paste the subscription URL</li>
                            <li>Save or import the calendar</li>
                        </ol>
                        <p><em>Most modern calendar applications support iCal subscriptions.</em></p>
                    </div>
                </div>
                
                <div class="subscription-notes">
                    <h5>Important Notes</h5>
                    <ul>
                        <li>Your calendar will automatically update when maintenance events are added or changed</li>
                        <li>Updates may take a few hours depending on your calendar app's sync frequency</li>
                        <li>This subscription is read-only - you cannot edit events from your calendar app</li>
                        <li>Contact your administrator if you need a personalized calendar feed</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeSubscriptionModal()">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/app.js"></script>
<script>
// Calendar functionality will be added in the next step
let currentDate = new Date();
let currentView = 'month';
let selectedDate = null;
let currentEvent = null;
let events = [];

// Initialize calendar on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCalendar();
    loadEvents();
    setupEventListeners();
    
    // Check for quick add parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('add') === '1') {
        setTimeout(() => {
            showAddEventModal();
            // Remove the parameter from URL
            window.history.replaceState({}, '', window.location.pathname);
        }, 500);
    }
    
    // Check for subscribe parameter
    if (urlParams.get('subscribe') === '1') {
        setTimeout(() => {
            showSubscriptionModal();
            // Remove the parameter from URL
            window.history.replaceState({}, '', window.location.pathname);
        }, 500);
    }
});

function setupEventListeners() {
    // Recurring event checkbox
    document.getElementById('isRecurring').addEventListener('change', function() {
        document.getElementById('recurrenceOptions').style.display = 
            this.checked ? 'block' : 'none';
    });

    // All day checkbox
    document.getElementById('allDay').addEventListener('change', function() {
        const timeInputs = ['startTime', 'endTime'];
        timeInputs.forEach(id => {
            document.getElementById(id).disabled = this.checked;
            if (this.checked) {
                document.getElementById(id).value = '';
            }
        });
    });

    // View controls
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentView = this.dataset.view;
            loadCalendar();
        });
    });
}

function loadCalendar() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    if (currentView === 'month') {
        loadMonthView();
    } else if (currentView === 'week') {
        loadWeekView();
    } else {
        loadDayView();
    }
}

function loadMonthView() {
    const title = document.getElementById('calendarTitle');
    const grid = document.getElementById('calendarGrid');
    
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    title.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
    
    // Clear grid
    grid.innerHTML = '';
    
    // Add day headers
    dayNames.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Get first day of month and number of days
    const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    const firstDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDayOfWeek; i++) {
        const prevDate = new Date(firstDay);
        prevDate.setDate(prevDate.getDate() - (firstDayOfWeek - i));
        const cell = createDayCell(prevDate, true);
        grid.appendChild(cell);
    }
    
    // Add days of current month
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
        const cell = createDayCell(date, false);
        grid.appendChild(cell);
    }
    
    // Add empty cells for days after month ends
    const totalCells = grid.children.length - 7; // Subtract headers
    const remainingCells = 42 - totalCells; // 6 weeks * 7 days
    for (let i = 1; i <= remainingCells; i++) {
        const nextDate = new Date(lastDay);
        nextDate.setDate(nextDate.getDate() + i);
        const cell = createDayCell(nextDate, true);
        grid.appendChild(cell);
    }
}

function createDayCell(date, isOtherMonth) {
    const cell = document.createElement('div');
    cell.className = `calendar-day${isOtherMonth ? ' other-month' : ''}`;
    cell.dataset.date = formatDate(date);
    
    const dayNumber = document.createElement('div');
    dayNumber.className = 'day-number';
    dayNumber.textContent = date.getDate();
    cell.appendChild(dayNumber);
    
    // Add events for this day
    const dayEvents = getEventsForDate(date);
    dayEvents.forEach(event => {
        const eventEl = document.createElement('div');
        eventEl.className = `calendar-event priority-${event.priority.toLowerCase()} status-${event.status.toLowerCase()}`;
        eventEl.textContent = event.title;
        eventEl.onclick = (e) => {
            e.stopPropagation();
            showEventDetails(event);
        };
        cell.appendChild(eventEl);
    });
    
    cell.onclick = () => selectDate(date);
    
    return cell;
}

function getEventsForDate(date) {
    const dateStr = formatDate(date);
    return events.filter(event => {
        const eventDate = formatDate(new Date(event.start_datetime));
        return eventDate === dateStr;
    });
}

function formatDate(date) {
    return date.getFullYear() + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
           String(date.getDate()).padStart(2, '0');
}

function selectDate(date) {
    // Remove previous selection
    document.querySelectorAll('.calendar-day.selected').forEach(cell => {
        cell.classList.remove('selected');
    });
    
    // Add selection to clicked cell
    const cell = document.querySelector(`[data-date="${formatDate(date)}"]`);
    if (cell) {
        cell.classList.add('selected');
        selectedDate = date;
    }
}

function navigateCalendar(direction) {
    if (direction === 'prev') {
        currentDate.setMonth(currentDate.getMonth() - 1);
    } else if (direction === 'next') {
        currentDate.setMonth(currentDate.getMonth() + 1);
    } else if (direction === 'today') {
        currentDate = new Date();
    }
    
    loadCalendar();
    loadEvents(); // Reload events for new month
}

function showAddEventModal(date = null) {
    currentEvent = null;
    document.getElementById('modalTitle').textContent = 'Add Maintenance Event';
    document.getElementById('editMode').value = '0';
    document.getElementById('eventForm').reset();
    document.querySelector('.file-preview').innerHTML = '';
    document.getElementById('recurrenceOptions').style.display = 'none';
    document.getElementById('saveEventBtn').textContent = 'Save Event';
    
    // Set default date if provided or selected
    if (date || selectedDate) {
        const defaultDate = date || selectedDate;
        document.getElementById('startDate').value = formatDate(defaultDate);
    }
    
    document.getElementById('eventModal').style.display = 'flex';
    
    // Focus on title
    setTimeout(() => {
        document.getElementById('eventTitle').focus();
    }, 100);
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function showEventDetails(event) {
    try {
        currentEvent = event;
        document.getElementById('eventDetailsTitle').textContent = event.title;
        
        const content = document.getElementById('eventDetailsContent');
        const startDate = new Date(event.start_datetime);
        const endDate = event.end_datetime ? new Date(event.end_datetime) : null;
        
        // Parse attachments
        let attachments = [];
        if (event.attachments) {
            try {
                attachments = typeof event.attachments === 'string' ? JSON.parse(event.attachments) : event.attachments;
                console.log('Parsed attachments:', attachments);
            } catch (e) {
                console.error('Error parsing attachments:', e);
                console.log('Raw attachments data:', event.attachments);
            }
        }
        
        // Parse resolution attachments
        let resolutionAttachments = [];
        if (event.resolution_attachments) {
            try {
                resolutionAttachments = typeof event.resolution_attachments === 'string' ? JSON.parse(event.resolution_attachments) : event.resolution_attachments;
                console.log('Parsed resolution attachments:', resolutionAttachments);
            } catch (e) {
                console.error('Error parsing resolution attachments:', e);
                console.log('Raw resolution attachments data:', event.resolution_attachments);
            }
        }
    
    content.innerHTML = `
        <div class="event-info-card">
            <div class="event-meta-grid">
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    <span class="meta-label">Start:</span>
                    <span class="meta-value">${formatDateTime(startDate)}</span>
                </div>
                ${endDate ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                    <span class="meta-label">End:</span>
                    <span class="meta-value">${formatDateTime(endDate)}</span>
                </div>
                ` : ''}
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                    <span class="meta-label">Priority:</span>
                    <span class="priority-badge priority-${event.priority.toLowerCase()}">${event.priority}</span>
                </div>
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    <span class="meta-label">Status:</span>
                    <span class="status-badge status-${event.status.toLowerCase()}">${event.status}</span>
                </div>
                ${event.location ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span class="meta-label">Location:</span>
                    <span class="meta-value">${event.location}</span>
                </div>
                ` : ''}
                ${event.assigned_to_name ? `
                <div class="meta-item">
                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span class="meta-label">Assigned:</span>
                    <span class="meta-value">${event.assigned_to_name}</span>
                </div>
                ` : ''}
            </div>
            
            ${event.description ? `
            <div class="event-description">
                <h4>
                    <svg style="width: 18px; height: 18px; vertical-align: -3px; margin-right: 6px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14,2 14,8 20,8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10,9 9,9 8,9"></polyline>
                    </svg>
                    Description
                </h4>
                <p class="description-text">${event.description}</p>
            </div>
            ` : ''}
            
            ${attachments && attachments.length > 0 ? `
            <div class="event-attachments">
                <h4 class="attachments-header">
                    <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    Attachments (${attachments.length})
                </h4>
                <div class="attachments-grid">
                    ${attachments.map(file => {
                        const extension = file.extension || '';
                        const isImage = file.is_image || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension.toLowerCase());
                        const originalName = file.original_name || file.filename || 'Unknown file';
                        const filename = file.filename || '';
                        
                        return `
                        <a href="../uploads/${filename}" target="_blank" class="attachment-item" title="${originalName}">
                            <div class="attachment-preview">
                                ${isImage ? 
                                    `<img src="../uploads/${filename}" alt="${originalName}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                     <div class="attachment-icon" style="display: none;"><i class="fas fa-image"></i></div>` :
                                    `<div class="attachment-icon">${getFileIcon(extension)}</div>`
                                }
                            </div>
                            <div class="attachment-name">${originalName}</div>
                        </a>
                        `;
                    }).join('')}
                </div>
            </div>
            ` : ''}
        </div>
        
        ${event.resolved_at ? `
        <div class="resolution-section">
            <div class="resolution-header">
                <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
                Task Completed
            </div>
            <div class="resolution-content">
                <div class="resolution-meta">
                    <div class="meta-item">
                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="meta-label">Completed by:</span>
                        <span class="meta-value">${event.resolved_by_name || 'Unknown'}</span>
                    </div>
                    <div class="meta-item">
                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12,6 12,12 16,14"></polyline>
                        </svg>
                        <span class="meta-label">Completed on:</span>
                        <span class="meta-value">${formatDateTime(new Date(event.resolved_at))}</span>
                    </div>
                </div>
                
                ${event.resolution_notes ? `
                <div class="resolution-notes">
                    <h5>Completion Notes:</h5>
                    <p style="margin: 0; line-height: 1.5;">${event.resolution_notes}</p>
                </div>
                ` : ''}
                
                ${resolutionAttachments && resolutionAttachments.length > 0 ? `
                <div style="margin-top: 1rem;">
                    <h5 style="margin: 0 0 1rem 0; color: #155724; font-size: 0.9rem; font-weight: 600;">
                        Completion Photos/Files (${resolutionAttachments.length})
                    </h5>
                    <div class="attachments-grid">
                        ${resolutionAttachments.map(file => {
                            const extension = file.extension || '';
                            const isImage = file.is_image || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension.toLowerCase());
                            const originalName = file.original_name || file.filename || 'Unknown file';
                            const filename = file.filename || '';
                            
                            return `
                            <a href="../uploads/${filename}" target="_blank" class="attachment-item" title="${originalName}">
                                <div class="attachment-preview">
                                    ${isImage ? 
                                        `<img src="../uploads/${filename}" alt="${originalName}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                         <div class="attachment-icon" style="display: none;"><i class="fas fa-image"></i></div>` :
                                        `<div class="attachment-icon">${getFileIcon(extension)}</div>`
                                    }
                                </div>
                                <div class="attachment-name">${originalName}</div>
                            </a>
                            `;
                        }).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
    `;
    
    document.getElementById('eventDetailsModal').style.display = 'flex';
    
    } catch (error) {
        console.error('Error in showEventDetails:', error);
        console.log('Event data:', event);
        alert('Error displaying event details. Check console for details.');
    }
}

function closeEventDetailsModal() {
    document.getElementById('eventDetailsModal').style.display = 'none';
}

function editEvent() {
    if (!currentEvent) return;
    
    closeEventDetailsModal();
    
    // Populate form with event data
    document.getElementById('modalTitle').textContent = 'Edit Maintenance Event';
    document.getElementById('editMode').value = '1';
    document.getElementById('eventId').value = currentEvent.id;
    document.getElementById('eventTitle').value = currentEvent.title;
    document.getElementById('eventDescription').value = currentEvent.description || '';
    
    const startDate = new Date(currentEvent.start_datetime);
    document.getElementById('startDate').value = formatDate(startDate);
    document.getElementById('startTime').value = formatTime(startDate);
    
    if (currentEvent.end_datetime) {
        const endDate = new Date(currentEvent.end_datetime);
        document.getElementById('endDate').value = formatDate(endDate);
        document.getElementById('endTime').value = formatTime(endDate);
    }
    
    document.getElementById('allDay').checked = currentEvent.all_day == 1;
    document.getElementById('priority').value = currentEvent.priority;
    document.getElementById('assignedTo').value = currentEvent.assigned_to || '';
    document.getElementById('location').value = currentEvent.location || '';
    
    document.getElementById('isRecurring').checked = currentEvent.is_recurring == 1;
    if (currentEvent.is_recurring) {
        document.getElementById('recurrenceOptions').style.display = 'block';
        document.getElementById('recurrenceType').value = currentEvent.recurrence_type || 'WEEKLY';
        document.getElementById('recurrenceInterval').value = currentEvent.recurrence_interval || 1;
        document.getElementById('recurrenceEndDate').value = currentEvent.recurrence_end_date || '';
    }
    
    document.getElementById('saveEventBtn').textContent = 'Update Event';
    document.getElementById('eventModal').style.display = 'flex';
}

function getFileIcon(extension) {
    const ext = extension?.toLowerCase();
    switch (ext) {
        case 'pdf':  return '<i class="fas fa-file-pdf"></i>';
        case 'doc':
        case 'docx': return '<i class="fas fa-file-word"></i>';
        case 'xls':
        case 'xlsx': return '<i class="fas fa-file-excel"></i>';
        case 'ppt':
        case 'pptx': return '<i class="fas fa-file-powerpoint"></i>';
        case 'zip':
        case 'rar':
        case '7z':   return '<i class="fas fa-file-archive"></i>';
        case 'mp4':
        case 'avi':
        case 'mov':  return '<i class="fas fa-file-video"></i>';
        case 'mp3':
        case 'wav':  return '<i class="fas fa-file-audio"></i>';
        case 'txt':  return '<i class="fas fa-file-alt"></i>';
        default:     return '<i class="fas fa-file"></i>';
    }
}

function formatDateTime(date) {
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function formatTime(date) {
    return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
}


function saveEvent() {
    const form = document.getElementById('eventForm');
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    const saveBtn = document.getElementById('saveEventBtn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    fetch('maintenance_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEventModal();
            loadEvents();
            alert(data.message || 'Event saved successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to save event'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the event');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = document.getElementById('editMode').value === '1' ? 'Update Event' : 'Save Event';
    });
}

function loadEvents() {
    // Get the current month's date range
    const startOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    const endOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    
    const params = new URLSearchParams({
        action: 'list',
        start_date: formatDate(startOfMonth),
        end_date: formatDate(endOfMonth)
    });
    
    fetch('maintenance_api.php?' + params)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            events = data.events || [];
            loadCalendar(); // Refresh calendar with events
        } else {
            console.error('Failed to load events:', data.message);
            events = []; // Set empty events array
            loadCalendar(); // Still refresh calendar
        }
    })
    .catch(error => {
        console.error('Error loading events:', error);
        events = []; // Set empty events array on error
        loadCalendar(); // Still refresh calendar
    });
}

// Placeholder functions for week and day views
function loadWeekView() {
    // TODO: Implement week view
    document.getElementById('calendarTitle').textContent = 'Week View (Coming Soon)';
    document.getElementById('calendarGrid').innerHTML = '<div style="padding: 2rem; text-align: center;">Week view will be implemented in a future update.</div>';
}

function loadDayView() {
    // TODO: Implement day view
    document.getElementById('calendarTitle').textContent = 'Day View (Coming Soon)';
    document.getElementById('calendarGrid').innerHTML = '<div style="padding: 2rem; text-align: center;">Day view will be implemented in a future update.</div>';
}

function showSubscriptionModal() {
    // Generate or get subscription URL
    generateSubscriptionUrl().then(url => {
        document.getElementById('subscriptionUrl').value = url;
        document.getElementById('subscriptionModal').style.display = 'flex';
    }).catch(error => {
        console.error('Error generating subscription URL:', error);
        alert('Error: Could not generate subscription URL');
    });
}

function closeSubscriptionModal() {
    document.getElementById('subscriptionModal').style.display = 'none';
}

function generateSubscriptionUrl() {
    return new Promise((resolve, reject) => {
        // For now, create a public subscription token
        const formData = new FormData();
        formData.append('action', 'create');
        formData.append('name', 'Public Maintenance Calendar');
        formData.append('description', 'Public access to maintenance calendar events');
        formData.append('token_type', 'public');
        formData.append('permissions', '{}');
        formData.append('csrf_token', '<?= generateCSRFToken() ?>');
        
        fetch('calendar_subscriptions_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resolve(data.subscription.webcal_url);
            } else {
                // Try to get existing public subscription
                getExistingSubscriptionUrl().then(resolve).catch(reject);
            }
        })
        .catch(error => {
            // Fallback: try to get existing subscription
            getExistingSubscriptionUrl().then(resolve).catch(reject);
        });
    });
}

function getExistingSubscriptionUrl() {
    return new Promise((resolve, reject) => {
        // This would normally fetch existing public subscriptions
        // For now, create a simple public URL
        const baseUrl = window.location.origin + window.location.pathname.replace('maintenance.php', '');
        const publicUrl = baseUrl + '../calendar_feed.php?token=public';
        const webcalUrl = publicUrl.replace(/^https?:\/\//, 'webcal://');
        resolve(webcalUrl);
    });
}

function subscribeToCalendar() {
    const url = document.getElementById('subscriptionUrl').value;
    if (!url) {
        alert('Subscription URL not available');
        return;
    }
    
    // Try to open the webcal URL
    window.open(url, '_blank');
    
    // Also show a confirmation message
    setTimeout(() => {
        if (confirm('Did your calendar app open? If not, you can copy the URL and add it manually to your calendar application.')) {
            closeSubscriptionModal();
        }
    }, 2000);
}

function copySubscriptionUrl() {
    const urlInput = document.getElementById('subscriptionUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Show feedback
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.style.background = '#28a745';
        
        setTimeout(() => {
            button.textContent = originalText;
            button.style.background = '';
        }, 2000);
        
    } catch (err) {
        alert('Could not copy URL. Please select and copy manually.');
    }
}

function showInstructions(platform) {
    // Hide all instruction content
    document.querySelectorAll('.instruction-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Hide all tab active states
    document.querySelectorAll('.instruction-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected content and activate tab
    document.getElementById('instructions-' + platform).classList.add('active');
    event.target.classList.add('active');
}

// Enhanced subscription modal functions
function showCalendarInstructions(type) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.instruction-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    event.target.classList.add('active');
    document.getElementById('instructions-' + type).classList.add('active');
}

function subscribeToCalendar() {
    const url = document.getElementById('subscriptionUrl').value;
    if (!url) {
        alert('Subscription URL not available. Please try again.');
        return;
    }
    
    // Try to open the webcal URL
    window.open(url, '_blank');
    
    // Show a confirmation message
    setTimeout(() => {
        if (confirm('Did your calendar app open successfully? If not, you can copy the URL and add it manually using the instructions above.')) {
            closeSubscriptionModal();
        }
    }, 2000);
}

function copySubscriptionUrl() {
    const urlInput = document.getElementById('subscriptionUrl');
    const copyBtn = document.getElementById('copyBtn');
    
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        
        const originalContent = copyBtn.innerHTML;
        copyBtn.innerHTML = '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';
        copyBtn.style.background = '#28a745';
        copyBtn.style.borderColor = '#28a745';
        
        setTimeout(() => {
            copyBtn.innerHTML = originalContent;
            copyBtn.style.background = '';
            copyBtn.style.borderColor = '';
        }, 2000);
        
    } catch (err) {
        alert('Could not copy URL. Please select and copy manually.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>