<?php
/**
 * Create User Page (Admin Only)
 * Presswick Sailing Club Issue Reporting System
 */

require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../config/database.php';

// Require admin authentication
requireAdmin();
checkPasswordChangeRequired();

$errors = [];
$formData = [
    'full_name' => '',
    'role' => 'USER'
];
$newUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid request. Please try again.';
    }
    
    // Get form data
    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['role'] = $_POST['role'] ?? 'USER';
    
    // Validate form data
    $isValid = validateUserData($formData, $errors);
    
    // Generate username
    $username = '';
    if (!empty($formData['full_name'])) {
        $username = generateUsername($formData['full_name']);
    }
    
    // Check if username already exists
    if ($isValid && !empty($username)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $errors['full_name'] = 'A user with this name already exists (username would be: ' . $username . ')';
                $isValid = false;
            }
        } catch (PDOException $e) {
            error_log("Username check error: " . $e->getMessage());
            $errors['general'] = 'Database error occurred.';
            $isValid = false;
        }
    }
    
    // Create user if validation passes
    if ($isValid && empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, username, password_hash, role, must_change_password, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            // Create placeholder password hash (will be updated with real default password)
            $placeholderHash = password_hash('temp', PASSWORD_DEFAULT);
            
            $stmt->execute([
                $formData['full_name'],
                $username,
                $placeholderHash,
                $formData['role']
            ]);
            
            $userId = $pdo->lastInsertId();
            
            // Generate and set the default password
            $defaultPassword = generateDefaultPassword($userId);
            $defaultPasswordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$defaultPasswordHash, $userId]);
            
            // Get the created user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();
            $newUser['default_password'] = $defaultPassword;
            
            $pdo->commit();
            
            // Set success message
            $_SESSION['flash_message'] = 'User created successfully!';
            $_SESSION['flash_type'] = 'success';
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("User creation error: " . $e->getMessage());
            $errors['general'] = 'Failed to create user. Please try again.';
        }
    }
}

$pageTitle = 'Create User';
include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Create New User</h1>
                <p class="page-description">Add a new user account to the system</p>
            </div>
            <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="btn btn-outline btn-sm">← Dashboard</a>
        </div>
        
        <?php if ($newUser): ?>
            <!-- Success Panel -->
            <section class="panel success-panel">
                <header class="panel-header">
                    <div>
                        <h2>User Created Successfully</h2>
                        <p>Share these credentials with the new user</p>
                    </div>
                </header>
                <div class="panel-body">
                    <div class="user-credentials">
                        <div class="cred-item">
                            <label>Full Name</label>
                            <span><?= h($newUser['full_name']) ?></span>
                        </div>
                        <div class="cred-item">
                            <label>Username</label>
                            <code><?= h($newUser['username']) ?></code>
                        </div>
                        <div class="cred-item">
                            <label>Default Password</label>
                            <code id="defaultPassword"><?= h($newUser['default_password']) ?></code>
                        </div>
                        <div class="cred-item">
                            <label>Login URL</label>
                            <code><?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/login.php</code>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5>Onboarding Message</h5>
                        <p class="text-muted">Copy this message to share with the new user:</p>
                        <div class="form-group">
                            <textarea id="onboardingMessage" class="form-control" rows="7" readonly style="font-family: monospace; background: #f8f9fa;">Your PSC Issues account has been created!

Username: <?= h($newUser['username']) ?>

Password: <?= h($newUser['default_password']) ?>

Login at: <?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/login.php

