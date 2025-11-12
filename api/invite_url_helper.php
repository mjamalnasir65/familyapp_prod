<?php
// Central helper for building invite URLs and generating tokens.
// Ensures consistent language fallback and scheme/host resolution.

function normalizeInviteLang(?string $explicitLang, ?string $sessionLang): string {
    $lang = strtoupper(trim((string)($explicitLang ?? '')));
    if ($lang === '') {
        $lang = strtoupper(trim((string)($sessionLang ?? '')));
    }
    if ($lang === '' || !in_array($lang, ['EN','MY'], true)) {
        $lang = 'EN';
    }
    return $lang;
}

function generateInviteToken(): array {
    try {
        $raw = random_bytes(16); // 128-bit entropy
    } catch (Throwable $e) {
        throw new RuntimeException('token_generation_failed');
    }
    $token = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    $hash  = hash('sha256', $token);
    return [$token, $hash];
}

function buildInviteUrl(string $token, string $lang): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme.'://'.$host.'/pages/'.$lang.'/accept_invite.html?token='.$token;
}

?>