<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, validation_status TEXT, tipo TEXT, natureza TEXT)',
    'CREATE TABLE institution_members (id INTEGER PRIMARY KEY, institution_id INTEGER, user_id INTEGER, status TEXT)',
    'CREATE TABLE product_plans (code TEXT PRIMARY KEY, account_type TEXT, institution_type TEXT, is_enabled INTEGER)',
    'CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, institution_id INTEGER, plan_code TEXT, status TEXT, payment_confirmed_at TEXT, starts_at TEXT, expires_at TEXT)',
    'CREATE TABLE institution_portal_suspensions (institution_id INTEGER PRIMARY KEY, suspended_by INTEGER, reason TEXT)',
    'CREATE TABLE salas (
        id INTEGER PRIMARY KEY, criador_id INTEGER, institution_id INTEGER, privacidade TEXT,
        alunos INTEGER DEFAULT 0
    )',
    'CREATE TABLE salas_usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT, sala_id INTEGER, usuario_id INTEGER, papel TEXT,
        status TEXT, entrou_em TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (sala_id, usuario_id, status)
    )',
    'CREATE TABLE room_join_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, user_id INTEGER, status TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, reviewed_at TEXT, reviewed_by INTEGER
    )',
    'CREATE TABLE room_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, reporter_user_id INTEGER,
        target_type TEXT DEFAULT "room", target_id INTEGER DEFAULT 0, reason TEXT, details TEXT,
        status TEXT DEFAULT "pending", reviewed_by INTEGER,
        UNIQUE (room_id, reporter_user_id, target_type, target_id, status)
    )',
] as $sql) {
    $pdo->exec($sql);
}

require_once __DIR__ . '/../includes/room_actions.php';

