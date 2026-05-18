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
    
    // Get all active users only
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, role, is_active, created_at,
               (SELECT COUNT(*) FROM problems WHERE reported_by = users.id) as problems_count
        FROM users 
        WHERE is_active = 1
        ORDER BY full_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Users list error: " . $e->getMessage());
    $users = [];
}

$pageTitle = 'Manage Users';
include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Manage Users</h1>
                <p class="page-description">View, edit, and manage user accounts</p>
            </div>
            <div class="header-actions">
                <a href="users_create.php" class="btn btn-primary btn-sm">
                    + New User
                </a>
                <a href="dashboard.php" class="btn btn-outline btn-sm">
                    ← Dashboard
                </a>
            </div>
        </div>
        
        <?php if (empty($users)): ?>
            <section class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16,4c0-1.11.89-2,2-2s2,.89,2,2-.89,2-2,2-2-.89-2-2ZM20.78,7.58c-.48-.44-1.23-.39-1.66,.09l-1.69,1.88c-.18,.2-.43,.31-.7,.31-.22,0-.43-.07-.6-.22l-.87-.75c-.41-.36-1.04-.31-1.4,.1-.36,.41-.31,1.04,.1,1.4l.87,.75c.51,.44,1.14,.67,1.78,.67,.78,0,1.52-.33,2.05-.91l1.69-1.88c.44-.48,.39-1.23-.09-1.66-.48-.44-1.23-.39-1.66,.09Z"/>
                                <circle cx="6" cy="6" r="4"/>
                                <path d="M12,14H2c-.55,0-1-.45-1-1v-1c0-2.21,1.79-4,4-4h4c2.21,0,4,1.79,4,4v1c0,.55-.45,1-1,1Z"/>
                            </svg>
                        </div>
                        <div class="empty-text">No users found</div>
                        <p class="empty-description">There are no users in the system.</p>
                        <a href="users_create.php" class="btn btn-primary btn-sm">
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
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M18,8h-1V6A5,5,0,0,0,8,6V8H7a3,3,0,0,0-3,3v8a3,3,0,0,0,3,3H17a3,3,0,0,0,3-3V11A3,3,0,0,0,18,8ZM10,6a3,3,0,0,1,6,0V8H10Zm2,10a1,1,0,0,1-1,1H9a1,1,0,0,1,0-2h2A1,1,0,0,1,12,16Zm3-2a1,1,0,0,1-1,1H9a1,1,0,0,1,0-2h5A1,1,0,0,1,15,14Z"/>
                                        </svg>
                                        Reset Password
                                    </button>
                                    
                                    <button 
                                        type="button" 
                                        class="btn btn-primary btn-sm" 
                                        onclick="copyCredentials(<?= $user['id'] ?>, '<?= h($user['username']) ?>', '<?= h($user['full_name']) ?>')"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                                            <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                                        </svg>
                                        Copy Credentials
                                    </button>
                                    
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm" 
                                        onclick="shareCredentials(<?= $user['id'] ?>, '<?= h($user['username']) ?>', '<?= h($user['full_name']) ?>')"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
                                        </svg>
                                        Share
                                    </button>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] === 'USER'): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm role-toggle" 
                                        onclick="toggleRole(<?= $user['id'] ?>, '<?= $user['role'] ?>')"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12,2A10,10,0,1,0,22,12,10,10,0,0,0,12,2Zm0,18a8,8,0,1,1,8-8A8,8,0,0,1,12,20Zm4.24-7.76L13.41,9.41a1,1,0,0,0-1.42,0L9.17,12.24a1,1,0,0,0,1.42,1.42L12,12.24l1.41,1.42a1,1,0,0,0,1.42-1.42Z"/>
                                        </svg>
                                        Make Admin
                                    </button>
                                    
                                    <button 
                                        type="button" 
                                        class="btn btn-danger btn-sm" 
                                        onclick="removeUser(<?= $user['id'] ?>, '<?= h($user['full_name']) ?>')"
                                    >
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M3 6H5H21M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19ZM10 11V17M14 11V17"/>
                                        </svg>
                                        Remove User
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

