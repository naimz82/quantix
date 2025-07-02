<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check authentication
requireLogin();

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'stock-history':
        printStockHistory();
        break;
    case 'low-stock':
        printLowStock();
        break;
    case 'items':
        printItems();
        break;
    default:
        http_response_code(400);
        echo "Invalid print type";
        break;
}

function printStockHistory() {
    global $pdo;
    
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $item_id = $_GET['item_id'] ?? '';
    $movement_type = $_GET['movement_type'] ?? '';
    
    $sql = "SELECT sm.*, i.name as item_name, i.sku, u.name as user_name
            FROM stock_movements sm
            JOIN items i ON sm.item_id = i.id
            JOIN users u ON sm.created_by = u.id
            WHERE DATE(sm.created_at) BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    
    if ($item_id) {
        $sql .= " AND sm.item_id = ?";
        $params[] = $item_id;
    }
    
    if ($movement_type) {
        $sql .= " AND sm.movement_type = ?";
        $params[] = $movement_type;
    }
    
    $sql .= " ORDER BY sm.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // Generate HTML for printing
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Stock Movement History</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .report-title { font-size: 16px; margin-bottom: 10px; }
            .report-period { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .movement-in { color: #28a745; }
            .movement-out { color: #dc3545; }
            .footer { margin-top: 30px; font-size: 10px; color: #666; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">Quantix Inventory System</div>
            <div class="report-title">Stock Movement History</div>
            <div class="report-period">Period: <?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?></div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>SKU</th>
                    <th>Type</th>
                    <th class="text-right">Quantity</th>
                    <th>Reference</th>
                    <th>User</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= date('M d, Y H:i', strtotime($movement['created_at'])) ?></td>
                    <td><?= htmlspecialchars($movement['item_name']) ?></td>
                    <td><?= htmlspecialchars($movement['sku']) ?></td>
                    <td class="text-center">
                        <span class="movement-<?= $movement['movement_type'] ?>">
                            <?= strtoupper($movement['movement_type']) ?>
                        </span>
                    </td>
                    <td class="text-right"><?= number_format($movement['quantity']) ?></td>
                    <td><?= htmlspecialchars($movement['reference_number']) ?></td>
                    <td><?= htmlspecialchars($movement['user_name']) ?></td>
                    <td><?= htmlspecialchars($movement['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated on <?= date('M d, Y H:i:s') ?> by <?= htmlspecialchars($_SESSION['user_name']) ?></p>
            <p>Total Records: <?= count($movements) ?></p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    echo $html;
}

function printLowStock() {
    global $pdo;
    
    $sql = "SELECT i.*, c.name as category_name, s.name as supplier_name,
            CASE 
                WHEN i.current_stock = 0 THEN 'Out of Stock'
                WHEN i.current_stock <= i.minimum_stock * 0.5 THEN 'Critical'
                ELSE 'Low Stock'
            END as status_level
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.current_stock <= i.minimum_stock 
            AND i.status = 'active'
            ORDER BY i.current_stock / NULLIF(i.minimum_stock, 1) ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Low Stock Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .report-title { font-size: 16px; margin-bottom: 10px; }
            .report-date { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .status-critical { color: #dc3545; font-weight: bold; }
            .status-out { color: #6c757d; font-weight: bold; }
            .status-low { color: #ffc107; font-weight: bold; }
            .footer { margin-top: 30px; font-size: 10px; color: #666; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">Quantix Inventory System</div>
            <div class="report-title">Low Stock Report</div>
            <div class="report-date">Generated on <?= date('M d, Y H:i:s') ?></div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Min Stock</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Unit Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['sku']) ?></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                    <td class="text-right"><?= number_format($item['current_stock']) ?></td>
                    <td class="text-right"><?= number_format($item['minimum_stock']) ?></td>
                    <td class="text-center">
                        <span class="status-<?= strtolower(str_replace(' ', '-', $item['status_level'])) ?>">
                            <?= $item['status_level'] ?>
                        </span>
                    </td>
                    <td class="text-right">$<?= number_format($item['unit_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated by <?= htmlspecialchars($_SESSION['user_name']) ?></p>
            <p>Total Items: <?= count($items) ?></p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    echo $html;
}

function printItems() {
    global $pdo;
    
    $category_id = $_GET['category_id'] ?? '';
    $status = $_GET['status'] ?? 'active';
    
    $sql = "SELECT i.*, c.name as category_name, s.name as supplier_name
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.status = ?";
    $params = [$status];
    
    if ($category_id) {
        $sql .= " AND i.category_id = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY i.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Items List</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
            .report-title { font-size: 16px; margin-bottom: 10px; }
            .report-date { font-size: 12px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .footer { margin-top: 30px; font-size: 10px; color: #666; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="company-name">Quantix Inventory System</div>
            <div class="report-title">Items List</div>
            <div class="report-date">Generated on <?= date('M d, Y H:i:s') ?></div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Min Stock</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['sku']) ?></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td><?= htmlspecialchars($item['supplier_name']) ?></td>
                    <td class="text-right"><?= number_format($item['current_stock']) ?></td>
                    <td class="text-right"><?= number_format($item['minimum_stock']) ?></td>
                    <td class="text-right">$<?= number_format($item['unit_price'], 2) ?></td>
                    <td class="text-center"><?= ucfirst($item['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated by <?= htmlspecialchars($_SESSION['user_name']) ?></p>
            <p>Total Items: <?= count($items) ?></p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    echo $html;
}
?>
