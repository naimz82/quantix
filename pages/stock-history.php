<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'Stock Movement History';

// Get filter parameters
$itemFilter = $_GET['item_id'] ?? '';
$supplierFilter = $_GET['supplier_id'] ?? '';
$typeFilter = $_GET['type'] ?? ''; // 'in', 'out', or empty for all
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE conditions
$whereConditions = [];
$params = [];

if ($itemFilter) {
    $whereConditions[] = "(si.item_id = ? OR so.item_id = ?)";
    $params[] = $itemFilter;
    $params[] = $itemFilter;
}

if ($supplierFilter) {
    $whereConditions[] = "si.supplier_id = ?";
    $params[] = $supplierFilter;
}

if ($dateFrom) {
    $whereConditions[] = "(si.date >= ? OR so.date >= ?)";
    $params[] = $dateFrom;
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "(si.date <= ? OR so.date <= ?)";
    $params[] = $dateTo;
    $params[] = $dateTo;
}

if ($search) {
    $whereConditions[] = "(i.name LIKE ? OR s.name LIKE ? OR so.purpose LIKE ? OR si.remarks LIKE ? OR so.remarks LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Base query for stock movements
$baseQuery = "
    SELECT 
        'in' as type,
        si.id,
        si.item_id,
        si.quantity,
        si.date,
        si.remarks,
        si.created_at,
        i.name as item_name,
        i.unit,
        s.name as supplier_name,
        si.supplier_id,
        NULL as purpose
    FROM stock_in si
    LEFT JOIN items i ON si.item_id = i.id
    LEFT JOIN suppliers s ON si.supplier_id = s.id
    
    UNION ALL
    
    SELECT 
        'out' as type,
        so.id,
        so.item_id,
        so.quantity,
        so.date,
        so.remarks,
        so.created_at,
        i.name as item_name,
        i.unit,
        NULL as supplier_name,
        NULL as supplier_id,
        so.purpose
    FROM stock_out so
    LEFT JOIN items i ON so.item_id = i.id
";

// Apply type filter
if ($typeFilter === 'in') {
    $movementsQuery = "
        SELECT 
            'in' as type,
            si.id,
            si.item_id,
            si.quantity,
            si.date,
            si.remarks,
            si.created_at,
            i.name as item_name,
            i.unit,
            s.name as supplier_name,
            si.supplier_id,
            NULL as purpose
        FROM stock_in si
        LEFT JOIN items i ON si.item_id = i.id
        LEFT JOIN suppliers s ON si.supplier_id = s.id
        {$whereClause}
        ORDER BY si.date DESC, si.created_at DESC
    ";
} elseif ($typeFilter === 'out') {
    $movementsQuery = "
        SELECT 
            'out' as type,
            so.id,
            so.item_id,
            so.quantity,
            so.date,
            so.remarks,
            so.created_at,
            i.name as item_name,
            i.unit,
            NULL as supplier_name,
            NULL as supplier_id,
            so.purpose
        FROM stock_out so
        LEFT JOIN items i ON so.item_id = i.id
        {$whereClause}
        ORDER BY so.date DESC, so.created_at DESC
    ";
} else {
    $movementsQuery = "
        SELECT * FROM ({$baseQuery}) as combined
        {$whereClause}
        ORDER BY date DESC, created_at DESC
    ";
}

// Get movements with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

$movements = fetchAll($movementsQuery . " LIMIT {$perPage} OFFSET {$offset}", $params);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM ({$baseQuery}) as combined {$whereClause}";
$totalResult = fetchOne($countQuery, $params);
$totalMovements = $totalResult['total'];
$totalPages = ceil($totalMovements / $perPage);

// Get data for filters
$items = fetchAll("SELECT id, name FROM items ORDER BY name");
$suppliers = fetchAll("SELECT id, name FROM suppliers ORDER BY name");

// Get statistics
$stats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM stock_in) as total_in,
        (SELECT COUNT(*) FROM stock_out) as total_out,
        (SELECT SUM(quantity) FROM stock_in) as total_in_qty,
        (SELECT SUM(quantity) FROM stock_out) as total_out_qty
