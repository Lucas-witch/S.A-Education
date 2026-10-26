<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/helpers.php';
session_configure_security();
session_start();
send_private_no_store_headers();

if (isset($_SESSION['instituicao_usuario_id'])) {
    header('Location: instituicao-dashboard.php');
    exit;
}
if (isset($_SESSION['usuario_id'])) {
    header('Location: perfil-dashboard.php');
    exit;
}

$erro = $_SESSION['erro_login_instituicao'] ?? '';
$sucesso = $_SESSION['sucesso_instituicao'] ?? '';
unset($_SESSION['erro_login_instituicao'], $_SESSION['sucesso_instituicao']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Acesso institucional</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card login-card">
        <a class="back" href="login.php" aria-label="Voltar ao login de estudante">←</a>
        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app-512.png" alt="S.A Education" width="180" height="180"></div>
            <h1>Acesso institucional</h1>
            <span>Entre com as credenciais da sua instituição.</span>
        </div>
        <?php if ($erro !== ''): ?>
            <div class="alert error" role="alert"><?= esc($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso !== ''): ?>
            <div class="alert success" role="status"><?= esc($sucesso) ?></div>
        <?php endif; ?>
        <form action="processa_login_instituicao.php" method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <label>E-mail ou nome de usuário:
                <input type="text" name="login" autocomplete="username" required>
            </label>
            <label>Senha:
                <input type="password" name="senha" autocomplete="current-password" required>
            </label>
            <button class="btn btn-primary" type="submit">Entrar como instituição</button>
        </form>
        <p class="switch">Ainda não tem acesso? <a href="cadastro-instituicao.php">Cadastrar instituição</a></p>
        <p class="switch"><a href="redefinir-senha.php">Esqueci minha senha</a></p>
    </section>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
