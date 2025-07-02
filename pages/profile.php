<?php
require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'My Profile';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: profile.php');
        exit();
    }
    
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required.';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        
        // Check if email already exists for another user
        $existing = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $_SESSION['user_id']]);
        if ($existing) {
            $errors[] = 'Email address already exists.';
        }
        
        if (empty($errors)) {
            $result = updateRecord('users', [
                'name' => $name,
                'email' => $email
            ], ['id' => $_SESSION['user_id']]);
            
            if ($result) {
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                setAlert('Profile updated successfully!', 'success');
            } else {
                setAlert('Failed to update profile.', 'danger');
            }
        } else {
            foreach ($errors as $error) {
                setAlert($error, 'danger');
                break; // Show only first error
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validation
        $errors = [];
        
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required.';
        }
        
        if (empty($newPassword) || strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }
        
        // Verify current password
        if (empty($errors)) {
            $user = fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (empty($errors)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = updateRecord('users', [
                'password' => $hashedPassword
            ], ['id' => $_SESSION['user_id']]);
            
            if ($result) {
                setAlert('Password changed successfully!', 'success');
            } else {
                setAlert('Failed to change password.', 'danger');
            }
        } else {
            foreach ($errors as $error) {
                setAlert($error, 'danger');
                break; // Show only first error
            }
        }
    }
    
    header('Location: profile.php');
    exit();
}

// Get current user data
$currentUser = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

if (!$currentUser) {
    setAlert('User not found.', 'danger');
    logout();
}

// Get user activity statistics
$userStats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM stock_in WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as stock_in_30d,
        (SELECT COUNT(*) FROM stock_out WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as stock_out_30d,
        (SELECT COUNT(*) FROM items WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as items_added_30d
");

include_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-circle me-2"></i>My Profile</h2>
    <div>
        <span class="badge bg-<?php echo $currentUser['role'] === 'admin' ? 'danger' : 'secondary'; ?> fs-6">
            <?php echo ucfirst($currentUser['role']); ?>
        </span>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Profile Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($currentUser['name']); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide your full name.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo ucfirst($currentUser['role']); ?>" 
                                   readonly>
                            <div class="form-text">
                                Contact an administrator to change your role.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control format-date" 
                                   value="<?php echo $currentUser['created_at']; ?>" 
                                   readonly>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" 
                               required>
                        <div class="invalid-feedback">
                            Please enter your current password.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                            <div class="invalid-feedback">
                                Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required>
                            <div class="invalid-feedback">
                                Please confirm your new password.
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Password Requirements:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters long</li>
                            <li>Use a combination of letters, numbers, and symbols</li>
                            <li>Avoid using personal information</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Activity Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Activity Summary (Last 30 Days)</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-12 mb-3">
                        <h4 class="text-success mb-1"><?php echo number_format($userStats['stock_in_30d'] ?? 0); ?></h4>
                        <small class="text-muted">Stock In Entries</small>
                    </div>
                    <div class="col-12 mb-3">
                        <h4 class="text-danger mb-1"><?php echo number_format($userStats['stock_out_30d'] ?? 0); ?></h4>
                        <small class="text-muted">Stock Out Entries</small>
                    </div>
                    <div class="col-12">
                        <h4 class="text-primary mb-1"><?php echo number_format($userStats['items_added_30d'] ?? 0); ?></h4>
                        <small class="text-muted">Items Added</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>User ID:</strong><br>
                    <code><?php echo $currentUser['id']; ?></code>
                </div>
                
                <div class="mb-3">
                    <strong>Login Status:</strong><br>
                    <span class="badge bg-success">Currently Logged In</span>
                </div>
                
                <div class="mb-3">
                    <strong>Session Started:</strong><br>
                    <small class="text-muted">
                        <?php echo date('M j, Y g:i A', $_SESSION['login_time'] ?? time()); ?>
                    </small>
                </div>
                
                <div>
                    <strong>Account Status:</strong><br>
                    <span class="badge bg-success">Active</span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../dashboard.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                    </a>
                    <a href="stock-in.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-arrow-down me-2"></i>Add Stock
                    </a>
                    <a href="stock-out.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-arrow-up me-2"></i>Record Usage
                    </a>
                    <a href="items.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-boxes me-2"></i>Manage Items
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="users.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </a>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="d-grid">
                    <a href="../logout.php" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password confirmation validation
    $('#confirm_password').on('input', function() {
        var newPassword = $('#new_password').val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
    
    $('#new_password').on('input', function() {
        var confirmPassword = $('#confirm_password').val();
        var newPassword = $(this).val();
        
        if (confirmPassword && newPassword !== confirmPassword) {
            document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
        } else {
            document.getElementById('confirm_password').setCustomValidity('');
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
