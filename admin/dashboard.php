<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/utils.php';

// Require admin access
requireAuth();
if (!isAdmin()) {
    header('Location: ' . getFullUrl());
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get basic statistics
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $openProblems = $pdo->query("SELECT COUNT(*) FROM problems WHERE status = 'OPEN'")->fetchColumn();
        $resolvedProblems = $pdo->query("SELECT COUNT(*) FROM problems WHERE status = 'RESOLVED'")->fetchColumn();
    
    // Get dynamic urgency levels and problem categories for email config
    $urgencyLevels = $pdo->query("SELECT * FROM urgency_levels WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
    $problemCategories = $pdo->query("SELECT * FROM problem_categories WHERE is_active = 1 ORDER BY display_order, name")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $totalProblems = $openProblems = 0;
    $urgencyLevels = [];
    $problemCategories = [];
}

$pageTitle = 'Admin Dashboard';
include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p class="page-description">System administration and user management</p>
        </div>
        
        <!-- Compact Stat Widgets -->
            <div class="admin-stat-row">
                <div class="mini-stat">
                    <div class="mini-stat-label">Open</div>
                    <div class="mini-stat-value text-danger"><?= number_format($openProblems) ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-label">Resolved</div>
                    <div class="mini-stat-value text-success"><?= number_format($resolvedProblems) ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-label">Active Users</div>
                    <div class="mini-stat-value"><?= number_format($totalUsers) ?></div>
                </div>
            </div>
        
        <!-- Panels -->
        <div class="admin-panels">
            <section class="panel">
                <header class="panel-header">
                    <div>
                        <h2>User Management</h2>
                        <p>Create & manage access</p>
                    </div>
                </header>
                <div class="panel-body">
                    <div class="button-row">
                        <a href="<?= getFullUrl('admin/users_create.php') ?>" class="btn btn-primary btn-sm">New User</a>
                        <a href="<?= getFullUrl('admin/users_list.php') ?>" class="btn btn-outline btn-sm">All Users</a>
                        <a href="<?= getFullUrl('admin/config.php') ?>" class="btn btn-secondary btn-sm">Configure System</a>
                    </div>
                </div>
            </section>
            <section class="panel">
                <header class="panel-header">
                    <div>
                        <h2>Maintenance Calendar</h2>
                        <p>Schedule & track maintenance events</p>
                    </div>
                </header>
                <div class="panel-body">
                    <?php
                    // Get maintenance events summary
                    try {
                        $stmt = $pdo->query("
                            SELECT 
                                COUNT(*) as total_events,
                                COUNT(CASE WHEN status = 'SCHEDULED' THEN 1 END) as scheduled_events,
                                COUNT(CASE WHEN status = 'IN_PROGRESS' THEN 1 END) as in_progress_events,
                                COUNT(CASE WHEN DATE(start_datetime) = CURDATE() THEN 1 END) as today_events
                            FROM maintenance_events 
                            WHERE is_active = 1 AND start_datetime >= CURDATE() - INTERVAL 30 DAY
                        ");
                        $maintenanceSummary = $stmt->fetch(PDO::FETCH_ASSOC);
                        $totalEvents = $maintenanceSummary['total_events'] ?? 0;
                        $scheduledEvents = $maintenanceSummary['scheduled_events'] ?? 0;
                        $todayEvents = $maintenanceSummary['today_events'] ?? 0;
                    } catch (Exception $e) {
                        $totalEvents = 0;
                        $scheduledEvents = 0;
                        $todayEvents = 0;
                    }
                    ?>
                    
                    <div class="maintenance-summary">
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <span class="stat-number"><?= $totalEvents ?></span>
                                <span class="stat-label">Total Events (30 days)</span>
                            </div>
                            <div class="summary-stat">
                                <span class="stat-number"><?= $scheduledEvents ?></span>
                                <span class="stat-label">Scheduled</span>
                            </div>
                            <div class="summary-stat">
                                <span class="stat-number"><?= $todayEvents ?></span>
                                <span class="stat-label">Today</span>
                            </div>
                        </div>
                        
                        <?php if ($totalEvents > 0): ?>
                            <p class="summary-description">
                                Active maintenance schedule with upcoming events and recurring tasks.
                            </p>
                        <?php else: ?>
                            <p class="summary-description text-muted">
                                No maintenance events scheduled. Start planning maintenance tasks.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="button-row">
                        <a href="<?= getFullUrl('admin/maintenance.php') ?>" class="btn btn-primary btn-sm">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                            </svg>
                            Maintenance Calendar
                        </a>
                    </div>
                    </div>
                </div>
            </section>
            <section class="panel">
                <header class="panel-header">
                    <div>
                        <h2>Email Notifications</h2>
                        <p>Manage recipients & preferences</p>
                    </div>
                </header>
                <div class="panel-body">
                    <?php
                    // Get email notification summary
                    try {
                        $stmt = $pdo->query("
                            SELECT COUNT(*) as recipient_count
                            FROM email_notification_preferences 
                            WHERE is_active = 1
                        ");
                        $notificationSummary = $stmt->fetch(PDO::FETCH_ASSOC);
                        $recipientCount = $notificationSummary['recipient_count'] ?? 0;
                    } catch (Exception $e) {
                        // Fall back to old system if new table doesn't exist
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM notification_recipients WHERE is_active = 1");
                            $recipientCount = $stmt->fetchColumn();
                        } catch (Exception $e2) {
                            $recipientCount = 0;
                        }
                    }
                    ?>
                    
                    <div class="notification-summary">
                        <div class="summary-stat">
                            <span class="stat-number"><?= $recipientCount ?></span>
                            <span class="stat-label">Email Recipients</span>
                        </div>
                        <?php if ($recipientCount > 0): ?>
                            <p class="summary-description">
                                Recipients configured with individual notification preferences for different problem types and urgency levels.
                            </p>
                        <?php else: ?>
                            <p class="summary-description text-muted">
                                No email recipients configured. Add recipients to enable automatic notifications.
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="button-row">
                        <a href="<?= getFullUrl('admin/notifications.php') ?>" class="btn btn-primary btn-sm">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                            </svg>
                            Manage Email Notifications
                        </a>
                        <?php if ($recipientCount > 0): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="sendTestEmail()">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                                Send Test Email
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>        
        <script>
        // Send test email function
        function sendTestEmail() {
            const btn = event.target.closest('button');
            if (!btn) return;
            
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Sending...';
            
            fetch('notifications_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test&csrf_token=<?= generateCSRFToken() ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Test email sent successfully! Check recipient inboxes.');
                } else {
                    alert('Failed to send test email: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred while sending test email.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
                });
    }
});
</script>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
<style>
/* Admin dashboard redesigned layout */
.admin-stat-row {display:flex;gap:.75rem;margin:1rem 0 1.25rem;flex-wrap:wrap;}
.mini-stat {flex:1;min-width:100px;background:#fff;border:1px solid #e2e5e9;border-radius:10px;padding:.6rem .75rem;display:flex;flex-direction:column;justify-content:center;box-shadow:0 1px 2px rgba(0,0,0,.04);} 
.mini-stat-label {font-size:.6rem;letter-spacing:.08em;font-weight:600;text-transform:uppercase;color:#6c757d;margin-bottom:2px;}
.mini-stat-value {font-size:1.1rem;font-weight:700;line-height:1;}
.mini-stat-value.text-danger {color:#dc3545;}
.mini-stat-value.text-success {color:#198754;}

/* Panel system */
.admin-panels {display:flex;flex-direction:column;gap:1.25rem;margin-bottom:2rem;}
.panel {background:#fff;border:1px solid #e2e5e9;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;}
.panel-header {padding:.85rem 1rem;border-bottom:1px solid #eceff2;display:flex;justify-content:space-between;align-items:center;}
.panel-header h2 {font-size:1rem;margin:0;font-weight:600;}
.panel-header p {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.panel-body {padding:1rem;display:flex;flex-direction:column;gap:1rem;}

.button-row {display:flex;gap:.5rem;flex-wrap:wrap;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;}
.btn svg {margin-right:.35rem;vertical-align:-.1em;}

/* Notification summary styling */
.notification-summary {margin-bottom:1rem;}
.summary-stat {display:flex;align-items:baseline;gap:.5rem;margin-bottom:.5rem;}
.stat-number {font-size:1.5rem;font-weight:700;color:#007bff;}
.stat-label {font-size:.8rem;color:#6c757d;font-weight:600;}
.summary-description {font-size:.8rem;color:#6c757d;margin:0;line-height:1.4;}
.summary-description.text-muted {color:#adb5bd;}

/* Maintenance summary styling */
.maintenance-summary {margin-bottom:1rem;}
.summary-stats {display:flex;gap:1rem;margin-bottom:.5rem;}
.maintenance-summary .summary-stat {display:flex;flex-direction:column;align-items:center;text-align:center;margin-bottom:0;}
.maintenance-summary .stat-number {margin-bottom:.25rem;}
.maintenance-summary .stat-label {font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;}

/* Mobile optimizations */
@media (max-width:600px){
    .admin-stat-row {margin-top:.5rem;}
    .panel-body {padding:.85rem;}
    .btn.btn-sm {width:auto;}
    .button-row {flex-direction:column;}
    .button-row .btn {width:100%;}
}
</style>