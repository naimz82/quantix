<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Items Management';
$action = $_GET['action'] ?? 'list';
$itemId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: items.php');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $unit = sanitizeInput($_POST['unit']);
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    $lowStockThreshold = (int)$_POST['low_stock_threshold'];
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    if ($action === 'add') {
        $result = insertRecord('items', [
            'name' => $name,
            'category_id' => $categoryId,
            'unit' => $unit,
            'quantity' => $quantity,
            'price' => $price,
            'low_stock_threshold' => $lowStockThreshold,
            'status' => $status
        ]);
        
        if ($result) {
            setAlert('Item added successfully!', 'success');
        } else {
            setAlert('Failed to add item.', 'danger');
        }
        
        header('Location: items.php');
        exit();
    }
    
    if ($action === 'edit' && $itemId) {
        $result = updateRecord('items', [
            'name' => $name,
            'category_id' => $categoryId,
            'unit' => $unit,
            'quantity' => $quantity,
            'price' => $price,
            'low_stock_threshold' => $lowStockThreshold,
            'status' => $status
        ], ['id' => $itemId]);
        
        if ($result) {
            setAlert('Item updated successfully!', 'success');
        } else {
            setAlert('Failed to update item.', 'danger');
        }
        
        header('Location: items.php');
        exit();
    }
}

// Handle delete action
if ($action === 'delete' && $itemId) {
    // Check if item has stock movements
    $hasMovements = fetchOne("SELECT COUNT(*) as count FROM stock_in WHERE item_id = ?", [$itemId])['count'] > 0 ||
                   fetchOne("SELECT COUNT(*) as count FROM stock_out WHERE item_id = ?", [$itemId])['count'] > 0;
    
    if ($hasMovements) {
        setAlert('Cannot delete item. It has associated stock movements.', 'danger');
    } else {
        if (deleteRecord('items', ['id' => $itemId])) {
            setAlert('Item deleted successfully!', 'success');
        } else {
            setAlert('Failed to delete item.', 'danger');
        }
    }
    
    header('Location: items.php');
    exit();
}

// Get data for forms
$categories = fetchAll("SELECT * FROM categories ORDER BY name");
$currentItem = null;

