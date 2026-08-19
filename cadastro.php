<?php
declare(strict_types=1);
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erro = $_SESSION['erro_cadastro'] ?? '';
$dados = $_SESSION['dados_cadastro'] ?? [];
unset($_SESSION['erro_cadastro'], $_SESSION['dados_cadastro']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Criar conta</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>
<main class="form-page">
    <section class="form-card">
        <a class="back" href="index.php">←</a>
        <div class="page-title">
            <h1>Criar conta</h1>
            <p>Vamos começar!</p>
            <span>Crie sua conta para acessar todos os recursos da plataforma.</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form action="processa_cadastro.php" method="POST" autocomplete="on">
            <label>Nome completo
                <input type="text" name="nome_completo" placeholder="Digite seu nome" maxlength="120"
                       value="<?= htmlspecialchars($dados['nome_completo'] ?? '') ?>" required>
            </label>

            <label>E-mail
                <input type="email" name="email" placeholder="Digite seu e-mail" maxlength="180"
                       value="<?= htmlspecialchars($dados['email'] ?? '') ?>" required>
            </label>

            <label>Nome de usuário
                <input type="text" name="username" placeholder="Escolha um nome de usuário" maxlength="40"
                       pattern="[A-Za-z0-9_.-]{3,40}" value="<?= htmlspecialchars($dados['username'] ?? '') ?>" required>
                <small>Use de 3 a 40 caracteres: letras, números, ponto, hífen ou _.</small>
            </label>

            <label>Senha
                <input type="password" name="senha" placeholder="Digite sua senha" minlength="8" required>
            </label>

            <label>Confirmar senha
                <input type="password" name="confirmar_senha" placeholder="Confirme sua senha" minlength="8" required>
            </label>

            <div class="password-rules">
                <b>A senha deve conter:</b>
                <span>✓ Mínimo de 8 caracteres</span>
                <span>✓ Uma letra maiúscula</span>
                <span>✓ Um número ou símbolo</span>
            </div>

            <label>Perfil
                <select name="perfil" required>
                    <option value="estudante" <?= (($dados['perfil'] ?? '') === 'estudante') ? 'selected' : '' ?>>Sou estudante</option>
                    <option value="professor" <?= (($dados['perfil'] ?? '') === 'professor') ? 'selected' : '' ?>>Sou professor</option>
                </select>
            </label>

            <label>Interesses <em>(opcional)</em>
                <input type="text" name="interesses" placeholder="Ex.: Matemática, História, Tecnologia"
                       value="<?= htmlspecialchars($dados['interesses'] ?? '') ?>" maxlength="500">
            </label>

            <button class="btn btn-primary" type="submit">Criar conta</button>
        </form>

        <p class="switch">Já possui uma conta? <a href="login.php">Entrar</a></p>
    </section>
</main>
</body>
</html>
