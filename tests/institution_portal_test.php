<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/institution_portal.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$schema = [
    'CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY, perfil TEXT NOT NULL, professor_status TEXT NOT NULL, nome_completo TEXT, email TEXT
    )',
    'CREATE TABLE instituicoes (
        id INTEGER PRIMARY KEY, usuario_id INTEGER NOT NULL, nome TEXT, tipo TEXT, natureza TEXT,
        validation_status TEXT
    )',
    'CREATE TABLE product_plans (
        code TEXT PRIMARY KEY, account_type TEXT, institution_type TEXT, monthly_price NUMERIC, is_enabled INTEGER
    )',
    'CREATE TABLE subscriptions (
        id INTEGER PRIMARY KEY, institution_id INTEGER, plan_code TEXT, status TEXT,
        payment_confirmed_at TEXT, starts_at TEXT, expires_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_members (
        id INTEGER PRIMARY KEY, institution_id INTEGER, user_id INTEGER, member_type TEXT,
        status TEXT DEFAULT "active"
    )',
    'CREATE TABLE institution_academic_units (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, parent_unit_id INTEGER,
        unit_type TEXT, name TEXT, code TEXT, status TEXT DEFAULT "active", created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_programs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, academic_unit_id INTEGER,
        name TEXT, code TEXT, degree_level TEXT, status TEXT DEFAULT "active", created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_terms (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, name TEXT, year INTEGER,
        period_code TEXT, starts_on TEXT, ends_on TEXT, status TEXT DEFAULT "planned",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, program_id INTEGER, term_id INTEGER,
        name TEXT, stage_label TEXT, grade_label TEXT, section TEXT, status TEXT DEFAULT "active",
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, name TEXT, knowledge_area TEXT,
        status TEXT DEFAULT "active", created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_enrollments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, class_id INTEGER, student_user_id INTEGER,
        status TEXT DEFAULT "active", enrolled_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(class_id, student_user_id)
    )',
    'CREATE TABLE institution_class_subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, class_id INTEGER, subject_id INTEGER,
        teacher_user_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(class_id, subject_id)
    )',
    'CREATE TABLE institution_learning_resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, class_subject_id INTEGER,
        resource_type TEXT, resource_id INTEGER, assigned_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(class_subject_id, resource_type, resource_id)
    )',
    'CREATE TABLE publicacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER,
        tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, arquivo_url TEXT, video_url TEXT,
        categoria TEXT, visibilidade TEXT, status TEXT, is_premium_only INTEGER, question_bank_id INTEGER,
        education_stage TEXT, knowledge_area TEXT, subject TEXT, topic TEXT, subtopic TEXT,
        course_title TEXT, complementary_materials TEXT, related_exercises TEXT, captions_url TEXT,
        publicado_em TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE biblioteca (
        id INTEGER PRIMARY KEY, instituicao_id INTEGER, titulo TEXT, autor TEXT, materia TEXT,
        status TEXT, revisao_status TEXT, direitos_declarados INTEGER, direitos_status TEXT,
        criado_em TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_assessments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, class_subject_id INTEGER,
        title TEXT, assessment_type TEXT, description TEXT, max_score NUMERIC, opens_at TEXT, due_at TEXT,
        status TEXT DEFAULT "draft", created_by INTEGER, published_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_assessment_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, assessment_id INTEGER, student_user_id INTEGER,
        score NUMERIC, feedback TEXT, published_at TEXT, recorded_by INTEGER,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(assessment_id, student_user_id)
    )',
    'CREATE TABLE institution_academic_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT, institution_id INTEGER, actor_user_id INTEGER, action TEXT,
        target_type TEXT, target_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
    'CREATE TABLE institution_portal_suspensions (
        institution_id INTEGER PRIMARY KEY, suspended_by INTEGER, reason TEXT, suspended_at TEXT DEFAULT CURRENT_TIMESTAMP
    )',
];
foreach ($schema as $sql) {
    $pdo->exec($sql);
}

