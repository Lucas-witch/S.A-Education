<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_rbac.php';

class PermissionDeniedException extends DomainException {}

function resolve_institution_plan_type(string $type, string $nature): ?string {
    $schoolType = in_array($type, ['fundamental', 'medio'], true)
        ? 'school'
        : (in_array($type, ['faculdade', 'universidade'], true) ? 'university' : null);
    $natureType = match ($nature) {
        'publica' => 'public',
        'particular' => 'private',
        default => null,
    };
    return $schoolType !== null && $natureType !== null
        ? $natureType . '_' . $schoolType
        : null;
}

function resolve_account_permissions(PDO $pdo, int $userId): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Usuário inválido para resolução de permissões.');
    }

    $userQuery = $pdo->prepare('SELECT id, perfil, professor_status FROM usuarios WHERE id = :id LIMIT 1');
    $userQuery->execute(['id' => $userId]);
    $user = $userQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        throw new DomainException('Conta não encontrada.');
    }

    $profile = (string) $user['perfil'];
    $admin = $userId === configured_admin_user_id();
    if ($admin) {
        return [
            'user_id' => $userId,
            'profile' => $profile,
            'is_admin' => true,
            'approved' => true,
            'professor_status' => 'approved',
            'institution_validated' => true,
            'plan_code' => 'administrator',
            'tier' => 'administrator',
            'premium' => true,
            'can_create_courses' => true,
            'monthly_lesson_limit' => null,
            'monthly_material_limit' => null,
            'monthly_question_bank_limit' => null,
            'questions_per_bank_limit' => null,
            'institution_member_limit' => null,
            'institution_class_limit' => null,
        ];
    }

    $institutionId = null;
    $institutionValidated = true;
    $institutionType = null;
    if ($profile === 'instituicao') {
        $institutionQuery = $pdo->prepare(
            'SELECT id, validation_status, tipo, natureza FROM instituicoes WHERE usuario_id = :user_id LIMIT 1'
        );
        $institutionQuery->execute(['user_id' => $userId]);
        $institution = $institutionQuery->fetch(PDO::FETCH_ASSOC);
        $institutionId = is_array($institution) ? (int) $institution['id'] : null;
        $institutionValidated = is_array($institution)
            && (string) $institution['validation_status'] === 'validated';
        if (is_array($institution)) {
            $institutionType = resolve_institution_plan_type(
                (string) $institution['tipo'],
                (string) $institution['natureza']
            );
        }
    }

    $approved = $profile !== 'professor' || (string) $user['professor_status'] === 'approved';
    $subscription = null;
    if ($profile === 'instituicao' && $institutionId !== null && $institutionType !== null) {
        $subscriptionQuery = $pdo->prepare(
            'SELECT s.plan_code, p.tier, p.can_create_courses, p.monthly_lesson_limit,
                    p.monthly_material_limit, p.monthly_question_bank_limit, p.questions_per_bank_limit,
                    p.institution_member_limit, p.institution_class_limit
             FROM subscriptions s
             INNER JOIN product_plans p ON p.code = s.plan_code
             WHERE s.institution_id = :institution_id
               AND p.account_type = "institution"
               AND p.institution_type = :institution_type
               AND s.status = "active"
               AND s.payment_confirmed_at IS NOT NULL
               AND (s.starts_at IS NULL OR s.starts_at <= CURRENT_TIMESTAMP)
               AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)
               AND p.is_enabled = 1
               AND NOT EXISTS (
                   SELECT 1 FROM subscriptions newer
                   WHERE newer.institution_id = s.institution_id AND newer.id > s.id
               )
             ORDER BY s.created_at DESC
             LIMIT 1'
        );
        $subscriptionQuery->execute(['institution_id' => $institutionId, 'institution_type' => $institutionType]);
        $subscription = $subscriptionQuery->fetch(PDO::FETCH_ASSOC);
    } elseif (in_array($profile, ['estudante', 'professor'], true)) {
        $subscriptionQuery = $pdo->prepare(
            'SELECT s.plan_code, s.income_verified_at, p.tier, p.requires_income_verification,
                    p.can_create_courses, p.monthly_lesson_limit, p.monthly_material_limit,
                    p.monthly_question_bank_limit, p.questions_per_bank_limit
             FROM subscriptions s
             INNER JOIN product_plans p ON p.code = s.plan_code
             WHERE s.user_id = :user_id
               AND s.status = "active"
               AND s.payment_confirmed_at IS NOT NULL
               AND (s.starts_at IS NULL OR s.starts_at <= CURRENT_TIMESTAMP)
               AND (s.expires_at IS NULL OR s.expires_at > CURRENT_TIMESTAMP)
               AND p.is_enabled = 1
               AND p.account_type = :account_type
               AND (p.requires_income_verification = 0 OR s.income_verified_at IS NOT NULL)
               AND NOT EXISTS (
                   SELECT 1 FROM subscriptions newer
                   WHERE newer.user_id = s.user_id AND newer.id > s.id
               )
             ORDER BY s.created_at DESC
             LIMIT 1'
        );
        $subscriptionQuery->execute([
            'user_id' => $userId,
            'account_type' => $profile === 'estudante' ? 'student' : 'teacher',
        ]);
        $subscription = $subscriptionQuery->fetch(PDO::FETCH_ASSOC);
    }

    $defaultCode = match ($profile) {
        'estudante' => 'student-free',
        'professor' => 'teacher-free',
        default => null,
    };
    $plan = is_array($subscription) ? $subscription : null;
    if ($plan === null && $defaultCode !== null) {
        $planQuery = $pdo->prepare(
            'SELECT code AS plan_code, tier, can_create_courses, monthly_lesson_limit,
                    monthly_material_limit, monthly_question_bank_limit, questions_per_bank_limit
             FROM product_plans WHERE code = :code AND is_enabled = 1 LIMIT 1'
        );
        $planQuery->execute(['code' => $defaultCode]);
        $plan = $planQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($plan === null && $profile === 'professor') {
        $plan = [
            'plan_code' => 'teacher-free',
            'tier' => 'free',
            'can_create_courses' => 0,
            'monthly_lesson_limit' => 0,
            'monthly_material_limit' => 0,
            'monthly_question_bank_limit' => 0,
            'questions_per_bank_limit' => 0,
        ];
    }

    $premium = $approved && $institutionValidated && is_array($subscription);
    $active = $approved && $institutionValidated
        && ($profile !== 'instituicao' || is_array($subscription));
    $readLimit = static fn (string $key): ?int => !$active
        ? 0
        : (isset($plan[$key]) && $plan[$key] !== '' ? (int) $plan[$key] : null);
    return [
        'user_id' => $userId,
        'profile' => $profile,
        'is_admin' => false,
        'approved' => $approved,
        'professor_status' => (string) $user['professor_status'],
        'institution_validated' => $institutionValidated,
        'plan_code' => (string) ($plan['plan_code'] ?? ''),
        'tier' => (string) ($plan['tier'] ?? 'free'),
        'premium' => $premium,
        'can_create_courses' => $premium && !empty($plan['can_create_courses']),
        'monthly_lesson_limit' => $readLimit('monthly_lesson_limit'),
        'monthly_material_limit' => $readLimit('monthly_material_limit'),
        'monthly_question_bank_limit' => $readLimit('monthly_question_bank_limit'),
        'questions_per_bank_limit' => $readLimit('questions_per_bank_limit'),
        'institution_member_limit' => $profile === 'instituicao' ? $readLimit('institution_member_limit') : null,
        'institution_class_limit' => $profile === 'instituicao' ? $readLimit('institution_class_limit') : null,
    ];
}

