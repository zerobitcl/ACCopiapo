<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
sa_boot_session();

if (sa_is_authed()) {
    header('Location: app.php', true, 303);
    exit;
}

$cfg = sa_config();
$company = $cfg['company'] ?? [];
$name = (string) ($company['name'] ?? 'Refrigeración San Agustín');
$tagline = (string) ($company['tagline'] ?? 'Tu equipo en buenas manos');
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="theme-color" content="#0b3d91">
  <title>Acceso · Cotizador <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="cotizador.css">
</head>
<body class="login-body">
  <main class="login-card" aria-labelledby="login-title">
    <div class="brand-lockup" aria-hidden="true">
      <svg class="brand-mark" viewBox="0 0 64 64" width="56" height="56">
        <circle cx="32" cy="32" r="30" fill="#0b3d91"/>
        <path d="M32 10v8M32 46v8M10 32h8M46 32h8M16 16l6 6M42 42l6 6M48 16l-6 6M22 42l-6 6" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
        <circle cx="32" cy="32" r="7" fill="#1e88e5"/>
      </svg>
    </div>
    <p class="eyebrow">Servicio técnico</p>
    <h1 id="login-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="login-lead"><?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></p>

    <form id="pin-form" class="pin-form" autocomplete="off">
      <label for="pin">
        <span>Clave de acceso</span>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="8" placeholder="••••" required autofocus>
      </label>
      <p class="form-error" id="pin-error" hidden role="alert"></p>
      <button class="btn btn--primary" type="submit">Entrar al cotizador</button>
    </form>
    <p class="login-foot">Uso interno. No indexado por buscadores.</p>
  </main>
  <script>
  (() => {
    const form = document.getElementById("pin-form");
    const error = document.getElementById("pin-error");
    const input = document.getElementById("pin");
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      error.hidden = true;
      const btn = form.querySelector("button");
      btn.disabled = true;
      try {
        const res = await fetch("auth.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ action: "login", pin: input.value })
        });
        const data = await res.json();
        if (!data.ok) {
          error.textContent = data.error || "No se pudo ingresar.";
          error.hidden = false;
          input.select();
          return;
        }
        window.location.href = data.redirect || "app.php";
      } catch (err) {
        error.textContent = "Error de conexión. Intenta de nuevo.";
        error.hidden = false;
      } finally {
        btn.disabled = false;
      }
    });
  })();
  </script>
</body>
</html>
