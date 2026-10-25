<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/library_lending.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE biblioteca (
        id INTEGER PRIMARY KEY, titulo TEXT, disponibilidade TEXT, revisao_status TEXT,
        status TEXT, direitos_status TEXT, direitos_declarados INTEGER, instituicao_id INTEGER
    )'
);
$pdo->exec(
    'CREATE TABLE instituicoes (
        id INTEGER PRIMARY KEY, usuario_id INTEGER, nome TEXT, validation_status TEXT
    )'
);
$pdo->exec(
    'CREATE TABLE library_copies (
        id INTEGER PRIMARY KEY AUTOINCREMENT, material_id INTEGER, institution_id INTEGER,
        copy_code TEXT, format TEXT, status TEXT, distribution_authorized INTEGER,
        access_rules TEXT, authorization_evidence TEXT
    )'
);
$pdo->exec(
    'CREATE TABLE library_loans (
        id INTEGER PRIMARY KEY AUTOINCREMENT, copy_id INTEGER, user_id INTEGER,
        responsible_user_id INTEGER, status TEXT, borrowed_at TEXT, due_at TEXT, returned_at TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->exec("INSERT INTO biblioteca VALUES
    (1, 'Livro autorizado', 'leitura_online', 'approved', 'ativo', 'licensed', 1, 7),
    (2, 'Livro sem direitos', 'leitura_online', 'approved', 'ativo', 'unknown', 1, 7)");
$pdo->exec("INSERT INTO instituicoes VALUES (7, 70, 'Instituição de teste', 'validated')");
$institution = ['id' => 7, 'user_id' => 70, 'name' => 'Instituição de teste'];

function assert_library_lending(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$physicalId = library_lending_create_copy($pdo, $institution, [
    'material_id' => '1',
    'copy_code' => 'FIS-001',
    'format' => 'physical',
    'access_rules' => 'Retirada na biblioteca.',
]);
$physical = $pdo->query('SELECT format, distribution_authorized FROM library_copies WHERE id = ' . $physicalId)->fetch();
assert_library_lending($physical['format'] === 'physical' && (int) $physical['distribution_authorized'] === 0, 'Exemplar físico deve ser independente da autorização de distribuição digital.');
$requestId = library_lending_request($pdo, 15, $physicalId);
$request = $pdo->query('SELECT status, borrowed_at, due_at FROM library_loans WHERE id = ' . $requestId)->fetch();
assert_library_lending($request['status'] === 'requested' && $request['borrowed_at'] === null && $request['due_at'] === null, 'Uma solicitação não deve ser confundida com empréstimo já iniciado.');
library_lending_decide($pdo, $institution, $requestId, 'issue', 21);
$issued = $pdo->query('SELECT status, borrowed_at, due_at FROM library_loans WHERE id = ' . $requestId)->fetch();
assert_library_lending($issued['status'] === 'active' && $issued['borrowed_at'] !== null && $issued['due_at'] !== null, 'A aprovação deve iniciar o período e registrar a data de devolução.');
$copyStatus = $pdo->query('SELECT status FROM library_copies WHERE id = ' . $physicalId)->fetchColumn();
assert_library_lending($copyStatus === 'on_loan', 'Exemplar aprovado deve ficar indisponível durante o empréstimo.');
library_lending_decide($pdo, $institution, $requestId, 'return');
assert_library_lending($pdo->query('SELECT status FROM library_loans WHERE id = ' . $requestId)->fetchColumn() === 'returned', 'A devolução deve encerrar o empréstimo.');
assert_library_lending($pdo->query('SELECT status FROM library_copies WHERE id = ' . $physicalId)->fetchColumn() === 'available', 'A devolução deve liberar o exemplar.');

$rejectedId = library_lending_request($pdo, 16, $physicalId);
library_lending_decide($pdo, $institution, $rejectedId, 'reject');
assert_library_lending($pdo->query('SELECT status FROM library_loans WHERE id = ' . $rejectedId)->fetchColumn() === 'canceled', 'A instituição deve poder recusar uma solicitação pendente.');

try {
    library_lending_create_copy($pdo, $institution, [
        'material_id' => '1',
        'copy_code' => 'DIG-INVALID',
        'format' => 'digital',
    ]);
    throw new RuntimeException('Exemplar digital sem evidência deve ser recusado.');
} catch (InvalidArgumentException) {
}

try {
    library_lending_create_copy($pdo, $institution, [
        'material_id' => '2',
        'copy_code' => 'FIS-RESTRITO',
        'format' => 'physical',
    ]);
    throw new RuntimeException('Material sem direitos confirmados não deve ser emprestado.');
} catch (DomainException) {
}

$digitalId = library_lending_create_copy($pdo, $institution, [
    'material_id' => '1',
    'copy_code' => 'DIG-001',
    'format' => 'digital',
    'distribution_authorized' => '1',
    'authorization_evidence' => 'Licença institucional registrada.',
]);
$digitalRequest = library_lending_request($pdo, 17, $digitalId);
assert_library_lending($digitalRequest > 0, 'Exemplar digital autorizado deve aceitar solicitação.');

echo "OK: solicitação, autorização digital, decisão institucional, prazos, devolução e proteção por direitos.\n";
