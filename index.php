<?php
// Front controller to route all non-API, non-asset requests
// Try config inside public/config first (for production docroot), then fallback to repo root config
if (is_file(__DIR__ . '/config/config.php')) {
	require_once __DIR__ . '/config/config.php';
} else {
	require_once __DIR__ . '/../config/config.php';
}

// For safety, disable directory listing
header('X-Content-Type-Options: nosniff');

// Delegate to router
require __DIR__ . '/router.php';
