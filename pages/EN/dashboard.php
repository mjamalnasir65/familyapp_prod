<?php
// Temporary wrapper to maintain compatibility with links to dashboard.php
// Redirect permanently to the HTML dashboard while preserving standards mode.
// Redirect to the static dashboard HTML under language folder (root-relative)
header('Location: /pages/EN/dashboard.html', true, 302);
exit;