<!-- Remove User Modal -->
<div id="removeUserModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Remove User</h3>
            <button type="button" class="modal-close" onclick="closeModal('removeUserModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-warning" style="margin-bottom: 1rem; padding: 0.75rem; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 6px; color: #856404;">
                <strong>Warning:</strong> This action cannot be undone!
            </div>
            
            <p>You are about to remove the following user:</p>
            <div class="user-confirm-details" style="background: #f8f9fa; padding: 0.75rem; border-radius: 6px; margin: 1rem 0; border: 1px solid #e9ecef;">
                <strong id="removeUserName"></strong>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="adminPasswordConfirm" style="font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 0.5rem; display: block;">
                    Re-enter your admin password to confirm:
                </label>
                <input 
                    type="password" 
                    id="adminPasswordConfirm" 
                    class="form-control" 
                    placeholder="Your admin password"
                    style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.85rem;"
                    autocomplete="current-password"
                >
            </div>
            
            <p class="modal-note" style="font-size: 0.8rem; color: #6c757d; margin: 0.5rem 0 0;">
                If the user has reported problems, they will be deactivated instead of deleted to preserve data integrity.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('removeUserModal')">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmRemoveBtn" onclick="confirmRemoveUser()">Remove User</button>
        </div>
    </div>
</div>

<script>
let selectedUserId = null;
let newGeneratedPassword = '';
let selectedUserIdForRemoval = null;

function resetPassword(userId, userName) {
    selectedUserId = userId;
    newGeneratedPassword = 'PSC' + userId;
    
    document.getElementById('resetUserName').textContent = userName;
    document.getElementById('newPassword').textContent = newGeneratedPassword;
    
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function removeUser(userId, userName) {
    selectedUserIdForRemoval = userId;
    
    document.getElementById('removeUserName').textContent = userName;
    document.getElementById('adminPasswordConfirm').value = '';
    
    document.getElementById('removeUserModal').style.display = 'flex';
    
    // Focus on password input
    setTimeout(() => {
        document.getElementById('adminPasswordConfirm').focus();
    }, 100);
}

function confirmResetPassword() {
    if (!selectedUserId) return;
    
    // Show loading
    const btn = document.getElementById('confirmResetBtn');
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    
    fetch('users_reset_password_json.php', {
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

function confirmRemoveUser() {
    if (!selectedUserIdForRemoval) return;
    
    const adminPassword = document.getElementById('adminPasswordConfirm').value;
    
    if (!adminPassword) {
        alert('Please enter your admin password to confirm.');
        document.getElementById('adminPasswordConfirm').focus();
        return;
    }
    
    // Show loading
    const btn = document.getElementById('confirmRemoveBtn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Removing...';
    
    fetch('users_remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${selectedUserIdForRemoval}&admin_password=${encodeURIComponent(adminPassword)}&csrf_token=<?= generateCSRFToken() ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal('removeUserModal');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = originalText;
            
            // Clear password field if it was invalid
            if (data.message.includes('password')) {
                document.getElementById('adminPasswordConfirm').value = '';
                document.getElementById('adminPasswordConfirm').focus();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function toggleRole(userId, currentRole) {
    // Only allow promoting USER to ADMIN, not demoting ADMIN to USER
    if (currentRole === 'ADMIN') {
        alert('Admin users cannot be demoted to regular users.');
        return;
    }
    
    const newRole = 'ADMIN';
    
    if (!confirm(`Promote this user to ${newRole}?`)) return;
    
    fetch('users_toggle_role.php', {
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

function copyCredentials(userId, username, fullName) {
    // Generate the password based on user ID (same format as system uses)
    const password = 'PSC' + userId;
    const loginUrl = window.location.protocol + '//' + window.location.host + '/login.php';
    
    const message = `Your PSC Issues account has been created!

Username: ${username}

Password: ${password}

Login at: ${loginUrl}

You will be required to change your password on first login.`;
    
    // Try using the modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(message).then(function() {
            showCopyFeedback(event.target.closest('button'), 'Credentials copied!');
        }).catch(function(err) {
            console.error('Clipboard API failed: ', err);
            fallbackCopy(message, event.target.closest('button'));
        });
    } else {
        // Fallback for older browsers or non-secure contexts
        fallbackCopy(message, event.target.closest('button'));
    }
}

function shareCredentials(userId, username, fullName) {
    // Generate the password based on user ID (same format as system uses)
    const password = 'PSC' + userId;
    const loginUrl = window.location.protocol + '//' + window.location.host + '/login.php';
    
    const message = `Your PSC Issues account has been created!

Username: ${username}

Password: ${password}

Login at: ${loginUrl}

You will be required to change your password on first login.`;
    
    if (navigator.share) {
        navigator.share({
            title: 'PSC Issues - User Account Details',
            text: message
        }).catch(function(err) {
            console.error('Share failed: ', err);
            // Fallback to copy
            copyCredentials(userId, username, fullName);
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        copyCredentials(userId, username, fullName);
        alert('Sharing not supported on this browser. Credentials copied to clipboard instead.');
    }
}

function fallbackCopy(text, button) {
    try {
        // Create a temporary textarea element
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        
        textarea.select();
        textarea.setSelectionRange(0, 99999); // For mobile devices
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textarea);
        
        if (successful) {
            showCopyFeedback(button, 'Credentials copied!');
        } else {
            alert('Copying failed. Please manually copy the credentials.');
        }
    } catch (err) {
        console.error('Fallback copy failed: ', err);
        alert('Unable to copy automatically. Please manually copy the credentials.');
    }
}

function showCopyFeedback(button, message) {
    if (!button) return;
    
    const originalText = button.innerHTML;
    button.innerHTML = '<svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg> Copied!';
    button.classList.add('btn-success');
    button.classList.remove('btn-primary');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-primary');
    }, 2000);
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    
    // Reset form data when closing remove user modal
    if (modalId === 'removeUserModal') {
        document.getElementById('adminPasswordConfirm').value = '';
        selectedUserIdForRemoval = null;
    }
}

// Close modals when clicking outside
document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('resetPasswordModal');
    }
});

document.getElementById('removeUserModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal('removeUserModal');
    }
});

// Handle Enter key in password field
document.getElementById('adminPasswordConfirm').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        confirmRemoveUser();
    }
});
</script>

