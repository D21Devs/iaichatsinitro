<?php
/**
 * Training API — Executa suite de testes via SSE (Server-Sent Events)
 * GET /training/api_run.php?category=vitimas   (ou "all")
 *
 * Cada evento SSE tem formato: data: <json>\n\n
 * Tipos: { type: "progress", index, total, pass, question, response, fails, action_got, action_exp }
 *        { type: "done", score, passed, total, by_category, duration_ms }
 *        { type: "error", message }
 */

error_reporting(0);
ini_set('display_errors', '0');

// Limpa buffers ativos (output_buffering do php.ini em hosting compartilhado)
while (ob_get_level() > 0) { ob_end_clean(); }
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

// SSE headers
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

// ── Carrega config e testes ──────────────────────────────────────────────────
$ROOT = dirname(__DIR__);
require_once $ROOT . '/api/config.php';

$KNOWLEDGE_FILE = __DIR__ . '/knowledge.json';
$TESTS_FILE     = __DIR__ . '/tests.json';
$DEEPSEEK_KEY   = 'sk-c5041edcb15b48c595aa9681568ee956';
$DEEPSEEK_URL   = 'https://api.deepseek.com/v1/chat/completions';

// ── Monta system prompt a partir dos blocos ativos ───────────────────────────
function buildPrompt(string $knowledgeFile): string {
    $knowledge = json_decode(file_get_contents($knowledgeFile), true);
    $sections  = [];
    foreach ($knowledge['blocks'] as $block) {
        if (!$block['active']) continue;
        $title = strtoupper($block['name']);
        $sections[] = "════════════════════════════════\n{$title}\n════════════════════════════════\n{$block['content']}";
    }
    return implode("\n\n", $sections);
}

