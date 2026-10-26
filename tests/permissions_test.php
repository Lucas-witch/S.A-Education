<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin_console.php';
require_once __DIR__ . '/../includes/room_access.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, perfil TEXT NOT NULL, professor_status TEXT NOT NULL)',
    'CREATE TABLE instituicoes (
        id INTEGER PRIMARY KEY, usuario_id INTEGER NOT NULL, validation_status TEXT NOT NULL,
        tipo TEXT, natureza TEXT
    )',
    'CREATE TABLE product_plans (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE, account_type TEXT, tier TEXT,
        institution_type TEXT, monthly_price NUMERIC, low_income_price NUMERIC,
        requires_income_verification INTEGER, monthly_lesson_limit INTEGER,
        monthly_material_limit INTEGER, monthly_question_bank_limit INTEGER,
        questions_per_bank_limit INTEGER, can_create_courses INTEGER,
        institution_member_limit INTEGER, institution_class_limit INTEGER, is_enabled INTEGER DEFAULT 1
    )',
    'CREATE TABLE subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, institution_id INTEGER,
        plan_code TEXT, status TEXT, payment_confirmed_at TEXT, income_verified_at TEXT,
        starts_at TEXT, expires_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE biblioteca (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_user_id INTEGER, status TEXT, revisao_status TEXT DEFAULT "approved", criado_em TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE publicacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, tipo TEXT, status TEXT,
        question_bank_id INTEGER, publicado_em TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE teacher_question_banks (
        id INTEGER PRIMARY KEY AUTOINCREMENT, owner_user_id INTEGER, month_start TEXT,
        questions_count INTEGER DEFAULT 0, UNIQUE(owner_user_id, month_start)
    )',
    'CREATE TABLE admin_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, action TEXT,
        target_type TEXT, target_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
] as $sql) {
    $pdo->exec($sql);
}

