<?php
declare(strict_types=1);

/*
 * Desenvolvimento mantém os padrões do XAMPP. Produção exige configuração
 * explícita e nunca cria o banco ou aplica o schema durante uma requisição.
 */
$readEnvironment = static function (string $name, ?string $default = null): ?string {
    $value = getenv($name);
    if ($value !== false) {
        return (string) $value;
    }
    return isset($_ENV[$name]) ? (string) $_ENV[$name] : $default;
};
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$hostWithoutPort = preg_replace('/:\d+$/', '', $httpHost) ?: $httpHost;
$defaultAppEnv = in_array($hostWithoutPort, ['localhost', '127.0.0.1', '::1'], true)
    ? 'development'
    : 'production';
$appEnv = strtolower((string) $readEnvironment('APP_ENV', $defaultAppEnv));
$hostValue = $readEnvironment('DB_HOST', $appEnv === 'development' ? '127.0.0.1' : null);
$dbValue = $readEnvironment('DB_NAME', $appEnv === 'development' ? 'sa_education' : null);
$userValue = $readEnvironment('DB_USER', $appEnv === 'development' ? 'root' : null);
$passValue = $readEnvironment('DB_PASS', $appEnv === 'development' ? '' : null);
$host = (string) $hostValue;
$db = (string) $dbValue;
$user = (string) $userValue;
$pass = (string) $passValue;
$port = (int) $readEnvironment('DB_PORT', '3306');
$charset = 'utf8mb4';

function database_configuration_failure(string $message, int $statusCode = 500): void {
    error_log('[S.A Education][database_configuration] ' . $message);
    if (defined('SA_API_REQUEST')) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        http_response_code($statusCode);
        echo json_encode([
            'data' => null,
            'error' => [
                'code' => $statusCode === 503 ? 'DATABASE_UNAVAILABLE' : 'INTERNAL_ERROR',
                'message' => 'Não foi possível iniciar o serviço agora. Tente novamente mais tarde.',
            ],
            'meta' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    http_response_code($statusCode);
    exit('Não foi possível iniciar o serviço agora. Tente novamente mais tarde.');
}

function database_request_failure(PDOException $exception, string $context): array {
    error_log('[S.A Education][' . $context . '] ' . $exception->getMessage());
    $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);
    if (in_array($driverCode, [1054, 1146], true) || in_array($sqlState, ['42S02', '42S22'], true)) {
        return [
            'status' => 503,
            'message' => 'Este conteúdo ainda não está disponível. Tente novamente mais tarde.',
        ];
    }
    if (str_starts_with($sqlState, '08') || in_array($driverCode, [1045, 1049, 2002, 2003], true)) {
        return [
            'status' => 503,
            'message' => 'Não foi possível conectar ao serviço agora. Tente novamente em instantes.',
        ];
    }
    return [
        'status' => 500,
        'message' => 'Não foi possível carregar este conteúdo agora. Tente novamente mais tarde.',
    ];
}

if (!in_array($appEnv, ['development', 'test', 'production'], true)) {
    database_configuration_failure('APP_ENV deve ser development, test ou production.');
}
if (!defined('APP_ENVIRONMENT')) {
    define('APP_ENVIRONMENT', $appEnv);
}
if (
    $appEnv === 'production'
    && ($userValue === null || $user === '' || $dbValue === null || $hostValue === null || $passValue === null || $port < 1)
) {
    database_configuration_failure('A configuração de conexão com o banco não está completa.');
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $db) || !preg_match('/^[A-Za-z0-9.-]+$/', $host) || $port < 1 || $port > 65535) {
    database_configuration_failure('A configuração de conexão com o banco é inválida.');
}

if (!extension_loaded('pdo_mysql')) {
    database_configuration_failure('Extensão PDO MySQL não está habilitada no PHP.');
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function create_database_if_needed(string $host, int $port, string $user, string $pass, string $db): void {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
        throw new InvalidArgumentException('Identificador de banco de dados inválido.');
    }
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function load_database_schema(PDO $pdo, string $baseDir): void {
    $candidates = [
        $baseDir . '/db/sa_education.sql',
        $baseDir . '/db/schema.sql',
    ];

    $schemaPath = null;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $schemaPath = $candidate;
            break;
        }
    }

    if ($schemaPath === null) {
        return;
    }

    $schema = file_get_contents($schemaPath);
    if ($schema === false || $schema === '') {
        return;
    }

    $tableCheck = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if ($tableCheck !== false && $tableCheck->fetch() !== false) {
        $tableCheck->closeCursor();
        return;
    }

    if ($tableCheck !== false) {
        $tableCheck->closeCursor();
    }

    $statements = array_filter(
        array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema)),
        static fn (string $statement): bool => $statement !== ''
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $needsDatabase = (int) ($e->errorInfo[1] ?? 0) === 1049;

    if ($needsDatabase && $appEnv === 'development' && $readEnvironment('DB_AUTO_CREATE', 'true') === 'true') {
        try {
            create_database_if_needed($host, (int) $port, $user, $pass, $db);
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset={$charset}", $user, $pass, $options);
        } catch (PDOException $createError) {
            error_log('[S.A Education][database_create][PDO] ' . $createError->getMessage());
            database_configuration_failure('Não foi possível inicializar o banco de dados. Verifique a configuração local.', 503);
        }
    } else {
        error_log('[S.A Education][database_connect][PDO] ' . $e->getMessage());
        database_configuration_failure('Não foi possível conectar ao banco de dados. Verifique a configuração do ambiente.', 503);
    }
}

if ($appEnv === 'development' && $readEnvironment('DB_AUTO_MIGRATE', 'true') === 'true') {
    load_database_schema($pdo, __DIR__);
}

unset($schemaPath, $schema, $statements, $tableCheck, $readEnvironment, $appEnv, $hostValue, $dbValue, $userValue, $passValue, $httpHost, $hostWithoutPort, $defaultAppEnv);
