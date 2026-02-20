<?php
// Training API — CRUD de casos de teste
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$TESTS_FILE = __DIR__ . '/tests.json';

// Inicializa tests.json a partir do test_agent.php se não existir
if (!file_exists($TESTS_FILE)) {
    // Carrega os testes hardcoded do test_agent.php e converte para JSON
    $categories = [
        'saudacoes'     => 'Saudações e Primeiro Contato',
        'abertura'      => 'Intenção de Abrir Sinistro',
        'coleta_data'   => 'Coleta: Data',
        'coleta_local'  => 'Coleta: Local',
        'coleta_veiculo'=> 'Coleta: Situação do Veículo',
        'coleta_terc'   => 'Coleta: Terceiros',
        'vitimas'       => 'Vítimas — Ação Crítica',
        'documentacao'  => 'Documentação',
        'status'        => 'Consulta de Status',
        'pendencias'    => 'Pendências Documentais',
        'cota'          => 'Cota de Participação',
        'atendente'     => 'Pedido de Atendente Humano',
        'faq'           => 'FAQ Metis Brasil',
        'escopo'        => 'Fora do Escopo',
        'reclamacoes'   => 'Reclamações e Edge Cases',
    ];
    file_put_contents($TESTS_FILE, json_encode(['categories' => $categories, 'tests' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadTests($f) { return json_decode(file_get_contents($f), true); }
function saveTests($f, $d) { file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    echo json_encode(loadTests($TESTS_FILE));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $data = loadTests($TESTS_FILE);

    if ($action === 'save_test') {
        $test = $body['test'];
        if (empty($test['id'])) $test['id'] = uniqid('t_');
        $found = false;
        foreach ($data['tests'] as &$t) {
            if ($t['id'] === $test['id']) { $t = $test; $found = true; break; }
        }
        if (!$found) $data['tests'][] = $test;
        saveTests($TESTS_FILE, $data);
        echo json_encode(['ok' => true, 'test' => $test]);
        exit;
    }

    if ($action === 'delete_test') {
        $id = $body['id'];
        $data['tests'] = array_values(array_filter($data['tests'], fn($t) => $t['id'] !== $id));
        saveTests($TESTS_FILE, $data);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'import') {
        // Importa array de testes em lote
        foreach ($body['tests'] as $test) {
            if (empty($test['id'])) $test['id'] = uniqid('t_');
            $found = false;
            foreach ($data['tests'] as &$t) {
                if ($t['id'] === $test['id']) { $t = $test; $found = true; break; }
            }
            if (!$found) $data['tests'][] = $test;
        }
        saveTests($TESTS_FILE, $data);
        echo json_encode(['ok' => true, 'imported' => count($body['tests'])]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Ação inválida']);