function institution_content_access_condition(
    string $institutionIdExpression,
    string $ownerUserParameter,
    string $memberUserParameter
): string {
    if (
        preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $institutionIdExpression) !== 1
        || preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $ownerUserParameter) !== 1
        || preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $memberUserParameter) !== 1
    ) {
        throw new InvalidArgumentException('Referência SQL inválida para acesso institucional.');
    }

    return 'EXISTS (
        SELECT 1 FROM instituicoes access_institution
        WHERE access_institution.id = ' . $institutionIdExpression . '
          AND access_institution.validation_status = "validated"
          AND NOT EXISTS (
              SELECT 1 FROM institution_portal_suspensions access_suspension
              WHERE access_suspension.institution_id = access_institution.id
          )
          AND (access_institution.usuario_id = ' . $ownerUserParameter . '
               OR EXISTS (
                   SELECT 1 FROM institution_members access_member
                   WHERE access_member.institution_id = access_institution.id
                     AND access_member.user_id = ' . $memberUserParameter . '
                     AND access_member.status = "active"
               ))
          AND EXISTS (
              SELECT 1 FROM subscriptions access_subscription
              INNER JOIN product_plans access_plan ON access_plan.code = access_subscription.plan_code
              WHERE access_subscription.institution_id = access_institution.id
                AND access_subscription.status = "active"
                AND access_subscription.payment_confirmed_at IS NOT NULL
                AND (access_subscription.starts_at IS NULL OR access_subscription.starts_at <= CURRENT_TIMESTAMP)
                AND (access_subscription.expires_at IS NULL OR access_subscription.expires_at > CURRENT_TIMESTAMP)
                AND access_plan.account_type = "institution"
                AND access_plan.institution_type = CASE
                    WHEN access_institution.tipo IN ("fundamental", "medio")
                         AND access_institution.natureza = "publica" THEN "public_school"
                    WHEN access_institution.tipo IN ("fundamental", "medio")
                         AND access_institution.natureza = "particular" THEN "private_school"
                    WHEN access_institution.tipo IN ("faculdade", "universidade")
                         AND access_institution.natureza = "publica" THEN "public_university"
                    WHEN access_institution.tipo IN ("faculdade", "universidade")
                         AND access_institution.natureza = "particular" THEN "private_university"
                    ELSE NULL
                END
                AND access_plan.is_enabled = 1
          )
    )';
}

