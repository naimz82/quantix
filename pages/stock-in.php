<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Stock In - Add Inventory';
$action = $_GET['action'] ?? 'add';
$preSelectedItem = $_GET['item_id'] ?? null;
$preSelectedSupplier = $_GET['supplier_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: stock-in.php');
        exit();
    }
    
    $itemId = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    $unitPrice = (float)$_POST['unit_price'] ?? 0.00;
    $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
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
    
    if ($unitPrice < 0) {
        $errors[] = 'Unit price cannot be negative.';
    }
    
    if (empty($date)) {
        $errors[] = 'Please select a date.';
    }
    
    if (empty($errors)) {
        $result = addStockIn($itemId, $quantity, $supplierId, $date, $remarks, $unitPrice);
        
        if ($result) {
            $totalCost = $quantity * $unitPrice;
            setAlert("Successfully added {$quantity} units to inventory!" . 
                     ($totalCost > 0 ? " Total cost: $" . number_format($totalCost, 2) : ""), 'success');
            header('Location: stock-in.php');
            exit();
        } else {
            setAlert('Failed to add stock. Please try again.', 'danger');
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
    ORDER BY i.name
");

$suppliers = fetchAll("SELECT * FROM suppliers ORDER BY name");

// Get recent stock in entries
$recentEntries = fetchAll("
    SELECT si.*, i.name as item_name, i.unit, s.name as supplier_name
    FROM stock_in si
    LEFT JOIN items i ON si.item_id = i.id
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    ORDER BY si.created_at DESC
    LIMIT 10
");

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-arrow-down me-2 text-success"></i>Stock In - Add Inventory</h2>
    <div>
        <a href="../pages/stock-history.php?type=in" class="btn btn-outline-info">
            <i class="fas fa-history me-2"></i>View History
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Stock In Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Stock Entry</h5>
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
                                        <?php echo $preSelectedItem == $item['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['name']); ?>
                                    <?php if ($item['category_name']): ?>
                                        (<?php echo htmlspecialchars($item['category_name']); ?>)
                                    <?php endif; ?>
                                    - Current: <?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select an item.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select select2" id="supplier_id" name="supplier_id">
                                <option value="">Select Supplier (Optional)</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>"
                                        <?php echo $preSelectedSupplier == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="quantity" class="form-label">Quantity *</label>
                            <div class="input-group">
                                <input type="number" class="form-control numeric-input" id="quantity" name="quantity" 
                                       min="1" step="1" required>
                                <span class="input-group-text" id="unit-display">units</span>
                            </div>
                            <div class="invalid-feedback">
                                Please enter a valid quantity.
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="unit_price" class="form-label">Unit Price</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control numeric-input" id="unit_price" name="unit_price" 
                                       min="0" step="0.01" value="0.00">
                            </div>
                            <small class="form-text text-muted">Price per unit from supplier</small>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Please select a date.
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Total Cost</label>
                            <div class="form-control-plaintext fw-bold text-success" id="total-cost">
                                $0.00
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Stock Level</label>
                            <div class="form-control-plaintext fw-bold" id="new-stock">
                                Select item first
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                  placeholder="Optional notes about this stock entry..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add Stock
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
        
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Today's Summary</h6>
            </div>
            <div class="card-body">
                <?php
                $todayStats = fetchOne("
                    SELECT COUNT(*) as entries, SUM(quantity) as total_quantity
                    FROM stock_in 
                    WHERE DATE(date) = CURDATE()
                ");
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-success mb-1"><?php echo number_format($todayStats['entries'] ?? 0); ?></h4>
                            <small class="text-muted">Entries</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-primary mb-1"><?php echo number_format($todayStats['total_quantity'] ?? 0); ?></h4>
                        <small class="text-muted">Items Added</small>
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
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Stock In Entries</h6>
                <a href="stock-history.php?type=in" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-log">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Cost</th>
                                <th>Supplier</th>
                                <th>Remarks</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEntries as $entry): ?>
                            <tr>
                                <td class="format-date"><?php echo $entry['date']; ?></td>
                                <td><?php echo htmlspecialchars($entry['item_name']); ?></td>
                                <td>
                                    <span class="text-success fw-bold">
                                        +<?php echo number_format($entry['quantity']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($entry['unit']); ?>
                                </td>
                                <td>
                                    <?php if ($entry['unit_price'] > 0): ?>
                                        <span class="text-info">$<?php echo number_format($entry['unit_price'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry['total_cost'] > 0): ?>
                                        <span class="text-success fw-bold">$<?php echo number_format($entry['total_cost'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['supplier_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($entry['remarks'])): ?>
                                        <span class="text-truncate" style="max-width: 100px;" 
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

<?php 
// Custom JavaScript to run after jQuery is loaded
$customScript = '
$(document).ready(function() {
    // Update item information when item is selected
    $("#item_id").on("change", function() {
        var selectedOption = $(this).find("option:selected");
        var itemId = $(this).val();
        
        if (itemId) {
            var unit = selectedOption.data("unit");
            var currentStock = selectedOption.data("current");
            var itemName = selectedOption.text().split(" - Current:")[0];
            
            // Update unit display
            $("#unit-display").text(unit);
            
            // Update item info panel
            $("#item-info").html(
                "<div class=\"text-start\">" +
                    "<h6 class=\"mb-2\">" + itemName + "</h6>" +
                    "<p class=\"mb-1\"><strong>Current Stock:</strong> " + currentStock.toLocaleString() + " " + unit + "</p>" +
                    "<p class=\"mb-1\"><strong>Unit:</strong> " + unit + "</p>" +
                "</div>"
            );
            
            // Calculate new stock level when quantity changes
            updateNewStockLevel();
        } else {
            $("#unit-display").text("units");
            $("#item-info").html(
                "<div class=\"text-muted text-center py-3\">" +
                    "<i class=\"fas fa-box fa-2x mb-2\"></i>" +
                    "<p class=\"mb-0\">Select an item to view details</p>" +
                "</div>"
            );
            $("#new-stock").text("Select item first");
        }
    });
    
    // Update new stock level when quantity changes
    $("#quantity").on("input", function() {
        updateNewStockLevel();
        updateTotalCost();
    });
    
    // Update total cost when unit price changes
    $("#unit_price").on("input", updateTotalCost);
    
    function updateNewStockLevel() {
        var selectedOption = $("#item_id").find("option:selected");
        var currentStock = selectedOption.data("current");
        var quantity = parseInt($("#quantity").val()) || 0;
        var unit = selectedOption.data("unit") || "units";
        
        if (currentStock !== undefined) {
            if (quantity > 0) {
                var newStock = currentStock + quantity;
                $("#new-stock").html(
                    "<span class=\"text-success\">" + newStock.toLocaleString() + " " + unit + "</span>" +
                    "<small class=\"text-muted d-block\">(+" + quantity.toLocaleString() + ")</small>"
                );
            } else {
                // Show current stock even when no quantity is entered
                $("#new-stock").html(
                    "<span class=\"text-muted\">" + currentStock.toLocaleString() + " " + unit + "</span>" +
                    "<small class=\"text-muted d-block\">Enter quantity to see new level</small>"
                );
            }
        } else {
            $("#new-stock").text("Select item first");
        }
    }
    
    function updateTotalCost() {
        var quantity = parseInt($("#quantity").val()) || 0;
        var unitPrice = parseFloat($("#unit_price").val()) || 0;
        var totalCost = quantity * unitPrice;
        
        if (totalCost > 0) {
            $("#total-cost").html(
                "<span class=\"text-success\">$" + totalCost.toFixed(2) + "</span>"
            );
        } else {
            $("#total-cost").text("$0.00");
        }
    }
    ' . 
    ($preSelectedItem ? '
    // Pre-select item if provided in URL
    setTimeout(function() {
        $("#item_id").val("' . $preSelectedItem . '").trigger("change");
    }, 300);
    ' : '') . 
    ($preSelectedSupplier ? '
    // Pre-select supplier if provided in URL  
    setTimeout(function() {
        $("#supplier_id").val("' . $preSelectedSupplier . '").trigger("change");
    }, 300);
    ' : '') . '
    
    // Also trigger change on page load if item is already selected
    if ($("#item_id").val()) {
        $("#item_id").trigger("change");
    }
});
';
?>

<?php include_once '../includes/footer.php'; ?>
