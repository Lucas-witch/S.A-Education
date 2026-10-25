<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/room_access.php';
require_once __DIR__ . '/includes/room_actions.php';
require_auth();

$erro = '';
$viaCodigo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $usuarioId = (int) $_SESSION['usuario_id'];
    $roomIdInput = $_POST['sala_id'] ?? '';
    $codigoInput = $_POST['codigo'] ?? '';

    if (is_string($roomIdInput) && $roomIdInput !== '') {
        if (!ctype_digit($roomIdInput) || (int) $roomIdInput <= 0) {
            $erro = 'Não foi possível entrar nessa sala.';
        } else {
            $stmt = $pdo->prepare('SELECT id, criador_id, institution_id, privacidade FROM salas WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $roomIdInput]);
            $sala = $stmt->fetch();
            if (
                !$sala
                || strtolower((string) $sala['privacidade']) !== 'publica'
                || !room_user_can_access($pdo, $sala, $usuarioId, is_admin_user())
            ) {
                $erro = 'Não foi possível entrar nessa sala.';
            }
        }
    } elseif (is_string($codigoInput) && preg_match('/^[A-Za-z0-9]{1,16}$/D', trim($codigoInput))) {
        $viaCodigo = true;
        $codigo = strtoupper(trim($codigoInput));
        $stmt = $pdo->prepare('SELECT id, criador_id, institution_id, privacidade FROM salas WHERE codigo = :codigo LIMIT 2');
        $stmt->execute([':codigo' => $codigo]);
        $salasEncontradas = $stmt->fetchAll();
        if (count($salasEncontradas) !== 1) {
            $erro = 'Código inválido ou sala indisponível.';
        } else {
            $sala = $salasEncontradas[0];
            if (
                strtolower((string) $sala['privacidade']) === 'instituicao'
                && !room_user_can_access($pdo, $sala, $usuarioId, is_admin_user())
            ) {
                $erro = 'Não foi possível entrar nessa sala.';
            }
        }
    } else {
        $erro = 'Informe um código de sala válido.';
    }

    if ($erro === '' && isset($sala['id'])) {
        try {
            room_join($pdo, (int) $sala['id'], $usuarioId, $viaCodigo, is_admin_user());
            header('Location: sala.php?id=' . (int) $sala['id'], true, 303);
            exit;
        } catch (DomainException $exception) {
            $erro = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('[S.A Education][join_room] ' . $exception->getMessage());
            $erro = 'Não foi possível entrar na sala agora.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Método não permitido.');
}

$erroNoCodigo = $erro !== '' && (
    !isset($roomIdInput)
    || !is_string($roomIdInput)
    || $roomIdInput === ''
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Entrar em uma sala | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/entrar-sala.css">
</head>
<body class="room-entry-page">
<div class="app">
    <?php require __DIR__ . '/includes/header.php'; ?>
    <main class="main room-entry-main">
        <header class="topbar room-entry-heading">
            <div class="room-entry-heading-copy">
                <h1>Entrar em uma sala</h1>
                <p>Insira o código de convite para acessar sua sala de estudos.</p>
            </div>
            <a href="salas.php" class="btn btn-outline room-entry-back">Voltar</a>
        </header>
        <section class="card room-entry-card" aria-labelledby="room-entry-card-title">
            <div class="room-entry-card-content">
                <div class="room-entry-intro">
                    <span class="room-entry-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm6-6.5a3.5 3.5 0 0 1 0 6.8m1 3.7h.5a3.5 3.5 0 0 1 3.5 3.5V20"/>
                        </svg>
                    </span>
                    <div>
                        <h2 id="room-entry-card-title">Você tem um código de convite?</h2>
                        <p>Peça o código ao responsável pela sala para participar da comunidade de estudos.</p>
                    </div>
                </div>

                <?php if ($erro !== '' && !$erroNoCodigo): ?>
                    <p class="alert error room-entry-alert" role="alert"><?= esc($erro) ?></p>
                <?php endif; ?>

                <form class="room-entry-form" method="post" action="entrar-sala.php">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <div class="room-entry-field">
                        <label for="room-code">Código da sala</label>
                        <input
                            id="room-code"
                            name="codigo"
                            class="form-control room-entry-code"
                            maxlength="16"
                            pattern="[A-Za-z0-9]{1,16}"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            aria-describedby="room-code-help<?= $erroNoCodigo ? ' room-code-error' : '' ?>"
                            <?= $erroNoCodigo ? 'aria-invalid="true"' : '' ?>
                            required
                        >
                        <?php if ($erroNoCodigo): ?>
                            <p class="alert error room-entry-alert" id="room-code-error" role="alert"><?= esc($erro) ?></p>
                        <?php endif; ?>
                        <p class="room-entry-help" id="room-code-help">Use de 1 a 16 letras ou números, sem espaços.</p>
                    </div>
                    <button class="room-entry-submit" type="submit">Validar e entrar</button>
                </form>

                <div class="room-entry-alternative">
                    <div class="room-entry-divider" aria-hidden="true"><span>ou</span></div>
                    <p>Quer conhecer outras comunidades?</p>
                    <a class="room-entry-public-link" href="salas.php">Procurar salas públicas</a>
                </div>
            </div>
        </section>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
