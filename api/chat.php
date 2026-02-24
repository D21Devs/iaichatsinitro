<?php
// ============================================================
// Metis Brasil — Endpoint de Chat
// POST /sinistro/api/chat.php
// Body: {"message": "...", "session_id": "...", "history": [...]}
// history (opcional): array [{role, content}] enviado pelo chat full-page
//   → se presente, usado como contexto direto (contexto 100% persistido)
//   → se ausente, cai para PHP session (compat. com widget)
// ============================================================

// Suprime warnings/notices para não contaminar o JSON
error_reporting(0);
ini_set('display_errors', '0');
ob_start(); // buffer: garante que nada vaze antes dos headers

require_once __DIR__ . '/config.php';

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

// Lê e valida o body
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

$userMessage = trim($input['message'] ?? '');
$sessionId   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['session_id'] ?? '');

if (empty($userMessage) || empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros inválidos.']);
    exit;
}

// Limita tamanho da mensagem (evita abuso)
if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}

// ---- Gerenciamento de histórico ----
// Se o cliente enviou `history` (chat full-page), usa diretamente — contexto real persistido.
// Caso contrário, cai para PHP session (compatibilidade com o widget).
$clientHistory = isset($input['history']) && is_array($input['history']) ? $input['history'] : null;
$useSession    = ($clientHistory === null);

if (!$useSession) {
    // Filtra e sanitiza o histórico enviado pelo cliente
    $history = array_values(array_filter($clientHistory, function ($m) {
        return in_array($m['role'] ?? '', ['user', 'assistant'], true) && !empty($m['content']);
    }));
    // Limita a 40 mensagens para não estourar tokens
    if (count($history) > 40) {
        $history = array_slice($history, -40);
    }
} else {
    // Fallback: PHP session (widget)
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    session_id($sessionId);
    session_start();

    if (!isset($_SESSION['history']) || !is_array($_SESSION['history'])) {
        $_SESSION['history'] = [];
    }
    $_SESSION['history'][] = ['role' => 'user', 'content' => $userMessage];
    if (count($_SESSION['history']) > 30) {
        $_SESSION['history'] = array_slice($_SESSION['history'], -30);
    }
    $history = $_SESSION['history'];
}

// ---- Monta payload para OpenAI ----
$messages = array_merge(
    [['role' => 'system', 'content' => SYSTEM_PROMPT]],
    $history
);

// ---- Chama OpenAI ----
$apiResponse = callOpenAI($messages);

if (isset($apiResponse['error'])) {
    if ($useSession) session_write_close();
    ob_clean();
    http_response_code(502);
    echo json_encode([
        'message' => 'Erro: ' . $apiResponse['error'],
        'action'  => null,
        'url'     => null,
        'number'  => null
    ]);
    exit;
}

$aiContent = $apiResponse['choices'][0]['message']['content'] ?? '{}';

// Salva resposta no histórico (apenas modo session)
if ($useSession) {
    $_SESSION['history'][] = ['role' => 'assistant', 'content' => $aiContent];
    session_write_close();
}

// ---- Parse e retorno ----
$parsed = json_decode($aiContent, true);

ob_clean(); // descarta qualquer lixo antes de enviar

if (!$parsed || !isset($parsed['message'])) {
    echo json_encode([
        'message' => strip_tags($aiContent),
        'action'  => null,
        'url'     => null,
        'number'  => null
    ]);
    exit;
}

$response = [
    'message' => (string) ($parsed['message'] ?? ''),
    'action'  => $parsed['action'] ?? null,
    'url'     => $parsed['url'] ?? null,
    'number'  => $parsed['number'] ?? null,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);

// ============================================================
// Função: chamada à API OpenAI
// ============================================================
function callOpenAI(array $messages): array
{
    $payload = json_encode([
        'model'           => OPENAI_MODEL,
        'messages'        => $messages,
        'max_tokens'      => OPENAI_MAX_TOKENS,
        'temperature'     => OPENAI_TEMPERATURE,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_POSTFIELDS      => $payload,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => false,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'cURL: ' . $curlErr];
    }

    if ($httpCode !== 200) {
        return ['error' => 'OpenAI HTTP ' . $httpCode . ': ' . $result];
    }

    $decoded = json_decode($result, true);
    if (!$decoded) {
        return ['error' => 'Resposta inválida da API'];
    }

    return $decoded;
}
