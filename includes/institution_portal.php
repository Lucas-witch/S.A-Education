<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/institution_members.php';
require_once __DIR__ . '/feed.php';

interface InstitutionLiveClassProvider {
    public function status(): array;
    public function createSession(int $institutionId, int $classSubjectId, int $actorUserId): void;
}

final class UnconfiguredInstitutionLiveClassProvider implements InstitutionLiveClassProvider {
    public function status(): array {
        return [
            'configured' => false,
            'provider' => null,
            'message' => 'Nenhum provedor de aula ao vivo foi escolhido ou configurado.',
        ];
    }

    public function createSession(int $institutionId, int $classSubjectId, int $actorUserId): void {
        throw new LogicException('A integração de aulas ao vivo ainda não está configurada.');
    }
}

function institution_live_class_provider(): InstitutionLiveClassProvider {
    return new UnconfiguredInstitutionLiveClassProvider();
}

function institution_portal_subscription(PDO $pdo, int $institutionId): ?array {
    $statement = $pdo->prepare(
        'SELECT s.plan_code, s.status, s.starts_at, s.expires_at, s.payment_confirmed_at,
                p.monthly_price, p.institution_type,
                p.institution_member_limit, p.institution_class_limit
         FROM subscriptions s
         INNER JOIN product_plans p ON p.code = s.plan_code
         INNER JOIN instituicoes i ON i.id = s.institution_id
         WHERE s.institution_id = :institution_id
           AND s.status = "active"
           AND s.payment_confirmed_at IS NOT NULL
           AND (s.starts_at IS NULL OR s.starts_at <= CURRENT_TIMESTAMP)
           AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)
           AND p.account_type = "institution"
           AND p.is_enabled = 1
           AND p.institution_type = CASE
               WHEN i.tipo IN ("fundamental", "medio") AND i.natureza = "publica" THEN "public_school"
               WHEN i.tipo IN ("fundamental", "medio") AND i.natureza = "particular" THEN "private_school"
               WHEN i.tipo IN ("faculdade", "universidade") AND i.natureza = "publica" THEN "public_university"
               WHEN i.tipo IN ("faculdade", "universidade") AND i.natureza = "particular" THEN "private_university"
               ELSE NULL
           END
           AND NOT EXISTS (
               SELECT 1 FROM subscriptions newer
               WHERE newer.institution_id = s.institution_id AND newer.id > s.id
           )
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 1'
    );
    $statement->execute(['institution_id' => $institutionId]);
    $subscription = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($subscription) ? $subscription : null;
}

function institution_portal_context(
    PDO $pdo,
    int $actorUserId,
    ?int $requestedInstitutionId,
    bool $institutionLogin
): array {
    if ($actorUserId <= 0) {
        throw new PermissionDeniedException('A sessão não corresponde a uma conta válida.');
    }

    if ($institutionLogin) {
        $statement = $pdo->prepare(
            'SELECT i.id, i.usuario_id AS owner_user_id, i.nome, i.tipo, i.natureza, i.validation_status,
                    EXISTS (SELECT 1 FROM institution_portal_suspensions x WHERE x.institution_id = i.id) AS is_suspended
             FROM instituicoes i
             WHERE i.usuario_id = :actor_user_id
             LIMIT 1'
        );
        $statement->execute(['actor_user_id' => $actorUserId]);
        $institution = $statement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($institution)
            || ($requestedInstitutionId !== null && (int) $institution['id'] !== $requestedInstitutionId)
        ) {
            throw new PermissionDeniedException('A instituição não corresponde à sessão autenticada.');
        }
        $role = 'manager';
    } else {
        $statement = $pdo->prepare(
            'SELECT i.id, i.usuario_id AS owner_user_id, i.nome, i.tipo, i.natureza, i.validation_status,
                    EXISTS (SELECT 1 FROM institution_portal_suspensions x WHERE x.institution_id = i.id) AS is_suspended,
                    im.member_type, u.perfil, u.professor_status
             FROM institution_members im
             INNER JOIN instituicoes i ON i.id = im.institution_id
             INNER JOIN usuarios u ON u.id = im.user_id
             WHERE im.user_id = :actor_user_id
               AND im.status = "active"
               AND (:institution_id IS NULL OR i.id = :requested_institution_id)
             ORDER BY i.nome
             LIMIT 1'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'institution_id' => $requestedInstitutionId,
            'requested_institution_id' => $requestedInstitutionId,
        ]);
        $institution = $statement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($institution)
            || ($institution['member_type'] === 'student' && $institution['perfil'] !== 'estudante')
            || ($institution['member_type'] === 'teacher'
                && ($institution['perfil'] !== 'professor' || $institution['professor_status'] !== 'approved'))
        ) {
            throw new PermissionDeniedException('Não há vínculo institucional ativo para esta conta.');
        }
        $role = $institution['member_type'] === 'teacher' ? 'teacher' : 'student';
    }

    $institutionId = (int) $institution['id'];
    $subscription = institution_portal_subscription($pdo, $institutionId);
    return [
        'institution_id' => $institutionId,
        'owner_user_id' => (int) $institution['owner_user_id'],
        'institution_name' => (string) $institution['nome'],
        'institution_type' => (string) $institution['tipo'],
        'institution_nature' => (string) $institution['natureza'],
        'validation_status' => (string) $institution['validation_status'],
        'suspended' => (bool) $institution['is_suspended'],
        'subscription_active' => $subscription !== null,
        'subscription' => $subscription,
        'subscription_state' => institution_portal_subscription_state($pdo, $institutionId, $subscription),
        'role' => $role,
        'actor_user_id' => $actorUserId,
    ];
}

function institution_portal_subscription_state(PDO $pdo, int $institutionId, ?array $activeSubscription): string {
    if ($activeSubscription !== null) {
        return 'active';
    }
    $statement = $pdo->prepare(
        'SELECT status, payment_confirmed_at, expires_at,
                (expires_at IS NOT NULL AND expires_at <= CURRENT_TIMESTAMP) AS is_expired
         FROM subscriptions
         WHERE institution_id = :institution_id
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );
    $statement->execute(['institution_id' => $institutionId]);
    $subscription = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subscription)) {
        return 'none';
    }
    if (
        $subscription['status'] === 'expired'
        || (int) $subscription['is_expired'] === 1
    ) {
        return 'expired';
    }
    if ($subscription['status'] === 'canceled') {
        return 'canceled';
    }
    if ($subscription['status'] === 'suspended') {
        return 'suspended';
    }
    if ($subscription['status'] === 'pending' || $subscription['payment_confirmed_at'] === null) {
        return 'pending';
    }
    return 'inactive';
}

