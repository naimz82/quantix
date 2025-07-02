#!/bin/bash

# Quantix Inventory System - Quick Deployment Script
# This script helps set up the Quantix system quickly

echo "=================================="
echo "Quantix Inventory System Deployment"
echo "=================================="
echo

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    echo "⚠️  Please don't run this script as root"
    exit 1
fi

# Check required commands
commands=("php" "mysql" "curl" "wget")
for cmd in "${commands[@]}"; do
    if ! command -v $cmd &> /dev/null; then
        echo "❌ $cmd is not installed"
        exit 1
    fi
done

echo "✅ All required commands are available"

# Check PHP version
php_version=$(php -r "echo PHP_VERSION;")
echo "📋 PHP Version: $php_version"

# Check PHP extensions
required_extensions=("pdo" "pdo_mysql" "json" "session" "curl")
missing_extensions=()

for ext in "${required_extensions[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        missing_extensions+=("$ext")
    fi
done

if [ ${#missing_extensions[@]} -eq 0 ]; then
    echo "✅ All required PHP extensions are loaded"
else
    echo "❌ Missing PHP extensions: ${missing_extensions[*]}"
    echo "Please install missing extensions and try again"
    exit 1
fi

# Set permissions
echo "🔧 Setting up directory permissions..."
mkdir -p uploads exports logs
chmod 755 uploads exports logs
chmod 644 *.php
chmod 644 includes/*.php
chmod 644 pages/*.php
chmod 644 api/*.php
chmod 644 assets/css/*.css
chmod 644 assets/js/*.js

echo "✅ Permissions set"

# Database setup prompt
echo
read -p "🗄️  Do you want to set up the database now? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📋 Please provide database information:"
    read -p "Database host (localhost): " db_host
    db_host=${db_host:-localhost}
    
    read -p "Database name: " db_name
    if [ -z "$db_name" ]; then
        echo "❌ Database name is required"
        exit 1
    fi
    
    read -p "Database user: " db_user
    if [ -z "$db_user" ]; then
        echo "❌ Database user is required"
        exit 1
    fi
    
    read -s -p "Database password: " db_pass
    echo
    
    # Test database connection
    echo "🔍 Testing database connection..."
    if mysql -h "$db_host" -u "$db_user" -p"$db_pass" -e "SELECT 1;" &> /dev/null; then
        echo "✅ Database connection successful"
        
        # Create database if it doesn't exist
        mysql -h "$db_host" -u "$db_user" -p"$db_pass" -e "CREATE DATABASE IF NOT EXISTS \`$db_name\`;"
        
        # Import schema
        echo "📋 Importing database schema..."
        mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" < dbschema.sql
        
        # Update config file
        echo "🔧 Updating configuration..."
        cp includes/config.php includes/config.php.backup
        
        sed -i "s/define('DB_HOST', 'localhost');/define('DB_HOST', '$db_host');/" includes/config.php
        sed -i "s/define('DB_NAME', 'quantix_inventory');/define('DB_NAME', '$db_name');/" includes/config.php
        sed -i "s/define('DB_USER', 'root');/define('DB_USER', '$db_user');/" includes/config.php
        sed -i "s/define('DB_PASS', '');/define('DB_PASS', '$db_pass');/" includes/config.php
        
        echo "✅ Database setup completed"
    else
        echo "❌ Database connection failed"
        echo "Please check your credentials and try again"
        exit 1
    fi
fi

# Web server configuration
echo
echo "🌐 Web Server Configuration:"
echo "For Apache, add to your .htaccess or VirtualHost:"
echo
cat << 'EOF'
<Directory "/path/to/quantix">
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>

# Redirect to login if accessing root
DirectoryIndex dashboard.php login.php index.php
EOF

echo
echo "For Nginx, add to your server block:"
echo
cat << 'EOF'
location /quantix {
    try_files $uri $uri/ /quantix/login.php;
    index dashboard.php login.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
EOF

# Security recommendations
echo
echo "🔒 Security Recommendations:"
echo "1. Change default admin password after first login"
echo "2. Enable HTTPS for production use"
echo "3. Set up regular database backups"
echo "4. Monitor log files in the logs/ directory"
echo "5. Keep PHP and MySQL updated"

# Final checks
echo
echo "🏁 Final System Check:"
echo "Running health check..."

# Check if we can create a test PHP script
cat > test_deployment.php << 'EOF'
<?php
require_once 'includes/functions.php';

try {
    $db = getDB();
    echo "✅ Database connection: OK\n";
    
    $tables = ['users', 'categories', 'items', 'suppliers', 'stock_in', 'stock_out'];
    foreach ($tables as $table) {
        $result = fetchOne("SHOW TABLES LIKE '{$table}'");
        if ($result) {
            echo "✅ Table {$table}: OK\n";
        } else {
            echo "❌ Table {$table}: MISSING\n";
        }
    }
    
    echo "✅ System deployment: SUCCESSFUL\n";
    echo "\n🎉 Quantix is ready to use!\n";
    echo "📱 Access your system at: http://your-domain/quantix/\n";
    echo "👤 Default admin: admin@example.com / password123\n";
    
} catch (Exception $e) {
    echo "❌ System check failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
EOF

php test_deployment.php
rm test_deployment.php

echo
echo "=================================="
echo "Deployment completed successfully!"
echo "=================================="
echo
echo "📋 Next Steps:"
echo "1. Access your system at: http://your-domain/quantix/"
echo "2. Run the installer if database wasn't set up: /quantix/install.php"
echo "3. Log in with default admin credentials"
echo "4. Change the default password"
echo "5. Start adding your inventory items!"
echo
echo "📚 Documentation: README.md"
echo "🏥 Health Check: /quantix/health-check.php"
echo
echo "Happy inventory tracking! 📦"
