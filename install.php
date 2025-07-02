<?php
require_once 'includes/config.php';

// Check if installation is needed
$installNeeded = false;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if ($result->rowCount() == 0) {
        $installNeeded = true;
    } else {
        // Check if tables exist
        $pdo->exec("USE " . DB_NAME);
        $result = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($result->rowCount() == 0) {
            $installNeeded = true;
        }
    }
} catch (PDOException $e) {
    $installNeeded = true;
}

if (!$installNeeded) {
    header('Location: login.php');
    exit();
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);
        
        // Read and execute schema
        $schema = file_get_contents('dbschema.sql');
        $pdo->exec($schema);
        
        // Create default admin user
        $adminName = $_POST['admin_name'] ?? 'Administrator';
        $adminEmail = $_POST['admin_email'] ?? 'admin@example.com';
        $adminPassword = $_POST['admin_password'] ?? 'password123';
        
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
        
        // Insert sample categories
        $categories = ['Electronics', 'Office Supplies', 'Stationery', 'Food & Beverages', 'Cleaning Supplies'];
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        foreach ($categories as $category) {
            $stmt->execute([$category]);
        }
        
        // Insert sample suppliers
        $suppliers = [
            ['Tech Supplies Inc.', 'Phone: (555) 123-4567\nEmail: sales@techsupplies.com'],
            ['Office Depot', 'Phone: (555) 987-6543\nEmail: orders@officedepot.com'],
            ['Local Grocery Store', 'Phone: (555) 456-7890\nAddress: 123 Main St.']
        ];
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_info) VALUES (?, ?)");
        foreach ($suppliers as $supplier) {
            $stmt->execute($supplier);
        }
        
        $success = 'Installation completed successfully!';
        $step = 3;
        
    } catch (PDOException $e) {
        $error = 'Installation failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .install-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .brand-logo {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            background: #e9ecef;
            color: #6c757d;
        }
        
        .step.active {
            background: #0d6efd;
            color: white;
        }
        
        .step.completed {
            background: #198754;
            color: white;
        }
        
        .step-line {
            width: 50px;
            height: 2px;
            background: #e9ecef;
            margin-top: 19px;
        }
        
        .step-line.completed {
            background: #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card install-card border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-boxes brand-logo"></i>
                            <h2 class="fw-bold text-dark"><?php echo APP_NAME; ?></h2>
                            <p class="text-muted">Installation Wizard</p>
                        </div>
                        
                        <!-- Step Indicator -->
                        <div class="step-indicator">
                            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                            <div class="step-line <?php echo $step > 1 ? 'completed' : ''; ?>"></div>
                            <div class="step <?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>">2</div>
                            <div class="step-line <?php echo $step > 2 ? 'completed' : ''; ?>"></div>
                            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($step == 1): ?>
                        <!-- Step 1: Welcome -->
                        <div class="text-center">
                            <h4 class="mb-4">Welcome to Quantix Installation</h4>
                            <p class="text-muted mb-4">
                                This wizard will help you set up your inventory management system. 
                                Please ensure your database server is running and accessible.
                            </p>
                            
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">System Requirements</h6>
                                    <ul class="list-unstyled text-start">
                                        <li><i class="fas fa-check text-success me-2"></i>PHP 7.4 or higher</li>
                                        <li><i class="fas fa-check text-success me-2"></i>MySQL 5.7 or higher</li>
                                        <li><i class="fas fa-check text-success me-2"></i>PDO MySQL extension</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Web server (Apache/Nginx)</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <a href="install.php?step=2" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right me-2"></i>Continue Installation
                            </a>
                        </div>
                        
                        <?php elseif ($step == 2): ?>
                        <!-- Step 2: Database Setup -->
                        <h4 class="mb-4 text-center">Database Setup</h4>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Administrator Account</h6>
                                
                                <div class="mb-3">
                                    <label for="admin_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="admin_name" name="admin_name" 
                                           value="Administrator" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="admin@example.com" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" 
                                           value="password123" required>
                                    <div class="form-text">
                                        Default: password123 (you can change this later)
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                This will create the database, tables, and sample data including categories and suppliers.
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="install.php?step=1" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database me-2"></i>Install Database
                                </button>
                            </div>
                        </form>
                        
                        <?php elseif ($step == 3): ?>
                        <!-- Step 3: Complete -->
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-4x text-success mb-4"></i>
                            <h4 class="mb-4">Installation Complete!</h4>
                            <p class="text-muted mb-4">
                                Your Quantix Inventory Management System has been successfully installed and configured.
                            </p>
                            
                            <div class="card bg-light mb-4">
                                <div class="card-body text-start">
                                    <h6 class="card-title">What's Next?</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-arrow-right text-primary me-2"></i>Login with your administrator account</li>
                                        <li><i class="fas fa-arrow-right text-primary me-2"></i>Add your inventory items</li>
                                        <li><i class="fas fa-arrow-right text-primary me-2"></i>Set up additional user accounts if needed</li>
                                        <li><i class="fas fa-arrow-right text-primary me-2"></i>Configure low stock thresholds</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <a href="login.php" class="btn btn-success btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>
