<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || count($argv) !== 4 || $argv[1] !== 'grant') {
    fwrite(STDERR, "Uso: php db/provision_admin.php grant <id_usuario> <codigo_funcao>\n");
    exit(2);
}

$userId = filter_var($argv[2], FILTER_VALIDATE_INT);
$roleCode = $argv[3];
if ($userId === false || $userId <= 0 || preg_match('/^[a-z][a-z0-9_]{1,79}$/D', $roleCode) !== 1) {
    fwrite(STDERR, "Informe um ID positivo e um código de função válido.\n");
    exit(2);
}

require_once __DIR__ . '/../conexao.php';

$user = $pdo->prepare(
    'SELECT id, username, perfil FROM usuarios
     WHERE id = :id AND perfil IN ("estudante", "professor") LIMIT 1'
);
$user->execute(['id' => $userId]);
$account = $user->fetch(PDO::FETCH_ASSOC);
$role = $pdo->prepare('SELECT id, label FROM admin_roles WHERE code = :code AND is_enabled = 1 LIMIT 1');
$role->execute(['code' => $roleCode]);
$adminRole = $role->fetch(PDO::FETCH_ASSOC);
if (!is_array($account) || !is_array($adminRole)) {
    fwrite(STDERR, "Conta elegível ou função inexistente. Nenhuma alteração foi feita.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Atribuir a função \"" . $adminRole['label'] . "\" à conta #"
        . $account['id'] . " (" . $account['username'] . ", " . $account['perfil'] . ")?\n"
        . "Digite o motivo da atribuição: "
);
$reason = trim((string) fgets(STDIN));
if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
    fwrite(STDERR, "Motivo obrigatório, com até 500 caracteres.\n");
    exit(2);
}
fwrite(STDOUT, "Digite CONFIRMAR para aplicar: ");
if (trim((string) fgets(STDIN)) !== 'CONFIRMAR') {
    fwrite(STDOUT, "Cancelado; nenhuma alteração foi feita.\n");
    exit(0);
}

$pdo->beginTransaction();
try {
    $lockSuffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $accountLock = $pdo->prepare('SELECT id FROM usuarios WHERE id = :id' . $lockSuffix);
    $accountLock->execute(['id' => $userId]);
    if (!$accountLock->fetchColumn()) {
        throw new DomainException('A conta não existe mais; nenhuma alteração foi feita.');
    }
    $existing = $pdo->prepare(
        'SELECT id FROM platform_admin_assignments
         WHERE user_id = :user_id AND role_id = :role_id AND status = "active" LIMIT 1'
    );
    $existing->execute(['user_id' => $userId, 'role_id' => $adminRole['id']]);
    if ($existing->fetchColumn() !== false) {
        throw new DomainException('A conta já possui esta função ativa.');
    }
    $insert = $pdo->prepare(
        'INSERT INTO platform_admin_assignments (user_id, role_id, status)
         VALUES (:user_id, :role_id, "active")'
    );
    $insert->execute(['user_id' => $userId, 'role_id' => $adminRole['id']]);
    $audit = $pdo->prepare(
        'INSERT INTO platform_admin_audit
            (actor_user_id, actor_source, action, target_user_id, details)
         VALUES (NULL, "cli", "role.provisioned", :target_user_id, :details)'
    );
    $audit->execute([
        'target_user_id' => $userId,
        'details' => json_encode(
            ['role' => $roleCode, 'reason' => $reason],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ),
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Função atribuída e registrada na auditoria administrativa.\n");
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error instanceof DomainException) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    throw $error;
}
