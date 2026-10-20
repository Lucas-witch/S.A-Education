<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
session_configure_security();
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/room_search.php';

require_auth();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: salas.php');
    exit;
}

$salaResultados = buscar_salas($pdo, (int) $_SESSION['usuario_id'], '', '', '', '', $id);
$sala = $salaResultados[0] ?? null;

if (!$sala) {
    header('Location: salas.php');
    exit;
}

$stmt = $pdo->prepare('SELECT 1 FROM salas_usuarios WHERE sala_id = :sala_id AND usuario_id = :usuario_id AND status = "ativo" LIMIT 1');
$stmt->execute([':sala_id' => $id, ':usuario_id' => (int) $_SESSION['usuario_id']]);
$isMember = (bool) $stmt->fetchColumn();

$roomIcon = subject_icon_for((string) ($sala['materia'] ?? ''));
$privacidade = strtolower((string) ($sala['privacidade'] ?? 'publica')) === 'privada' ? 'Privada' : 'Pública';
$nomeUsuario = $_SESSION['usuario_nome'] ?? ($_SESSION['usuario_username'] ?? 'Usuário');
$codigoCriado = $_SESSION['sala_codigo_criado'][$id] ?? null;
unset($_SESSION['sala_codigo_criado'][$id]);
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
                    <img src="<?= htmlspecialchars((string) $roomIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" class="room-subject-icon room-subject-icon-large">
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

            <?php if (is_string($codigoCriado) && $codigoCriado !== ''): ?>
                <div class="alert success" role="status">
                    Código de convite (mostrado uma única vez): <strong><?= htmlspecialchars($codigoCriado, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <?php if (!$isMember && strtolower((string) ($sala['privacidade'] ?? 'publica')) === 'publica'): ?>
                    <form action="entrar-sala.php" method="post">
                        <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                        <input type="hidden" name="sala_id" value="<?= $id ?>">
                        <button type="submit" class="btn btn-primary">Entrar na sala</button>
                    </form>
                <?php elseif ($isMember): ?>
                    <span class="btn btn-outline" aria-label="Você já participa desta sala">Você já participa</span>
                <?php endif; ?>
                <a href="salas.php" class="btn btn-outline">Explorar outras salas</a>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
