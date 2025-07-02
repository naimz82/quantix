<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check authentication
requireLogin();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

switch ($type) {
    case 'items':
        exportItems($format);
        break;
    case 'stock-movements':
        exportStockMovements($format);
        break;
    case 'low-stock':
        exportLowStock($format);
        break;
    case 'categories':
        exportCategories($format);
        break;
    case 'suppliers':
        exportSuppliers($format);
        break;
    default:
        http_response_code(400);
        echo "Invalid export type";
        break;
}

function exportItems($format) {
    global $pdo;
    
    $sql = "SELECT i.id, i.name, i.sku, i.description, i.current_stock, 
            i.minimum_stock, i.maximum_stock, i.unit_price, i.status,
            c.name as category_name, s.name as supplier_name,
            i.created_at, i.updated_at
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            ORDER BY i.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($items, 'items_' . date('Y-m-d') . '.csv');
    } else {
        exportJSON($items, 'items_' . date('Y-m-d') . '.json');
    }
}

function exportStockMovements($format) {
    global $pdo;
    
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $sql = "SELECT sm.id, sm.movement_type, sm.quantity, sm.reference_number,
            sm.notes, sm.created_at, i.name as item_name, i.sku,
            u.name as user_name
            FROM stock_movements sm
            JOIN items i ON sm.item_id = i.id
            JOIN users u ON sm.created_by = u.id
            WHERE DATE(sm.created_at) BETWEEN ? AND ?
            ORDER BY sm.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($movements, 'stock_movements_' . date('Y-m-d') . '.csv');
    } else {
        exportJSON($movements, 'stock_movements_' . date('Y-m-d') . '.json');
    }
}

function exportLowStock($format) {
    global $pdo;
    
    $sql = "SELECT i.id, i.name, i.sku, i.current_stock, i.minimum_stock,
            i.unit_price, c.name as category_name, s.name as supplier_name,
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
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($items, 'low_stock_' . date('Y-m-d') . '.csv');
    } else {
        exportJSON($items, 'low_stock_' . date('Y-m-d') . '.json');
    }
}

function exportCategories($format) {
    global $pdo;
    
    $sql = "SELECT c.id, c.name, c.description, c.created_at,
            COUNT(i.id) as item_count,
            SUM(i.current_stock * i.unit_price) as total_value
            FROM categories c
            LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
            GROUP BY c.id, c.name, c.description, c.created_at
            ORDER BY c.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($categories, 'categories_' . date('Y-m-d') . '.csv');
    } else {
        exportJSON($categories, 'categories_' . date('Y-m-d') . '.json');
    }
}

function exportSuppliers($format) {
    global $pdo;
    
    $sql = "SELECT s.id, s.name, s.contact_person, s.email, s.phone,
            s.address, s.created_at, COUNT(i.id) as item_count
            FROM suppliers s
            LEFT JOIN items i ON s.id = i.supplier_id AND i.status = 'active'
            GROUP BY s.id, s.name, s.contact_person, s.email, s.phone, s.address, s.created_at
            ORDER BY s.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        exportCSV($suppliers, 'suppliers_' . date('Y-m-d') . '.csv');
    } else {
        exportJSON($suppliers, 'suppliers_' . date('Y-m-d') . '.json');
    }
}

function exportCSV($data, $filename) {
    if (empty($data)) {
        echo "No data to export";
        return;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, array_keys($data[0]));
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
}

function exportJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    
    echo json_encode($data, JSON_PRETTY_PRINT);
}
?>