<!-- Password Reset Onboarding Modal -->
<?php if (isset($_SESSION['password_reset'])): 
    $resetData = $_SESSION['password_reset']; 
    unset($_SESSION['password_reset']); // Clear after use
?>
<div id="passwordResetOnboardingModal" class="modal" style="display: flex;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Password Reset Complete</h3>
            <button type="button" class="close" onclick="closePasswordResetOnboardingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-success mb-3">
                <strong>Password has been reset for <?= h($resetData['full_name']) ?></strong>
            </div>
            
            <div class="mb-3">
                <h5>Onboarding Message</h5>
                <p class="text-muted">Copy this message to share with the user:</p>
                <textarea id="resetOnboardingMessage" class="form-control" rows="7" readonly style="font-family: monospace; background: #f8f9fa;">Your PSC Issues password has been reset!

Username: <?= h($resetData['username']) ?>

Password: <?= h($resetData['new_password']) ?>

Login at: <?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/login.php

You will be required to change your password on first login.</textarea>
            </div>
            
            <div class="d-flex gap-3 flex-wrap" style="margin-top: 1rem;">
                <button type="button" class="btn btn-primary" onclick="copyResetOnboardingMessage()" style="margin-right: 0.5rem;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                        <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                    </svg>
                    Copy Message
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="shareResetOnboardingMessage()" style="margin-right: 0.5rem;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
                    </svg>
                    Share
                </button>
                
                <button type="button" class="btn btn-outline" onclick="closePasswordResetOnboardingModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function copyResetOnboardingMessage() {
    const textarea = document.getElementById('resetOnboardingMessage');
    if (!textarea) {
        alert('Unable to find message text.');
        return;
    }
    
    // Try using the modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textarea.value).then(function() {
            // Show success feedback
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg> Copied!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
            }, 2000);
        }).catch(function(err) {
            console.error('Clipboard API failed: ', err);
            fallbackCopyReset();
        });
    } else {
        // Fallback for older browsers or non-secure contexts
        fallbackCopyReset();
    }
    
    function fallbackCopyReset() {
        try {
            textarea.select();
            textarea.setSelectionRange(0, 99999); // For mobile devices
            const successful = document.execCommand('copy');
            
            if (successful) {
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/></svg> Copied!';
                button.classList.add('btn-success');
                button.classList.remove('btn-primary');
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                }, 2000);
            } else {
                alert('Copying failed. Please manually select and copy the text.');
            }
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            alert('Unable to copy automatically. Please manually select and copy the text.');
        }
    }
}

