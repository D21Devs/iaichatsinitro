<?php
// Widget Config — injeta variáveis de configuração como JavaScript
// Carregado pelo loader.js antes do widget.js
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
require_once dirname(__DIR__) . '/api/config.php';
?>
window.METIS_AGENT_NAME  = '<?= addslashes(AGENT_NAME) ?>';
window.METIS_WELCOME_MSG = '<?= addslashes(WELCOME_MESSAGE) ?>';
