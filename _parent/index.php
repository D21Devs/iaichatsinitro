<?php
require_once __DIR__ . '/sinistro/api/config.php';
$agentName = htmlspecialchars(AGENT_NAME);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $agentName ?> — Assistente Virtual com IA</title>
  <meta name="description" content="Plataforma de assistente virtual com inteligência artificial. Converse com o agente ou configure sua base de conhecimento no painel de treinamento." />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --blue-950: #03091a;
      --blue-900: #060f2e;
      --blue-800: #0a1854;
      --blue-700: #0d2380;
      --blue-600: #1a3fad;
      --blue-500: #2563eb;
      --blue-400: #3b82f6;
      --blue-300: #93c5fd;
      --blue-100: #dbeafe;
      --cyan:      #06b6d4;
      --cyan-light:#a5f3fc;
      --white:     #ffffff;
      --gray-400:  #9ca3af;
      --text:      #dbeafe;
      --muted:     #60a5fa;
      --radius-lg: 20px;
      --radius-md: 14px;
      --radius-sm: 8px;
    }

    html, body {
      min-height: 100%;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
      background: var(--blue-950);
      color: var(--text);
      overflow-x: hidden;
    }

    /* ── BACKGROUND ──────────────────────────────── */
    body::before {
      content: '';
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 0%,   rgba(37,99,235,.45) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 100%, rgba(6,182,212,.18)  0%, transparent 55%),
        radial-gradient(ellipse 100% 80% at 50% 50%, rgba(3,9,26,1)       0%, transparent 100%);
      pointer-events: none;
    }
    body::after {
      content: '';
      position: fixed; inset: 0; z-index: 0;
      background-image:
        radial-gradient(circle, rgba(255,255,255,.035) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
    }

    /* ── LAYOUT ──────────────────────────────────── */
    .page {
      position: relative; z-index: 1;
      min-height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 48px 24px 32px;
      gap: 0;
    }

    /* ── BADGE ───────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(37,99,235,.2);
      border: 1px solid rgba(59,130,246,.35);
      border-radius: 100px;
      padding: 5px 14px;
      font-size: 11px; font-weight: 600; letter-spacing: .6px;
      text-transform: uppercase; color: var(--blue-300);
      margin-bottom: 32px;
    }
    .badge-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: var(--cyan);
      box-shadow: 0 0 6px var(--cyan);
      animation: pulse 2s ease-in-out infinite;
    }
    @keyframes pulse {
      0%,100% { opacity: 1; transform: scale(1); }
      50%      { opacity: .5; transform: scale(.8); }
    }

    /* ── HERO ────────────────────────────────────── */
    .hero { text-align: center; margin-bottom: 52px; }

    .logo-wrap {
      display: inline-block;
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: var(--radius-lg);
      padding: 16px 28px;
      margin-bottom: 28px;
      backdrop-filter: blur(10px);
      transition: transform .3s, box-shadow .3s;
    }
    .logo-wrap:hover { transform: translateY(-3px); box-shadow: 0 16px 48px rgba(37,99,235,.4); }
    .logo-wrap img { height: 64px; display: block; }

    .logo-text {
      font-size: 2rem; font-weight: 800; letter-spacing: -.5px;
      background: linear-gradient(135deg, var(--white) 0%, var(--blue-300) 60%, var(--cyan) 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-title {
      font-size: clamp(2rem, 5vw, 3.2rem);
      font-weight: 800;
      letter-spacing: -.5px;
      line-height: 1.1;
      background: linear-gradient(135deg, var(--white) 0%, var(--blue-300) 50%, var(--cyan) 100%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 14px;
    }

    .hero-sub {
      font-size: clamp(.95rem, 2vw, 1.1rem);
      color: var(--muted);
      max-width: 480px;
      line-height: 1.6;
      margin: 0 auto;
    }

    /* ── CARDS ───────────────────────────────────── */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      width: 100%;
      max-width: 680px;
      margin-bottom: 48px;
    }

    .card {
      position: relative; overflow: hidden;
      border-radius: var(--radius-lg);
      padding: 32px 28px;
      display: flex; flex-direction: column; gap: 12px;
      cursor: pointer; text-decoration: none;
      transition: transform .25s, box-shadow .25s;
      isolation: isolate;
    }
    .card:hover { transform: translateY(-5px); }

    /* Card 1 — Agente (filled blue) */
    .card-agent {
      background: linear-gradient(145deg, var(--blue-500) 0%, var(--blue-700) 100%);
      border: 1px solid rgba(59,130,246,.5);
      box-shadow: 0 8px 32px rgba(37,99,235,.5);
    }
    .card-agent::before {
      content: '';
      position: absolute; inset: 0; z-index: -1;
      background: radial-gradient(circle at 80% 20%, rgba(6,182,212,.25) 0%, transparent 50%);
    }
    .card-agent:hover { box-shadow: 0 20px 56px rgba(37,99,235,.7); }

    /* Card 2 — Treinamento (glass outline) */
    .card-training {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.12);
      box-shadow: 0 8px 32px rgba(0,0,0,.3);
      backdrop-filter: blur(16px);
    }
    .card-training:hover {
      background: rgba(255,255,255,.07);
      border-color: rgba(6,182,212,.4);
      box-shadow: 0 20px 56px rgba(6,182,212,.15);
    }

    .card-icon {
      font-size: 2rem;
      line-height: 1;
      margin-bottom: 4px;
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--white);
      letter-spacing: -.2px;
    }

    .card-desc {
      font-size: .9rem;
      color: rgba(255,255,255,.65);
      line-height: 1.55;
      flex: 1;
    }
    .card-agent .card-desc { color: rgba(255,255,255,.8); }

    .card-cta {
      display: inline-flex; align-items: center; gap: 6px;
      margin-top: 8px;
      font-size: .85rem; font-weight: 600;
      color: var(--white);
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      width: fit-content;
      transition: gap .2s;
    }
    .card-agent   .card-cta { background: rgba(6,182,212,.2); color: var(--cyan-light); }
    .card-training .card-cta { background: rgba(255,255,255,.08); }
    .card:hover .card-cta { gap: 10px; }

    /* ── FOOTER ──────────────────────────────────── */
    .footer {
      display: flex; align-items: center; gap: 8px;
      font-size: 12px; color: rgba(255,255,255,.3);
      user-select: none;
    }
    .footer a {
      color: rgba(255,255,255,.45);
      text-decoration: none;
      font-weight: 600;
      transition: color .2s;
    }
    .footer a:hover { color: var(--blue-300); }
    .footer-sep { opacity: .4; }

    /* ── RESPONSIVE ──────────────────────────────── */
    @media (max-width: 480px) {
      .cards { grid-template-columns: 1fr; }
      .card  { padding: 28px 22px; }
    }
  </style>
</head>
<body>
  <div class="page">

    <div class="badge">
      <span class="badge-dot"></span>
      Agente online agora
    </div>

    <div class="hero">
      <div class="logo-wrap">
        <img src="sinistro/img/logo.png" alt="<?= $agentName ?>" onerror="this.style.display='none';this.parentElement.querySelector('.logo-text').style.display='block'">
        <span class="logo-text" style="display:none"><?= $agentName ?></span>
      </div>
      <h1 class="hero-title"><?= $agentName ?></h1>
      <p class="hero-sub">
        Plataforma de <strong style="color:var(--blue-300)">assistente virtual com IA</strong>
        para atendimento inteligente, base de conhecimento e simulações de qualidade.
      </p>
    </div>

    <div class="cards">

      <a class="card card-agent" href="sinistro/chat.php">
        <div class="card-icon">💬</div>
        <div class="card-title">Falar com o Agente</div>
        <div class="card-desc">
          Converse em tempo real com o <?= $agentName ?> — tire dúvidas, obtenha informações e veja o agente em ação.
        </div>
        <div class="card-cta">Acessar agora &rarr;</div>
      </a>

      <a class="card card-training" href="sinistro/training/">
        <div class="card-icon">⚙️</div>
        <div class="card-title">Painel de Treinamento</div>
        <div class="card-desc">
          Configure a base de conhecimento, crie personas, rode simulações e monitore a qualidade do agente.
        </div>
        <div class="card-cta">Abrir painel &rarr;</div>
      </a>

    </div>

    <footer class="footer">
      <span>Powered by</span>
      <a href="https://iaichat.com.br" target="_blank">IAIchat</a>
      <span class="footer-sep">·</span>
      <span>Plataforma de agentes com IA</span>
    </footer>

  </div>
</body>
</html>
