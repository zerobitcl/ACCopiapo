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

/**
 * Solo rutas internas relativas para no abrir open-redirect.
 */
function safe_origin_path(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || $raw[0] !== '/' || strpos($raw, '//') === 0) {
        return '/';
    }
    if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $raw)) {
        return '/';
    }
    $path = parse_url($raw, PHP_URL_PATH);
    if (!is_string($path) || $path === '' || $path[0] !== '/') {
        return '/';
    }
    if (strpos($path, '..') !== false) {
        return '/';
    }
    $allowed = [
        '/',
        '/tierra-amarilla/',
        '/caldera/',
        '/bahia-inglesa/',
        '/calefaccion/',
        '/ducteria/',
        '/cobre-soldadura-plata/',
        '/gasfiteria-ppr/',
        '/mantencion-aire-acondicionado/',
        '/reparacion-carga-gas/',
        '/calculadora-btu/',
        '/guias/',
        '/guias/cuantos-btu-necesito/',
        '/guias/aire-acondicionado-polvo-desierto/',
        '/guias/inverter-vs-on-off-atacama/',
    ];
    if (!in_array($path, $allowed, true)) {
        return '/';
    }
    return $path;
}

function form_anchor(string $path): string
{
    return $path === '/' ? '#lead-form' : '#cotizar';
}

function redirect_error(string $code, string $originPath): void
{
    $anchor = form_anchor($originPath);
    $target = $originPath . '?error=' . rawurlencode($code) . $anchor;
    header('Location: ' . $target, true, 303);
    exit;
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
$tipoRecinto = campo('Tipo_recinto', 120);
$tipoProyecto = campo('Tipo_proyecto', 120);
$tipoTrabajo = campo('Tipo_trabajo', 120);
$necesidad = campo('Necesidad', 120);
$cantidadEquipos = campo('Cantidad_equipos', 80);
$sintoma = campo('Sintoma', 160);
$capacidadBtu = campo('Capacidad_BTU', 40);
$metros = campo('Metros_cuadrados', 40);
$paginaOrigen = safe_origin_path(campo('Pagina_origen', 200));

$detalleTecnico = $tipoRecinto !== '' ? $tipoRecinto
    : ($tipoProyecto !== '' ? $tipoProyecto
    : ($tipoTrabajo !== '' ? $tipoTrabajo
    : ($necesidad !== '' ? $necesidad
    : ($cantidadEquipos !== '' ? $cantidadEquipos
    : ($sintoma !== '' ? $sintoma : '')))));

if ($nombre === '' || $telefono === '' || $servicio === '') {
    redirect_error('campos', $paginaOrigen);
}

$configPath = __DIR__ . '/mail.config.php';
if (!is_file($configPath)) {
    redirect_error('config', $paginaOrigen);
}

/** @var array{host?:string,port?:int,user?:string,pass?:string,from?:string} $cfg */
$cfg = require $configPath;
require_once __DIR__ . '/smtp-mail.php';

$ubicacion = $comuna !== '' ? $comuna : ($zona !== '' ? $zona : 'No indicada');
$fromMailbox = (string) ($cfg['from'] ?? $cfg['user'] ?? 'contacto@aireacondicionadocopiapo.cl');
$outlookCopy = 'eduardorojas@outlook.cl';

$subject = 'Cotización web: ' . $nombre . ' — ' . $ubicacion;

$bodyLines = [
    'Hola Eduardo,',
    '',
    'Llegó una solicitud de cotización desde aireacondicionadocopiapo.cl.',
    '',
    'Nombre: ' . $nombre,
    'Teléfono: ' . $telefono,
    'Servicio: ' . $servicio,
    'Comuna / ciudad: ' . $ubicacion,
    'Anillo operativo: ' . ($anillo !== '' ? $anillo : 'No indicado'),
];

if ($detalleTecnico !== '') {
    $bodyLines[] = 'Detalle técnico: ' . $detalleTecnico;
}
if ($capacidadBtu !== '') {
    $bodyLines[] = 'Capacidad BTU: ' . $capacidadBtu;
}
if ($metros !== '') {
    $bodyLines[] = 'Metros cuadrados: ' . $metros;
}

$bodyLines[] = 'Página origen: ' . $paginaOrigen;
$bodyLines[] = 'Detalle: ' . ($mensaje !== '' ? $mensaje : 'Sin mensaje adicional');
$bodyLines[] = '';
$bodyLines[] = 'Puedes llamar o escribir por WhatsApp al número indicado.';
$bodyLines[] = '';
$bodyLines[] = '— Formulario Aire Copiapó';
$bodyLines[] = 'https://www.aireacondicionadocopiapo.cl' . $paginaOrigen;

$body = implode("\n", $bodyLines);

$lead = [
    'ts' => gmdate('c'),
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'nombre' => $nombre,
    'telefono' => $telefono,
    'servicio' => $servicio,
    'comuna' => $ubicacion,
    'anillo' => $anillo,
    'detalle_tecnico' => $detalleTecnico,
    'capacidad_btu' => $capacidadBtu,
    'metros' => $metros,
    'pagina_origen' => $paginaOrigen,
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
    redirect_error('envio', $paginaOrigen);
}

$thanksQs = http_build_query([
    'servicio' => $servicio,
    'ubicacion' => $ubicacion,
], '', '&', PHP_QUERY_RFC3986);

header('Location: /gracias.html?' . $thanksQs, true, 303);
exit;
