<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
sa_boot_session();

if (!sa_is_authed()) {
    header('Location: index.php', true, 303);
    exit;
}

$cfg = sa_config();
$company = $cfg['company'] ?? [];
$name = (string) ($company['name'] ?? 'Refrigeración San Agustín');
$phone = (string) ($company['phone'] ?? '+56 9 2365 0596');
$whatsapp = (string) ($company['whatsapp'] ?? '56923650596');
$csrf = sa_csrf_token();
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="theme-color" content="#0b3d91">
  <title>Cotizador · <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="cotizador.css">
  <script>
    window.SA_CONFIG = {
      csrf: <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>,
      companyName: <?= json_encode($name, JSON_UNESCAPED_UNICODE) ?>,
      phone: <?= json_encode($phone, JSON_UNESCAPED_UNICODE) ?>,
      whatsapp: <?= json_encode($whatsapp, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="cotizador.js" defer></script>
</head>
<body class="app-body">
  <header class="app-header">
    <div class="app-header__brand">
      <svg class="brand-mark brand-mark--sm" viewBox="0 0 64 64" width="36" height="36" aria-hidden="true">
        <circle cx="32" cy="32" r="30" fill="#0b3d91"/>
        <path d="M32 10v8M32 46v8M10 32h8M46 32h8M16 16l6 6M42 42l6 6M48 16l-6 6M22 42l-6 6" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
        <circle cx="32" cy="32" r="7" fill="#1e88e5"/>
      </svg>
      <div>
        <p class="app-header__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="app-header__sub">Cotizador interno</p>
      </div>
    </div>
    <button class="btn-text" type="button" id="btn-logout">Salir</button>
  </header>

  <nav class="tabs" aria-label="Secciones">
    <button class="tabs__btn is-active" type="button" data-tab="quote">Nueva</button>
    <button class="tabs__btn" type="button" data-tab="history">Historial</button>
  </nav>

  <main class="app-main">
    <section id="tab-quote" class="tab-panel is-active" aria-label="Nueva cotización">
      <form id="quote-form" novalidate>
        <details class="panel" open>
          <summary>1. Cliente</summary>
          <div class="panel__body">
            <label>
              <span>Nombre / razón social</span>
              <input type="text" name="cliente_nombre" id="cliente_nombre" autocomplete="name" required placeholder="Cliente">
            </label>
            <div class="grid-2">
              <label>
                <span>RUT</span>
                <input type="text" name="cliente_rut" id="cliente_rut" inputmode="text" placeholder="12.345.678-9" required>
              </label>
              <label>
                <span>Teléfono</span>
                <input type="tel" name="cliente_telefono" id="cliente_telefono" inputmode="tel" autocomplete="tel" placeholder="+56 9 ..." required>
              </label>
            </div>
            <label>
              <span>Dirección</span>
              <input type="text" name="cliente_direccion" id="cliente_direccion" autocomplete="street-address" placeholder="Calle, comuna">
            </label>
            <label>
              <span>Correo <small>(opcional)</small></span>
              <input type="email" name="cliente_email" id="cliente_email" autocomplete="email" placeholder="correo@ejemplo.cl">
            </label>
            <p class="field-hint" id="rut-hint" hidden></p>
          </div>
        </details>

        <details class="panel" open>
          <summary>2. Ítems</summary>
          <div class="panel__body">
            <div id="items-list" class="items-list" aria-live="polite"></div>
            <button class="btn btn--ghost" type="button" id="btn-add-item">+ Agregar ítem</button>
          </div>
        </details>

        <details class="panel" open>
          <summary>3. Totales</summary>
          <div class="panel__body">
            <label class="check-row">
              <input type="checkbox" id="aplica_iva" checked>
              <span>Aplicar IVA 19%</span>
            </label>
            <dl class="totals" id="totals-box">
              <div><dt>Neto</dt><dd id="total-neto">$0</dd></div>
              <div><dt>IVA 19%</dt><dd id="total-iva">$0</dd></div>
              <div class="totals__grand"><dt>Total</dt><dd id="total-final">$0</dd></div>
            </dl>
          </div>
        </details>

        <details class="panel">
          <summary>4. Condiciones</summary>
          <div class="panel__body">
            <div class="grid-2">
              <label>
                <span>Validez (días)</span>
                <input type="number" id="validez_dias" min="1" max="90" value="15">
              </label>
              <label>
                <span>Garantía</span>
                <input type="text" id="garantia" value="90 días en mano de obra">
              </label>
            </div>
            <label>
              <span>Forma de pago</span>
              <input type="text" id="forma_pago" value="Transferencia o efectivo. 50% al confirmar, saldo al entregar.">
            </label>
            <label>
              <span>Notas / condiciones</span>
              <textarea id="condiciones" rows="3">Cotización sujeta a inspección del equipo. Repuestos no incluidos salvo que se indiquen en los ítems. Precios en pesos chilenos.</textarea>
            </label>
          </div>
        </details>
      </form>
    </section>

    <section id="tab-history" class="tab-panel" aria-label="Historial" hidden>
      <label class="search-box">
        <span class="visually-hidden">Buscar cotización</span>
        <input type="search" id="history-search" placeholder="Buscar por cliente o número SA-…">
      </label>
      <div id="history-list" class="history-list"></div>
      <p class="empty-state" id="history-empty" hidden>Aún no hay cotizaciones guardadas.</p>
    </section>
  </main>

  <div class="result-sheet" id="result-sheet" hidden>
    <div class="result-sheet__card">
      <p class="eyebrow">PDF listo</p>
      <h2 id="result-number">Cotización</h2>
      <p id="result-msg">El archivo se generó correctamente.</p>
      <div class="form-actions">
        <a class="btn btn--primary" id="result-download" href="#" target="_blank" rel="noopener">Descargar PDF</a>
        <a class="btn btn--wa" id="result-whatsapp" href="#" target="_blank" rel="noopener">WhatsApp</a>
        <button class="btn btn--ghost" type="button" id="result-new">Nueva cotización</button>
        <button class="btn-text" type="button" id="result-close">Cerrar</button>
      </div>
    </div>
  </div>

  <footer class="sticky-bar" id="sticky-bar">
    <div class="sticky-bar__totals">
      <span>Total</span>
      <strong id="sticky-total">$0</strong>
    </div>
    <button class="btn btn--primary" type="button" id="btn-generate">Generar PDF</button>
  </footer>
</body>
</html>
