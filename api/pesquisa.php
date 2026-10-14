<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}

require_once __DIR__ . '/../conexao.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível conectar ao servidor.']);
    exit;
}

function normalize_search_text(?string $value): string {
    return trim((string) preg_replace('/\s+/', ' ', strtolower((string) ($value ?? ''))));
}

function price_label_for(string $access, int $premiumOnly): string {
    if ($access === 'privada' || $premiumOnly === 1) {
        return 'Premium · R$ 29,90/mês';
    }
    return 'Gratuito';
}

function map_sala_item(array $row): array {
    $access = strtolower((string) ($row['privacidade'] ?? 'publica'));
    $premiumOnly = $access === 'privada' ? 1 : 0;

    return [
        'id' => (int) ($row['id'] ?? 0),
        'type' => 'sala',
        'typeLabel' => 'Sala',
        'title' => (string) ($row['nome'] ?? 'Sala'),
        'description' => trim((string) ($row['descricao'] ?? '')) !== '' ? (string) $row['descricao'] : 'Sala de estudo com materiais e acompanhamento focados em ' . ((string) ($row['materia'] ?? 'Geral')) . '.',
        'responsible' => (string) ($row['prof'] ?? 'Equipe S.A'),
        'category' => 'Sala de estudo',
        'subject' => (string) ($row['materia'] ?? 'Geral'),
        'access' => $access,
        'accessLabel' => $access === 'privada' ? 'Acesso privado' : 'Acesso público',
        'priceLabel' => $access === 'privada' ? 'Premium' : 'Gratuito',
        'priceText' => price_label_for($access, $premiumOnly),
        'url' => 'sala.php?id=' . (int) ($row['id'] ?? 0),
    ];
}

function map_publicacao_item(array $row): array {
    $tipo = strtolower((string) ($row['tipo'] ?? 'material'));
    $tipoLabels = [
        'video_aula' => 'Aula',
        'artigo' => 'Artigo',
        'redacao' => 'Redação',
        'pdf' => 'Material',
        'livro' => 'Livro',
        'post' => 'Publicação',
        'material' => 'Material',
        'curso' => 'Curso',
        'mentoria' => 'Mentoria',
    ];

    $access = strtolower((string) ($row['visibilidade'] ?? 'publica'));
    if ($access === 'privada') {
        $access = 'privada';
    } elseif ((int) ($row['is_premium_only'] ?? 0) === 1) {
        $access = 'privada';
    } else {
        $access = 'publica';
    }

    $priceLabel = $access === 'privada' ? 'Premium' : 'Gratuito';

    return [
        'id' => (int) ($row['id'] ?? 0),
        'type' => 'publicacao',
        'typeLabel' => $tipoLabels[$tipo] ?? 'Material',
        'title' => (string) ($row['titulo'] ?? 'Publicação'),
        'description' => trim((string) ($row['resumo'] ?? $row['conteudo'] ?? '')) !== '' ? trim((string) ($row['resumo'] ?? $row['conteudo'])) : 'Conteúdo educacional com abordagem prática e didática.',
        'responsible' => (string) ($row['responsavel'] ?? 'Equipe S.A'),
        'category' => (string) ($row['categoria'] ?? 'Educacional'),
        'subject' => (string) ($row['categoria'] ?? 'Geral'),
        'access' => $access,
        'accessLabel' => $access === 'privada' ? 'Acesso privado' : 'Acesso público',
        'priceLabel' => $priceLabel,
        'priceText' => price_label_for($access, $access === 'privada' ? 1 : 0),
        'url' => 'feed.php',
    ];
}