// ── Chama OpenAI (agente) ────────────────────────────────────────────────────
function callAgent(string $prompt, string $question): array {
    $payload = json_encode([
        'model'           => OPENAI_MODEL,
        'messages'        => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user',   'content' => $question],
        ],
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

    if ($err)        return ['_error' => 'cURL: ' . $err];
    if ($code !== 200) return ['_error' => "HTTP $code: " . substr($raw, 0, 200)];
    $dec = json_decode($raw, true);
    $content = $dec['choices'][0]['message']['content'] ?? '{}';
    $parsed  = json_decode($content, true);
    return $parsed ?: ['_error' => 'JSON inválido: ' . $content];
}

// ── Chama DeepSeek (juiz LLM) ────────────────────────────────────────────────
function callJudge(string $question, string $response, ?string $expectedAction, string $actualAction): array {
    global $DEEPSEEK_KEY, $DEEPSEEK_URL;

    $judgePrompt = "Você é um avaliador de qualidade de chatbot de atendimento de sinistros.\n"
        . "Avalie a resposta do agente de forma objetiva.\n\n"
        . "Pergunta do usuário: \"{$question}\"\n"
        . "Resposta do agente: \"{$response}\"\n"
        . "Action esperada: " . json_encode($expectedAction) . "\n"
        . "Action obtida: " . json_encode($actualAction) . "\n\n"
        . "Avalie em JSON com os campos:\n"
        . "- score: 0 a 10 (qualidade geral da resposta)\n"
        . "- empathy: 0 a 10 (empatia e tom)\n"
        . "- accuracy: 0 a 10 (precisão da informação)\n"
        . "- clarity: 0 a 10 (clareza)\n"
        . "- issues: array de strings com problemas encontrados (vazio se não houver)\n"
        . "- suggestion: string com sugestão de melhoria (ou null)\n"
        . "Responda SOMENTE em JSON válido.";

    $payload = json_encode([
        'model'    => 'deepseek-chat',
        'messages' => [['role' => 'user', 'content' => $judgePrompt]],
        'max_tokens' => 300,
        'temperature' => 0.1,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($DEEPSEEK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
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

    if ($code !== 200) return ['score' => null, 'issues' => ["DeepSeek HTTP $code"], 'suggestion' => null];
    $dec     = json_decode($raw, true);
    $content = $dec['choices'][0]['message']['content'] ?? '{}';
    return json_decode($content, true) ?: ['score' => null, 'issues' => ['parse error'], 'suggestion' => null];
}

// ── Avalia ação e keywords ───────────────────────────────────────────────────
function evaluateBasic(array $resp, ?string $expAction, array $mustHave, array $mustNot): array {
    $fails = [];
    if (isset($resp['_error'])) return ['pass' => false, 'fails' => ['API error: ' . $resp['_error']]];
    $msg    = strtolower($resp['message'] ?? '');
    $action = $resp['action'] ?? null;
    if (empty(trim($msg)))        $fails[] = 'Mensagem vazia';
    if ($action !== $expAction)   $fails[] = "Action: esperado=" . json_encode($expAction) . " obtido=" . json_encode($action);
    if (!empty($mustHave)) {
        $found = false;
        foreach ($mustHave as $kw) { if (str_contains($msg, strtolower($kw))) { $found = true; break; } }
        if (!$found) $fails[] = 'Sem keyword: [' . implode('|', $mustHave) . ']';
    }
    foreach ($mustNot as $kw) {
        if (str_contains($msg, strtolower($kw))) $fails[] = "Proibido: \"$kw\"";
    }
    return ['pass' => empty($fails), 'fails' => $fails];
}

// ── MAIN ─────────────────────────────────────────────────────────────────────
$filterCategory = $_GET['category'] ?? 'all';
$useJudge       = ($_GET['judge'] ?? '0') === '1';

if (!file_exists($TESTS_FILE)) {
    sse(['type' => 'error', 'message' => 'tests.json não encontrado. Importe os testes primeiro.']);
    exit;
}

$testsData = json_decode(file_get_contents($TESTS_FILE), true);
$allTests  = $testsData['tests'] ?? [];

if ($filterCategory !== 'all') {
    $allTests = array_values(array_filter($allTests, fn($t) => ($t['category'] ?? '') === $filterCategory));
}

if (empty($allTests)) {
    sse(['type' => 'error', 'message' => 'Nenhum teste encontrado' . ($filterCategory !== 'all' ? " na categoria '$filterCategory'" : '') . '.']);
    exit;
}

$systemPrompt = buildPrompt($KNOWLEDGE_FILE);
$total        = count($allTests);
$passed       = 0;
$byCategory   = [];
$startTime    = microtime(true);

sse(['type' => 'start', 'total' => $total, 'category' => $filterCategory, 'judge' => $useJudge]);

foreach ($allTests as $i => $test) {
    $question   = $test['question'] ?? '';
    $expAction  = $test['expected_action'] ?? null;
    $mustHave   = $test['keywords'] ?? [];
    $mustNot    = $test['forbidden'] ?? [];
    $category   = $test['category'] ?? 'outros';

    $resp    = callAgent($systemPrompt, $question);
    $basic   = evaluateBasic($resp, $expAction, $mustHave, $mustNot);
    $pass    = $basic['pass'];
    $message = $resp['message'] ?? '';
    $action  = $resp['action'] ?? null;

    // Avaliação por LLM judge (apenas em falhas ou se flag ativa)
    $judgeResult = null;
    if ($useJudge && (!$pass || $i % 10 === 0)) {
        $judgeResult = callJudge($question, $message, $expAction, $action);
    }

    if ($pass) $passed++;
    if (!isset($byCategory[$category])) $byCategory[$category] = ['passed' => 0, 'total' => 0];
    $byCategory[$category]['total']++;
    if ($pass) $byCategory[$category]['passed']++;

    sse([
        'type'        => 'progress',
        'index'       => $i + 1,
        'total'       => $total,
        'pass'        => $pass,
        'question'    => $question,
        'response'    => mb_substr($message, 0, 150),
        'action_got'  => $action,
        'action_exp'  => $expAction,
        'fails'       => $basic['fails'],
        'category'    => $category,
        'judge'       => $judgeResult,
    ]);

    usleep(300000); // 300ms entre chamadas
}

$duration = round((microtime(true) - $startTime) * 1000);
$score    = $total > 0 ? round($passed / $total * 100, 1) : 0;

// Score por categoria em %
$catScores = [];
foreach ($byCategory as $cat => $v) {
    $catScores[$cat] = ['passed' => $v['passed'], 'total' => $v['total'], 'score' => $total > 0 ? round($v['passed'] / $v['total'] * 100, 1) : 0];
}

sse([
    'type'        => 'done',
    'score'       => $score,
    'passed'      => $passed,
    'total'       => $total,
    'by_category' => $catScores,
    'duration_ms' => $duration,
]);
