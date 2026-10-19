<?php
declare(strict_types=1);

function feed_authorization_scope(int $usuarioId): array {
    if ($usuarioId <= 0) {
        return ['sql' => '1 = 0', 'params' => []];
    }

    return [
        'sql' => '(p.visibilidade = "publica" OR (p.visibilidade = "privada" AND p.usuario_id = :feed_owner_id) OR (p.visibilidade = "sala" AND EXISTS (SELECT 1 FROM salas_usuarios su_feed WHERE su_feed.sala_id = p.sala_id AND su_feed.usuario_id = :feed_room_user_id AND su_feed.status = "ativo")) OR (p.visibilidade = "instituicao" AND EXISTS (SELECT 1 FROM instituicoes i_feed WHERE i_feed.id = p.instituicao_id AND i_feed.usuario_id = :feed_institution_owner_id)))',
        'params' => [
            ':feed_owner_id' => $usuarioId,
            ':feed_room_user_id' => $usuarioId,
            ':feed_institution_owner_id' => $usuarioId,
        ],
    ];
}

function feed_parse_id($value): int {
    if (!is_string($value) || !ctype_digit($value)) {
        throw new InvalidArgumentException('Identificador inválido.');
    }
    $id = (int) $value;
    if ($id <= 0) {
        throw new InvalidArgumentException('Identificador inválido.');
    }
    return $id;
}

function feed_read_field(array $input, string $field, int $limit, bool $required = false): string {
    $value = $input[$field] ?? '';
    if (!is_string($value) || mb_strlen($value) > $limit) {
        throw new InvalidArgumentException('Campo inválido.');
    }
    $value = trim($value);
    if ($required && $value === '') {
        throw new InvalidArgumentException('Preencha os campos obrigatórios.');
    }
    return $value;
}

function feed_safe_resource_url(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x00-\x20]/', $value)) {
        throw new InvalidArgumentException('URL de recurso inválida.');
    }
    $parts = parse_url($value);
    if (!is_array($parts)) {
        throw new InvalidArgumentException('URL de recurso inválida.');
    }
    if (isset($parts['scheme'])) {
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('A URL precisa usar HTTP ou HTTPS.');
        }
        return $value;
    }
    if (isset($parts['host']) || str_starts_with($value, '/') || str_contains($value, '\\')) {
        throw new InvalidArgumentException('Caminho de recurso inválido.');
    }
    foreach (explode('/', (string) ($parts['path'] ?? '')) as $segment) {
        if ($segment === '..' || $segment === '.') {
            throw new InvalidArgumentException('Caminho de recurso inválido.');
        }
    }
    return $value;
}

function feed_normalize_publication_input(array $input): array {
    $kind = $input['kind'] ?? null;
    if (!is_string($kind) || !in_array($kind, ['material', 'question', 'free_lesson'], true)) {
        throw new InvalidArgumentException('Tipo de publicação inválido.');
    }

    $title = feed_read_field($input, 'title', 200, true);
    $summary = feed_read_field($input, 'summary', 1000);
    $content = feed_read_field($input, 'content', 20000);
    $category = feed_read_field($input, 'category', 80);
    $visibility = feed_read_field($input, 'visibility', 12);
    if (!in_array($visibility, ['publica', 'privada'], true)) {
        throw new InvalidArgumentException('Visibilidade inválida.');
    }

    $resourceUrl = feed_safe_resource_url(feed_read_field($input, 'resource_url', 255));
    $videoUrl = '';
    if ($kind === 'material' && $content === '' && $resourceUrl === '') {
        throw new InvalidArgumentException('Material precisa de descrição ou URL de recurso.');
    }
    if ($kind === 'question' && $content === '') {
        throw new InvalidArgumentException('Informe o contexto ou a explicação da questão.');
    }
    if ($kind === 'free_lesson') {
        $videoUrl = feed_safe_resource_url(feed_read_field($input, 'video_url', 255, true));
        if ($videoUrl === '') {
            throw new InvalidArgumentException('Informe a URL da aula.');
        }
        $visibility = 'publica';
        $category = $category !== '' ? $category : 'Aula gratuita';
    } elseif ($kind === 'question') {
        $category = 'Questão' . ($category !== '' ? ': ' . $category : '');
    }

    $type = $kind === 'free_lesson' ? 'video_aula' : ($kind === 'question' ? 'post' : 'material');
    return [
        'tipo' => $type,
        'titulo' => $title,
        'resumo' => $summary !== '' ? $summary : null,
        'conteudo' => $content !== '' ? $content : null,
        'categoria' => $category !== '' ? $category : null,
        'arquivo_url' => $kind === 'material' && $resourceUrl !== '' ? $resourceUrl : null,
        'video_url' => $kind === 'free_lesson' ? $videoUrl : null,
        'visibilidade' => $visibility,
        'is_premium_only' => 0,
    ];
}

