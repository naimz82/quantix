<?php
require_once '../includes/functions.php';
requireAdmin(); // Only admins can access user management

$pageTitle = 'User Management';
$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setAlert('Invalid security token.', 'danger');
        header('Location: users.php');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $role = sanitizeInput($_POST['role']);
    $password = $_POST['password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    
    if (!in_array($role, ['admin', 'staff'])) {
        $errors[] = 'Invalid role selected.';
    }
    
    if ($action === 'add') {
        if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }
        
        // Check if email already exists
        $existing = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $errors[] = 'Email address already exists.';
        }
    }
    
    if ($action === 'edit' && $userId) {
        // Check if email already exists for another user
        $existing = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existing) {
            $errors[] = 'Email address already exists.';
        }
        
        // Prevent demoting the last admin
        if ($role !== 'admin') {
            $adminCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];
            $currentUser = fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
            if ($currentUser['role'] === 'admin' && $adminCount <= 1) {
                $errors[] = 'Cannot demote the last administrator.';
            }
        }
    }
    
    if (empty($errors)) {
        if ($action === 'add') {
            $result = createUser($name, $email, $password, $role);
            if ($result) {
                setAlert('User created successfully!', 'success');
            } else {
                setAlert('Failed to create user.', 'danger');
            }
        } elseif ($action === 'edit' && $userId) {
            $updateData = [
                'name' => $name,
                'email' => $email,
                'role' => $role
            ];
            
            // Update password if provided
            if (!empty($password)) {
                if (strlen($password) < PASSWORD_MIN_LENGTH) {
                    $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                } else {
                    $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
            }
            
            if (empty($errors)) {
                $result = updateRecord('users', $updateData, ['id' => $userId]);
                if ($result) {
                    setAlert('User updated successfully!', 'success');
                } else {
                    setAlert('Failed to update user.', 'danger');
                }
            }
        }
        
        if (empty($errors)) {
            header('Location: users.php');
            exit();
        }
    }
    
    // Display errors
    foreach ($errors as $error) {
        setAlert($error, 'danger');
    }
}

