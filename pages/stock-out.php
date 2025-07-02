<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Stock Out - Record Usage';
$action = $_GET['action'] ?? 'add';
$preSelectedItem = $_GET['item_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: stock-out.php');
        exit();
    }
    
    $itemId = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $purpose = sanitizeInput($_POST['purpose']);
    $date = sanitizeInput($_POST['date']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    // Validation
    $errors = [];
    
    if (empty($itemId)) {
        $errors[] = 'Please select an item.';
    }
    
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be greater than 0.';
    }
    
    if (empty($purpose)) {
        $errors[] = 'Please specify the purpose.';
    }
    
    if (empty($date)) {
        $errors[] = 'Please select a date.';
    }
    
    // Check if sufficient stock available
    if ($itemId && $quantity > 0) {
        $currentItem = fetchOne("SELECT quantity, name FROM items WHERE id = ?", [$itemId]);
        if ($currentItem && $currentItem['quantity'] < $quantity) {
            $errors[] = "Insufficient stock. Available: {$currentItem['quantity']}, Requested: {$quantity}";
        }
    }
    
    if (empty($errors)) {
        $result = addStockOut($itemId, $quantity, $purpose, $date, $remarks);
        
        if ($result) {
            setAlert("Successfully recorded usage of {$quantity} units!", 'success');
            header('Location: stock-out.php');
            exit();
        } else {
            setAlert('Failed to record stock usage. Please try again.', 'danger');
        }
    } else {
        foreach ($errors as $error) {
            setAlert($error, 'danger');
            break; // Show only first error
        }
    }
}

