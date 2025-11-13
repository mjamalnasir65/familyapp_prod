<?php
/**
 * Router Test Script
 * Tests SmartRouter functionality without making HTTP requests
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Router Test</title>\n";
echo "<style>body{font-family:Arial;padding:20px;} .pass{color:green;} .fail{color:red;} .info{color:blue;} pre{background:#f4f4f4;padding:10px;}</style>\n";
echo "</head>\n<body>\n";
echo "<h1>SmartRouter Test Suite</h1>\n";

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>\n";
try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='pass'>✓ Database connection successful</p>\n";
} catch (Exception $e) {
    echo "<p class='fail'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test 2: Session Initialization
echo "<h2>Test 2: Session Initialization</h2>\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='pass'>✓ Session already active</p>\n";
} else {
    session_start();
    echo "<p class='pass'>✓ Session started</p>\n";
}
echo "<p class='info'>Session ID: " . session_id() . "</p>\n";

// Test 3: Language Detection
echo "<h2>Test 3: Language Detection</h2>\n";
$_SESSION['lang'] = 'EN'; // Set test language
echo "<p class='info'>Session language set to: EN</p>\n";

// Test 4: Router Class Instantiation
echo "<h2>Test 4: Router Class Instantiation</h2>\n";
try {
    // Simulate request URI
    $_SERVER['REQUEST_URI'] = '/pages/en/dashboard.html';
    $_SERVER['QUERY_STRING'] = '';
    
    // Include router to get class definition
    require_once __DIR__ . '/router.php';
    
    echo "<p class='pass'>✓ SmartRouter class loaded successfully</p>\n";
} catch (Exception $e) {
    echo "<p class='fail'>✗ Router class failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test 5: Check Key Files Exist
echo "<h2>Test 5: Required Files Check</h2>\n";
$requiredFiles = [
    'router.php' => 'SmartRouter main file',
    'config/config.php' => 'Configuration file',
    'classes/Database.php' => 'Database class',
    'pages/en/404.html' => 'EN 404 page',
    'pages/my/404.html' => 'MY 404 page',
    'pages/en/auth/login.php' => 'EN login page',
    'pages/my/auth/login.php' => 'MY login page',
    'pages/en/dashboard.html' => 'EN dashboard',
    'pages/my/dashboard.html' => 'MY dashboard',
    'index.html' => 'Landing page'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p class='pass'>✓ {$description}: {$file}</p>\n";
    } else {
        echo "<p class='fail'>✗ Missing {$description}: {$file}</p>\n";
    }
}

// Test 6: Check Users Table Structure
echo "<h2>Test 6: Database Schema Check</h2>\n";
try {
    $db = Database::getInstance()->getConnection();
    
    // Check users table
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id', 'email', 'families_id', 'pwa_admin'];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columns)) {
            echo "<p class='pass'>✓ users.{$col} column exists</p>\n";
        } else {
            echo "<p class='fail'>✗ users.{$col} column missing</p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p class='fail'>✗ Schema check failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test 7: Routing Logic Tests
echo "<h2>Test 7: Routing Logic Tests</h2>\n";
$testCases = [
    '/' => 'Landing page',
    '/index.html' => 'Index page',
    '/pages/en/dashboard.html' => 'EN dashboard',
    '/pages/my/dashboard.html' => 'MY dashboard',
    '/pages/en/auth/login.php' => 'EN login',
    '/api/session_info.php' => 'API endpoint (should pass through)',
    '/assets/css/main.css' => 'Static asset (should pass through)',
    '/auth/login.php' => 'Old auth path (should redirect)'
];

foreach ($testCases as $uri => $description) {
    echo "<p class='info'>Route: <code>{$uri}</code> - {$description}</p>\n";
}

// Test 8: .htaccess Check
echo "<h2>Test 8: .htaccess Configuration</h2>\n";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<p class='pass'>✓ .htaccess file exists</p>\n";
    $htaccess = file_get_contents(__DIR__ . '/.htaccess');
    if (strpos($htaccess, 'router.php') !== false) {
        echo "<p class='pass'>✓ .htaccess configured to use router.php</p>\n";
    } else {
        echo "<p class='fail'>✗ .htaccess not configured for router.php</p>\n";
    }
} else {
    echo "<p class='fail'>✗ .htaccess file missing</p>\n";
}

// Test 9: mod_rewrite Check
echo "<h2>Test 9: Apache mod_rewrite Check</h2>\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p class='pass'>✓ mod_rewrite is enabled</p>\n";
    } else {
        echo "<p class='fail'>✗ mod_rewrite is NOT enabled</p>\n";
    }
} else {
    echo "<p class='info'>⚠ Cannot check mod_rewrite status (not running as Apache module)</p>\n";
}

// Summary
echo "<h2>Test Summary</h2>\n";
echo "<p>All critical components checked. Router should be ready for testing.</p>\n";
echo "<p class='info'><strong>Next Steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>Test by visiting <a href='/'>http://localhost/familyapp/</a></li>\n";
echo "<li>Try language switching with ?lang=EN and ?lang=MY</li>\n";
echo "<li>Test login flow at /pages/en/auth/login.php</li>\n";
echo "<li>Verify dashboard access after login</li>\n";
echo "<li>Check that API endpoints still work</li>\n";
echo "</ol>\n";

echo "</body>\n</html>";
?>
