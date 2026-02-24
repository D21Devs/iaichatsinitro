<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/api/config.php';
$agentName    = htmlspecialchars(AGENT_NAME);
$welcomeMsg   = htmlspecialchars(WELCOME_MESSAGE, ENT_QUOTES);
$brandPrimary = htmlspecialchars(BRAND_PRIMARY);
$brandAccent  = htmlspecialchars(BRAND_ACCENT);
$logoUrl      = 'img/logo.png';
$logoChatUrl  = 'img/logoCHAT.png';
$storageKey   = 'sinistro_chat_sessions';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $agentName ?> — Chat</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:      <?= $brandPrimary ?>;
      --accent:     <?= $brandAccent ?>;
      --brand-dark: color-mix(in srgb, <?= $brandPrimary ?> 80%, #000);
      --sidebar-w:  260px;
      --hd-h:       64px;
      --bg:         #f4f6f9;
      --surface:    #ffffff;
      --border:     #e4e9f0;
      --text:       #1a1a2e;
      --muted:      #64748b;
      --radius:     12px;
    }

    html, body { height: 100%; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); }

    /* ══ LAYOUT ══════════════════════════════════════ */
    .app { display: flex; height: 100vh; overflow: hidden; }

    /* ══ SIDEBAR ══════════════════════════════════════ */
    .sidebar {
      width: var(--sidebar-w); flex-shrink: 0;
      background: var(--brand);
      display: flex; flex-direction: column;
      overflow: hidden;
      transition: transform .3s;
    }

    .sb-head {
      padding: 18px 16px 14px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      display: flex; align-items: center; gap: 10px;
    }
    .sb-logo {
      width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
      border: 2px solid rgba(255,255,255,.3); flex-shrink: 0;
    }
    .sb-name {
      color: #fff; font-size: 15px; font-weight: 700;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;
    }

    .sb-new {
      margin: 12px; border-radius: var(--radius);
      background: rgba(255,255,255,.15);
      border: 1px solid rgba(255,255,255,.25);
      color: #fff; font-size: 13px; font-weight: 600;
      padding: 9px 14px; cursor: pointer; width: calc(100% - 24px);
      display: flex; align-items: center; gap: 8px;
      transition: background .2s;
    }
    .sb-new:hover { background: rgba(255,255,255,.25); }
    .sb-new svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }

    .sb-list {
      flex: 1; overflow-y: auto; padding: 4px 8px 12px;
    }
    .sb-list::-webkit-scrollbar { width: 3px; }
    .sb-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 2px; }

    .sb-item {
      display: flex; align-items: center; gap: 8px;
      padding: 9px 10px; border-radius: 8px;
      cursor: pointer; color: rgba(255,255,255,.8);
      font-size: 13px; transition: background .15s;
      user-select: none;
    }
    .sb-item:hover { background: rgba(255,255,255,.12); color: #fff; }
    .sb-item.active { background: rgba(255,255,255,.2); color: #fff; font-weight: 600; }
    .sb-item-icon { font-size: 14px; flex-shrink: 0; }
    .sb-item-title { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sb-item-del {
      opacity: 0; width: 20px; height: 20px; border-radius: 4px;
      background: rgba(255,255,255,.15); border: none; color: #fff;
      font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: opacity .15s, background .15s;
    }
    .sb-item:hover .sb-item-del { opacity: 1; }
    .sb-item-del:hover { background: rgba(220,38,38,.6); }

    .sb-footer {
      padding: 12px 16px;
      border-top: 1px solid rgba(255,255,255,.12);
      font-size: 11px; color: rgba(255,255,255,.45); text-align: center;
    }
    .sb-footer a { color: rgba(255,255,255,.6); text-decoration: none; }
    .sb-footer a:hover { color: #fff; }

    /* ══ MAIN AREA ════════════════════════════════════ */
    .main {
      flex: 1; display: flex; flex-direction: column; overflow: hidden;
      min-width: 0;
    }

    /* ── Header ── */
    .chat-hd {
      height: var(--hd-h); flex-shrink: 0;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
      padding: 0 20px;
    }
    .hd-burger {
      display: none; background: none; border: none; cursor: pointer;
      padding: 6px; border-radius: 6px; color: var(--muted);
      transition: background .15s;
    }
    .hd-burger:hover { background: var(--bg); }
    .hd-burger svg { width: 20px; height: 20px; fill: currentColor; display: block; }

    .hd-logo {
      width: 38px; height: 38px; border-radius: 50%; object-fit: cover;
      border: 2px solid var(--border); flex-shrink: 0;
    }
    .hd-info { flex: 1; }
    .hd-name { font-size: 15px; font-weight: 700; color: var(--text); }
    .hd-status { font-size: 12px; color: #22c55e; display: flex; align-items: center; gap: 5px; }
    .hd-dot { width: 7px; height: 7px; background: #22c55e; border-radius: 50%; }

    /* ── Messages ── */
    #msgs {
      flex: 1; overflow-y: auto; padding: 20px 16px;
      display: flex; flex-direction: column; gap: 12px;
    }
    #msgs::-webkit-scrollbar { width: 5px; }
    #msgs::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    .msg-row { display: flex; gap: 10px; max-width: 780px; width: 100%; }
    .msg-row.bot { align-self: flex-start; }
    .msg-row.usr { align-self: flex-end; flex-direction: row-reverse; }

    .msg-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      flex-shrink: 0; align-self: flex-end;
      object-fit: cover; border: 1.5px solid var(--border);
    }
    .msg-row.usr .msg-avatar { border-color: var(--brand); }

    .bubble {
      max-width: calc(100% - 80px);
      padding: 11px 15px;
      border-radius: 16px;
      font-size: 14px; line-height: 1.6;
      word-break: break-word;
    }
    .bubble.bot {
      background: var(--surface);
      color: var(--text);
      border-bottom-left-radius: 4px;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .bubble.usr {
      background: var(--brand);
      color: #fff;
      border-bottom-right-radius: 4px;
    }

    /* markdown inside bubbles */
    .bubble strong { font-weight: 700; }
    .bubble em { font-style: italic; }
    .bubble code {
      background: rgba(0,0,0,.06); padding: 1px 5px;
      border-radius: 4px; font-size: 13px; font-family: monospace;
    }
    .bubble.usr code { background: rgba(255,255,255,.2); }
    .bubble ul, .bubble ol { padding-left: 18px; margin: 4px 0; }
    .bubble li { margin: 2px 0; }
    .bubble a { color: var(--accent); text-decoration: underline; }
    .bubble.usr a { color: #fff; }
    .bubble p + p { margin-top: 6px; }

    /* action cards */
    .action-card {
      margin-top: 8px; display: flex; flex-direction: column; gap: 6px;
    }
    .act-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 16px; border-radius: 10px;
      font-size: 13px; font-weight: 700;
      text-decoration: none; border: none; cursor: pointer;
      transition: opacity .15s, transform .15s;
      width: fit-content;
    }
    .act-btn:hover { opacity: .88; transform: translateY(-1px); }
    .act-btn.portal { background: var(--brand); color: #fff; }
    .act-btn.fone   { background: #16a34a; color: #fff; }
    .act-btn svg { width: 16px; height: 16px; fill: currentColor; flex-shrink: 0; }

    /* typing indicator */
    .typing {
      display: flex; gap: 5px; align-items: center;
      padding: 11px 15px;
      background: var(--surface); border-radius: 16px; border-bottom-left-radius: 4px;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
      align-self: flex-start;
    }
    .typing span {
      width: 7px; height: 7px; background: var(--brand); border-radius: 50%;
      opacity: .5; animation: bounce 1.1s infinite;
    }
    .typing span:nth-child(2) { animation-delay: .18s; }
    .typing span:nth-child(3) { animation-delay: .36s; }
    @keyframes bounce {
      0%,80%,100% { transform: translateY(0); opacity: .5; }
      40%          { transform: translateY(-5px); opacity: 1; }
    }

    /* empty state */
    .empty-state {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 12px; color: var(--muted); text-align: center; padding: 32px;
    }
    .empty-logo {
      width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
      border: 3px solid var(--border); opacity: .7;
    }
    .empty-title { font-size: 20px; font-weight: 700; color: var(--text); }
    .empty-sub { font-size: 14px; max-width: 340px; line-height: 1.6; }

    /* ── Input bar ── */
    .input-bar {
      flex-shrink: 0; padding: 12px 16px;
      background: var(--surface); border-top: 1px solid var(--border);
      display: flex; gap: 10px; align-items: flex-end;
    }
    #inp {
      flex: 1; border: 1.5px solid var(--border); border-radius: 22px;
      padding: 10px 16px; font-size: 14px; font-family: inherit;
      resize: none; outline: none; max-height: 120px;
      line-height: 1.5; background: var(--bg); color: var(--text);
      transition: border-color .2s;
    }
    #inp:focus { border-color: var(--brand); background: var(--surface); }
    #inp::placeholder { color: #a0aab4; }
    #send-btn {
      width: 42px; height: 42px; border-radius: 50%;
      background: var(--brand); border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: background .2s, transform .15s;
    }
    #send-btn:hover:not(:disabled) { background: var(--brand-dark); transform: scale(1.06); }
    #send-btn:disabled { background: #b0bec5; cursor: not-allowed; }
    #send-btn svg { width: 18px; height: 18px; fill: #fff; }

    /* ══ MOBILE ═══════════════════════════════════════ */
    .sb-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.45); z-index: 99;
    }
    @media (max-width: 640px) {
      .sidebar {
        position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
        transform: translateX(-100%);
      }
      .sidebar.open { transform: translateX(0); }
      .sb-overlay.show { display: block; }
      .hd-burger { display: flex; }
      #msgs { padding: 12px 10px; }
      .bubble { max-width: calc(100% - 52px); }
    }
  </style>
</head>
<body>
<div class="app">

  <!-- ══ SIDEBAR ══ -->
  <div class="sidebar" id="sidebar">
    <div class="sb-head">
      <img class="sb-logo" src="<?= $logoChatUrl ?>" alt="<?= $agentName ?>" onerror="this.src='img/logo.png'">
      <span class="sb-name"><?= $agentName ?></span>
    </div>

    <button class="sb-new" onclick="newSession()">
      <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Nova conversa
    </button>

    <div class="sb-list" id="sb-list"></div>

    <div class="sb-footer">
      Powered by <a href="https://iaichat.com.br" target="_blank">IAIchat</a>
      <a href="?logout=1" style="display:block;margin-top:6px;opacity:.5;font-size:11px;" title="Sair">&#8286; Sair</a>
    </div>
  </div>
  <div class="sb-overlay" id="sb-overlay" onclick="closeSidebar()"></div>

  <!-- ══ MAIN ══ -->
  <div class="main">

    <div class="chat-hd">
      <button class="hd-burger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      </button>
      <img class="hd-logo" src="<?= $logoChatUrl ?>" alt="<?= $agentName ?>" onerror="this.src='img/logo.png'">
      <div class="hd-info">
        <div class="hd-name"><?= $agentName ?></div>
        <div class="hd-status"><span class="hd-dot"></span>Online agora</div>
      </div>
    </div>

    <div id="msgs"></div>

    <div class="input-bar">
      <textarea id="inp" rows="1" placeholder="Digite uma mensagem..."></textarea>
      <button id="send-btn" onclick="sendMsg()" aria-label="Enviar">
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>

  </div>
</div>

<script>
(function () {
'use strict';

/* ── Config ────────────────────────────────────────── */
var API_URL     = 'api/chat.php';
var STORAGE_KEY = '<?= $storageKey ?>';
var WELCOME     = '<?= addslashes(WELCOME_MESSAGE) ?>';
var AGENT_NAME  = '<?= addslashes(AGENT_NAME) ?>';
var LOGO_CHAT   = '<?= $logoChatUrl ?>';
var LOGO_HDR    = '<?= $logoUrl ?>';

/* ── State ─────────────────────────────────────────── */
var sessions   = [];
var activeId   = null;
var isBusy     = false;

/* ── Icons ─────────────────────────────────────────── */
var IC_EXT   = '<svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>';
var IC_PHONE = '<svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>';

/* ── Markdown parser (inline, sem CDN) ──────────────── */
function md(text) {
  if (!text) return '';
  var s = text
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  s = s.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
  s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
  s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
  s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener">$1</a>');
  var lines = s.split('\n');
  var out = []; var inList = false;
  for (var i = 0; i < lines.length; i++) {
    var l = lines[i];
    var li = l.match(/^[\-\*]\s+(.+)/);
    if (li) {
      if (!inList) { out.push('<ul>'); inList = true; }
      out.push('<li>' + li[1] + '</li>');
    } else {
      if (inList) { out.push('</ul>'); inList = false; }
      out.push(l);
    }
  }
  if (inList) out.push('</ul>');
  s = out.join('\n').replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');
  return '<p>' + s + '</p>';
}

/* ── localStorage helpers ───────────────────────────── */
function save() {
  try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ sessions: sessions, activeId: activeId })); } catch(e) {}
}
function load() {
  try {
    var raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    var d = JSON.parse(raw);
    sessions = d.sessions || [];
    activeId = d.activeId || null;
  } catch(e) {}
}

function uuid() {
  return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
}

/* ── Session ops ─────────────────────────────────────── */
function getSession(id) {
  return sessions.find(function(s) { return s.id === id; }) || null;
}

function newSession() {
  var sess = { id: uuid(), title: 'Nova conversa', messages: [], createdAt: new Date().toISOString() };
  sessions.unshift(sess);
  activeId = sess.id;
  save();
  renderSidebar();
  renderChat();
  closeSidebar();
  setTimeout(function() { document.getElementById('inp').focus(); }, 100);
}

function deleteSession(id) {
  sessions = sessions.filter(function(s) { return s.id !== id; });
  if (activeId === id) {
    activeId = sessions.length ? sessions[0].id : null;
  }
  if (!activeId && sessions.length === 0) {
    var sess = { id: uuid(), title: 'Nova conversa', messages: [], createdAt: new Date().toISOString() };
    sessions.push(sess);
    activeId = sess.id;
  }
  save();
  renderSidebar();
  renderChat();
}

function switchSession(id) {
  activeId = id;
  save();
  renderSidebar();
  renderChat();
  closeSidebar();
}

/* ── Render sidebar ──────────────────────────────────── */
function renderSidebar() {
  var list = document.getElementById('sb-list');
  if (!sessions.length) { list.innerHTML = ''; return; }
  list.innerHTML = sessions.map(function(s) {
    var active = s.id === activeId ? ' active' : '';
    return '<div class="sb-item' + active + '" data-id="' + esc(s.id) + '">' +
      '<span class="sb-item-icon">💬</span>' +
      '<span class="sb-item-title">' + esc(s.title) + '</span>' +
      '<button class="sb-item-del" data-del="' + esc(s.id) + '" title="Excluir">✕</button>' +
    '</div>';
  }).join('');

  list.querySelectorAll('.sb-item').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (e.target.closest('[data-del]')) return;
      switchSession(el.dataset.id);
    });
  });
  list.querySelectorAll('[data-del]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      deleteSession(btn.dataset.del);
    });
  });
}

