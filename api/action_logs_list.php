<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

// Optional filters
$limit = (int)($_GET['limit'] ?? 50); if ($limit < 1 || $limit > 200) $limit = 50;
$action = trim($_GET['action'] ?? '');
$userIdFilter = (int)($_GET['user_id'] ?? 0);

try {
    $pdo = Database::getInstance()->getConnection();
    $sql = 'SELECT id, user_id, session_id, action, details, ip_address, user_agent, created_at FROM action_logs WHERE 1=1';
    $params = [];
    if ($action !== '') { $sql .= ' AND action = ?'; $params[] = $action; }
    if ($userIdFilter) { $sql .= ' AND user_id = ?'; $params[] = $userIdFilter; }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
    $st = $pdo->prepare($sql); $st->execute($params);
    $rows = $st->fetchAll();
    // Decode JSON details
    foreach ($rows as &$r) { if (!empty($r['details'])) { $r['details'] = json_decode($r['details'], true); } }
    echo json_encode(['ok'=>true,'items'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_error']);
}
