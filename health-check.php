<?php
/**
 * Quantix Inventory System - System Health Check
 * This script validates that all components are working correctly
 */

require_once 'includes/functions.php';

// HTML header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - Quantix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Quantix System Health Check</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        
                        $checks = [];
                        $allPassed = true;
                        
                        // Database Connection Test
                        try {
                            $db = getDB();
                            $checks['database'] = [
                                'name' => 'Database Connection',
                                'status' => 'success',
                                'message' => 'Successfully connected to database'
                            ];
                        } catch (Exception $e) {
                            $checks['database'] = [
                                'name' => 'Database Connection',
                                'status' => 'danger',
                                'message' => 'Failed to connect: ' . $e->getMessage()
                            ];
                            $allPassed = false;
                        }
                        
                        // Table Existence Test
                        $requiredTables = ['users', 'categories', 'items', 'suppliers', 'stock_in', 'stock_out'];
                        $missingTables = [];
                        
                        foreach ($requiredTables as $table) {
                            try {
                                $result = fetchOne("SHOW TABLES LIKE '{$table}'");
                                if (!$result) {
                                    $missingTables[] = $table;
                                }
                            } catch (Exception $e) {
                                $missingTables[] = $table;
                            }
                        }
                        
                        if (empty($missingTables)) {
                            $checks['tables'] = [
                                'name' => 'Database Tables',
                                'status' => 'success',
                                'message' => 'All required database tables exist'
                            ];
                        } else {
                            $checks['tables'] = [
                                'name' => 'Database Tables',
                                'status' => 'danger',
                                'message' => 'Missing tables: ' . implode(', ', $missingTables)
                            ];
                            $allPassed = false;
                        }
                        
                        // File Permissions Test
                        $writeablePaths = ['uploads/', 'exports/'];
                        $permissionIssues = [];
                        
                        foreach ($writeablePaths as $path) {
                            if (!file_exists($path)) {
                                try {
                                    mkdir($path, 0755, true);
                                } catch (Exception $e) {
                                    $permissionIssues[] = $path . ' (cannot create)';
                                }
                            }
                            
                            if (!is_writable($path)) {
                                $permissionIssues[] = $path . ' (not writable)';
                            }
                        }
                        
                        if (empty($permissionIssues)) {
                            $checks['permissions'] = [
                                'name' => 'File Permissions',
                                'status' => 'success',
                                'message' => 'All required directories are writable'
                            ];
                        } else {
                            $checks['permissions'] = [
                                'name' => 'File Permissions',
                                'status' => 'warning',
                                'message' => 'Issues with: ' . implode(', ', $permissionIssues)
                            ];
                        }
                        
                        // PHP Extensions Test
                        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'session'];
                        $missingExtensions = [];
                        
                        foreach ($requiredExtensions as $ext) {
                            if (!extension_loaded($ext)) {
                                $missingExtensions[] = $ext;
                            }
                        }
                        
                        if (empty($missingExtensions)) {
                            $checks['extensions'] = [
                                'name' => 'PHP Extensions',
                                'status' => 'success',
                                'message' => 'All required PHP extensions are loaded'
                            ];
                        } else {
                            $checks['extensions'] = [
                                'name' => 'PHP Extensions',
                                'status' => 'danger',
                                'message' => 'Missing extensions: ' . implode(', ', $missingExtensions)
                            ];
                            $allPassed = false;
                        }
                        
                        // API Endpoints Test
                        $apiEndpoints = [
                            'items.php' => 'Items API',
                            'categories.php' => 'Categories API',
                            'suppliers.php' => 'Suppliers API',
                            'stock.php' => 'Stock API',
                            'dashboard.php' => 'Dashboard API'
                        ];
                        
                        $missingApis = [];
                        foreach ($apiEndpoints as $file => $name) {
                            if (!file_exists("api/{$file}")) {
                                $missingApis[] = $name;
                            }
                        }
                        
                        if (empty($missingApis)) {
                            $checks['apis'] = [
                                'name' => 'API Endpoints',
                                'status' => 'success',
                                'message' => 'All API endpoints are present'
                            ];
                        } else {
                            $checks['apis'] = [
                                'name' => 'API Endpoints',
                                'status' => 'warning',
                                'message' => 'Missing APIs: ' . implode(', ', $missingApis)
                            ];
                        }
                        
                        // Core Pages Test
                        $corePages = [
                            'items.php' => 'Items Management',
                            'categories.php' => 'Categories Management',
                            'suppliers.php' => 'Suppliers Management',
                            'stock-in.php' => 'Stock In',
                            'stock-out.php' => 'Stock Out',
                            'stock-history.php' => 'Stock History',
                            'low-stock.php' => 'Low Stock Report',
                            'users.php' => 'User Management',
                            'profile.php' => 'Profile Management',
                            'analytics.php' => 'Analytics',
                            'reports.php' => 'Reports'
                        ];
                        
                        $missingPages = [];
                        foreach ($corePages as $file => $name) {
                            if (!file_exists("pages/{$file}")) {
                                $missingPages[] = $name;
                            }
                        }
                        
                        if (empty($missingPages)) {
                            $checks['pages'] = [
                                'name' => 'Core Pages',
                                'status' => 'success',
                                'message' => 'All core pages are present'
                            ];
                        } else {
                            $checks['pages'] = [
                                'name' => 'Core Pages',
                                'status' => 'warning',
                                'message' => 'Missing pages: ' . implode(', ', $missingPages)
                            ];
                        }
                        
                        // Sample Data Test
                        try {
                            $userCount = fetchOne("SELECT COUNT(*) as count FROM users")['count'];
                            $categoryCount = fetchOne("SELECT COUNT(*) as count FROM categories")['count'];
                            $itemCount = fetchOne("SELECT COUNT(*) as count FROM items")['count'];
                            
                            $checks['data'] = [
                                'name' => 'Sample Data',
                                'status' => 'info',
                                'message' => "Users: {$userCount}, Categories: {$categoryCount}, Items: {$itemCount}"
                            ];
                        } catch (Exception $e) {
                            $checks['data'] = [
                                'name' => 'Sample Data',
                                'status' => 'warning',
                                'message' => 'Unable to fetch data counts'
                            ];
                        }
                        
                        // Display Results
                        foreach ($checks as $check) {
                            $icon = [
                                'success' => 'fa-check-circle text-success',
                                'warning' => 'fa-exclamation-triangle text-warning',
                                'danger' => 'fa-times-circle text-danger',
                                'info' => 'fa-info-circle text-info'
                            ][$check['status']];
                            
                            echo "<div class='d-flex align-items-center mb-3'>";
                            echo "<i class='fas {$icon} me-3 fa-lg'></i>";
                            echo "<div>";
                            echo "<h6 class='mb-1'>{$check['name']}</h6>";
                            echo "<small class='text-muted'>{$check['message']}</small>";
                            echo "</div>";
                            echo "</div>";
                        }
                        
                        ?>
                        
                        <hr>
                        
                        <div class="text-center">
                            <?php if ($allPassed): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>System Status: Healthy</strong><br>
                                    All critical components are working correctly. Your Quantix system is ready to use!
                                </div>
                                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                </a>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>System Status: Issues Detected</strong><br>
                                    Please resolve the critical issues above before using the system.
                                </div>
                                <a href="<?php echo BASE_URL; ?>/install.php" class="btn btn-warning">
                                    <i class="fas fa-wrench me-2"></i>Run Installer
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>System Information</h6>
                                <ul class="list-unstyled">
                                    <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                                    <li><strong>App Version:</strong> <?php echo APP_VERSION; ?></li>
                                    <li><strong>Base URL:</strong> <?php echo BASE_URL; ?></li>
                                    <li><strong>Database:</strong> <?php echo DB_NAME; ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
                                    <a href="<?php echo BASE_URL; ?>/pages/items.php" class="btn btn-outline-secondary btn-sm">Manage Items</a>
                                    <a href="<?php echo BASE_URL; ?>/pages/analytics.php" class="btn btn-outline-info btn-sm">View Analytics</a>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
