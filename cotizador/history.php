<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

sa_boot_session();
sa_require_auth_json();

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = sa_json_body();
    $action = (string) ($body['action'] ?? $action);
    $token = (string) ($body['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!sa_csrf_ok($token)) {
        sa_json_out(['ok' => false, 'error' => 'Token CSRF inválido.'], 403);
    }
} else {
    $body = [];
}

if ($action === 'list' || $action === '') {
    try {
        $quotes = sa_with_quotes_lock(static function (array $quotes) {
            return $quotes;
        });
    } catch (Throwable $e) {
        $quotes = [];
    }
    sa_json_out([
        'ok' => true,
        'csrf' => sa_csrf_token(),
        'quotes' => is_array($quotes) ? $quotes : [],
    ]);
}

if ($action === 'delete') {
    $id = (string) ($body['id'] ?? '');
    if ($id === '') {
        sa_json_out(['ok' => false, 'error' => 'ID requerido.'], 400);
    }
    try {
        sa_with_quotes_lock(static function (array $quotes) use ($id) {
            $filtered = array_values(array_filter(
                $quotes,
                static fn($row) => !is_array($row) || (($row['id'] ?? '') !== $id)
            ));
            return ['__save' => $filtered, '__return' => true];
        });
    } catch (Throwable $e) {
        sa_json_out(['ok' => false, 'error' => 'No se pudo eliminar.'], 500);
    }
    sa_json_out(['ok' => true, 'csrf' => sa_csrf_token()]);
}

if ($action === 'get') {
    $id = (string) ($_GET['id'] ?? $body['id'] ?? '');
    try {
        $found = sa_with_quotes_lock(static function (array $quotes) use ($id) {
            foreach ($quotes as $row) {
                if (is_array($row) && ($row['id'] ?? '') === $id) {
                    return $row;
                }
            }
            return null;
        });
    } catch (Throwable $e) {
        $found = null;
    }
    if (!$found) {
        sa_json_out(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
    }
    sa_json_out(['ok' => true, 'csrf' => sa_csrf_token(), 'quote' => $found]);
}

sa_json_out(['ok' => false, 'error' => 'Acción no válida.'], 400);
