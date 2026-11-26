<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

sa_boot_session();
sa_require_auth_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sa_json_out(['ok' => false, 'error' => 'Método no permitido.'], 405);
}

$body = sa_json_body();
$token = (string) ($body['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!sa_csrf_ok($token)) {
    sa_json_out(['ok' => false, 'error' => 'Token CSRF inválido.'], 403);
}

$quoteIn = $body['quote'] ?? null;
if (!is_array($quoteIn)) {
    sa_json_out(['ok' => false, 'error' => 'Cotización inválida.'], 400);
}

$clienteIn = is_array($quoteIn['cliente'] ?? null) ? $quoteIn['cliente'] : [];
$itemsIn = is_array($quoteIn['items'] ?? null) ? $quoteIn['items'] : [];
if (count($itemsIn) > 50) {
    sa_json_out(['ok' => false, 'error' => 'Máximo 50 ítems por cotización.'], 400);
}

$cliente = [
    'nombre' => trim((string) ($clienteIn['nombre'] ?? '')),
    'rut' => trim((string) ($clienteIn['rut'] ?? '')),
    'telefono' => trim((string) ($clienteIn['telefono'] ?? '')),
    'direccion' => trim((string) ($clienteIn['direccion'] ?? '')),
    'email' => trim((string) ($clienteIn['email'] ?? '')),
];

if ($cliente['nombre'] === '' || $cliente['rut'] === '' || $cliente['telefono'] === '') {
    sa_json_out(['ok' => false, 'error' => 'Faltan datos del cliente.'], 400);
}

$items = [];
$neto = 0;
foreach ($itemsIn as $row) {
    if (!is_array($row)) {
        continue;
    }
    $descripcion = trim((string) ($row['descripcion'] ?? ''));
    $cantidad = (float) ($row['cantidad'] ?? 0);
    $precio = (int) round((float) ($row['precio'] ?? 0));
    if ($descripcion === '' || $cantidad <= 0 || $precio < 0) {
        continue;
    }
    $subtotal = (int) round($cantidad * $precio);
    $neto += $subtotal;
    $items[] = [
        'descripcion' => $descripcion,
        'cantidad' => $cantidad,
        'precio' => $precio,
        'subtotal' => $subtotal,
    ];
}

if (!$items) {
    sa_json_out(['ok' => false, 'error' => 'Debes incluir al menos un ítem válido.'], 400);
}

$aplicaIva = !empty($quoteIn['aplica_iva']);
$iva = $aplicaIva ? (int) round($neto * 0.19) : 0;
$total = $neto + $iva;

$validez = max(1, min(90, (int) ($quoteIn['validez_dias'] ?? 15)));
$garantia = trim((string) ($quoteIn['garantia'] ?? '90 días en mano de obra'));
$formaPago = trim((string) ($quoteIn['forma_pago'] ?? ''));
$condiciones = trim((string) ($quoteIn['condiciones'] ?? ''));

$cfg = sa_config();
$company = is_array($cfg['company'] ?? null) ? $cfg['company'] : [];
$apiKey = trim((string) ($cfg['api2pdf_key'] ?? ''));
if ($apiKey === '' || $apiKey === 'TU_API2PDF_KEY_AQUI') {
    sa_json_out([
        'ok' => false,
        'error' => 'Configura api2pdf_key en cotizador/config.php antes de generar PDFs.',
    ], 500);
}

try {
    $number = sa_next_quote_number();
} catch (Throwable $e) {
    sa_json_out(['ok' => false, 'error' => 'No se pudo asignar correlativo.'], 500);
}

$html = sa_build_quote_html([
    'number' => $number,
    'date' => date('d/m/Y'),
    'cliente' => $cliente,
    'items' => $items,
    'aplica_iva' => $aplicaIva,
    'neto' => $neto,
    'iva' => $iva,
    'total' => $total,
    'validez_dias' => $validez,
    'garantia' => $garantia,
    'forma_pago' => $formaPago,
    'condiciones' => $condiciones,
    'company' => $company,
]);

$payload = [
    'html' => $html,
    'fileName' => $number . '.pdf',
    'options' => [
        // Márgenes compactos: evita empujar el pie a una 2.ª página casi vacía
        'marginTop' => 0.35,
        'marginBottom' => 0.35,
        'marginLeft' => 0.4,
        'marginRight' => 0.4,
        'printBackground' => true,
        'paperFormat' => 'A4',
    ],
];

$ch = curl_init('https://v2.api2pdf.com/chrome/pdf/html');
if ($ch === false) {
    sa_json_out(['ok' => false, 'error' => 'No se pudo iniciar cURL.'], 500);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0 || !is_string($response)) {
    sa_json_out(['ok' => false, 'error' => 'Error de red al contactar Api2Pdf.'], 502);
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    sa_json_out(['ok' => false, 'error' => 'Respuesta inválida de Api2Pdf.'], 502);
}

$success = !empty($decoded['Success']) || !empty($decoded['success']);
$fileUrl = (string) ($decoded['FileUrl'] ?? $decoded['fileUrl'] ?? $decoded['pdf'] ?? '');
if (!$success || $fileUrl === '') {
    $msg = (string) ($decoded['Error'] ?? $decoded['error'] ?? 'Api2Pdf no devolvió un PDF.');
    sa_json_out(['ok' => false, 'error' => $msg, 'http' => $status], 502);
}

$record = [
    'id' => bin2hex(random_bytes(8)),
    'number' => $number,
    'created_at' => date('c'),
    'cliente' => $cliente,
    'items' => $items,
    'aplica_iva' => $aplicaIva,
    'neto' => $neto,
    'iva' => $iva,
    'total' => $total,
    'validez_dias' => $validez,
    'garantia' => $garantia,
    'forma_pago' => $formaPago,
    'condiciones' => $condiciones,
    'pdf_url' => $fileUrl,
];

try {
    sa_with_quotes_lock(static function (array $quotes) use ($record) {
        array_unshift($quotes, $record);
        if (count($quotes) > 300) {
            $quotes = array_slice($quotes, 0, 300);
        }
        return ['__save' => $quotes, '__return' => true];
    });
} catch (Throwable $e) {
    // PDF ya generado: no fallar la respuesta por historial
}

sa_json_out([
    'ok' => true,
    'csrf' => sa_csrf_token(),
    'quote' => $record,
    'pdf_url' => $fileUrl,
]);

/**
 * @param array<string,mixed> $data
 */
function sa_build_quote_html(array $data): string
{
    $company = is_array($data['company'] ?? null) ? $data['company'] : [];
    $cliente = is_array($data['cliente'] ?? null) ? $data['cliente'] : [];
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];

    $companyName = sa_h((string) ($company['name'] ?? 'Servicio Técnico San Agustín'));
    $tagline = sa_h((string) ($company['tagline'] ?? 'Climatización y refrigeración'));
    $footer = sa_h((string) ($company['footer'] ?? 'Técnico oficial de Aire Copiapó · Climatización y refrigeración'));
    $phone = sa_h((string) ($company['phone'] ?? ''));
    $email = sa_h((string) ($company['email'] ?? ''));
    $rutCo = sa_h((string) ($company['rut'] ?? ''));
    $address = sa_h((string) ($company['address'] ?? ''));

    $logoSrc = '';
    $logoPath = dirname(__DIR__) . '/img/logo-san-agustin.jpg';
    if (is_readable($logoPath)) {
        $logoSrc = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td class="desc">' . sa_format_item_desc((string) ($item['descripcion'] ?? '')) . '</td>'
            . '<td class="num">' . sa_h((string) ($item['cantidad'] ?? '')) . '</td>'
            . '<td class="num">' . sa_h(sa_clp((int) ($item['precio'] ?? 0))) . '</td>'
            . '<td class="num">' . sa_h(sa_clp((int) ($item['subtotal'] ?? 0))) . '</td>'
            . '</tr>';
    }

    $ivaRow = !empty($data['aplica_iva'])
        ? '<tr><td>IVA 19%</td><td class="num">' . sa_h(sa_clp((int) $data['iva'])) . '</td></tr>'
        : '<tr><td>IVA</td><td class="num">No aplica</td></tr>';

    $extraContact = '';
    if ($rutCo !== '') {
        $extraContact .= '<div>RUT: ' . $rutCo . '</div>';
    }
    if ($email !== '') {
        $extraContact .= '<div>' . $email . '</div>';
    }
    if ($address !== '') {
        $extraContact .= '<div>' . $address . '</div>';
    }

    $brandMark = $logoSrc !== ''
        ? '<img class="logo" src="' . $logoSrc . '" width="72" height="72" alt="">'
        : '<div class="mark-fallback"></div>';

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
      *{box-sizing:border-box}
      html,body{margin:0;padding:0;height:auto!important;min-height:0!important}
      body{font-family:Arial,Helvetica,sans-serif;color:#10243f;font-size:11.5px;line-height:1.35}
      .wrap{padding:10px 14px 8px}
      .head{display:flex;justify-content:space-between;gap:12px;border-bottom:3px solid #0b3d91;padding-bottom:10px;margin-bottom:10px}
      .brand{display:flex;gap:10px;align-items:center}
      .logo{width:72px;height:72px;object-fit:contain;border-radius:8px;background:#fff}
      .mark-fallback{width:48px;height:48px;border-radius:50%;background:#0b3d91}
      h1{margin:0;font-size:16px;color:#0b3d91} .tag{margin:2px 0 0;color:#1e88e5;font-weight:700;font-size:11px}
      .meta{text-align:right;font-size:11px} .meta strong{display:block;font-size:13px;color:#0b3d91}
      .box{border:1px solid #d7e3f2;border-radius:8px;padding:10px;margin-bottom:10px;background:#f8fbff}
      .box h2{margin:0 0 6px;font-size:12px;color:#0b3d91}
      table{width:100%;border-collapse:collapse;margin:4px 0 10px}
      thead{display:table-header-group}
      tr{page-break-inside:auto;break-inside:auto}
      th,td{padding:7px 8px;border-bottom:1px solid #d7e3f2;vertical-align:top}
      th{background:#0b3d91;color:#fff;text-align:left;font-size:10.5px}
      .desc{word-wrap:break-word;overflow-wrap:anywhere;line-height:1.4;font-size:10.5px}
      .desc .bullet{display:block;padding-left:12px;text-indent:-12px;margin:1px 0 1px 4px}
      .num{text-align:right;white-space:nowrap;width:1%;font-size:11px}
      .totals{width:240px;margin-left:auto;margin-bottom:8px}
      .totals td{border:0;padding:4px 0}
      .totals .grand td{font-size:13px;font-weight:900;color:#0b3d91;border-top:2px solid #0b3d91;padding-top:6px}
      .conds{font-size:10.5px;color:#5b6f88;line-height:1.4;page-break-inside:avoid;break-inside:avoid}
      .closing{page-break-inside:avoid;break-inside:avoid}
      .foot{margin-top:10px;padding:10px;border-radius:8px;background:#0b3d91;color:#fff;text-align:center;font-weight:700;font-size:11px}
      .yellow{display:inline-block;margin-top:5px;padding:3px 9px;border-radius:999px;background:#ffd23f;color:#10243f;font-size:10px}
    </style></head><body><div class="wrap">
      <div class="head">
        <div class="brand">
          ' . $brandMark . '
          <div>
            <div style="font-size:9px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;color:#5b6f88">Técnico oficial · Aire Copiapó</div>
            <h1>' . $companyName . '</h1>
            <p class="tag">' . $tagline . '</p>
          </div>
        </div>
        <div class="meta">
          <strong>' . sa_h((string) $data['number']) . '</strong>
          <div>Fecha: ' . sa_h((string) $data['date']) . '</div>
          <div>Tel: ' . $phone . '</div>' . $extraContact . '
        </div>
      </div>

      <div class="box">
        <h2>Cliente</h2>
        <div><strong>' . sa_h((string) ($cliente['nombre'] ?? '')) . '</strong></div>
        <div>RUT: ' . sa_h((string) ($cliente['rut'] ?? '')) . '</div>
        <div>Teléfono: ' . sa_h((string) ($cliente['telefono'] ?? '')) . '</div>
        <div>Dirección: ' . sa_h((string) ($cliente['direccion'] ?? '—')) . '</div>
        <div>Email: ' . sa_h((string) (($cliente['email'] ?? '') !== '' ? $cliente['email'] : '—')) . '</div>
      </div>

      <table>
        <thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">P. unit. neto</th><th class="num">Subtotal</th></tr></thead>
        <tbody>' . $rows . '</tbody>
      </table>

      <div class="closing">
        <table class="totals">
          <tr><td>Neto</td><td class="num">' . sa_h(sa_clp((int) $data['neto'])) . '</td></tr>
          ' . $ivaRow . '
          <tr class="grand"><td>Total</td><td class="num">' . sa_h(sa_clp((int) $data['total'])) . '</td></tr>
        </table>

        <div class="box conds">
          <h2>Condiciones</h2>
          <div>Validez: ' . sa_h((string) $data['validez_dias']) . ' días</div>
          <div>Garantía: ' . sa_h((string) $data['garantia']) . '</div>
          <div>Forma de pago: ' . sa_h((string) $data['forma_pago']) . '</div>
          <div style="margin-top:6px">' . nl2br(sa_h((string) $data['condiciones'])) . '</div>
        </div>

        <div class="foot">
          ' . $footer . '
          <div class="yellow">Técnico oficial de Aire Copiapó</div>
        </div>
      </div>
    </div></body></html>';
}

/**
 * Preserva saltos de línea y viñetas en la descripción del ítem del PDF.
 */
function sa_format_item_desc(string $raw): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($raw));
    if ($text === '') {
        return '';
    }

    $lines = explode("\n", $text);
    $out = [];
    foreach ($lines as $line) {
        $trimmed = rtrim($line);
        if ($trimmed === '') {
            $out[] = '<br>';
            continue;
        }
        $escaped = sa_h($trimmed);
        // Viñetas • - * o guion al inicio → indentación legible
        if (preg_match('/^([•\-\*]|\d+[\.\)])\s+/u', $trimmed)) {
            $out[] = '<span class="bullet">' . $escaped . '</span>';
        } else {
            $out[] = $escaped . '<br>';
        }
    }

    $html = implode('', $out);
    return preg_replace('/(?:<br>)+$/', '', $html) ?? $html;
}
