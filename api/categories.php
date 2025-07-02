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
            $stmt = $pdo->prepare("SELECT c.*, COUNT(i.id) as item_count 
                                   FROM categories c 
                                   LEFT JOIN items i ON c.id = i.category_id 
                                   GROUP BY c.id 
                                   ORDER BY c.name");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            echo json_encode($categories);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch();
            
            if ($category) {
                echo json_encode($category);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Category not found']);
            }
            break;
            
        case 'search':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([]);
                return;
            }
            
            $stmt = $pdo->prepare("SELECT id, name FROM categories 
                                   WHERE name LIKE ? 
                                   ORDER BY name LIMIT 20");
            $stmt->execute(["%$query%"]);
            $categories = $stmt->fetchAll();
            
            echo json_encode($categories);
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
                echo json_encode(['error' => 'Category name is required']);
                return;
            }
            
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$data['name']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Category already exists']);
                return;
            }
            
            $sql = "INSERT INTO categories (name, description, created_by) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create category']);
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
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Category not found']);
                return;
            }
            
            $sql = "UPDATE categories SET name = ?, description = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $id
            ]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update category']);
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
            // Check if category has items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete category with items']);
                return;
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete category']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
?>
