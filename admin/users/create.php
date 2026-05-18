<?php
/**
 * Create User Page (Admin Only)
 * Presswick Sailing Club Issue Reporting System
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/utils.php';
require_once __DIR__ . '/../../config/database.php';

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
include '../../includes/header.php';
?>

<main class="main">
    <div class="container-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Create New User</h1>
            <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="btn btn-outline">← Back to Dashboard</a>
        </div>
        
        <?php if ($newUser): ?>
            <!-- Success Modal/Card -->
            <div class="card mb-4" style="border-color: #28a745;">
                <div class="card-header" style="background-color: #d4edda; color: #155724;">
                    <h3>User Created Successfully</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <strong>User Details:</strong>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Full Name:</strong> <?= h($newUser['full_name']) ?></p>
                            <p><strong>Username:</strong> <code><?= h($newUser['username']) ?></code></p>
                            <p><strong>Role:</strong> <?= h($newUser['role']) ?></p>
                            <p><strong>Default Password:</strong> <code id="defaultPassword"><?= h($newUser['default_password']) ?></code></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Login URL:</strong><br><code><?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/login.php</code></p>
                            <p><strong>Status:</strong> User must change password on first login</p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5>Onboarding Message</h5>
                        <p class="text-muted">Copy this message to share with the new user:</p>
                        <div class="form-group">
                            <textarea id="onboardingMessage" class="form-control" rows="6" readonly style="font-family: monospace; background: #f8f9fa;">Your PSC Issues account has been created!

Username: <?= h($newUser['username']) ?>
Password: <?= h($newUser['default_password']) ?>

Login at: <?= $_SERVER['REQUEST_SCHEME'] ?>://<?= $_SERVER['HTTP_HOST'] ?>/login.php

You will be required to change your password on first login.</textarea>
                        </div>
                        
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary" onclick="copyOnboardingMessage()">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                                    <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                                </svg>
                                Copy Message
                            </button>
                            
                            <button type="button" class="btn btn-secondary" onclick="shareOnboardingMessage()">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
                                </svg>
                                Share
                            </button>
                            
                            <a href="<?= getFullUrl('admin/users/create.php') ?>" class="btn btn-success">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                                </svg>
                                Create Another User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>User Information</h2>
                <p class="text-muted">Enter the details for the new user account</p>
            </div>
            <div class="card-body">
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
                
                <form method="POST" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name <span style="color: red;">*</span></label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                            value="<?= h($formData['full_name']) ?>"
                            required
                            placeholder="Enter the user's full name"
                        >
                        <small class="form-text text-muted">
                            Username will be auto-generated from the full name (no spaces, lowercase)
                        </small>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= h($errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">Role <span style="color: red;">*</span></label>
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
                    
                    <div class="alert alert-info">
                        <h5>Default Account Settings:</h5>
                        <ul class="mb-0">
                            <li><strong>Username:</strong> Generated from full name (e.g., "John Smith" → "johnsmith")</li>
                            <li><strong>Password:</strong> Format PSC{user_id} (e.g., "PSC3", "PSC4")</li>
                            <li><strong>First Login:</strong> User will be required to change password</li>
                            <li><strong>Account Status:</strong> Active by default</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Create User Account
                        </button>
                        
                        <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function copyOnboardingMessage() {
    const textarea = document.getElementById('onboardingMessage');
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices
    
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
        console.error('Failed to copy: ', err);
        alert('Failed to copy to clipboard. Please select the text manually and copy.');
    });
}

function shareOnboardingMessage() {
    const message = document.getElementById('onboardingMessage').value;
    
    if (navigator.share) {
        navigator.share({
            title: 'PSC Issues - New User Account',
            text: message
        }).catch(console.error);
    } else {
        // Fallback for browsers that don't support Web Share API
        copyOnboardingMessage();
        alert('Sharing not supported on this browser. Message copied to clipboard instead.');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>