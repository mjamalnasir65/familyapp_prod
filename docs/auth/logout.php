<?php
// Adjust path for new public/ layout
require_once __DIR__ . '/../config/config.php';

// Destroy PHP session on server
$_SESSION = [];
if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
                $params['path'] ?? '/', $params['domain'] ?? '',
                (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true)
        );
}
session_destroy();

// Prevent caches from serving this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Output a tiny HTML page that clears browser storage and then redirects to landing
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="referrer" content="no-referrer" />
    <meta name="robots" content="noindex" />
    <title>Signing out…</title>
    <script>
        (function(){
            try { sessionStorage.clear(); } catch(_){}
            try { localStorage.clear(); } catch(_){}
            // Also unregister any service workers to avoid stale control
            if ('serviceWorker' in navigator) {
                try { navigator.serviceWorker.getRegistrations().then(function(regs){ regs.forEach(function(reg){ reg.unregister().catch(function(){}); }); }); } catch(_){}
            }
            // Redirect to landing
            setTimeout(function(){ location.replace('<?php echo isset($_SESSION['lang']) && strtoupper($_SESSION['lang'])==='EN' ? "/pages/EN/public.html" : "/pages/MY/public.html"; ?>'); }, 10);
        })();
    </script>
</head>
<body>
    <noscript>
    <meta http-equiv="refresh" content="0;url=<?php echo (isset($_SESSION['lang']) && strtoupper($_SESSION['lang'])==='EN') ? '/pages/EN/public.html' : '/pages/MY/public.html'; ?>" />
    </noscript>
    Signing out…
</body>
</html>
