<?php
declare(strict_types=1);

/*
 * Configuração do banco de dados.
 * Altere estes valores conforme seu XAMPP/WAMP/Laragon/hospedagem.
 */
$host = 'localhost';
$db   = 'sa_education';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique a configuração em conexao.php.');
}
