<?php
require_once 'config.php';
require_once 'database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

function login($email, $password) {
    $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        return true;
    }
    
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

function createUser($name, $email, $password, $role = 'staff') {
    // Check if email already exists
    $existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return false;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    return insertRecord('users', [
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role
    ]);
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}

function formatDateTime($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}

function formatCurrency($amount) {
    return number_format($amount, 2);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Alert message functions
function setAlert($message, $type = 'success') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Pagination helper
function paginate($table, $page = 1, $perPage = 10, $conditions = '', $params = []) {
    $offset = ($page - 1) * $perPage;
    
    // Count total records
    $countSql = "SELECT COUNT(*) as total FROM {$table}" . ($conditions ? " WHERE {$conditions}" : "");
    $totalResult = fetchOne($countSql, $params);
    $total = $totalResult['total'];
    
    // Get records for current page
    $dataSql = "SELECT * FROM {$table}" . ($conditions ? " WHERE {$conditions}" : "") . " LIMIT {$perPage} OFFSET {$offset}";
    $data = fetchAll($dataSql, $params);
    
    return [
        'data' => $data,
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'current_page' => $page,
        'per_page' => $perPage
    ];
}

// Stock functions
function updateItemQuantity($itemId, $quantity) {
    return updateRecord('items', ['quantity' => $quantity], ['id' => $itemId]);
}

function addStockIn($itemId, $quantity, $supplierId, $date, $remarks = '', $unitPrice = 0.00) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Insert stock in record
        $stockInId = insertRecord('stock_in', [
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'supplier_id' => $supplierId,
            'date' => $date,
            'remarks' => $remarks
        ]);
        
        if (!$stockInId) {
            throw new Exception('Failed to insert stock in record');
        }
        
        // Update item quantity
        $currentItem = fetchOne("SELECT quantity FROM items WHERE id = ?", [$itemId]);
        $newQuantity = $currentItem['quantity'] + $quantity;
        
        if (!updateItemQuantity($itemId, $newQuantity)) {
            throw new Exception('Failed to update item quantity');
        }
        
        $db->commit();
        return $stockInId;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Stock in error: " . $e->getMessage());
        return false;
    }
}

function addStockOut($itemId, $quantity, $purpose, $date, $remarks = '') {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Check if sufficient stock available
        $currentItem = fetchOne("SELECT quantity FROM items WHERE id = ?", [$itemId]);
        if ($currentItem['quantity'] < $quantity) {
            throw new Exception('Insufficient stock available');
        }
        
        // Insert stock out record
        $stockOutId = insertRecord('stock_out', [
            'item_id' => $itemId,
            'quantity' => $quantity,
            'purpose' => $purpose,
            'date' => $date,
            'remarks' => $remarks
        ]);
        
        if (!$stockOutId) {
            throw new Exception('Failed to insert stock out record');
        }
        
        // Update item quantity
        $newQuantity = $currentItem['quantity'] - $quantity;
        
        if (!updateItemQuantity($itemId, $newQuantity)) {
            throw new Exception('Failed to update item quantity');
        }
        
        $db->commit();
        return $stockOutId;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Stock out error: " . $e->getMessage());
        return false;
    }
}

function getLowStockItems() {
    return fetchAll("
        SELECT i.*, c.name as category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE i.quantity <= i.low_stock_threshold
        ORDER BY i.quantity ASC
    ");
}
?>
