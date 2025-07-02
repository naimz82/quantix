<?php
/**
 * Quantix Inventory Management System - Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to 'config.php' in the same directory
 * 2. Update the configuration values below with your actual settings
 * 3. Never commit the actual config.php file to version control
 */

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================

// Database host (usually 'localhost' for local development)
define('DB_HOST', 'localhost');

// Database name (create this database in MySQL)
define('DB_NAME', 'quantix_inventory');

// Database username (MySQL user with privileges to the database)
define('DB_USER', 'your_db_username');

// Database password (corresponding password for the MySQL user)
define('DB_PASS', 'your_db_password');

// =============================================================================
// APPLICATION CONFIGURATION
// =============================================================================

// Application name (displayed in the interface)
define('APP_NAME', 'Quantix Inventory Tracker');

// Application version
define('APP_VERSION', '1.0.0');

// Base URL (path to your application from web root)
// Examples: 
//   - For root installation: '/'
//   - For subdirectory: '/quantix'
//   - For subdomain: '/'
define('BASE_URL', '/quantix');

// =============================================================================
// SECURITY CONFIGURATION
// =============================================================================

// Session timeout (in seconds)
// 3600 = 1 hour, 28800 = 8 hours, 86400 = 24 hours
define('SESSION_TIMEOUT', 3600);

// Minimum password length for user accounts
define('PASSWORD_MIN_LENGTH', 8);

// Secret key for additional security (generate a random string)
// You can use: openssl_rand -base64 32
define('SECRET_KEY', 'your-secret-key-here-change-this');

// =============================================================================
// FILE UPLOAD CONFIGURATION
// =============================================================================

// Upload directory (relative to application root)
define('UPLOAD_PATH', 'uploads/');

// Maximum file size for uploads (in bytes)
// 5242880 = 5MB, 10485760 = 10MB
define('MAX_FILE_SIZE', 5242880);

// Allowed file extensions for uploads
define('ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// =============================================================================
// EMAIL CONFIGURATION (Optional - for notifications)
// =============================================================================

// SMTP settings (if you want to send email notifications)
define('SMTP_HOST', 'your-smtp-server.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@domain.com');
define('SMTP_PASSWORD', 'your-email-password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Quantix Inventory System');

// =============================================================================
// SYSTEM CONFIGURATION
// =============================================================================

// Timezone (see: https://www.php.net/manual/en/timezones.php)
date_default_timezone_set('UTC');

// Error reporting
// DEVELOPMENT: Use E_ALL and display_errors = 1
// PRODUCTION: Use 0 and display_errors = 0
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// =============================================================================
// FEATURE FLAGS (Enable/Disable Features)
// =============================================================================

// Enable/disable user registration (if you want public registration)
define('ALLOW_REGISTRATION', false);

// Enable/disable email notifications for low stock
define('ENABLE_EMAIL_ALERTS', false);

// Enable/disable automatic backups
define('ENABLE_AUTO_BACKUP', true);

// Enable/disable API access
define('ENABLE_API', true);

// =============================================================================
// BACKUP CONFIGURATION
// =============================================================================

// Backup directory
define('BACKUP_PATH', 'backups/');

// Number of backups to keep
define('BACKUP_RETENTION_COUNT', 30);

// =============================================================================
// DEBUGGING (Set to false in production)
// =============================================================================

// Enable debug mode (shows detailed error messages)
define('DEBUG_MODE', true);

// Enable query logging (logs all database queries)
define('LOG_QUERIES', false);

// =============================================================================
// ENVIRONMENT DETECTION
// =============================================================================

// Detect if running in development or production
if (isset($_SERVER['HTTP_HOST'])) {
    $is_development = in_array($_SERVER['HTTP_HOST'], [
        'localhost',
        '127.0.0.1',
        'localhost:8000',
        'dev.yourdomain.com'
    ]);
    
    define('IS_DEVELOPMENT', $is_development);
    define('IS_PRODUCTION', !$is_development);
} else {
    define('IS_DEVELOPMENT', true);
    define('IS_PRODUCTION', false);
}

// Adjust settings based on environment
if (IS_PRODUCTION) {
    // Production settings
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
} else {
    // Development settings
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
}

// =============================================================================
// SECURITY HEADERS (Optional but recommended)
// =============================================================================

if (!headers_sent()) {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Strict Transport Security (HTTPS only)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

?>