function feed_list_publications(PDO $pdo, int $usuarioId, bool $usuarioPremium, string $q, string $type, string $visibility, int $limit = 40): array {
    $allowedTypes = ['', 'video_aula', 'artigo', 'redacao', 'pdf', 'livro', 'post', 'material'];
    $allowedVisibility = ['', 'publica', 'privada', 'sala', 'instituicao', 'premium'];
    if (!in_array($type, $allowedTypes, true) || !in_array($visibility, $allowedVisibility, true)) {
        throw new InvalidArgumentException('Filtro de Feed inválido.');
    }

    $scope = feed_authorization_scope($usuarioId);
    $where = ['p.status = "publicado"', $scope['sql'], '(p.tipo <> "video_aula" OR p.is_premium_only = 0)'];
    $params = $scope['params'];
    if (!$usuarioPremium) {
        $where[] = '(p.is_premium_only = 0 OR p.usuario_id = :feed_premium_owner_id)';
        $params[':feed_premium_owner_id'] = $usuarioId;
    }
    if ($q !== '') {
        $where[] = '(LOWER(p.titulo) LIKE :feed_q_title OR LOWER(COALESCE(p.resumo, "")) LIKE :feed_q_summary OR LOWER(COALESCE(p.conteudo, "")) LIKE :feed_q_content OR LOWER(COALESCE(p.categoria, "")) LIKE :feed_q_category OR LOWER(COALESCE(u.nome_completo, "")) LIKE :feed_q_author)';
        $like = '%' . mb_strtolower($q) . '%';
        $params += [
            ':feed_q_title' => $like,
            ':feed_q_summary' => $like,
            ':feed_q_content' => $like,
            ':feed_q_category' => $like,
            ':feed_q_author' => $like,
        ];
    }
    if ($type !== '') {
        $where[] = 'p.tipo = :feed_type';
        $params[':feed_type'] = $type;
    }
    if ($visibility === 'premium') {
        $where[] = 'p.is_premium_only = 1';
    } elseif ($visibility !== '') {
        $where[] = 'p.visibilidade = :feed_visibility';
        $params[':feed_visibility'] = $visibility;
    }

    $limit = max(1, min(80, $limit));
    $sql = 'SELECT p.id, p.usuario_id, p.sala_id, p.tipo, p.titulo, p.resumo, p.conteudo, p.categoria, p.arquivo_url, p.video_url, p.visibilidade, p.is_premium_only, p.publicado_em, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem, '
        . '(SELECT COUNT(*) FROM publicacao_curtidas pc WHERE pc.publicacao_id = p.id) AS curtidas, '
        . '(SELECT COUNT(*) FROM publicacao_comentarios cm WHERE cm.publicacao_id = p.id) AS comentarios, '
        . 'EXISTS(SELECT 1 FROM publicacao_curtidas pc_user WHERE pc_user.publicacao_id = p.id AND pc_user.usuario_id = :feed_like_user) AS usuario_curtiu, '
        . 'EXISTS(SELECT 1 FROM publicacao_salvamentos ps_user WHERE ps_user.publicacao_id = p.id AND ps_user.usuario_id = :feed_save_user) AS usuario_salvou '
        . 'FROM publicacoes p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.publicado_em DESC, p.id DESC LIMIT :feed_limit';
    $params[':feed_like_user'] = $usuarioId;
    $params[':feed_save_user'] = $usuarioId;
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':feed_limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function feed_get_publication(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium): ?array {
    if ($publicationId <= 0) {
        return null;
    }
    $scope = feed_authorization_scope($usuarioId);
    $sql = 'SELECT p.*, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem FROM publicacoes p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = :feed_publication_id AND p.status = "publicado" AND ' . $scope['sql'] . ' AND (p.tipo <> "video_aula" OR p.is_premium_only = 0)';
    $params = [':feed_publication_id' => $publicationId] + $scope['params'];
    if (!$usuarioPremium) {
        $sql .= ' AND (p.is_premium_only = 0 OR p.usuario_id = :feed_premium_owner_id)';
        $params[':feed_premium_owner_id'] = $usuarioId;
    }
    $statement = $pdo->prepare($sql . ' LIMIT 1');
    $statement->execute($params);
    $publication = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($publication) ? $publication : null;
}

function feed_create_publication(PDO $pdo, int $usuarioId, array $input): int {
    if ($usuarioId <= 0) {
        throw new DomainException('Autenticação necessária.');
    }
    $data = feed_normalize_publication_input($input);
    $statement = $pdo->prepare('INSERT INTO publicacoes (usuario_id, tipo, titulo, resumo, conteudo, arquivo_url, video_url, categoria, visibilidade, status, is_premium_only) VALUES (:usuario_id, :tipo, :titulo, :resumo, :conteudo, :arquivo_url, :video_url, :categoria, :visibilidade, "publicado", 0)');
    $statement->execute([
        ':usuario_id' => $usuarioId,
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
    ]);
    return (int) $pdo->lastInsertId();
}

