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
    case 'POST':
        handlePost($action);
        break;
    case 'PUT':
        handlePut($action);
        break;
    case 'DELETE':
        handleDelete($action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleGet($action) {
    global $pdo;
    
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            $status = $_GET['status'] ?? '';
            $limit = $_GET['limit'] ?? 50;
            $offset = $_GET['offset'] ?? 0;
            
            $sql = "SELECT i.*, c.name as category_name, s.name as supplier_name 
                    FROM items i 
                    LEFT JOIN categories c ON i.category_id = c.id 
                    LEFT JOIN suppliers s ON i.supplier_id = s.id 
                    WHERE 1=1";
            $params = [];
            
            if ($search) {
                $sql .= " AND (i.name LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            if ($category) {
                $sql .= " AND i.category_id = ?";
                $params[] = $category;
            }
            
            if ($status) {
                $sql .= " AND i.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY i.name LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
            
            echo json_encode($items);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            
            if ($item) {
                echo json_encode($item);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Item not found']);
            }
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([]);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT id, name, sku, current_stock FROM items 
                                   WHERE (name LIKE ? OR sku LIKE ?) AND status = 'active' 
                                   ORDER BY name LIMIT 20");
            $stmt->execute(["%$query%", "%$query%"]);
            $items = $stmt->fetchAll();
            
            echo json_encode($items);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePost($action) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            // Only logged in users can create items
            if (!isLoggedIn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $required = ['name', 'sku', 'category_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Field $field is required"]);
                    return;
                }
            }
            
            // Check if SKU already exists
            $stmt = $pdo->prepare("SELECT id FROM items WHERE sku = ?");
            $stmt->execute([$data['sku']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'SKU already exists']);
                return;
            }
            
            $sql = "INSERT INTO items (name, sku, description, category_id, supplier_id, 
                    current_stock, minimum_stock, maximum_stock, unit_price, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['sku'],
                $data['description'] ?? '',
                $data['category_id'],
                $data['supplier_id'] ?? null,
                $data['current_stock'] ?? 0,
                $data['minimum_stock'] ?? 0,
                $data['maximum_stock'] ?? null,
                $data['unit_price'] ?? 0,
                $data['status'] ?? 'active',
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create item']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handlePut($action) {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    switch ($action) {
        case 'update':
            // Only logged in users can update items
            if (!isLoggedIn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM items WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Item not found']);
                return;
            }
            
            $sql = "UPDATE items SET name = ?, sku = ?, description = ?, category_id = ?, 
                    supplier_id = ?, minimum_stock = ?, maximum_stock = ?, unit_price = ?, 
                    status = ?, updated_at = NOW() WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['sku'],
                $data['description'] ?? '',
                $data['category_id'],
                $data['supplier_id'] ?? null,
                $data['minimum_stock'] ?? 0,
                $data['maximum_stock'] ?? null,
                $data['unit_price'] ?? 0,
                $data['status'] ?? 'active',
                $id
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update item']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

function handleDelete($action) {
    global $pdo;
    
    $id = $_GET['id'] ?? 0;
    
    switch ($action) {
        case 'delete':
            // Only logged in users can delete items
            if (!isLoggedIn()) {
                http_response_code(403);
                echo json_encode(['error' => 'Insufficient permissions']);
                return;
            }
            
            // Check if item has stock movements
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE item_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // Soft delete - mark as inactive
                $stmt = $pdo->prepare("UPDATE items SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$id]);
            } else {
                // Hard delete
                $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
                $result = $stmt->execute([$id]);
            }
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete item']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
