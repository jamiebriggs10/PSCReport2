<?php
require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../includes/notifications.php';
requireAdmin();

$pdo = getDbConnection();
$errors = [];
$messages = [];

// Get current email preferences
try {
    $stmt = $pdo->query("
        SELECT email, problem_categories, urgency_levels, is_active, created_at, updated_at
        FROM email_notification_preferences 
        WHERE is_active = 1 
        ORDER BY email ASC
    ");
    $emailPreferences = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch email preferences: " . $e->getMessage());
    $emailPreferences = [];
}

// Get available problem categories and urgency levels
try {
    $stmt = $pdo->query("SELECT id, name, color FROM problem_categories WHERE is_active = 1 ORDER BY display_order, name");
    $problemCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT id, name, color FROM urgency_levels WHERE is_active = 1 ORDER BY display_order, name");
    $urgencyLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch categories/urgency levels: " . $e->getMessage());
    $problemCategories = [];
    $urgencyLevels = [];
}

$pageTitle = 'Email Notification Settings';
include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Email Notification Settings</h1>
                <p class="page-description">Manage email recipients and their notification preferences</p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn btn-primary btn-sm" onclick="showAddEmailModal()">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
                    </svg>
                    Add Email Recipient
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

        <?php if (empty($emailPreferences)): ?>
            <section class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM13 17h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                            </svg>
                        </div>
                        <div class="empty-text">No Email Recipients Configured</div>
                        <p class="empty-description">Add email recipients to start receiving automatic notifications when problems are reported.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="showAddEmailModal()">
                            Add First Email Recipient
                        </button>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="panel">
                <header class="panel-header">
                    <div>
                        <h2>Email Recipients (<?= count($emailPreferences) ?>)</h2>
                        <p>Manage who receives notifications and for which problem types</p>
                    </div>
                </header>
                <div class="panel-body">
                    <div class="email-recipients-container">
                        <?php foreach ($emailPreferences as $pref): ?>
                            <?php
                            $categories = json_decode($pref['problem_categories'] ?? '[]', true) ?: [];
                            $urgencies = json_decode($pref['urgency_levels'] ?? '[]', true) ?: [];
                            ?>
                            <div class="email-card">
                                <div class="email-info">
                                    <div class="email-address">
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <?= h($pref['email']) ?>
                                    </div>
                                    <div class="preferences-summary">
                                        <div class="preference-section">
                                            <span class="section-label">Problem Types:</span>
                                            <div class="badges">
                                                <?php if (empty($categories)): ?>
                                                    <span class="badge badge-warning">None selected</span>
                                                <?php else: ?>
                                                    <?php foreach ($problemCategories as $cat): ?>
                                                        <?php if (in_array($cat['id'], $categories)): ?>
                                                            <span class="badge" style="background-color: <?= h($cat['color']) ?>; color: white;">
                                                                <?= h($cat['name']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="preference-section">
                                            <span class="section-label">Urgency Levels:</span>
                                            <div class="badges">
                                                <?php if (empty($urgencies)): ?>
                                                    <span class="badge badge-warning">None selected</span>
                                                <?php else: ?>
                                                    <?php foreach ($urgencyLevels as $urgency): ?>
                                                        <?php if (in_array($urgency['name'], $urgencies)): ?>
                                                            <span class="badge" style="background-color: <?= h($urgency['color']) ?>; color: white;">
                                                                <?= h($urgency['name']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="email-actions">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary btn-sm" 
                                        onclick="editEmailPreferences('<?= h($pref['email']) ?>', <?= h(json_encode($categories)) ?>, <?= h(json_encode($urgencies)) ?>)"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button 
                                        type="button" 
                                        class="btn btn-danger btn-sm" 
                                        onclick="removeEmailRecipient('<?= h($pref['email']) ?>')"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                        </svg>
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit Email Preferences Modal -->
<div id="emailPreferencesModal" class="modal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="modalTitle">Add Email Recipient</h3>
            <button type="button" class="modal-close" onclick="closeEmailModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="emailPreferencesForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" id="editMode" name="edit_mode" value="0">
                
                <div class="form-group">
                    <label for="emailAddress" class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        id="emailAddress" 
                        name="email" 
                        class="form-control" 
                        placeholder="recipient@example.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Problem Types</label>
                    <p class="form-help">Select which problem types this email should receive notifications for:</p>
                    <div class="checkbox-grid">
                        <?php foreach ($problemCategories as $category): ?>
                            <div class="checkbox-item">
                                <input 
                                    type="checkbox" 
                                    id="cat_<?= $category['id'] ?>" 
                                    name="problem_categories[]" 
                                    value="<?= $category['id'] ?>"
                                >
                                <label for="cat_<?= $category['id'] ?>" class="category-label">
                                    <span class="category-indicator" style="background-color: <?= h($category['color']) ?>;"></span>
                                    <?= h($category['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions-inline">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectAllCategories()">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectAllCategories()">Deselect All</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Urgency Levels</label>
                    <p class="form-help">Select which urgency levels this email should receive notifications for:</p>
                    <div class="checkbox-grid">
                        <?php foreach ($urgencyLevels as $urgency): ?>
                            <div class="checkbox-item">
                                <input 
                                    type="checkbox" 
                                    id="urg_<?= $urgency['id'] ?>" 
                                    name="urgency_levels[]" 
                                    value="<?= h($urgency['name']) ?>"
                                >
                                <label for="urg_<?= $urgency['id'] ?>" class="urgency-label">
                                    <span class="urgency-indicator" style="background-color: <?= h($urgency['color']) ?>;"></span>
                                    <?= h($urgency['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions-inline">
                        <button type="button" class="btn btn-sm btn-outline" onclick="selectAllUrgencies()">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deselectAllUrgencies()">Deselect All</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeEmailModal()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="saveEmailBtn" onclick="saveEmailPreferences()">Save</button>
        </div>
    </div>
</div>

<!-- Remove Email Confirmation Modal -->
<div id="removeEmailModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Remove Email Recipient</h3>
            <button type="button" class="modal-close" onclick="closeRemoveModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning">
                <strong>Warning:</strong> This will permanently remove this email recipient from all notifications.
            </div>
            <p>Are you sure you want to remove the following email recipient?</p>
            <div class="email-confirm-details">
                <strong id="removeEmailAddress"></strong>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeRemoveModal()">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmRemoveEmailBtn" onclick="confirmRemoveEmail()">Remove</button>
        </div>
    </div>
</div>

<script>
let currentEditEmail = null;

function showAddEmailModal() {
    currentEditEmail = null;
    document.getElementById('modalTitle').textContent = 'Add Email Recipient';
    document.getElementById('editMode').value = '0';
    document.getElementById('emailAddress').disabled = false;
    document.getElementById('emailPreferencesForm').reset();
    document.getElementById('saveEmailBtn').textContent = 'Save';
    
    // Select all urgency levels by default for new emails
    document.querySelectorAll('input[name="urgency_levels[]"]').forEach(cb => {
        cb.checked = true;
    });
    
    document.getElementById('emailPreferencesModal').style.display = 'flex';
    
    // Focus on email input
    setTimeout(() => {
        document.getElementById('emailAddress').focus();
    }, 100);
}

function editEmailPreferences(email, categories, urgencies) {
    currentEditEmail = email;
    document.getElementById('modalTitle').textContent = 'Edit Email Preferences';
    document.getElementById('editMode').value = '1';
    document.getElementById('emailAddress').value = email;
    document.getElementById('emailAddress').disabled = true;
    document.getElementById('saveEmailBtn').textContent = 'Update';
    
    // Clear all checkboxes first
    document.querySelectorAll('#emailPreferencesForm input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Check selected categories
    categories.forEach(catId => {
        const checkbox = document.getElementById('cat_' + catId);
        if (checkbox) checkbox.checked = true;
    });
    
    // Check selected urgencies
    urgencies.forEach(urgency => {
        // Find the urgency checkbox by value
        document.querySelectorAll('input[name="urgency_levels[]"]').forEach(cb => {
            if (cb.value === urgency) cb.checked = true;
        });
    });
    
    document.getElementById('emailPreferencesModal').style.display = 'flex';
}

function closeEmailModal() {
    document.getElementById('emailPreferencesModal').style.display = 'none';
    currentEditEmail = null;
}

function selectAllCategories() {
    document.querySelectorAll('input[name="problem_categories[]"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllCategories() {
    document.querySelectorAll('input[name="problem_categories[]"]').forEach(cb => {
        cb.checked = false;
    });
}

function selectAllUrgencies() {
    document.querySelectorAll('input[name="urgency_levels[]"]').forEach(cb => {
        cb.checked = true;
    });
}

function deselectAllUrgencies() {
    document.querySelectorAll('input[name="urgency_levels[]"]').forEach(cb => {
        cb.checked = false;
    });
}

function saveEmailPreferences() {
    const form = document.getElementById('emailPreferencesForm');
    const formData = new FormData(form);
    
    // Show loading
    const btn = document.getElementById('saveEmailBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    fetch('notifications_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEmailModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function removeEmailRecipient(email) {
    document.getElementById('removeEmailAddress').textContent = email;
    document.getElementById('removeEmailModal').style.display = 'flex';
}

function closeRemoveModal() {
    document.getElementById('removeEmailModal').style.display = 'none';
}

function confirmRemoveEmail() {
    const email = document.getElementById('removeEmailAddress').textContent;
    
    // Show loading
    const btn = document.getElementById('confirmRemoveEmailBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Removing...';
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('email', email);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('notifications_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeRemoveModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

// Close modals when clicking outside
document.getElementById('emailPreferencesModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmailModal();
    }
});

document.getElementById('removeEmailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRemoveModal();
    }
});
</script>

<style>
/* Page styling consistent with admin dashboard */
.page-header {display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;}
.page-header h1 {font-size:1.5rem;margin:0;font-weight:600;}
.page-header p.page-description {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.header-actions {display:flex;gap:.5rem;flex-wrap:wrap;}

.panel {background:#fff;border:1px solid #e2e5e9;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:1.25rem;}
.panel-header {padding:.85rem 1rem;border-bottom:1px solid #eceff2;display:flex;justify-content:space-between;align-items:center;}
.panel-header h2 {font-size:1rem;margin:0;font-weight:600;}
.panel-header p {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.panel-body {padding:1rem;}

/* Email cards layout */
.email-recipients-container {display:flex;flex-direction:column;gap:.75rem;}
.email-card {display:flex;justify-content:space-between;align-items:flex-start;padding:1rem;border:1px solid #e9ecef;border-radius:8px;background:#fafbfc;transition:box-shadow .15s ease;}
.email-card:hover {box-shadow:0 2px 8px rgba(0,0,0,.1);}

.email-info {display:flex;flex-direction:column;gap:.75rem;flex:1;}
.email-address {display:flex;align-items:center;gap:.5rem;font-weight:600;font-size:.9rem;}
.email-address svg {color:#28a745;}

.preferences-summary {display:flex;flex-direction:column;gap:.5rem;}
.preference-section {display:flex;flex-direction:column;gap:.25rem;}
.section-label {font-size:.75rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.03em;}
.badges {display:flex;gap:.25rem;flex-wrap:wrap;}

.badge {padding:.2rem .5rem;border-radius:12px;font-size:.65rem;font-weight:600;display:inline-block;}
.badge-warning {background:#ffc107;color:#000;}

.email-actions {display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-start;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;white-space:nowrap;transition:all .15s ease;}

/* Empty state */
.empty-state {text-align:center;padding:3rem 1rem;color:#6c757d;}
.empty-icon {margin-bottom:1rem;}
.empty-icon svg {color:#6c757d;opacity:.7;}
.empty-text {font-size:1.1rem;margin-bottom:.5rem;font-weight:600;}
.empty-description {margin-bottom:1.5rem;}

/* Modal styling */
.modal {display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;}
.modal-content {background:#fff;padding:0;width:90%;max-width:500px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.15);max-height:90vh;overflow-y:auto;}
.modal-content.modal-lg {max-width:700px;}
.modal-header {padding:1rem 1.25rem;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;}
.modal-header h3 {margin:0;font-size:1.1rem;font-weight:600;}
.modal-body {padding:1.25rem;max-height:calc(90vh - 120px);overflow-y:auto;}
.modal-footer {padding:1rem 1.25rem;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:.5rem;}
.modal-close {background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6c757d;line-height:1;padding:0;}
.modal-close:hover {color:#000;}

/* Form styling */
.form-group {margin-bottom:1.5rem;}
.form-label {font-size:.85rem;font-weight:600;color:#495057;margin-bottom:.5rem;display:block;}
.form-help {font-size:.75rem;color:#6c757d;margin-bottom:.75rem;}
.form-control {width:100%;padding:.5rem;border:1px solid #ced4da;border-radius:6px;font-size:.85rem;font-family:inherit;box-sizing:border-box;}
.form-control:focus {outline:none;border-color:#007bff;box-shadow:0 0 0 2px rgba(0,123,255,.25);}
.form-control:disabled {background-color:#e9ecef;opacity:1;}

.checkbox-grid {display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:.5rem;margin-bottom:.75rem;}
.checkbox-item {display:flex;align-items:center;gap:.5rem;}
.checkbox-item input[type="checkbox"] {margin:0;}
.category-label, .urgency-label {display:flex;align-items:center;gap:.5rem;font-size:.8rem;cursor:pointer;}
.category-indicator, .urgency-indicator {width:12px;height:12px;border-radius:2px;display:inline-block;}

.form-actions-inline {display:flex;gap:.5rem;margin-top:.5rem;}

.email-confirm-details {background:#f8f9fa;padding:.75rem;border-radius:6px;margin:1rem 0;border:1px solid #e9ecef;font-family:monospace;}

.alert {padding:.75rem;border-radius:6px;margin-bottom:1rem;}
.alert-success {background:#d1edff;border:1px solid #bee5eb;color:#0c5460;}
.alert-danger {background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}
.alert-warning {background:#fff3cd;border:1px solid #ffeeba;color:#856404;}

.btn svg {margin-right:.35rem;vertical-align:-.1em;}
.btn.btn-danger {background:#dc3545;color:#fff;border:1px solid #dc3545;}
.btn.btn-danger:hover {background:#c82333;border-color:#bd2130;}

/* Mobile optimizations */
@media (max-width:768px){
  .page-header {flex-direction:column;align-items:stretch;}
  .header-actions {justify-content:stretch;}
  .header-actions .btn {flex:1;}
  
  .email-card {flex-direction:column;align-items:stretch;gap:.75rem;}
  .email-actions {justify-content:stretch;}
  .email-actions .btn {flex:1;}
  
  .modal {align-items:flex-start;padding-top:5vh;}
  .modal-content {width:95%;}
  
  .checkbox-grid {grid-template-columns:1fr;}
}
</style>

<?php include '../includes/footer.php'; ?>
<?php include '../includes/footer.php'; ?>