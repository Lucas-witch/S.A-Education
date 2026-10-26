<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/user_preferences.php';
require_once SA_PROJECT_ROOT . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$account = require_settings_account();
verify_csrf($_POST['csrf'] ?? null);

$redirectWithFlash = static function (string $type, string $message): never {
    $_SESSION['settings_flash'] = ['type' => $type, 'message' => $message];
    header('Location: configuracoes.php#seguranca');
    exit;
};

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['change_password', 'revoke_sessions'], true)) {
    http_response_code(400);
    exit('Ação de configuração inválida.');
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
if ($currentPassword === '' || strlen($currentPassword) > 4096) {
    $redirectWithFlash('error', 'Informe sua senha atual para confirmar esta operação.');
}

try {
    $statement = $pdo->prepare('SELECT senha, auth_version FROM usuarios WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $account['user_id']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('[S.A Education][settings_password_read] ' . $exception->getMessage());
    $redirectWithFlash('error', 'Não foi possível validar sua conta agora. Tente novamente.');
}

if (!is_array($user) || !password_verify($currentPassword, (string) $user['senha'])) {
    $redirectWithFlash('error', 'A senha atual não confere.');
}

try {
    if ($action === 'change_password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        if (
            strlen($newPassword) < 8
            || strlen($newPassword) > 4096
            || !preg_match('/[A-Z]/', $newPassword)
            || !preg_match('/[\d\W]/', $newPassword)
        ) {
            $redirectWithFlash('error', 'A nova senha deve ter ao menos 8 caracteres, uma letra maiúscula e um número ou símbolo.');
        }
        if (!hash_equals($newPassword, $confirmPassword)) {
            $redirectWithFlash('error', 'A confirmação da nova senha não corresponde.');
        }
        if (password_verify($newPassword, (string) $user['senha'])) {
            $redirectWithFlash('error', 'Escolha uma senha diferente da atual.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($newHash)) {
            throw new RuntimeException('Não foi possível gerar o hash da nova senha.');
        }
        $update = $pdo->prepare(
            'UPDATE usuarios SET senha = :senha, auth_version = auth_version + 1 WHERE id = :id'
        );
        $update->execute(['senha' => $newHash, 'id' => $account['user_id']]);
    } else {
        $update = $pdo->prepare('UPDATE usuarios SET auth_version = auth_version + 1 WHERE id = :id');
        $update->execute(['id' => $account['user_id']]);
    }

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('A conta não foi atualizada.');
    }
    $newVersion = (int) $user['auth_version'] + 1;
    if ($account['institution_account']) {
        $_SESSION['instituicao_auth_version'] = $newVersion;
    } else {
        $_SESSION['usuario_auth_version'] = $newVersion;
    }
} catch (PDOException | RuntimeException $exception) {
    error_log('[S.A Education][settings_security_action] ' . $exception->getMessage());
    $redirectWithFlash('error', 'Não foi possível concluir a operação de segurança agora. Tente novamente.');
}

$redirectWithFlash(
    'success',
    $action === 'change_password'
        ? 'Senha alterada. As outras sessões da conta foram encerradas.'
        : 'As outras sessões da conta foram encerradas.'
);
