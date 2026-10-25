<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/room_access.php';

$isInstitutionSession = isset($_SESSION['instituicao_usuario_id']);
if ($isInstitutionSession) {
    require_institution_auth();
    $userId = (int) $_SESSION['instituicao_usuario_id'];
} else {
    require_auth();
    $userId = (int) $_SESSION['usuario_id'];
}

$permissions = resolve_account_permissions($pdo, $userId);
$canCreateRooms = room_creation_is_allowed($permissions);
$institutionId = null;
if (($permissions['profile'] ?? '') === 'instituicao') {
    $institutionQuery = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
    $institutionQuery->execute(['user_id' => $userId]);
    $institutionValue = $institutionQuery->fetchColumn();
    $institutionId = $institutionValue === false ? null : (int) $institutionValue;
}

$erro = '';
$nome = '';
$materia = '';
$descricao = '';
$categoria = '';
$areaConhecimento = '';
$privacidade = 'publica';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? null);
    $nomeInput = $_POST['nome'] ?? null;
    $materiaInput = $_POST['materia'] ?? null;
    $descricaoInput = $_POST['descricao'] ?? '';
    $categoriaInput = $_POST['categoria'] ?? '';
    $areaInput = $_POST['area_conhecimento'] ?? '';
    $privacidadeInput = $_POST['privacidade'] ?? null;

    if (
        !is_string($nomeInput)
        || !is_string($materiaInput)
        || !is_string($descricaoInput)
        || !is_string($categoriaInput)
        || !is_string($areaInput)
        || !is_string($privacidadeInput)
    ) {
        $erro = 'Revise os dados informados.';
    } else {
        $nome = trim($nomeInput);
        $materia = trim($materiaInput);
        $descricao = trim($descricaoInput);
        $categoria = trim($categoriaInput);
        $areaConhecimento = trim($areaInput);
        $privacidade = strtolower(trim($privacidadeInput));

        if (
            $nome === ''
            || mb_strlen($nome) > 150
            || $materia === ''
            || mb_strlen($materia) > 80
            || mb_strlen($descricao) > 3000
            || mb_strlen($categoria) > 80
            || mb_strlen($areaConhecimento) > 120
            || !in_array($privacidade, ['publica', 'privada', 'instituicao'], true)
            || ($privacidade === 'instituicao' && $institutionId === null)
        ) {
            $erro = 'Revise os dados informados.';
        }
    }

    if (!$canCreateRooms) {
        http_response_code(403);
        $erro = 'A criação de salas exige Premium ativo e, para professores e instituições, aprovação vigente.';
    }

    if ($erro === '' && $canCreateRooms) {
        $displayName = $isInstitutionSession
            ? (string) ($_SESSION['instituicao_nome'] ?? 'Instituição')
            : trim((string) ($_SESSION['usuario_nome'] ?? $_SESSION['usuario_username'] ?? ''));
        $icon = subject_icon_for($materia);

        try {
            $pdo->beginTransaction();
            $code = '';
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = strtoupper(bin2hex(random_bytes(8)));
                $statement = $pdo->prepare('SELECT 1 FROM salas WHERE codigo = :code LIMIT 1');
                $statement->execute(['code' => $code]);
                if (!$statement->fetchColumn()) {
                    break;
                }
                $code = '';
            }
            if ($code === '') {
                throw new RuntimeException('Não foi possível gerar um código exclusivo.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO salas
                    (nome, criador_id, institution_id, prof, alunos, materia, categoria,
                     area_conhecimento, icone, codigo, privacidade, descricao)
                 VALUES
                    (:nome, :creator_id, :institution_id, :owner_name, 0, :subject, :category,
                     :knowledge_area, :icon, :code, :privacy, :description)'
            );
            $statement->execute([
                'nome' => $nome,
                'creator_id' => $userId,
                'institution_id' => $privacidade === 'instituicao' ? $institutionId : null,
                'owner_name' => $displayName,
                'subject' => $materia,
                'category' => $categoria !== '' ? $categoria : null,
                'knowledge_area' => $areaConhecimento !== '' ? $areaConhecimento : null,
                'icon' => $icon,
                'code' => $code,
                'privacy' => $privacidade,
                'description' => $descricao !== '' ? $descricao : null,
            ]);
            $roomId = (int) $pdo->lastInsertId();

            $role = ($permissions['profile'] ?? '') === 'professor' ? 'professor' : 'moderador';
            $statement = $pdo->prepare(
                'INSERT INTO salas_usuarios (sala_id, usuario_id, papel, status)
                 VALUES (:room_id, :user_id, :role, "ativo")'
            );
            $statement->execute(['room_id' => $roomId, 'user_id' => $userId, 'role' => $role]);
            $pdo->commit();

            $_SESSION['sala_codigo_criado'][$roomId] = $code;
            header('Location: sala.php?id=' . $roomId, true, 303);
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
    <title>Criar sala | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/sala-config.css">
</head>
<body>
<div class="app">
    <?php require 'includes/header.php'; ?>
    <main class="main">
        <header class="topbar">
            <div>
                <h1>Criar sala ou comunidade</h1>
                <p>Um espaço para estudar, discutir e compartilhar com a comunidade.</p>
            </div>
        </header>
        <?php if (!$canCreateRooms): ?>
            <section class="card form-card" role="status">
                <p>A criação de salas está disponível para estudantes e professores aprovados com Premium ativo, instituições aprovadas com assinatura válida e administradores autorizados.</p>
                <a class="btn outline" href="salas.php">Voltar às salas</a>
            </section>
        <?php else: ?>
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
                    <label for="room-subject">Matéria ou tema</label>
                    <input id="room-subject" name="materia" class="form-control" maxlength="80" value="<?= esc($materia) ?>" required>
                </div>
                <div class="form-group">
                    <label for="room-category">Categoria ou tipo de grupo</label>
                    <input id="room-category" name="categoria" class="form-control" maxlength="80" value="<?= esc($categoria) ?>" placeholder="Ex.: vestibular, curso, discussão acadêmica">
                </div>
                <div class="form-group">
                    <label for="room-knowledge-area">Área do conhecimento</label>
                    <input id="room-knowledge-area" name="area_conhecimento" class="form-control" maxlength="120" value="<?= esc($areaConhecimento) ?>">
                </div>
                <div class="form-group">
                    <label for="room-description">Descrição</label>
                    <textarea id="room-description" name="descricao" class="form-control" maxlength="3000"><?= esc($descricao) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="room-privacy">Visibilidade</label>
                    <select id="room-privacy" name="privacidade" class="form-control" required>
                        <option value="publica" <?= $privacidade === 'publica' ? 'selected' : '' ?>>Pública</option>
                        <option value="privada" <?= $privacidade === 'privada' ? 'selected' : '' ?>>Privada, por código ou aprovação</option>
                        <?php if ($institutionId !== null): ?>
                            <option value="instituicao" <?= $privacidade === 'instituicao' ? 'selected' : '' ?>>Restrita à instituição</option>
                        <?php endif; ?>
                    </select>
                </div>
                <button class="btn light full" type="submit">Criar sala</button>
            </form>
        <?php endif; ?>
    </main>
</div>
<?php require 'includes/footer.php'; ?>
</body>
</html>
