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
        'marginTop' => 0.5,
        'marginBottom' => 0.5,
        'marginLeft' => 0.5,
        'marginRight' => 0.5,
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

    $companyName = sa_h((string) ($company['name'] ?? 'Refrigeración San Agustín'));
    $tagline = sa_h((string) ($company['tagline'] ?? 'Tu equipo en buenas manos'));
    $footer = sa_h((string) ($company['footer'] ?? '¡Tu refrigeración, nuestra prioridad!'));
    $phone = sa_h((string) ($company['phone'] ?? ''));
    $email = sa_h((string) ($company['email'] ?? ''));
    $rutCo = sa_h((string) ($company['rut'] ?? ''));
    $address = sa_h((string) ($company['address'] ?? ''));

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td>' . sa_h((string) ($item['descripcion'] ?? '')) . '</td>'
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

    return '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
      *{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#10243f;font-size:12px}
      .wrap{padding:18px 22px}
      .head{display:flex;justify-content:space-between;gap:16px;border-bottom:3px solid #0b3d91;padding-bottom:14px;margin-bottom:16px}
      .brand{display:flex;gap:12px;align-items:center}
      .mark{width:54px;height:54px;border-radius:50%;background:#0b3d91;display:flex;align-items:center;justify-content:center}
      .mark svg{display:block}
      h1{margin:0;font-size:18px;color:#0b3d91} .tag{margin:4px 0 0;color:#1e88e5;font-weight:700}
      .meta{text-align:right} .meta strong{display:block;font-size:14px;color:#0b3d91}
      .box{border:1px solid #d7e3f2;border-radius:10px;padding:12px;margin-bottom:14px;background:#f8fbff}
      .box h2{margin:0 0 8px;font-size:13px;color:#0b3d91}
      table{width:100%;border-collapse:collapse;margin:8px 0 14px}
      th,td{padding:8px;border-bottom:1px solid #d7e3f2;vertical-align:top}
      th{background:#0b3d91;color:#fff;text-align:left;font-size:11px}
      .num{text-align:right;white-space:nowrap}
      .totals{width:260px;margin-left:auto}
      .totals td{border:0;padding:5px 0}
      .totals .grand td{font-size:14px;font-weight:900;color:#0b3d91;border-top:2px solid #0b3d91;padding-top:8px}
      .conds{font-size:11px;color:#5b6f88;line-height:1.45}
      .foot{margin-top:22px;padding:12px;border-radius:10px;background:#0b3d91;color:#fff;text-align:center;font-weight:700}
      .yellow{display:inline-block;margin-top:6px;padding:4px 10px;border-radius:999px;background:#ffd23f;color:#10243f;font-size:11px}
    </style></head><body><div class="wrap">
      <div class="head">
        <div class="brand">
          <div class="mark"><svg width="28" height="28" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><path d="M32 8v12M32 44v12M8 32h12M44 32h12M16 16l8 8M40 40l8 8M48 16l-8 8M24 40l-8 8" stroke="#fff" stroke-width="4" stroke-linecap="round" fill="none"/><circle cx="32" cy="32" r="6" fill="#1e88e5"/></svg></div>
          <div>
            <div style="font-size:10px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;color:#5b6f88">Servicio técnico</div>
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
        <div style="margin-top:8px">' . nl2br(sa_h((string) $data['condiciones'])) . '</div>
      </div>

      <div class="foot">
        ' . $footer . '
        <div class="yellow">Frío que da confianza</div>
      </div>
    </div></body></html>';
}
