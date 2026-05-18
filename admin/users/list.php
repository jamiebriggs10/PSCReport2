<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';

// Require admin access
requireAuth();
if (!isAdmin()) {
    header('Location: ' . getFullUrl());
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Get all users
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, role, is_active, created_at,
               (SELECT COUNT(*) FROM problems WHERE reported_by = users.id) as problems_count
        FROM users 
        ORDER BY full_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Users list error: " . $e->getMessage());
    $users = [];
}

$pageTitle = 'Manage Users';
include __DIR__ . '/../../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Manage Users</h1>
                <p class="page-description">View, edit, and manage user accounts</p>
            </div>
            <div class="header-actions">
                <a href="create.php" class="btn btn-primary btn-sm">
                    + New User
                </a>
                <a href="../dashboard.php" class="btn btn-outline btn-sm">
                    ← Dashboard
                </a>
            </div>
        </div>
        
        <?php if (empty($users)): ?>
            <section class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><circle cx="17" cy="7" r="2.5"/><path d="M15 14c3 0 5 2 5 5"/></svg>
                        </div>
                        <div class="empty-text">No users found</div>
                        <a href="create.php" class="btn btn-primary btn-sm">
                            Create First User
                        </a>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="panel">
                <header class="panel-header">
                    <div>
                        <h2>User Accounts (<?= count($users) ?>)</h2>
                        <p>Manage roles, passwords, and account access</p>
                    </div>
                </header>
                <div class="panel-body">
                    <div class="users-container">
                        <?php foreach ($users as $user): ?>
                            <div class="user-card">
                                <div class="user-info">
                                    <div class="user-identity">
                                        <div class="user-avatar">
                                            <?= substr($user['full_name'], 0, 1) ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?= h($user['full_name']) ?></div>
                                            <div class="user-username">@<?= h($user['username']) ?></div>
                                        </div>
                                    </div>
                                    <div class="user-meta">
                                        <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                            <?= ucfirst(strtolower($user['role'])) ?>
                                        </span>
                                        <span class="status-badge <?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                        <span class="problems-count"><?= $user['problems_count'] ?> problems</span>
                                    </div>
                                </div>
                                <div class="user-actions">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary btn-sm" 
                                        onclick="resetPassword(<?= $user['id'] ?>, '<?= h($user['full_name']) ?>')"
                                    >
                                        Reset Password
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm role-toggle" 
                                        onclick="toggleRole(<?= $user['id'] ?>, '<?= $user['role'] ?>')"
                                    >
                                        <?= $user['role'] === 'ADMIN' ? 'Make User' : 'Make Admin' ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reset Password</h3>
            <button type="button" class="modal-close" onclick="closeModal('resetPasswordModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p id="resetPasswordText">Reset password for <strong id="resetUserName"></strong>?</p>
            <div class="password-display">
                <label>New Password:</label>
                <code id="newPassword"></code>
            </div>
            <p class="modal-note">Share this password with the user. They will be required to change it on first login.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('resetPasswordModal')">Cancel</button>
            <button type="button" class="btn btn-warning btn-sm" id="confirmResetBtn" onclick="confirmResetPassword()">Reset Password</button>
        </div>
    </div>
</div>

<script>
let selectedUserId = null;
let newGeneratedPassword = '';

function resetPassword(userId, userName) {
    selectedUserId = userId;
    newGeneratedPassword = 'PSC' + userId;
    
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('newPassword').textContent = newGeneratedPassword;
    
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function confirmResetPassword() {
    if (!selectedUserId) return;
    
    // Show loading
    const btn = document.getElementById('confirmResetBtn');
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    
    fetch('reset_password_json.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${selectedUserId}&new_password=${newGeneratedPassword}&csrf_token=<?= generateCSRFToken() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password reset successfully!');
            closeModal('resetPasswordModal');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'Reset Password';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Reset Password';
    });
}

