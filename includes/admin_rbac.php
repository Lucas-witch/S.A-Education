<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function admin_rbac_tables_available(PDO $pdo): bool {
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
            if ($result === false) {
                return false;
            }
            $tables = array_fill_keys(array_map('strval', $result->fetchAll(PDO::FETCH_COLUMN)), true);
            return isset($tables['admin_roles'], $tables['admin_role_permissions'], $tables['platform_admin_assignments'], $tables['admin_permissions'], $tables['platform_admin_audit']);
        }

        $result = $pdo->query('SHOW TABLES');
        if ($result === false) {
            return false;
        }
        $tables = [];
        foreach ($result->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
            $tables[(string) $tableName] = true;
        }
        return isset($tables['admin_roles'], $tables['admin_role_permissions'], $tables['platform_admin_assignments'], $tables['admin_permissions'], $tables['platform_admin_audit']);
    } catch (Throwable) {
        return false;
    }
}

function admin_user_can(PDO $pdo, int $userId, string $permission): bool {
    if ($userId <= 0 || preg_match('/^[a-z][a-z0-9_.-]{1,79}$/D', $permission) !== 1) {
        return false;
    }
    if ($userId === configured_admin_user_id()) {
        return true;
    }
    if (!admin_rbac_tables_available($pdo)) {
        return false;
    }

    $statement = $pdo->prepare(
        'SELECT 1
         FROM platform_admin_assignments assignment
         INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id AND admin_role.is_enabled = 1
         INNER JOIN admin_role_permissions role_permission ON role_permission.role_id = admin_role.id
         WHERE assignment.user_id = :user_id
           AND assignment.status = "active"
           AND role_permission.permission_code = :permission
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId, 'permission' => $permission]);
    return $statement->fetchColumn() !== false;
}

function admin_user_has_any_role(PDO $pdo, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }
    if ($userId === configured_admin_user_id()) {
        return true;
    }
    if (!admin_rbac_tables_available($pdo)) {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT 1 FROM platform_admin_assignments assignment
         INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id AND admin_role.is_enabled = 1
         WHERE assignment.user_id = :user_id AND assignment.status = "active" LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchColumn() !== false;
}

function current_admin_permissions(PDO $pdo): array {
    $userId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($userId === false || $userId <= 0) {
        return [];
    }
    if ($userId === configured_admin_user_id()) {
        return ['*'];
    }
    if (!admin_rbac_tables_available($pdo)) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT DISTINCT admin_permission.code
         FROM platform_admin_assignments assignment
         INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id AND admin_role.is_enabled = 1
         INNER JOIN admin_role_permissions role_permission ON role_permission.role_id = admin_role.id
         INNER JOIN admin_permissions admin_permission ON admin_permission.code = role_permission.permission_code
         WHERE assignment.user_id = :user_id AND assignment.status = "active"
         ORDER BY admin_permission.code'
    );
    $statement->execute(['user_id' => $userId]);
    return array_values($statement->fetchAll(PDO::FETCH_COLUMN));
}