function feed_update_publication(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium, array $input): void {
    $publication = feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium);
    if (!$publication || (int) $publication['usuario_id'] !== $usuarioId) {
        throw new DomainException('Publicação indisponível para alteração.');
    }
    $data = feed_normalize_publication_input($input);
    if ($data['tipo'] !== 'video_aula') {
        $data['is_premium_only'] = (int) $publication['is_premium_only'];
    }
    $statement = $pdo->prepare('UPDATE publicacoes SET tipo = :tipo, titulo = :titulo, resumo = :resumo, conteudo = :conteudo, arquivo_url = :arquivo_url, video_url = :video_url, categoria = :categoria, visibilidade = :visibilidade, is_premium_only = :premium_only WHERE id = :id AND usuario_id = :usuario_id AND status = "publicado"');
    $statement->execute([
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
        ':premium_only' => $data['is_premium_only'],
        ':id' => $publicationId,
        ':usuario_id' => $usuarioId,
    ]);
}

function feed_archive_publication(PDO $pdo, int $publicationId, int $usuarioId): void {
    $statement = $pdo->prepare('UPDATE publicacoes SET status = "arquivado" WHERE id = :id AND usuario_id = :usuario_id AND status = "publicado"');
    $statement->execute([':id' => $publicationId, ':usuario_id' => $usuarioId]);
    if ($statement->rowCount() !== 1) {
        throw new DomainException('Publicação indisponível para remoção.');
    }
}

function feed_toggle_interaction(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium, string $interaction): array {
    $tables = [
        'like' => 'publicacao_curtidas',
        'save' => 'publicacao_salvamentos',
    ];
    if (!isset($tables[$interaction])) {
        throw new InvalidArgumentException('Ação inválida.');
    }
    if (!feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium)) {
        throw new DomainException('Publicação não encontrada.');
    }
    $table = $tables[$interaction];
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE publicacao_id = :publication_id AND usuario_id = :usuario_id');
        $statement->execute([':publication_id' => $publicationId, ':usuario_id' => $usuarioId]);
        $active = (bool) $statement->fetchColumn();
        if ($active) {
            $statement = $pdo->prepare('DELETE FROM ' . $table . ' WHERE publicacao_id = :publication_id AND usuario_id = :usuario_id');
            $statement->execute([':publication_id' => $publicationId, ':usuario_id' => $usuarioId]);
            $active = false;
        } else {
            $statement = $pdo->prepare('INSERT INTO ' . $table . ' (publicacao_id, usuario_id) VALUES (:publication_id, :usuario_id)');
            $statement->execute([':publication_id' => $publicationId, ':usuario_id' => $usuarioId]);
            $active = true;
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE publicacao_id = :publication_id');
    $count->execute([':publication_id' => $publicationId]);
    return ['active' => $active, 'count' => (int) $count->fetchColumn()];
}

function feed_add_comment(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium, string $content): array {
    $content = trim($content);
    if ($content === '' || mb_strlen($content) > 2000) {
        throw new InvalidArgumentException('Comentário inválido.');
    }
    if (!feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium)) {
        throw new DomainException('Publicação não encontrada.');
    }
    $statement = $pdo->prepare('INSERT INTO publicacao_comentarios (publicacao_id, usuario_id, conteudo) VALUES (:publication_id, :usuario_id, :content)');
    $statement->execute([':publication_id' => $publicationId, ':usuario_id' => $usuarioId, ':content' => $content]);
    $commentId = (int) $pdo->lastInsertId();
    $read = $pdo->prepare('SELECT c.id, c.publicacao_id, c.usuario_id, c.conteudo, c.criado_em, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem FROM publicacao_comentarios c INNER JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = :id LIMIT 1');
    $read->execute([':id' => $commentId]);
    return $read->fetch(PDO::FETCH_ASSOC) ?: [];
}

function feed_list_comments(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium): array {
    if (!feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium)) {
        throw new DomainException('Publicação não encontrada.');
    }
    $statement = $pdo->prepare('SELECT c.id, c.publicacao_id, c.usuario_id, c.conteudo, c.criado_em, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem FROM publicacao_comentarios c INNER JOIN usuarios u ON u.id = c.usuario_id WHERE c.publicacao_id = :publication_id ORDER BY c.criado_em ASC, c.id ASC LIMIT 100');
    $statement->execute([':publication_id' => $publicationId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function feed_delete_comment(PDO $pdo, int $commentId, int $usuarioId, bool $usuarioPremium): void {
    $statement = $pdo->prepare('SELECT c.usuario_id AS comment_owner, c.publicacao_id, p.usuario_id AS publication_owner FROM publicacao_comentarios c INNER JOIN publicacoes p ON p.id = c.publicacao_id WHERE c.id = :id AND p.status = "publicado" LIMIT 1');
    $statement->execute([':id' => $commentId]);
    $comment = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$comment || !feed_get_publication($pdo, (int) $comment['publicacao_id'], $usuarioId, $usuarioPremium)) {
        throw new DomainException('Comentário não encontrado.');
    }
    if ((int) $comment['comment_owner'] !== $usuarioId && (int) $comment['publication_owner'] !== $usuarioId) {
        throw new DomainException('Permissão insuficiente para remover o comentário.');
    }
    $delete = $pdo->prepare('DELETE FROM publicacao_comentarios WHERE id = :id');
    $delete->execute([':id' => $commentId]);
}