function shareResetOnboardingMessage() {
    const textarea = document.getElementById('resetOnboardingMessage');
    if (!textarea) {
        alert('Unable to find message text.');
        return;
    }
    
    const message = textarea.value;
    
    if (navigator.share) {
        navigator.share({
            title: 'PSC Issues - Password Reset',
            text: message
        }).catch(function(err) {
            console.error('Share failed: ', err);
            copyResetOnboardingMessage();
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        copyResetOnboardingMessage();
        alert('Sharing not supported on this browser. Message copied to clipboard instead.');
    }
}

function closePasswordResetOnboardingModal() {
    document.getElementById('passwordResetOnboardingModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Prevent background scroll when modal is open
if (document.getElementById('passwordResetOnboardingModal')) {
    document.body.style.overflow = 'hidden';
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

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
.user-card {display:flex;justify-content:space-between;align-items:center;padding:.85rem;border:1px solid #e9ecef;border-radius:8px;background:#fafbfc;transition:box-shadow .15s ease;}
.user-card:hover {box-shadow:0 2px 8px rgba(0,0,0,.1);}
.user-info {display:flex;flex-direction:column;gap:.5rem;flex:1;}
.user-identity {display:flex;align-items:center;gap:.75rem;}
.user-avatar {width:2.5rem;height:2.5rem;border-radius:50%;background:#007bff;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.9rem;flex-shrink:0;}
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
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;white-space:nowrap;transition:all .15s ease;}

/* Empty state */
.empty-state {text-align:center;padding:3rem 1rem;color:#6c757d;}
.empty-icon {margin-bottom:1rem;}
.empty-icon svg {color:#6c757d;opacity:.7;}
.empty-text {font-size:1.1rem;margin-bottom:.5rem;font-weight:600;}
.empty-description {margin-bottom:1.5rem;}

.btn svg {margin-right:.35rem;vertical-align:-.1em;}

/* Modal styling */
.modal {display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;}
.modal-content {background:#fff;padding:0;width:90%;max-width:400px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.15);}
.modal-header {padding:1rem 1.25rem;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;}
.modal-header h3 {margin:0;font-size:1.1rem;font-weight:600;}
.modal-body {padding:1.25rem;}
.modal-footer {padding:1rem 1.25rem;border-top:1px solid #e9ecef;display:flex;justify-content:flex-end;gap:.5rem;}
.modal-close {background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6c757d;line-height:1;padding:0;}
.modal-close:hover {color:#000;}

/* Remove user modal specific styling */
.alert {margin-bottom:1rem;padding:0.75rem;border-radius:6px;}
.alert-warning {background:#fff3cd;border:1px solid #ffeeba;color:#856404;}
.user-confirm-details {background:#f8f9fa;padding:0.75rem;border-radius:6px;margin:1rem 0;border:1px solid #e9ecef;font-family:inherit;}
.form-group {margin-bottom:1rem;}
.form-group label {font-size:0.85rem;font-weight:600;color:#495057;margin-bottom:0.5rem;display:block;}
.form-control {width:100%;padding:0.5rem;border:1px solid #ced4da;border-radius:6px;font-size:0.85rem;font-family:inherit;box-sizing:border-box;}
.form-control:focus {outline:none;border-color:#007bff;box-shadow:0 0 0 2px rgba(0,123,255,.25);}

/* Button styling updates */
.btn.btn-danger {background:#dc3545;color:#fff;border:1px solid #dc3545;}
.btn.btn-danger:hover {background:#c82333;border-color:#bd2130;}
.btn.btn-danger:disabled {background:#6c757d;border-color:#6c757d;cursor:not-allowed;}

.password-display {background:#f8f9fa;padding:.75rem;border-radius:6px;margin:1rem 0;border:1px solid #e9ecef;}
.password-display label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.25rem;}
.password-display code {font-size:.9rem;color:#007bff;background:transparent;padding:0;}
.modal-note {font-size:.8rem;color:#6c757d;margin:.5rem 0 0;}

/* Mobile optimizations */
@media (max-width:768px){
  .page-header {flex-direction:column;align-items:stretch;}
  .header-actions {justify-content:stretch;}
  .header-actions .btn {flex:1;}
  
  .user-card {flex-direction:column;align-items:stretch;gap:.75rem;}
  .user-info {gap:.5rem;}
  .user-identity {gap:.5rem;}
  .user-actions {justify-content:stretch;}
  .user-actions .btn {flex:1;}
  
  .modal {align-items:flex-start;padding-top:5vh;}
  .modal-content {width:95%;}
}

@media (max-width:480px){
  .panel-body {padding:.85rem;}
  .users-container {gap:.6rem;}
  .user-card {padding:.75rem;}
  .user-actions {flex-direction:column;}
  .btn.btn-sm {width:100%;}
  .user-meta {flex-direction:column;align-items:flex-start;gap:.25rem;}
}
</style>