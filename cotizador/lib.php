<?php
declare(strict_types=1);

function sa_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/config.sample.php';
    }
    /** @var array $loaded */
    $loaded = require $path;
    $cfg = $loaded;
    return $cfg;
}

function sa_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('sa_cotizador');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/cotizador/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function sa_csrf_token(): string
{
    sa_boot_session();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function sa_csrf_ok(?string $token): bool
{
    sa_boot_session();
    $session = $_SESSION['csrf'] ?? '';
    return is_string($token) && is_string($session) && $session !== '' && hash_equals($session, $token);
}

function sa_is_authed(): bool
{
    sa_boot_session();
    $cfg = sa_config();
    $ttl = (int) ($cfg['session_ttl'] ?? 28800);
    if (empty($_SESSION['sa_authed']) || empty($_SESSION['sa_authed_at'])) {
        return false;
    }
    if ((time() - (int) $_SESSION['sa_authed_at']) > $ttl) {
        unset($_SESSION['sa_authed'], $_SESSION['sa_authed_at']);
        return false;
    }
    return true;
}

function sa_require_auth_json(): void
{
    if (sa_is_authed()) {
        return;
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Vuelve a ingresar el PIN.']);
    exit;
}

function sa_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sa_json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sa_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sa_data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function sa_quotes_path(): string
{
    return sa_data_dir() . '/quotes.json';
}

function sa_meta_path(): string
{
    return sa_data_dir() . '/meta.json';
}

/**
 * @template T
 * @param callable(array):T $mutator
 * @return T
 */
function sa_with_quotes_lock(callable $mutator)
{
    $path = sa_quotes_path();
    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        throw new RuntimeException('No se pudo abrir el bloqueo del historial.');
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('No se pudo bloquear el historial.');
    }
    try {
        $quotes = [];
        if (is_file($path)) {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw !== false ? $raw : '[]', true);
            if (is_array($decoded)) {
                $quotes = $decoded;
            }
        }
        $result = $mutator($quotes);
        if (is_array($result) && array_key_exists('__save', $result)) {
            $toSave = $result['__save'];
            unset($result['__save']);
            $json = json_encode($toSave, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new RuntimeException('No se pudo serializar el historial.');
            }
            file_put_contents($path, $json, LOCK_EX);
            return $result['__return'] ?? true;
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function sa_next_quote_number(): string
{
    $cfg = sa_config();
    $prefix = (string) ($cfg['quote_prefix'] ?? 'SA');
    $year = date('Y');
    $metaPath = sa_meta_path();
    $lockPath = $metaPath . '.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) {
        throw new RuntimeException('No se pudo abrir el correlativo.');
    }
    flock($lock, LOCK_EX);
    try {
        $meta = ['year' => $year, 'seq' => (int) ($cfg['next_number'] ?? 1) - 1];
        if (is_file($metaPath)) {
            $raw = file_get_contents($metaPath);
            $decoded = json_decode($raw !== false ? $raw : '{}', true);
            if (is_array($decoded)) {
                $meta = array_merge($meta, $decoded);
            }
        }
        if ((string) ($meta['year'] ?? '') !== $year) {
            $meta['year'] = $year;
            $meta['seq'] = 0;
        }
        $meta['seq'] = (int) $meta['seq'] + 1;
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT), LOCK_EX);
        return sprintf('%s-%s-%04d', $prefix, $year, (int) $meta['seq']);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function sa_clp(int $amount): string
{
    return '$' . number_format($amount, 0, ',', '.');
}
