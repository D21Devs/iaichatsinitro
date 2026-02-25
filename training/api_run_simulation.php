<?php
/**
 * Training API — Simulação LLM-vs-LLM com Personas
 * GET /training/api_run_simulation.php?id=<persona_id>
 *
 * SSE events:
 *   {type:"start",   name, max_turns}
 *   {type:"turn",    turn, role:"user"|"bot", message, action}
 *   {type:"judging"}
 *   {type:"judge",   score_geral, empatia, precisao, fluidez,
 *                    objetivo_atingido, escalacao_correta,
 *                    issues, pontos_positivos, resumo}
 *   {type:"done",    turns, duration_ms}
 *   {type:"error",   message}
 */

error_reporting(0);
ini_set('display_errors', '0');

// Limpa buffers ativos (output_buffering do php.ini em hosting compartilhado)
while (ob_get_level() > 0) { ob_end_clean(); }
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Connection: keep-alive');

@set_time_limit(300);
ob_implicit_flush(true);

function sse(array $data): void {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/api/config.php';

$PERSONAS_FILE  = __DIR__ . '/personas.json';
$KNOWLEDGE_FILE = __DIR__ . '/knowledge.json';
$DEEPSEEK_KEY   = 'sk-c5041edcb15b48c595aa9681568ee956';
$DEEPSEEK_URL   = 'https://api.deepseek.com/v1/chat/completions';

// ── Carregar persona ──────────────────────────────────────────────────────────
$id = $_GET['id'] ?? '';
if (!$id) { sse(['type' => 'error', 'message' => 'ID da persona não informado']); exit; }
if (!file_exists($PERSONAS_FILE)) { sse(['type' => 'error', 'message' => 'personas.json não encontrado']); exit; }

$pData    = json_decode(file_get_contents($PERSONAS_FILE), true);
$persona  = null;
foreach ($pData['personas'] ?? [] as $p) {
    if ($p['id'] === $id) { $persona = $p; break; }
}
if (!$persona) { sse(['type' => 'error', 'message' => "Persona '$id' não encontrada"]); exit; }

// ── Monta system prompt do bot ────────────────────────────────────────────────
function buildPrompt(string $kf): string {
    if (!file_exists($kf)) return '';
    $k = json_decode(file_get_contents($kf), true);
    $sections = [];
    foreach ($k['blocks'] ?? [] as $b) {
        if (!$b['active']) continue;
        $t = strtoupper($b['name']);
        $sections[] = "════════════════════════════════\n{$t}\n════════════════════════════════\n{$b['content']}";
    }
    return implode("\n\n", $sections);
}

// ── Chamada OpenAI genérica ───────────────────────────────────────────────────
function callOpenAI(array $messages, string $model, int $maxTokens, float $temp, bool $jsonMode): array {
    $payload = ['model' => $model, 'messages' => $messages, 'max_tokens' => $maxTokens, 'temperature' => $temp];
    if ($jsonMode) $payload['response_format'] = ['type' => 'json_object'];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)          return ['_error' => 'cURL: ' . $err];
    if ($code !== 200) return ['_error' => "HTTP $code: " . substr($raw, 0, 200)];
    $dec = json_decode($raw, true);
    return ['content' => $dec['choices'][0]['message']['content'] ?? ''];
}

// ── Juiz DeepSeek ─────────────────────────────────────────────────────────────
function callJudge(array $persona, array $conv): array {
    global $DEEPSEEK_KEY, $DEEPSEEK_URL;

    $transcript = '';
    foreach ($conv as $t) {
        $who = $t['role'] === 'user' ? '[Usuário]' : '[' . AGENT_NAME . ']';
        $transcript .= $who . ': ' . $t['message'] . "\n";
    }

    $escEsc = json_encode($persona['escalation_expected'] ?? null);
    $prompt = "Avalie esta conversa simulada entre um usuário e o " . AGENT_NAME . " (chatbot de sinistros).\n\n"
        . "PERSONA: " . $persona['context'] . "\n"
        . "OBJETIVO: " . $persona['goal'] . "\n"
        . "ESCALAÇÃO ESPERADA: $escEsc\n\n"
        . "CONVERSA:\n$transcript\n\n"
        . "Responda SOMENTE em JSON com:\n"
        . "- score_geral: 0-10\n"
        . "- empatia: 0-10\n"
        . "- precisao: 0-10\n"
        . "- fluidez: 0-10\n"
        . "- objetivo_atingido: true/false\n"
        . "- escalacao_correta: true/false\n"
        . "- issues: array de strings com problemas encontrados\n"
        . "- pontos_positivos: array de strings\n"
        . "- resumo: 2-3 frases avaliando a conversa";

    $ch = curl_init($DEEPSEEK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'           => 'deepseek-chat',
            'messages'        => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'      => 600,
            'temperature'     => 0.1,
            'response_format' => ['type' => 'json_object'],
        ], JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $DEEPSEEK_KEY,
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return ['score_geral' => null, 'issues' => ["DeepSeek HTTP $code"], 'resumo' => 'Erro na avaliação'];
    $dec     = json_decode($raw, true);
    $content = $dec['choices'][0]['message']['content'] ?? '{}';
    return json_decode($content, true) ?: ['score_geral' => null, 'issues' => ['parse error'], 'resumo' => ''];
}

