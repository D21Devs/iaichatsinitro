<?php require_once __DIR__ . '/../auth.php'; ?>
<?php include __DIR__ . '/index.html'; ?>
<script>
(function() {
  var btn = document.createElement('a');
  btn.href = '?logout=1';
  btn.title = 'Sair';
  btn.textContent = '\u2686 Sair';
  btn.style.cssText = [
    'position:fixed', 'bottom:14px', 'right:14px', 'z-index:9999',
    'background:rgba(37,99,235,.85)', 'color:#fff',
    'border:1px solid rgba(255,255,255,.2)', 'border-radius:8px',
    'padding:5px 12px', 'font-size:12px', 'font-weight:600',
    'text-decoration:none', 'backdrop-filter:blur(8px)',
    'transition:background .15s'
  ].join(';');
  btn.onmouseover = function() { btn.style.background = 'rgba(29,78,216,.95)'; };
  btn.onmouseout  = function() { btn.style.background = 'rgba(37,99,235,.85)'; };
  document.body.appendChild(btn);
})();
</script>
