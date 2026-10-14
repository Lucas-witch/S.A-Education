<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
require_once __DIR__ . '/../conexao.php';

try {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT id,nome,prof,alunos,materia,icone,codigo,privacidade,descricao,criado_em FROM salas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_GET['id']]);
        $sala = $stmt->fetch();

        if (!$sala) {
            http_response_code(404);
            echo json_encode(['error' => 'Sala não encontrada']);
            exit;
        }

        if (($sala['privacidade'] ?? 'publica') === 'privada' && !isset($_SESSION['usuario_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Sala privada. Faça login para acessar.']);
            exit;
        }

        echo json_encode($sala);
        exit;
    }

    $usuarioAtualId = (int) ($_SESSION['usuario_id'] ?? 0);
    $where = ['1 = 1'];
    $params = [];

    if ($usuarioAtualId > 0) {
        $where[] = '(privacidade <> :privada OR EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = salas.id AND su.usuario_id = :usuario_id AND su.status = "ativo"))';
        $params[':privada'] = 'privada';
        $params[':usuario_id'] = $usuarioAtualId;
    } else {
        $where[] = 'privacidade <> :privada';
        $params[':privada'] = 'privada';
    }

    $termo = trim((string) ($_GET['q'] ?? ''));
    if ($termo !== '') {
        $where[] = '(LOWER(nome) LIKE :q OR LOWER(COALESCE(prof, "")) LIKE :q OR LOWER(COALESCE(materia, "")) LIKE :q OR LOWER(COALESCE(descricao, "")) LIKE :q)';
        $params[':q'] = '%' . strtolower($termo) . '%';
    }

    $materia = strtolower(trim((string) ($_GET['materia'] ?? '')));
    if ($materia !== '') {
        $where[] = 'LOWER(materia) LIKE :materia';
        $params[':materia'] = '%' . $materia . '%';
    }

    $tipo = strtolower(trim((string) ($_GET['tipo'] ?? '')));
    if ($tipo !== '') {
        $where[] = '(LOWER(COALESCE(nome, "")) LIKE :tipo OR LOWER(COALESCE(descricao, "")) LIKE :tipo OR LOWER(COALESCE(materia, "")) LIKE :tipo OR LOWER(COALESCE(prof, "")) LIKE :tipo)';
        $params[':tipo'] = '%' . $tipo . '%';
    }

    $acesso = strtolower(trim((string) ($_GET['acesso'] ?? '')));
    if ($acesso === 'gratis') {
        $where[] = 'privacidade = :acesso';
        $params[':acesso'] = 'publica';
    } elseif ($acesso === 'particular') {
        $where[] = 'privacidade = :acesso';
        $params[':acesso'] = 'privada';
    }

    $sql = 'SELECT id,nome,prof,alunos,materia,icone,codigo,privacidade,descricao FROM salas WHERE ' . implode(' AND ', $where) . ' ORDER BY criado_em DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $salas = $stmt->fetchAll();
    echo json_encode($salas);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar salas.']);
    exit;
}
?>