$pdo->exec(
    "INSERT INTO usuarios (id, perfil, professor_status) VALUES
     (1, 'professor', 'approved'), (2, 'professor', 'pending'),
     (3, 'estudante', 'not_applicable'), (4, 'professor', 'approved'),
     (5, 'estudante', 'not_applicable'), (6, 'instituicao', 'not_applicable'),
     (7, 'instituicao', 'not_applicable'), (8, 'estudante', 'not_applicable')"
);
$pdo->exec(
    "INSERT INTO instituicoes (id, usuario_id, validation_status, tipo, natureza) VALUES
     (61, 6, 'validated', 'fundamental', 'publica'),
     (71, 7, 'validated', 'universidade', 'particular')"
);
$plans = [
    ['student-free', 'student', 'free', null, 0, null, 0, null, null, null, null, 0],
    ['student-premium', 'student', 'premium', null, 70, null, 0, null, null, null, null, 0],
    ['student-premium-low-income', 'student', 'premium', null, 70, 21, 1, null, null, null, null, 0],
    ['teacher-free', 'teacher', 'free', null, 0, null, 0, 5, 1, 1, 100, 0],
    ['teacher-premium', 'teacher', 'premium', null, 100, null, 0, null, null, null, null, 1],
    ['institution-public-school', 'institution', 'institutional', 'public_school', 5000, null, 0, null, null, null, null, 0],
    ['institution-public-university', 'institution', 'institutional', 'public_university', 7000, null, 0, null, null, null, null, 0],
    ['institution-private-school', 'institution', 'institutional', 'private_school', 10000, null, 0, null, null, null, null, 0],
    ['institution-private-university', 'institution', 'institutional', 'private_university', 15000, null, 0, null, null, null, null, 0],
];
$planInsert = $pdo->prepare(
    'INSERT INTO product_plans
        (code, account_type, tier, institution_type, monthly_price, low_income_price,
         requires_income_verification, monthly_lesson_limit, monthly_material_limit,
         monthly_question_bank_limit, questions_per_bank_limit, can_create_courses)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($plans as $plan) {
    $planInsert->execute($plan);
}
$subscriptionInsert = $pdo->prepare(
    'INSERT INTO subscriptions
        (user_id, institution_id, plan_code, status, payment_confirmed_at, income_verified_at, starts_at, expires_at)
     VALUES (:user_id, :institution_id, :plan_code, :status, :payment_confirmed_at,
             :income_verified_at, :starts_at, :expires_at)'
);
$addSubscription = static function (
    PDO $pdo,
    ?int $userId,
    ?int $institutionId,
    string $planCode,
    ?string $paymentConfirmedAt,
    ?string $incomeVerifiedAt = null,
    ?string $expiresAt = null
): void {
    $statement = $pdo->prepare(
        'INSERT INTO subscriptions
            (user_id, institution_id, plan_code, status, payment_confirmed_at, income_verified_at, starts_at, expires_at)
         VALUES (:user_id, :institution_id, :plan_code, "active", :payment_confirmed_at,
                 :income_verified_at, CURRENT_TIMESTAMP, :expires_at)'
    );
    $statement->execute([
        'user_id' => $userId,
        'institution_id' => $institutionId,
        'plan_code' => $planCode,
        'payment_confirmed_at' => $paymentConfirmedAt,
        'income_verified_at' => $incomeVerifiedAt,
        'expires_at' => $expiresAt,
    ]);
};

function assert_permissions(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assert_permissions(resolve_institution_plan_type('fundamental', 'publica') === 'public_school', 'Public school mapping must use its catalog category.');
assert_permissions(resolve_institution_plan_type('universidade', 'particular') === 'private_university', 'Private university mapping must use its catalog category.');
assert_permissions(resolve_institution_plan_type('outro', 'publica') === null, 'Unclassified institutions must not inherit a commercial plan.');

$student = resolve_account_permissions($pdo, 3);
assert_permissions($student['plan_code'] === 'student-free' && !$student['premium'], 'Students must not gain Premium from legacy profile state.');
assert_permissions(!room_creation_is_allowed($student), 'Estudantes gratuitos não podem criar salas.');
assert_permissions($student['monthly_lesson_limit'] === null, 'No student content limit must be invented.');
$pendingTeacher = resolve_account_permissions($pdo, 2);
assert_permissions(!$pendingTeacher['approved'] && $pendingTeacher['monthly_lesson_limit'] === 0, 'Pending teachers must not publish.');
assert_permissions(!room_creation_is_allowed($pendingTeacher), 'Professores pendentes não podem criar salas.');
$freeTeacher = resolve_account_permissions($pdo, 1);
assert_permissions(
    $freeTeacher['approved']
    && !$freeTeacher['premium']
    && $freeTeacher['monthly_lesson_limit'] === 5
    && $freeTeacher['monthly_material_limit'] === 1
    && $freeTeacher['monthly_question_bank_limit'] === 1
    && $freeTeacher['questions_per_bank_limit'] === 100,
    'Approved free teachers must resolve the exact publication limits.'
);

$addSubscription($pdo, 4, null, 'teacher-premium', date('Y-m-d H:i:s'));
$premiumTeacher = resolve_account_permissions($pdo, 4);
assert_permissions($premiumTeacher['premium'] && $premiumTeacher['can_create_courses'], 'Paid and active Teacher Premium must unlock courses.');
assert_permissions(room_creation_is_allowed($premiumTeacher), 'Professor aprovado com assinatura Premium ativa pode criar salas.');
$addSubscription($pdo, 5, null, 'student-premium-low-income', date('Y-m-d H:i:s'));
assert_permissions(!resolve_account_permissions($pdo, 5)['premium'], 'Low-income Premium must require proof verification.');
$pdo->exec("UPDATE subscriptions SET income_verified_at = CURRENT_TIMESTAMP WHERE user_id = 5");
assert_permissions(resolve_account_permissions($pdo, 5)['premium'], 'Verified payment and income must activate the discounted plan.');
assert_permissions(room_creation_is_allowed(resolve_account_permissions($pdo, 5)), 'Estudante Premium ativo pode criar salas.');
$addSubscription($pdo, null, 61, 'institution-public-university', date('Y-m-d H:i:s'));
assert_permissions(!resolve_account_permissions($pdo, 6)['premium'], 'Institution subscription must match its verified type and nature.');
$pdo->exec('DELETE FROM subscriptions WHERE institution_id = 61');
$addSubscription($pdo, null, 61, 'institution-public-school', date('Y-m-d H:i:s'));
assert_permissions(resolve_account_permissions($pdo, 6)['premium'], 'Validated institution with confirmed subscription must be active.');
assert_permissions(room_creation_is_allowed(resolve_account_permissions($pdo, 6)), 'Instituição aprovada e assinante ativa pode criar comunidades.');
assert_permissions(!resolve_account_permissions($pdo, 7)['premium'], 'An institution without its own subscription must remain inactive.');
assert_permissions(!room_creation_is_allowed(resolve_account_permissions($pdo, 7)), 'Instituição sem assinatura não pode criar comunidades.');
$addSubscription($pdo, 3, null, 'student-premium', date('Y-m-d H:i:s'), null, date('Y-m-d H:i:s', time() - 3600));
assert_permissions(!resolve_account_permissions($pdo, 3)['premium'], 'Expired subscriptions must not grant Premium.');
assert_permissions(!room_creation_is_allowed(resolve_account_permissions($pdo, 3)), 'Assinatura expirada não autoriza a criação de salas.');

$publishLesson = static function (?int $bankId, bool $publish, array $permissions) use ($pdo): int {
    $insert = $pdo->prepare('INSERT INTO publicacoes (usuario_id, tipo, status) VALUES (1, "video_aula", "publicado")');
    $insert->execute();
    return (int) $pdo->lastInsertId();
};
for ($index = 0; $index < 5; $index++) {
    with_teacher_monthly_quota($pdo, 1, 'lesson', true, $publishLesson);
}
try {
    with_teacher_monthly_quota($pdo, 1, 'lesson', true, $publishLesson);
    throw new RuntimeException('A sexta videoaula publicada no mês deveria ser bloqueada.');
} catch (PermissionDeniedException) {
}
$draftSaved = with_teacher_monthly_quota(
    $pdo,
    1,
    'lesson',
    false,
    static fn (?int $bankId, bool $publish, array $permissions): string => 'rascunho'
);
assert_permissions($draftSaved === 'rascunho', 'Rascunhos devem ser permitidos mesmo quando a cota está cheia.');

$insertMaterial = $pdo->prepare('INSERT INTO biblioteca (owner_user_id, status) VALUES (1, "ativo")');
$insertMaterial->execute();
try {
    with_teacher_monthly_quota($pdo, 1, 'material', true, static fn (): bool => true);
    throw new RuntimeException('O segundo material mensal deveria ser bloqueado.');
} catch (PermissionDeniedException) {
}

$addQuestion = static function (?int $bankId, bool $publish, array $permissions) use ($pdo): int {
    $insert = $pdo->prepare(
        'INSERT INTO publicacoes (usuario_id, tipo, status, question_bank_id)
         VALUES (1, "post", "publicado", :bank_id)'
    );
    $insert->execute(['bank_id' => $bankId]);
    return (int) $pdo->lastInsertId();
};
for ($index = 0; $index < 100; $index++) {
    with_teacher_monthly_quota($pdo, 1, 'question', true, $addQuestion);
}
$bank = $pdo->query('SELECT COUNT(*) AS banks, MAX(questions_count) AS questions FROM teacher_question_banks WHERE owner_user_id = 1')->fetch();
assert_permissions(
    (int) $bank['banks'] === 1 && (int) $bank['questions'] === 100,
    'Questions must share one monthly bank and stop at 100: ' . json_encode($bank)
);
try {
    with_teacher_monthly_quota($pdo, 1, 'question', true, $addQuestion);
    throw new RuntimeException('The 101st question in the monthly bank should be blocked.');
} catch (PermissionDeniedException) {
}

putenv('APP_ADMIN_USER_ID=8');
$_SESSION['usuario_id'] = 8;
assert_permissions(resolve_account_permissions($pdo, 8)['is_admin'], 'Only the configured account ID must receive administrative permission.');
assert_permissions(room_creation_is_allowed(resolve_account_permissions($pdo, 8)), 'Administrador autorizado pode criar sem assinatura pessoal.');
$subscriptionsBeforePreview = (int) $pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn();
$teacherPreview = admin_simulate_account_permissions($pdo, 'teacher_free');
assert_permissions(
    $teacherPreview['read_only']
    && !$teacherPreview['creates_payment']
    && !$teacherPreview['premium']
    && $teacherPreview['monthly_lesson_limit'] === 5,
    'A simulação do professor gratuito deve refletir o catálogo sem habilitar Premium nem alterar contas.'
);
$pendingPreview = admin_simulate_account_permissions($pdo, 'institution_pending');
assert_permissions(
    !$pendingPreview['institution_validated']
    && !$pendingPreview['premium']
    && $pendingPreview['monthly_lesson_limit'] === 0,
    'Instituição pendente deve permanecer bloqueada na pré-visualização.'
);
$expiredInstitutionPreview = admin_simulate_account_permissions($pdo, 'institution_expired');
assert_permissions(
    !$expiredInstitutionPreview['premium']
    && $expiredInstitutionPreview['monthly_lesson_limit'] === 0,
    'Assinatura institucional expirada não deve desbloquear recursos na pré-visualização.'
);
$institutionScenarios = [
    'institution_public_school' => 'public_school',
    'institution_public_university' => 'public_university',
    'institution_private_school' => 'private_school',
    'institution_private_university' => 'private_university',
];
foreach ($institutionScenarios as $scenarioKey => $institutionType) {
    $institutionPreview = admin_simulate_account_permissions($pdo, $scenarioKey);
    assert_permissions(
        $institutionPreview['premium']
        && $institutionPreview['plan']['institution_type'] === $institutionType,
        'Cada categoria institucional deve usar seu plano configurado no catálogo.'
    );
}
assert_permissions(
    $subscriptionsBeforePreview === (int) $pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn()
    && is_admin_user(),
    'Executar pré-visualizações não cria pagamentos nem remove as permissões reais do administrador.'
);
record_admin_action($pdo, 'approve_teacher', 'teacher', 2, ['from' => 'pending', 'to' => 'approved']);
assert_permissions((int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn() === 1, 'Administrative actions must be audited.');
putenv('APP_ADMIN_USER_ID');

echo "OK: plan, approval, subscription, institution, quota, draft and audit permissions.\n";
