<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    private function __wakeup() {}
}

// Database helper functions
function getDB() {
    return Database::getInstance()->getConnection();
}

function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return false;
    }
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt ? $stmt->fetch() : null;
}

function insertRecord($table, $data) {
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    
    $stmt = executeQuery($sql, $data);
    return $stmt ? getDB()->lastInsertId() : false;
}

function updateRecord($table, $data, $conditions) {
    $setClause = implode(', ', array_map(fn($key) => "{$key} = :{$key}", array_keys($data)));
    $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :where_{$key}", array_keys($conditions)));
    
    $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
    
    $params = $data;
    foreach ($conditions as $key => $value) {
        $params["where_{$key}"] = $value;
    }
    
    return executeQuery($sql, $params) !== false;
}

function deleteRecord($table, $conditions) {
    $whereClause = implode(' AND ', array_map(fn($key) => "{$key} = :{$key}", array_keys($conditions)));
    $sql = "DELETE FROM {$table} WHERE {$whereClause}";
    
    return executeQuery($sql, $conditions) !== false;
}
?>