function assert_room_access(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec(
    "INSERT INTO instituicoes VALUES
     (20, 5, 'validated', 'universidade', 'publica'),
     (21, 6, 'validated', 'universidade', 'publica')"
);
$pdo->exec("INSERT INTO institution_members VALUES (1, 20, 2, 'active'), (2, 21, 3, 'active')");
$pdo->exec("INSERT INTO product_plans VALUES ('uni', 'institution', 'public_university', 1)");
$pdo->exec(
    "INSERT INTO subscriptions VALUES
     (1, 20, 'uni', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59'),
     (2, 21, 'uni', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')"
);
$pdo->exec(
    "INSERT INTO salas (id, criador_id, institution_id, privacidade) VALUES
     (1, 1, NULL, 'publica'),
     (2, 1, NULL, 'privada'),
     (3, 5, 20, 'instituicao'),
     (4, 6, 21, 'instituicao')"
);
$pdo->exec("INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status) VALUES (2, 1, 'professor', 'ativo'), (2, 2, 'moderador', 'ativo'), (3, 2, 'aluno', 'ativo')");

assert_room_access(room_user_can_access($pdo, ['id' => 1, 'privacidade' => 'publica'], 4), 'Usuário gratuito autenticado pode acessar uma sala pública.');
assert_room_access(!room_user_can_access($pdo, ['id' => 2, 'criador_id' => 1, 'privacidade' => 'privada'], 4), 'Pessoa sem vínculo não acessa sala privada.');
assert_room_access(room_user_can_access($pdo, ['id' => 2, 'criador_id' => 1, 'privacidade' => 'privada'], 1), 'Criador mantém acesso à sala privada.');
assert_room_access(room_user_can_access($pdo, ['id' => 3, 'institution_id' => 20, 'privacidade' => 'instituicao'], 2), 'Membro acessa comunidade da própria instituição.');
assert_room_access(!room_user_can_access($pdo, ['id' => 3, 'institution_id' => 20, 'privacidade' => 'instituicao'], 3), 'Membro de outra instituição não acessa a comunidade.');

room_join($pdo, 1, 4);
assert_room_access(room_user_is_member($pdo, 1, 4), 'Usuário autenticado consegue participar de uma sala pública.');
assert_room_access((int) $pdo->query('SELECT alunos FROM salas WHERE id = 1')->fetchColumn() === 1, 'Contagem da sala acompanha a entrada.');
try {
    room_join($pdo, 2, 4);
    throw new RuntimeException('Sala privada não deve aceitar entrada sem código.');
} catch (DomainException) {
}
room_request_join($pdo, 2, 4);
$requestId = (int) $pdo->lastInsertId();
assert_room_access(!room_request_join($pdo, 2, 4), 'Solicitação pendente duplicada não deve ser criada.');
try {
    room_review_join_request($pdo, 2, $requestId, 3, true);
    throw new RuntimeException('Usuário sem papel de moderação não deve aprovar solicitações.');
} catch (DomainException) {
}
room_review_join_request($pdo, 2, $requestId, 1, true);
assert_room_access(room_user_is_member($pdo, 2, 4), 'A aprovação cria um vínculo ativo.');
assert_room_access((int) $pdo->query('SELECT alunos FROM salas WHERE id = 2')->fetchColumn() === 1, 'A contagem é atualizada após aprovação.');

room_request_join($pdo, 2, 6);
$repeatRequestId = (int) $pdo->lastInsertId();
room_review_join_request($pdo, 2, $repeatRequestId, 1, false);
assert_room_access(room_request_join($pdo, 2, 6), 'Usuário pode solicitar novamente após recusa.');
$repeatRequestId = (int) $pdo->lastInsertId();
room_review_join_request($pdo, 2, $repeatRequestId, 1, false);
assert_room_access(
    (int) $pdo->query('SELECT COUNT(*) FROM room_join_requests WHERE room_id = 2 AND user_id = 6 AND status = "rejected"')->fetchColumn() === 2,
    'Solicitações recusadas repetidas preservam o histórico sem bloquear novo ciclo.'
);

room_join($pdo, 2, 5, true);
assert_room_access(room_user_is_member($pdo, 2, 5), 'Código de convite permite entrada em sala privada.');
room_remove_member($pdo, 2, 5, 2);
assert_room_access(!room_user_is_member($pdo, 2, 5), 'Moderador pode remover membro comum.');
try {
    room_remove_member($pdo, 2, 1, 2);
    throw new RuntimeException('Moderador não pode remover o criador.');
} catch (InvalidArgumentException) {
}
room_leave($pdo, 2, 4);
assert_room_access(!room_user_is_member($pdo, 2, 4), 'Participante pode sair da sala.');
try {
    room_leave($pdo, 2, 1);
    throw new RuntimeException('Responsável não pode deixar sua própria sala.');
} catch (DomainException) {
}
room_join($pdo, 3, 2, true);
assert_room_access(room_user_is_member($pdo, 3, 2), 'Membro institucional vinculado consegue entrar na comunidade.');
try {
    room_join($pdo, 3, 3, true);
    throw new RuntimeException('Código não pode substituir vínculo à instituição.');
} catch (DomainException) {
}
$pdo->exec('DELETE FROM institution_members WHERE institution_id = 20 AND user_id = 2');
assert_room_access(!room_user_can_access($pdo, ['id' => 3, 'institution_id' => 20, 'privacidade' => 'instituicao'], 2), 'Revogação institucional corta acesso existente à comunidade.');
assert_room_access(!room_user_can_manage($pdo, ['id' => 3, 'criador_id' => 5, 'institution_id' => 20, 'privacidade' => 'instituicao'], 2), 'Vínculo institucional revogado também remove poderes de moderação.');

assert_room_access(room_submit_report($pdo, 1, 4, 'spam', 'Conteúdo repetido.'), 'Usuário pode enviar denúncia de sala.');
assert_room_access(!room_submit_report($pdo, 1, 4, 'spam', 'Conteúdo repetido.'), 'Denúncia pendente duplicada não é armazenada.');
$reportId = (int) $pdo->lastInsertId();
try {
    room_review_report($pdo, 1, $reportId, 'reviewed', false, 6);
    throw new RuntimeException('Revisão de denúncia deve ser exclusiva da administração.');
} catch (DomainException) {
}
room_review_report($pdo, 1, $reportId, 'reviewed', true, 6);
$review = $pdo->query('SELECT status, reviewed_by FROM room_reports WHERE id = ' . $reportId)->fetch();
assert_room_access($review['status'] === 'reviewed' && (int) $review['reviewed_by'] === 6, 'Administrador pode concluir e registrar a revisão da denúncia.');

echo "OK: salas públicas e privadas, convites, solicitações, saída, remoção, moderação e isolamento institucional validados.\n";
