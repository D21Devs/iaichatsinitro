/* Metis Brasil — Widget Loader
   Cole este script no <head> ou antes do </body> do seu site:
   <script src="http://localhost/sinistro/widget/loader.js"></script>
*/
(function () {
  'use strict';

  var currentScript = document.currentScript ||
    (function () {
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length - 1];
    })();

  var loaderSrc = currentScript ? currentScript.src : '';
  // base = tudo antes de /widget/loader.js
  var base = loaderSrc.replace(/\/widget\/loader\.js.*$/, '');

  // Expõe o base URL, endpoint da API e logo para o widget.js
  window.METIS_WIDGET_BASE     = base;
  window.METIS_WIDGET_API      = base + '/api/chat.php';
  window.METIS_WIDGET_LOGO     = base + '/img/logo.png';
  window.METIS_WIDGET_LOGO_BTN = base + '/img/logoCHAT.png';

  // Carrega o widget.js principal
  var script = document.createElement('script');
  script.src = base + '/widget/widget.js';
  script.async = true;
  (document.head || document.body || document.documentElement).appendChild(script);
})();
