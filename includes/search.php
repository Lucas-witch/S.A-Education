<?php
declare(strict_types=1);

function pesquisar_catalogo(PDO $pdo, int $usuarioId, bool $usuarioPremium, string $q, string $assunto, string $materia, string $tipo, string $acesso, int $page, int $limit): array {
    $supportedTypes = ['sala', 'aula', 'video_aula', 'material', 'pdf', 'livro', 'apostila', 'artigo', 'redacao', 'post'];
    if ($tipo !== '' && !in_array($tipo, $supportedTypes, true)) {
        throw new InvalidArgumentException('Tipo de conteúdo inválido.');
    }
    if (!in_array($acesso, ['', 'publica', 'privada', 'premium'], true)) {
        throw new InvalidArgumentException('Filtro de acesso inválido.');
    }

    $includeRooms = $tipo === '' || $tipo === 'sala';
    if ($acesso === 'premium') {
        $includeRooms = false;
    }
    $publicationTypes = [
        'aula' => ['video_aula'],
        'video_aula' => ['video_aula'],
        'material' => ['pdf', 'livro', 'material'],
        'pdf' => ['pdf'],
        'livro' => ['livro'],
        'artigo' => ['artigo'],
        'redacao' => ['redacao'],
        'post' => ['post'],
    ];
    $libraryTypes = ['livro', 'pdf', 'apostila', 'artigo', 'material'];
    $includePublications = $tipo === '' || isset($publicationTypes[$tipo]);
    $includeLibrary = ($tipo === '' || in_array($tipo, $libraryTypes, true)) && !in_array($acesso, ['privada', 'premium'], true);
    $parts = [];
    $params = [];

    if ($includeRooms) {
        $roomWhere = [];
        $roomParams = [];
        if ($usuarioId > 0) {
            $roomWhere[] = '(s.privacidade = "publica" OR EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = s.id AND su.usuario_id = :r_user_id AND su.status = "ativo"))';
            $roomParams[':r_user_id'] = $usuarioId;
        } else {
            $roomWhere[] = 's.privacidade = "publica"';
        }
        if ($q !== '') {
            $roomWhere[] = '(LOWER(s.nome) LIKE :r_q_nome OR LOWER(COALESCE(s.prof, "")) LIKE :r_q_prof OR LOWER(COALESCE(s.materia, "")) LIKE :r_q_materia OR LOWER(COALESCE(s.descricao, "")) LIKE :r_q_desc)';
            $like = '%' . mb_strtolower($q) . '%';
            $roomParams += [':r_q_nome' => $like, ':r_q_prof' => $like, ':r_q_materia' => $like, ':r_q_desc' => $like];
        }
        if ($assunto !== '') {
            $roomWhere[] = '(LOWER(s.nome) LIKE :r_assunto_nome OR LOWER(COALESCE(s.materia, "")) LIKE :r_assunto_materia OR LOWER(COALESCE(s.descricao, "")) LIKE :r_assunto_desc)';
            $like = '%' . mb_strtolower($assunto) . '%';
            $roomParams += [':r_assunto_nome' => $like, ':r_assunto_materia' => $like, ':r_assunto_desc' => $like];
        }
        if ($materia !== '') {
            $roomWhere[] = 'LOWER(COALESCE(s.materia, "")) LIKE :r_materia';
            $roomParams[':r_materia'] = '%' . mb_strtolower($materia) . '%';
        }
        if ($acesso === 'publica' || $acesso === 'privada') {
            $roomWhere[] = 's.privacidade = :r_access';
            $roomParams[':r_access'] = $acesso;
        }
        $parts[] = 'SELECT "sala" AS source_type, "sala" AS content_type, s.id, s.nome AS title, COALESCE(s.descricao, "") AS description, COALESCE(s.prof, "") AS responsible, "Sala de estudo" AS category, COALESCE(s.materia, "") AS subject, s.privacidade AS access, 0 AS premium_only FROM salas s WHERE ' . implode(' AND ', $roomWhere);
        $params += $roomParams;
    }

    if ($includePublications) {
        $publicationWhere = ['p.status = "publicado"'];
        $publicationParams = [];
        $publicationWhere[] = '(p.visibilidade = "publica" OR (p.visibilidade = "privada" AND p.usuario_id = :p_owner_id) OR (p.visibilidade = "sala" AND EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = p.sala_id AND su.usuario_id = :p_room_user_id AND su.status = "ativo")) OR (p.visibilidade = "instituicao" AND EXISTS (SELECT 1 FROM instituicoes i WHERE i.id = p.instituicao_id AND i.usuario_id = :p_institution_owner_id)))';
        $publicationParams += [':p_owner_id' => $usuarioId, ':p_room_user_id' => $usuarioId, ':p_institution_owner_id' => $usuarioId];
        if (!$usuarioPremium) {
            $publicationWhere[] = 'p.is_premium_only = 0';
        }
        if ($q !== '') {
            $publicationWhere[] = '(LOWER(p.titulo) LIKE :p_q_titulo OR LOWER(COALESCE(p.resumo, "")) LIKE :p_q_resumo OR LOWER(COALESCE(p.conteudo, "")) LIKE :p_q_conteudo OR LOWER(COALESCE(p.categoria, "")) LIKE :p_q_categoria OR LOWER(COALESCE(u.nome_completo, "")) LIKE :p_q_autor)';
            $like = '%' . mb_strtolower($q) . '%';
            $publicationParams += [':p_q_titulo' => $like, ':p_q_resumo' => $like, ':p_q_conteudo' => $like, ':p_q_categoria' => $like, ':p_q_autor' => $like];
        }
        if ($assunto !== '') {
            $publicationWhere[] = '(LOWER(p.tipo) LIKE :p_assunto_tipo OR LOWER(COALESCE(p.categoria, "")) LIKE :p_assunto_categoria OR LOWER(p.titulo) LIKE :p_assunto_titulo OR LOWER(COALESCE(p.resumo, "")) LIKE :p_assunto_resumo)';
            $like = '%' . mb_strtolower($assunto) . '%';
            $publicationParams += [':p_assunto_tipo' => $like, ':p_assunto_categoria' => $like, ':p_assunto_titulo' => $like, ':p_assunto_resumo' => $like];
        }
        if ($materia !== '') {
            $publicationWhere[] = 'LOWER(COALESCE(p.categoria, "")) LIKE :p_materia';
            $publicationParams[':p_materia'] = '%' . mb_strtolower($materia) . '%';
        }
        if (isset($publicationTypes[$tipo])) {
            $placeholders = [];
            foreach ($publicationTypes[$tipo] as $index => $publicationType) {
                $parameter = ':p_type_' . $index;
                $placeholders[] = $parameter;
                $publicationParams[$parameter] = $publicationType;
            }
            $publicationWhere[] = 'p.tipo IN (' . implode(', ', $placeholders) . ')';
        }
        if ($acesso === 'publica') {
            $publicationWhere[] = 'p.visibilidade = "publica"';
        } elseif ($acesso === 'privada') {
            $publicationWhere[] = 'p.visibilidade = "privada"';
        } elseif ($acesso === 'premium') {
            $publicationWhere[] = 'p.is_premium_only = 1';
        }
        $parts[] = 'SELECT "publicacao" AS source_type, p.tipo AS content_type, p.id, p.titulo AS title, COALESCE(p.resumo, p.conteudo, "") AS description, COALESCE(u.nome_completo, "") AS responsible, COALESCE(p.categoria, "") AS category, COALESCE(p.categoria, "") AS subject, p.visibilidade AS access, p.is_premium_only AS premium_only FROM publicacoes p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $publicationWhere);
        $params += $publicationParams;
    }

    if ($includeLibrary) {
        $libraryWhere = [
            'l.status = "ativo"',
            '(l.instituicao_id IS NULL OR EXISTS (SELECT 1 FROM instituicoes i WHERE i.id = l.instituicao_id AND i.usuario_id = :l_owner_id))',
        ];
        $libraryParams = [':l_owner_id' => $usuarioId];
        if ($q !== '') {
            $libraryWhere[] = '(LOWER(l.titulo) LIKE :l_q_titulo OR LOWER(COALESCE(l.autor, "")) LIKE :l_q_autor OR LOWER(COALESCE(l.materia, "")) LIKE :l_q_materia OR LOWER(COALESCE(l.descricao, "")) LIKE :l_q_descricao)';
            $like = '%' . mb_strtolower($q) . '%';
            $libraryParams += [':l_q_titulo' => $like, ':l_q_autor' => $like, ':l_q_materia' => $like, ':l_q_descricao' => $like];
        }
        if ($assunto !== '') {
            $libraryWhere[] = '(LOWER(l.tipo) LIKE :l_assunto_tipo OR LOWER(COALESCE(l.materia, "")) LIKE :l_assunto_materia OR LOWER(l.titulo) LIKE :l_assunto_titulo OR LOWER(COALESCE(l.descricao, "")) LIKE :l_assunto_descricao)';
            $like = '%' . mb_strtolower($assunto) . '%';
            $libraryParams += [':l_assunto_tipo' => $like, ':l_assunto_materia' => $like, ':l_assunto_titulo' => $like, ':l_assunto_descricao' => $like];
        }
        if ($materia !== '') {
            $libraryWhere[] = 'LOWER(COALESCE(l.materia, "")) LIKE :l_materia';
            $libraryParams[':l_materia'] = '%' . mb_strtolower($materia) . '%';
        }
        if (in_array($tipo, $libraryTypes, true)) {
            $libraryWhere[] = 'l.tipo = :l_type';
            $libraryParams[':l_type'] = $tipo;
        }
        if ($acesso === 'publica') {
            $libraryWhere[] = 'l.instituicao_id IS NULL';
        }
        $parts[] = 'SELECT "biblioteca" AS source_type, l.tipo AS content_type, l.id, l.titulo AS title, COALESCE(l.descricao, "") AS description, COALESCE(l.autor, "") AS responsible, l.tipo AS category, COALESCE(l.materia, "") AS subject, CASE WHEN l.instituicao_id IS NULL THEN "publica" ELSE "instituicao" END AS access, 0 AS premium_only FROM biblioteca l WHERE ' . implode(' AND ', $libraryWhere);
        $params += $libraryParams;
    }

    if ($parts === []) {
        return ['items' => [], 'meta' => ['page' => 1, 'limit' => $limit, 'total' => 0, 'totalPages' => 1]];
    }

    $union = implode(' UNION ALL ', $parts);
    $count = $pdo->prepare('SELECT COUNT(*) FROM (' . $union . ') AS catalog_items');
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $limit;

    $statement = $pdo->prepare('SELECT * FROM (' . $union . ') AS catalog_items ORDER BY title ASC, source_type ASC, id ASC LIMIT :result_limit OFFSET :result_offset');
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $typeLabels = [
        'video_aula' => 'Videoaula',
        'artigo' => 'Artigo',
        'redacao' => 'Redação',
        'pdf' => 'PDF',
        'livro' => 'Livro',
        'post' => 'Publicação',
        'apostila' => 'Apostila',
        'material' => 'Material',
    ];
    $items = [];
    foreach ($rows as $row) {
        $source = (string) $row['source_type'];
        $publicationType = (string) $row['content_type'];
        $access = (string) $row['access'];
        $premiumOnly = (int) $row['premium_only'] === 1;
        $items[] = [
            'id' => (int) $row['id'],
            'source' => $source,
            'type' => $source === 'sala' ? 'sala' : $publicationType,
            'typeLabel' => $source === 'sala' ? 'Sala de estudo' : ($typeLabels[$publicationType] ?? 'Conteúdo'),
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'responsible' => (string) $row['responsible'],
            'category' => (string) $row['category'],
            'subject' => (string) $row['subject'],
            'access' => $access,
            'accessLabel' => $premiumOnly ? 'Plano Premium' : ($access === 'privada' ? 'Acesso privado' : ($access === 'sala' ? 'Acesso pela sala' : ($access === 'instituicao' ? 'Acesso institucional' : 'Acesso público'))),
            'premiumOnly' => $premiumOnly,
            'priceLabel' => null,
            'priceText' => null,
            'url' => $source === 'sala' ? 'sala.php?id=' . (int) $row['id'] : null,
        ];
    }

    return [
        'items' => $items,
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $totalPages],
    ];
}

