<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$schema = [
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT)',
    'CREATE TABLE salas (id INTEGER PRIMARY KEY, nome TEXT, prof TEXT, alunos INTEGER, materia TEXT, icone TEXT, privacidade TEXT, descricao TEXT, criado_em TEXT)',
    'CREATE TABLE salas_usuarios (id INTEGER PRIMARY KEY, sala_id INTEGER, usuario_id INTEGER, status TEXT, entrou_em TEXT)',
    'CREATE TABLE ranking (id INTEGER PRIMARY KEY, usuario_id INTEGER, pontos INTEGER)',
    'CREATE TABLE biblioteca (id INTEGER PRIMARY KEY, titulo TEXT, autor TEXT, tipo TEXT, disponibilidade TEXT, status TEXT, criado_em TEXT)',
    'CREATE TABLE publicacoes (id INTEGER PRIMARY KEY, titulo TEXT, tipo TEXT, status TEXT, publicado_em TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY)',
    'CREATE TABLE emprestimos (id INTEGER PRIMARY KEY, usuario_id INTEGER, status TEXT)',
    'CREATE TABLE tentativas_atividade (id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, tipo TEXT, atividade_id INTEGER, categoria TEXT, respostas_corretas INTEGER, respostas_incorretas INTEGER, total_respostas INTEGER, percentual_acerto REAL, concluido INTEGER, criado_em TEXT)',
];

foreach ($schema as $statement) {
    $pdo->exec($statement);
}

require_once __DIR__ . '/../includes/data.php';

function assert_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true));
    }
}

$pdo->exec("INSERT INTO usuarios (id, nome_completo) VALUES (10, 'Conta nova'), (20, 'Com histórico'), (30, 'Sem atividades'), (40, 'Outro usuário'), (50, 'Um período')");
$insertAttempt = $pdo->prepare('INSERT INTO tentativas_atividade (usuario_id, tipo, atividade_id, categoria, respostas_corretas, respostas_incorretas, total_respostas, percentual_acerto, concluido, criado_em) VALUES (:usuario_id, :tipo, 1, :categoria, :acertos, :erros, :total, :percentual, :concluido, :criado_em)');
$attempts = [
    [20, 'quiz', 'Matemática', 2, 1, 3, 66.67, 1, '2026-07-12 10:00:00'],
    [20, 'exercicio', 'Português', 4, 0, 4, 100.00, 1, '2026-08-01 10:00:00'],
    [20, 'quiz', 'História', 1, 0, 1, 100.00, 0, '2026-09-01 10:00:00'],
    [20, 'quiz', 'Biologia', 2, 0, 3, 66.67, 1, '2026-09-02 10:00:00'],
    [30, 'quiz', 'Geografia', 0, 0, 0, 0.00, 1, '2026-09-03 10:00:00'],
    [40, 'quiz', 'Química', 9, 1, 10, 90.00, 1, '2026-08-15 10:00:00'],
    [50, 'quiz', 'Física', 1, 0, 1, 100.00, 1, '2026-09-04 10:00:00'],
];

foreach ($attempts as [$userId, $type, $category, $correct, $incorrect, $total, $percentage, $completed, $createdAt]) {
    $insertAttempt->execute([
        ':usuario_id' => $userId,
        ':tipo' => $type,
        ':categoria' => $category,
        ':acertos' => $correct,
        ':erros' => $incorrect,
        ':total' => $total,
        ':percentual' => $percentage,
        ':concluido' => $completed,
        ':criado_em' => $createdAt,
    ]);
}

$pdo->exec("INSERT INTO emprestimos (usuario_id, status) VALUES (20, 'devolvido'), (20, 'ativo'), (40, 'devolvido')");

$newUser = obter_dados_dashboard_usuario($pdo, 10);
assert_same(true, $newUser['historico_disponivel'], 'Conta nova deve consultar histórico');
assert_same(0, $newUser['total_tentativas'], 'Conta nova não deve receber tentativas demonstrativas');
assert_same([], $newUser['labels'], 'Conta nova não deve receber períodos fictícios');
assert_same(0, $newUser['emprestimos_devolvidos'], 'Conta nova não deve receber empréstimos de outro usuário');
assert_same(false, dashboard_pode_exibir_grafico($newUser), 'Conta sem histórico não deve exibir gráfico');

$historyUser = obter_dados_dashboard_usuario($pdo, 20);
assert_same(2, $historyUser['total_tentativas'], 'Somente tentativas concluídas e consistentes contam');
assert_same(6, $historyUser['total_acertos'], 'Acertos devem vir das tentativas válidas do usuário');
assert_same(1, $historyUser['total_erros'], 'Erros devem vir das tentativas válidas do usuário');
assert_same(7, $historyUser['total_respostas'], 'Total de respostas deve refletir registros válidos');
assert_same(1, $historyUser['quizzes_com_tentativa'], 'Contagem de respostas de quiz deve usar registros reais');
assert_same(1, $historyUser['exercicios_com_tentativa'], 'Contagem de respostas de exercício deve usar registros reais');
assert_same(2, count($historyUser['labels']), 'Gráfico deve conter somente períodos com registros reais');
assert_same(true, dashboard_pode_exibir_grafico($historyUser), 'Histórico em dois períodos deve permitir gráfico');
assert_same(1, $historyUser['emprestimos_devolvidos'], 'Empréstimos devem ser filtrados pelo usuário');

$inactiveUser = obter_dados_dashboard_usuario($pdo, 30);
assert_same(0, $inactiveUser['total_tentativas'], 'Tentativas não concluídas não devem contar');
assert_same([], $inactiveUser['labels'], 'Sem atividades concluídas, não deve haver dados de gráfico');
assert_same(false, dashboard_pode_exibir_grafico($inactiveUser), 'Tentativa incompleta não deve liberar gráfico');

$onePeriodUser = obter_dados_dashboard_usuario($pdo, 50);
assert_same(1, count($onePeriodUser['labels']), 'Um único período deve permanecer no resumo sem fabricar série');
assert_same(false, dashboard_pode_exibir_grafico($onePeriodUser), 'Um único período não deve exibir gráfico de evolução');

$beforeInsert = (int) $pdo->query('SELECT COUNT(*) FROM tentativas_atividade')->fetchColumn();
try {
    registrar_tentativa_atividade($pdo, 20, 'quiz', 1, 'Matemática', 0, 0, 0);
    throw new RuntimeException('Registro sem respostas deveria ser rejeitado.');
} catch (InvalidArgumentException) {
}
assert_same($beforeInsert, (int) $pdo->query('SELECT COUNT(*) FROM tentativas_atividade')->fetchColumn(), 'Tentativa inválida não deve ser persistida');

try {
    registrar_tentativa_atividade($pdo, 20, 'quiz', 1, 'Matemática', 1, 0, -1);
    throw new RuntimeException('Total negativo deveria ser rejeitado.');
} catch (InvalidArgumentException) {
}
assert_same($beforeInsert, (int) $pdo->query('SELECT COUNT(*) FROM tentativas_atividade')->fetchColumn(), 'Total negativo não deve ser persistido');

registrar_tentativa_atividade($pdo, 10, 'quiz', 1, 'Geografia', 1, 0, 1);
$newUserAfterAttempt = obter_dados_dashboard_usuario($pdo, 10);
assert_same(1, $newUserAfterAttempt['total_tentativas'], 'Tentativa válida deve persistir para o usuário informado');
assert_same(1, $newUserAfterAttempt['total_acertos'], 'Tentativa válida deve atualizar o agregado real');

echo "OK: usuários novos, histórico válido, tentativas incompletas, isolamento por usuário e persistência validados.\n";