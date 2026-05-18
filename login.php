<?php
/**
 * Login Page
 * Presswick Sailing Club Issue Reporting System
 */

require_once 'includes/auth.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getFullUrl());
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // Attempt login if no validation errors
    if (empty($errors)) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT id, full_name, username, password_hash, role, must_change_password, is_active 
                FROM users 
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                loginUser($user);
                
                // Determine redirect destination
                $redirectTo = $_GET['redirect'] ?? null;
                
                // Don't redirect back to login page or other auth pages
                if (empty($redirectTo) || 
                    strpos($redirectTo, 'login.php') !== false || 
                    strpos($redirectTo, 'logout.php') !== false) {
                    $redirectTo = getRelativeUrl('index.php');
                }
                
                header("Location: {$redirectTo}");
                exit;
            } else {
                $errors['login'] = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $errors['login'] = 'Login failed. Please try again.';
        }
    }
}

$pageTitle = 'Login';
include 'includes/header.php';
?>

<main class="main">
    <div class="container-sm">
        <div class="card">
            <div class="card-header text-center">
                <h2>Welcome to <?= APP_NAME ?></h2>
                <p class="text-muted">Please sign in to continue</p>
            </div>
            <div class="card-body">
                <?php if (isset($errors['login'])): ?>
                    <div class="alert alert-danger">
                        <?= h($errors['login']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" data-validate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                            value="<?= h($username) ?>"
                            required
                            autocomplete="username"
                            autofocus
                        >
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?= h($errors['username']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                            required
                            autocomplete="current-password"
                        >
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= h($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        Sign In
                    </button>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <p class="text-muted">
                <small>
                    If you have any troubles, contact the administrator.
                </small>
            </p>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>