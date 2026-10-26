<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/subscriptions.php';

putenv('APP_ADMIN_USER_ID');
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, perfil TEXT, professor_status TEXT)');
$pdo->exec('CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, validation_status TEXT, tipo TEXT, natureza TEXT)');
$pdo->exec(
    'CREATE TABLE product_plans (
        code TEXT PRIMARY KEY, account_type TEXT, tier TEXT, institution_type TEXT,
        requires_income_verification INTEGER DEFAULT 0, can_create_courses INTEGER DEFAULT 0,
        monthly_lesson_limit INTEGER, monthly_material_limit INTEGER,
        monthly_question_bank_limit INTEGER, questions_per_bank_limit INTEGER,
        institution_member_limit INTEGER, institution_class_limit INTEGER, is_enabled INTEGER DEFAULT 1
    )'
);
$pdo->exec(
    'CREATE TABLE subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, institution_id INTEGER,
        plan_code TEXT, status TEXT, payment_confirmed_at TEXT, income_verified_at TEXT,
        starts_at TEXT, expires_at TEXT, canceled_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->exec(
    'CREATE TABLE subscription_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, subscription_id INTEGER, event_type TEXT,
        previous_status TEXT, new_status TEXT, actor_user_id INTEGER, source TEXT,
        provider_event_id TEXT, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->exec(
    "INSERT INTO usuarios (id, perfil, professor_status) VALUES
     (1, 'professor', 'approved'), (2, 'instituicao', 'not_applicable')"
);
$pdo->exec(
    "INSERT INTO instituicoes (id, usuario_id, validation_status, tipo, natureza)
     VALUES (20, 2, 'validated', 'fundamental', 'publica')"
);
$pdo->exec(
    "INSERT INTO product_plans
        (code, account_type, tier, can_create_courses, monthly_lesson_limit)
     VALUES ('teacher-free', 'teacher', 'free', 0, 5),
            ('teacher-premium', 'teacher', 'premium', 1, NULL)"
);
$pdo->exec(
    "INSERT INTO subscriptions (user_id, plan_code, status, payment_confirmed_at)
     VALUES (1, 'teacher-premium', 'active', CURRENT_TIMESTAMP)"
);
$pdo->exec(
    "INSERT INTO subscriptions (user_id, plan_code, status, payment_confirmed_at)
     VALUES (1, 'teacher-premium', 'active', CURRENT_TIMESTAMP)"
);
$latestSubscriptionId = (int) $pdo->lastInsertId();

function assert_subscription(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assert_subscription(
    resolve_account_permissions($pdo, 1)['premium'],
    'A última assinatura paga e ativa precisa habilitar o plano.'
);
cancel_account_subscription($pdo, 1, false, $latestSubscriptionId);
$afterCancel = resolve_account_permissions($pdo, 1);
assert_subscription(
    !$afterCancel['premium'] && $afterCancel['plan_code'] === 'teacher-free',
    'Cancelar a assinatura mais recente não pode restaurar uma assinatura antiga.'
);
assert_subscription(
    (int) $pdo->query("SELECT COUNT(*) FROM subscription_events WHERE event_type = 'canceled_by_account'")->fetchColumn() === 1,
    'O cancelamento precisa permanecer registrado no histórico.'
);

try {
    cancel_account_subscription($pdo, 1, false, $latestSubscriptionId);
    throw new RuntimeException('A assinatura já cancelada não pode ser cancelada novamente.');
} catch (DomainException) {
}

$pdo->exec(
    "INSERT INTO subscriptions (institution_id, plan_code, status)
     VALUES (99, 'teacher-premium', 'active')"
);
$foreignSubscriptionId = (int) $pdo->lastInsertId();
try {
    cancel_account_subscription($pdo, 2, true, $foreignSubscriptionId);
    throw new RuntimeException('Uma conta institucional não pode cancelar assinatura de outra instituição.');
} catch (DomainException) {
}

assert_subscription(
    normalize_plan_benefits("Acompanhamento\n\nConteúdo público") === ['Acompanhamento', 'Conteúdo público'],
    'Benefícios devem ser normalizados em linhas, sem itens vazios.'
);

echo "OK: latest subscription wins, owner-only cancellation, audit history and benefit normalization.\n";