function institution_learning_resource_access_condition(
    string $resourceType,
    string $resourceIdExpression,
    string $institutionIdExpression,
    string $ownerUserParameter,
    string $memberUserParameter
): string {
    if (
        !in_array($resourceType, ['publication', 'library_material'], true)
        || preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $resourceIdExpression) !== 1
        || preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $institutionIdExpression) !== 1
        || preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $ownerUserParameter) !== 1
        || preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $memberUserParameter) !== 1
    ) {
        throw new InvalidArgumentException('Referência SQL inválida para acesso curricular.');
    }

    return '(NOT EXISTS (
        SELECT 1 FROM institution_learning_resources scoped_resource
        WHERE scoped_resource.institution_id = ' . $institutionIdExpression . '
          AND scoped_resource.resource_type = "' . $resourceType . '"
          AND scoped_resource.resource_id = ' . $resourceIdExpression . '
    ) OR EXISTS (
        SELECT 1 FROM instituicoes resource_owner
        WHERE resource_owner.id = ' . $institutionIdExpression . '
          AND resource_owner.usuario_id = ' . $ownerUserParameter . '
    ) OR EXISTS (
        SELECT 1
        FROM institution_learning_resources authorized_resource
        INNER JOIN institution_class_subjects authorized_subject
            ON authorized_subject.id = authorized_resource.class_subject_id
           AND authorized_subject.institution_id = authorized_resource.institution_id
        INNER JOIN institution_members authorized_member
            ON authorized_member.institution_id = authorized_resource.institution_id
           AND authorized_member.user_id = ' . $memberUserParameter . '
           AND authorized_member.status = "active"
        WHERE authorized_resource.institution_id = ' . $institutionIdExpression . '
          AND authorized_resource.resource_type = "' . $resourceType . '"
          AND authorized_resource.resource_id = ' . $resourceIdExpression . '
          AND (
              (authorized_member.member_type = "teacher"
               AND authorized_subject.teacher_user_id = authorized_member.user_id)
              OR (authorized_member.member_type = "student" AND EXISTS (
                  SELECT 1 FROM institution_enrollments authorized_enrollment
                  WHERE authorized_enrollment.institution_id = authorized_subject.institution_id
                    AND authorized_enrollment.class_id = authorized_subject.class_id
                    AND authorized_enrollment.student_user_id = authorized_member.user_id
                    AND authorized_enrollment.status = "active"
              ))
          )
    ))';
}

