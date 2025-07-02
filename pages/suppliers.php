<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Suppliers Management';
$action = $_GET['action'] ?? 'list';
$supplierId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: suppliers.php');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    $contactInfo = sanitizeInput($_POST['contact_info']);
    
    if (empty($name)) {
        setAlert('Supplier name is required.', 'danger');
        header('Location: suppliers.php?action=' . $action . ($supplierId ? '&id=' . $supplierId : ''));
        exit();
    }
    
    if ($action === 'add') {
        $result = insertRecord('suppliers', [
            'name' => $name,
            'contact_info' => $contactInfo
        ]);
        
        if ($result) {
            setAlert('Supplier added successfully!', 'success');
        } else {
            setAlert('Failed to add supplier.', 'danger');
        }
        
        header('Location: suppliers.php');
        exit();
    }
    
    if ($action === 'edit' && $supplierId) {
        $result = updateRecord('suppliers', [
            'name' => $name,
            'contact_info' => $contactInfo
        ], ['id' => $supplierId]);
        
        if ($result) {
            setAlert('Supplier updated successfully!', 'success');
        } else {
            setAlert('Failed to update supplier.', 'danger');
        }
        
        header('Location: suppliers.php');
        exit();
    }
}

// Handle delete action
if ($action === 'delete' && $supplierId) {
    // Check if supplier has stock movements
    $stockCount = fetchOne("SELECT COUNT(*) as count FROM stock_in WHERE supplier_id = ?", [$supplierId])['count'];
    
    if ($stockCount > 0) {
        setAlert("Cannot delete supplier. It has {$stockCount} stock movement(s) associated with it.", 'danger');
    } else {
        if (deleteRecord('suppliers', ['id' => $supplierId])) {
            setAlert('Supplier deleted successfully!', 'success');
        } else {
            setAlert('Failed to delete supplier.', 'danger');
        }
    }
    
    header('Location: suppliers.php');
    exit();
}

// Get current supplier for edit/view
$currentSupplier = null;
if (($action === 'edit' || $action === 'view') && $supplierId) {
    $currentSupplier = fetchOne("SELECT * FROM suppliers WHERE id = ?", [$supplierId]);
    
    if (!$currentSupplier) {
        setAlert('Supplier not found.', 'danger');
        header('Location: suppliers.php');
        exit();
    }
}

