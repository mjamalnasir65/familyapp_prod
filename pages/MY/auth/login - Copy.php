<?php
// Simple login handler for newly registered users → redirects to wizard
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/login.html');
    exit;
}

// Basic input sanitation
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: /auth/login.html?error=missing');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: /auth/login.html?error=invalid');
        exit;
    }

    // Create session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];

    // Update last_login and login_count
    $up = $pdo->prepare('UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?');
    $up->execute([(int)$user['id']]);

    // Smart redirect based on wizard progress
    $status = getWizardStatus($pdo, (int)$user['id']);
    if (!empty($status['family_id'])) {
        // Standardize session key to 'families_id' to match DB naming
        $_SESSION['families_id'] = (int)$status['family_id'];
    }

    if (($status['redirect_to'] ?? 'wizard') === 'dashboard') {
    header('Location: /pages/my/dashboard.html');
        exit;
    }

    $next = (int)($status['next_step'] ?? 1);
    // Route to chat-based wizard (replacing classic UI). Preserve server-calculated next step.
    header('Location: /pages/my/chat_wizard.html?step=' . max(1, min(6, $next)));
    exit;
} catch (Throwable $e) {
    header('Location: /auth/login.html?error=server');
    exit;
}

// Helper to compute wizard status
function getWizardStatus(PDO $pdo, int $userId): array {
    // Default: no family → start at step 1
    $status = [
        'has_family' => false,
        'family_id' => null,
        'wizard_complete' => false,
        'next_step' => 1,
        'redirect_to' => 'wizard',
    ];

    // Fetch user's family id
    $st = $pdo->prepare('SELECT families_id FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row || empty($row['families_id'])) {
        return $status; // No family yet
    }

    $familyId = (int)$row['families_id'];
    $fs = $pdo->prepare('SELECT id, step_1_completed, step_2_completed, step_3_completed, step_4_completed, step_5_completed, step_6_completed FROM families WHERE id = ? LIMIT 1');
    $fs->execute([$familyId]);
    $fam = $fs->fetch();
    if (!$fam) {
        return $status; // Family missing; treat as no family
    }

    $status['has_family'] = true;
    $status['family_id'] = $familyId;

    $steps = [
        1 => $fam['step_1_completed'] ?? 'N',
        2 => $fam['step_2_completed'] ?? 'N',
        3 => $fam['step_3_completed'] ?? 'N',
        4 => $fam['step_4_completed'] ?? 'N',
        5 => $fam['step_5_completed'] ?? 'N',
        6 => $fam['step_6_completed'] ?? 'N',
    ];

    $next = 7; // assume complete
    for ($i=1; $i<=6; $i++) {
        if (($steps[$i] ?? 'N') !== 'Y') { $next = $i; break; }
    }

    if ($next === 7) {
        $status['wizard_complete'] = true;
        $status['redirect_to'] = 'dashboard';
        $status['next_step'] = 6;
    } else {
        $status['next_step'] = $next;
        $status['redirect_to'] = 'wizard';
    }

    return $status;
}
