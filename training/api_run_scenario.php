<?php
/**
 * Training API — Executa cenário conversacional via SSE
 * GET /training/api_run_scenario.php?id=<scenario_id>
 *
 * SSE events:
 *   { type:"start", name, total }
 *   { type:"step",  step, total, user, response, action_got, action_exp, pass, fails, keywords }
 *   { type:"done",  passed, total, duration_ms }
 *   { type:"error", message }
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

set_time_limit(0);
ob_implicit_flush(true);

function sse(array $data): void {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/api/config.php';

$SCENARIOS_FILE  = __DIR__ . '/scenarios.json';
$KNOWLEDGE_FILE  = __DIR__ . '/knowledge.json';

$id = $_GET['id'] ?? '';
if (!$id) { sse(['type' => 'error', 'message' => 'ID do cenário não informado']); exit; }

if (!file_exists($SCENARIOS_FILE)) { sse(['type' => 'error', 'message' => 'scenarios.json não encontrado']); exit; }

$data      = json_decode(file_get_contents($SCENARIOS_FILE), true);
$scenarios = $data['scenarios'] ?? [];
$scenario  = null;
foreach ($scenarios as $s) { if ($s['id'] === $id) { $scenario = $s; break; } }
if (!$scenario) { sse(['type' => 'error', 'message' => "Cenário '$id' não encontrado"]); exit; }

// Monta system prompt
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

$systemPrompt = buildPrompt($KNOWLEDGE_FILE);
$steps        = $scenario['steps'] ?? [];
$total        = count($steps);
$history      = [['role' => 'system', 'content' => $systemPrompt]]; // conversa acumulada
$passed       = 0;
$startTime    = microtime(true);

sse(['type' => 'start', 'name' => $scenario['name'], 'total' => $total]);

foreach ($steps as $i => $step) {
    $userMsg    = $step['user'] ?? '';
    $expAction  = $step['expected_action'] ?? null;
    $keywords   = $step['keywords'] ?? [];

    // Adiciona mensagem do usuário ao histórico
    $history[] = ['role' => 'user', 'content' => $userMsg];

    // Chama OpenAI com histórico completo
    $payload = json_encode([
        'model'           => OPENAI_MODEL,
        'messages'        => $history,
        'max_tokens'      => 300,
        'temperature'     => OPENAI_TEMPERATURE,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
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

    if ($err || $code !== 200) {
        sse(['type' => 'step', 'step' => $i+1, 'total' => $total,
             'user' => $userMsg, 'response' => '', 'action_got' => null,
             'action_exp' => $expAction, 'pass' => false,
             'fails' => ["API error: " . ($err ?: "HTTP $code")]]);
        $history[] = ['role' => 'assistant', 'content' => '{}'];
        usleep(500000);
        continue;
    }

    $dec     = json_decode($raw, true);
    $content = $dec['choices'][0]['message']['content'] ?? '{}';
    $resp    = json_decode($content, true) ?: [];

    // Adiciona resposta do assistente ao histórico (conteúdo bruto JSON)
    $history[] = ['role' => 'assistant', 'content' => $content];

    $message   = $resp['message'] ?? '';
    $actionGot = $resp['action'] ?? null;
    $msgLower  = strtolower($message);

    // Avalia
    $fails = [];
    if (empty(trim($message))) $fails[] = 'Mensagem vazia';
    if ($actionGot !== $expAction) $fails[] = "Action: esperado=" . json_encode($expAction) . " obtido=" . json_encode($actionGot);
    if (!empty($keywords)) {
        $found = false;
        foreach ($keywords as $kw) { if (str_contains($msgLower, strtolower($kw))) { $found = true; break; } }
        if (!$found) $fails[] = 'Sem keyword: [' . implode('|', $keywords) . ']';
    }

    $pass = empty($fails);
    if ($pass) $passed++;

    sse([
        'type'       => 'step',
        'step'       => $i + 1,
        'total'      => $total,
        'user'       => $userMsg,
        'response'   => mb_substr($message, 0, 200),
        'action_got' => $actionGot,
        'action_exp' => $expAction,
        'pass'       => $pass,
        'fails'      => $fails,
        'keywords'   => $keywords,
    ]);

    usleep(400000); // 400ms entre passos
}

$duration = round((microtime(true) - $startTime) * 1000);
sse([
    'type'        => 'done',
    'passed'      => $passed,
    'total'       => $total,
    'score'       => $total > 0 ? round($passed / $total * 100, 1) : 0,
    'duration_ms' => $duration,
]);
