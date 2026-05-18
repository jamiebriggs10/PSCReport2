<?php
/**
 * Admin Configuration Page
 * Manage urgency levels and problem categories
 */

require_once '../includes/auth.php';
require_once '../includes/utils.php';
require_once '../config/database.php';

// Require admin access
requireAuth();
if (!isAdmin()) {
    header('Location: ' . getFullUrl());
    exit;
}

$pageTitle = 'System Configuration';
$pdo = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_urgency':
                // Get next display order
                $maxOrder = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM urgency_levels")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO urgency_levels (name, color, display_order) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['urgency_name'], $_POST['urgency_color'], $maxOrder]);
                $success = "Urgency level added successfully!";
                break;
                
            case 'add_category':
                // Get next display order
                $maxOrder = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 FROM problem_categories")->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO problem_categories (name, description, color, admin_only, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['category_name'], $_POST['category_description'], $_POST['category_color'], isset($_POST['admin_only']) ? 1 : 0, $maxOrder]);
                $success = "Problem category added successfully!";
                break;
                
            case 'toggle_urgency':
                $stmt = $pdo->prepare("UPDATE urgency_levels SET is_active = !is_active WHERE id = ?");
                $stmt->execute([$_POST['urgency_id']]);
                $success = "Urgency level updated successfully!";
                break;
                
            case 'toggle_category':
                $stmt = $pdo->prepare("UPDATE problem_categories SET is_active = !is_active WHERE id = ?");
                $stmt->execute([$_POST['category_id']]);
                $success = "Problem category updated successfully!";
                break;
                
            case 'delete_urgency':
                // First check if it's inactive
                $stmt = $pdo->prepare("SELECT is_active, name FROM urgency_levels WHERE id = ?");
                $stmt->execute([$_POST['urgency_id']]);
                $urgency = $stmt->fetch();
                
                if (!$urgency) {
                    throw new Exception("Urgency level not found.");
                } elseif ($urgency['is_active']) {
                    throw new Exception("Cannot delete active urgency level. Please disable it first.");
                }
                
                // Check if it's being used by any problems
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE urgency_tags = ?");
                $stmt->execute([$urgency['name']]);
                $usageCount = $stmt->fetchColumn();
                
                if ($usageCount > 0) {
                    throw new Exception("Cannot delete urgency level '{$urgency['name']}' - it is used by {$usageCount} problem(s).");
                }
                
                // Safe to delete
                $stmt = $pdo->prepare("DELETE FROM urgency_levels WHERE id = ?");
                $stmt->execute([$_POST['urgency_id']]);
                $success = "Urgency level '{$urgency['name']}' deleted permanently!";
                break;
                
            case 'delete_category':
                // First check if it's inactive
                $stmt = $pdo->prepare("SELECT is_active, name FROM problem_categories WHERE id = ?");
                $stmt->execute([$_POST['category_id']]);
                $category = $stmt->fetch();
                
                if (!$category) {
                    throw new Exception("Problem category not found.");
                } elseif ($category['is_active']) {
                    throw new Exception("Cannot delete active category. Please disable it first.");
                }
                
                // Check if it's being used by any problems
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM problems WHERE problem_category_id = ?");
                $stmt->execute([$_POST['category_id']]);
                $usageCount = $stmt->fetchColumn();
                
                if ($usageCount > 0) {
                    throw new Exception("Cannot delete category '{$category['name']}' - it is used by {$usageCount} problem(s).");
                }
                
                // Safe to delete
                $stmt = $pdo->prepare("DELETE FROM problem_categories WHERE id = ?");
                $stmt->execute([$_POST['category_id']]);
                $success = "Problem category '{$category['name']}' deleted permanently!";
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current urgency levels
$urgencyLevels = $pdo->query("SELECT * FROM urgency_levels ORDER BY display_order, name")->fetchAll();

// Get current problem categories
$problemCategories = $pdo->query("SELECT * FROM problem_categories ORDER BY display_order, name")->fetchAll();

