<?php
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';

// Get dashboard statistics
$totalItems = fetchOne("SELECT COUNT(*) as count FROM items")['count'];
$lowStockItems = getLowStockItems();
$lowStockCount = count($lowStockItems);
$totalCategories = fetchOne("SELECT COUNT(*) as count FROM categories")['count'];
$totalSuppliers = fetchOne("SELECT COUNT(*) as count FROM suppliers")['count'];

// Get recent stock movements
$recentStockIn = fetchAll("
    SELECT si.*, i.name as item_name, s.name as supplier_name
    FROM stock_in si
    LEFT JOIN items i ON si.item_id = i.id
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    ORDER BY si.created_at DESC
    LIMIT 5
");

$recentStockOut = fetchAll("
    SELECT so.*, i.name as item_name
    FROM stock_out so
    LEFT JOIN items i ON so.item_id = i.id
    ORDER BY so.created_at DESC
    LIMIT 5
");

// Get stock status data for chart
$stockStats = [
    'in_stock' => fetchOne("SELECT COUNT(*) as count FROM items WHERE quantity > low_stock_threshold")['count'],
    'low_stock' => fetchOne("SELECT COUNT(*) as count FROM items WHERE quantity <= low_stock_threshold AND quantity > 0")['count'],
    'out_of_stock' => fetchOne("SELECT COUNT(*) as count FROM items WHERE quantity = 0")['count']
];

include_once 'includes/header.php';
?>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card border-start-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Items</div>
                        <div class="h5 mb-0 font-weight-bold" id="total-items"><?php echo number_format($totalItems); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card border-start-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Items</div>
                        <div class="h5 mb-0 font-weight-bold" id="low-stock-count"><?php echo number_format($lowStockCount); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card border-start-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Categories</div>
                        <div class="h5 mb-0 font-weight-bold" id="total-categories"><?php echo number_format($totalCategories); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tags fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card dashboard-card border-start-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Suppliers</div>
                        <div class="h5 mb-0 font-weight-bold" id="total-suppliers"><?php echo number_format($totalSuppliers); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Stock Status Chart -->
    <div class="col-xl-4 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i>Stock Status Overview
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="col-xl-8 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert</span>
                <a href="<?php echo BASE_URL; ?>/pages/low-stock.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($lowStockItems)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <p class="mb-0">All items are adequately stocked!</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Threshold</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($lowStockItems, 0, 5) as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo number_format($item['low_stock_threshold']); ?></td>
                                <td>
                                    <?php if ($item['quantity'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Low Stock</span>
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
    </div>
</div>

<div class="row">
    <!-- Recent Stock In -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-arrow-down me-2"></i>Recent Stock In</span>
                <a href="<?php echo BASE_URL; ?>/pages/stock-in.php" class="btn btn-sm btn-outline-success">Add Stock</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentStockIn)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p class="mb-0">No recent stock entries</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStockIn as $stock): ?>
                            <tr>
                                <td class="format-date"><?php echo $stock['date']; ?></td>
                                <td><?php echo htmlspecialchars($stock['item_name']); ?></td>
                                <td class="text-success">+<?php echo number_format($stock['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($stock['supplier_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Stock Out -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-arrow-up me-2"></i>Recent Stock Out</span>
                <a href="<?php echo BASE_URL; ?>/pages/stock-out.php" class="btn btn-sm btn-outline-danger">Record Usage</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentStockOut)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p class="mb-0">No recent stock usage</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentStockOut as $stock): ?>
                            <tr>
                                <td class="format-date"><?php echo $stock['date']; ?></td>
                                <td><?php echo htmlspecialchars($stock['item_name']); ?></td>
                                <td class="text-danger">-<?php echo number_format($stock['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($stock['purpose']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize stock status chart
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('stockChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['In Stock', 'Low Stock', 'Out of Stock'],
            datasets: [{
                data: [<?php echo $stockStats['in_stock']; ?>, <?php echo $stockStats['low_stock']; ?>, <?php echo $stockStats['out_of_stock']; ?>],
                backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