/* ── Render chat ─────────────────────────────────────── */
function renderChat() {
  var msgs  = document.getElementById('msgs');
  var sess  = activeId ? getSession(activeId) : null;
  msgs.innerHTML = '';

  if (!sess || sess.messages.length === 0) {
    msgs.innerHTML =
      '<div class="empty-state">' +
        '<img class="empty-logo" src="' + LOGO_CHAT + '" onerror="this.src=\'' + LOGO_HDR + '\'" alt="">' +
        '<div class="empty-title">' + esc(AGENT_NAME) + '</div>' +
        '<div class="empty-sub">Olá! Inicie uma conversa enviando uma mensagem abaixo.</div>' +
      '</div>';
    return;
  }

  sess.messages.forEach(function(m) {
    if (m.role === 'user') {
      appendUserBubble(m.content, false);
    } else if (m.role === 'assistant') {
      var parsed = tryParse(m.content);
      appendBotBubble(parsed.message, parsed.action, parsed.url, parsed.number, false);
    }
  });

  scrollBottom();
}

/* ── Bubble helpers ──────────────────────────────────── */
function appendUserBubble(text, scroll) {
  var msgs = document.getElementById('msgs');
  var es = msgs.querySelector('.empty-state');
  if (es) es.remove();

  var row = document.createElement('div');
  row.className = 'msg-row usr';
  row.innerHTML =
    '<div class="bubble usr">' + esc(text) + '</div>' +
    '<img class="msg-avatar" src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Ccircle cx=\'12\' cy=\'8\' r=\'4\' fill=\'%23fff\'/%3E%3Cpath d=\'M20 20c0-4.4-3.6-8-8-8s-8 3.6-8 8\' fill=\'%23fff\'/%3E%3C/svg%3E" style="background:var(--brand)" alt="Você">';
  msgs.appendChild(row);
  if (scroll !== false) scrollBottom();
}

