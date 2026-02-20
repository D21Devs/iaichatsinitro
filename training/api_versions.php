<?php
// Training API — Versionamento de prompts
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$VERSIONS_DIR    = __DIR__ . '/versions';
$KNOWLEDGE_FILE  = __DIR__ . '/knowledge.json';

if (!is_dir($VERSIONS_DIR)) mkdir($VERSIONS_DIR, 0755, true);

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $files = glob($VERSIONS_DIR . '/*.json');
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $versions = array_map(function($f) {
        $data = json_decode(file_get_contents($f), true);
        return [
            'file'    => basename($f),
            'score'   => $data['score'] ?? null,
            'label'   => $data['label'] ?? basename($f),
            'saved_at'=> $data['saved_at'] ?? '',
            'total'   => $data['total'] ?? null,
            'passed'  => $data['passed'] ?? null,
        ];
    }, $files);
    echo json_encode($versions);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if ($action === 'save') {
        $score   = $body['score'] ?? null;
        $label   = $body['label'] ?? ('v_' . date('Ymd_His'));
        $fname   = $VERSIONS_DIR . '/' . date('Ymd_His') . '_' . round($score ?? 0) . 'pct.json';
        $knowledge = json_decode(file_get_contents($KNOWLEDGE_FILE), true);
        $knowledge['score']    = $score;
        $knowledge['label']    = $label;
        $knowledge['saved_at'] = date('Y-m-d H:i:s');
        $knowledge['total']    = $body['total'] ?? null;
        $knowledge['passed']   = $body['passed'] ?? null;
        file_put_contents($fname, json_encode($knowledge, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok' => true, 'file' => basename($fname)]);
        exit;
    }

    if ($action === 'restore') {
        $file = $body['file'];
        $src  = $VERSIONS_DIR . '/' . basename($file);
        if (!file_exists($src)) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }
        copy($src, $KNOWLEDGE_FILE);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        $file = $VERSIONS_DIR . '/' . basename($body['file']);
        if (file_exists($file)) unlink($file);
        echo json_encode(['ok' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