// Handle delete action
if ($action === 'delete' && $userId) {
    // Prevent deleting self
    if ($userId == $_SESSION['user_id']) {
        setAlert('You cannot delete your own account.', 'danger');
    } else {
        // Prevent deleting the last admin
        $userToDelete = fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($userToDelete['role'] === 'admin') {
            $adminCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];
            if ($adminCount <= 1) {
                setAlert('Cannot delete the last administrator.', 'danger');
            } else {
                if (deleteRecord('users', ['id' => $userId])) {
                    setAlert('User deleted successfully!', 'success');
                } else {
                    setAlert('Failed to delete user.', 'danger');
                }
            }
        } else {
            if (deleteRecord('users', ['id' => $userId])) {
                setAlert('User deleted successfully!', 'success');
            } else {
                setAlert('Failed to delete user.', 'danger');
            }
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get current user for edit/view
$currentUser = null;
if (($action === 'edit' || $action === 'view') && $userId) {
    $currentUser = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if (!$currentUser) {
        setAlert('User not found.', 'danger');
        header('Location: users.php');
        exit();
    }
}

// Get users list
$users = fetchAll("SELECT * FROM users ORDER BY name");

include_once '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<!-- Users List View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users me-2"></i>User Management</h2>
    <a href="users.php?action=add" class="btn btn-primary">
        <i class="fas fa-user-plus me-2"></i>Add New User
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No users found</h5>
                    <p class="text-muted mb-4">This shouldn't happen as you are logged in as a user.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr <?php echo $user['id'] == $_SESSION['user_id'] ? 'class="table-info"' : ''; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info ms-2">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">Administrator</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Staff</span>
                                    <?php endif; ?>
                                </td>
                                <td class="format-date"><?php echo $user['created_at']; ?></td>
                                <td>
                                    <span class="badge bg-success">Active</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="users.php?action=view&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <?php
                                        $adminCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];
                                        $canDelete = !($user['role'] === 'admin' && $adminCount <= 1);
                                        ?>
                                        <?php if ($canDelete): ?>
                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-danger delete-btn" 
                                           data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                           title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- User Statistics -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>User Statistics</h6>
            </div>
            <div class="card-body">
                <?php
                $userStats = fetchOne("
                    SELECT 
                        COUNT(*) as total_users,
                        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count
                    FROM users
                ");
                ?>
                <div class="row text-center">
                    <div class="col-12 mb-3">
                        <h4 class="text-primary mb-1"><?php echo number_format($userStats['total_users']); ?></h4>
                        <small class="text-muted">Total Users</small>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-danger mb-1"><?php echo number_format($userStats['admin_count']); ?></h5>
                            <small class="text-muted">Administrators</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-secondary mb-1"><?php echo number_format($userStats['staff_count']); ?></h5>
                        <small class="text-muted">Staff Members</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Guidelines -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Guidelines</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Use strong passwords (min <?php echo PASSWORD_MIN_LENGTH; ?> characters)</small>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Limit admin access to trusted users</small>
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Review user access regularly</small>
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-check text-success me-2"></i>
                        <small>Always maintain at least one admin</small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit User Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'add' ? 'user-plus' : 'user-edit'; ?> me-2"></i>
                    <?php echo $action === 'add' ? 'Add New User' : 'Edit User'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide a full name.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" 
                                   required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?php echo ($currentUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                    Administrator
                                </option>
                                <option value="staff" <?php echo ($currentUser['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>
                                    Staff Member
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a role.
                            </div>
                            <div class="form-text">
                                <strong>Administrator:</strong> Full system access including user management<br>
                                <strong>Staff:</strong> Can manage inventory but not users
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">
                                Password <?php echo $action === 'add' ? '*' : '(leave blank to keep current)'; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   <?php echo $action === 'add' ? 'required' : ''; ?>>
                            <div class="invalid-feedback">
                                Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.
                            </div>
                            <div class="form-text">
                                Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($action === 'edit' && $currentUser && $currentUser['role'] === 'admin'): ?>
                    <?php
                    $adminCount = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'];
                    if ($adminCount <= 1):
                    ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> This is the last administrator account. The role cannot be changed to maintain system security.
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $action === 'add' ? 'Create User' : 'Update User'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view'): ?>
<!-- View User Details -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>User Details
                </h5>
                <div>
                    <a href="users.php?action=edit&id=<?php echo $currentUser['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <a href="users.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Name:</th>
                                <td>
                                    <?php echo htmlspecialchars($currentUser['name']); ?>
                                    <?php if ($currentUser['id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info ms-2">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($currentUser['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Role:</th>
                                <td>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        <span class="badge bg-danger fs-6">Administrator</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary fs-6">Staff Member</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><span class="badge bg-success fs-6">Active</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Created:</th>
                                <td class="format-datetime"><?php echo $currentUser['created_at']; ?></td>
                            </tr>
                            <tr>
                                <th>User ID:</th>
                                <td><?php echo $currentUser['id']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Role Description -->
                <div class="border-top pt-3 mt-3">
                    <h6>Role Permissions</h6>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Administrator Access</h6>
                        <ul class="mb-0">
                            <li>Full access to all system features</li>
                            <li>Can manage users and assign roles</li>
                            <li>Can view and modify all inventory data</li>
                            <li>Can access system settings and reports</li>
                            <li>Can export data and generate reports</li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-secondary">
                        <h6 class="alert-heading">Staff Member Access</h6>
                        <ul class="mb-0">
                            <li>Can manage inventory items and categories</li>
                            <li>Can record stock movements (in/out)</li>
                            <li>Can view reports and export data</li>
                            <li>Can manage suppliers</li>
                            <li>Cannot manage users or system settings</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>
