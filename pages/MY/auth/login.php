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
$inviteToken = trim($_POST['invite_token'] ?? '');

if ($email === '' || $password === '') {
    header('Location: /auth/login.html?error=missing');
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $emailLower = strtolower($email);
    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, pwa_admin FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$emailLower]);
    $user = $stmt->fetch();

    // If user not found but invite token is present, support first-time onboarding via invite
    if (!$user && $inviteToken !== '') {
        $hashTok = hash('sha256', $inviteToken);
        $sel = $pdo->prepare('SELECT * FROM invites WHERE token_hash = ? AND status = "pending" LIMIT 1');
        $sel->execute([$hashTok]);
        $inv = $sel->fetch(PDO::FETCH_ASSOC);
        // Validate invite matches provided email and is not expired
        $invEmailMatch = $inv && strcasecmp((string)$inv['email'], $email) === 0;
        $invNotExpired = $inv && (empty($inv['token_expires_at']) || (new DateTime() <= new DateTime($inv['token_expires_at'])));
        if ($inv && $invEmailMatch && $invNotExpired) {
            // Require a reasonable password to set for the brand-new user
            if (!is_string($password) || strlen($password) < 8) {
                header('Location: /auth/login.html?error=invite_pw');
                exit;
            }
            $familiesId = (int)$inv['families_id'];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $fullName = strstr($email, '@', true) ?: $email; // fallback to local-part
            try {
                $pdo->beginTransaction();
                // Create user and attach to family immediately
                $ins = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, is_active, email_verified, families_id, last_login, login_count) VALUES (?, ?, ?, 1, 0, ?, NOW(), 1)');
                $ins->execute([$fullName, $emailLower, $passwordHash, $familiesId]);
                $newUserId = (int)$pdo->lastInsertId();

                // Ensure family membership from invite permissions
                $upMem = $pdo->prepare('INSERT INTO family_members (family_id,user_id,role,can_edit,can_add,can_delete,can_manage_files,status,joined_at)
                    VALUES (?,?,?,?,?,?,?,"active",NOW())
                    ON DUPLICATE KEY UPDATE role=VALUES(role), can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete), can_manage_files=VALUES(can_manage_files), status="active"');
                $upMem->execute([$familiesId,$newUserId,$inv['role'],$inv['can_edit'],$inv['can_add'],$inv['can_delete'],$inv['can_manage_files']]);

                // Mark invite accepted
                $mark = $pdo->prepare('UPDATE invites SET status = "accepted", accepted_at = NOW(), accepted_by = ? WHERE id = ?');
                $mark->execute([$newUserId, (int)$inv['id']]);

                $pdo->commit();

                // Establish session and redirect
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_email'] = $emailLower;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['families_id'] = $familiesId;
                header('Location: /pages/my/dashboard.html');
                exit;
            } catch (Throwable $ce) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                header('Location: /auth/login.html?error=server');
                exit;
            }
        }
        // If invite invalid or email mismatch, fall through to standard invalid response
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: /auth/login.html?error=invalid');
        exit;
    }

    // Create session
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];

    // Update last_login and login_count
    $up = $pdo->prepare('UPDATE users SET last_login = NOW(), login_count = COALESCE(login_count,0) + 1 WHERE id = ?');
    $up->execute([(int)$user['id']]);

    // Admin short-circuit: send admins to Admin Dashboard
    if (!empty($user['pwa_admin'])) {
    header('Location: /pages/my/admin_dashboard.html');
        exit;
    }

    // If invite token provided, validate and attach user to invited family
    if ($inviteToken !== '') {
        $hashTok = hash('sha256', $inviteToken);
        // Load invite and validate email + expiry + status
        $sel = $pdo->prepare('SELECT * FROM invites WHERE token_hash = ? AND status = "pending" LIMIT 1');
        $sel->execute([$hashTok]);
        $inv = $sel->fetch(PDO::FETCH_ASSOC);
        if ($inv && strcasecmp((string)$inv['email'], (string)$user['email']) === 0) {
            if (empty($inv['token_expires_at']) || (new DateTime() <= new DateTime($inv['token_expires_at']))) {
                $familiesId = (int)$inv['families_id'];
                // Attach current user to the invited family and accept the invite
                $pdo->beginTransaction();
                try {
                    // Set current family on users table
                    $upFam = $pdo->prepare('UPDATE users SET families_id = ? WHERE id = ?');
                    $upFam->execute([$familiesId, (int)$user['id']]);

                    // Ensure membership in family_members
                    $upMem = $pdo->prepare('INSERT INTO family_members (family_id,user_id,role,can_edit,can_add,can_delete,can_manage_files,status,joined_at)
                        VALUES (?,?,?,?,?,?,?,"active",NOW())
                        ON DUPLICATE KEY UPDATE role=VALUES(role), can_edit=VALUES(can_edit), can_add=VALUES(can_add), can_delete=VALUES(can_delete), can_manage_files=VALUES(can_manage_files), status="active"');
                    $upMem->execute([$familiesId,(int)$user['id'],$inv['role'],$inv['can_edit'],$inv['can_add'],$inv['can_delete'],$inv['can_manage_files']]);

                    // Mark invite as accepted
                    $mark = $pdo->prepare('UPDATE invites SET status = "accepted", accepted_at = NOW(), accepted_by = ? WHERE id = ?');
                    $mark->execute([(int)$user['id'], (int)$inv['id']]);

                    $pdo->commit();
                    // Cache family in session and go to dashboard
                    $_SESSION['families_id'] = $familiesId;
                    header('Location: /pages/my/dashboard.html');
                    exit;
                } catch (Throwable $ie) {
                    if ($pdo->inTransaction()) { $pdo->rollBack(); }
                    // If something fails, fall through to normal flow
                }
            }
        }
        // If invalid/expired/mismatched, continue to normal wizard/dashboard routing
    }

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