function refresh_user_permission_session(PDO $pdo, int $userId): array {
    $permissions = resolve_account_permissions($pdo, $userId);
    if ((int) ($_SESSION['usuario_id'] ?? 0) !== $userId) {
        throw new DomainException('A sessão não corresponde ao usuário autorizado.');
    }

    $_SESSION['usuario_is_admin'] = $permissions['is_admin'];
    $_SESSION['usuario_plano'] = $permissions['plan_code'];
    $_SESSION['usuario_premium_ativo'] = $permissions['premium'];
    $_SESSION['usuario_aprovado'] = $permissions['approved'];
    $_SESSION['usuario_professor_status'] = $permissions['professor_status'];
    $_SESSION['usuario_pode_criar_cursos'] = $permissions['can_create_courses'];
    $_SESSION['usuario_pode_postar_aulas_ilimitadas'] = $permissions['is_admin']
        || $permissions['monthly_lesson_limit'] === null;
    $_SESSION['usuario_pode_postar_materiais_ilimitados'] = $permissions['is_admin']
        || $permissions['monthly_material_limit'] === null;

    return $permissions;
}

function require_admin_permission(string $permission = 'admin.dashboard.view'): void {
    global $pdo;
    $userId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId <= 0 || !admin_user_can($pdo, $userId, $permission)) {
        http_response_code(403);
        throw new DomainException('Você não possui permissão para esta operação administrativa.');
    }
}

function record_admin_action(PDO $pdo, string $action, string $targetType, ?int $targetId, array $details = []): void {
    $actorId = (int) ($_SESSION['usuario_id'] ?? 0);
    if (!admin_user_has_any_role($pdo, $actorId)) {
        throw new DomainException('Ação administrativa não autorizada.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO admin_audit_log (actor_user_id, action, target_type, target_id, details)
         VALUES (:actor_user_id, :action, :target_type, :target_id, :details)'
    );
    $statement->execute([
        'actor_user_id' => $actorId,
        'action' => $action,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR),
    ]);
}

