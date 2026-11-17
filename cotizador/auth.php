<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

sa_boot_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = sa_json_body();
    $action = (string) ($body['action'] ?? $action);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    sa_json_out(['ok' => true]);
}

if ($action === 'status') {
    sa_json_out([
        'ok' => true,
        'authed' => sa_is_authed(),
        'csrf' => sa_is_authed() ? sa_csrf_token() : null,
    ]);
}

if ($action !== 'login' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sa_json_out(['ok' => false, 'error' => 'Acción no válida.'], 400);
}

$cfg = sa_config();
$maxAttempts = (int) ($cfg['max_pin_attempts'] ?? 5);
$lockSeconds = (int) ($cfg['pin_lock_seconds'] ?? 600);

$lockedUntil = (int) ($_SESSION['pin_locked_until'] ?? 0);
if ($lockedUntil > time()) {
    $wait = $lockedUntil - time();
    sa_json_out([
        'ok' => false,
        'error' => 'Demasiados intentos. Espera ' . $wait . ' segundos.',
        'locked_seconds' => $wait,
    ], 429);
}

$pin = (string) ($body['pin'] ?? '');
$pin = preg_replace('/\s+/', '', $pin) ?? '';
$hash = (string) ($cfg['pin_hash'] ?? '');

$ok = $hash !== '' && password_verify($pin, $hash);
if (!$ok) {
    $attempts = (int) ($_SESSION['pin_attempts'] ?? 0) + 1;
    $_SESSION['pin_attempts'] = $attempts;
    if ($attempts >= $maxAttempts) {
        $_SESSION['pin_locked_until'] = time() + $lockSeconds;
        $_SESSION['pin_attempts'] = 0;
        sa_json_out([
            'ok' => false,
            'error' => 'PIN bloqueado por ' . (int) ($lockSeconds / 60) . ' minutos.',
            'locked_seconds' => $lockSeconds,
        ], 429);
    }
    $left = $maxAttempts - $attempts;
    sa_json_out([
        'ok' => false,
        'error' => 'PIN incorrecto. Te quedan ' . $left . ' intentos.',
        'attempts_left' => $left,
    ], 401);
}

$_SESSION['pin_attempts'] = 0;
unset($_SESSION['pin_locked_until']);
session_regenerate_id(true);
$_SESSION['sa_authed'] = true;
$_SESSION['sa_authed_at'] = time();
$token = sa_csrf_token();

sa_json_out([
    'ok' => true,
    'csrf' => $token,
    'redirect' => 'app.php',
]);
