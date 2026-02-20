<?php
/**
 * Training API — CRUD para Personas de Simulação
 * GET  ?action=list
 * POST ?action=save_persona   body: {persona}
 * POST ?action=delete_persona body: {id}
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$FILE = __DIR__ . '/personas.json';

function readPersonas(string $file): array {
    if (!file_exists($file)) return ['personas' => []];
    $d = json_decode(file_get_contents($file), true);
    return $d ?: ['personas' => []];
}

function writePersonas(string $file, array $data): void {
    $data['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'list') {
    echo json_encode(readPersonas($FILE));
    exit;
}

if ($action === 'save_persona') {
    $p = $body['persona'] ?? null;
    if (!$p || empty($p['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Persona inválida']);
        exit;
    }
    $data = readPersonas($FILE);
    $idx  = null;
    foreach ($data['personas'] as $i => $x) {
        if ($x['id'] === ($p['id'] ?? '')) { $idx = $i; break; }
    }
    if ($idx !== null) {
        $data['personas'][$idx] = $p;
    } else {
        $p['id'] = 'p_' . time() . '_' . rand(100, 999);
        $data['personas'][] = $p;
    }
    writePersonas($FILE, $data);
    echo json_encode(['ok' => true, 'persona' => $p]);
    exit;
}

if ($action === 'delete_persona') {
    $id   = $body['id'] ?? '';
    $data = readPersonas($FILE);
    $data['personas'] = array_values(array_filter($data['personas'], fn($p) => $p['id'] !== $id));
    writePersonas($FILE, $data);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
