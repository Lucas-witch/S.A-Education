<?php require 'includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>Entrar em uma sala</title>
        <link rel="stylesheet" href="assets/css/estilo.css">
    </head>
    <body>
        <div class="app"><?php require 'includes/header.php'; ?>
        <main class="main">
        <header class="topbar">
            <a href="salas.php">← Voltar</a>
        </header>
        <div class="card form-card" style="text-align:center">
            <div style="font-size:65px">👨‍🎓</div>
                <h1 style="font-size:20px">Tem um código de sala?</h1>
                <p style="color:var(--muted);font-size:13px">Digite o código para entrar.</p>
                <div class="form-group" style="text-align:left;margin-top:25px">
                    <label>Código da sala</label>
                    <input class="form-control" placeholder="Ex: ABC123">
                </div>
    <button class="btn full" onclick="location.href='sala.php'">Entrar</button>
                <div class="auth-divider">ou</div>
                    <a class="btn outline full" href="salas.php">Procurar salas</a>
        </div>
        </main>
        </div>
        </html>
        <?php require 'includes/footer.php'; ?>
