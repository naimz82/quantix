<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Reports & Export';

include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-export me-2"></i>Reports & Export
        </h1>
    </div>

    <div class="row">
        <!-- Export Options -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-download me-2"></i>Export Data
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Items Export -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-boxes text-primary me-2"></i>Items
                                    </h5>
                                    <p class="card-text">Export all items with their current stock levels and details.</p>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm export-btn" 
                                                data-type="items" data-format="csv">
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm export-btn" 
                                                data-type="items" data-format="json">
                                            <i class="fas fa-file-code me-1"></i>JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories Export -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-tags text-success me-2"></i>Categories
                                    </h5>
                                    <p class="card-text">Export all categories with item counts.</p>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-success btn-sm export-btn" 
                                                data-type="categories" data-format="csv">
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm export-btn" 
                                                data-type="categories" data-format="json">
                                            <i class="fas fa-file-code me-1"></i>JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Suppliers Export -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-truck text-info me-2"></i>Suppliers
                                    </h5>
                                    <p class="card-text">Export all suppliers with their contact information.</p>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-info btn-sm export-btn" 
                                                data-type="suppliers" data-format="csv">
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-sm export-btn" 
                                                data-type="suppliers" data-format="json">
                                            <i class="fas fa-file-code me-1"></i>JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Movements Export -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-exchange-alt text-warning me-2"></i>Stock Movements
                                    </h5>
                                    <p class="card-text">Export stock in/out movements with date range.</p>
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <input type="date" class="form-control form-control-sm" id="movements-start-date" 
                                                       value="<?php echo date('Y-m-01'); ?>">
                                            </div>
                                            <div class="col-6">
                                                <input type="date" class="form-control form-control-sm" id="movements-end-date" 
                                                       value="<?php echo date('Y-m-t'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-warning btn-sm export-btn" 
                                                data-type="movements" data-format="csv">
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                        <button type="button" class="btn btn-outline-warning btn-sm export-btn" 
                                                data-type="movements" data-format="json">
                                            <i class="fas fa-file-code me-1"></i>JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Export -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-danger">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>Low Stock Report
                                    </h5>
                                    <p class="card-text">Export items with low stock or out of stock.</p>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-danger btn-sm export-btn" 
                                                data-type="low-stock" data-format="csv">
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm export-btn" 
                                                data-type="low-stock" data-format="json">
                                            <i class="fas fa-file-code me-1"></i>JSON
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Reports -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-print me-2"></i>Print Reports
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary print-btn" data-type="items">
                            <i class="fas fa-boxes me-2"></i>Items Report
                        </button>
                        <button type="button" class="btn btn-outline-warning print-btn" data-type="stock-history">
                            <i class="fas fa-history me-2"></i>Stock History
                        </button>
                        <button type="button" class="btn btn-outline-danger print-btn" data-type="low-stock">
                            <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Report
                        </button>
                        <button type="button" class="btn btn-outline-success print-btn" data-type="categories">
                            <i class="fas fa-tags me-2"></i>Categories Report
                        </button>
                        <button type="button" class="btn btn-outline-info print-btn" data-type="suppliers">
                            <i class="fas fa-truck me-2"></i>Suppliers Report
                        </button>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">Custom Date Range (for Stock History)</h6>
                    <div class="mb-3">
                        <label for="print-start-date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="print-start-date" 
                               value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="print-end-date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="print-end-date" 
                               value="<?php echo date('Y-m-t'); ?>">
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Quick Stats
                </div>
                <div class="card-body">
                    <?php
                    $stats = [
                        'total_items' => fetchOne("SELECT COUNT(*) as count FROM items")['count'],
                        'total_categories' => fetchOne("SELECT COUNT(*) as count FROM categories")['count'],
                        'total_suppliers' => fetchOne("SELECT COUNT(*) as count FROM suppliers")['count'],
                        'low_stock_items' => count(getLowStockItems()),
                        'out_of_stock' => fetchOne("SELECT COUNT(*) as count FROM items WHERE quantity = 0")['count'],
                        'total_value' => fetchOne("SELECT SUM(quantity * price) as total FROM items WHERE price IS NOT NULL")['total'] ?? 0
                    ];
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border-bottom pb-2">
                                <h5 class="mb-0 text-primary"><?php echo number_format($stats['total_items']); ?></h5>
                                <small class="text-muted">Total Items</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border-bottom pb-2">
                                <h5 class="mb-0 text-success"><?php echo number_format($stats['total_categories']); ?></h5>
                                <small class="text-muted">Categories</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border-bottom pb-2">
                                <h5 class="mb-0 text-info"><?php echo number_format($stats['total_suppliers']); ?></h5>
                                <small class="text-muted">Suppliers</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border-bottom pb-2">
                                <h5 class="mb-0 text-warning"><?php echo number_format($stats['low_stock_items']); ?></h5>
                                <small class="text-muted">Low Stock</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h5 class="mb-0 text-danger"><?php echo number_format($stats['out_of_stock']); ?></h5>
                            <small class="text-muted">Out of Stock</small>
                        </div>
                        <div class="col-6">
                            <h5 class="mb-0 text-dark">$<?php echo number_format($stats['total_value'], 2); ?></h5>
                            <small class="text-muted">Total Value</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Export functionality
    $('.export-btn').on('click', function() {
        var type = $(this).data('type');
        var format = $(this).data('format');
        var url = '../exports/export.php?type=' + type + '&format=' + format;
        
        // Add date range for movements
        if (type === 'movements') {
            var startDate = $('#movements-start-date').val();
            var endDate = $('#movements-end-date').val();
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        }
        
        // Show loading state
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Exporting...')
             .prop('disabled', true);
        
        // Create download link
        var link = document.createElement('a');
        link.href = url;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Reset button after delay
        setTimeout(function() {
            $btn.html(originalText).prop('disabled', false);
        }, 2000);
    });
    
    // Print functionality
    $('.print-btn').on('click', function() {
        var type = $(this).data('type');
        var url = '../exports/print.php?type=' + type;
        
        // Add date range for stock history
        if (type === 'stock-history') {
            var startDate = $('#print-start-date').val();
            var endDate = $('#print-end-date').val();
            url += '&start_date=' + startDate + '&end_date=' + endDate;
        }
        
        // Open print window
        var printWindow = window.open(url, '_blank');
        printWindow.onload = function() {
            printWindow.print();
        };
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