function institution_portal_require_active(array $context): void {
    if ($context['suspended']) {
        throw new PermissionDeniedException('O acesso ao portal institucional está suspenso.');
    }
    if ($context['validation_status'] !== 'validated') {
        throw new PermissionDeniedException('A instituição ainda não foi validada; o ambiente acadêmico está bloqueado.');
    }
    if (!$context['subscription_active']) {
        throw new PermissionDeniedException('A instituição não possui assinatura ativa confirmada.');
    }
}

function institution_portal_audit(
    PDO $pdo,
    array $context,
    string $action,
    string $targetType,
    ?int $targetId,
    array $details = []
): void {
    $statement = $pdo->prepare(
        'INSERT INTO institution_academic_audit
            (institution_id, actor_user_id, action, target_type, target_id, details)
         VALUES (:institution_id, :actor_user_id, :action, :target_type, :target_id, :details)'
    );
    $statement->execute([
        'institution_id' => $context['institution_id'],
        'actor_user_id' => $context['actor_user_id'],
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
    ]);
}

function institution_portal_transaction(PDO $pdo, callable $operation): mixed {
    $pdo->beginTransaction();
    try {
        $result = $operation();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function institution_portal_input_text(array $input, string $key, int $maximum, bool $required = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || mb_strlen(trim($value), 'UTF-8') > $maximum) {
        throw new InvalidArgumentException('Revise os dados enviados.');
    }
    $value = trim($value);
    if ($required && $value === '') {
        throw new InvalidArgumentException('Preencha os campos obrigatórios.');
    }
    return $value;
}

function institution_portal_input_id(array $input, string $key): int {
    $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value <= 0) {
        throw new InvalidArgumentException('Identificador acadêmico inválido.');
    }
    return (int) $value;
}

function institution_portal_require_manager(array $context): void {
    if ($context['role'] !== 'manager') {
        throw new PermissionDeniedException('Somente a gestão institucional pode organizar o currículo e as matrículas.');
    }
    institution_portal_require_active($context);
}

function institution_portal_require_class_subject(PDO $pdo, array $context, int $classSubjectId): array {
    $lockClause = $pdo->inTransaction() && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? ' FOR UPDATE'
        : '';
    $statement = $pdo->prepare(
        'SELECT cs.id, cs.class_id, cs.subject_id, cs.teacher_user_id,
                c.name AS class_name, s.name AS subject_name
         FROM institution_class_subjects cs
         INNER JOIN institution_classes c
            ON c.id = cs.class_id AND c.institution_id = cs.institution_id
         INNER JOIN institution_subjects s
            ON s.id = cs.subject_id AND s.institution_id = cs.institution_id
         WHERE cs.id = :class_subject_id
           AND cs.institution_id = :institution_id
         LIMIT 1' . $lockClause
    );
    $statement->execute([
        'class_subject_id' => $classSubjectId,
        'institution_id' => $context['institution_id'],
    ]);
    $assignment = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($assignment)) {
        throw new DomainException('Disciplina não encontrada nesta instituição.');
    }
    if (
        $context['role'] !== 'manager'
        && ($context['role'] !== 'teacher' || (int) $assignment['teacher_user_id'] !== $context['actor_user_id'])
    ) {
        throw new PermissionDeniedException('Esta disciplina não está atribuída à sua conta.');
    }
    if ($context['role'] === 'teacher') {
        $membership = $pdo->prepare(
            'SELECT 1 FROM institution_members im
             INNER JOIN usuarios u ON u.id = im.user_id
             WHERE im.institution_id = :institution_id
               AND im.user_id = :teacher_user_id
               AND im.member_type = "teacher"
               AND im.status = "active"
               AND u.perfil = "professor"
               AND u.professor_status = "approved"
             LIMIT 1'
        );
        $membership->execute([
            'institution_id' => $context['institution_id'],
            'teacher_user_id' => $context['actor_user_id'],
        ]);
        if (!$membership->fetchColumn()) {
            throw new PermissionDeniedException('Seu vínculo docente não está mais ativo nesta instituição.');
        }
    }
    return $assignment;
}

function institution_portal_learning_resources(PDO $pdo, array $context): array {
    $institutionId = (int) $context['institution_id'];
    $available = [];
    if ($context['role'] !== 'student') {
        $publications = $pdo->prepare(
            'SELECT id, titulo, tipo FROM publicacoes
             WHERE instituicao_id = :institution_id
               AND status = "publicado" AND visibilidade IN ("instituicao", "publica")
             ORDER BY publicado_em DESC, id DESC LIMIT 100'
        );
        $publications->execute(['institution_id' => $institutionId]);
        foreach ($publications->fetchAll(PDO::FETCH_ASSOC) as $publication) {
            $available[] = [
                'resource_type' => 'publication',
                'resource_id' => (int) $publication['id'],
                'title' => (string) $publication['titulo'],
                'resource_kind' => (string) $publication['tipo'],
            ];
        }
        $materials = $pdo->prepare(
            'SELECT id, titulo FROM biblioteca
             WHERE instituicao_id = :institution_id
               AND status = "ativo" AND revisao_status = "approved"
               AND direitos_declarados = 1 AND direitos_status <> "unknown"
             ORDER BY criado_em DESC, id DESC LIMIT 100'
        );
        $materials->execute(['institution_id' => $institutionId]);
        foreach ($materials->fetchAll(PDO::FETCH_ASSOC) as $material) {
            $available[] = [
                'resource_type' => 'library_material',
                'resource_id' => (int) $material['id'],
                'title' => (string) $material['titulo'],
                'resource_kind' => 'material de biblioteca',
            ];
        }
    }

    $query = $pdo->prepare(
        'SELECT r.id, r.class_subject_id, r.resource_type, r.resource_id,
                cs.class_id, c.name AS class_name, s.name AS subject_name,
                p.titulo AS publication_title, p.tipo AS publication_kind,
                b.titulo AS library_title
         FROM institution_learning_resources r
         INNER JOIN institution_class_subjects cs
            ON cs.id = r.class_subject_id AND cs.institution_id = r.institution_id
         INNER JOIN institution_classes c
            ON c.id = cs.class_id AND c.institution_id = cs.institution_id
         INNER JOIN institution_subjects s
            ON s.id = cs.subject_id AND s.institution_id = cs.institution_id
         LEFT JOIN publicacoes p
            ON r.resource_type = "publication" AND p.id = r.resource_id
           AND p.instituicao_id = r.institution_id AND p.status = "publicado"
           AND p.visibilidade IN ("instituicao", "publica")
         LEFT JOIN biblioteca b
            ON r.resource_type = "library_material" AND b.id = r.resource_id
           AND b.instituicao_id = r.institution_id AND b.status = "ativo"
           AND b.revisao_status = "approved" AND b.direitos_declarados = 1
           AND b.direitos_status <> "unknown"
         WHERE r.institution_id = :institution_id
           AND (p.id IS NOT NULL OR b.id IS NOT NULL)'
        . ($context['role'] === 'teacher' ? ' AND cs.teacher_user_id = :teacher_user_id' : '')
        . ($context['role'] === 'student'
            ? ' AND EXISTS (
                  SELECT 1 FROM institution_enrollments e
                  WHERE e.institution_id = cs.institution_id
                    AND e.class_id = cs.class_id
                    AND e.student_user_id = :student_user_id
                    AND e.status = "active"
              )'
            : '')
        . ' ORDER BY c.name, s.name, r.created_at DESC'
    );
    $parameters = ['institution_id' => $institutionId];
    if ($context['role'] === 'teacher') {
        $parameters['teacher_user_id'] = $context['actor_user_id'];
    } elseif ($context['role'] === 'student') {
        $parameters['student_user_id'] = $context['actor_user_id'];
    }
    $query->execute($parameters);
    $resources = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $resource) {
        $isPublication = $resource['resource_type'] === 'publication';
        $title = (string) ($isPublication ? $resource['publication_title'] : $resource['library_title']);
        $kind = $isPublication ? (string) $resource['publication_kind'] : 'library_material';
        $resource['title'] = $title;
        $resource['resource_kind'] = $kind;
        $resource['url'] = $isPublication
            ? ($kind === 'video_aula'
                ? 'videoaula.php?id=' . (int) $resource['resource_id']
                : 'feed.php?q=' . rawurlencode($title))
            : 'biblioteca-leitor.php?id=' . (int) $resource['resource_id'];
        $resources[] = $resource;
    }

    return ['available' => $available, 'assigned' => $resources];
}

