<?php
require_once 'includes/functions.php';

// Redirect to install if needed
try {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) FROM users");
} catch (Exception $e) {
    header('Location: install.php');
    exit();
}

// Redirect based on login status
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>
