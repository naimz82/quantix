# Quantix Setup Guide

This guide will help you set up the Quantix Inventory Management System on your server.

## 🚀 Localhost Quick Start

For local development (XAMPP/WAMP/MAMP), follow these simple steps:

```bash
# 1. Create required directories
mkdir -p uploads logs backups && chmod 755 uploads logs backups

# 2. Open your browser and run the installer
# http://localhost/quantix/install.php
```

**That's it!** The installer will handle everything else automatically.

---

## Full Setup Guide

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/quantix.git
cd quantix
```

### 2. Create Required Directories

Create the necessary directories for uploads, logs, and backups:

```bash
# Create required directories
mkdir -p uploads logs backups

# Set appropriate permissions
chmod 755 uploads logs backups
```

### 3. Configure the Application

Copy the template configuration files and customize them:

```bash
# Copy the main configuration template
cp includes/config.template.php includes/config.php

# Edit the configuration file with your settings
nano includes/config.php
```

### 4. Set Up the Database (Optional)

The web installer will create the database automatically, but you can create it manually if preferred:

```sql
CREATE DATABASE quantix_inventory;
# Note: install.php will handle this step for you
```

### 5. Run the Installation

Navigate to your application URL and run the installer:

```
http://yourdomain.com/quantix/install.php
```

The installer will:
- Create the database (if it doesn't exist)
- Set up all database tables
- Create sample categories and suppliers
- Set up the admin user account

### 6. Complete Setup (Optional)

The installer handles most setup automatically. For manual file permissions (if needed):

```bash
# Only run if you encounter permission issues
chmod 644 *.php
find . -name "*.php" -exec chmod 644 {} \;
```

## Configuration Files

### Required Configuration

**includes/config.php** - Main application configuration
- Copy from `includes/config.template.php`
- Update database credentials
- Set application settings
- Configure security options

### Optional Configuration

**deploy-config.sh** - Deployment configuration
- Copy from `deploy-config.template.sh`
- Configure server details for automated deployment

**docker.env** - Docker environment variables (Docker deployments only)
- Copy from `docker.env.template`
- Configure for containerized deployment using docker-compose

## Environment-Specific Setup

### Development Environment

1. Enable debug mode in configuration
2. Use local database
3. Set appropriate error reporting

```php
// In config.php
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Production Environment

1. Disable debug mode
2. Set secure database credentials
3. Enable security headers
4. Set up SSL certificates

```php
// In config.php
define('DEBUG_MODE', false);
error_reporting(0);
ini_set('display_errors', 0);
```

### Staging Environment

1. Use staging database
2. Enable limited debugging
3. Test deployment procedures

## Security Checklist

- [ ] Change default admin password
- [ ] Update SECRET_KEY in configuration
- [ ] Set strong database passwords
- [ ] Configure proper file permissions
- [ ] Enable HTTPS in production
- [ ] Set up regular backups
- [ ] Configure firewall rules
- [ ] Keep PHP and MySQL updated

## Backup and Maintenance

### Automated Backups

Set up a cron job for regular backups:

```bash
# Add to crontab (crontab -e)
0 2 * * * /path/to/quantix/backup.sh
```

### Manual Backup

```bash
# Database backup
mysqldump -u username -p quantix_inventory > backup_$(date +%Y%m%d).sql

# File backup
tar -czf quantix_backup_$(date +%Y%m%d).tar.gz /path/to/quantix/
```

### Health Checks

Run regular health checks:

```bash
php health-check.php
```

## Troubleshooting

### Common Issues

**Database Connection Error**
- Check database credentials in config.php
- Verify MySQL service is running
- Ensure database exists

**Permission Errors**
- Check file permissions (755 for directories, 644 for files)
- Ensure web server can write to uploads/ and logs/

**Session Issues**
- Check session directory permissions
- Verify session configuration in PHP

### Log Files

Check these log files for errors:
- `logs/php_errors.log` - PHP errors
- `logs/application.log` - Application logs
- `/var/log/apache2/error.log` - Apache errors
- `/var/log/nginx/error.log` - Nginx errors

## Advanced Configuration

### Load Balancing

For high-traffic environments, configure load balancing:
- Use multiple web servers
- Set up shared session storage (Redis/Memcached)
- Configure database replication

### Caching

Enable caching for better performance:
- Configure Redis or Memcached
- Enable OPcache for PHP
- Use CDN for static assets

### Monitoring

Set up monitoring and alerting:
- Configure application monitoring
- Set up database monitoring
- Monitor disk space and server resources

## Support

For additional help:
1. Check the main README.md file
2. Review the configuration templates
3. Run the health check script
4. Check the logs for error messages

## Contributing

If you find issues or want to contribute:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request
