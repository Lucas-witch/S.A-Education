<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_auth();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $usuarioId = (int) $_SESSION['usuario_id'];
    $roomIdInput = $_POST['sala_id'] ?? '';
    $codigoInput = $_POST['codigo'] ?? '';

    if (is_string($roomIdInput) && $roomIdInput !== '') {
        if (!ctype_digit($roomIdInput) || (int) $roomIdInput <= 0) {
            $erro = 'Não foi possível entrar nessa sala.';
        } else {
            $stmt = $pdo->prepare('SELECT id, privacidade FROM salas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $roomIdInput]);
            $sala = $stmt->fetch();
            if (!$sala || strtolower((string) $sala['privacidade']) !== 'publica') {
                $erro = 'Não foi possível entrar nessa sala.';
            }
        }
    } elseif (is_string($codigoInput) && preg_match('/^[A-Za-z0-9]{1,16}$/D', trim($codigoInput))) {
        $codigo = strtoupper(trim($codigoInput));
        $stmt = $pdo->prepare('SELECT id, privacidade FROM salas WHERE codigo = :codigo LIMIT 2');
        $stmt->execute([':codigo' => $codigo]);
        $salasEncontradas = $stmt->fetchAll();
        if (count($salasEncontradas) !== 1) {
            $erro = 'Código inválido ou sala indisponível.';
        } else {
            $sala = $salasEncontradas[0];
        }
    } else {
        $erro = 'Informe um código de sala válido.';
    }

    if ($erro === '' && isset($sala['id'])) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status) VALUES (:sala_id, :usuario_id, "aluno", "ativo") ON DUPLICATE KEY UPDATE status = "ativo", entrou_em = CURRENT_TIMESTAMP');
            $stmt->execute([
                ':sala_id' => (int) $sala['id'],
                ':usuario_id' => $usuarioId,
            ]);

            $stmt = $pdo->prepare('UPDATE salas SET alunos = (SELECT COUNT(*) FROM salas_usuarios WHERE sala_id = :sala_id AND papel = "aluno" AND status = "ativo") WHERE id = :room_id');
            $stmt->execute([
                ':sala_id' => (int) $sala['id'],
                ':room_id' => (int) $sala['id'],
            ]);
            $pdo->commit();

            header('Location: sala.php?id=' . (int) $sala['id'], true, 303);
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[S.A Education][join_room] ' . $exception->getMessage());
            $erro = 'Não foi possível entrar na sala agora.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Entrar em uma sala | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main">
        <header class="topbar">
            <div><h1>Entrar em uma sala</h1><p>Use o código de convite recebido.</p></div>
            <a href="salas.php" class="btn btn-outline">Voltar</a>
        </header>
        <form class="card form-card" method="post" action="entrar-sala.php">
            <?php if ($erro !== ''): ?>
                <p class="alert error" role="alert"><?= esc($erro) ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <div class="form-group">
                <label for="room-code">Código da sala</label>
                <input id="room-code" name="codigo" class="form-control" maxlength="16" pattern="[A-Za-z0-9]{1,16}" autocomplete="off" required>
            </div>
            <button class="btn btn-primary full" type="submit">Validar e entrar</button>
            <div class="auth-divider">ou</div>
            <a class="btn outline full" href="salas.php">Procurar salas públicas</a>
        </form>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
