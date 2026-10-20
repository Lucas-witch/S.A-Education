<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_perfil(['professor']);

$erro = '';
$nome = '';
$materia = '';
$descricao = '';
$privacidade = 'publica';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $nomeInput = $_POST['nome'] ?? null;
    $materiaInput = $_POST['materia'] ?? null;
    $descricaoInput = $_POST['descricao'] ?? '';
    $privacidadeInput = $_POST['privacidade'] ?? null;

    if (!is_string($nomeInput) || !is_string($materiaInput) || !is_string($descricaoInput) || !is_string($privacidadeInput)) {
        $erro = 'Revise os dados informados.';
    } else {
        $nome = trim($nomeInput);
        $materia = trim($materiaInput);
        $descricao = trim($descricaoInput);
        $privacidade = strtolower(trim($privacidadeInput));

        if ($nome === '' || mb_strlen($nome) > 150 || $materia === '' || mb_strlen($materia) > 80 || mb_strlen($descricao) > 3000 || !in_array($privacidade, ['publica', 'privada'], true)) {
            $erro = 'Revise os dados informados.';
        }
    }

    if ($erro === '') {
        $usuarioId = (int) $_SESSION['usuario_id'];
        $nomeProfessor = trim((string) ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? ''));
        $icone = subject_icon_for($materia);

        try {
            $pdo->beginTransaction();
            $codigo = '';
            for ($tentativa = 0; $tentativa < 5; $tentativa++) {
                $codigo = strtoupper(bin2hex(random_bytes(8)));
                $stmt = $pdo->prepare('SELECT 1 FROM salas WHERE codigo = :codigo LIMIT 1');
                $stmt->execute([':codigo' => $codigo]);
                if (!$stmt->fetchColumn()) {
                    break;
                }
                $codigo = '';
            }

            if ($codigo === '') {
                throw new RuntimeException('Não foi possível gerar um código exclusivo.');
            }

            $stmt = $pdo->prepare('INSERT INTO salas (nome, prof, alunos, materia, icone, codigo, privacidade, descricao, criador_id) VALUES (:nome, :prof, 0, :materia, :icone, :codigo, :privacidade, :descricao, :criador_id)');
            $stmt->execute([
                ':nome' => $nome,
                ':prof' => $nomeProfessor,
                ':materia' => $materia,
                ':icone' => $icone,
                ':codigo' => $codigo,
                ':privacidade' => $privacidade,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':criador_id' => $usuarioId,
            ]);
            $salaId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status) VALUES (:sala_id, :usuario_id, "professor", "ativo")');
            $stmt->execute([':sala_id' => $salaId, ':usuario_id' => $usuarioId]);
            $pdo->commit();

            $_SESSION['sala_codigo_criado'][$salaId] = $codigo;
            header('Location: sala.php?id=' . $salaId, true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[S.A Education][create_room] ' . $exception->getMessage());
            $erro = 'Não foi possível criar a sala. Verifique a atualização do banco de dados e tente novamente.';
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
    <title>Criar Sala</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/sala-config.css">
</head>
<body>
    <div class="app">
        <?php require 'includes/header.php'; ?>
        <main class="main">
    <header class="topbar">
        <div>
            <h1>Criar Sala</h1>
            <p>Monte um espaço para sua turma.</p>
        </div>
    </header>
<form class="card form-card" action="criar-sala.php" method="post">
    <?php if ($erro !== ''): ?>
        <p class="alert error" role="alert"><?= esc($erro) ?></p>
    <?php endif; ?>
    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
        <div class="form-group">
            <label for="room-name">Nome da sala</label>
            <input id="room-name" name="nome" class="form-control" maxlength="150" value="<?= esc($nome) ?>" required>
        </div>
            <div class="form-group">
                <label for="room-subject">Matéria</label>
                <input id="room-subject" name="materia" class="form-control" maxlength="80" value="<?= esc($materia) ?>" required>
            </div>
            <div class="form-group">
                <label for="room-description">Descrição</label>
                <textarea id="room-description" name="descricao" class="form-control" maxlength="3000"><?= esc($descricao) ?></textarea>
            </div>
    <div class="form-group">
        <label for="room-privacy">Visibilidade</label>
        <select id="room-privacy" name="privacidade" class="form-control" required>
            <option value="publica" <?= $privacidade === 'publica' ? 'selected' : '' ?>>Pública</option>
            <option value="privada" <?= $privacidade === 'privada' ? 'selected' : '' ?>>Privada</option>
        </select>
    </div>
    <button class="btn light full" type="submit">Criar sala</button>
</form>
</main>
</div>
</body>
</html>
<?php require 'includes/footer.php'; ?>
