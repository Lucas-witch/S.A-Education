<?php
require_once __DIR__ . '/includes/helpers.php';

$dados = $_SESSION['dados_instituicao'] ?? [];
$erro = $_SESSION['erro_instituicao'] ?? '';
unset($_SESSION['dados_instituicao'], $_SESSION['erro_instituicao']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S.A Education | Cadastro institucional</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/login-cadastro.css">
</head>
<body>
<main class="form-page">
    <section class="form-card">
        <a class="back" href="cadastro.php">←</a>

        <div class="page-title">
            <div class="login-icon"><img src="assets/images/logo-app.png" alt="Logotype"></div>
            <h1>Cadastrar instituição</h1>
            <span>Crie o acesso da sua escola ou instituição de ensino.</span>
        </div>

        <?php if ($erro): ?>
            <div class="alert error"><?= esc($erro) ?></div>
        <?php endif; ?>

        <form action="processa_cadastro_instituicao.php" method="POST" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">

            <label>Nome da instituição:
                <input type="text" name="nome" value="<?= esc($dados['nome'] ?? '') ?>" required>
            </label>

            <div class="grid grid-2">
                <label>Tipo de ensino:
                    <select name="tipo" required>
                        <option value="">Selecione</option>
                        <option value="fundamental" <?= ($dados['tipo'] ?? '') === 'fundamental' ? 'selected' : '' ?>>Ensino fundamental</option>
                        <option value="medio" <?= ($dados['tipo'] ?? '') === 'medio' ? 'selected' : '' ?>>Ensino médio</option>
                        <option value="faculdade" <?= ($dados['tipo'] ?? '') === 'faculdade' ? 'selected' : '' ?>>Faculdade</option>
                        <option value="universidade" <?= ($dados['tipo'] ?? '') === 'universidade' ? 'selected' : '' ?>>Universidade</option>
                        <option value="outro" <?= ($dados['tipo'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </label>

                <label>Natureza:
                    <select name="natureza" required>
                        <option value="">Selecione</option>
                        <option value="publica" <?= ($dados['natureza'] ?? '') === 'publica' ? 'selected' : '' ?>>Pública</option>
                        <option value="particular" <?= ($dados['natureza'] ?? '') === 'particular' ? 'selected' : '' ?>>Particular</option>
                    </select>
                </label>
            </div>

            <label>Nome do responsável:
                <input type="text" name="responsavel" value="<?= esc($dados['responsavel'] ?? '') ?>" required>
            </label>

            <label>E-mail institucional:
                <input type="email" name="email" value="<?= esc($dados['email'] ?? '') ?>" required>
            </label>

            <div class="grid grid-2">
                <label>Nome de usuário:
                    <input type="text" name="username" value="<?= esc($dados['username'] ?? '') ?>" required>
                </label>

                <label>Telefone:
                    <input type="tel" name="telefone" value="<?= esc($dados['telefone'] ?? '') ?>">
                </label>
            </div>

            <label>Cidade:
                <input type="text" name="cidade" value="<?= esc($dados['cidade'] ?? '') ?>" required>
            </label>

            <label>Senha:
                <input type="password" name="senha" required>
            </label>

            <label>Confirmar senha:
                <input type="password" name="confirmar_senha" required>
            </label>

            <button class="btn btn-primary" type="submit">Criar acesso institucional</button>
        </form>

        <p class="switch">Já possui acesso? <a href="login.php">Entrar</a></p>
    </section>
</main>
</body>
</html>
