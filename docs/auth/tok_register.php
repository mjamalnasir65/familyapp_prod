<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/register.html');
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';

// Basic validation
if ($full_name === '' || $email === '' || $password === '' || $confirm === '') {
    header('Location: /auth/register.html?error=missing&full_name=' . urlencode($full_name) . '&email=' . urlencode($email));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /auth/register.html?error=invalid_email&full_name=' . urlencode($full_name));
    exit;
}
if ($password !== $confirm) {
    header('Location: /auth/register.html?error=mismatch&full_name=' . urlencode($full_name) . '&email=' . urlencode($email));
    exit;
}
if (strlen($password) < 8) {
    header('Location: /auth/register.html?error=weak&full_name=' . urlencode($full_name) . '&email=' . urlencode($email));
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    // Check uniqueness
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
    header('Location: /auth/register.html?error=exists&full_name=' . urlencode($full_name));
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

    // Redirect to chat wizard start (replacing classic UI)
    header('Location: /pages/en/chat_wizard.html?step=1');
    exit;
} catch (Throwable $e) {
    header('Location: /auth/register.html?error=server');
    exit;
}