// ── MAIN ──────────────────────────────────────────────────────────────────────
$systemPrompt = buildPrompt($KNOWLEDGE_FILE);
$maxTurns     = max(2, min(12, (int)($persona['max_turns'] ?? 6)));
$startTime    = microtime(true);

sse(['type' => 'start', 'name' => $persona['name'], 'max_turns' => $maxTurns]);

// System prompt do simulador de usuário
$userSimPrompt = "Você é um usuário simulado de um chatbot de atendimento de sinistros.\n\n"
    . "PERSONA: " . $persona['context'] . "\n"
    . "OBJETIVO: " . $persona['goal'] . "\n\n"
    . "REGRAS:\n"
    . "- Responda APENAS como esse usuário responderia, com a emoção e linguagem da persona\n"
    . "- Seja conciso (1-3 frases por mensagem)\n"
    . "- NÃO quebre o personagem nem explique que é uma simulação\n"
    . "- Quando o objetivo for atingido ou a conversa chegar a um fim natural, responda SOMENTE: [FIM]\n"
    . "- Responda SOMENTE com a fala do usuário, sem prefixos como 'Usuário:' ou aspas";

// Histórico do bot (GPT-4o): começa com system prompt
$botHistory = [];

// Histórico do simulador: perspectiva invertida (bot messages = role:user para o simulador)
$userSimHistory = [['role' => 'system', 'content' => $userSimPrompt]];

// Conversa completa para o juiz
$fullConv = [];

// Mensagem de boas-vindas do bot (fixa, sem chamada de API)
$welcomeMsg = WELCOME_MESSAGE;
$botHistory[]     = ['role' => 'assistant', 'content' => json_encode(['message' => $welcomeMsg, 'action' => null, 'url' => null, 'number' => null], JSON_UNESCAPED_UNICODE)];
$userSimHistory[] = ['role' => 'user', 'content' => $welcomeMsg];
$fullConv[]       = ['role' => 'bot', 'message' => $welcomeMsg];

for ($turn = 1; $turn <= $maxTurns; $turn++) {

    // ── Simulador gera próxima mensagem do usuário ────────────────────────────
    $simResp = callOpenAI($userSimHistory, 'gpt-4o-mini', 120, 0.8, false);
    if (isset($simResp['_error'])) {
        sse(['type' => 'error', 'message' => 'Erro no simulador: ' . $simResp['_error']]);
        exit;
    }
    $userMsg = trim($simResp['content']);

    // Fim da simulação
    if (str_contains(strtoupper($userMsg), '[FIM]')) break;

    sse(['type' => 'turn', 'turn' => $turn, 'role' => 'user', 'message' => $userMsg]);
    $fullConv[]       = ['role' => 'user', 'message' => $userMsg];
    $botHistory[]     = ['role' => 'user',      'content' => $userMsg];
    $userSimHistory[] = ['role' => 'assistant',  'content' => $userMsg];

    usleep(200000);

    // ── Bot responde ──────────────────────────────────────────────────────────
    $botMessages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $botHistory
    );
    $botResp = callOpenAI($botMessages, OPENAI_MODEL, 400, OPENAI_TEMPERATURE, true);
    if (isset($botResp['_error'])) {
        sse(['type' => 'error', 'message' => 'Erro no bot: ' . $botResp['_error']]);
        exit;
    }

    $rawContent = $botResp['content'];
    $parsed     = json_decode($rawContent, true) ?: [];
    $botMsg     = $parsed['message'] ?? $rawContent;
    $botAction  = $parsed['action']  ?? null;

    sse(['type' => 'turn', 'turn' => $turn, 'role' => 'bot', 'message' => $botMsg, 'action' => $botAction]);
    $fullConv[]       = ['role' => 'bot', 'message' => $botMsg, 'action' => $botAction];
    $botHistory[]     = ['role' => 'assistant', 'content' => $rawContent];
    $userSimHistory[] = ['role' => 'user',       'content' => $botMsg];

    // Encerra se bot escalou
    if ($botAction === 'redirect' || $botAction === 'phone') break;

    usleep(300000);
}

// ── Avaliação do juiz ─────────────────────────────────────────────────────────
sse(['type' => 'judging']);
$judgeResult = callJudge($persona, $fullConv);
sse(array_merge(['type' => 'judge'], $judgeResult));

$duration  = round((microtime(true) - $startTime) * 1000);
$userTurns = count(array_filter($fullConv, fn($t) => $t['role'] === 'user'));
sse(['type' => 'done', 'turns' => $userTurns, 'duration_ms' => $duration]);
