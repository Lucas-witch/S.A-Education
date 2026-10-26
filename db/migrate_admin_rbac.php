<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== 'up' || count($argv) !== 2) {
    fwrite(STDERR, "Uso: php db/migrate_admin_rbac.php up\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$requiredTables = ['usuarios', 'admin_audit_log'];
$tableCheck = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
);
foreach ($requiredTables as $table) {
    $tableCheck->execute(['table_name' => $table]);
    if ((int) $tableCheck->fetchColumn() !== 1) {
        throw new RuntimeException('Execute as migrações-base e migrate_permissions.php up antes desta migração.');
    }
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admin_roles (
        id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(80) NOT NULL,
        label VARCHAR(120) NOT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_admin_roles_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admin_permissions (
        code VARCHAR(80) NOT NULL,
        label VARCHAR(160) NOT NULL,
        PRIMARY KEY (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admin_role_permissions (
        role_id SMALLINT UNSIGNED NOT NULL,
        permission_code VARCHAR(80) NOT NULL,
        PRIMARY KEY (role_id, permission_code),
        CONSTRAINT fk_admin_role_permission_role FOREIGN KEY (role_id)
            REFERENCES admin_roles (id) ON DELETE CASCADE,
        CONSTRAINT fk_admin_role_permission_permission FOREIGN KEY (permission_code)
            REFERENCES admin_permissions (code) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS platform_admin_assignments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        role_id SMALLINT UNSIGNED NOT NULL,
        status ENUM('active','revoked') NOT NULL DEFAULT 'active',
        assigned_by INT(11) DEFAULT NULL,
        revoked_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_platform_admin_assignment_user (user_id, status),
        KEY idx_platform_admin_assignment_role (role_id, status),
        CONSTRAINT fk_platform_admin_assignment_user FOREIGN KEY (user_id)
            REFERENCES usuarios (id) ON DELETE CASCADE,
        CONSTRAINT fk_platform_admin_assignment_role FOREIGN KEY (role_id)
            REFERENCES admin_roles (id) ON DELETE RESTRICT,
        CONSTRAINT fk_platform_admin_assignment_granter FOREIGN KEY (assigned_by)
            REFERENCES usuarios (id) ON DELETE SET NULL,
        CONSTRAINT fk_platform_admin_assignment_revoker FOREIGN KEY (revoked_by)
            REFERENCES usuarios (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS platform_admin_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_user_id INT(11) DEFAULT NULL,
        actor_source ENUM('cli','user') NOT NULL,
        action VARCHAR(80) NOT NULL,
        target_user_id INT(11) DEFAULT NULL,
        details JSON DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_platform_admin_audit_created (created_at, id),
        KEY idx_platform_admin_audit_target (target_user_id, created_at),
        CONSTRAINT fk_platform_admin_audit_actor FOREIGN KEY (actor_user_id)
            REFERENCES usuarios (id) ON DELETE SET NULL,
        CONSTRAINT fk_platform_admin_audit_target FOREIGN KEY (target_user_id)
            REFERENCES usuarios (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$permissions = [
    'admin.dashboard.view' => 'Visualizar painel administrativo',
    'admin.roles.manage' => 'Atribuir e revogar funções administrativas',
    'admin.audit.read' => 'Consultar registros de auditoria',
    'admin.users.manage' => 'Gerenciar usuários e instituições',
    'admin.library.review' => 'Revisar materiais da biblioteca',
    'admin.content.official' => 'Gerenciar conteúdo oficial',
    'admin.feed.moderate' => 'Moderar publicações denunciadas',
    'admin.reports.manage' => 'Analisar denúncias',
    'admin.plans.manage' => 'Gerenciar planos e assinaturas',
];
$permissionInsert = $pdo->prepare(
    'INSERT INTO admin_permissions (code, label) VALUES (:code, :label)
     ON DUPLICATE KEY UPDATE label = VALUES(label)'
);
foreach ($permissions as $code => $label) {
    $permissionInsert->execute(['code' => $code, 'label' => $label]);
}

$roles = [
    'platform_admin' => [
        'Administrador da plataforma',
        array_keys($permissions),
    ],
    'moderator' => [
        'Moderador',
        ['admin.dashboard.view', 'admin.feed.moderate', 'admin.reports.manage'],
    ],
    'content_reviewer' => [
        'Revisor de conteúdo',
        ['admin.dashboard.view', 'admin.library.review', 'admin.content.official'],
    ],
    'auditor' => [
        'Auditor',
        ['admin.dashboard.view', 'admin.audit.read'],
    ],
];
$roleInsert = $pdo->prepare(
    'INSERT INTO admin_roles (code, label, is_enabled) VALUES (:code, :label, 1)
     ON DUPLICATE KEY UPDATE label = VALUES(label)'
);
$roleLookup = $pdo->prepare('SELECT id FROM admin_roles WHERE code = :code');
$permissionGrant = $pdo->prepare(
    'INSERT IGNORE INTO admin_role_permissions (role_id, permission_code) VALUES (:role_id, :permission_code)'
);
foreach ($roles as $code => [$label, $rolePermissions]) {
    $roleInsert->execute(['code' => $code, 'label' => $label]);
    $roleLookup->execute(['code' => $code]);
    $roleId = (int) $roleLookup->fetchColumn();
    foreach ($rolePermissions as $permissionCode) {
        $permissionGrant->execute(['role_id' => $roleId, 'permission_code' => $permissionCode]);
    }
}

echo "Funções administrativas e permissões aplicadas sem alterar usuários existentes.\n";
