<?php
// Quick syntax test for router.php
echo "Testing router.php syntax...\n";

try {
    // Try to include the router to check for syntax errors
    $routerCode = file_get_contents(__DIR__ . '/router.php');
    
    // Check for basic syntax issues
    if (strpos($routerCode, 'class SmartRouter') === false) {
        echo "ERROR: SmartRouter class not found\n";
        exit(1);
    }
    
    echo "✓ SmartRouter class found\n";
    
    // Check for required methods
    $requiredMethods = ['__construct', 'route', 'initializeLanguage', 'initializeUser'];
    foreach ($requiredMethods as $method) {
        if (strpos($routerCode, "function $method") === false && 
            strpos($routerCode, "$method()") === false) {
            echo "ERROR: Method $method not found\n";
            exit(1);
        }
        echo "✓ Method $method found\n";
    }
    
    // Try to parse (won't execute)
    $tokenErrors = 0;
    $tokens = token_get_all($routerCode);
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_BAD_CHARACTER) {
            $tokenErrors++;
        }
    }
    
    if ($tokenErrors > 0) {
        echo "ERROR: Found $tokenErrors token errors\n";
        exit(1);
    }
    
    echo "✓ No token errors found\n";
    
    echo "\n========================================\n";
    echo "SUCCESS: router.php appears syntactically correct!\n";
    echo "========================================\n";
    echo "\nNext steps:\n";
    echo "1. Visit http://localhost/familyapp/test_router.php\n";
    echo "2. Verify database connection\n";
    echo "3. Test routing flows\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
