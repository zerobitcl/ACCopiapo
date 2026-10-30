<?php
declare(strict_types=1);

/**
 * Endpoint de leads — SMTP Hostinger (SPF/DKIM alineados).
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /', true, 303);
    exit;
}

if (!empty($_POST['_honey'])) {
    header('Location: /gracias.html', true, 303);
    exit;
}

function campo(string $key, int $max = 500, bool $multiline = false): string
{
    $raw = $_POST[$key] ?? '';
    if (!is_string($raw)) {
        return '';
    }
    $clean = $multiline
        ? trim(str_replace(["\r\n", "\r"], "\n", $raw))
        : trim(preg_replace('/[\r\n]+/', ' ', $raw) ?? '');
    return function_exists('mb_substr')
        ? mb_substr($clean, 0, $max)
        : substr($clean, 0, $max);
}

$nombre = campo('Nombre', 120);
if ($nombre === '') {
    $nombre = campo('name', 120);
}
$telefono = campo('Teléfono', 40);
$servicio = campo('Servicio', 120);
$comuna   = campo('Comuna', 80);
$zona     = campo('Zona', 80);
$mensaje  = campo('Mensaje', 2000, true);

if ($nombre === '' || $telefono === '' || $servicio === '') {
    header('Location: /?error=campos#lead-form', true, 303);
    exit;
}

$configPath = __DIR__ . '/mail.config.php';
if (!is_file($configPath)) {
    header('Location: /?error=config#lead-form', true, 303);
    exit;
}

/** @var array{host?:string,port?:int,user?:string,pass?:string,from?:string} $cfg */
$cfg = require $configPath;
require_once __DIR__ . '/smtp-mail.php';

$ubicacion = $comuna !== '' ? $comuna : ($zona !== '' ? $zona : 'No indicada');
$fromMailbox = (string) ($cfg['from'] ?? $cfg['user'] ?? 'contacto@aireacondicionadocopiapo.cl');

// contacto@ = bandeja Hostinger (confiables); Outlook sigue recibiendo copia
$destinos = [
    $fromMailbox,
    'eduardorojas@outlook.cl',
];

$subject = 'Cotización web: ' . $nombre . ' — ' . $ubicacion;

$body = implode("\n", [
    'Hola Eduardo,',
    '',
    'Llegó una solicitud de cotización desde aireacondicionadocopiapo.cl.',
    '',
    'Nombre: ' . $nombre,
    'Teléfono: ' . $telefono,
    'Servicio: ' . $servicio,
    'Comuna / zona: ' . $ubicacion,
    'Detalle: ' . ($mensaje !== '' ? $mensaje : 'Sin mensaje adicional'),
    '',
    'Puedes llamar o escribir por WhatsApp al número indicado.',
    '',
    '— Formulario Aire Copiapó',
    'https://www.aireacondicionadocopiapo.cl/',
]);

$ok = smtp_send($cfg, $destinos, $subject, $body, 'Aire Copiapó');

if (!$ok) {
    header('Location: /?error=envio#lead-form', true, 303);
    exit;
}

header('Location: /gracias.html', true, 303);
exit;
