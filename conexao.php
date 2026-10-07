<?php
declare(strict_types=1);

/*
 * Configuração do banco de dados.
 * Usa os valores padrão do XAMPP e ainda aceita variáveis de ambiente.
 * O projeto passa a considerar o banco como fonte única de verdade:
 * não há usuários ou dados padrão inseridos fora do banco.
 */
$host = getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?? '127.0.0.1';
$db   = getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?? 'sa_education';
$user = getenv('DB_USER') ?: $_ENV['DB_USER'] ?? 'root';
$pass = getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?? '';
$port = getenv('DB_PORT') ?: $_ENV['DB_PORT'] ?? '3306';
$charset = 'utf8mb4';

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    exit('Extensão PDO MySQL não está habilitada no PHP. Ative a extensão pdo_mysql no arquivo php.ini.');
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function create_database_if_needed(string $host, int $port, string $user, string $pass, string $db): void {
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
    $message = $e->getMessage();
    $needsDatabase = str_contains($message, 'Unknown database') || str_contains($message, '1049') || str_contains($message, 'Base') || str_contains($message, 'database');

    if ($needsDatabase) {
        try {
            create_database_if_needed($host, (int) $port, $user, $pass, $db);
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset={$charset}", $user, $pass, $options);
        } catch (PDOException $createError) {
            http_response_code(500);
            exit('Não foi possível criar o banco de dados automaticamente. Verifique o acesso do MySQL no XAMPP e as credenciais em conexao.php.');
        }
    } else {
        http_response_code(500);
        exit('Não foi possível conectar ao banco de dados. Verifique se o MySQL está rodando e se as credenciais estão corretas.');
    }
}

load_database_schema($pdo, __DIR__);

unset($schemaPath, $schema, $statements, $tableCheck);
