<?php
// Temporary wrapper to maintain compatibility with links to dashboard.php
// Redirect permanently to the HTML dashboard while preserving standards mode.
header('Location: /pages/my/dashboard.html', true, 302);
exit;
