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
    case 'POST':
        handlePost($action);
        break;
    case 'GET':
        handleGet($action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handlePost($action) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'stock-in':
            if (empty($data['item_id']) || empty($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Item ID and quantity are required']);
                return;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Get current stock
                $stmt = $pdo->prepare("SELECT current_stock FROM items WHERE id = ?");
                $stmt->execute([$data['item_id']]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock === false) {
                    throw new Exception('Item not found');
                }
                
                // Update item stock
                $new_stock = $current_stock + $data['quantity'];
                $stmt = $pdo->prepare("UPDATE items SET current_stock = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_stock, $data['item_id']]);
                
                // Record stock movement
                $stmt = $pdo->prepare("INSERT INTO stock_movements 
                    (item_id, movement_type, quantity, reference_number, notes, created_by) 
                    VALUES (?, 'in', ?, ?, ?, ?)");
                $stmt->execute([
                    $data['item_id'],
                    $data['quantity'],
                    $data['reference_number'] ?? '',
                    $data['notes'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'new_stock' => $new_stock]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        case 'stock-out':
            if (empty($data['item_id']) || empty($data['quantity'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Item ID and quantity are required']);
                return;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Get current stock
                $stmt = $pdo->prepare("SELECT current_stock FROM items WHERE id = ?");
                $stmt->execute([$data['item_id']]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock === false) {
                    throw new Exception('Item not found');
                }
                
                // Check if sufficient stock
                if ($current_stock < $data['quantity']) {
                    throw new Exception('Insufficient stock');
                }
                
                // Update item stock
                $new_stock = $current_stock - $data['quantity'];
                $stmt = $pdo->prepare("UPDATE items SET current_stock = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_stock, $data['item_id']]);
                
                // Record stock movement
                $stmt = $pdo->prepare("INSERT INTO stock_movements 
                    (item_id, movement_type, quantity, reference_number, notes, created_by) 
                    VALUES (?, 'out', ?, ?, ?, ?)");
                $stmt->execute([
                    $data['item_id'],
                    $data['quantity'],
                    $data['reference_number'] ?? '',
                    $data['notes'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'new_stock' => $new_stock]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleGet($action) {
    global $pdo;
    
    switch ($action) {
        case 'recent-movements':
            $limit = $_GET['limit'] ?? 10;
            $stmt = $pdo->prepare("SELECT sm.*, i.name as item_name, i.sku, u.name as user_name 
                                   FROM stock_movements sm 
                                   JOIN items i ON sm.item_id = i.id 
                                   JOIN users u ON sm.created_by = u.id 
                                   ORDER BY sm.created_at DESC 
                                   LIMIT ?");
            $stmt->execute([$limit]);
            $movements = $stmt->fetchAll();
            echo json_encode($movements);
            break;
            
        case 'item-stock':
            $item_id = $_GET['item_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT current_stock, minimum_stock, maximum_stock FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            $stock = $stmt->fetch();
            
            if ($stock) {
                echo json_encode($stock);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Item not found']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
