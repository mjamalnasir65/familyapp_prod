<?php
/**
 * Friendly URL Testing Script
 * Tests all 45 friendly URLs defined in router.php
 * 
 * Usage: php tests/test_friendly_urls.php
 * Or browse to: http://localhost:8080/tests/test_friendly_urls.php
 */

// Set up test environment
$baseUrl = 'http://localhost:8080';
$results = [];
$passed = 0;
$failed = 0;

// All friendly URLs from router.php (organized by category)
$friendlyUrls = [
    // Authentication
    'login',
    'register',
    'logout',
    'tok_register',
    
    // Main Application
    'dashboard',
    
    // Wizard
    'wizard',
    'chat_wizard',
    'token_wizard',
    'chat-token-wizard',
    'family_token_wizard',
    
    // Family Tree
    'tree',
    'view_tree',
    'edit_tree',
    'test_tree',
    
    // Person Management
    'edit_persons',
    'chat_edit_persons',
    'profile_persons',
    'expand',
    'chat-expand',
    'expand_children',
    'expand_partners',
    'expand_siblings',
    
    // Invitations
    'invites',
    'chat-invites',
    'accept-invite',
    
    // Pending Decisions
    'pd_view',
    'pd_tree',
    'pending_decisions',
    
    // Profile & Settings
    'profile',
    'settings',
    
    // Other Pages
    'families',
    'people',
    'about',
    'terms',
    'public',
    
    // Admin
    'admin_dashboard',
    'admin_pending_people',
    'admin',
];

// Test function
function testUrl($url, $baseUrl) {
    $fullUrl = $baseUrl . '/' . $url;
    
    // Use curl if available, otherwise use get_headers
    if (function_exists('curl_init')) {
        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        
        return [
            'status' => $httpCode,
            'redirect' => $redirectUrl ?: null,
            'expected' => ($httpCode === 302 || $httpCode === 200),
        ];
    } else {
        // Fallback to get_headers
        $headers = @get_headers($fullUrl, 1);
        if ($headers === false) {
            return [
                'status' => 0,
                'redirect' => null,
                'expected' => false,
                'error' => 'Failed to connect'
            ];
        }
        
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
        $httpCode = isset($matches[1]) ? (int)$matches[1] : 0;
        
        $redirectUrl = null;
        if (isset($headers['Location'])) {
            $redirectUrl = is_array($headers['Location']) 
                ? $headers['Location'][0] 
                : $headers['Location'];
        }
        
        return [
            'status' => $httpCode,
            'redirect' => $redirectUrl,
            'expected' => ($httpCode === 302 || $httpCode === 200),
        ];
    }
}

// Run tests
echo "Testing " . count($friendlyUrls) . " friendly URLs...\n\n";
echo str_repeat("=", 80) . "\n";

foreach ($friendlyUrls as $url) {
    $result = testUrl($url, $baseUrl);
    $results[$url] = $result;
    
    $status = $result['expected'] ? '✅ PASS' : '❌ FAIL';
    $httpCode = $result['status'];
    $redirect = $result['redirect'] ?? 'N/A';
    
    echo sprintf(
        "%-30s | %-10s | HTTP %3d | Redirect: %s\n",
        $url,
        $status,
        $httpCode,
        $redirect
    );
    
    if ($result['expected']) {
        $passed++;
    } else {
        $failed++;
    }
}

echo str_repeat("=", 80) . "\n";
echo "\nRESULTS:\n";
echo "  Passed: $passed / " . count($friendlyUrls) . "\n";
echo "  Failed: $failed / " . count($friendlyUrls) . "\n";
echo "  Success Rate: " . round(($passed / count($friendlyUrls)) * 100, 2) . "%\n";

if ($failed > 0) {
    echo "\n❌ FAILED URLS:\n";
    foreach ($results as $url => $result) {
        if (!$result['expected']) {
            echo "  - /$url (HTTP {$result['status']})\n";
            if (isset($result['error'])) {
                echo "    Error: {$result['error']}\n";
            }
        }
    }
}

echo "\n";

// Detailed HTML report if accessed via browser
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Friendly URL Test Results</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 40px 20px;
                color: #333;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 { font-size: 32px; margin-bottom: 10px; }
            .header p { font-size: 16px; opacity: 0.9; }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                padding: 30px;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            .stat-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .stat-card h3 { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
            .stat-card .number { font-size: 36px; font-weight: bold; color: #667eea; }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            thead {
                background: #f8f9fa;
                border-bottom: 2px solid #dee2e6;
            }
            th, td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #dee2e6;
            }
            th { font-weight: 600; color: #495057; }
            tr:hover { background: #f8f9fa; }
            .status-pass { color: #28a745; font-weight: bold; }
            .status-fail { color: #dc3545; font-weight: bold; }
            .url { font-family: 'Courier New', monospace; color: #667eea; }
            .redirect { font-size: 12px; color: #6c757d; word-break: break-all; }
            .footer {
                padding: 20px 30px;
                background: #f8f9fa;
                text-align: center;
                color: #6c757d;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔗 Friendly URL Test Results</h1>
                <p>Testing <?= count($friendlyUrls) ?> friendly URLs from router.php</p>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>Total URLs</h3>
                    <div class="number"><?= count($friendlyUrls) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Passed</h3>
                    <div class="number" style="color: #28a745;"><?= $passed ?></div>
                </div>
                <div class="stat-card">
                    <h3>Failed</h3>
                    <div class="number" style="color: #dc3545;"><?= $failed ?></div>
                </div>
                <div class="stat-card">
                    <h3>Success Rate</h3>
                    <div class="number"><?= round(($passed / count($friendlyUrls)) * 100, 1) ?>%</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Friendly URL</th>
                        <th>Status</th>
                        <th>HTTP Code</th>
                        <th>Redirect Target</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $url => $result): ?>
                    <tr>
                        <td><span class="url">/<?= htmlspecialchars($url) ?></span></td>
                        <td class="<?= $result['expected'] ? 'status-pass' : 'status-fail' ?>">
                            <?= $result['expected'] ? '✅ PASS' : '❌ FAIL' ?>
                        </td>
                        <td><?= $result['status'] ?></td>
                        <td class="redirect"><?= htmlspecialchars($result['redirect'] ?? 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="footer">
                Generated: <?= date('Y-m-d H:i:s') ?> | 
                Base URL: <?= htmlspecialchars($baseUrl) ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
