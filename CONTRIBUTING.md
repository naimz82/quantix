# Contributing to Quantix

Thank you for your interest in contributing to Quantix! This document provides guidelines and information for contributors.

## 🤝 How to Contribute

### Reporting Issues

Before creating an issue, please:
1. Check if the issue already exists
2. Use a clear and descriptive title
3. Provide detailed steps to reproduce
4. Include relevant error messages and screenshots
5. Specify your environment (PHP version, MySQL version, browser, OS)

### Suggesting Features

For feature requests:
1. Check if the feature has already been requested
2. Explain the use case and benefits
3. Provide mockups or detailed descriptions if possible
4. Consider the scope and complexity

### Code Contributions

#### Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/yourusername/quantix.git`
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Set up your development environment (see Setup Guide)

#### Development Setup

```bash
# Copy configuration template
cp includes/config.template.php includes/config.php

# Set up your development database
mysql -u root -p
CREATE DATABASE quantix_dev;

# Update config.php with your database details
# Run the installer or import schema
mysql -u root -p quantix_dev < dbschema.sql
```

#### Coding Standards

**PHP Code Style:**
- Follow PSR-12 coding standard
- Use meaningful variable and function names
- Add PHPDoc comments for functions and classes
- Keep functions focused and concise
- Use prepared statements for all database queries

**Frontend Standards:**
- Use Bootstrap 5 classes consistently
- Write semantic HTML
- Minimize custom CSS (prefer Bootstrap utilities)
- Use jQuery for DOM manipulation
- Comment complex JavaScript logic

**Database Standards:**
- Use descriptive table and column names
- Include proper indexes
- Use appropriate data types
- Add foreign key constraints where applicable

#### Example Code Style

```php
<?php
/**
 * Add a new inventory item
 * 
 * @param array $itemData Item information
 * @return int|false Item ID on success, false on failure
 */
function addInventoryItem(array $itemData): int|false {
    try {
        // Validate required fields
        if (empty($itemData['name']) || empty($itemData['category_id'])) {
            throw new InvalidArgumentException('Name and category are required');
        }
        
        // Insert the item
        return insertRecord('items', [
            'name' => sanitizeInput($itemData['name']),
            'category_id' => (int)$itemData['category_id'],
            'quantity' => (int)($itemData['quantity'] ?? 0),
            'low_stock_threshold' => (int)($itemData['low_stock_threshold'] ?? 5)
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to add inventory item: " . $e->getMessage());
        return false;
    }
}
```

### Pull Request Process

1. **Create a Feature Branch**
   ```bash
   git checkout -b feature/add-barcode-scanning
   ```

2. **Make Your Changes**
   - Write clean, documented code
   - Follow the coding standards
   - Add or update tests as needed

3. **Test Your Changes**
   ```bash
   # Run syntax check
   find . -name "*.php" -exec php -l {} \;
   
   # Run integration tests
   php test-integration.php
   
   # Run health check
   php health-check.php
   ```

4. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Add barcode scanning functionality
   
   - Add barcode input field to item form
   - Implement barcode validation
   - Update database schema for barcode column
   - Add barcode display in item list"
   ```

5. **Push and Create Pull Request**
   ```bash
   git push origin feature/add-barcode-scanning
   ```

6. **Pull Request Guidelines**
   - Use a clear, descriptive title
   - Provide detailed description of changes
   - Link related issues
   - Include screenshots for UI changes
   - Ensure all tests pass

### Commit Message Format

Use the following format for commit messages:

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Example:**
```
feat: Add inventory analytics dashboard

- Add charts for stock levels and movement trends
- Implement filters for date ranges and categories
- Add export functionality for analytics data
- Update navigation to include analytics link

Closes #123
```

## 🧪 Testing

### Running Tests

```bash
# Syntax check all PHP files
find . -name "*.php" -exec php -l {} \;

# Run integration tests
php test-integration.php

# Run health check
php health-check.php
```

### Writing Tests

When adding new features:
1. Add appropriate validation to existing test files
2. Create new test cases for complex functionality
3. Test both success and failure scenarios
4. Include database transaction tests where applicable

## 📚 Documentation

### Updating Documentation

When making changes:
1. Update relevant comments in code
2. Update README.md if needed
3. Update SETUP.md for configuration changes
4. Add entries to CHANGELOG.md
5. Update API documentation if applicable

### Documentation Style

- Use clear, concise language
- Provide examples where helpful
- Include screenshots for UI changes
- Keep documentation up to date with code changes

## 🏗️ Architecture Guidelines

### File Organization

```
quantix/
├── api/           # REST API endpoints
├── assets/        # CSS, JS, images
├── exports/       # Export functionality
├── includes/      # Core PHP includes
├── pages/         # Main application pages
├── .github/       # GitHub workflows and templates
└── docs/          # Additional documentation
```

### Adding New Features

1. **API First**: Create API endpoint before UI
2. **Security**: Always validate input and check permissions
3. **Database**: Use transactions for multi-table operations
4. **UI/UX**: Follow existing design patterns
5. **Documentation**: Document new features and APIs

### Database Changes

1. Update `dbschema.sql` with new schema
2. Create migration script if needed
3. Update relevant functions in `includes/functions.php`
4. Test with both new and existing data

## 🐛 Bug Fixes

### Priority Levels

- **Critical**: Security vulnerabilities, data loss, system crashes
- **High**: Major functionality broken, significant performance issues
- **Medium**: Minor functionality issues, UI problems
- **Low**: Cosmetic issues, minor enhancements

### Bug Fix Process

1. Reproduce the bug
2. Identify root cause
3. Create minimal fix
4. Test thoroughly
5. Document the fix

## 📋 Review Process

All pull requests will be reviewed for:
- Code quality and style
- Security implications
- Performance impact
- Documentation completeness
- Test coverage
- Backward compatibility

## 🎯 Areas for Contribution

We especially welcome contributions in these areas:

### High Priority
- Security enhancements
- Performance optimizations
- Mobile responsiveness improvements
- API documentation
- Test coverage expansion

### Medium Priority
- Additional export formats (PDF, Excel)
- Advanced reporting features
- Barcode scanning integration
- Multi-language support
- Email notifications

### Low Priority
- UI/UX enhancements
- Additional chart types
- Integration with external services
- Advanced search features

## 📝 License

By contributing to Quantix, you agree that your contributions will be licensed under the same license as the project.

## 💬 Questions?

If you have questions about contributing:
1. Check the existing documentation
2. Search existing issues and discussions
3. Create a new issue with the "question" label
4. Join our community discussions

## 🙏 Recognition

Contributors will be recognized in:
- README.md contributors section
- CHANGELOG.md for significant contributions
- Release notes for major contributions

Thank you for helping make Quantix better! 🚀