function toggleRole(userId, currentRole) {
    const newRole = currentRole === 'ADMIN' ? 'USER' : 'ADMIN';
    
    if (!confirm(`Change role to ${newRole}?`)) return;
    
    fetch('toggle_role.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${userId}&new_role=${newRole}&csrf_token=<?= generateCSRFToken() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('resetPasswordModal');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>

<style>
/* Modern panel styling consistent with admin dashboard */
.page-header {display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;}
.page-header h1 {font-size:1.5rem;margin:0;font-weight:600;}
.page-header p.page-description {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.header-actions {display:flex;gap:.5rem;flex-wrap:wrap;}

.panel {background:#fff;border:1px solid #e2e5e9;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:1.25rem;}
.panel-header {padding:.85rem 1rem;border-bottom:1px solid #eceff2;display:flex;justify-content:space-between;align-items:center;}
.panel-header h2 {font-size:1rem;margin:0;font-weight:600;}
.panel-header p {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.panel-body {padding:1rem;}

/* User cards layout */
.users-container {display:flex;flex-direction:column;gap:.75rem;}
.user-card {display:flex;justify-content:space-between;align-items:center;padding:.85rem;border:1px solid #e9ecef;border-radius:8px;background:#fafbfc;}
.user-info {display:flex;flex-direction:column;gap:.5rem;flex:1;}
.user-identity {display:flex;align-items:center;gap:.75rem;}
.user-avatar {width:2.5rem;height:2.5rem;border-radius:50%;background:#007bff;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.9rem;}
.user-details {display:flex;flex-direction:column;gap:.1rem;}
.user-name {font-weight:600;font-size:.85rem;}
.user-username {font-size:.75rem;color:#6c757d;font-family:monospace;}
.user-meta {display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}

.role-badge {padding:.2rem .5rem;border-radius:12px;font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
.role-admin {background:#dc3545;color:#fff;}
.role-user {background:#007bff;color:#fff;}

.status-badge {padding:.2rem .5rem;border-radius:12px;font-size:.65rem;font-weight:600;}
.status-badge.active {background:#28a745;color:#fff;}
.status-badge.inactive {background:#6c757d;color:#fff;}

.problems-count {font-size:.7rem;color:#6c757d;padding:.2rem .5rem;background:#e9ecef;border-radius:12px;}

.user-actions {display:flex;gap:.5rem;flex-wrap:wrap;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;white-space:nowrap;}

/* Empty state */
.empty-state {text-align:center;padding:3rem 1rem;color:#6c757d;}
.empty-icon {font-size:3rem;margin-bottom:1rem;}
.empty-text {font-size:1.1rem;margin-bottom:1.5rem;}

/* Modal styling */
.modal {display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;}
.modal-content {background:#fff;padding:0;width:90%;max-width:400px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.15);}
.modal-header {padding:1rem 1.25rem;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;}
.modal-header h3 {margin:0;font-size:1.1rem;font-weight:600;}
.modal-body {padding:1.25rem;}
.modal-footer {padding:1rem 1.25rem;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:.5rem;}
.modal-close {background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6c757d;line-height:1;}
.modal-close:hover {color:#000;}

.password-display {background:#f8f9fa;padding:.75rem;border-radius:6px;margin:1rem 0;border:1px solid #e9ecef;}
.password-display label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.25rem;}
.password-display code {font-size:.9rem;color:#007bff;}
.modal-note {font-size:.8rem;color:#6c757d;margin:.5rem 0 0;}

/* Mobile optimizations */
@media (max-width:768px){
  .page-header {flex-direction:column;align-items:stretch;}
  .header-actions {justify-content:stretch;}
  .header-actions .btn {flex:1;}
  
  .user-card {flex-direction:column;align-items:stretch;gap:.75rem;}
  .user-info {gap:.5rem;}
  .user-identity {flex-direction:column;align-items:flex-start;gap:.5rem;}
  .user-actions {justify-content:stretch;}
  .user-actions .btn {flex:1;}
  
  .modal-content {margin:5vh auto;width:95%;}
  .modal {align-items:flex-start;padding-top:5vh;}
}

@media (max-width:480px){
  .panel-body {padding:.85rem;}
  .users-container {gap:.6rem;}
  .user-card {padding:.75rem;}
  .user-actions {flex-direction:column;}
  .btn.btn-sm {width:100%;}
}
</style>