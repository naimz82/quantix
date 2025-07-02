<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Categories Management';
$action = $_GET['action'] ?? 'list';
$categoryId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: categories.php');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    
    if (empty($name)) {
        setAlert('Category name is required.', 'danger');
        header('Location: categories.php?action=' . $action . ($categoryId ? '&id=' . $categoryId : ''));
        exit();
    }
    
    if ($action === 'add') {
        // Check if category already exists
        $existing = fetchOne("SELECT id FROM categories WHERE name = ?", [$name]);
        if ($existing) {
            setAlert('Category already exists.', 'danger');
        } else {
            $result = insertRecord('categories', ['name' => $name]);
            if ($result) {
                setAlert('Category added successfully!', 'success');
            } else {
                setAlert('Failed to add category.', 'danger');
            }
        }
        
        header('Location: categories.php');
        exit();
    }
    
    if ($action === 'edit' && $categoryId) {
        // Check if another category with same name exists
        $existing = fetchOne("SELECT id FROM categories WHERE name = ? AND id != ?", [$name, $categoryId]);
        if ($existing) {
            setAlert('Category name already exists.', 'danger');
        } else {
            $result = updateRecord('categories', ['name' => $name], ['id' => $categoryId]);
            if ($result) {
                setAlert('Category updated successfully!', 'success');
            } else {
                setAlert('Failed to update category.', 'danger');
            }
        }
        
        header('Location: categories.php');
        exit();
    }
}

// Handle delete action
if ($action === 'delete' && $categoryId) {
    // Check if category has items
    $itemCount = fetchOne("SELECT COUNT(*) as count FROM items WHERE category_id = ?", [$categoryId])['count'];
    
    if ($itemCount > 0) {
        setAlert("Cannot delete category. It has {$itemCount} item(s) associated with it.", 'danger');
    } else {
        if (deleteRecord('categories', ['id' => $categoryId])) {
            setAlert('Category deleted successfully!', 'success');
        } else {
            setAlert('Failed to delete category.', 'danger');
        }
    }
    
    header('Location: categories.php');
    exit();
}

// Get current category for edit/view
$currentCategory = null;
if (($action === 'edit' || $action === 'view') && $categoryId) {
    $currentCategory = fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
    
    if (!$currentCategory) {
        setAlert('Category not found.', 'danger');
        header('Location: categories.php');
        exit();
    }
}

// Get categories list with item counts
$categories = fetchAll("
    SELECT c.*, COUNT(i.id) as item_count
    FROM categories c
    LEFT JOIN items i ON c.id = i.category_id
    GROUP BY c.id, c.name
    ORDER BY c.name
");

include_once '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Categories List View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tags me-2"></i>Categories Management</h2>
    <a href="categories.php?action=add" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add New Category
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Categories Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($categories)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No categories found</h5>
                    <p class="text-muted mb-4">Start by adding your first category to organize your inventory.</p>
                    <a href="categories.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Category
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Items Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo number_format($category['item_count']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="../pages/items.php?category=<?php echo $category['id']; ?>" 
                                           class="btn btn-outline-info" title="View Items">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($category['item_count'] == 0): ?>
                                        <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>" 
                                           class="btn btn-outline-danger delete-btn" 
                                           data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Quick Add Category -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Quick Add Category</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="categories.php?action=add" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="quick_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="quick_name" name="name" 
                               placeholder="Enter category name" required>
                        <div class="invalid-feedback">
                            Please provide a category name.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Add Category
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Category Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Category Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?php echo count($categories); ?></h4>
                            <small class="text-muted">Total Categories</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-1"><?php echo array_sum(array_column($categories, 'item_count')); ?></h4>
                        <small class="text-muted">Total Items</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Category Form -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Add New Category' : 'Edit Category'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($currentCategory['name'] ?? ''); ?>" 
                               placeholder="Enter category name" required>
                        <div class="invalid-feedback">
                            Please provide a category name.
                        </div>
                        <div class="form-text">
                            Choose a descriptive name for your category (e.g., Electronics, Office Supplies, etc.)
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $action === 'add' ? 'Add Category' : 'Update Category'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>
