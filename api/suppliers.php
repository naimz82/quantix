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
            $stmt = $pdo->prepare("SELECT s.*, COUNT(i.id) as item_count 
                                   FROM suppliers s 
                                   LEFT JOIN items i ON s.id = i.supplier_id 
                                   GROUP BY s.id 
                                   ORDER BY s.name");
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            echo json_encode($suppliers);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch();
            
            if ($supplier) {
                echo json_encode($supplier);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Supplier not found']);
            }
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([]);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT id, name, contact_person, phone FROM suppliers 
                                   WHERE name LIKE ? OR contact_person LIKE ? 
                                   ORDER BY name LIMIT 20");
            $stmt->execute(["%$query%", "%$query%"]);
            $suppliers = $stmt->fetchAll();
            
            echo json_encode($suppliers);
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
            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Supplier name is required']);
                return;
            }
            
            $sql = "INSERT INTO suppliers (name, contact_person, email, phone, address, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['contact_person'] ?? '',
                $data['email'] ?? '',
                $data['phone'] ?? '',
                $data['address'] ?? '',
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create supplier']);
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
            $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Supplier not found']);
                return;
            }
            
            $sql = "UPDATE suppliers SET name = ?, contact_person = ?, email = ?, 
                    phone = ?, address = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['contact_person'] ?? '',
                $data['email'] ?? '',
                $data['phone'] ?? '',
                $data['address'] ?? '',
                $id
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update supplier']);
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
            // Check if supplier has items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete supplier with items']);
                return;
            }
            
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete supplier']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
