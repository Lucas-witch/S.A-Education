<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach ([
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, perfil TEXT NOT NULL, professor_status TEXT NOT NULL, email TEXT NOT NULL)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER NOT NULL, nome TEXT, validation_status TEXT, tipo TEXT, natureza TEXT)',
    'CREATE TABLE product_plans (code TEXT PRIMARY KEY, account_type TEXT, tier TEXT, institution_type TEXT, can_create_courses INTEGER, monthly_lesson_limit INTEGER, monthly_material_limit INTEGER, monthly_question_bank_limit INTEGER, questions_per_bank_limit INTEGER, is_enabled INTEGER)',
    'CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, institution_id INTEGER, user_id INTEGER, plan_code TEXT, status TEXT, payment_confirmed_at TEXT, income_verified_at TEXT, starts_at TEXT, expires_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE institution_members (id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, user_id INTEGER, member_type TEXT, status TEXT DEFAULT "active", joined_at TEXT DEFAULT CURRENT_TIMESTAMP, revoked_at TEXT, UNIQUE(institution_id, user_id))',
    'CREATE TABLE institution_invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, invited_user_id INTEGER, inviter_user_id INTEGER, member_type TEXT, token_hash TEXT UNIQUE, status TEXT DEFAULT "pending", expires_at TEXT, accepted_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE institution_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, actor_user_id INTEGER, action TEXT, target_user_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
] as $sql) {
    $pdo->exec($sql);
}

require_once __DIR__ . '/../includes/institution_members.php';

