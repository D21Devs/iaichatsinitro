/* ============================================================
   Metis Brasil — Widget de Chat
   v1.0 | 2026
   ============================================================ */
(function (window, document) {
  'use strict';

  /* ---- Config ---- */
  var API_URL     = window.METIS_WIDGET_API || '/sinistro/api/chat.php';
  var SESSION_KEY = 'metis_chat_sid';
  var BRAND_COLOR = '#20629d';
  var BRAND_DARK  = '#1a5285';
  var LOGO_URL     = window.METIS_WIDGET_LOGO     || '/sinistro/img/logo.png';
  var LOGO_BTN_URL = window.METIS_WIDGET_LOGO_BTN || '/sinistro/img/logoCHAT.png';

  /* ---- Session ID (persiste entre recarregamentos) ---- */
  var sessionId = (function () {
    var stored = '';
    try { stored = localStorage.getItem(SESSION_KEY) || ''; } catch (e) {}
    if (!stored) {
      stored = 'ms_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      try { localStorage.setItem(SESSION_KEY, stored); } catch (e) {}
    }
    return stored;
  })();

  /* ---- Estado ---- */
  var isOpen       = false;
  var isBusy       = false;
  var initialized  = false;
  var msgCount     = 0;

  /* ============================================================
     CSS — injetado no <head>
     ============================================================ */
  var CSS = [
    /* ---- Botão flutuante ---- */
    '#metis-btn{',
      'position:fixed;bottom:24px;right:24px;',
      'width:60px;height:60px;border-radius:50%;',
      'background:' + BRAND_COLOR + ';',
      'cursor:pointer;border:none;outline:none;',
      'box-shadow:0 4px 20px rgba(32,98,157,.45);',
      'z-index:2147483647;',
      'display:flex;align-items:center;justify-content:center;',
      'transition:transform .2s,box-shadow .2s;',
    '}',
    '#metis-btn:hover{transform:scale(1.08);box-shadow:0 6px 26px rgba(32,98,157,.55)}',
    '#metis-btn svg{width:28px;height:28px;fill:#fff;pointer-events:none}',
    '#metis-btn img{width:40px;height:40px;object-fit:contain;pointer-events:none}',
    '#metis-badge{',
      'position:absolute;top:2px;right:2px;',
      'width:14px;height:14px;',
      'background:#e53935;border:2px solid #fff;border-radius:50%;',
      'display:none;',
    '}',
    '#metis-badge.on{display:block}',

    /* ---- Janela ---- */
    '#metis-win{',
      'position:fixed;bottom:96px;right:24px;',
      'width:380px;height:565px;',
      'border-radius:16px;background:#fff;',
      'box-shadow:0 12px 48px rgba(0,0,0,.18);',
      'z-index:2147483646;',
      'display:flex;flex-direction:column;overflow:hidden;',
      'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;',
      'font-size:14px;line-height:1.5;color:#1a1a2e;',
      /* animação de entrada */
      'opacity:0;pointer-events:none;',
      'transform:scale(.92) translateY(14px);',
      'transform-origin:bottom right;',
      'transition:transform .28s cubic-bezier(.34,1.56,.64,1),opacity .2s ease;',
    '}',
    '#metis-win.open{opacity:1;pointer-events:all;transform:scale(1) translateY(0)}',

    /* ---- Header ---- */
    '.m-hd{',
      'background:linear-gradient(135deg,' + BRAND_COLOR + ' 0%,' + BRAND_DARK + ' 100%);',
      'padding:14px 16px;',
      'display:flex;align-items:center;gap:12px;flex-shrink:0;',
    '}',
    '.m-logo{height:52px;width:auto;flex-shrink:0}',
    '.m-info{flex:1;display:flex;flex-direction:column;gap:2px}',
    '.m-title{color:#fff;font-size:14px;font-weight:700;line-height:1.2}',
    '.m-sub{color:rgba(255,255,255,.78);font-size:11.5px;display:flex;align-items:center;gap:5px}',
    '.m-dot{width:7px;height:7px;background:#4caf50;border-radius:50%;flex-shrink:0}',
    '.m-close{',
      'background:rgba(255,255,255,.15);border:none;border-radius:50%;',
      'width:28px;height:28px;cursor:pointer;',
      'display:flex;align-items:center;justify-content:center;',
      'color:#fff;font-size:16px;line-height:1;transition:background .2s;flex-shrink:0;',
    '}',
    '.m-close:hover{background:rgba(255,255,255,.28)}',

    /* ---- Área de mensagens ---- */
    '#metis-msgs{',
      'flex:1;overflow-y:auto;',
      'padding:14px 12px;',
      'display:flex;flex-direction:column;gap:9px;',
      'background:#f4f6f9;',
    '}',
    '#metis-msgs::-webkit-scrollbar{width:4px}',
    '#metis-msgs::-webkit-scrollbar-thumb{background:#c5cdd6;border-radius:2px}',

    /* ---- Bubbles ---- */
    '.m-bbl{',
      'max-width:84%;padding:10px 14px;border-radius:16px;',
      'word-break:break-word;white-space:pre-wrap;',
    '}',
    '.m-bbl.bot{',
      'background:#fff;color:#1a1a2e;',
      'border-bottom-left-radius:3px;align-self:flex-start;',
      'box-shadow:0 1px 4px rgba(0,0,0,.07);',
    '}',
    '.m-bbl.usr{',
      'background:' + BRAND_COLOR + ';color:#fff;',
      'border-bottom-right-radius:3px;align-self:flex-end;',
    '}',

    /* ---- Typing ---- */
    '.m-typing{',
      'display:flex;gap:5px;align-items:center;align-self:flex-start;',
      'background:#fff;padding:11px 14px;',
      'border-radius:16px;border-bottom-left-radius:3px;',
      'box-shadow:0 1px 4px rgba(0,0,0,.07);',
    '}',
    '.m-typing span{',
      'width:7px;height:7px;background:' + BRAND_COLOR + ';border-radius:50%;opacity:.5;',
      'animation:m-bounce 1.1s infinite;',
    '}',
    '.m-typing span:nth-child(2){animation-delay:.18s}',
    '.m-typing span:nth-child(3){animation-delay:.36s}',
    '@keyframes m-bounce{0%,80%,100%{transform:translateY(0);opacity:.5}40%{transform:translateY(-5px);opacity:1}}',

    /* ---- Cards de ação ---- */
    '.m-card{align-self:flex-start;width:min(84%,280px);margin-top:2px}',
    '.m-act{',
      'display:flex;align-items:center;gap:9px;',
      'padding:11px 15px;border-radius:12px;',
      'text-decoration:none;font-size:13.5px;font-weight:700;',
      'cursor:pointer;border:none;width:100%;',
      'transition:opacity .18s,transform .15s;',
    '}',
    '.m-act:hover{opacity:.88;transform:translateY(-1px)}',
    '.m-act.portal{background:' + BRAND_COLOR + ';color:#fff}',
    '.m-act.fone{background:#2e7d32;color:#fff}',
    '.m-act svg{width:17px;height:17px;fill:currentColor;flex-shrink:0}',

    /* ---- Input area ---- */
    '.m-foot{',
      'padding:10px 12px;',
      'border-top:1px solid #e4e9f0;',
      'display:flex;gap:8px;background:#fff;flex-shrink:0;',
    '}',
    '#metis-inp{',
      'flex:1;border:1.5px solid #cdd6e0;border-radius:22px;',
      'padding:9px 15px;font-size:14px;font-family:inherit;',
      'color:#1a1a2e;outline:none;resize:none;',
      'max-height:80px;line-height:1.4;background:#fff;',
      'transition:border-color .2s;',
    '}',
    '#metis-inp:focus{border-color:' + BRAND_COLOR + '}',
    '#metis-inp::placeholder{color:#a0aab4}',
    '#metis-send{',
      'width:40px;height:40px;border-radius:50%;',
      'background:' + BRAND_COLOR + ';border:none;cursor:pointer;',
      'display:flex;align-items:center;justify-content:center;',
      'flex-shrink:0;align-self:flex-end;',
      'transition:background .2s,transform .15s;',
    '}',
    '#metis-send:hover:not(:disabled){background:' + BRAND_DARK + ';transform:scale(1.06)}',
    '#metis-send:disabled{background:#b0bec5;cursor:not-allowed}',
    '#metis-send svg{width:17px;height:17px;fill:#fff}',

    /* ---- Brand footer ---- */
    '.m-brand{',
      'text-align:center;padding:5px;',
      'font-size:10px;color:#a8b2bc;',
      'background:#fff;border-top:1px solid #f0f2f4;flex-shrink:0;',
    '}',

    /* ---- Mobile ---- */
    '@media(max-width:460px){',
      '#metis-win{width:100vw;height:100dvh;bottom:0;right:0;border-radius:0;transform:translateY(16px);transform-origin:bottom center}',
      '#metis-win.open{transform:translateY(0)}',
      '#metis-btn{top:80vh;bottom:auto;right:16px}',
      '.m-foot{padding-bottom:max(10px,env(safe-area-inset-bottom))}',
    '}',
  ].join('');

  /* ============================================================
     SVG icons
     ============================================================ */
  var IC_CHAT   = '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>';
  var IC_BOT    = '<svg viewBox="0 0 24 24"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7H3a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2zm-5 9a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm10 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2zM5 17h14v2a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-2z"/></svg>';
  var IC_SEND   = '<svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
  var IC_EXT    = '<svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>';
  var IC_PHONE  = '<svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>';

  /* ============================================================
     Init
     ============================================================ */
  function init() {
    if (initialized) return;
    initialized = true;

    // Injeta CSS
    var style = document.createElement('style');
    style.textContent = CSS;
    document.head.appendChild(style);

    // ---- Botão flutuante ----
    var btn = document.createElement('button');
    btn.id = 'metis-btn';
    btn.setAttribute('aria-label', 'Abrir chat Metis Brasil');
    btn.innerHTML = IC_CHAT + '<span id="metis-badge"></span>';
    btn.addEventListener('click', toggleChat);

    // ---- Janela de chat ----
    var win = document.createElement('div');
    win.id = 'metis-win';
    win.setAttribute('role', 'dialog');
    win.setAttribute('aria-label', 'Chat Assistente Metis');
    win.innerHTML =
      '<div class="m-hd">' +
        '<img class="m-logo" src="' + LOGO_BTN_URL + '" alt="Metis Brasil">' +
        '<div class="m-info">' +
          '<span class="m-title">Assistente Metis</span>' +
          '<div class="m-sub"><span class="m-dot"></span>Online agora</div>' +
        '</div>' +
        '<button class="m-close" id="metis-cls" aria-label="Fechar">&#x2715;</button>' +
      '</div>' +
      '<div id="metis-msgs"></div>' +
      '<div class="m-foot">' +
        '<textarea id="metis-inp" rows="1" placeholder="Digite sua mensagem..."></textarea>' +
        '<button id="metis-send" aria-label="Enviar">' + IC_SEND + '</button>' +
      '</div>' +
      '<div class="m-brand">Metis Brasil &bull; Proteção Patrimonial</div>';

    document.body.appendChild(btn);
    document.body.appendChild(win);

    // ---- Eventos ----
    document.getElementById('metis-cls').addEventListener('click', closeChat);
    document.getElementById('metis-send').addEventListener('click', sendMessage);

    var inp = document.getElementById('metis-inp');
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    inp.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });
  }

  /* ============================================================
     Controles de janela
     ============================================================ */
  function toggleChat() { isOpen ? closeChat() : openChat(); }

  function openChat() {
    isOpen = true;
    document.getElementById('metis-win').classList.add('open');
    document.getElementById('metis-badge').classList.remove('on');
    document.getElementById('metis-btn').style.display = 'none';
    setTimeout(function () {
      var inp = document.getElementById('metis-inp');
      if (inp) inp.focus();
    }, 300);
    // Boas-vindas apenas na primeira abertura
    if (msgCount === 0) welcome();
  }

  function closeChat() {
    isOpen = false;
    document.getElementById('metis-win').classList.remove('open');
    document.getElementById('metis-btn').style.display = '';
  }

  /* ============================================================
     Boas-vindas automáticas
     ============================================================ */
  function welcome() {
    var t1 = showTyping();
    delay(900, function () {
      removeEl(t1);
      addBot('Olá! Eu sou a Assistente Metis. Estou aqui para te ajudar com sinistros da Metis Brasil. Como posso te ajudar hoje?');
    });
  }

  /* ============================================================
     Envio de mensagem
     ============================================================ */
  function sendMessage() {
    var inp  = document.getElementById('metis-inp');
    var text = inp.value.trim();
    if (!text || isBusy) return;

    inp.value = '';
    inp.style.height = 'auto';

    addUser(text);
    setLoading(true);

    var typing = showTyping();

    fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, session_id: sessionId })
    })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      removeEl(typing);
      setLoading(false);

      var msg = data.message || 'Desculpe, não entendi. Pode reformular?';
      addBot(msg);

      if (data.action === 'redirect' && data.url) {
        addCard('portal', data.url, IC_EXT + ' Acessar Portal do Associado', null);
      } else if (data.action === 'phone' && data.number) {
        addCard('fone', null, IC_PHONE + ' WhatsApp Sinistros: ' + data.number, data.number);
      }
    })
    .catch(function () {
      removeEl(typing);
      setLoading(false);
      addBot('Não consegui me conectar no momento. Verifique sua conexão e tente novamente.');
    });

    // Badge se janela estiver fechada
    if (!isOpen) {
      document.getElementById('metis-badge').classList.add('on');
    }
  }

  /* ============================================================
     Helpers de DOM
     ============================================================ */
  function addBot(text) {
    var el = document.createElement('div');
    el.className = 'm-bbl bot';
    el.textContent = text;
    appendMsg(el);
    notifyIfClosed();
  }

  function addUser(text) {
    var el = document.createElement('div');
    el.className = 'm-bbl usr';
    el.textContent = text;
    appendMsg(el);
  }

  function addCard(type, url, labelHTML, phone) {
    var card = document.createElement('div');
    card.className = 'm-card';

    var a = document.createElement('a');
    a.className = 'm-act ' + type;
    a.innerHTML = labelHTML;

    if (type === 'portal' && url) {
      a.href = url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    } else if (type === 'fone' && phone) {
      var digits = phone.replace(/\D/g, '').replace(/^0/, '');
      a.href = 'https://wa.me/55' + digits;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }

    card.appendChild(a);
    appendMsg(card);
    notifyIfClosed();
  }

  function showTyping() {
    var el = document.createElement('div');
    el.className = 'm-typing';
    el.innerHTML = '<span></span><span></span><span></span>';
    appendMsg(el);
    return el;
  }

  function appendMsg(el) {
    var msgs = document.getElementById('metis-msgs');
    msgs.appendChild(el);
    msgs.scrollTop = msgs.scrollHeight;
    msgCount++;
  }

  function removeEl(el) {
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function notifyIfClosed() {
    if (!isOpen) {
      document.getElementById('metis-badge').classList.add('on');
    }
  }

  function setLoading(on) {
    isBusy = on;
    var s = document.getElementById('metis-send');
    if (s) s.disabled = on;
  }

  function delay(ms, fn) { setTimeout(fn, ms); }

  /* ============================================================
     Boot
     ============================================================ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