You will be required to change your password on first login.</textarea>
                        </div>
                        
                        <div class="d-flex gap-3 flex-wrap" style="margin-top: 1rem;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="copyOnboardingMessage()" style="margin-right: 0.5rem;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                                </svg>
                                Copy Message
                            </button>
                            
                            <button type="button" class="btn btn-secondary btn-sm" onclick="shareOnboardingMessage()" style="margin-right: 0.5rem;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
                                </svg>
                                Share
                            </button>
                            
                            <a href="<?= getFullUrl('admin/users_create.php') ?>" class="btn btn-success btn-sm">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                </svg>
                                Create Another User
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        
        <section class="panel">
            <header class="panel-header">
                <div>
                    <h2>User Information</h2>
                    <p>Enter the details for the new user account</p>
                </div>
            </header>
            <div class="panel-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?= h($errors['general']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($errors['csrf'])): ?>
                    <div class="alert alert-danger">
                        <?= h($errors['csrf']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" data-validate class="user-form">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                            value="<?= h($formData['full_name']) ?>"
                            required
                            placeholder="Enter the user's full name"
                        >
                        <small class="form-help">
                            Username will be auto-generated (e.g., "John Smith" → "johnsmith")
                        </small>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Role <span class="required">*</span></label>
                        <select 
                            id="role" 
                            name="role" 
                            class="form-control <?= isset($errors['role']) ? 'is-invalid' : '' ?>"
                            required
                        >
                            <option value="USER" <?= $formData['role'] === 'USER' ? 'selected' : '' ?>>USER - Can report and view problems</option>
                            <option value="ADMIN" <?= $formData['role'] === 'ADMIN' ? 'selected' : '' ?>>ADMIN - Full system access</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?= h($errors['role']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary btn-sm">
                            Create User Account
                        </button>
                        
                        <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="btn btn-secondary btn-sm">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function copyOnboardingMessage() {
    const textarea = document.getElementById('onboardingMessage');
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
            fallbackCopy();
        });
    } else {
        // Fallback for older browsers or non-secure contexts
        fallbackCopy();
    }
    
    function fallbackCopy() {
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

function shareOnboardingMessage() {
    const textarea = document.getElementById('onboardingMessage');
    if (!textarea) {
        alert('Unable to find message text.');
        return;
    }
    
    const message = textarea.value;
    
    if (navigator.share) {
        navigator.share({
            title: 'PSC Issues - New User Account',
            text: message
        }).catch(function(err) {
            console.error('Share failed: ', err);
            copyOnboardingMessage();
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        copyOnboardingMessage();
        alert('Sharing not supported on this browser. Message copied to clipboard instead.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>

<style>
/* Modern panel styling consistent with admin dashboard */
.page-header {display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;}
.page-header h1 {font-size:1.5rem;margin:0;font-weight:600;}
.page-header p.page-description {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}

.panel {background:#fff;border:1px solid #e2e5e9;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.05);overflow:hidden;margin-bottom:1.25rem;}
.panel-header {padding:.85rem 1rem;border-bottom:1px solid #eceff2;display:flex;justify-content:space-between;align-items:center;}
.panel-header h2 {font-size:1rem;margin:0;font-weight:600;}
.panel-header p {margin:2px 0 0;font-size:.65rem;letter-spacing:.03em;color:#6c757d;text-transform:uppercase;font-weight:600;}
.panel-body {padding:1rem;}

.success-panel {border-color:#28a745;}
.success-panel .panel-header {background:#d4edda;color:#155724;}

.user-credentials {display:flex;flex-direction:column;gap:.75rem;margin-bottom:1rem;}
.cred-item {display:flex;flex-direction:column;gap:.25rem;}
.cred-item label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;}
.cred-item span, .cred-item code {font-size:.8rem;}
.cred-item code {background:#f8f9fa;padding:.25rem .4rem;border-radius:4px;border:1px solid #e9ecef;}

.user-form {display:flex;flex-direction:column;gap:1rem;}
.form-group {display:flex;flex-direction:column;gap:.4rem;}
.form-label {font-size:.7rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.03em;margin:0;}
.required {color:#dc3545;}
.form-help {font-size:.65rem;color:#6c757d;margin:0;line-height:1.2;}

.button-row, .action-buttons {display:flex;gap:.5rem;flex-wrap:wrap;}
.btn.btn-sm {padding:.4rem .65rem;font-size:.7rem;border-radius:6px;}

/* Mobile optimizations */
@media (max-width:600px){
  .page-header {flex-direction:column;align-items:stretch;}
  .panel-body {padding:.85rem;}
  .user-credentials {gap:.6rem;}
  .action-buttons, .button-row {flex-direction:column;}
  .btn.btn-sm {width:100%;}
}
</style>