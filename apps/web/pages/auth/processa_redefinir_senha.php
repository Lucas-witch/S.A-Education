<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/helpers.php';
session_configure_security();
session_start();
send_private_no_store_headers();
require_once SA_PROJECT_ROOT . '/includes/rate_limit.php';
require_once SA_PROJECT_ROOT . '/includes/smtp_mailer.php';
require_once SA_PROJECT_ROOT . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf($_POST['csrf'] ?? null);

$token = trim((string) ($_POST['token'] ?? ''));
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$redirect = static function (string $message, string $type = 'error'): never {
    $_SESSION[$type === 'success' ? 'sucesso' : 'erro_redefinir'] = $message;
    header('Location: redefinir-senha.php');
    exit;
};

try {
    $wait = auth_rate_limit_remaining($pdo, 'password-reset', $clientIp, 10, 900);
    if ($wait > 0) {
        $redirect('Se os dados corresponderem a uma conta, você receberá as instruções por e-mail. Aguarde alguns minutos antes de tentar novamente.', 'success');
    }

    if ($token === '') {
        $identifier = trim((string) ($_POST['identificador'] ?? ''));
        if ($identifier === '' || mb_strlen($identifier, 'UTF-8') > 255) {
            $redirect('Informe um e-mail ou nome de usuário válido.');
        }

        $baseUrl = app_base_url();

        auth_rate_limit_record_failure($pdo, 'password-reset', $clientIp, 10, 900);
        $stmt = $pdo->prepare(
            'SELECT id, email
             FROM usuarios
             WHERE LOWER(email) = LOWER(:email)
                OR LOWER(username) = LOWER(:username)
             LIMIT 1'
        );
        $stmt->execute(['email' => $identifier, 'username' => $identifier]);
        $usuario = $stmt->fetch();

        if ($usuario && filter_var((string) $usuario['email'], FILTER_VALIDATE_EMAIL)) {
            $accountWait = auth_rate_limit_remaining(
                $pdo,
                'password-reset-account',
                (string) ((int) $usuario['id']),
                3,
                3600
            );
            if ($accountWait === 0) {
                auth_rate_limit_record_failure(
                    $pdo,
                    'password-reset-account',
                    (string) ((int) $usuario['id']),
                    3,
                    3600
                );

                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $pdo->beginTransaction();
                $delete = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
                $delete->execute(['user_id' => (int) $usuario['id']]);
                $insert = $pdo->prepare(
                    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                     VALUES (:user_id, :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))'
                );
                $insert->execute([
                    'user_id' => (int) $usuario['id'],
                    'token_hash' => $tokenHash,
                ]);
                $pdo->commit();

                $resetUrl = $baseUrl . '/redefinir-senha.php?token=' . rawurlencode($rawToken);
                $message = "Recebemos uma solicitação para redefinir sua senha.\r\n\r\n"
                    . "Use o link abaixo em até uma hora. Ele pode ser usado uma única vez:\r\n"
                    . $resetUrl . "\r\n\r\n"
                    . "Se você não solicitou a alteração, ignore esta mensagem.";

                try {
                    send_smtp_message(
                        (string) $usuario['email'],
                        'Redefinição de senha — S.A Education',
                        $message
                    );
                } catch (RuntimeException $mailError) {
                    $cleanup = $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = :token_hash');
                    $cleanup->execute(['token_hash' => $tokenHash]);
                    error_log('[S.A Education][password_reset_mail] ' . $mailError->getMessage());
                    throw new RuntimeException('Não foi possível enviar o link de redefinição.', 0, $mailError);
                }
            }
        }

        $redirect('Se existir uma conta correspondente, enviaremos as instruções por e-mail. Se não receber, tente novamente mais tarde.', 'success');
    }

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $redirect('Este link de redefinição é inválido ou expirou. Solicite um novo link.');
    }

    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarNovaSenha = (string) ($_POST['confirmar_nova_senha'] ?? '');
    if (
        strlen($novaSenha) < 8
        || !preg_match('/[A-Z]/', $novaSenha)
        || !preg_match('/[\d\W]/', $novaSenha)
    ) {
        $redirect('A nova senha precisa ter 8 caracteres, uma letra maiúscula e um número ou símbolo.');
    }
    if (!hash_equals($novaSenha, $confirmarNovaSenha)) {
        $redirect('As novas senhas não coincidem.');
    }

    $pdo->beginTransaction();
    $lookup = $pdo->prepare(
        'SELECT id, user_id
         FROM password_reset_tokens
         WHERE token_hash = :token_hash
           AND used_at IS NULL
           AND expires_at > UTC_TIMESTAMP()
         LIMIT 1
         FOR UPDATE'
    );
    $lookup->execute(['token_hash' => hash('sha256', $token)]);
    $reset = $lookup->fetch();
    if (!$reset) {
        $pdo->rollBack();
        $redirect('Este link de redefinição é inválido ou expirou. Solicite um novo link.');
    }

    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Falha ao gerar o hash da nova senha.');
    }

    $updateUser = $pdo->prepare(
        'UPDATE usuarios
         SET senha = :senha, auth_version = auth_version + 1
         WHERE id = :id'
    );
    $updateUser->execute(['senha' => $hash, 'id' => (int) $reset['user_id']]);

    $markUsed = $pdo->prepare('UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id');
    $markUsed->execute(['id' => (int) $reset['id']]);
    $removeOtherTokens = $pdo->prepare(
        'DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id <> :token_id'
    );
    $removeOtherTokens->execute([
        'user_id' => (int) $reset['user_id'],
        'token_id' => (int) $reset['id'],
    ]);
    $pdo->commit();

    $redirect('Sua senha foi redefinida. Entre novamente com a nova senha.', 'success');
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][password_reset][PDO] ' . $error->getMessage());
    $redirect('Não foi possível concluir a solicitação agora. Tente novamente mais tarde.');
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[S.A Education][password_reset] ' . $error->getMessage());
    $redirect('Não foi possível concluir a solicitação agora. Tente novamente mais tarde.');
}