$pdo->exec(
    "INSERT INTO usuarios (id, perfil, professor_status, nome_completo, email) VALUES
     (1, 'instituicao', 'not_applicable', 'Gestão A', 'gestao-a@test.invalid'),
     (2, 'instituicao', 'not_applicable', 'Gestão B', 'gestao-b@test.invalid'),
     (10, 'professor', 'approved', 'Docente A', 'docente-a@test.invalid'),
     (11, 'professor', 'approved', 'Docente B', 'docente-b@test.invalid'),
     (20, 'estudante', 'not_applicable', 'Estudante A', 'estudante-a@test.invalid'),
     (22, 'estudante', 'not_applicable', 'Estudante sem matrícula', 'estudante-sem-matricula@test.invalid'),
     (21, 'estudante', 'not_applicable', 'Estudante B', 'estudante-b@test.invalid')"
);
$pdo->exec(
    "INSERT INTO instituicoes (id, usuario_id, nome, tipo, natureza, validation_status) VALUES
     (100, 1, 'Universidade A', 'universidade', 'publica', 'validated'),
     (200, 2, 'Universidade B', 'universidade', 'publica', 'validated')"
);
$pdo->exec("INSERT INTO product_plans VALUES ('public-university', 'institution', 'public_university', 7000, 1)");
$pdo->exec(
    "INSERT INTO subscriptions (id, institution_id, plan_code, status, payment_confirmed_at, starts_at, expires_at)
     VALUES
     (1, 100, 'public-university', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59'),
     (2, 200, 'public-university', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')"
);
$pdo->exec(
    "INSERT INTO institution_members (institution_id, user_id, member_type, status) VALUES
     (100, 10, 'teacher', 'active'), (200, 11, 'teacher', 'active'),
     (100, 20, 'student', 'active'), (100, 22, 'student', 'active'),
     (200, 21, 'student', 'active')"
);

function assert_institution_portal(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$managerA = institution_portal_context($pdo, 1, 100, true);
$managerB = institution_portal_context($pdo, 2, 200, true);
$teacherA = institution_portal_context($pdo, 10, 100, false);
$studentA = institution_portal_context($pdo, 20, 100, false);

$unitId = institution_portal_handle_action($pdo, $managerA, 'create_unit', [
    'unit_type' => 'faculty', 'name' => 'Faculdade A', 'code' => 'FA',
]);
$programId = institution_portal_handle_action($pdo, $managerA, 'create_program', [
    'academic_unit_id' => (string) $unitId, 'name' => 'Programa A', 'degree_level' => 'undergraduate',
]);
$termId = institution_portal_handle_action($pdo, $managerA, 'create_term', [
    'name' => '1º semestre', 'year' => '2026', 'starts_on' => '2026-02-01', 'ends_on' => '2026-07-01',
]);
$classId = institution_portal_handle_action($pdo, $managerA, 'create_class', [
    'program_id' => (string) $programId, 'term_id' => (string) $termId, 'name' => 'Turma A',
]);
$subjectId = institution_portal_handle_action($pdo, $managerA, 'create_subject', [
    'name' => 'Disciplina A', 'knowledge_area' => 'Ciências Humanas',
]);
$classSubjectId = institution_portal_handle_action($pdo, $managerA, 'assign_subject', [
    'class_id' => (string) $classId, 'subject_id' => (string) $subjectId, 'teacher_user_id' => '10',
]);
$pdo->exec("INSERT INTO institution_classes
    (institution_id, program_id, term_id, name) VALUES (200, $programId, $termId, 'Turma B')");
$classB = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO institution_subjects (institution_id, name) VALUES (200, 'Disciplina B')");
$subjectB = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO institution_class_subjects
    (institution_id, class_id, subject_id, teacher_user_id) VALUES (200, $classB, $subjectB, 11)");
$classSubjectB = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO institution_enrollments (institution_id, class_id, student_user_id) VALUES
    (100, $classId, 20), (200, $classB, 21)");
$pdo->exec(
    "INSERT INTO publicacoes (id, instituicao_id, tipo, titulo, visibilidade, status) VALUES
     (500, 100, 'video_aula', 'Video institucional A', 'instituicao', 'publicado'),
     (501, 200, 'video_aula', 'Video institucional B', 'instituicao', 'publicado')"
);
$pdo->exec(
    "INSERT INTO biblioteca
        (id, instituicao_id, titulo, status, revisao_status, direitos_declarados, direitos_status) VALUES
     (600, 100, 'Material autorizado A', 'ativo', 'approved', 1, 'institution_authorized'),
     (601, 200, 'Material autorizado B', 'ativo', 'approved', 1, 'institution_authorized')"
);

$institutionLessonId = institution_portal_handle_action($pdo, $teacherA, 'create_institution_lesson', [
    'class_subject_id' => (string) $classSubjectId,
    'title' => 'Aula da disciplina A',
    'video_url' => 'https://media.example.test/aula-a.mp4',
    'subject' => 'Disciplina A',
    'course_title' => 'Programa A',
]);
assert_institution_portal(
    (int) $pdo->query('SELECT instituicao_id FROM publicacoes WHERE id = ' . $institutionLessonId)->fetchColumn() === 100,
    'Aula criada por professor atribuído é publicada sob o escopo da própria instituição.'
);
assert_institution_portal(
    (int) $pdo->query('SELECT COUNT(*) FROM institution_learning_resources WHERE resource_id = ' . $institutionLessonId)->fetchColumn() === 1,
    'Aula institucional é associada à disciplina na mesma transação.'
);
try {
    institution_portal_handle_action($pdo, $teacherA, 'create_institution_lesson', [
        'class_subject_id' => (string) $classSubjectB,
        'title' => 'Aula não autorizada',
        'video_url' => 'https://media.example.test/nao-autorizada.mp4',
    ]);
    throw new RuntimeException('Professor não deve criar conteúdo na disciplina de outra instituição.');
} catch (DomainException) {
}

$publicationResourceId = institution_portal_handle_action($pdo, $managerA, 'attach_learning_resource', [
    'class_subject_id' => (string) $classSubjectId,
    'resource_type' => 'publication',
    'resource_id' => '500',
]);
$libraryResourceId = institution_portal_handle_action($pdo, $teacherA, 'attach_learning_resource', [
    'class_subject_id' => (string) $classSubjectId,
    'resource_type' => 'library_material',
    'resource_id' => '600',
]);
assert_institution_portal($publicationResourceId > 0 && $libraryResourceId > 0, 'Gestão e docente responsável podem associar conteúdos existentes.');
$publicationAccessCondition = institution_content_access_condition(
    'p.instituicao_id',
    ':access_owner_id',
    ':access_member_id'
) . ' AND ' . institution_learning_resource_access_condition(
    'publication',
    'p.id',
    'p.instituicao_id',
    ':resource_owner_id',
    ':resource_member_id'
);
$publicationAccess = $pdo->prepare(
    'SELECT ' . $publicationAccessCondition . ' FROM publicacoes p WHERE p.id = :resource_id'
);
$resourceAccessParameters = static fn (int $userId): array => [
    'access_owner_id' => $userId,
    'access_member_id' => $userId,
    'resource_owner_id' => $userId,
    'resource_member_id' => $userId,
    'resource_id' => 500,
];
$publicationAccess->execute($resourceAccessParameters(20));
assert_institution_portal((bool) $publicationAccess->fetchColumn(), 'Estudante matriculado pode abrir o conteúdo vinculado à sua turma.');
$publicationAccess->execute($resourceAccessParameters(22));
assert_institution_portal(!(bool) $publicationAccess->fetchColumn(), 'Membro da instituição sem matrícula não pode abrir conteúdo curricular restrito por ID.');
$libraryAccessCondition = institution_content_access_condition(
    'b.instituicao_id',
    ':library_access_owner_id',
    ':library_access_member_id'
) . ' AND ' . institution_learning_resource_access_condition(
    'library_material',
    'b.id',
    'b.instituicao_id',
    ':library_resource_owner_id',
    ':library_resource_member_id'
);
$libraryAccess = $pdo->prepare(
    'SELECT ' . $libraryAccessCondition . ' FROM biblioteca b WHERE b.id = :resource_id'
);
$libraryAccess->execute([
    'library_access_owner_id' => 22,
    'library_access_member_id' => 22,
    'library_resource_owner_id' => 22,
    'library_resource_member_id' => 22,
    'resource_id' => 600,
]);
assert_institution_portal(!(bool) $libraryAccess->fetchColumn(), 'Membro sem matrícula não pode consultar material curricular institucional associado.');
try {
    institution_portal_handle_action($pdo, $managerA, 'attach_learning_resource', [
        'class_subject_id' => (string) $classSubjectId,
        'resource_type' => 'publication',
        'resource_id' => '500',
    ]);
    throw new RuntimeException('A mesma publicação não deve aparecer duas vezes na mesma disciplina.');
} catch (DomainException) {
}
try {
    institution_portal_handle_action($pdo, $managerA, 'attach_learning_resource', [
        'class_subject_id' => (string) $classSubjectId,
        'resource_type' => 'publication',
        'resource_id' => '501',
    ]);
    throw new RuntimeException('Conteúdo de outra instituição não deve ser associado.');
} catch (DomainException) {
}
try {
    institution_portal_handle_action($pdo, $teacherA, 'attach_learning_resource', [
        'class_subject_id' => (string) $classSubjectB,
        'resource_type' => 'publication',
        'resource_id' => '500',
    ]);
    throw new RuntimeException('Docente não deve associar conteúdo em turma não atribuída.');
} catch (DomainException) {
}
$resourceDashboard = institution_portal_dashboard($pdo, $studentA);
assert_institution_portal(
    count($resourceDashboard['learning_resources']) === 3,
    'Estudante matriculado vê aulas e conteúdos associados às suas disciplinas.'
);
assert_institution_portal(
    count(array_filter(
        $resourceDashboard['learning_resources'],
        static fn (array $item): bool => $item['url'] === 'videoaula.php?id=' . $institutionLessonId
    )) === 1,
    'Aulas novas usam o player existente para manter autorização da publicação.'
);
$pdo->exec("UPDATE publicacoes SET status = 'arquivado' WHERE id = 500");
institution_portal_handle_action($pdo, $teacherA, 'detach_learning_resource', [
    'class_subject_id' => (string) $classSubjectId,
    'resource_type' => 'publication',
    'resource_id' => '500',
]);
assert_institution_portal(
    count(institution_portal_dashboard($pdo, $studentA)['learning_resources']) === 2,
    'Conteúdo que deixou de estar publicado pode ser removido da disciplina e deixa de aparecer ao estudante.'
);
$pdo->exec("UPDATE publicacoes SET status = 'publicado' WHERE id = 500");
$studentB = institution_portal_context($pdo, 21, 200, false);
assert_institution_portal(
    institution_portal_dashboard($pdo, $studentB)['learning_resources'] === [],
    'Conteúdo de disciplina não atravessa instituições nem matrículas.'
);
try {
    institution_portal_handle_action($pdo, $managerA, 'attach_learning_resource', [
        'class_subject_id' => (string) $classSubjectId,
        'resource_type' => 'library_material',
        'resource_id' => '601',
    ]);
    throw new RuntimeException('Material não pode ser associado fora da instituição proprietária.');
} catch (DomainException) {
}

try {
    institution_portal_handle_action($pdo, $managerA, 'create_class', [
        'program_id' => (string) $programId, 'term_id' => '999', 'name' => 'Turma inválida',
    ]);
    throw new RuntimeException('Gestor não deve referenciar período de outra instituição.');
} catch (DomainException) {
}
try {
    institution_portal_context($pdo, 11, 100, false);
    throw new RuntimeException('Professor da instituição B não deve abrir o portal privado da A.');
} catch (PermissionDeniedException) {
}
try {
    institution_portal_require_class_subject($pdo, $teacherA, $classSubjectB);
    throw new RuntimeException('Professor não deve acessar disciplina de outra instituição.');
} catch (DomainException) {
}

$teacherDashboard = institution_portal_dashboard($pdo, $teacherA);
assert_institution_portal(count($teacherDashboard['classes']) === 1, 'Professor deve ver somente turmas atribuídas.');
assert_institution_portal(
    (int) $teacherDashboard['classes'][0]['id'] === $classId,
    'Professor não deve ver a turma da outra instituição.'
);
assert_institution_portal(
    count($teacherDashboard['members']) === 1 && (int) $teacherDashboard['members'][0]['user_id'] === 20,
    'Docente deve ver somente estudantes matriculados em suas disciplinas.'
);

$assessmentId = institution_portal_handle_action($pdo, $teacherA, 'create_assessment', [
    'class_subject_id' => (string) $classSubjectId,
    'title' => 'Avaliação A',
    'assessment_type' => 'exam',
    'max_score' => '10',
]);
$teacherDashboard = institution_portal_dashboard($pdo, $teacherA);
assert_institution_portal(count($teacherDashboard['assessments']) === 1, 'Docente deve acessar a avaliação da disciplina atribuída.');
$studentDashboard = institution_portal_dashboard($pdo, $studentA);
assert_institution_portal($studentDashboard['assessments'] === [], 'Estudante não deve ver rascunhos de avaliação.');

institution_portal_handle_action($pdo, $teacherA, 'publish_assessment', ['assessment_id' => (string) $assessmentId]);
$resultId = institution_portal_handle_action($pdo, $teacherA, 'save_result', [
    'assessment_id' => (string) $assessmentId,
    'student_user_id' => '20',
    'score' => '8.5',
    'feedback' => 'Bom trabalho.',
]);
$studentDashboard = institution_portal_dashboard($pdo, $studentA);
assert_institution_portal($studentDashboard['results'] === [], 'Nota privada não deve aparecer antes da publicação.');

try {
    institution_portal_handle_action($pdo, $teacherA, 'save_result', [
        'assessment_id' => (string) $assessmentId,
        'student_user_id' => '21',
        'score' => '8',
    ]);
    throw new RuntimeException('Professor não deve registrar nota para estudante de outra instituição.');
} catch (PermissionDeniedException) {
}
try {
    institution_portal_handle_action($pdo, $studentA, 'publish_result', ['result_id' => (string) $resultId]);
    throw new RuntimeException('Estudante não deve publicar notas.');
} catch (PermissionDeniedException) {
}

institution_portal_handle_action($pdo, $teacherA, 'publish_result', ['result_id' => (string) $resultId]);
$studentDashboard = institution_portal_dashboard($pdo, $studentA);
assert_institution_portal(
    count($studentDashboard['results']) === 1
    && (float) $studentDashboard['results'][0]['score'] === 8.5,
    'Estudante matriculado deve consultar somente suas notas publicadas.'
);
$otherStudentContext = institution_portal_context($pdo, 21, 200, false);
assert_institution_portal(institution_portal_dashboard($pdo, $otherStudentContext)['results'] === [], 'Notas não devem atravessar a fronteira institucional.');
assert_institution_portal(
    (int) $pdo->query('SELECT COUNT(*) FROM institution_academic_audit WHERE institution_id = 100')->fetchColumn() >= 8,
    'Operações acadêmicas devem gerar trilha de auditoria.'
);
$pdo->exec("INSERT INTO institution_portal_suspensions (institution_id, suspended_by, reason) VALUES (100, 1, 'Revisão administrativa')");
$suspendedManager = institution_portal_context($pdo, 1, 100, true);
assert_institution_portal($suspendedManager['suspended'] === true, 'Suspensão administrativa deve aparecer no contexto do gestor.');
assert_institution_portal(
    institution_portal_dashboard($pdo, $suspendedManager)['classes'] === [],
    'Instituição suspensa não deve carregar os dados acadêmicos.'
);
assert_institution_portal(
    institution_portal_dashboard($pdo, $suspendedManager)['learning_resources'] === [],
    'Instituição suspensa não deve carregar vínculos de conteúdo acadêmico.'
);
try {
    institution_portal_require_active($suspendedManager);
    throw new RuntimeException('Instituição suspensa não pode alterar o ambiente acadêmico.');
} catch (PermissionDeniedException) {
}
$suspendedMember = institution_portal_dashboard($pdo, institution_portal_context($pdo, 20, 100, false));
assert_institution_portal(
    $suspendedMember['context']['suspended'] === true && $suspendedMember['classes'] === [],
    'Membro deve receber somente o estado de suspensão, sem dados acadêmicos.'
);

echo "OK: isolamento entre instituições, atribuição docente, matrícula, avaliações, notas privadas e publicadas.\n";
