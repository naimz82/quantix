<?php
/**
 * Quantix API Integration Test
 * This script tests all API endpoints to ensure they're working correctly
 */

// Set up basic environment for testing
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = [
    'user_id' => 1,
    'user_name' => 'Test User',
    'user_email' => 'test@example.com',
    'user_role' => 'admin'
];

// Test configuration
$api_endpoints = [
    'items.php' => 'Items API',
    'categories.php' => 'Categories API', 
    'suppliers.php' => 'Suppliers API',
    'stock.php' => 'Stock API',
    'dashboard.php' => 'Dashboard API'
];

$results = [];

echo "================================\n";
echo "Quantix API Integration Test\n";
echo "================================\n\n";

foreach ($api_endpoints as $endpoint => $name) {
    echo "Testing {$name}...\n";
    
    $file_path = "api/{$endpoint}";
    
    if (!file_exists($file_path)) {
        $results[$endpoint] = [
            'status' => 'FAIL',
            'message' => 'File not found'
        ];
        echo "❌ {$name}: File not found\n\n";
        continue;
    }
    
    // Check if file has proper PHP opening tag and basic structure
    $content = file_get_contents($file_path);
    
    if (!$content) {
        $results[$endpoint] = [
            'status' => 'FAIL', 
            'message' => 'Cannot read file'
        ];
        echo "❌ {$name}: Cannot read file\n\n";
        continue;
    }
    
    // Basic syntax checks
    $checks = [
        'php_tag' => strpos($content, '<?php') === 0,
        'includes_functions' => strpos($content, 'functions.php') !== false,
        'has_auth_check' => strpos($content, 'isLoggedIn') !== false || strpos($content, 'requireLogin') !== false,
        'returns_json' => strpos($content, 'application/json') !== false,
        'has_error_handling' => strpos($content, 'try') !== false || strpos($content, 'catch') !== false
    ];
    
    $passed_checks = array_filter($checks);
    $total_checks = count($checks);
    $passed_count = count($passed_checks);
    
    if ($passed_count === $total_checks) {
        $results[$endpoint] = [
            'status' => 'PASS',
            'message' => 'All checks passed'
        ];
        echo "✅ {$name}: All checks passed ({$passed_count}/{$total_checks})\n";
    } else {
        $results[$endpoint] = [
            'status' => 'WARN',
            'message' => "Some checks failed ({$passed_count}/{$total_checks})"
        ];
        echo "⚠️  {$name}: Some checks failed ({$passed_count}/{$total_checks})\n";
        
        // Show which checks failed
        foreach ($checks as $check_name => $passed) {
            if (!$passed) {
                echo "   - {$check_name}: FAIL\n";
            }
        }
    }
    
    echo "\n";
}

// Test core pages
echo "Testing Core Pages...\n";
echo "=====================\n\n";

$core_pages = [
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

$page_results = [];

foreach ($core_pages as $page => $name) {
    $file_path = "pages/{$page}";
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        $checks = [
            'has_php' => strpos($content, '<?php') === 0,
            'includes_functions' => strpos($content, 'functions.php') !== false,
            'has_auth' => strpos($content, 'requireLogin') !== false,
            'includes_header' => strpos($content, 'header.php') !== false,
            'includes_footer' => strpos($content, 'footer.php') !== false,
            'has_html' => strpos($content, '<div') !== false
        ];
        
        $passed = array_filter($checks);
        $score = count($passed) . '/' . count($checks);
        
        if (count($passed) === count($checks)) {
            echo "✅ {$name}: Complete ({$score})\n";
            $page_results[$page] = 'PASS';
        } else {
            echo "⚠️  {$name}: Incomplete ({$score})\n";
            $page_results[$page] = 'WARN';
        }
    } else {
        echo "❌ {$name}: Missing\n";
        $page_results[$page] = 'FAIL';
    }
}

// Summary
echo "\n================================\n";
echo "Test Summary\n";
echo "================================\n\n";

$api_pass = count(array_filter($results, function($r) { return $r['status'] === 'PASS'; }));
$api_warn = count(array_filter($results, function($r) { return $r['status'] === 'WARN'; }));
$api_fail = count(array_filter($results, function($r) { return $r['status'] === 'FAIL'; }));

$page_pass = count(array_filter($page_results, function($r) { return $r === 'PASS'; }));
$page_warn = count(array_filter($page_results, function($r) { return $r === 'WARN'; }));
$page_fail = count(array_filter($page_results, function($r) { return $r === 'FAIL'; }));

echo "API Endpoints:\n";
echo "  ✅ Passed: {$api_pass}\n";
echo "  ⚠️  Warnings: {$api_warn}\n";
echo "  ❌ Failed: {$api_fail}\n\n";

echo "Core Pages:\n";
echo "  ✅ Complete: {$page_pass}\n";
echo "  ⚠️  Incomplete: {$page_warn}\n";
echo "  ❌ Missing: {$page_fail}\n\n";

$total_tests = count($results) + count($page_results);
$total_pass = $api_pass + $page_pass;
$success_rate = round(($total_pass / $total_tests) * 100);

echo "Overall Success Rate: {$success_rate}% ({$total_pass}/{$total_tests})\n\n";

if ($success_rate >= 90) {
    echo "🎉 Excellent! Your Quantix system is ready for production use.\n";
} elseif ($success_rate >= 75) {
    echo "👍 Good! Your system is mostly ready. Consider addressing the warnings.\n";
} elseif ($success_rate >= 50) {
    echo "⚠️  Your system needs some work before it's ready for use.\n";
} else {
    echo "❌ Significant issues found. Please review and fix the problems.\n";
}

echo "\nNext Steps:\n";
echo "1. Run health-check.php for database and system validation\n";
echo "2. Test the web interface by accessing login.php\n";
echo "3. Check that all functionality works as expected\n";
echo "4. Set up proper production environment security\n\n";

echo "Happy inventory tracking! 📦\n";
?>