function institution_portal_dashboard(PDO $pdo, array $context): array {
    $result = [
        'context' => [
            'institution_id' => $context['institution_id'],
            'institution_name' => $context['institution_name'],
            'institution_type' => $context['institution_type'],
            'institution_nature' => $context['institution_nature'],
            'validation_status' => $context['validation_status'],
            'suspended' => $context['suspended'],
            'subscription_active' => $context['subscription_active'],
            'subscription_state' => $context['subscription_state'],
            'institution_member_limit' => is_array($context['subscription'])
                ? $context['subscription']['institution_member_limit']
                : 0,
            'institution_class_limit' => is_array($context['subscription'])
                ? $context['subscription']['institution_class_limit']
                : 0,
            'role' => $context['role'],
        ],
        'units' => [],
        'programs' => [],
        'terms' => [],
        'classes' => [],
        'subjects' => [],
        'class_subjects' => [],
        'members' => [],
        'assessments' => [],
        'results' => [],
        'publications' => [],
        'library_materials' => [],
        'available_resources' => [],
        'learning_resources' => [],
        'live_class_integration' => institution_live_class_provider()->status(),
    ];
    if ($context['suspended'] || $context['validation_status'] !== 'validated' || !$context['subscription_active']) {
        return $result;
    }

    $institutionId = $context['institution_id'];
    if ($context['role'] === 'manager') {
        foreach ([
            'units' => ['institution_academic_units', 'id, parent_unit_id, unit_type AS type, name, code, status'],
            'programs' => ['institution_programs', 'id, academic_unit_id, name, code, degree_level, status'],
            'terms' => ['institution_terms', 'id, name, year, period_code, starts_on, ends_on, status'],
            'classes' => ['institution_classes', 'id, program_id, term_id, name, stage_label, grade_label, section, status'],
            'subjects' => ['institution_subjects', 'id, name, knowledge_area, status'],
        ] as $key => [$table, $columns]) {
            $query = $pdo->prepare(
                'SELECT ' . $columns . ' FROM ' . $table . '
                 WHERE institution_id = :institution_id ORDER BY name, id'
            );
            $query->execute(['institution_id' => $institutionId]);
            $result[$key] = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        $memberQuery = $pdo->prepare(
            'SELECT im.user_id, im.member_type, u.nome_completo, u.email
             FROM institution_members im
             INNER JOIN usuarios u ON u.id = im.user_id
             WHERE im.institution_id = :institution_id AND im.status = "active"
             ORDER BY im.member_type, u.nome_completo'
        );
        $memberQuery->execute(['institution_id' => $institutionId]);
        $result['members'] = $memberQuery->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($context['role'] === 'teacher') {
        $classQuery = $pdo->prepare(
            'SELECT DISTINCT c.id, c.program_id, c.term_id, c.name, c.stage_label, c.grade_label, c.section, c.status
             FROM institution_classes c
             INNER JOIN institution_class_subjects cs
                ON cs.class_id = c.id AND cs.institution_id = c.institution_id
             WHERE c.institution_id = :institution_id
               AND cs.teacher_user_id = :teacher_user_id
             ORDER BY c.name'
        );
        $classQuery->execute([
            'institution_id' => $institutionId,
            'teacher_user_id' => $context['actor_user_id'],
        ]);
        $result['classes'] = $classQuery->fetchAll(PDO::FETCH_ASSOC);
        $students = $pdo->prepare(
            'SELECT DISTINCT im.user_id, im.member_type, u.nome_completo, u.email
             FROM institution_class_subjects cs
             INNER JOIN institution_enrollments e
                ON e.class_id = cs.class_id AND e.institution_id = cs.institution_id
               AND e.status = "active"
             INNER JOIN institution_members im
                ON im.institution_id = e.institution_id AND im.user_id = e.student_user_id
               AND im.status = "active" AND im.member_type = "student"
             INNER JOIN usuarios u ON u.id = im.user_id
             WHERE cs.institution_id = :institution_id AND cs.teacher_user_id = :teacher_user_id
             ORDER BY u.nome_completo'
        );
        $students->execute([
            'institution_id' => $institutionId,
            'teacher_user_id' => $context['actor_user_id'],
        ]);
        $result['members'] = $students->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $classQuery = $pdo->prepare(
            'SELECT c.id, c.program_id, c.term_id, c.name, c.stage_label, c.grade_label, c.section, c.status
             FROM institution_enrollments e
             INNER JOIN institution_classes c
                ON c.id = e.class_id AND c.institution_id = e.institution_id
             WHERE e.institution_id = :institution_id
               AND e.student_user_id = :student_user_id
               AND e.status = "active"
             ORDER BY c.name'
        );
        $classQuery->execute([
            'institution_id' => $institutionId,
            'student_user_id' => $context['actor_user_id'],
        ]);
        $result['classes'] = $classQuery->fetchAll(PDO::FETCH_ASSOC);
    }

    $classSubjects = $pdo->prepare(
        'SELECT cs.id, cs.class_id, cs.subject_id, cs.teacher_user_id,
                c.name AS class_name, s.name AS subject_name, teacher.nome_completo AS teacher_name
         FROM institution_class_subjects cs
         INNER JOIN institution_classes c
            ON c.id = cs.class_id AND c.institution_id = cs.institution_id
         INNER JOIN institution_subjects s
            ON s.id = cs.subject_id AND s.institution_id = cs.institution_id
         LEFT JOIN usuarios teacher ON teacher.id = cs.teacher_user_id
         WHERE cs.institution_id = :institution_id'
        . ($context['role'] === 'teacher' ? ' AND cs.teacher_user_id = :teacher_user_id' : '')
        . ($context['role'] === 'student'
            ? ' AND EXISTS (
                  SELECT 1 FROM institution_enrollments e
                  WHERE e.class_id = cs.class_id
                    AND e.institution_id = cs.institution_id
                    AND e.student_user_id = :student_user_id
                    AND e.status = "active"
              )'
            : '')
        . ' ORDER BY c.name, s.name'
    );
    $subjectParameters = ['institution_id' => $institutionId];
    if ($context['role'] === 'teacher') {
        $subjectParameters['teacher_user_id'] = $context['actor_user_id'];
    }
    if ($context['role'] === 'student') {
        $subjectParameters['student_user_id'] = $context['actor_user_id'];
    }
    $classSubjects->execute($subjectParameters);
    $result['class_subjects'] = $classSubjects->fetchAll(PDO::FETCH_ASSOC);
    $learningResources = institution_portal_learning_resources($pdo, $context);
    $result['available_resources'] = $learningResources['available'];
    $result['learning_resources'] = $learningResources['assigned'];

    $assessmentQuery = $pdo->prepare(
        'SELECT a.id, a.class_subject_id, a.title, a.assessment_type, a.description,
                a.max_score, a.opens_at, a.due_at, a.status, a.created_by, a.published_at,
                cs.class_id, c.name AS class_name, s.name AS subject_name
         FROM institution_assessments a
         INNER JOIN institution_class_subjects cs
            ON cs.id = a.class_subject_id AND cs.institution_id = a.institution_id
         INNER JOIN institution_classes c
            ON c.id = cs.class_id AND c.institution_id = cs.institution_id
         INNER JOIN institution_subjects s
            ON s.id = cs.subject_id AND s.institution_id = cs.institution_id
         WHERE a.institution_id = :institution_id'
        . ($context['role'] === 'teacher' ? ' AND cs.teacher_user_id = :teacher_user_id' : '')
        . ($context['role'] === 'student'
            ? ' AND a.status = "published"
                 AND (a.opens_at IS NULL OR a.opens_at <= CURRENT_TIMESTAMP)
                 AND EXISTS (
                     SELECT 1 FROM institution_enrollments e
                     WHERE e.class_id = cs.class_id
                       AND e.institution_id = cs.institution_id
                       AND e.student_user_id = :student_user_id
                       AND e.status = "active"
                 )'
            : '')
        . ' ORDER BY a.due_at IS NULL, a.due_at, a.created_at DESC'
    );
    $assessmentParameters = ['institution_id' => $institutionId];
    if ($context['role'] === 'teacher') {
        $assessmentParameters['teacher_user_id'] = $context['actor_user_id'];
    }
    if ($context['role'] === 'student') {
        $assessmentParameters['student_user_id'] = $context['actor_user_id'];
    }
    $assessmentQuery->execute($assessmentParameters);
    $result['assessments'] = $assessmentQuery->fetchAll(PDO::FETCH_ASSOC);

    $resultQuery = $pdo->prepare(
        'SELECT r.id, r.assessment_id, r.student_user_id, r.score, r.feedback, r.published_at,
                u.nome_completo AS student_name, a.title AS assessment_title, a.max_score,
                cs.class_id, c.name AS class_name, s.name AS subject_name
         FROM institution_assessment_results r
         INNER JOIN institution_assessments a
            ON a.id = r.assessment_id AND a.institution_id = r.institution_id
         INNER JOIN institution_class_subjects cs
            ON cs.id = a.class_subject_id AND cs.institution_id = a.institution_id
         INNER JOIN institution_classes c
            ON c.id = cs.class_id AND c.institution_id = cs.institution_id
         INNER JOIN institution_subjects s
            ON s.id = cs.subject_id AND s.institution_id = cs.institution_id
         INNER JOIN usuarios u ON u.id = r.student_user_id
         WHERE r.institution_id = :institution_id'
        . ($context['role'] === 'teacher' ? ' AND cs.teacher_user_id = :teacher_user_id' : '')
        . ($context['role'] === 'student'
            ? ' AND r.student_user_id = :student_user_id AND r.published_at IS NOT NULL'
            : '')
        . ' ORDER BY c.name, s.name, a.title, u.nome_completo'
    );
    $resultParameters = ['institution_id' => $institutionId];
    if ($context['role'] === 'teacher') {
        $resultParameters['teacher_user_id'] = $context['actor_user_id'];
    }
    if ($context['role'] === 'student') {
        $resultParameters['student_user_id'] = $context['actor_user_id'];
    }
    $resultQuery->execute($resultParameters);
    $result['results'] = $resultQuery->fetchAll(PDO::FETCH_ASSOC);

    if ($context['role'] === 'manager') {
        $publications = $pdo->prepare(
            'SELECT id, tipo, titulo, subject, course_title, status, publicado_em
             FROM publicacoes
             WHERE instituicao_id = :institution_id
             ORDER BY publicado_em DESC LIMIT 50'
        );
        $publications->execute(['institution_id' => $institutionId]);
        $result['publications'] = $publications->fetchAll(PDO::FETCH_ASSOC);
        $materials = $pdo->prepare(
            'SELECT id, titulo, autor, materia, revisao_status, status
             FROM biblioteca
             WHERE instituicao_id = :institution_id
             ORDER BY criado_em DESC LIMIT 50'
        );
        $materials->execute(['institution_id' => $institutionId]);
        $result['library_materials'] = $materials->fetchAll(PDO::FETCH_ASSOC);
    }
    return $result;
}

function institution_portal_handle_action(PDO $pdo, array $context, string $action, array $input): int {
    if ($action === 'create_institution_lesson') {
        institution_portal_require_active($context);
        $classSubjectId = institution_portal_input_id($input, 'class_subject_id');
        institution_portal_require_class_subject($pdo, $context, $classSubjectId);
        $lessonInput = $input;
        $lessonInput['kind'] = 'free_lesson';
        $lessonInput['visibility'] = 'publica';
        $lessonInput['room_id'] = '';
        $lesson = feed_normalize_publication_input($lessonInput);

        return institution_portal_transaction($pdo, static function () use (
            $pdo,
            $context,
            $classSubjectId,
            $lesson
        ): int {
            $insert = $pdo->prepare(
                'INSERT INTO publicacoes
                    (usuario_id, instituicao_id, sala_id, tipo, titulo, resumo, conteudo, arquivo_url,
                     video_url, categoria, visibilidade, status, is_premium_only, question_bank_id,
                     education_stage, knowledge_area, subject, topic, subtopic, course_title,
                     complementary_materials, related_exercises, captions_url)
                 VALUES
                    (:user_id, :institution_id, NULL, :type, :title, :summary, :content, NULL,
                     :video_url, :category, "instituicao", "publicado", 0, NULL,
                     :education_stage, :knowledge_area, :subject, :topic, :subtopic, :course_title,
                     :complementary_materials, :related_exercises, :captions_url)'
            );
            $insert->execute([
                'user_id' => $context['actor_user_id'],
                'institution_id' => $context['institution_id'],
                'type' => $lesson['tipo'],
                'title' => $lesson['titulo'],
                'summary' => $lesson['resumo'],
                'content' => $lesson['conteudo'],
                'video_url' => $lesson['video_url'],
                'category' => $lesson['categoria'],
                'education_stage' => $lesson['education_stage'] ?: null,
                'knowledge_area' => $lesson['knowledge_area'] ?: null,
                'subject' => $lesson['subject'] ?: null,
                'topic' => $lesson['topic'] ?: null,
                'subtopic' => $lesson['subtopic'] ?: null,
                'course_title' => $lesson['course_title'] ?: null,
                'complementary_materials' => $lesson['complementary_materials'] ?: null,
                'related_exercises' => $lesson['related_exercises'] ?: null,
                'captions_url' => $lesson['captions_url'] ?: null,
            ]);
            $publicationId = (int) $pdo->lastInsertId();
            $assignment = $pdo->prepare(
                'INSERT INTO institution_learning_resources
                    (institution_id, class_subject_id, resource_type, resource_id, assigned_by)
                 VALUES (:institution_id, :class_subject_id, "publication", :resource_id, :assigned_by)'
            );
            $assignment->execute([
                'institution_id' => $context['institution_id'],
                'class_subject_id' => $classSubjectId,
                'resource_id' => $publicationId,
                'assigned_by' => $context['actor_user_id'],
            ]);
            institution_portal_audit(
                $pdo,
                $context,
                'institution_lesson_published',
                'publication',
                $publicationId,
                ['class_subject_id' => $classSubjectId]
            );
            return $publicationId;
        });
    }

    if ($action === 'attach_learning_resource' || $action === 'detach_learning_resource') {
        institution_portal_require_active($context);
        $classSubjectId = institution_portal_input_id($input, 'class_subject_id');
        institution_portal_require_class_subject($pdo, $context, $classSubjectId);
        $resourceType = institution_portal_input_text($input, 'resource_type', 30);
        $resourceId = institution_portal_input_id($input, 'resource_id');
        if (!in_array($resourceType, ['publication', 'library_material'], true)) {
            throw new InvalidArgumentException('Tipo de conteúdo institucional inválido.');
        }
        if ($action === 'attach_learning_resource') {
            $sourceTable = $resourceType === 'publication' ? 'publicacoes' : 'biblioteca';
            $sourceFilter = $resourceType === 'publication'
                ? 'status = "publicado" AND visibilidade IN ("instituicao", "publica")'
                : 'status = "ativo" AND revisao_status = "approved"
                   AND direitos_declarados = 1 AND direitos_status <> "unknown"';
            $source = $pdo->prepare(
                'SELECT id FROM ' . $sourceTable . '
                 WHERE id = :resource_id AND instituicao_id = :institution_id AND ' . $sourceFilter . '
                 LIMIT 1'
            );
            $source->execute([
                'resource_id' => $resourceId,
                'institution_id' => $context['institution_id'],
            ]);
            if (!$source->fetchColumn()) {
                throw new DomainException('O conteúdo precisa estar publicado, autorizado e pertencer à instituição atual.');
            }
        }

        return institution_portal_transaction($pdo, static function () use (
            $pdo,
            $context,
            $action,
            $classSubjectId,
            $resourceType,
            $resourceId
        ): int {
            if ($action === 'attach_learning_resource') {
                $existing = $pdo->prepare(
                    'SELECT id FROM institution_learning_resources
                     WHERE institution_id = :institution_id
                       AND class_subject_id = :class_subject_id
                       AND resource_type = :resource_type AND resource_id = :resource_id'
                );
                $existing->execute([
                    'institution_id' => $context['institution_id'],
                    'class_subject_id' => $classSubjectId,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                ]);
                if ($existing->fetchColumn() !== false) {
                    throw new DomainException('Este conteúdo já está associado à disciplina.');
                }
                $insert = $pdo->prepare(
                    'INSERT INTO institution_learning_resources
                        (institution_id, class_subject_id, resource_type, resource_id, assigned_by)
                     VALUES (:institution_id, :class_subject_id, :resource_type, :resource_id, :assigned_by)'
                );
                $insert->execute([
                    'institution_id' => $context['institution_id'],
                    'class_subject_id' => $classSubjectId,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'assigned_by' => $context['actor_user_id'],
                ]);
                $resourceAssignmentId = (int) $pdo->lastInsertId();
                institution_portal_audit(
                    $pdo,
                    $context,
                    'learning_resource_assigned',
                    $resourceType,
                    $resourceId,
                    ['class_subject_id' => $classSubjectId]
                );
                return $resourceAssignmentId;
            }

            $delete = $pdo->prepare(
                'DELETE FROM institution_learning_resources
                 WHERE institution_id = :institution_id
                   AND class_subject_id = :class_subject_id
                   AND resource_type = :resource_type AND resource_id = :resource_id'
            );
            $delete->execute([
                'institution_id' => $context['institution_id'],
                'class_subject_id' => $classSubjectId,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]);
            if ($delete->rowCount() !== 1) {
                throw new DomainException('O conteúdo associado não foi encontrado.');
            }
            institution_portal_audit(
                $pdo,
                $context,
                'learning_resource_unassigned',
                $resourceType,
                $resourceId,
                ['class_subject_id' => $classSubjectId]
            );
            return $resourceId;
        });
    }

    if ($action === 'create_unit') {
        institution_portal_require_manager($context);
        $type = institution_portal_input_text($input, 'unit_type', 20);
        if (!in_array($type, ['campus', 'faculty', 'department', 'area'], true)) {
            throw new InvalidArgumentException('Tipo de unidade acadêmica inválido.');
        }
        $parentId = null;
        if (($input['parent_unit_id'] ?? '') !== '') {
            $parentId = institution_portal_input_id($input, 'parent_unit_id');
            $parent = $pdo->prepare(
                'SELECT id FROM institution_academic_units
                 WHERE id = :parent_id AND institution_id = :institution_id AND status = "active"'
            );
            $parent->execute(['parent_id' => $parentId, 'institution_id' => $context['institution_id']]);
            if (!$parent->fetchColumn()) {
                throw new DomainException('A unidade superior não pertence a esta instituição.');
            }
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $type, $parentId, $input): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_academic_units
                    (institution_id, parent_unit_id, unit_type, name, code)
                 VALUES (:institution_id, :parent_unit_id, :unit_type, :name, :code)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'parent_unit_id' => $parentId,
                'unit_type' => $type,
                'name' => institution_portal_input_text($input, 'name', 180),
                'code' => institution_portal_input_text($input, 'code', 60, false) ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'academic_unit_created', 'academic_unit', $id);
            return $id;
        });
    }

    if ($action === 'create_program') {
        institution_portal_require_manager($context);
        $level = institution_portal_input_text($input, 'degree_level', 30);
        if (!in_array($level, ['fundamental', 'medio', 'undergraduate', 'graduate', 'continuing'], true)) {
            throw new InvalidArgumentException('Etapa ou nível do curso inválido.');
        }
        $unitId = ($input['academic_unit_id'] ?? '') !== ''
            ? institution_portal_input_id($input, 'academic_unit_id')
            : null;
        if ($unitId !== null) {
            $unit = $pdo->prepare(
                'SELECT id FROM institution_academic_units
                 WHERE id = :unit_id AND institution_id = :institution_id AND status = "active"'
            );
            $unit->execute(['unit_id' => $unitId, 'institution_id' => $context['institution_id']]);
            if (!$unit->fetchColumn()) {
                throw new DomainException('A unidade acadêmica não pertence a esta instituição.');
            }
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $level, $unitId, $input): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_programs
                    (institution_id, academic_unit_id, name, code, degree_level)
                 VALUES (:institution_id, :unit_id, :name, :code, :degree_level)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'unit_id' => $unitId,
                'name' => institution_portal_input_text($input, 'name', 180),
                'code' => institution_portal_input_text($input, 'code', 60, false) ?: null,
                'degree_level' => $level,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'academic_program_created', 'program', $id);
            return $id;
        });
    }

    if ($action === 'create_term') {
        institution_portal_require_manager($context);
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > 2200) {
            throw new InvalidArgumentException('Ano letivo inválido.');
        }
        $startsOn = institution_portal_input_text($input, 'starts_on', 10, false) ?: null;
        $endsOn = institution_portal_input_text($input, 'ends_on', 10, false) ?: null;
        foreach ([$startsOn, $endsOn] as $date) {
            if ($date !== null) {
                $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
                    throw new InvalidArgumentException('Informe as datas do período no formato AAAA-MM-DD.');
                }
            }
        }
        if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
            throw new InvalidArgumentException('A data final deve ser posterior à data inicial.');
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $year, $startsOn, $endsOn, $input): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_terms
                    (institution_id, name, year, period_code, starts_on, ends_on)
                 VALUES (:institution_id, :name, :year, :period_code, :starts_on, :ends_on)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'name' => institution_portal_input_text($input, 'name', 100),
                'year' => $year,
                'period_code' => institution_portal_input_text($input, 'period_code', 40, false) ?: null,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'academic_term_created', 'term', $id);
            return $id;
        });
    }

    if ($action === 'create_class') {
        institution_portal_require_manager($context);
        $programId = institution_portal_input_id($input, 'program_id');
        $termId = institution_portal_input_id($input, 'term_id');
        foreach ([['institution_programs', 'program_id', $programId], ['institution_terms', 'term_id', $termId]] as [$table, $column, $id]) {
            $query = $pdo->prepare(
                'SELECT id FROM ' . $table . '
                 WHERE id = :record_id AND institution_id = :institution_id AND status <> "closed" AND status <> "archived"'
            );
            $query->execute(['record_id' => $id, 'institution_id' => $context['institution_id']]);
            if (!$query->fetchColumn()) {
                throw new DomainException('Curso ou período não pertence à instituição ou está encerrado.');
            }
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $programId, $termId, $input): int {
            $institutionLockSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? 'SELECT id FROM instituicoes WHERE id = :institution_id FOR UPDATE'
                : 'SELECT id FROM instituicoes WHERE id = :institution_id';
            $institutionLock = $pdo->prepare($institutionLockSql);
            $institutionLock->execute(['institution_id' => $context['institution_id']]);
            if (!$institutionLock->fetchColumn()) {
                throw new PermissionDeniedException('A instituição não está disponível.');
            }

            $currentSubscription = institution_portal_subscription($pdo, (int) $context['institution_id']);
            if ($currentSubscription === null) {
                throw new PermissionDeniedException('A instituição não possui uma assinatura ativa confirmada.');
            }
            $classLimit = $currentSubscription['institution_class_limit'];
            if ($classLimit !== null) {
                $count = $pdo->prepare(
                    'SELECT COUNT(*) FROM institution_classes
                     WHERE institution_id = :institution_id AND status = "active"'
                );
                $count->execute(['institution_id' => $context['institution_id']]);
                if ((int) $count->fetchColumn() >= (int) $classLimit) {
                    throw new PermissionDeniedException('O plano atingiu o limite de turmas institucionais ativas.');
                }
            }

            $insert = $pdo->prepare(
                'INSERT INTO institution_classes
                    (institution_id, program_id, term_id, name, stage_label, grade_label, section)
                 VALUES (:institution_id, :program_id, :term_id, :name, :stage_label, :grade_label, :section)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'program_id' => $programId,
                'term_id' => $termId,
                'name' => institution_portal_input_text($input, 'name', 150),
                'stage_label' => institution_portal_input_text($input, 'stage_label', 100, false) ?: null,
                'grade_label' => institution_portal_input_text($input, 'grade_label', 100, false) ?: null,
                'section' => institution_portal_input_text($input, 'section', 60, false) ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'academic_class_created', 'class', $id);
            return $id;
        });
    }

    if ($action === 'create_subject') {
        institution_portal_require_manager($context);
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $input): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_subjects (institution_id, name, knowledge_area)
                 VALUES (:institution_id, :name, :knowledge_area)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'name' => institution_portal_input_text($input, 'name', 150),
                'knowledge_area' => institution_portal_input_text($input, 'knowledge_area', 120, false) ?: null,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'academic_subject_created', 'subject', $id);
            return $id;
        });
    }

    if ($action === 'assign_subject') {
        institution_portal_require_manager($context);
        $classId = institution_portal_input_id($input, 'class_id');
        $subjectId = institution_portal_input_id($input, 'subject_id');
        $teacherId = ($input['teacher_user_id'] ?? '') !== ''
            ? institution_portal_input_id($input, 'teacher_user_id')
            : null;
        $class = $pdo->prepare(
            'SELECT id FROM institution_classes WHERE id = :class_id AND institution_id = :institution_id'
        );
        $class->execute(['class_id' => $classId, 'institution_id' => $context['institution_id']]);
        $subject = $pdo->prepare(
            'SELECT id FROM institution_subjects WHERE id = :subject_id AND institution_id = :institution_id'
        );
        $subject->execute(['subject_id' => $subjectId, 'institution_id' => $context['institution_id']]);
        if (!$class->fetchColumn() || !$subject->fetchColumn()) {
            throw new DomainException('A turma ou disciplina não pertence a esta instituição.');
        }
        if ($teacherId !== null) {
            $teacher = $pdo->prepare(
                'SELECT 1 FROM institution_members im
                 INNER JOIN usuarios u ON u.id = im.user_id
                 WHERE im.institution_id = :institution_id AND im.user_id = :teacher_id
                   AND im.member_type = "teacher" AND im.status = "active"
                   AND u.perfil = "professor" AND u.professor_status = "approved"
                 LIMIT 1'
            );
            $teacher->execute(['institution_id' => $context['institution_id'], 'teacher_id' => $teacherId]);
            if (!$teacher->fetchColumn()) {
                throw new DomainException('O professor precisa ter vínculo ativo e aprovação nesta instituição.');
            }
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $classId, $subjectId, $teacherId): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_class_subjects
                    (institution_id, class_id, subject_id, teacher_user_id)
                 VALUES (:institution_id, :class_id, :subject_id, :teacher_user_id)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_user_id' => $teacherId,
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'teacher_subject_assigned', 'class_subject', $id);
            return $id;
        });
    }

    if ($action === 'enroll_student') {
        institution_portal_require_manager($context);
        $classId = institution_portal_input_id($input, 'class_id');
        $studentId = institution_portal_input_id($input, 'student_user_id');
        $student = $pdo->prepare(
            'SELECT 1 FROM institution_members im
             INNER JOIN usuarios u ON u.id = im.user_id
             WHERE im.institution_id = :institution_id AND im.user_id = :student_id
               AND im.member_type = "student" AND im.status = "active" AND u.perfil = "estudante"
             LIMIT 1'
        );
        $student->execute(['institution_id' => $context['institution_id'], 'student_id' => $studentId]);
        $class = $pdo->prepare(
            'SELECT id FROM institution_classes WHERE id = :class_id AND institution_id = :institution_id'
        );
        $class->execute(['class_id' => $classId, 'institution_id' => $context['institution_id']]);
        if (!$student->fetchColumn() || !$class->fetchColumn()) {
            throw new DomainException('Estudante ou turma não está disponível nesta instituição.');
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $classId, $studentId): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_enrollments (institution_id, class_id, student_user_id)
                 VALUES (:institution_id, :class_id, :student_user_id)
                 ON DUPLICATE KEY UPDATE status = "active"'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'class_id' => $classId,
                'student_user_id' => $studentId,
            ]);
            $query = $pdo->prepare(
                'SELECT id FROM institution_enrollments
                 WHERE class_id = :class_id AND student_user_id = :student_user_id'
            );
            $query->execute(['class_id' => $classId, 'student_user_id' => $studentId]);
            $id = (int) $query->fetchColumn();
            institution_portal_audit($pdo, $context, 'student_enrolled', 'enrollment', $id);
            return $id;
        });
    }

    if ($action === 'create_assessment') {
        institution_portal_require_active($context);
        $classSubjectId = institution_portal_input_id($input, 'class_subject_id');
        $assignment = institution_portal_require_class_subject($pdo, $context, $classSubjectId);
        $title = institution_portal_input_text($input, 'title', 180);
        $type = institution_portal_input_text($input, 'assessment_type', 20);
        if (!in_array($type, ['activity', 'assignment', 'exam', 'quiz'], true)) {
            throw new InvalidArgumentException('Tipo de avaliação inválido.');
        }
        $maxScore = filter_var($input['max_score'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($maxScore === false || !is_finite((float) $maxScore) || $maxScore <= 0 || $maxScore > 10000) {
            throw new InvalidArgumentException('Pontuação máxima inválida.');
        }
        $description = institution_portal_input_text($input, 'description', 5000, false) ?: null;
        $opensAt = institution_portal_input_text($input, 'opens_at', 16, false) ?: null;
        $dueAt = institution_portal_input_text($input, 'due_at', 16, false) ?: null;
        foreach (['opens_at' => $opensAt, 'due_at' => $dueAt] as $key => $date) {
            if ($date !== null) {
                $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $date);
                if ($parsedDate === false || $parsedDate->format('Y-m-d\TH:i') !== $date) {
                    throw new InvalidArgumentException('Datas inválidas; use o formato local de data e hora.');
                }
            }
        }
        if ($opensAt !== null && $dueAt !== null && $dueAt < $opensAt) {
            throw new InvalidArgumentException('O prazo não pode ser anterior à abertura.');
        }
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $classSubjectId, $title, $type, $maxScore, $description, $opensAt, $dueAt): int {
            $insert = $pdo->prepare(
                'INSERT INTO institution_assessments
                    (institution_id, class_subject_id, title, assessment_type, description,
                     max_score, opens_at, due_at, created_by)
                 VALUES (:institution_id, :class_subject_id, :title, :assessment_type, :description,
                         :max_score, :opens_at, :due_at, :created_by)'
            );
            $insert->execute([
                'institution_id' => $context['institution_id'],
                'class_subject_id' => $classSubjectId,
                'title' => $title,
                'assessment_type' => $type,
                'description' => $description,
                'max_score' => $maxScore,
                'opens_at' => $opensAt !== null ? str_replace('T', ' ', $opensAt) . ':00' : null,
                'due_at' => $dueAt !== null ? str_replace('T', ' ', $dueAt) . ':00' : null,
                'created_by' => $context['actor_user_id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            institution_portal_audit($pdo, $context, 'assessment_created', 'assessment', $id);
            return $id;
        });
    }

    if ($action === 'publish_assessment') {
        institution_portal_require_active($context);
        $assessmentId = institution_portal_input_id($input, 'assessment_id');
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $assessmentId): int {
            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $query = $pdo->prepare(
                'SELECT class_subject_id, status FROM institution_assessments
                 WHERE id = :assessment_id AND institution_id = :institution_id' . $lockClause
            );
            $query->execute([
                'assessment_id' => $assessmentId,
                'institution_id' => $context['institution_id'],
            ]);
            $assessment = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($assessment) || $assessment['status'] !== 'draft') {
                throw new DomainException('Avaliação não encontrada ou já publicada.');
            }
            institution_portal_require_class_subject($pdo, $context, (int) $assessment['class_subject_id']);
            $update = $pdo->prepare(
                'UPDATE institution_assessments
                 SET status = "published", published_at = CURRENT_TIMESTAMP
                 WHERE id = :assessment_id AND institution_id = :institution_id'
            );
            $update->execute([
                'assessment_id' => $assessmentId,
                'institution_id' => $context['institution_id'],
            ]);
            institution_portal_audit($pdo, $context, 'assessment_published', 'assessment', $assessmentId);
            return $assessmentId;
        });
    }

    if ($action === 'save_result') {
        institution_portal_require_active($context);
        $assessmentId = institution_portal_input_id($input, 'assessment_id');
        $studentId = institution_portal_input_id($input, 'student_user_id');
        $score = filter_var($input['score'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($score === false || !is_finite((float) $score) || $score < 0) {
            throw new InvalidArgumentException('Nota inválida.');
        }
        $feedback = institution_portal_input_text($input, 'feedback', 3000, false) ?: null;
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $assessmentId, $studentId, $score, $feedback): int {
            $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $query = $pdo->prepare(
                'SELECT a.class_subject_id, a.max_score, a.status, cs.class_id
                 FROM institution_assessments a
                 INNER JOIN institution_class_subjects cs
                    ON cs.id = a.class_subject_id AND cs.institution_id = a.institution_id
                 WHERE a.id = :assessment_id AND a.institution_id = :institution_id' . $lockClause
            );
            $query->execute([
                'assessment_id' => $assessmentId,
                'institution_id' => $context['institution_id'],
            ]);
            $assessment = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($assessment) || $assessment['status'] === 'draft' || $score > (float) $assessment['max_score']) {
                throw new DomainException('Avaliação indisponível ou nota acima da pontuação máxima.');
            }
            institution_portal_require_class_subject($pdo, $context, (int) $assessment['class_subject_id']);
            $enrollment = $pdo->prepare(
                'SELECT id FROM institution_enrollments
                 WHERE institution_id = :institution_id
                   AND class_id = :class_id
                   AND student_user_id = :student_user_id
                   AND status = "active"
                 LIMIT 1'
            );
            $enrollment->execute([
                'institution_id' => $context['institution_id'],
                'class_id' => (int) $assessment['class_id'],
                'student_user_id' => $studentId,
            ]);
            if (!$enrollment->fetchColumn()) {
                throw new PermissionDeniedException('O estudante não está matriculado nesta turma.');
            }
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = $driver === 'sqlite'
                ? 'INSERT INTO institution_assessment_results
                       (institution_id, assessment_id, student_user_id, score, feedback, published_at, recorded_by)
                   VALUES (:institution_id, :assessment_id, :student_user_id, :score, :feedback, NULL, :recorded_by)
                   ON CONFLICT(assessment_id, student_user_id) DO UPDATE SET
                       score = excluded.score, feedback = excluded.feedback, published_at = NULL,
                       recorded_by = excluded.recorded_by, updated_at = CURRENT_TIMESTAMP'
                : 'INSERT INTO institution_assessment_results
                       (institution_id, assessment_id, student_user_id, score, feedback, published_at, recorded_by)
                   VALUES (:institution_id, :assessment_id, :student_user_id, :score, :feedback, NULL, :recorded_by)
                   ON DUPLICATE KEY UPDATE
                       score = VALUES(score), feedback = VALUES(feedback), published_at = NULL,
                       recorded_by = VALUES(recorded_by), updated_at = CURRENT_TIMESTAMP';
            $pdo->prepare($sql)->execute([
                'institution_id' => $context['institution_id'],
                'assessment_id' => $assessmentId,
                'student_user_id' => $studentId,
                'score' => $score,
                'feedback' => $feedback,
                'recorded_by' => $context['actor_user_id'],
            ]);
            $resultQuery = $pdo->prepare(
                'SELECT id FROM institution_assessment_results
                 WHERE assessment_id = :assessment_id AND student_user_id = :student_user_id'
            );
            $resultQuery->execute(['assessment_id' => $assessmentId, 'student_user_id' => $studentId]);
            $id = (int) $resultQuery->fetchColumn();
            institution_portal_audit($pdo, $context, 'assessment_result_saved', 'assessment_result', $id);
            return $id;
        });
    }

    if ($action === 'publish_result') {
        institution_portal_require_active($context);
        $resultId = institution_portal_input_id($input, 'result_id');
        return institution_portal_transaction($pdo, static function () use ($pdo, $context, $resultId): int {
            $query = $pdo->prepare(
                'SELECT a.class_subject_id, r.published_at
                 FROM institution_assessment_results r
                 INNER JOIN institution_assessments a
                    ON a.id = r.assessment_id AND a.institution_id = r.institution_id
                 WHERE r.id = :result_id AND r.institution_id = :institution_id'
            );
            $query->execute(['result_id' => $resultId, 'institution_id' => $context['institution_id']]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($result)) {
                throw new DomainException('Nota não encontrada nesta instituição.');
            }
            institution_portal_require_class_subject($pdo, $context, (int) $result['class_subject_id']);
            $pdo->prepare(
                'UPDATE institution_assessment_results
                 SET published_at = CURRENT_TIMESTAMP
                 WHERE id = :result_id AND institution_id = :institution_id'
            )->execute(['result_id' => $resultId, 'institution_id' => $context['institution_id']]);
            institution_portal_audit($pdo, $context, 'assessment_result_published', 'assessment_result', $resultId);
            return $resultId;
        });
    }

    throw new InvalidArgumentException('Ação do portal institucional inválida.');
}
