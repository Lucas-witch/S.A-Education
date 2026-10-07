<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/includes/helpers.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: perfil-dashboard.php');
    exit;
}

$erro = $_SESSION['erro_redefinir'] ?? '';
$sucesso = $_SESSION['sucesso'] ?? '';
unset($_SESSION['erro_redefinir'], $_SESSION['sucesso']);
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
            <h1>Redefinir senha</h1>
            <span>Informe seus dados e defina uma nova senha.</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert success"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form action="processa_redefinir_senha.php" method="POST" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">

            <label>E-mail ou nome de usuário:
                <input type="text" name="identificador" placeholder="Digite seu e-mail ou usuário" required>
            </label>

            <label>Nova senha:
                <input type="password" name="nova_senha" placeholder="Digite a nova senha" required>
            </label>

            <label>Confirmar nova senha:
                <input type="password" name="confirmar_nova_senha" placeholder="Confirme a nova senha" required>
            </label>

            <button class="btn btn-primary" type="submit">Salvar nova senha</button>
        </form>

        <p class="switch"><a href="login.php">Voltar para o login</a></p>
    </section>
</main>
</body>
</html>