// Get suppliers list with stock movement counts
$suppliers = fetchAll("
    SELECT s.*, COUNT(si.id) as stock_movements
    FROM suppliers s
    LEFT JOIN stock_in si ON s.id = si.supplier_id
    GROUP BY s.id, s.name, s.contact_info
    ORDER BY s.name
");

include_once '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Suppliers List View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-truck me-2"></i>Suppliers Management</h2>
    <a href="suppliers.php?action=add" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add New Supplier
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Suppliers Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($suppliers)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No suppliers found</h5>
                    <p class="text-muted mb-4">Start by adding your first supplier to track stock sources.</p>
                    <a href="suppliers.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Supplier
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Supplier Name</th>
                                <th>Contact Information</th>
                                <th>Stock Movements</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                </td>
                                <td>
                                    <?php if (!empty($supplier['contact_info'])): ?>
                                        <small class="text-muted">
                                            <?php echo nl2br(htmlspecialchars(substr($supplier['contact_info'], 0, 100))); ?>
                                            <?php if (strlen($supplier['contact_info']) > 100): ?>...<?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">No contact info</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo number_format($supplier['stock_movements']); ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="suppliers.php?action=view&id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="suppliers.php?action=edit&id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($supplier['stock_movements'] == 0): ?>
                                        <a href="suppliers.php?action=delete&id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-outline-danger delete-btn" 
                                           data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
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
        <!-- Quick Add Supplier -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Quick Add Supplier</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="suppliers.php?action=add" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="quick_name" class="form-label">Supplier Name</label>
                        <input type="text" class="form-control" id="quick_name" name="name" 
                               placeholder="Enter supplier name" required>
                        <div class="invalid-feedback">
                            Please provide a supplier name.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quick_contact" class="form-label">Contact Info</label>
                        <textarea class="form-control" id="quick_contact" name="contact_info" 
                                  rows="3" placeholder="Phone, email, address..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Add Supplier
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Supplier Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Supplier Statistics</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?php echo count($suppliers); ?></h4>
                            <small class="text-muted">Total Suppliers</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-1"><?php echo array_sum(array_column($suppliers, 'stock_movements')); ?></h4>
                        <small class="text-muted">Stock Movements</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Supplier Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Add New Supplier' : 'Edit Supplier'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($currentSupplier['name'] ?? ''); ?>" 
                                   placeholder="Enter supplier name" required>
                            <div class="invalid-feedback">
                                Please provide a supplier name.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_info" class="form-label">Contact Information</label>
                            <textarea class="form-control" id="contact_info" name="contact_info" 
                                      rows="4" placeholder="Phone, email, address, etc."><?php echo htmlspecialchars($currentSupplier['contact_info'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Include phone number, email, address, or any other relevant contact details.
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="suppliers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $action === 'add' ? 'Add Supplier' : 'Update Supplier'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view'): ?>
<!-- View Supplier Details -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2"></i>Supplier Details
                </h5>
                <div>
                    <a href="suppliers.php?action=edit&id=<?php echo $currentSupplier['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <a href="suppliers.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Supplier Name</h6>
                        <p class="h5 mb-4"><?php echo htmlspecialchars($currentSupplier['name']); ?></p>
                        
                        <h6 class="text-muted mb-2">Contact Information</h6>
                        <?php if (!empty($currentSupplier['contact_info'])): ?>
                            <p class="mb-4"><?php echo nl2br(htmlspecialchars($currentSupplier['contact_info'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted mb-4">No contact information provided</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <?php
                        // Get supplier statistics
                        $stockMovements = fetchAll("
                            SELECT si.*, i.name as item_name
                            FROM stock_in si
                            LEFT JOIN items i ON si.item_id = i.id
                            WHERE si.supplier_id = ?
                            ORDER BY si.date DESC
                            LIMIT 5
                        ", [$currentSupplier['id']]);
                        
                        $totalStockMovements = fetchOne("SELECT COUNT(*) as count FROM stock_in WHERE supplier_id = ?", [$currentSupplier['id']])['count'];
                        $totalQuantity = fetchOne("SELECT SUM(quantity) as total FROM stock_in WHERE supplier_id = ?", [$currentSupplier['id']])['total'] ?? 0;
                        ?>
                        
                        <h6 class="text-muted mb-2">Statistics</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h4 class="text-primary mb-1"><?php echo number_format($totalStockMovements); ?></h4>
                                            <small class="text-muted">Total Orders</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success mb-1"><?php echo number_format($totalQuantity); ?></h4>
                                        <small class="text-muted">Items Supplied</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Stock Movements -->
                <?php if (!empty($stockMovements)): ?>
                <div class="border-top pt-4 mt-4">
                    <h6 class="mb-3">Recent Stock Movements</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockMovements as $movement): ?>
                                <tr>
                                    <td class="format-date"><?php echo $movement['date']; ?></td>
                                    <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                    <td class="text-success">+<?php echo number_format($movement['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($movement['remarks'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalStockMovements > 5): ?>
                    <div class="text-center mt-3">
                        <a href="stock-history.php?supplier_id=<?php echo $currentSupplier['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-history me-1"></i>View All Movements
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="<?php echo !empty($stockMovements) ? 'border-top pt-3 mt-3' : ''; ?>">
                    <h6>Quick Actions</h6>
                    <div class="btn-group" role="group">
                        <a href="stock-in.php?supplier_id=<?php echo $currentSupplier['id']; ?>" class="btn btn-success">
                            <i class="fas fa-arrow-down me-1"></i>Add Stock
                        </a>
                        <a href="suppliers.php?action=edit&id=<?php echo $currentSupplier['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Edit Supplier
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>