function appendBotBubble(text, action, url, number, scroll) {
  var msgs = document.getElementById('msgs');
  var es = msgs.querySelector('.empty-state');
  if (es) es.remove();

  var row = document.createElement('div');
  row.className = 'msg-row bot';

  var bubbleHTML = '<div class="bubble bot">' + md(text) + '</div>';
  var cardHTML   = '';
  if (action === 'redirect' && url) {
    cardHTML = '<div class="action-card"><a class="act-btn portal" href="' + esc(url) + '" target="_blank" rel="noopener">' + IC_EXT + ' Acessar Portal</a></div>';
  } else if (action === 'phone' && number) {
    var digits = number.replace(/\D/g, '').replace(/^0/, '');
    cardHTML = '<div class="action-card"><a class="act-btn fone" href="https://wa.me/55' + esc(digits) + '" target="_blank" rel="noopener">' + IC_PHONE + ' WhatsApp: ' + esc(number) + '</a></div>';
  }

  row.innerHTML =
    '<img class="msg-avatar" src="' + LOGO_CHAT + '" onerror="this.src=\'' + LOGO_HDR + '\'" alt="' + esc(AGENT_NAME) + '">' +
    '<div>' + bubbleHTML + cardHTML + '</div>';

  msgs.appendChild(row);
  if (scroll !== false) scrollBottom();
}

