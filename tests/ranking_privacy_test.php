<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ranking.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT, username TEXT, ranking_publico INTEGER NOT NULL DEFAULT 0)');
$pdo->exec('CREATE TABLE ranking (id INTEGER PRIMARY KEY, usuario_id INTEGER, pontos INTEGER)');
$pdo->exec(
    "INSERT INTO usuarios (id, nome_completo, username, ranking_publico) VALUES
        (1, 'Nome Completo Público Somente com Consentimento', 'ana_estuda', 1),
        (2, 'Pessoa Privada', 'pessoa_privada', 0)"
);
$pdo->exec('INSERT INTO ranking (id, usuario_id, pontos) VALUES (1, 1, 20), (2, 1, 15), (3, 2, 999)');

$ranking = obter_ranking_publico($pdo);
if (count($ranking) !== 1 || $ranking[0]['nome'] !== 'ana_estuda' || (int) $ranking[0]['pontos'] !== 20) {
    throw new RuntimeException('O ranking deve incluir apenas opt-in e não deve expor nomes completos.');
}
if (isset($ranking[0]['email']) || isset($ranking[0]['nome_completo']) || (int) $ranking[0]['pos'] !== 1) {
    throw new RuntimeException('A resposta do ranking deve conter apenas dados públicos mínimos.');
}

try {
    obter_ranking_publico($pdo, 101);
    throw new RuntimeException('Limite de ranking acima de 100 deveria ser rejeitado.');
} catch (InvalidArgumentException) {
}

echo "OK: ranking público aplica opt-in, username público e dados mínimos.\n";
