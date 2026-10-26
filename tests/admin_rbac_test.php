<?php
declare(strict_types=1);

putenv('APP_ADMIN_USER_ID=1');
require_once __DIR__ . '/../includes/permissions.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY, perfil TEXT NOT NULL, username TEXT NOT NULL,
        professor_status TEXT NOT NULL DEFAULT "not_applicable"
    )'
);
$pdo->exec(
    'CREATE TABLE admin_roles (
        id INTEGER PRIMARY KEY, code TEXT NOT NULL, label TEXT NOT NULL, is_enabled INTEGER NOT NULL DEFAULT 1
    )'
);
$pdo->exec('CREATE TABLE admin_permissions (code TEXT PRIMARY KEY, label TEXT NOT NULL)');
$pdo->exec(
    'CREATE TABLE admin_role_permissions (
        role_id INTEGER NOT NULL, permission_code TEXT NOT NULL, PRIMARY KEY (role_id, permission_code)
    )'
);
$pdo->exec(
    'CREATE TABLE platform_admin_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, role_id INTEGER NOT NULL,
        status TEXT NOT NULL, assigned_by INTEGER, revoked_by INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        revoked_at TEXT
    )'
);
$pdo->exec(
    'CREATE TABLE platform_admin_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, actor_source TEXT NOT NULL,
        action TEXT NOT NULL, target_user_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->exec(
    'CREATE TABLE admin_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER NOT NULL, action TEXT NOT NULL,
        target_type TEXT NOT NULL, target_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$pdo->exec(
    "INSERT INTO usuarios (id, perfil, username) VALUES
        (1, 'estudante', 'root-admin'), (2, 'professor', 'moderator'), (3, 'estudante', 'ordinary-user')"
);
$pdo->exec("INSERT INTO admin_roles (id, code, label) VALUES (1, 'moderator', 'Moderador')");
$pdo->exec("INSERT INTO admin_roles (id, code, label) VALUES (2, 'platform_admin', 'Administrador da plataforma')");
$pdo->exec(
    "INSERT INTO admin_permissions (code, label) VALUES
        ('admin.dashboard.view', 'Dashboard'), ('admin.reports.manage', 'Denúncias'),
        ('admin.roles.manage', 'Funções')"
);
$pdo->exec(
    "INSERT INTO admin_role_permissions (role_id, permission_code)
        VALUES (1, 'admin.dashboard.view'), (1, 'admin.reports.manage'),
            (2, 'admin.roles.manage')"
);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$_SESSION['usuario_id'] = 3;
$assert(!is_admin_user(), 'Um valor de sessão não pode conceder superadmin.');
$assert(!admin_user_can($pdo, 3, 'admin.reports.manage'), 'Conta sem atribuição não recebe permissões.');
$assert(!admin_user_can($pdo, 2, 'admin.users.manage'), 'Moderador não recebe permissões fora de sua função.');
$assert(!admin_user_can($pdo, 2, 'admin.reports.manage'), 'Um nome de usuário não concede acesso sem atribuição ativa.');
$assert(admin_user_can($pdo, 1, 'admin.users.manage'), 'ID configurado conserva acesso de superadmin.');
$assert(current_admin_permissions($pdo) === [], 'A sessão comum não pode herdar permissões da conta configurada.');

$_SESSION['usuario_id'] = 1;
admin_assign_role($pdo, 2, 'moderator', 'Cobertura do turno de moderação');
$assignmentId = (int) $pdo->query(
    'SELECT id FROM platform_admin_assignments WHERE user_id = 2 AND status = "active"'
)->fetchColumn();
$assert(admin_user_can($pdo, 2, 'admin.reports.manage'), 'A atribuição concedida passa a valer imediatamente.');
$assert(!admin_user_can($pdo, 2, 'admin.users.manage'), 'A função concedida não amplia permissões não atribuídas.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM platform_admin_audit')->fetchColumn() === 1, 'A atribuição gera evento de segurança.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn() === 1, 'A atribuição gera auditoria administrativa.');

admin_revoke_role($pdo, $assignmentId, 'Fim da cobertura');
$assert(!admin_user_can($pdo, 2, 'admin.reports.manage'), 'Revogar a atribuição remove a permissão imediatamente.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM platform_admin_audit')->fetchColumn() === 2, 'A revogação gera evento de segurança.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn() === 2, 'A revogação gera auditoria administrativa.');

$selfGrantRejected = false;
try {
    $_SESSION['usuario_id'] = 2;
    admin_assign_role($pdo, 2, 'moderator', 'Não pode conceder a si mesmo');
} catch (DomainException) {
    $selfGrantRejected = true;
}
$assert($selfGrantRejected, 'Conta delegada não pode conceder função a si própria.');

putenv('APP_ADMIN_USER_ID');
$_SESSION['usuario_id'] = 2;
$pdo->exec(
    "INSERT INTO platform_admin_assignments (user_id, role_id, status) VALUES (2, 2, 'active')"
);
$lastAdminId = (int) $pdo->lastInsertId();
$lastAdminProtected = false;
try {
    admin_revoke_role($pdo, $lastAdminId, 'Proteção contra bloqueio da administração');
} catch (DomainException) {
    $lastAdminProtected = true;
}
$assert($lastAdminProtected, 'A última conta platform_admin permanece ativa sem administrador de recuperação.');
$pdo->exec(
    "INSERT INTO platform_admin_assignments (user_id, role_id, status) VALUES (3, 2, 'active')"
);
$secondAdminId = (int) $pdo->lastInsertId();
admin_revoke_role($pdo, $secondAdminId, 'Remover acesso administrativo extra');
$assert(
    (int) $pdo->query("SELECT COUNT(*) FROM platform_admin_assignments WHERE role_id = 2 AND status = 'active'")->fetchColumn() === 1,
    'Uma atribuição adicional pode ser revogada sem remover o último administrador.'
);

echo "OK: superadmin configurado, RBAC least-privilege, atribuição/revogação e auditoria validados.\n";
