<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Low Stock Report';

// Get low stock items
$lowStockItems = getLowStockItems();

// Separate out of stock and low stock items
$outOfStockItems = array_filter($lowStockItems, function($item) {
    return $item['quantity'] == 0;
});

$lowStockOnlyItems = array_filter($lowStockItems, function($item) {
    return $item['quantity'] > 0;
});

// Get category breakdown
$categoryBreakdown = fetchAll("
    SELECT 
        c.name as category_name,
        COUNT(i.id) as total_items,
        SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN i.quantity > 0 AND i.quantity <= i.low_stock_threshold THEN 1 ELSE 0 END) as low_stock
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.id
    GROUP BY c.id, c.name
    HAVING out_of_stock > 0 OR low_stock > 0
    ORDER BY (out_of_stock + low_stock) DESC
");

// Get recent stock out movements for critical items
$criticalItemIds = array_column($outOfStockItems, 'id');
$recentUsage = [];
if (!empty($criticalItemIds)) {
    $placeholders = str_repeat('?,', count($criticalItemIds) - 1) . '?';
    $recentUsage = fetchAll("
        SELECT so.*, i.name as item_name, i.unit
        FROM stock_out so
        LEFT JOIN items i ON so.item_id = i.id
        WHERE so.item_id IN ({$placeholders})
        ORDER BY so.date DESC
        LIMIT 10
    ", $criticalItemIds);
}

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Report</h2>
    <div>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportToCSV('#low-stock-table', 'low-stock-report.csv')">
            <i class="fas fa-download me-1"></i>Export CSV
        </button>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="printPage('report-content')">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <a href="stock-in.php" class="btn btn-success btn-sm">
            <i class="fas fa-plus me-1"></i>Add Stock
        </a>
    </div>
</div>

<div id="report-content">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start-danger">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Out of Stock</div>
                            <div class="h4 mb-0 text-danger"><?php echo count($outOfStockItems); ?></div>
                            <small class="text-muted">Items with 0 quantity</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start-warning">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock</div>
                            <div class="h4 mb-0 text-warning"><?php echo count($lowStockOnlyItems); ?></div>
                            <small class="text-muted">Items below threshold</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start-info">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Issues</div>
                            <div class="h4 mb-0 text-info"><?php echo count($lowStockItems); ?></div>
                            <small class="text-muted">Items needing attention</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-start-success">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Categories Affected</div>
                            <div class="h4 mb-0 text-success"><?php echo count($categoryBreakdown); ?></div>
                            <small class="text-muted">Product categories</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($lowStockItems)): ?>
    <!-- No Issues -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
            <h3 class="text-success mb-3">All Stock Levels Look Good!</h3>
            <p class="text-muted mb-4">
                All your inventory items are currently above their low stock thresholds. 
                Keep monitoring your stock levels to maintain optimal inventory.
            </p>
            <div>
                <a href="../dashboard.php" class="btn btn-success me-2">
                    <i class="fas fa-tachometer-alt me-1"></i>Go to Dashboard
                </a>
                <a href="items.php" class="btn btn-outline-primary">
                    <i class="fas fa-boxes me-1"></i>View All Items
                </a>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    
    <!-- Out of Stock Items (Critical) -->
    <?php if (!empty($outOfStockItems)): ?>
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="fas fa-times-circle me-2"></i>
                Critical: Out of Stock Items (<?php echo count($outOfStockItems); ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Immediate Action Required:</strong> These items have zero stock and need urgent restocking.
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Threshold</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outOfStockItems as $item): ?>
                        <tr class="table-danger">
                            <td>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td>
                                <span class="badge bg-danger fs-6">0</span>
                            </td>
                            <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="stock-in.php?item_id=<?php echo $item['id']; ?>" 
                                       class="btn btn-success" title="Add Stock">
                                        <i class="fas fa-plus"></i> Add Stock
                                    </a>
                                    <a href="items.php?action=view&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Low Stock Items (Warning) -->
    <?php if (!empty($lowStockOnlyItems)): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Warning: Low Stock Items (<?php echo count($lowStockOnlyItems); ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Action Recommended:</strong> These items are running low and should be restocked soon.
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Threshold</th>
                            <th>Difference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockOnlyItems as $item): ?>
                        <tr class="table-warning">
                            <td>
                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td>
                                <span class="badge bg-warning text-dark fs-6">
                                    <?php echo number_format($item['quantity']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                            <td>
                                <span class="text-danger">
                                    <?php echo number_format($item['low_stock_threshold'] - $item['quantity']); ?>
                                    below threshold
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="stock-in.php?item_id=<?php echo $item['id']; ?>" 
                                       class="btn btn-success" title="Add Stock">
                                        <i class="fas fa-plus"></i> Add Stock
                                    </a>
                                    <a href="items.php?action=view&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- All Low Stock Items Table (for export) -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-table me-2"></i>Complete Low Stock Report
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive" id="low-stock-table">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Low Stock Threshold</th>
                            <th>Status</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                        <tr class="<?php echo $item['quantity'] == 0 ? 'table-danger' : 'table-warning'; ?>">
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td><?php echo number_format($item['quantity']); ?></td>
                            <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                            <td>
                                <?php if ($item['quantity'] == 0): ?>
                                    Out of Stock
                                <?php else: ?>
                                    Low Stock
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['quantity'] == 0): ?>
                                    <span class="badge bg-danger">Critical</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Medium</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Category Breakdown -->
    <?php if (!empty($categoryBreakdown)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-chart-pie me-2"></i>Category Breakdown
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total Items</th>
                            <th>Out of Stock</th>
                            <th>Low Stock</th>
                            <th>Issues</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryBreakdown as $category): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></strong>
                            </td>
                            <td><?php echo number_format($category['total_items']); ?></td>
                            <td>
                                <?php if ($category['out_of_stock'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $category['out_of_stock']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($category['low_stock'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><?php echo $category['low_stock']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $category['out_of_stock'] + $category['low_stock']; ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Usage of Critical Items -->
    <?php if (!empty($recentUsage)): ?>
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-history me-2"></i>Recent Usage of Out-of-Stock Items
            </h6>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                Recent stock movements for items that are currently out of stock:
            </p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Quantity Used</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsage as $usage): ?>
                        <tr>
                            <td class="format-date"><?php echo $usage['date']; ?></td>
                            <td><?php echo htmlspecialchars($usage['item_name']); ?></td>
                            <td>
                                <span class="text-danger">
                                    -<?php echo number_format($usage['quantity']); ?> <?php echo htmlspecialchars($usage['unit']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($usage['purpose']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Report Footer -->
<div class="text-center mt-4 no-print">
    <small class="text-muted">
        Report generated on <?php echo date('F j, Y \a\t g:i A'); ?> by <?php echo htmlspecialchars($_SESSION['user_name']); ?>
    </small>
</div>

<?php include_once '../includes/footer.php'; ?>