/* ── Typing indicator ────────────────────────────────── */
function showTyping() {
  var msgs = document.getElementById('msgs');
  var el = document.createElement('div');
  el.className = 'msg-row bot'; el.id = 'typing-row';
  el.innerHTML =
    '<img class="msg-avatar" src="' + LOGO_CHAT + '" onerror="this.src=\'' + LOGO_HDR + '\'" alt="">' +
    '<div class="typing"><span></span><span></span><span></span></div>';
  msgs.appendChild(el);
  scrollBottom();
  return el;
}
function removeTyping() {
  var el = document.getElementById('typing-row');
  if (el) el.remove();
}

/* ── Send message ────────────────────────────────────── */
function sendMsg() {
  var inp  = document.getElementById('inp');
  var text = inp.value.trim();
  if (!text || isBusy) return;

  if (!activeId) newSession();
  var sess = getSession(activeId);
  if (!sess) return;

  inp.value = ''; inp.style.height = 'auto';

  sess.messages.push({ role: 'user', content: text });
  save();
  appendUserBubble(text);

  if (sess.messages.length === 1) {
    sess.title = text.length > 42 ? text.substring(0, 42) + '…' : text;
    renderSidebar();
  }

  isBusy = true;
  document.getElementById('send-btn').disabled = true;
  showTyping();

  var history = sess.messages.slice();

  fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: text, session_id: sess.id, history: history })
  })
  .then(function(r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  })
  .then(function(data) {
    removeTyping();
    isBusy = false;
    document.getElementById('send-btn').disabled = false;

    var msg = data.message || 'Desculpe, não entendi.';
    sess.messages.push({ role: 'assistant', content: JSON.stringify(data) });
    save();

    appendBotBubble(msg, data.action, data.url, data.number);
    document.getElementById('inp').focus();
  })
  .catch(function() {
    removeTyping();
    isBusy = false;
    document.getElementById('send-btn').disabled = false;
    var errMsg = 'Não consegui me conectar. Verifique sua conexão e tente novamente.';
    sess.messages.push({ role: 'assistant', content: JSON.stringify({ message: errMsg }) });
    save();
    appendBotBubble(errMsg);
  });
}