include '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">System Configuration</h1>
            <p class="page-description">Manage urgency levels and problem categories</p>
            <a href="<?= getFullUrl('admin/dashboard.php') ?>" class="btn btn-outline">← Back to Dashboard</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="config-sections">
            <!-- Urgency Levels Section -->
            <section class="config-section">
                <div class="section-header">
                    <h2>Urgency Levels</h2>
                    <p>Configure the urgency levels available when reporting problems</p>
                </div>

                <div class="config-content">
                    <div class="add-item-form">
                        <h3>Add New Urgency Level</h3>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_urgency">
                            <div class="form-group">
                                <label>Name:</label>
                                <input type="text" name="urgency_name" required maxlength="100" placeholder="e.g., Critical">
                            </div>
                            <div class="form-group">
                                <label>Color:</label>
                                <input type="color" name="urgency_color" value="#007bff">
                            </div>
                            <button type="submit" class="btn btn-primary">+ Add Level</button>
                        </form>
                    </div>

                    <div class="items-list">
                        <h3>Current Urgency Levels</h3>
                        <?php if (empty($urgencyLevels)): ?>
                            <p class="empty-state">No urgency levels configured.</p>
                        <?php else: ?>
                            <div class="items-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Name</th>
                                            <th>Color</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($urgencyLevels as $level): ?>
                                            <tr class="<?= $level['is_active'] ? '' : 'inactive' ?>">
                                                <td><?= h($level['display_order']) ?></td>
                                                <td>
                                                    <span class="urgency-preview" style="background-color: <?= h($level['color']) ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                        <?= h($level['name']) ?>
                                                    </span>
                                                </td>
                                                <td><?= h($level['color']) ?></td>
                                                <td>
                                                    <span class="status-badge <?= $level['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                        <?= $level['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="toggle_urgency">
                                                        <input type="hidden" name="urgency_id" value="<?= $level['id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $level['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                                            <?= $level['is_active'] ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if (!$level['is_active']): ?>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteUrgency(<?= $level['id'] ?>, '<?= h($level['name']) ?>')">
                                                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
                                                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Problem Categories Section -->
            <section class="config-section">
                <div class="section-header">
                    <h2>Problem Categories</h2>
                    <p>Configure the problem categories available when reporting issues</p>
                </div>

                <div class="config-content">
                    <div class="add-item-form">
                        <h3>Add New Problem Category</h3>
                        <form method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_category">
                            <div class="form-group">
                                <label>Name:</label>
                                <input type="text" name="category_name" required maxlength="100" placeholder="e.g., Equipment">
                            </div>
                            <div class="form-group">
                                <label>Description:</label>
                                <input type="text" name="category_description" maxlength="255" placeholder="Optional description">
                            </div>
                            <div class="form-group">
                                <label>Color:</label>
                                <input type="color" name="category_color" value="#28a745">
                            </div>
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 8px;">
                                    <input type="checkbox" name="admin_only" value="1">
                                    <span>Admin Only</span>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">+ Add Category</button>
                        </form>
                    </div>

                    <div class="items-list">
                        <h3>Current Problem Categories</h3>
                        <?php if (empty($problemCategories)): ?>
                            <p class="empty-state">No problem categories configured.</p>
                        <?php else: ?>
                            <div class="items-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Color</th>
                                            <th>Admin Only</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($problemCategories as $category): ?>
                                            <tr class="<?= $category['is_active'] ? '' : 'inactive' ?>">
                                                <td><?= h($category['display_order']) ?></td>
                                                <td>
                                                    <span class="category-preview" style="background-color: <?= h($category['color']) ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                        <?= h($category['name']) ?>
                                                    </span>
                                                </td>
                                                <td><?= h($category['description'] ?? '') ?></td>
                                                <td><?= h($category['color']) ?></td>
                                                <td>
                                                    <?php if ($category['admin_only']): ?>
                                                        <span class="admin-only-badge" style="background: #ffc107; color: #212529; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; font-weight: 500;">Admin Only</span>
                                                    <?php else: ?>
                                                        <span style="color: #6c757d; font-size: 0.8rem;">All Users</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?= $category['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                        <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="toggle_category">
                                                        <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= $category['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                                            <?= $category['is_active'] ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if (!$category['is_active']): ?>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteCategory(<?= $category['id'] ?>, '<?= h($category['name']) ?>')">
                                                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z"/>
                                                                <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3h11V2h-11v1Z"/>
                                                            </svg>
                                                            Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<style>
.config-sections {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.config-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-header h2 {
    margin: 0 0 0.5rem 0;
    color: #333;
}

.section-header p {
    margin: 0 0 1.5rem 0;
    color: #666;
}

.config-content {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.add-item-form {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.add-item-form h3 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.form-inline {
    display: flex;
    gap: 1rem;
    align-items: end;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.form-group label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.25rem;
}

.form-group input {
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9rem;
}

.items-list h3 {
    margin: 0 0 1rem 0;
    color: #495057;
}

.items-table {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.table th,
.table td {
    text-align: left;
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    font-weight: 600;
    color: #495057;
    background-color: #f8f9fa;
}

.table tr.inactive {
    opacity: 0.6;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.empty-state {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 2rem;
}

@media (max-width: 768px) {
    .form-inline {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-group {
        min-width: unset;
    }
}
</style>

<script>
function confirmDeleteUrgency(urgencyId, urgencyName) {
    if (confirm(`Are you sure you want to permanently delete the urgency level "${urgencyName}"?\n\nThis action cannot be undone.\n\nThe urgency level will only be deleted if it's not being used by any problems.`)) {
        // Create and submit a hidden form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= generateCSRFToken() ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_urgency';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'urgency_id';
        idInput.value = urgencyId;
        
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmDeleteCategory(categoryId, categoryName) {
    if (confirm(`Are you sure you want to permanently delete the category "${categoryName}"?\n\nThis action cannot be undone.\n\nThe category will only be deleted if it's not being used by any problems.`)) {
        // Create and submit a hidden form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= generateCSRFToken() ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_category';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'category_id';
        idInput.value = categoryId;
        
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>