// Get data for dropdowns
$items = fetchAll("
    SELECT i.*, c.name as category_name 
    FROM items i 
    LEFT JOIN categories c ON i.category_id = c.id 
    WHERE i.quantity > 0
    ORDER BY i.name
");

// Common purposes for dropdown
$commonPurposes = [
    'Sale/Customer Order',
    'Internal Use',
    'Production/Manufacturing',
    'Damaged/Expired',
    'Sample/Demo',
    'Transfer to Another Location',
    'Quality Testing',
    'Staff Use',
    'Other'
];

// Get recent stock out entries
$recentEntries = fetchAll("
    SELECT so.*, i.name as item_name, i.unit
    FROM stock_out so
    LEFT JOIN items i ON so.item_id = i.id
    ORDER BY so.created_at DESC
    LIMIT 10
");

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-arrow-up me-2 text-danger"></i>Stock Out - Record Usage</h2>
    <div>
        <a href="../pages/stock-history.php?type=out" class="btn btn-outline-info">
            <i class="fas fa-history me-2"></i>View History
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Stock Out Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-minus me-2"></i>Record Stock Usage</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="item_id" class="form-label">Item *</label>
                            <select class="form-select select2" id="item_id" name="item_id" required>
                                <option value="">Select Item</option>
                                <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" 
                                        data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                        data-current="<?php echo $item['quantity']; ?>"
                                        data-threshold="<?php echo $item['low_stock_threshold']; ?>"
                                        <?php echo $preSelectedItem == $item['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <?php if ($item['category_name']): ?>
                                        (<?php echo htmlspecialchars($item['category_name']); ?>)
                                    <?php endif; ?>
                                    - Available: <?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select an item.
                            </div>
                            <?php if (empty($items)): ?>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                No items with available stock found.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="purpose" class="form-label">Purpose *</label>
                            <div class="input-group">
                                <select class="form-select" id="purpose_select">
                                    <option value="">Select Purpose</option>
                                    <?php foreach ($commonPurposes as $purpose): ?>
                                    <option value="<?php echo htmlspecialchars($purpose); ?>">
                                        <?php echo htmlspecialchars($purpose); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" id="custom_purpose_btn">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            <input type="text" class="form-control mt-2 d-none" id="purpose" name="purpose" 
                                   placeholder="Enter custom purpose..." required>
                            <div class="invalid-feedback">
                                Please specify the purpose.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <div class="input-group">
                                <input type="number" class="form-control numeric-input" id="quantity" name="quantity" 
                                       min="1" step="1" required>
                                <span class="input-group-text" id="unit-display">units</span>
                            </div>
                            <div class="invalid-feedback">
                                Please enter a valid quantity.
                            </div>
                            <div class="form-text" id="stock-warning"></div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Please select a date.
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Remaining Stock</label>
                            <div class="form-control-plaintext fw-bold" id="remaining-stock">
                                Select item first
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                  placeholder="Optional notes about this stock usage..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-danger" id="submit-btn">
                            <i class="fas fa-minus me-2"></i>Record Usage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Current Stock Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Item Information</h6>
            </div>
            <div class="card-body">
                <div id="item-info" class="text-muted text-center py-3">
                    <i class="fas fa-box fa-2x mb-2"></i>
                    <p class="mb-0">Select an item to view details</p>
                </div>
            </div>
        </div>
        
        <!-- Stock Level Warning -->
        <div class="card mb-4 d-none" id="warning-card">
            <div class="card-header bg-warning">
                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Stock Level Warning</h6>
            </div>
            <div class="card-body">
                <div id="warning-message"></div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Today's Summary</h6>
            </div>
            <div class="card-body">
                <?php
                $todayStats = fetchOne("
                    SELECT COUNT(*) as entries, SUM(quantity) as total_quantity
                    FROM stock_out 
                    WHERE DATE(date) = CURDATE()
                ");
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-danger mb-1"><?php echo number_format($todayStats['entries'] ?? 0); ?></h4>
                            <small class="text-muted">Entries</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-primary mb-1"><?php echo number_format($todayStats['total_quantity'] ?? 0); ?></h4>
                        <small class="text-muted">Items Used</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Entries -->
<?php if (!empty($recentEntries)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Stock Out Entries</h6>
                <a href="stock-history.php?type=out" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Purpose</th>
                                <th>Remarks</th>
                                <th>Recorded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEntries as $entry): ?>
                            <tr>
                                <td class="format-date"><?php echo $entry['date']; ?></td>
                                <td><?php echo htmlspecialchars($entry['item_name']); ?></td>
                                <td>
                                    <span class="text-danger fw-bold">
                                        -<?php echo number_format($entry['quantity']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($entry['unit']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['purpose']); ?></td>
                                <td>
                                    <?php if (!empty($entry['remarks'])): ?>
                                        <span class="text-truncate" style="max-width: 150px;" 
                                              title="<?php echo htmlspecialchars($entry['remarks']); ?>">
                                            <?php echo htmlspecialchars($entry['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="format-datetime"><?php echo $entry['created_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    // Handle purpose selection
    $('#purpose_select').on('change', function() {
        var value = $(this).val();
        if (value) {
            $('#purpose').val(value).removeClass('d-none');
        }
    });
    
    $('#custom_purpose_btn').on('click', function() {
        $('#purpose').removeClass('d-none').focus();
        $('#purpose_select').val('');
    });
    
    // Update item information when item is selected
    $('#item_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var itemId = $(this).val();
        
        if (itemId) {
            var unit = selectedOption.data('unit');
            var currentStock = selectedOption.data('current');
            var threshold = selectedOption.data('threshold');
            var itemName = selectedOption.text().split(' - Available:')[0];
            
            // Update unit display
            $('#unit-display').text(unit);
            
            // Update item info panel
            $('#item-info').html(`
                <div class="text-start">
                    <h6 class="mb-2">${itemName}</h6>
                    <p class="mb-1"><strong>Available Stock:</strong> ${currentStock.toLocaleString()} ${unit}</p>
                    <p class="mb-1"><strong>Low Stock Threshold:</strong> ${threshold.toLocaleString()} ${unit}</p>
                    <p class="mb-0"><strong>Unit:</strong> ${unit}</p>
                </div>
            `);
            
            // Set max quantity to current stock
            $('#quantity').attr('max', currentStock);
            
            // Calculate remaining stock when quantity changes
            updateRemainingStock();
        } else {
            $('#unit-display').text('units');
            $('#item-info').html(`
                <div class="text-muted text-center py-3">
                    <i class="fas fa-box fa-2x mb-2"></i>
                    <p class="mb-0">Select an item to view details</p>
                </div>
            `);
            $('#remaining-stock').text('Select item first');
            $('#quantity').removeAttr('max');
            $('#warning-card').addClass('d-none');
        }
    });
    
    // Update remaining stock when quantity changes
    $('#quantity').on('input', updateRemainingStock);
    
    function updateRemainingStock() {
        var selectedOption = $('#item_id').find('option:selected');
        var currentStock = selectedOption.data('current');
        var threshold = selectedOption.data('threshold');
        var quantity = parseInt($('#quantity').val()) || 0;
        var unit = selectedOption.data('unit') || 'units';
        
        if (currentStock !== undefined) {
            var remainingStock = currentStock - quantity;
            var submitBtn = $('#submit-btn');
            var warningCard = $('#warning-card');
            var stockWarning = $('#stock-warning');
            
            // Reset warnings
            stockWarning.text('');
            warningCard.addClass('d-none');
            submitBtn.prop('disabled', false);
            
            if (quantity > currentStock) {
                // Insufficient stock
                $('#remaining-stock').html(`
                    <span class="text-danger">${remainingStock} ${unit}</span>
                    <small class="text-danger d-block">Insufficient stock!</small>
                `);
                stockWarning.html('<span class="text-danger">Insufficient stock available!</span>');
                submitBtn.prop('disabled', true);
                
            } else if (remainingStock <= threshold && remainingStock > 0) {
                // Will result in low stock
                $('#remaining-stock').html(`
                    <span class="text-warning">${remainingStock.toLocaleString()} ${unit}</span>
                    <small class="text-warning d-block">Low stock warning</small>
                `);
                warningCard.removeClass('d-none');
                $('#warning-message').html(`
                    <p class="mb-2">This action will result in low stock levels:</p>
                    <ul class="mb-0">
                        <li>Current: ${currentStock.toLocaleString()} ${unit}</li>
                        <li>After usage: ${remainingStock.toLocaleString()} ${unit}</li>
                        <li>Threshold: ${threshold.toLocaleString()} ${unit}</li>
                    </ul>
                `);
                
            } else if (remainingStock <= 0) {
                // Will result in out of stock
                $('#remaining-stock').html(`
                    <span class="text-danger">${remainingStock.toLocaleString()} ${unit}</span>
                    <small class="text-danger d-block">Out of stock</small>
                `);
                warningCard.removeClass('d-none');
                $('#warning-message').html(`
                    <p class="mb-2 text-danger">This action will result in out of stock:</p>
                    <ul class="mb-0">
                        <li>Current: ${currentStock.toLocaleString()} ${unit}</li>
                        <li>After usage: ${remainingStock.toLocaleString()} ${unit}</li>
                    </ul>
                    <p class="mt-2 mb-0"><strong>Please ensure you have enough stock before proceeding.</strong></p>
                `);
                
            } else {
                // Normal stock level
                $('#remaining-stock').html(`
                    <span class="text-success">${remainingStock.toLocaleString()} ${unit}</span>
                    <small class="text-muted d-block">(-${quantity.toLocaleString()})</small>
                `);
            }
        }
    }
    
    // Pre-select item if provided in URL
    <?php if ($preSelectedItem): ?>
    $('#item_id').val('<?php echo $preSelectedItem; ?>').trigger('change');
    <?php endif; ?>
});
</script>

<?php include_once '../includes/footer.php'; ?>
