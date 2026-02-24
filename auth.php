<?php
// ── Auth gate — Sinistro / Metis ─────────────────────────────
// Inclua este arquivo no topo de qualquer página protegida.
// Não gera saída se o usuário já estiver autenticado (return).

if (session_status() === PHP_SESSION_NONE) {
    session_name('metis_auth_sess');
    session_start();
}

require_once __DIR__ . '/api/config.php';

// Logout
if (isset($_GET['logout'])) {
    $_SESSION['metis_auth'] = false;
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Já autenticado → continua normalmente
if (!empty($_SESSION['metis_auth'])) {
    return;
}

// Verifica senha submetida
$authError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pwd'])) {
    if ($_POST['pwd'] === PANEL_PASSWORD) {
        $_SESSION['metis_auth'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $authError = true;
}

// Calcula prefixo para assets (img/) com base na profundidade do script chamador
$_authCallerDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
$_authImgBase   = ($_authCallerDir === __DIR__) ? '' : '../';
$_authLogo      = $_authImgBase . 'img/logoCHAT.png';
$_authName      = defined('AGENT_NAME') ? htmlspecialchars(AGENT_NAME) : 'Agente';

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acesso restrito — <?= $_authName ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
      background: #03091a;
      background-image:
        radial-gradient(ellipse 80% 60% at 20% 0%, rgba(37,99,235,.45) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 100%, rgba(6,182,212,.18) 0%, transparent 55%),
        radial-gradient(circle, rgba(255,255,255,.035) 1px, transparent 1px);
      background-size: auto, auto, 32px 32px;
    }
    .card {
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.12);
      backdrop-filter: blur(16px);
      border-radius: 20px;
      padding: 44px 40px;
      width: 100%; max-width: 380px;
      display: flex; flex-direction: column; align-items: center; gap: 22px;
      box-shadow: 0 24px 64px rgba(0,0,0,.4);
      margin: 24px;
    }
    .lock { font-size: 2.2rem; line-height: 1; }
    .logo-wrap {
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 16px;
      padding: 14px 28px;
    }
    .logo-wrap img { height: 48px; display: block; }
    .logo-text {
      font-size: 1.3rem; font-weight: 800; color: #93c5fd;
      letter-spacing: -.3px;
    }
    .title {
      font-size: 1rem; font-weight: 700; color: #fff; text-align: center;
    }
    .subtitle {
      font-size: .85rem; color: rgba(255,255,255,.45);
      text-align: center; margin-top: -14px;
    }
    form { width: 100%; display: flex; flex-direction: column; gap: 10px; }
    input[type="password"] {
      width: 100%; padding: 13px 16px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 10px;
      color: #fff; font-size: .95rem;
      outline: none; transition: border-color .2s;
    }
    input[type="password"]:focus { border-color: #3b82f6; }
    input[type="password"]::placeholder { color: rgba(255,255,255,.3); }
    .error {
      font-size: .82rem; color: #fca5a5;
      text-align: center; padding: 6px 0;
      display: <?= $authError ? 'block' : 'none' ?>;
    }
    button[type="submit"] {
      width: 100%; padding: 13px;
      background: #2563eb; color: #fff;
      border: none; border-radius: 10px;
      font-size: .95rem; font-weight: 700;
      cursor: pointer; transition: background .2s, transform .1s;
      letter-spacing: .02em;
    }
    button[type="submit"]:hover  { background: #1d4ed8; }
    button[type="submit"]:active { transform: scale(.98); }
  </style>
</head>
<body>
  <div class="card">
    <span class="lock">🔒</span>
    <div class="logo-wrap">
      <img src="<?= htmlspecialchars($_authLogo) ?>" alt="<?= $_authName ?>"
           onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'logo-text',textContent:'<?= addslashes($_authName) ?>'}))">
    </div>
    <div class="title"><?= $_authName ?></div>
    <div class="subtitle">Digite a senha para acessar</div>
    <form method="POST">
      <input type="password" name="pwd" placeholder="Senha" autofocus autocomplete="current-password">
      <div class="error">Senha incorreta. Tente novamente.</div>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>
<?php
exit;
