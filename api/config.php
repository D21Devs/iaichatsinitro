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

// Lê o knowledge.json uma única vez para uso das funções abaixo
function _loadKnowledge(): array {
    $f = dirname(__DIR__) . '/training/knowledge.json';
    if (!file_exists($f)) return ['blocks' => []];
    return json_decode(file_get_contents($f), true) ?? ['blocks' => []];
}

// Nome do agente lido do knowledge.json (campo agent_name) — edite pelo painel de treinamento
function _getAgentName(): string {
    $k = _loadKnowledge();
    return trim($k['agent_name'] ?? '') ?: 'Assistente';
}
define('AGENT_NAME', _getAgentName());

// Monta SYSTEM_PROMPT — blocos ativos exceto categoria "meta"
function _buildSystemPrompt(): string {
    $knowledge = _loadKnowledge();
    $sections  = [];
    foreach (($knowledge['blocks'] ?? []) as $block) {
        if (!($block['active'] ?? false)) continue;
        if (($block['category'] ?? '') === 'meta') continue; // blocos meta não vão pro LLM
        $title      = strtoupper($block['name']);
        $sections[] = "════════════════════════════════\n{$title}\n════════════════════════════════\n{$block['content']}";
    }
    return implode("\n\n", $sections);
}
define('SYSTEM_PROMPT', _buildSystemPrompt());

// Mensagem de boas-vindas lida do bloco "boas_vindas" (category: meta)
function _getWelcomeMessage(): string {
    $knowledge = _loadKnowledge();
    foreach (($knowledge['blocks'] ?? []) as $block) {
        if (($block['id'] ?? '') === 'boas_vindas' && ($block['active'] ?? false)) {
            return trim($block['content']);
        }
    }
    return 'Olá! Como posso te ajudar hoje?';
}
define('WELCOME_MESSAGE', _getWelcomeMessage());