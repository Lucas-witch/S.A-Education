<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';

require_auth();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: salas.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nome, prof, alunos, materia, icone, codigo, privacidade, descricao, criado_em FROM salas WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$sala = $stmt->fetch();

if (!$sala) {
    header('Location: salas.php');
    exit;
}

if (strtolower((string) ($sala['privacidade'] ?? 'publica')) === 'privada') {
    $stmt = $pdo->prepare('SELECT 1 FROM salas_usuarios WHERE sala_id = :sala_id AND usuario_id = :usuario_id AND status = "ativo" LIMIT 1');
    $stmt->execute([
        'sala_id' => $id,
        'usuario_id' => (int) ($_SESSION['usuario_id'] ?? 0),
    ]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        header('Location: salas.php?acesso=particular');
        exit;
    }
}

$roomIcon = !empty($sala['icone']) ? (string) $sala['icone'] : 'assets/images/icons/user-icon.png';
$privacidade = strtolower((string) ($sala['privacidade'] ?? 'publica')) === 'privada' ? 'Privada' : 'Pública';
$nomeUsuario = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $sala['nome'], ENT_QUOTES, 'UTF-8') ?> | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= htmlspecialchars((string) $sala['nome'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p>Detalhes da sala · <?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a href="salas.php" class="btn btn-outline">← Voltar</a>
        </header>

        <section class="card" style="padding:24px; display:grid; gap:20px;">
            <div class="room-banner" style="margin:0;">
                <div class="big-icon">
                    <img src="<?= htmlspecialchars((string) $roomIcon, ENT_QUOTES, 'UTF-8') ?>" alt="Ícone da sala" style="width:42px;height:42px;object-fit:contain;display:block;">
                </div>
                <div>
                    <strong style="display:block; font-size:12px; letter-spacing:.08em; text-transform:uppercase; opacity:.9;">Sala</strong>
                    <h2 style="margin:8px 0 4px; font-size:28px;"><?= htmlspecialchars((string) $sala['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p style="margin:0; opacity:.9;"><?= htmlspecialchars((string) $sala['prof'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) ($sala['alunos'] ?? 0) ?> alunos</p>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="card" style="padding:18px;">
                    <p style="margin:0 0 8px; color:var(--muted);">Matéria</p>
                    <strong><?= htmlspecialchars((string) ($sala['materia'] ?? 'Geral'), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="card" style="padding:18px;">
                    <p style="margin:0 0 8px; color:var(--muted);">Acesso</p>
                    <strong><?= htmlspecialchars((string) $privacidade, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="card" style="padding:18px;">
                    <p style="margin:0 0 8px; color:var(--muted);">Código da sala</p>
                    <strong><?= htmlspecialchars((string) ($sala['codigo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
                <div class="card" style="padding:18px;">
                    <p style="margin:0 0 8px; color:var(--muted);">Criada em</p>
                    <strong><?= htmlspecialchars((string) ($sala['criado_em'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </div>

            <div class="card" style="padding:18px;">
                <p style="margin:0 0 8px; color:var(--muted);">Descrição</p>
                <p style="margin:0; line-height:1.6;">
                    <?= !empty($sala['descricao']) ? nl2br(htmlspecialchars((string) $sala['descricao'], ENT_QUOTES, 'UTF-8')) : 'A descrição desta sala ainda será adicionada pelo professor.' ?>
                </p>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a href="entrar-sala.php" class="btn btn-primary">Entrar na sala</a>
                <a href="salas.php" class="btn btn-outline">Explorar outras salas</a>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
