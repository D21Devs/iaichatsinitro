<?php
// Training API — CRUD de cenários conversacionais
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$FILE = __DIR__ . '/scenarios.json';
if (!file_exists($FILE)) file_put_contents($FILE, json_encode(['scenarios' => []], JSON_PRETTY_PRINT));

function load($f) { return json_decode(file_get_contents($f), true); }
function save($f, $d) { file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    echo json_encode(load($FILE));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $data = load($FILE);

    if ($action === 'save') {
        $sc = $body['scenario'];
        if (empty($sc['id'])) $sc['id'] = 'scen_' . uniqid();
        $found = false;
        foreach ($data['scenarios'] as &$s) {
            if ($s['id'] === $sc['id']) { $s = $sc; $found = true; break; }
        }
        if (!$found) $data['scenarios'][] = $sc;
        save($FILE, $data);
        echo json_encode(['ok' => true, 'scenario' => $sc]);
        exit;
    }

    if ($action === 'delete') {
        $id = $body['id'];
        $data['scenarios'] = array_values(array_filter($data['scenarios'], fn($s) => $s['id'] !== $id));
        save($FILE, $data);
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
