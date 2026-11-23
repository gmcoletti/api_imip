<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

loadEnv(dirname(__DIR__) . '/.env');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    jsonResponse([
        'success' => false,
        'error' => 'Método não permitido. Use GET ou POST.',
    ], 405);
}

requireAuth();

$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    if (is_array($json)) {
        $input = $json;
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

$numeroAtendimento = $input['numero_atendimento'] ?? $input['numeroAtendimento'] ?? null;

if ($numeroAtendimento === null || $numeroAtendimento === '') {
    jsonResponse([
        'success' => false,
        'error' => 'Informe o parâmetro numero_atendimento.',
    ], 400);
}

$numeroAtendimento = trim((string) $numeroAtendimento);
$sql = env('SQL');

if (!str_contains($sql, ':numero_atendimento')) {
    jsonResponse([
        'success' => false,
        'error' => 'A variável SQL no .env deve conter o placeholder :numero_atendimento.',
    ], 500);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':numero_atendimento', $numeroAtendimento);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'numero_atendimento' => $numeroAtendimento,
        'total' => count($rows),
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Erro ao consultar o banco de dados.',
        'detail' => $e->getMessage(),
    ], 500);
}
