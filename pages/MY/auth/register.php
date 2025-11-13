<?php
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/register.html');
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';
$familyTok = trim($_POST['family_token'] ?? '');

// Basic validation
if ($full_name === '' || $email === '' || $password === '' || $confirm === '') {
    header('Location: /auth/register.html?error=missing&full_name=' . urlencode($full_name) . '&email=' . urlencode($email) . ($familyTok!=='' ? '&family_token=' . urlencode($familyTok) : ''));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /auth/register.html?error=invalid_email&full_name=' . urlencode($full_name) . ($familyTok!=='' ? '&family_token=' . urlencode($familyTok) : ''));
    exit;
}
if ($password !== $confirm) {
    header('Location: /auth/register.html?error=mismatch&full_name=' . urlencode($full_name) . '&email=' . urlencode($email) . ($familyTok!=='' ? '&family_token=' . urlencode($familyTok) : ''));
    exit;
}
if (strlen($password) < 8) {
    header('Location: /auth/register.html?error=weak&full_name=' . urlencode($full_name) . '&email=' . urlencode($email) . ($familyTok!=='' ? '&family_token=' . urlencode($familyTok) : ''));
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    // Check uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
    header('Location: /auth/register.html?error=exists&full_name=' . urlencode($full_name) . ($familyTok!=='' ? '&family_token=' . urlencode($familyTok) : ''));
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, is_active, email_verified) VALUES (?, ?, ?, 1, 0)');
    $insert->execute([$full_name, $email, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // Auto-login
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $full_name;

    // If a family token was provided and valid, attach user to that family and route to token wizard
    if ($familyTok !== '') {
        $hashTok = hash('sha256', $familyTok);
        $chk = $pdo->prepare('SELECT id FROM families WHERE family_token_hash = ? AND (family_token_expires_at IS NULL OR family_token_expires_at > NOW()) LIMIT 1');
        $chk->execute([$hashTok]);
        $famId = (int)($chk->fetchColumn() ?: 0);
        if ($famId) {
            // Set this family as the user's current family and cache in session
            $upFam = $pdo->prepare('UPDATE users SET families_id = ? WHERE id = ?');
            $upFam->execute([$famId, $userId]);
            $_SESSION['families_id'] = $famId;
            header('Location: /pages/my/chat_token_wizard.html?tok=' . urlencode($familyTok));
            exit;
        }
        // If token invalid/expired, continue to normal flow
    }

    // Default: route to chat wizard start
    header('Location: /pages/my/chat_wizard.html?step=1');
    exit;
} catch (Throwable $e) {
    header('Location: /auth/register.html?error=server');
    exit;
}
