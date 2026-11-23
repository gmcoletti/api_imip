<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80500) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Esta API exige PHP 8.5 ou superior. Versão atual: ' . PHP_VERSION,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Carrega variáveis do arquivo .env para o ambiente.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo .env não encontrado em: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('Não foi possível ler o arquivo .env.');
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove aspas simples ou duplas nas pontas
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

/**
 * Retorna uma variável de ambiente obrigatória.
 */
function env(string $key, ?string $default = null): string
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Variável de ambiente obrigatória não definida: {$key}");
    }

    return (string) $value;
}

/**
 * Cria conexão PDO com PostgreSQL.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST');
    $port = env('DB_PORT', '5432');
    $dbname = env('DB_NAME');
    $user = env('DB_USER');
    $password = env('DB_PASSWORD');

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/**
 * Resposta JSON padronizada.
 */
function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Extrai o token Bearer do header Authorization.
 */
function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if ($header === null && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if ($header === null || $header === '') {
        return null;
    }

    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Valida o Bearer token configurado no .env.
 */
function requireAuth(): void
{
    $expected = env('API_BEARER_TOKEN');
    $token = getBearerToken();

    if ($token === null || !hash_equals($expected, $token)) {
        jsonResponse([
            'success' => false,
            'error' => 'Não autorizado. Informe o header Authorization: Bearer <token>.',
        ], 401);
    }
}