/* ── Helpers ─────────────────────────────────────────── */
function tryParse(content) {
  try {
    var p = JSON.parse(content);
    if (p && p.message) return p;
  } catch(e) {}
  return { message: content, action: null, url: null, number: null };
}

function scrollBottom() {
  var msgs = document.getElementById('msgs');
  msgs.scrollTop = msgs.scrollHeight;
}

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Mobile sidebar ──────────────────────────────────── */
window.openSidebar = function() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sb-overlay').classList.add('show');
};
window.closeSidebar = function() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sb-overlay').classList.remove('show');
};

/* ── Input auto-resize + Enter ───────────────────────── */
document.getElementById('inp').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});
document.getElementById('inp').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

window.newSession = newSession;
window.sendMsg    = sendMsg;

/* ── Boot ────────────────────────────────────────────── */
load();

if (!sessions.length) {
  var first = { id: uuid(), title: 'Nova conversa', messages: [], createdAt: new Date().toISOString() };
  sessions.push(first);
  activeId = first.id;
  save();
}

if (!getSession(activeId)) {
  activeId = sessions[0].id;
  save();
}

var bootSess = getSession(activeId);
if (bootSess && bootSess.messages.length === 0) {
  bootSess.messages.push({ role: 'assistant', content: JSON.stringify({ message: WELCOME }) });
  save();
}

renderSidebar();
renderChat();

})();
</script>
</body>
</html>
