<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
send_private_no_store_headers();
header('Referrer-Policy: no-referrer');

if (isset($_SESSION['usuario_id'])) {
    header('Location: perfil-dashboard.php');
    exit;
}

$erro = $_SESSION['erro_redefinir'] ?? '';
$sucesso = $_SESSION['sucesso'] ?? '';
unset($_SESSION['erro_redefinir'], $_SESSION['sucesso']);
$token = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
$tokenInformado = array_key_exists('token', $_GET);
$tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Redefinir senha</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card login-card">
        <a class="back" href="login.php">←</a>

        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app.png" alt="Logotype"></div>
            <h1><?= $tokenValido ? 'Criar nova senha' : 'Recuperar senha' ?></h1>
            <span><?= $tokenValido ? 'Defina uma nova senha para sua conta.' : 'Enviaremos um link seguro para o e-mail cadastrado.' ?></span>
        </div>

        <?php if ($erro): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($tokenInformado && !$tokenValido): ?>
            <div class="alert error" role="alert">Este link é inválido. Solicite um novo link de redefinição.</div>
        <?php endif; ?>

        <form action="processa_redefinir_senha.php" method="POST" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">

            <?php if ($tokenValido): ?>
                <input type="hidden" name="token" value="<?= esc($token) ?>">
                <label>Nova senha:
                    <input type="password" name="nova_senha" autocomplete="new-password" required>
                </label>
                <label>Confirmar nova senha:
                    <input type="password" name="confirmar_nova_senha" autocomplete="new-password" required>
                </label>
                <button class="btn btn-primary" type="submit">Salvar nova senha</button>
            <?php else: ?>
                <label>E-mail ou nome de usuário:
                    <input type="text" name="identificador" placeholder="Digite seu e-mail ou usuário" autocomplete="username" required>
                </label>
                <button class="btn btn-primary" type="submit">Enviar link de redefinição</button>
            <?php endif; ?>
        </form>

        <p class="switch"><a href="login.php">Voltar para o login</a></p>
    </section>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