");

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-history me-2"></i>Stock Movement History</h2>
    <div>
        <a href="stock-in.php" class="btn btn-success btn-sm">
            <i class="fas fa-arrow-down me-1"></i>Add Stock
        </a>
        <a href="stock-out.php" class="btn btn-danger btn-sm">
            <i class="fas fa-arrow-up me-1"></i>Record Usage
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Stock In</div>
                        <div class="h6 mb-0"><?php echo number_format($stats['total_in'] ?? 0); ?> entries</div>
                        <small class="text-muted"><?php echo number_format($stats['total_in_qty'] ?? 0); ?> items added</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-arrow-down fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-danger">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Stock Out</div>
                        <div class="h6 mb-0"><?php echo number_format($stats['total_out'] ?? 0); ?> entries</div>
                        <small class="text-muted"><?php echo number_format($stats['total_out_qty'] ?? 0); ?> items used</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-arrow-up fa-2x text-danger"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Movement</div>
                        <div class="h6 mb-0">
                            <?php 
                            $netMovement = ($stats['total_in_qty'] ?? 0) - ($stats['total_out_qty'] ?? 0);
                            echo ($netMovement >= 0 ? '+' : '') . number_format($netMovement);
                            ?>
                        </div>
                        <small class="text-muted">Total net change</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-balance-scale fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-start-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Movements</div>
                        <div class="h6 mb-0"><?php echo number_format(($stats['total_in'] ?? 0) + ($stats['total_out'] ?? 0)); ?></div>
                        <small class="text-muted">All transactions</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exchange-alt fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters & Search</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Item, supplier, purpose...">
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Movement Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All Types</option>
                    <option value="in" <?php echo $typeFilter === 'in' ? 'selected' : ''; ?>>Stock In</option>
                    <option value="out" <?php echo $typeFilter === 'out' ? 'selected' : ''; ?>>Stock Out</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="item_id" class="form-label">Item</label>
                <select class="form-select select2" id="item_id" name="item_id">
                    <option value="">All Items</option>
                    <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>" 
                            <?php echo $itemFilter == $item['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($item['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select class="form-select select2" id="supplier_id" name="supplier_id">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>" 
                            <?php echo $supplierFilter == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($supplier['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-1">
                <label for="date_from" class="form-label">From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            
            <div class="col-md-1">
                <label for="date_to" class="form-label">To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            
            <div class="col-md-1 d-flex align-items-end">
                <div class="btn-group w-100" role="group">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="stock-history.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Movement History Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            Movement History 
            <?php if ($totalMovements > 0): ?>
                <span class="badge bg-secondary"><?php echo number_format($totalMovements); ?> records</span>
            <?php endif; ?>
        </h6>
        <div>
            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportToCSV('#movements-table', 'stock-movements.csv')">
                <i class="fas fa-download me-1"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-info btn-sm" onclick="printPage('movements-table')">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($movements)): ?>
        <div class="text-center py-5">
            <i class="fas fa-history fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No movements found</h5>
            <p class="text-muted mb-4">
                <?php if (!empty($whereConditions)): ?>
                    Try adjusting your filters to see more results.
                <?php else: ?>
                    Stock movements will appear here once you start recording inventory changes.
                <?php endif; ?>
            </p>
            <div>
                <a href="stock-in.php" class="btn btn-success me-2">
                    <i class="fas fa-plus me-1"></i>Add Stock
                </a>
                <a href="stock-out.php" class="btn btn-outline-danger">
                    <i class="fas fa-minus me-1"></i>Record Usage
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="table-responsive" id="movements-table">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Supplier/Purpose</th>
                        <th>Remarks</th>
                        <th>Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td class="format-date"><?php echo $movement['date']; ?></td>
                        <td>
                            <?php if ($movement['type'] === 'in'): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-arrow-down me-1"></i>Stock In
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-arrow-up me-1"></i>Stock Out
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($movement['item_name']); ?></strong>
                        </td>
                        <td>
                            <span class="fw-bold <?php echo $movement['type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $movement['type'] === 'in' ? '+' : '-'; ?><?php echo number_format($movement['quantity']); ?>
                            </span>
                            <?php echo htmlspecialchars($movement['unit']); ?>
                        </td>
                        <td>
                            <?php if ($movement['type'] === 'in'): ?>
                                <i class="fas fa-truck me-1 text-muted"></i>
                                <?php echo htmlspecialchars($movement['supplier_name'] ?? 'Unknown Supplier'); ?>
                            <?php else: ?>
                                <i class="fas fa-tag me-1 text-muted"></i>
                                <?php echo htmlspecialchars($movement['purpose']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($movement['remarks'])): ?>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                      title="<?php echo htmlspecialchars($movement['remarks']); ?>">
                                    <?php echo htmlspecialchars($movement['remarks']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted format-datetime"><?php echo $movement['created_at']; ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <small class="text-muted">
                    Showing <?php echo number_format(($page - 1) * $perPage + 1); ?> to 
                    <?php echo number_format(min($page * $perPage, $totalMovements)); ?> of 
                    <?php echo number_format($totalMovements); ?> entries
                </small>
            </div>
            
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            Previous
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
