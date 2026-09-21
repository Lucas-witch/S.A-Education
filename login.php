<?php
declare(strict_types=1);
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = $_SESSION['erro_login'] ?? '';
$sucesso = $_SESSION['sucesso'] ?? '';
unset($_SESSION['erro_login'], $_SESSION['sucesso']);
require_once __DIR__ . '/includes/helpers.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Entrar</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card login-card">
        <a class="back" href="index.php">←</a>

        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app.png" alt="Logotype"></div>
            <h1>Seja bem vindo!</h1>
            <span>Entre na sua conta para continuar aprendendo.</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form action="processa_login.php" method="POST" autocomplete="on">
            <?php echo '<input type="hidden" name="csrf" value="' . esc(csrf_token()) . '">'; ?>
            <label>E-mail ou nome de usuário:
                <input type="text" name="login" placeholder="Digite seu e-mail ou usuário" required autofocus>
            </label>

            <label>Senha:
                <input type="password" name="senha" placeholder="Digite sua senha" required>
            </label>

            <div class="form-row">
                <label class="check"><input type="checkbox" name="lembrar"> Lembrar de mim?</label>
                <a href="#" class="small-link">Esqueci minha senha</a>
            </div>

            <button class="btn btn-primary" type="submit">Entrar</button>
        </form>

        <p class="switch">Ainda não possui uma conta? <a href="cadastro.php">Criar conta</a></p>
    </section>
</main>
</body>
</html>