if (($action === 'edit' || $action === 'view') && $itemId) {
    $currentItem = fetchOne("
        SELECT i.*, c.name as category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE i.id = ?
    ", [$itemId]);
    
    if (!$currentItem) {
        setAlert('Item not found.', 'danger');
        header('Location: items.php');
        exit();
    }
}

// Get items list for main view
if ($action === 'list') {
    $searchTerm = $_GET['search'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    if ($searchTerm) {
        $whereConditions[] = "i.name LIKE ?";
        $params[] = "%{$searchTerm}%";
    }
    
    if ($categoryFilter) {
        $whereConditions[] = "i.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    $items = fetchAll("
        SELECT i.*, c.name as category_name,
               CASE 
                   WHEN i.quantity = 0 THEN 'out_of_stock'
                   WHEN i.quantity <= i.low_stock_threshold THEN 'low_stock'
                   ELSE 'in_stock'
               END as stock_status
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        {$whereClause}
        ORDER BY i.name
    ", $params);
}

include_once '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Items List View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-boxes me-2"></i>Items Management</h2>
    <a href="items.php?action=add" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add New Item
    </a>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="action" value="list">
            
            <div class="col-md-4">
                <label for="search" class="form-label">Search Items</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($searchTerm); ?>" 
                       placeholder="Search by name...">
            </div>
            
            <div class="col-md-4">
                <label for="category" class="form-label">Filter by Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" 
                            <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search me-1"></i>Filter
                </button>
                <a href="items.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Items Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($items)): ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No items found</h5>
            <p class="text-muted mb-4">Start by adding your first inventory item.</p>
            <a href="items.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Item
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Unit Price</th>
                        <th>Current Stock</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr class="<?php echo $item['stock_status'] === 'out_of_stock' ? 'table-danger' : 
                                   ($item['stock_status'] === 'low_stock' ? 'table-warning' : ''); ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td>
                            <span class="fw-bold text-primary">$<?php echo number_format($item['price'] ?? 0, 2); ?></span>
                        </td>
                        <td>
                            <span class="fw-bold"><?php echo number_format($item['quantity']); ?></span>
                            <small class="text-muted"><?php echo htmlspecialchars($item['unit']); ?></small>
                        </td>
                        <td>
                            <span class="fw-bold text-success">$<?php echo number_format(($item['quantity'] ?? 0) * ($item['price'] ?? 0), 2); ?></span>
                        </td>
                        <td>
                            <?php if ($item['stock_status'] === 'out_of_stock'): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($item['stock_status'] === 'low_stock'): ?>
                                <span class="badge bg-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="items.php?action=view&id=<?php echo $item['id']; ?>" 
                                   class="btn btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="items.php?action=edit&id=<?php echo $item['id']; ?>" 
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="items.php?action=delete&id=<?php echo $item['id']; ?>" 
                                   class="btn btn-outline-danger delete-btn" 
                                   data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
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

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Item Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Add New Item' : 'Edit Item'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($currentItem['name'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide an item name.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select select2" id="category_id" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($currentItem['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="unit" class="form-label">Unit *</label>
                            <input type="text" class="form-control" id="unit" name="unit" 
                                   value="<?php echo htmlspecialchars($currentItem['unit'] ?? ''); ?>" 
                                   placeholder="e.g., pcs, kg, liters" required>
                            <div class="invalid-feedback">
                                Please specify the unit of measurement.
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="price" class="form-label">Unit Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo number_format($currentItem['price'] ?? 0, 2, '.', ''); ?>" 
                                       min="0" step="0.01" required>
                            </div>
                            <div class="invalid-feedback">
                                Please enter the unit price.
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="quantity" class="form-label">Current Quantity *</label>
                            <input type="number" class="form-control numeric-input" id="quantity" name="quantity" 
                                   value="<?php echo $currentItem['quantity'] ?? 0; ?>" 
                                   min="0" required>
                            <div class="invalid-feedback">
                                Please enter the current quantity.
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="low_stock_threshold" class="form-label">Low Stock Threshold *</label>
                            <input type="number" class="form-control numeric-input" id="low_stock_threshold" name="low_stock_threshold" 
                                   value="<?php echo $currentItem['low_stock_threshold'] ?? 5; ?>" 
                                   min="0" required>
                            <div class="invalid-feedback">
                                Please set the low stock threshold.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo ($currentItem['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($currentItem['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a status.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Value</label>
                            <div class="form-control-plaintext fw-bold" id="total-value">
                                $0.00
                            </div>
                            <small class="text-muted">Quantity × Unit Price</small>
                        </div>
                    </div>
                            <div class="invalid-feedback">
                                Please set the low stock threshold.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stock Status Preview</label>
                        <div>
                            <span id="stock-status" class="badge bg-success">In Stock</span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="items.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $action === 'add' ? 'Add Item' : 'Update Item'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view'): ?>
<!-- View Item Details -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2"></i>Item Details
                </h5>
                <div>
                    <a href="items.php?action=edit&id=<?php echo $currentItem['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <a href="items.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Name:</th>
                                <td><?php echo htmlspecialchars($currentItem['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Category:</th>
                                <td><?php echo htmlspecialchars($currentItem['category_name'] ?? 'Uncategorized'); ?></td>
                            </tr>
                            <tr>
                                <th>Unit:</th>
                                <td><?php echo htmlspecialchars($currentItem['unit']); ?></td>
                            </tr>
                            <tr>
                                <th>Unit Price:</th>
                                <td>
                                    <span class="fw-bold text-primary fs-5">$<?php echo number_format($currentItem['price'] ?? 0, 2); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Current Stock:</th>
                                <td>
                                    <span class="fw-bold fs-5"><?php echo number_format($currentItem['quantity']); ?></span>
                                    <?php echo htmlspecialchars($currentItem['unit']); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Total Value:</th>
                                <td>
                                    <span class="fw-bold text-success fs-4">$<?php echo number_format(($currentItem['quantity'] ?? 0) * ($currentItem['price'] ?? 0), 2); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Low Stock Threshold:</th>
                                <td><?php echo number_format($currentItem['low_stock_threshold']); ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php if ($currentItem['quantity'] == 0): ?>
                                        <span class="badge bg-danger fs-6">Out of Stock</span>
                                    <?php elseif ($currentItem['quantity'] <= $currentItem['low_stock_threshold']): ?>
                                        <span class="badge bg-warning fs-6">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success fs-6">In Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td class="format-datetime"><?php echo $currentItem['created_at']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="border-top pt-3 mt-3">
                    <h6>Quick Actions</h6>
                    <div class="btn-group" role="group">
                        <a href="../pages/stock-in.php?item_id=<?php echo $currentItem['id']; ?>" class="btn btn-success">
                            <i class="fas fa-arrow-down me-1"></i>Add Stock
                        </a>
                        <a href="../pages/stock-out.php?item_id=<?php echo $currentItem['id']; ?>" class="btn btn-outline-danger">
                            <i class="fas fa-arrow-up me-1"></i>Remove Stock
                        </a>
                        <a href="../pages/stock-history.php?item_id=<?php echo $currentItem['id']; ?>" class="btn btn-outline-info">
                            <i class="fas fa-history me-1"></i>View History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php 
// Custom JavaScript for item forms
if (in_array($action, ['add', 'edit'])) {
    $customScript = '
    $(document).ready(function() {
        // Calculate total value when quantity or price changes
        function updateTotalValue() {
            var quantity = parseFloat($("#quantity").val()) || 0;
            var price = parseFloat($("#price").val()) || 0;
            var threshold = parseFloat($("#low_stock_threshold").val()) || 0;
            var totalValue = quantity * price;
            
            $("#total-value").text("$" + totalValue.toFixed(2));
            
            // Update stock status preview
            var statusBadge = $("#stock-status");
            if (quantity === 0) {
                statusBadge.removeClass().addClass("badge bg-danger").text("Out of Stock");
            } else if (quantity <= threshold) {
                statusBadge.removeClass().addClass("badge bg-warning").text("Low Stock");
            } else {
                statusBadge.removeClass().addClass("badge bg-success").text("In Stock");
            }
        }
        
        // Bind events
        $("#quantity, #price, #low_stock_threshold").on("input", updateTotalValue);
        
        // Initial calculation
        updateTotalValue();
    });
    ';
}
?>

<?php include_once '../includes/footer.php'; ?>
