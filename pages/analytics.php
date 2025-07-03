<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Analytics & Reports';

// Get date filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Analytics data
$analytics = [
    'inventory_value' => getInventoryValue(),
    'movement_trends' => getMovementTrends($dateFrom, $dateTo),
    'category_performance' => getCategoryPerformance(),
    'supplier_performance' => getSupplierPerformance(),
    'stock_turnover' => getStockTurnoverRate(),
    'critical_insights' => getCriticalInsights()
];

function getInventoryValue() {
    return fetchOne("
        SELECT 
            SUM(inventory_value.total_value) as total_value,
            COUNT(inventory_value.item_id) as total_items,
            AVG(inventory_value.total_value) as avg_item_value
        FROM (
            SELECT 
                i.id as item_id,
                i.quantity * COALESCE(latest_prices.avg_price, 0) as total_value
            FROM items i
            LEFT JOIN (
                SELECT 
                    si.item_id,
                    AVG(si.unit_price) as avg_price
                FROM stock_in si
                INNER JOIN (
                    SELECT item_id, MAX(date) as latest_date
                    FROM stock_in 
                    WHERE unit_price > 0
                    GROUP BY item_id
                ) latest ON si.item_id = latest.item_id 
                    AND si.date >= DATE_SUB(latest.latest_date, INTERVAL 30 DAY)
                WHERE si.unit_price > 0
                GROUP BY si.item_id
            ) latest_prices ON i.id = latest_prices.item_id
            WHERE i.status = 'active'
        ) inventory_value
    ");
}

function getMovementTrends($dateFrom, $dateTo) {
    $sql = "
        SELECT 
            DATE(si.date) as date,
            'Stock In' as type,
            SUM(si.quantity) as quantity,
            COUNT(*) as transactions
        FROM stock_in si
        WHERE si.date BETWEEN ? AND ?
        GROUP BY DATE(si.date)
        
        UNION ALL
        
        SELECT 
            DATE(so.date) as date,
            'Stock Out' as type,
            SUM(so.quantity) as quantity,
            COUNT(*) as transactions
        FROM stock_out so
        WHERE so.date BETWEEN ? AND ?
        GROUP BY DATE(so.date)
        
        ORDER BY date DESC
    ";
    
    return fetchAll($sql, [$dateFrom, $dateTo, $dateFrom, $dateTo]);
}

function getCategoryPerformance() {
    return fetchAll("
        SELECT 
            c.name as category,
            COUNT(i.id) as item_count,
            SUM(i.quantity) as total_stock,
            SUM(i.quantity * COALESCE(latest_prices.avg_price, 0)) as total_value,
            AVG(i.quantity) as avg_stock_per_item,
            COUNT(CASE WHEN i.quantity <= i.low_stock_threshold THEN 1 END) as low_stock_items
        FROM categories c
        LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
        LEFT JOIN (
            SELECT 
                si.item_id,
                AVG(si.unit_price) as avg_price
            FROM stock_in si
            INNER JOIN (
                SELECT item_id, MAX(date) as latest_date
                FROM stock_in 
                WHERE unit_price > 0
                GROUP BY item_id
            ) latest ON si.item_id = latest.item_id 
                AND si.date >= DATE_SUB(latest.latest_date, INTERVAL 30 DAY)
            WHERE si.unit_price > 0
            GROUP BY si.item_id
        ) latest_prices ON i.id = latest_prices.item_id
        GROUP BY c.id, c.name
        HAVING item_count > 0
        ORDER BY total_value DESC
    ");
}

function getSupplierPerformance() {
    return fetchAll("
        SELECT 
            s.name as supplier,
            COUNT(DISTINCT si.item_id) as items_supplied,
            COUNT(si.id) as deliveries,
            SUM(si.quantity) as total_delivered,
            AVG(si.quantity) as avg_delivery_size,
            MAX(si.date) as last_delivery,
            SUM(si.total_cost) as total_value_delivered,
            AVG(si.unit_price) as avg_unit_price
        FROM suppliers s
        JOIN stock_in si ON s.id = si.supplier_id
        WHERE si.date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY s.id, s.name
        HAVING deliveries > 0
        ORDER BY total_value_delivered DESC
    ");
}

function getStockTurnoverRate() {
    return fetchAll("
        SELECT 
            i.name as item,
            i.quantity as current_stock,
            COALESCE(usage.total_used, 0) as total_usage_3m,
            CASE 
                WHEN i.quantity > 0 AND usage.total_used > 0 
                THEN ROUND((usage.total_used / i.quantity), 2)
                ELSE 0 
            END as turnover_rate,
            CASE 
                WHEN usage.total_used > 0 
                THEN ROUND((i.quantity / (usage.total_used / 3)), 1)
                ELSE 999 
            END as months_of_stock
        FROM items i
        LEFT JOIN (
            SELECT 
                item_id, 
                SUM(quantity) as total_used
            FROM stock_out
            WHERE date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY item_id
        ) usage ON i.id = usage.item_id
        WHERE i.status = 'active'
        ORDER BY turnover_rate DESC
    ");
}

function getCriticalInsights() {
    $insights = [];
    
    // Dead stock (no movement in 6 months) with estimated value
    $deadStock = fetchAll("
        SELECT 
            i.name, 
            i.quantity, 
            COALESCE(latest_prices.avg_price, 0) as estimated_price,
            (i.quantity * COALESCE(latest_prices.avg_price, 0)) as estimated_value
        FROM items i
        LEFT JOIN (
            SELECT 
                si.item_id,
                AVG(si.unit_price) as avg_price
            FROM stock_in si
            INNER JOIN (
                SELECT item_id, MAX(date) as latest_date
                FROM stock_in 
                WHERE unit_price > 0
                GROUP BY item_id
            ) latest ON si.item_id = latest.item_id 
                AND si.date >= DATE_SUB(latest.latest_date, INTERVAL 30 DAY)
            WHERE si.unit_price > 0
            GROUP BY si.item_id
        ) latest_prices ON i.id = latest_prices.item_id
        WHERE i.status = 'active'
        AND i.id NOT IN (
            SELECT DISTINCT item_id 
            FROM stock_out 
            WHERE date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        )
        AND i.quantity > 0
        ORDER BY estimated_value DESC
        LIMIT 10
    ");
    
    // Fast moving items (high turnover)
    $fastMoving = fetchAll("
        SELECT 
            i.name, 
            COUNT(so.id) as transactions,
            SUM(so.quantity) as total_used,
            AVG(so.quantity) as avg_usage
        FROM items i
        JOIN stock_out so ON i.id = so.item_id
        WHERE so.date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
        GROUP BY i.id, i.name
        HAVING transactions >= 5
        ORDER BY total_used DESC
        LIMIT 10
    ");
    
    // Stockout frequency
    $stockoutFreq = fetchAll("
        SELECT 
            i.name,
            COUNT(CASE WHEN i.quantity = 0 THEN 1 END) as stockout_days,
            MIN(si.date) as first_recorded,
            DATEDIFF(NOW(), MIN(si.date)) as days_tracked
        FROM items i
        LEFT JOIN stock_in si ON i.id = si.item_id
        WHERE i.status = 'active'
        GROUP BY i.id, i.name
        HAVING days_tracked > 30
        ORDER BY stockout_days DESC
        LIMIT 10
    ");
    
    return [
        'dead_stock' => $deadStock,
        'fast_moving' => $fastMoving,
        'stockout_frequency' => $stockoutFreq
    ];
}

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-line me-2"></i>Analytics & Reports</h2>
    <div>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportAnalytics()">
            <i class="fas fa-download me-1"></i>Export Data
        </button>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i>Apply Filter
                </button>
            </div>
        </form>
        
        <!-- Filter Summary -->
        <div class="mt-3 text-muted">
            <small>
                <i class="fas fa-info-circle me-1"></i>
                Showing data from <?php echo date('M d, Y', strtotime($dateFrom)); ?> to <?php echo date('M d, Y', strtotime($dateTo)); ?>
                (<?php echo count($analytics['movement_trends']); ?> movement records found)
            </small>
        </div>
    </div>
</div>

<!-- Inventory Value Overview -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Inventory Value</div>
                        <div class="h4 mb-0">$<?php echo number_format($analytics['inventory_value']['total_value'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-primary"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Items</div>
                        <div class="h4 mb-0"><?php echo number_format($analytics['inventory_value']['total_items'] ?? 0); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-success"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg Item Value</div>
                        <div class="h4 mb-0">$<?php echo number_format($analytics['inventory_value']['avg_item_value'] ?? 0, 2); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calculator fa-2x text-info"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Dead Stock Value</div>
                        <div class="h4 mb-0">
                            $<?php 
                            $deadStockValue = array_sum(array_column($analytics['critical_insights']['dead_stock'], 'value'));
                            echo number_format($deadStockValue, 2); 
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Movement Trends Chart -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Stock Movement Trends</h6>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['movement_trends'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">No Movement Data</h6>
                        <p class="text-muted mb-0">No stock movements found for the selected date range.</p>
                        <small class="text-muted">Try adjusting the date range or add some stock movements.</small>
                    </div>
                <?php else: ?>
                    <canvas id="movementTrendsChart" width="400" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Quick Stats</h6>
            </div>
            <div class="card-body">
                <?php 
                $totalIn = array_sum(array_column(array_filter($analytics['movement_trends'], function($t) { return $t['type'] === 'Stock In'; }), 'quantity'));
                $totalOut = array_sum(array_column(array_filter($analytics['movement_trends'], function($t) { return $t['type'] === 'Stock Out'; }), 'quantity'));
                $netMovement = $totalIn - $totalOut;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Stock In:</span>
                        <span class="text-success fw-bold">+<?php echo number_format($totalIn); ?></span>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Stock Out:</span>
                        <span class="text-danger fw-bold">-<?php echo number_format($totalOut); ?></span>
                    </div>
                </div>
                <hr>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span><strong>Net Movement:</strong></span>
                        <span class="fw-bold <?php echo $netMovement >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($netMovement >= 0 ? '+' : '') . number_format($netMovement); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Performance -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Category Performance</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Items</th>
                        <th>Total Stock</th>
                        <th>Total Value</th>
                        <th>Avg Stock/Item</th>
                        <th>Low Stock Items</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['category_performance'] as $category): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($category['category']); ?></strong></td>
                        <td><?php echo number_format($category['item_count']); ?></td>
                        <td><?php echo number_format($category['total_stock']); ?></td>
                        <td>$<?php echo number_format($category['total_value'], 2); ?></td>
                        <td><?php echo number_format($category['avg_stock_per_item'], 1); ?></td>
                        <td>
                            <?php if ($category['low_stock_items'] > 0): ?>
                                <span class="badge bg-warning"><?php echo $category['low_stock_items']; ?></span>
                            <?php else: ?>
                                <span class="text-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $performance = ($category['low_stock_items'] / $category['item_count']) * 100;
                            if ($performance == 0): ?>
                                <span class="badge bg-success">Excellent</span>
                            <?php elseif ($performance <= 20): ?>
                                <span class="badge bg-info">Good</span>
                            <?php elseif ($performance <= 50): ?>
                                <span class="badge bg-warning">Fair</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Poor</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Stock Turnover Analysis -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Stock Turnover Analysis</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Current Stock</th>
                        <th>3-Month Usage</th>
                        <th>Turnover Rate</th>
                        <th>Months of Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($analytics['stock_turnover'], 0, 15) as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item']); ?></td>
                        <td><?php echo number_format($item['current_stock']); ?></td>
                        <td><?php echo number_format($item['total_usage_3m']); ?></td>
                        <td><?php echo $item['turnover_rate']; ?>x</td>
                        <td><?php echo $item['months_of_stock']; ?> months</td>
                        <td>
                            <?php if ($item['turnover_rate'] >= 2): ?>
                                <span class="badge bg-success">Fast Moving</span>
                            <?php elseif ($item['turnover_rate'] >= 0.5): ?>
                                <span class="badge bg-info">Normal</span>
                            <?php elseif ($item['turnover_rate'] > 0): ?>
                                <span class="badge bg-warning">Slow Moving</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Dead Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Critical Insights -->
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-warning">
                <h6 class="mb-0"><i class="fas fa-pause-circle me-2"></i>Dead Stock Items</h6>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['critical_insights']['dead_stock'])): ?>
                    <p class="text-success"><i class="fas fa-check-circle me-2"></i>No dead stock found!</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($analytics['critical_insights']['dead_stock'], 0, 5) as $item): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="text-warning fw-bold">$<?php echo number_format($item['value'], 2); ?></span>
                            </div>
                            <small class="text-muted"><?php echo number_format($item['quantity']); ?> units</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-success">
                <h6 class="mb-0"><i class="fas fa-rocket me-2"></i>Fast Moving Items</h6>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['critical_insights']['fast_moving'])): ?>
                    <p class="text-muted">No fast moving items identified.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($analytics['critical_insights']['fast_moving'], 0, 5) as $item): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="text-success fw-bold"><?php echo number_format($item['total_used']); ?></span>
                            </div>
                            <small class="text-muted"><?php echo $item['transactions']; ?> transactions</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-danger">
                <h6 class="mb-0"><i class="fas fa-times-circle me-2"></i>Frequent Stockouts</h6>
            </div>
            <div class="card-body">
                <?php if (empty($analytics['critical_insights']['stockout_frequency'])): ?>
                    <p class="text-success"><i class="fas fa-check-circle me-2"></i>No frequent stockouts!</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($analytics['critical_insights']['stockout_frequency'], 0, 5) as $item): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="text-danger fw-bold"><?php echo $item['stockout_days']; ?> days</span>
                            </div>
                            <small class="text-muted">in <?php echo $item['days_tracked']; ?> days tracked</small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        document.getElementById('movementTrendsChart').innerHTML = '<p class="text-center text-danger">Chart library failed to load</p>';
        return;
    }
    
    // Movement Trends Chart
    const movementData = <?php echo json_encode($analytics['movement_trends']); ?>;
    console.log('Movement data:', movementData);

    // Check if we have any data
    if (!movementData || movementData.length === 0) {
        console.log('No movement data available');
        const chartContainer = document.getElementById('movementTrendsChart').parentElement;
        chartContainer.innerHTML = '<p class="text-center text-muted">No movement data available for the selected date range.</p>';
        return;
    }
    
    try {
        const dates = [...new Set(movementData.map(d => d.date))].sort();
        console.log('Dates:', dates);
        
        const stockInData = dates.map(date => {
            const record = movementData.find(d => d.date === date && d.type === 'Stock In');
            return record ? parseInt(record.quantity) : 0;
        });
        const stockOutData = dates.map(date => {
            const record = movementData.find(d => d.date === date && d.type === 'Stock Out');
            return record ? parseInt(record.quantity) : 0;
        });

        console.log('Stock In Data:', stockInData);
        console.log('Stock Out Data:', stockOutData);

        const chartCanvas = document.getElementById('movementTrendsChart');
        if (!chartCanvas) {
            console.error('Chart canvas not found');
            return;
        }

        new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: dates.map(date => moment(date).format('MMM DD')),
                datasets: [{
                    label: 'Stock In',
                    data: stockInData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.1
                }, {
                    label: 'Stock Out',
                    data: stockOutData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    title: {
                        display: true,
                        text: 'Stock Movement Trends'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
        
        console.log('Chart created successfully');
        
    } catch (error) {
        console.error('Error creating chart:', error);
        const chartContainer = document.getElementById('movementTrendsChart').parentElement;
        chartContainer.innerHTML = '<p class="text-center text-danger">Error creating chart: ' + error.message + '</p>';
    }
});

function exportAnalytics() {
    window.location.href = '../exports/export.php?type=analytics&format=csv';
}
</script>

<?php include_once '../includes/footer.php'; ?>