function with_teacher_monthly_quota(PDO $pdo, int $userId, string $category, bool $publish, callable $operation): mixed {
    $categoryColumns = [
        'lesson' => 'monthly_lesson_limit',
        'material' => 'monthly_material_limit',
        'question' => 'monthly_question_bank_limit',
    ];
    if (!isset($categoryColumns[$category])) {
        throw new InvalidArgumentException('Categoria de cota desconhecida.');
    }

    if ($pdo->inTransaction()) {
        throw new LogicException('A verificação da cota precisa iniciar sua própria transação.');
    }
    $pdo->beginTransaction();
    try {
        $lockSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'SELECT id FROM usuarios WHERE id = :id FOR UPDATE'
            : 'SELECT id FROM usuarios WHERE id = :id';
        $lock = $pdo->prepare($lockSql);
        $lock->execute(['id' => $userId]);
        if (!$lock->fetchColumn()) {
            throw new DomainException('Conta não encontrada.');
        }

        $permissions = resolve_account_permissions($pdo, $userId);
        if ($permissions['profile'] !== 'professor' && !$permissions['is_admin']) {
            throw new PermissionDeniedException('Esta publicação é exclusiva do perfil de professor.');
        }
        if ($publish && !$permissions['approved']) {
            throw new PermissionDeniedException('O perfil de professor ainda aguarda aprovação administrativa.');
        }

        if ($publish && !$permissions['is_admin'] && !$permissions['premium']) {
            $limit = $permissions[$categoryColumns[$category]];
            if ($limit !== null) {
                $monthStart = gmdate('Y-m-01');
                $nextMonthStart = gmdate('Y-m-01', strtotime('+1 month', strtotime($monthStart . ' 00:00:00 UTC')));
                if ($category === 'lesson') {
                    $count = $pdo->prepare(
                        'SELECT COUNT(*) FROM publicacoes
                         WHERE usuario_id = :user_id AND tipo = "video_aula"
                           AND status IN ("publicado", "arquivado")
                           AND publicado_em >= :month_start AND publicado_em < :next_month_start'
                    );
                    $count->execute(['user_id' => $userId, 'month_start' => $monthStart, 'next_month_start' => $nextMonthStart]);
                    $used = (int) $count->fetchColumn();
                } elseif ($category === 'material') {
                    $count = $pdo->prepare(
                        'SELECT
                            (SELECT COUNT(*) FROM biblioteca
                             WHERE owner_user_id = :library_user_id
                               AND revisao_status IN ("pending_review", "approved", "archived")
                               AND criado_em >= :library_month_start AND criado_em < :library_next_month_start)
                            +
                            (SELECT COUNT(*) FROM publicacoes
                             WHERE usuario_id = :publication_user_id AND tipo IN ("material", "livro", "pdf")
                               AND status IN ("publicado", "arquivado")
                               AND publicado_em >= :publication_month_start AND publicado_em < :publication_next_month_start)'
                    );
                    $count->execute([
                        'library_user_id' => $userId,
                        'library_month_start' => $monthStart,
                        'library_next_month_start' => $nextMonthStart,
                        'publication_user_id' => $userId,
                        'publication_month_start' => $monthStart,
                        'publication_next_month_start' => $nextMonthStart,
                    ]);
                    $used = (int) $count->fetchColumn();
                } else {
                    $bank = $pdo->prepare(
                        'SELECT id, questions_count FROM teacher_question_banks
                         WHERE owner_user_id = :user_id AND month_start = :month_start
                         LIMIT 1'
                    );
                    $bank->execute(['user_id' => $userId, 'month_start' => $monthStart]);
                    $bankRow = $bank->fetch(PDO::FETCH_ASSOC);
                    $banksUsed = is_array($bankRow) ? 1 : 0;
                    $used = is_array($bankRow) ? (int) $bankRow['questions_count'] : 0;
                    $questionLimit = $permissions['questions_per_bank_limit'];
                    if ($banksUsed === 0 && (int) $limit < 1) {
                        throw new PermissionDeniedException('Você atingiu a cota mensal de bancos de questões. Salve a questão como rascunho.');
                    }
                    if ($used >= (int) $questionLimit) {
                        throw new PermissionDeniedException('O banco mensal atingiu o limite de questões. Salve a questão como rascunho.');
                    }
                }

                if ($category !== 'question' && $used >= (int) $limit) {
                    $categoryLabel = [
                        'lesson' => 'a cota mensal de videoaulas',
                        'material' => 'a cota mensal de materiais',
                        'question' => 'a cota mensal de bancos de questões',
                    ][$category];
                    throw new PermissionDeniedException('Você atingiu ' . $categoryLabel . '. Salve o conteúdo como rascunho ou aguarde o próximo mês.');
                }
            }
        }

        $bankId = null;
        if (
            $publish
            && $category === 'question'
            && $permissions['profile'] === 'professor'
            && !$permissions['is_admin']
            && !$permissions['premium']
        ) {
            $monthStart = gmdate('Y-m-01');
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $bankStatement = $pdo->prepare(
                    'INSERT INTO teacher_question_banks (owner_user_id, month_start, questions_count)
                     VALUES (:user_id, :month_start, 0)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
                );
                $bankStatement->execute(['user_id' => $userId, 'month_start' => $monthStart]);
                $bankId = (int) $pdo->lastInsertId();
            } else {
                $bankStatement = $pdo->prepare(
                    'INSERT OR IGNORE INTO teacher_question_banks (owner_user_id, month_start, questions_count)
                     VALUES (:user_id, :month_start, 0)'
                );
                $bankStatement->execute(['user_id' => $userId, 'month_start' => $monthStart]);
                $bankId = 0;
            }
            if ($bankId <= 0) {
                $lookup = $pdo->prepare(
                    'SELECT id FROM teacher_question_banks WHERE owner_user_id = :user_id AND month_start = :month_start'
                );
                $lookup->execute(['user_id' => $userId, 'month_start' => $monthStart]);
                $bankId = (int) $lookup->fetchColumn();
            }
        }

        $result = $operation($bankId, $publish, $permissions);
        if ($publish && $category === 'question' && $bankId !== null) {
            $increment = $pdo->prepare(
                'UPDATE teacher_question_banks SET questions_count = questions_count + 1 WHERE id = :id'
            );
            $increment->execute(['id' => $bankId]);
        }
        $pdo->commit();

        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
