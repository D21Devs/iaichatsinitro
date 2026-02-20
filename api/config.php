<?php
// ============================================================
// Metis Brasil — Chatbot de Sinistros
// Configurações da API OpenAI
// ============================================================

// Carrega variáveis do .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('OPENAI_API_KEY', $_ENV['OPENAI_API_KEY_1'] ?? '');
define('OPENAI_MODEL', 'gpt-4o');
define('OPENAI_MAX_TOKENS', 600);
define('OPENAI_TEMPERATURE', 0.3);

define('PORTAL_URL', 'https://associadometis.site/cliente/login');
define('PHONE_NUMBER', '08009443000');
define('PHONE_WHATSAPP', '08009443000'); // WhatsApp Sinistros

// Monta SYSTEM_PROMPT dinamicamente a partir dos blocos ativos em training/knowledge.json
function _buildSystemPrompt(): string {
    $knowledgeFile = dirname(__DIR__) . '/training/knowledge.json';
    if (!file_exists($knowledgeFile)) return '';
    $knowledge = json_decode(file_get_contents($knowledgeFile), true);
    $sections  = [];
    foreach (($knowledge['blocks'] ?? []) as $block) {
        if (!($block['active'] ?? false)) continue;
        $title      = strtoupper($block['name']);
        $sections[] = "════════════════════════════════\n{$title}\n════════════════════════════════\n{$block['content']}";
    }
    return implode("\n\n", $sections);
}
define('SYSTEM_PROMPT', _buildSystemPrompt());