function buscar_materiais_biblioteca(PDO $pdo, int $usuarioId, bool $usuarioPremium, string $q, string $materia, string $tipo, int $limit = 80): array {
    $supportedTypes = ['', 'livro', 'apostila', 'pdf', 'artigo', 'video_aula'];
    if (!in_array($tipo, $supportedTypes, true)) {
        throw new InvalidArgumentException('Tipo de material inválido.');
    }

    $parts = [];
    $params = [];
    if ($tipo !== 'video_aula') {
        $libraryWhere = [
            'l.status = "ativo"',
            '(l.instituicao_id IS NULL OR EXISTS (SELECT 1 FROM instituicoes i WHERE i.id = l.instituicao_id AND i.usuario_id = :library_owner_id))',
        ];
        $libraryParams = [':library_owner_id' => $usuarioId];
        if ($q !== '') {
            $libraryWhere[] = '(LOWER(l.titulo) LIKE :library_q_title OR LOWER(COALESCE(l.autor, "")) LIKE :library_q_author OR LOWER(COALESCE(l.materia, "")) LIKE :library_q_subject OR LOWER(COALESCE(l.descricao, "")) LIKE :library_q_description)';
            $like = '%' . mb_strtolower($q) . '%';
            $libraryParams += [':library_q_title' => $like, ':library_q_author' => $like, ':library_q_subject' => $like, ':library_q_description' => $like];
        }
        if ($materia !== '') {
            $libraryWhere[] = 'LOWER(COALESCE(l.materia, "")) LIKE :library_subject';
            $libraryParams[':library_subject'] = '%' . mb_strtolower($materia) . '%';
        }
        if ($tipo !== '') {
            $libraryWhere[] = 'l.tipo = :library_type';
            $libraryParams[':library_type'] = $tipo;
        }
        $parts[] = 'SELECT "biblioteca" AS source_type, l.id, l.titulo AS title, COALESCE(l.autor, "") AS responsible, l.tipo AS content_type, COALESCE(l.materia, "") AS subject, COALESCE(l.descricao, "") AS description, COALESCE(l.capa_url, "") AS cover_url, COALESCE(l.arquivo_url, "") AS resource_url, l.disponibilidade AS availability, CASE WHEN l.instituicao_id IS NULL THEN "publica" ELSE "instituicao" END AS access, 0 AS premium_only FROM biblioteca l WHERE ' . implode(' AND ', $libraryWhere);
        $params += $libraryParams;
    }

    if ($tipo === '' || in_array($tipo, ['artigo', 'pdf', 'livro', 'video_aula'], true)) {
        $publicationWhere = [
            'p.status = "publicado"',
            'p.tipo IN ("artigo", "pdf", "livro", "material", "video_aula")',
            '(p.visibilidade = "publica" OR (p.visibilidade = "privada" AND p.usuario_id = :publication_owner_id) OR (p.visibilidade = "sala" AND EXISTS (SELECT 1 FROM salas_usuarios su WHERE su.sala_id = p.sala_id AND su.usuario_id = :publication_room_user_id AND su.status = "ativo")) OR (p.visibilidade = "instituicao" AND EXISTS (SELECT 1 FROM instituicoes i WHERE i.id = p.instituicao_id AND i.usuario_id = :publication_institution_owner_id)))',
        ];
        $publicationParams = [
            ':publication_owner_id' => $usuarioId,
            ':publication_room_user_id' => $usuarioId,
            ':publication_institution_owner_id' => $usuarioId,
        ];
        if (!$usuarioPremium) {
            $publicationWhere[] = 'p.is_premium_only = 0';
        }
        if ($q !== '') {
            $publicationWhere[] = '(LOWER(p.titulo) LIKE :publication_q_title OR LOWER(COALESCE(u.nome_completo, "")) LIKE :publication_q_author OR LOWER(COALESCE(p.categoria, "")) LIKE :publication_q_subject OR LOWER(COALESCE(p.resumo, "")) LIKE :publication_q_summary OR LOWER(COALESCE(p.conteudo, "")) LIKE :publication_q_content)';
            $like = '%' . mb_strtolower($q) . '%';
            $publicationParams += [':publication_q_title' => $like, ':publication_q_author' => $like, ':publication_q_subject' => $like, ':publication_q_summary' => $like, ':publication_q_content' => $like];
        }
        if ($materia !== '') {
            $publicationWhere[] = 'LOWER(COALESCE(p.categoria, "")) LIKE :publication_subject';
            $publicationParams[':publication_subject'] = '%' . mb_strtolower($materia) . '%';
        }
        if ($tipo !== '') {
            $publicationWhere[] = 'p.tipo = :publication_type';
            $publicationParams[':publication_type'] = $tipo;
        }
        $parts[] = 'SELECT "publicacao" AS source_type, p.id, p.titulo AS title, COALESCE(u.nome_completo, "") AS responsible, p.tipo AS content_type, COALESCE(p.categoria, "") AS subject, COALESCE(p.resumo, p.conteudo, "") AS description, COALESCE(p.capa_url, "") AS cover_url, CASE WHEN p.tipo = "video_aula" THEN COALESCE(p.video_url, "") ELSE COALESCE(p.arquivo_url, "") END AS resource_url, "leitura_online" AS availability, p.visibilidade AS access, p.is_premium_only AS premium_only FROM publicacoes p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $publicationWhere);
        $params += $publicationParams;
    }

    if ($parts === []) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $parts) . ') AS library_items ORDER BY title ASC, source_type ASC, id ASC LIMIT :library_limit';
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':library_limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}