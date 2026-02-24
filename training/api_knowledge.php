<?php
// Training API — CRUD de blocos de conhecimento
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$KNOWLEDGE_FILE = __DIR__ . '/knowledge.json';

function loadKnowledge($file) {
    if (!file_exists($file)) return ['blocks' => []];
    return json_decode(file_get_contents($file), true) ?? ['blocks' => []];
}
function saveKnowledge($file, $data) {
    $data['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($method === 'GET' && $action === 'list') {
    echo json_encode(loadKnowledge($KNOWLEDGE_FILE));
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $knowledge = loadKnowledge($KNOWLEDGE_FILE);

    if ($action === 'save_block') {
        $block = $body['block'];
        $found = false;
        foreach ($knowledge['blocks'] as &$b) {
            if ($b['id'] === $block['id']) { $b = $block; $found = true; break; }
        }
        if (!$found) $knowledge['blocks'][] = $block;
        saveKnowledge($KNOWLEDGE_FILE, $knowledge);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete_block') {
        $id = $body['id'];
        $knowledge['blocks'] = array_values(array_filter($knowledge['blocks'], fn($b) => $b['id'] !== $id));
        saveKnowledge($KNOWLEDGE_FILE, $knowledge);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'toggle_block') {
        $id = $body['id'];
        foreach ($knowledge['blocks'] as &$b) {
            if ($b['id'] === $id) { $b['active'] = !$b['active']; break; }
        }
        saveKnowledge($KNOWLEDGE_FILE, $knowledge);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'reorder') {
        $ids = $body['ids'];
        $indexed = array_column($knowledge['blocks'], null, 'id');
        $knowledge['blocks'] = array_values(array_filter(array_map(fn($id) => $indexed[$id] ?? null, $ids)));
        saveKnowledge($KNOWLEDGE_FILE, $knowledge);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save_meta') {
        $name = trim($body['agent_name'] ?? '');
        if ($name === '') { echo json_encode(['ok' => false, 'error' => 'Nome não pode ser vazio.']); exit; }
        $knowledge['agent_name'] = $name;
        saveKnowledge($KNOWLEDGE_FILE, $knowledge);
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