function assert_institution_member(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec(
    "INSERT INTO usuarios (id, perfil, professor_status, email) VALUES
     (1, 'instituicao', 'not_applicable', 'school@example.test'),
     (2, 'estudante', 'not_applicable', 'student@example.test'),
     (3, 'professor', 'approved', 'teacher@example.test'),
     (4, 'professor', 'pending', 'pending-teacher@example.test'),
     (5, 'estudante', 'not_applicable', 'second-student@example.test'),
     (6, 'estudante', 'not_applicable', 'another-student@example.test'),
     (8, 'instituicao', 'not_applicable', 'other-school@example.test')"
);
$pdo->exec(
    "INSERT INTO instituicoes (id, usuario_id, nome, validation_status, tipo, natureza) VALUES
     (10, 1, 'Escola Central', 'validated', 'fundamental', 'publica'),
     (80, 8, 'Outra Escola', 'validated', 'fundamental', 'publica')"
);
$pdo->exec(
    "INSERT INTO product_plans
        (code, account_type, tier, institution_type, can_create_courses, is_enabled)
     VALUES ('institution-public-school', 'institution', 'institutional', 'public_school', 0, 1)"
);
$pdo->exec(
    "INSERT INTO subscriptions
        (id, institution_id, plan_code, status, payment_confirmed_at, starts_at, expires_at)
     VALUES (1, 10, 'institution-public-school', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')"
);

$sentBody = '';
$sendEmail = static function (string $recipient, string $subject, string $body) use (&$sentBody): void {
    assert_institution_member($recipient === 'student@example.test', 'Convite deve ir para o e-mail da conta encontrada.');
    assert_institution_member($subject === 'Convite institucional | S.A Education', 'Assunto do convite inesperado.');
    $sentBody = $body;
};
create_institution_invitation($pdo, 10, 1, 'student@example.test', 'https://education.example.test', $sendEmail);
preg_match('/\?token=([A-Za-z0-9_-]{43})/', $sentBody, $tokenMatch);
assert_institution_member(isset($tokenMatch[1]), 'O e-mail de convite deve conter token aleatório.');
$token = $tokenMatch[1];
$invitation = $pdo->query('SELECT token_hash, status, member_type FROM institution_invitations')->fetch();
assert_institution_member($invitation['token_hash'] === hash('sha256', $token), 'Banco deve armazenar somente o hash do token.');
assert_institution_member($invitation['status'] === 'pending' && $invitation['member_type'] === 'student', 'Convite deve iniciar pendente e preservar o perfil da conta.');

try {
    accept_institution_invitation($pdo, 5, $token);
    throw new RuntimeException('Uma conta diferente não deve aceitar o convite.');
} catch (DomainException) {
}
assert_institution_member((int) $pdo->query('SELECT COUNT(*) FROM institution_members')->fetchColumn() === 0, 'Tentativa com conta errada não deve criar vínculo.');

accept_institution_invitation($pdo, 2, $token);
assert_institution_member(
    $pdo->query('SELECT status FROM institution_members WHERE institution_id = 10 AND user_id = 2')->fetchColumn() === 'active',
    'Aceite autenticado deve criar o vínculo ativo.'
);
assert_institution_member(
    $pdo->query('SELECT status FROM institution_invitations')->fetchColumn() === 'accepted',
    'Aceite deve consumir o convite.'
);

revoke_institution_member($pdo, 10, 1, 2);
assert_institution_member(
    $pdo->query('SELECT status FROM institution_members WHERE institution_id = 10 AND user_id = 2')->fetchColumn() === 'revoked',
    'Instituição deve poder revogar o acesso do membro.'
);
assert_institution_member(
    (int) $pdo->query("SELECT COUNT(*) FROM institution_audit_log WHERE action = 'member_access_revoked'")->fetchColumn() === 1,
    'Revogação deve ser registrada na auditoria institucional.'
);

$teacherToken = '';
$teacherMailer = static function (string $recipient, string $subject, string $body) use (&$teacherToken): void {
    assert_institution_member($recipient === 'teacher@example.test', 'Convite docente deve usar e-mail da conta aprovada.');
    preg_match('/\?token=([A-Za-z0-9_-]{43})/', $body, $match);
    $teacherToken = (string) ($match[1] ?? '');
};
create_institution_invitation($pdo, 10, 1, 'teacher@example.test', 'https://education.example.test', $teacherMailer);
accept_institution_invitation($pdo, 3, $teacherToken);
assert_institution_member(
    $pdo->query('SELECT member_type FROM institution_members WHERE institution_id = 10 AND user_id = 3')->fetchColumn() === 'teacher',
    'Professor aprovado deve manter o tipo institucional correto.'
);

$expiredToken = '';
$tokenMailer = static function (string $recipient, string $subject, string $body) use (&$expiredToken): void {
    preg_match('/\?token=([A-Za-z0-9_-]{43})/', $body, $match);
    $expiredToken = (string) ($match[1] ?? '');
};
create_institution_invitation($pdo, 10, 1, 'second-student@example.test', 'https://education.example.test', $tokenMailer);
$pdo->exec("UPDATE institution_invitations SET expires_at = '2000-01-01 00:00:00' WHERE invited_user_id = 5");
try {
    accept_institution_invitation($pdo, 5, $expiredToken);
    throw new RuntimeException('Convite expirado não pode ser aceito.');
} catch (DomainException) {
}
assert_institution_member(
    (int) $pdo->query('SELECT COUNT(*) FROM institution_members WHERE institution_id = 10 AND user_id = 5')->fetchColumn() === 0,
    'Convite expirado não deve criar vínculo.'
);

try {
    create_institution_invitation($pdo, 10, 1, 'pending-teacher@example.test', 'https://education.example.test', $sendEmail);
    throw new RuntimeException('Professor pendente não pode ser convidado como membro docente.');
} catch (DomainException) {
}

$pdo->exec("UPDATE subscriptions SET status = 'canceled' WHERE institution_id = 10");
try {
    create_institution_invitation($pdo, 10, 1, 'second-student@example.test', 'https://education.example.test', $sendEmail);
    throw new RuntimeException('Instituição sem assinatura ativa não pode enviar convites.');
} catch (DomainException) {
}

$pdo->exec("UPDATE subscriptions SET status = 'active' WHERE institution_id = 10");
$failedSender = static function (): void {
    throw new RuntimeException('SMTP indisponível.');
};
try {
    create_institution_invitation($pdo, 10, 1, 'another-student@example.test', 'https://education.example.test', $failedSender);
    throw new RuntimeException('Falha de SMTP deve ser informada.');
} catch (RuntimeException $error) {
    assert_institution_member(
        str_contains($error->getMessage(), 'Não foi possível enviar o convite'),
        'Erro de envio deve ter mensagem explícita.'
    );
}
assert_institution_member(
    (int) $pdo->query("SELECT COUNT(*) FROM institution_invitations WHERE status = 'failed'")->fetchColumn() === 1,
    'Convite não entregue deve ser invalidado e marcado para diagnóstico.'
);

echo "Institution membership invitation tests passed.\n";
