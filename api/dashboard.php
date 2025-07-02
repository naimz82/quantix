<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action) {
    global $pdo;
    
    switch ($action) {
        case 'dashboard-stats':
            $stats = [];
            
            // Total items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE status = 'active'");
            $stmt->execute();
            $stats['total_items'] = $stmt->fetchColumn();
            
            // Total categories
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories");
            $stmt->execute();
            $stats['total_categories'] = $stmt->fetchColumn();
            
            // Total suppliers
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers");
            $stmt->execute();
            $stats['total_suppliers'] = $stmt->fetchColumn();
            
            // Low stock items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE current_stock <= minimum_stock AND status = 'active'");
            $stmt->execute();
            $stats['low_stock_items'] = $stmt->fetchColumn();
            
            // Total stock value
            $stmt = $pdo->prepare("SELECT SUM(current_stock * unit_price) FROM items WHERE status = 'active'");
            $stmt->execute();
            $stats['total_value'] = $stmt->fetchColumn() ?: 0;
            
            // Recent movements count (last 7 days)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['recent_movements'] = $stmt->fetchColumn();
            
            echo json_encode($stats);
            break;
            
        case 'stock-levels':
            $stmt = $pdo->prepare("SELECT 
                SUM(CASE WHEN current_stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN current_stock > 0 AND current_stock <= minimum_stock THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN current_stock > minimum_stock THEN 1 ELSE 0 END) as normal_stock
                FROM items WHERE status = 'active'");
            $stmt->execute();
            $levels = $stmt->fetch();
            echo json_encode($levels);
            break;
            
        case 'movement-trends':
            $days = $_GET['days'] ?? 30;
            $stmt = $pdo->prepare("SELECT 
                DATE(created_at) as date,
                movement_type,
                SUM(quantity) as total_quantity
                FROM stock_movements 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), movement_type
                ORDER BY date ASC");
            $stmt->execute([$days]);
            $trends = $stmt->fetchAll();
            echo json_encode($trends);
            break;
            
        case 'top-categories':
            $stmt = $pdo->prepare("SELECT c.name, COUNT(i.id) as item_count, 
                SUM(i.current_stock * i.unit_price) as total_value
                FROM categories c
                LEFT JOIN items i ON c.id = i.category_id AND i.status = 'active'
                GROUP BY c.id, c.name
                HAVING item_count > 0
                ORDER BY total_value DESC
                LIMIT 10");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            echo json_encode($categories);
            break;
            
        case 'recent-activity':
            $limit = $_GET['limit'] ?? 20;
            $stmt = $pdo->prepare("SELECT 
                sm.id,
                sm.movement_type,
                sm.quantity,
                sm.reference_number,
                sm.created_at,
                i.name as item_name,
                i.sku,
                u.name as user_name
                FROM stock_movements sm
                JOIN items i ON sm.item_id = i.id
                JOIN users u ON sm.created_by = u.id
                ORDER BY sm.created_at DESC
                LIMIT ?");
            $stmt->execute([$limit]);
            $activity = $stmt->fetchAll();
            echo json_encode($activity);
            break;
            
        case 'low-stock-alerts':
            $stmt = $pdo->prepare("SELECT i.*, c.name as category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.current_stock <= i.minimum_stock 
                AND i.status = 'active'
                ORDER BY (i.current_stock / NULLIF(i.minimum_stock, 0)) ASC");
            $stmt->execute();
            $alerts = $stmt->fetchAll();
            echo json_encode($alerts);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