function admin_user_role_labels(PDO $pdo, int $userId): array {
    if (!admin_rbac_tables_available($pdo)) {
        return [];
    }
    $statement = $pdo->prepare(
        'SELECT admin_role.code, admin_role.label
         FROM platform_admin_assignments assignment
         INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id AND admin_role.is_enabled = 1
         WHERE assignment.user_id = :user_id AND assignment.status = "active"
         ORDER BY admin_role.label'
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function admin_record_security_event(
    PDO $pdo,
    ?int $actorUserId,
    string $actorSource,
    string $action,
    ?int $targetUserId,
    array $details = []
): void {
    if (!in_array($actorSource, ['cli', 'user'], true)
        || preg_match('/^[a-z][a-z0-9_.-]{1,79}$/D', $action) !== 1
    ) {
        throw new InvalidArgumentException('Evento administrativo inválido.');
    }
    $statement = $pdo->prepare(
        'INSERT INTO platform_admin_audit
            (actor_user_id, actor_source, action, target_user_id, details)
         VALUES (:actor_user_id, :actor_source, :action, :target_user_id, :details)'
    );
    $statement->execute([
        'actor_user_id' => $actorUserId,
        'actor_source' => $actorSource,
        'action' => $action,
        'target_user_id' => $targetUserId,
        'details' => $details === []
            ? null
            : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function admin_assign_role(PDO $pdo, int $targetUserId, string $roleCode, string $reason): void {
    require_admin_permission('admin.roles.manage');
    $reason = trim($reason);
    if ($targetUserId <= 0 || $reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Informe a conta e um motivo válido (até 500 caracteres).');
    }
    $actorId = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($targetUserId === $actorId && !is_admin_user()) {
        throw new DomainException('Você não pode conceder funções administrativas à própria conta.');
    }
    if ($roleCode === 'platform_admin' && !is_admin_user()) {
        throw new DomainException('Somente o superadministrador pode conceder a função de administrador da plataforma.');
    }

    $user = $pdo->prepare(
        'SELECT id, perfil, professor_status FROM usuarios
         WHERE id = :id AND perfil IN ("estudante", "professor") LIMIT 1'
    );
    $user->execute(['id' => $targetUserId]);
    $account = $user->fetch(PDO::FETCH_ASSOC);
    if (!is_array($account)) {
        throw new DomainException('A conta selecionada não pode receber função administrativa.');
    }
    $role = $pdo->prepare('SELECT id FROM admin_roles WHERE code = :code AND is_enabled = 1 LIMIT 1');
    $role->execute(['code' => $roleCode]);
    $roleId = $role->fetchColumn();
    if ($roleId === false) {
        throw new InvalidArgumentException('Função administrativa inválida.');
    }
    if (
        $roleCode === 'content_reviewer'
        && ((string) $account['perfil'] !== 'professor' || (string) $account['professor_status'] !== 'approved')
    ) {
        throw new DomainException('Revisores de conteúdo devem ser professores aprovados.');
    }

    $pdo->beginTransaction();
    try {
        $lockSuffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $accountLock = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id' . $lockSuffix);
        $accountLock->execute(['id' => $targetUserId]);
        if (!$accountLock->fetchColumn()) {
            throw new DomainException('A conta selecionada não pode receber função administrativa.');
        }
        $active = $pdo->prepare(
            'SELECT id FROM platform_admin_assignments
             WHERE user_id = :user_id AND role_id = :role_id AND status = "active" LIMIT 1'
        );
        $active->execute(['user_id' => $targetUserId, 'role_id' => $roleId]);
        if ($active->fetchColumn() !== false) {
            throw new DomainException('A conta já possui esta função ativa.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO platform_admin_assignments (user_id, role_id, assigned_by, status)
             VALUES (:user_id, :role_id, :assigned_by, "active")'
        );
        $insert->execute([
            'user_id' => $targetUserId,
            'role_id' => $roleId,
            'assigned_by' => (int) $_SESSION['usuario_id'],
        ]);
        admin_record_security_event(
            $pdo,
            (int) $_SESSION['usuario_id'],
            'user',
            'role.assigned',
            $targetUserId,
            ['role' => $roleCode, 'reason' => $reason]
        );
        record_admin_action($pdo, 'admin_role_assigned', 'user', $targetUserId, [
            'role' => $roleCode,
            'reason' => $reason,
        ]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function admin_revoke_role(PDO $pdo, int $assignmentId, string $reason): void {
    require_admin_permission('admin.roles.manage');
    $reason = trim($reason);
    if ($assignmentId <= 0 || $reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
        throw new InvalidArgumentException('Informe a função e um motivo válido (até 500 caracteres).');
    }
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare(
            'SELECT assignment.id, assignment.user_id, admin_role.code
             FROM platform_admin_assignments assignment
             INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id
             WHERE assignment.id = :id AND assignment.status = "active" LIMIT 1'
        );
        $query->execute(['id' => $assignmentId]);
        $assignment = $query->fetch(PDO::FETCH_ASSOC);
        if (!is_array($assignment)) {
            throw new DomainException('A função já foi revogada ou não existe.');
        }
        $targetId = (int) $assignment['user_id'];
        if ((string) $assignment['code'] === 'platform_admin' && configured_admin_user_id() === null) {
            $lockSuffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $rootRoleLock = $pdo->prepare(
                'SELECT id FROM admin_roles WHERE code = "platform_admin"' . $lockSuffix
            );
            $rootRoleLock->execute();
            $rootRoleLock->fetchColumn();
            $activeAdmins = $pdo->prepare(
                'SELECT COUNT(*) FROM platform_admin_assignments assignment
                 INNER JOIN admin_roles admin_role ON admin_role.id = assignment.role_id
                 WHERE admin_role.code = "platform_admin" AND assignment.status = "active"'
            );
            $activeAdmins->execute();
            if ((int) $activeAdmins->fetchColumn() <= 1) {
                throw new DomainException('Mantenha pelo menos uma função de administrador da plataforma ativa.');
            }
        }
        record_admin_action($pdo, 'admin_role_revoked', 'user', $targetId, [
            'role' => (string) $assignment['code'],
            'reason' => $reason,
        ]);
        $update = $pdo->prepare(
            'UPDATE platform_admin_assignments
             SET status = "revoked", revoked_by = :actor_id, revoked_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = "active"'
        );
        $update->execute(['actor_id' => (int) $_SESSION['usuario_id'], 'id' => $assignmentId]);
        if ($update->rowCount() !== 1) {
            throw new DomainException('A função foi alterada por outra sessão.');
        }
        admin_record_security_event(
            $pdo,
            (int) $_SESSION['usuario_id'],
            'user',
            'role.revoked',
            $targetId,
            ['role' => (string) $assignment['code'], 'reason' => $reason]
        );
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
