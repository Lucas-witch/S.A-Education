<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/feed.php';

function admin_test_scenarios(): array {
    return [
        'student_free' => ['label' => 'Estudante gratuito', 'profile' => 'estudante', 'plan_code' => 'student-free', 'approved' => true, 'institution_validated' => true, 'subscription_active' => false],
        'student_premium' => ['label' => 'Estudante Premium', 'profile' => 'estudante', 'plan_code' => 'student-premium', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
        'teacher_pending' => ['label' => 'Professor não aprovado', 'profile' => 'professor', 'plan_code' => 'teacher-free', 'approved' => false, 'institution_validated' => true, 'subscription_active' => false],
        'teacher_free' => ['label' => 'Professor gratuito aprovado', 'profile' => 'professor', 'plan_code' => 'teacher-free', 'approved' => true, 'institution_validated' => true, 'subscription_active' => false],
        'teacher_premium' => ['label' => 'Professor Premium', 'profile' => 'professor', 'plan_code' => 'teacher-premium', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
        'teacher_expired' => ['label' => 'Professor com assinatura expirada', 'profile' => 'professor', 'plan_code' => 'teacher-free', 'subscription_plan_code' => 'teacher-premium', 'approved' => true, 'institution_validated' => true, 'subscription_active' => false],
        'institution_pending' => ['label' => 'Instituição pendente de validação', 'profile' => 'instituicao', 'plan_code' => 'institution-public-school', 'approved' => true, 'institution_validated' => false, 'subscription_active' => true],
        'institution_expired' => ['label' => 'Instituição com assinatura expirada', 'profile' => 'instituicao', 'plan_code' => 'institution-public-school', 'approved' => true, 'institution_validated' => true, 'subscription_active' => false],
        'institution_public_school' => ['label' => 'Escola pública', 'profile' => 'instituicao', 'plan_code' => 'institution-public-school', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
        'institution_public_university' => ['label' => 'Universidade pública', 'profile' => 'instituicao', 'plan_code' => 'institution-public-university', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
        'institution_private_school' => ['label' => 'Escola particular', 'profile' => 'instituicao', 'plan_code' => 'institution-private-school', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
        'institution_private_university' => ['label' => 'Universidade particular', 'profile' => 'instituicao', 'plan_code' => 'institution-private-university', 'approved' => true, 'institution_validated' => true, 'subscription_active' => true],
    ];
}

function admin_simulate_account_permissions(PDO $pdo, string $scenarioKey): array {
    $scenarios = admin_test_scenarios();
    if (!isset($scenarios[$scenarioKey])) {
        throw new InvalidArgumentException('Cenário de teste inválido.');
    }
    $scenario = $scenarios[$scenarioKey];
    $effectivePlanCode = $scenario['subscription_active']
        ? (string) ($scenario['subscription_plan_code'] ?? $scenario['plan_code'])
        : match ($scenario['profile']) {
            'estudante' => 'student-free',
            'professor' => 'teacher-free',
            default => (string) $scenario['plan_code'],
        };
    $planQuery = $pdo->prepare(
        'SELECT code, account_type, tier, institution_type, monthly_price,
                monthly_lesson_limit, monthly_material_limit, monthly_question_bank_limit,
                questions_per_bank_limit, can_create_courses, is_enabled
         FROM product_plans WHERE code = :code LIMIT 1'
    );
    $planQuery->execute(['code' => $effectivePlanCode]);
    $plan = $planQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($plan) || (int) $plan['is_enabled'] !== 1 || (string) $plan['account_type'] !== match ($scenario['profile']) {
        'estudante' => 'student',
        'professor' => 'teacher',
        default => 'institution',
    }) {
        throw new DomainException('O plano do cenário não está disponível no catálogo.');
    }

    $active = $scenario['approved'] && $scenario['institution_validated'];
    $premium = $active && $scenario['subscription_active'];
    $limits = static fn (string $field): ?int => !$active
        || ($scenario['profile'] === 'instituicao' && !$scenario['subscription_active'])
        ? 0
        : ($plan[$field] === null || $plan[$field] === '' ? null : (int) $plan[$field]);
    return [
        'scenario' => $scenario,
        'plan' => $plan,
        'premium' => $premium,
        'approved' => $scenario['approved'],
        'institution_validated' => $scenario['institution_validated'],
        'subscription_active' => $scenario['subscription_active'],
        'can_create_courses' => $premium && (int) $plan['can_create_courses'] === 1,
        'monthly_lesson_limit' => $limits('monthly_lesson_limit'),
        'monthly_material_limit' => $limits('monthly_material_limit'),
        'monthly_question_bank_limit' => $limits('monthly_question_bank_limit'),
        'questions_per_bank_limit' => $limits('questions_per_bank_limit'),
        'read_only' => true,
        'creates_payment' => false,
    ];
}

function admin_user_can_manage_official_content(PDO $pdo, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }
    if (admin_user_can($pdo, $userId, 'admin.content.official')) {
        return true;
    }
    $query = $pdo->prepare(
        'SELECT 1
         FROM usuarios u
         INNER JOIN official_content_editors editor
            ON editor.user_id = u.id AND editor.status = "active"
         WHERE u.id = :user_id AND u.perfil = "professor" AND u.professor_status = "approved"
         LIMIT 1'
    );
    $query->execute(['user_id' => $userId]);
    return $query->fetchColumn() !== false;
}

function admin_set_official_content_editor(PDO $pdo, int $targetUserId, bool $grant): void {
    require_admin_permission();
    if ($targetUserId <= 0 || $targetUserId === (int) $_SESSION['usuario_id']) {
        throw new InvalidArgumentException('Selecione um professor autorizado.');
    }
    $teacher = $pdo->prepare(
        'SELECT id FROM usuarios
         WHERE id = :id AND perfil = "professor" AND professor_status = "approved" LIMIT 1'
    );
    $teacher->execute(['id' => $targetUserId]);
    if (!$teacher->fetchColumn()) {
        throw new DomainException('Somente professores aprovados podem receber ou manter esta permissão.');
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $lockSuffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $existing = $pdo->prepare(
            'SELECT status FROM official_content_editors WHERE user_id = :user_id' . $lockSuffix
        );
        $existing->execute(['user_id' => $targetUserId]);
        $existingStatus = $existing->fetchColumn();
        if ($grant) {
            if ($existingStatus === false) {
                $statement = $pdo->prepare(
                    'INSERT INTO official_content_editors (user_id, granted_by, status)
                     VALUES (:user_id, :granted_by, "active")'
                );
            } else {
                $statement = $pdo->prepare(
                    'UPDATE official_content_editors
                     SET granted_by = :granted_by, status = "active", revoked_by = NULL, revoked_at = NULL
                     WHERE user_id = :user_id'
                );
            }
            $statement->execute([
                'user_id' => $targetUserId,
                'granted_by' => (int) $_SESSION['usuario_id'],
            ]);
        } else {
            if ($existingStatus !== 'active') {
                throw new DomainException('Este professor não possui permissão ativa para conteúdo oficial.');
            }
            $statement = $pdo->prepare(
                'UPDATE official_content_editors
                 SET status = "revoked", revoked_by = :actor_id, revoked_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id AND status = "active"'
            );
            $statement->execute([
                'actor_id' => (int) $_SESSION['usuario_id'],
                'user_id' => $targetUserId,
            ]);
        }
        record_admin_action(
            $pdo,
            $grant ? 'grant_official_content_editor' : 'revoke_official_content_editor',
            'teacher',
            $targetUserId
        );
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function admin_record_official_content_history(
    PDO $pdo,
    int $publicationId,
    string $action,
    array $snapshot
): void {
    $actorId = (int) ($_SESSION['usuario_id'] ?? 0);
    if (!admin_user_can_manage_official_content($pdo, $actorId)) {
        throw new PermissionDeniedException('Ação não autorizada para conteúdo oficial.');
    }
    if ($publicationId <= 0 || !in_array($action, ['created', 'updated', 'archived'], true)) {
        throw new InvalidArgumentException('Registro de histórico inválido.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO official_content_history (publication_id, actor_user_id, action, snapshot)
         VALUES (:publication_id, :actor_user_id, :action, :snapshot)'
    );
    $statement->execute([
        'publication_id' => $publicationId,
        'actor_user_id' => $actorId,
        'action' => $action,
        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if (admin_user_has_any_role($pdo, $actorId)) {
        record_admin_action($pdo, 'official_content_' . $action, 'publication', $publicationId);
    }
}

function admin_create_official_content(PDO $pdo, array $input): int {
    $actorId = (int) ($_SESSION['usuario_id'] ?? 0);
    if (!admin_user_can_manage_official_content($pdo, $actorId)) {
        throw new PermissionDeniedException('Ação não autorizada para conteúdo oficial.');
    }
    if (!is_string($input['kind'] ?? null) || !in_array($input['kind'], ['material', 'post', 'free_lesson'], true)) {
        throw new InvalidArgumentException('Formato não habilitado para conteúdo oficial.');
    }
    $input['visibility'] = 'publica';
    $input['room_id'] = '';
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $id = feed_create_publication($pdo, $actorId, $input, 'publicado', null, true);
        $snapshot = feed_get_publication($pdo, $id, $actorId, true);
        if (!is_array($snapshot)) {
            throw new RuntimeException('O conteúdo oficial foi criado, mas não pôde ser lido para auditoria.');
        }
        admin_record_official_content_history($pdo, $id, 'created', $snapshot);
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $id;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function admin_update_official_content(PDO $pdo, int $publicationId, array $input): void {
    $actorId = (int) $_SESSION['usuario_id'];
    if (!admin_user_can_manage_official_content($pdo, $actorId)) {
        throw new PermissionDeniedException('Ação não autorizada para conteúdo oficial.');
    }
    if (!is_string($input['kind'] ?? null) || !in_array($input['kind'], ['material', 'post', 'free_lesson'], true)) {
        throw new InvalidArgumentException('Formato não habilitado para conteúdo oficial.');
    }
    $existing = $pdo->prepare(
        'SELECT id, usuario_id FROM publicacoes WHERE id = :id AND is_official = 1 AND status = "publicado"'
    );
    $existing->execute(['id' => $publicationId]);
    $publication = $existing->fetch(PDO::FETCH_ASSOC);
    if (
        !is_array($publication)
        || ((int) $publication['usuario_id'] !== $actorId
            && !admin_user_can($pdo, $actorId, 'admin.content.official'))
    ) {
        throw new DomainException('Conteúdo oficial indisponível para alteração.');
    }
    $input['visibility'] = 'publica';
    $input['room_id'] = '';
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        feed_update_publication($pdo, $publicationId, $actorId, true, $input, true);
        $snapshot = feed_get_publication($pdo, $publicationId, $actorId, true);
        if (!is_array($snapshot)) {
            throw new RuntimeException('O conteúdo foi atualizado, mas não pôde ser lido para auditoria.');
        }
        admin_record_official_content_history($pdo, $publicationId, 'updated', $snapshot);
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function admin_archive_official_content(PDO $pdo, int $publicationId): void {
    $actorId = (int) $_SESSION['usuario_id'];
    if (!admin_user_can_manage_official_content($pdo, $actorId)) {
        throw new PermissionDeniedException('Ação não autorizada para conteúdo oficial.');
    }
    $existing = $pdo->prepare(
        'SELECT usuario_id FROM publicacoes WHERE id = :id
         AND is_official = 1 AND status = "publicado"'
    );
    $existing->execute(['id' => $publicationId]);
    $ownerId = $existing->fetchColumn();
    if (!$ownerId || ((int) $ownerId !== $actorId && !admin_user_can($pdo, $actorId, 'admin.content.official'))) {
        throw new DomainException('Conteúdo oficial indisponível para arquivamento.');
    }
    $snapshot = feed_get_publication($pdo, $publicationId, $actorId, true);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        feed_archive_publication($pdo, $publicationId, $actorId);
        admin_record_official_content_history($pdo, $publicationId, 'archived', is_array($snapshot) ? $snapshot : []);
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
