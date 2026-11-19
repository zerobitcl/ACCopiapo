<?php
declare(strict_types=1);

/**
 * Endpoint de leads — SMTP Hostinger (SPF/DKIM alineados).
 * Guarda copia local en data/leads.jsonl por si Outlook filtra o falla el relay.
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

function lead_log(array $row): void
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return;
    }
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    @file_put_contents($dir . '/leads.jsonl', $line . "\n", FILE_APPEND | LOCK_EX);
}

$nombre = campo('Nombre', 120);
if ($nombre === '') {
    $nombre = campo('name', 120);
}
$telefono = campo('Teléfono', 40);
$servicio = campo('Servicio', 120);
$comuna   = campo('Comuna', 80);
$zona     = campo('Zona', 80);
$anillo   = campo('Anillo', 40);
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
$outlookCopy = 'eduardorojas@outlook.cl';

$subject = 'Cotización web: ' . $nombre . ' — ' . $ubicacion;

$body = implode("\n", [
    'Hola Eduardo,',
    '',
    'Llegó una solicitud de cotización desde aireacondicionadocopiapo.cl.',
    '',
    'Nombre: ' . $nombre,
    'Teléfono: ' . $telefono,
    'Servicio: ' . $servicio,
    'Comuna / ciudad: ' . $ubicacion,
    'Anillo operativo: ' . ($anillo !== '' ? $anillo : 'No indicado'),
    'Detalle: ' . ($mensaje !== '' ? $mensaje : 'Sin mensaje adicional'),
    '',
    'Puedes llamar o escribir por WhatsApp al número indicado.',
    '',
    '— Formulario Aire Copiapó',
    'https://www.aireacondicionadocopiapo.cl/',
]);

$lead = [
    'ts' => gmdate('c'),
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'nombre' => $nombre,
    'telefono' => $telefono,
    'servicio' => $servicio,
    'comuna' => $ubicacion,
    'anillo' => $anillo,
    'mensaje' => $mensaje,
];
lead_log($lead);

// Primario: bandeja del dominio (Hostinger). Secundario: Outlook en envío aparte
// para que un rechazo/filtro de Microsoft no tumbe el lead completo.
$okPrimary = smtp_send($cfg, [$fromMailbox], $subject, $body, 'Aire Copiapó');
$okOutlook = false;
if ($okPrimary && $outlookCopy !== '' && strcasecmp($outlookCopy, $fromMailbox) !== 0) {
    $okOutlook = smtp_send($cfg, [$outlookCopy], $subject, $body, 'Aire Copiapó');
}

lead_log([
    'ts' => gmdate('c'),
    'event' => 'mail_result',
    'nombre' => $nombre,
    'telefono' => $telefono,
    'ok_primary' => $okPrimary,
    'ok_outlook' => $okOutlook,
    'primary' => $fromMailbox,
    'outlook' => $outlookCopy,
]);

if (!$okPrimary) {
    header('Location: /?error=envio#lead-form', true, 303);
    exit;
}

header('Location: /gracias.html', true, 303);
exit;