try {
    $q = trim((string) ($_GET['q'] ?? ''));
    $assunto = trim(strtolower((string) ($_GET['assunto'] ?? '')));
    $materia = trim(strtolower((string) ($_GET['materia'] ?? '')));
    $tipo = trim(strtolower((string) ($_GET['tipo'] ?? '')));
    $acesso = trim(strtolower((string) ($_GET['acesso'] ?? '')));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(1, min(12, (int) ($_GET['limit'] ?? 8)));
    $offset = ($page - 1) * $limit;

    $allowedAcesso = ['', 'publica', 'privada', 'gratuito', 'premium'];
    $allowedTipo = ['', 'curso', 'mentoria', 'sala', 'aula', 'material', 'artigo', 'redacao', 'video_aula', 'post'];

    if (($acesso !== '' && !in_array($acesso, $allowedAcesso, true)) || ($tipo !== '' && !in_array($tipo, $allowedTipo, true))) {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetros de filtro inválidos.']);
        exit;
    }

    $resultado = [
        'items' => [],
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => 0,
            'totalPages' => 1,
        ],
    ];

    $usuarioAtualId = (int) ($_SESSION['usuario_id'] ?? 0);
    $salasSql = 'SELECT s.id, s.nome, s.prof, s.materia, s.descricao, s.privacidade FROM salas s WHERE 1 = 1';
    $salasParams = [];

    if ($usuarioAtualId > 0) {
        $salasSql .= ' AND (s.privacidade <> :privada OR EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = s.id AND su.usuario_id = :usuario_id AND su.status = "ativo"))';
        $salasParams[':privada'] = 'privada';
        $salasParams[':usuario_id'] = $usuarioAtualId;
    } else {
        $salasSql .= ' AND s.privacidade <> :privada';
        $salasParams[':privada'] = 'privada';
    }

    if ($q !== '') {
        $salasSql .= ' AND (LOWER(s.nome) LIKE :q OR LOWER(COALESCE(s.prof, "")) LIKE :q OR LOWER(COALESCE(s.materia, "")) LIKE :q OR LOWER(COALESCE(s.descricao, "")) LIKE :q)';
        $salasParams[':q'] = '%' . strtolower($q) . '%';
    }

    if ($assunto !== '') {
        $salasSql .= ' AND (LOWER(s.nome) LIKE :assunto OR LOWER(COALESCE(s.descricao, "")) LIKE :assunto OR LOWER(COALESCE(s.prof, "")) LIKE :assunto)';
        $salasParams[':assunto'] = '%' . $assunto . '%';
    }

    if ($materia !== '') {
        $salasSql .= ' AND LOWER(s.materia) LIKE :materia';
        $salasParams[':materia'] = '%' . $materia . '%';
    }

    if ($acesso === 'publica' || $acesso === 'gratuito') {
        $salasSql .= ' AND s.privacidade = :acesso';
        $salasParams[':acesso'] = 'publica';
    } elseif ($acesso === 'privada' || $acesso === 'premium') {
        $salasSql .= ' AND s.privacidade = :acesso';
        $salasParams[':acesso'] = 'privada';
    }

    if ($tipo !== '' && $tipo !== 'sala') {
        $salasSql .= ' AND (LOWER(COALESCE(s.nome, "")) LIKE :tipo OR LOWER(COALESCE(s.descricao, "")) LIKE :tipo OR LOWER(COALESCE(s.materia, "")) LIKE :tipo)';
        $salasParams[':tipo'] = '%' . $tipo . '%';
    }

    $salasStatement = $pdo->prepare($salasSql);
    $salasStatement->execute($salasParams);
    $salasRows = $salasStatement->fetchAll(PDO::FETCH_ASSOC);

    $publicacoesSql = 'SELECT p.id, p.titulo, p.resumo, p.conteudo, p.categoria, p.tipo, p.visibilidade, p.is_premium_only, u.nome_completo AS responsavel FROM publicacoes p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.status = :status';
    $publicacoesParams = [':status' => 'publicado'];

    if ($q !== '') {
        $publicacoesSql .= ' AND (LOWER(p.titulo) LIKE :q OR LOWER(COALESCE(p.resumo, "")) LIKE :q OR LOWER(COALESCE(p.conteudo, "")) LIKE :q OR LOWER(COALESCE(p.categoria, "")) LIKE :q OR LOWER(COALESCE(u.nome_completo, "")) LIKE :q)';
        $publicacoesParams[':q'] = '%' . strtolower($q) . '%';
    }

    if ($assunto !== '') {
        $publicacoesSql .= ' AND (LOWER(COALESCE(p.tipo, "")) LIKE :assunto OR LOWER(COALESCE(p.categoria, "")) LIKE :assunto OR LOWER(COALESCE(p.titulo, "")) LIKE :assunto)';
        $publicacoesParams[':assunto'] = '%' . $assunto . '%';
    }

    if ($materia !== '') {
        $publicacoesSql .= ' AND (LOWER(COALESCE(p.categoria, "")) LIKE :materia OR LOWER(COALESCE(p.tipo, "")) LIKE :materia)';
        $publicacoesParams[':materia'] = '%' . $materia . '%';
    }

    if ($tipo !== '') {
        $publicacoesSql .= ' AND LOWER(COALESCE(p.tipo, "")) LIKE :tipo';
        $publicacoesParams[':tipo'] = '%' . $tipo . '%';
    }

    if ($acesso === 'publica' || $acesso === 'gratuito') {
        $publicacoesSql .= ' AND (p.is_premium_only = 0 AND p.visibilidade IN ("publica", "sala", "instituicao"))';
    } elseif ($acesso === 'privada' || $acesso === 'premium') {
        $publicacoesSql .= ' AND (p.is_premium_only = 1 OR p.visibilidade = "privada")';
    }

    $publicacoesStatement = $pdo->prepare($publicacoesSql);
    $publicacoesStatement->execute($publicacoesParams);
    $publicacoesRows = $publicacoesStatement->fetchAll(PDO::FETCH_ASSOC);

    $merged = [];
    foreach ($salasRows as $row) {
        $merged[] = map_sala_item($row);
    }

    foreach ($publicacoesRows as $row) {
        $merged[] = map_publicacao_item($row);
    }

    usort($merged, static function (array $a, array $b): int {
        return strcmp((string) ($b['title'] ?? ''), (string) ($a['title'] ?? ''));
    });

    $total = count($merged);
    $slice = array_slice($merged, $offset, $limit);
    $totalPages = max(1, (int) ceil($total / $limit));

    if ($page > $totalPages && $total > 0) {
        $page = $totalPages;
    }

    $resultado['items'] = $slice;
    $resultado['meta'] = [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'totalPages' => $totalPages,
    ];

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha de comunicação com o servidor.']);
    exit;
}
