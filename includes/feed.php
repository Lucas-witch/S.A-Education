<?php
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/room_actions.php';
require_once __DIR__ . '/digital_library.php';

function feed_store_attachment(array $upload, string $kind): array {
    return library_store_private_upload($upload, $kind === 'free_lesson' ? 'video' : 'feed');
}

function feed_discard_attachment(array $attachment): void {
    $path = $attachment['absolute_path'] ?? null;
    if (is_string($path) && is_file($path) && !unlink($path)) {
        error_log('[S.A Education][feed_storage] Não foi possível remover um arquivo não associado a uma publicação.');
    }
}

function feed_remove_replaced_attachment(array $previous): void {
    $key = $previous['media_storage_key'] ?? null;
    $size = (int) ($previous['media_size'] ?? 0);
    $hash = (string) ($previous['media_sha256'] ?? '');
    if (!is_string($key) || $key === '') {
        return;
    }
    $path = library_private_file_path($key, $size, $hash);
    if ($path !== null && !unlink($path)) {
        error_log('[S.A Education][feed_storage] Não foi possível remover o arquivo substituído.');
    }
}

function feed_authorization_scope(int $usuarioId): array {
    if ($usuarioId <= 0) {
        return ['sql' => '1 = 0', 'params' => []];
    }

    $roomInstitutionScope = institution_content_access_condition(
        'feed_room.institution_id',
        ':feed_room_institution_owner_id',
        ':feed_room_institution_member_id'
    );
    $adminRoomAccess = configured_admin_user_id() === $usuarioId ? ' OR 1 = 1' : '';
    return [
        'sql' => '(p.visibilidade = "publica" OR (p.visibilidade = "privada" AND p.usuario_id = :feed_owner_id) OR (p.visibilidade = "sala" AND (EXISTS (SELECT 1 FROM salas_usuarios su_feed INNER JOIN salas feed_room ON feed_room.id = su_feed.sala_id WHERE su_feed.sala_id = p.sala_id AND su_feed.usuario_id = :feed_room_user_id AND su_feed.status = "ativo" AND (feed_room.privacidade <> "instituicao" OR ' . $roomInstitutionScope . '))' . $adminRoomAccess . ')) OR (p.visibilidade = "instituicao" AND ' . institution_content_access_condition('p.instituicao_id', ':feed_institution_owner_id', ':feed_institution_member_id') . ' AND ' . institution_learning_resource_access_condition('publication', 'p.id', 'p.instituicao_id', ':feed_resource_owner_id', ':feed_resource_member_id') . '))',
        'params' => [
            ':feed_owner_id' => $usuarioId,
            ':feed_room_user_id' => $usuarioId,
            ':feed_room_institution_owner_id' => $usuarioId,
            ':feed_room_institution_member_id' => $usuarioId,
            ':feed_institution_owner_id' => $usuarioId,
            ':feed_institution_member_id' => $usuarioId,
            ':feed_resource_owner_id' => $usuarioId,
            ':feed_resource_member_id' => $usuarioId,
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

function feed_normalize_publication_input(array $input, bool $draft = false, bool $hasAttachment = false): array {
    $kind = $input['kind'] ?? null;
    if (!is_string($kind) || !in_array($kind, ['material', 'question', 'bank_question', 'post', 'free_lesson'], true)) {
        throw new InvalidArgumentException('Tipo de publicação inválido.');
    }

    $title = feed_read_field($input, 'title', 200, !$draft);
    $summary = feed_read_field($input, 'summary', 1000);
    $content = feed_read_field($input, 'content', 20000);
    $category = feed_read_field($input, 'category', 80);
    $visibility = feed_read_field($input, 'visibility', 12);
    if (!in_array($visibility, ['publica', 'privada'], true)) {
        if ($visibility !== 'sala') {
            throw new InvalidArgumentException('Visibilidade inválida.');
        }
    }
    $roomValue = $input['room_id'] ?? '';
    if (!is_string($roomValue) || ($roomValue !== '' && !ctype_digit($roomValue))) {
        throw new InvalidArgumentException('Sala inválida.');
    }
    $roomId = $roomValue === '' ? null : feed_parse_id($roomValue);
    if (($visibility === 'sala') !== ($roomId !== null)) {
        throw new InvalidArgumentException('Selecione uma sala para uma publicação restrita a participantes.');
    }

    $resourceUrl = feed_safe_resource_url(feed_read_field($input, 'resource_url', 255));
    $videoUrl = '';
    if (!$draft && $kind === 'material' && $content === '' && $resourceUrl === '' && !$hasAttachment) {
        throw new InvalidArgumentException('Material precisa de descrição, link ou arquivo.');
    }
    if (!$draft && in_array($kind, ['question', 'bank_question'], true) && $content === '') {
        throw new InvalidArgumentException('Informe o contexto ou a explicação da questão.');
    }
    if ($kind === 'free_lesson') {
        $videoUrl = feed_safe_resource_url(feed_read_field($input, 'video_url', 255));
        if (!$draft && $videoUrl === '' && !$hasAttachment) {
            throw new InvalidArgumentException('Envie um vídeo MP4/WEBM ou informe a URL da aula.');
        }
        $visibility = 'publica';
        $category = $category !== '' ? $category : 'Aula gratuita';
    } elseif ($kind === 'question') {
        $category = 'Dúvida' . ($category !== '' ? ': ' . $category : '');
    } elseif ($kind === 'bank_question') {
        $category = 'Banco de questões' . ($category !== '' ? ': ' . $category : '');
    } elseif ($kind === 'post' && !$draft && $content === '' && !$hasAttachment) {
        throw new InvalidArgumentException('Escreva o conteúdo ou anexe um arquivo à publicação.');
    }
    if ($kind === 'post' && $content !== '') {
        $content = library_sanitize_rich_text($content);
        if (strlen($content) > 60000) {
            throw new InvalidArgumentException('O texto formatado excede o tamanho permitido para uma publicação.');
        }
    }

    $type = $kind === 'free_lesson' ? 'video_aula' : (in_array($kind, ['question', 'bank_question', 'post'], true) ? 'post' : 'material');
    $lessonFields = [];
    if ($kind === 'free_lesson') {
        $lessonFields = [
            'education_stage' => feed_read_field($input, 'education_stage', 100),
            'knowledge_area' => feed_read_field($input, 'knowledge_area', 120),
            'subject' => feed_read_field($input, 'subject', 120),
            'topic' => feed_read_field($input, 'topic', 180),
            'subtopic' => feed_read_field($input, 'subtopic', 180),
            'course_title' => feed_read_field($input, 'course_title', 180),
            'complementary_materials' => feed_safe_resource_url(feed_read_field($input, 'complementary_materials', 1000)),
            'related_exercises' => feed_safe_resource_url(feed_read_field($input, 'related_exercises', 1000)),
            'captions_url' => feed_safe_resource_url(feed_read_field($input, 'captions_url', 1000)),
        ];
    } else {
        $lessonFields = [
            'education_stage' => '',
            'knowledge_area' => '',
            'subject' => '',
            'topic' => '',
            'subtopic' => '',
            'course_title' => '',
            'complementary_materials' => '',
            'related_exercises' => '',
            'captions_url' => '',
        ];
    }
    return [
        'tipo' => $type,
        'titulo' => $title,
        'resumo' => $summary !== '' ? $summary : null,
        'conteudo' => $content !== '' ? $content : null,
        'categoria' => $category !== '' ? $category : null,
        'arquivo_url' => $kind === 'material' && $resourceUrl !== '' ? $resourceUrl : null,
        'video_url' => $kind === 'free_lesson' && $videoUrl !== '' ? $videoUrl : null,
        'visibilidade' => $visibility,
        'sala_id' => $roomId,
        'is_premium_only' => 0,
        'is_bank_question' => $kind === 'bank_question',
    ] + $lessonFields;
}

function feed_list_publications(
    PDO $pdo,
    int $usuarioId,
    bool $usuarioPremium,
    string $q,
    string $type,
    string $visibility,
    int $limit = 40,
    ?int $roomId = null,
    int $page = 1,
    ?bool &$hasMore = null
): array {
    $allowedTypes = ['', 'video_aula', 'artigo', 'redacao', 'pdf', 'livro', 'post', 'material'];
    $allowedVisibility = ['', 'publica', 'privada', 'sala', 'instituicao', 'premium'];
    if (!in_array($type, $allowedTypes, true) || !in_array($visibility, $allowedVisibility, true)) {
        throw new InvalidArgumentException('Filtro de Feed inválido.');
    }
    if ($page < 1 || $page > 10000) {
        throw new InvalidArgumentException('Página do Feed inválida.');
    }

    $scope = feed_authorization_scope($usuarioId);
    $where = ['p.status = "publicado"', $scope['sql']];
    $params = $scope['params'];
    if (!$usuarioPremium) {
        $where[] = '(p.is_premium_only = 0 OR p.usuario_id = :feed_premium_owner_id)';
        $params[':feed_premium_owner_id'] = $usuarioId;
    }
    if ($q !== '') {
        $searchColumns = [
            'p.titulo',
            'p.resumo',
            'p.conteudo',
            'p.categoria',
            'p.subject',
            'p.topic',
            'p.subtopic',
            'p.education_stage',
            'p.knowledge_area',
            'p.course_title',
            'u.nome_completo',
            'u.username',
        ];
        $searchConditions = [];
        $like = '%' . mb_strtolower($q, 'UTF-8') . '%';
        foreach ($searchColumns as $index => $column) {
            $placeholder = ':feed_q_' . $index;
            $searchConditions[] = 'LOWER(COALESCE(' . $column . ', "")) LIKE ' . $placeholder;
            $params[$placeholder] = $like;
        }
        $where[] = '(' . implode(' OR ', $searchConditions) . ')';
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
    if ($roomId !== null) {
        if ($roomId <= 0) {
            throw new InvalidArgumentException('Sala inválida.');
        }
        $where[] = 'p.sala_id = :feed_room_filter_id';
        $params[':feed_room_filter_id'] = $roomId;
    }

    $limit = max(1, min(80, $limit));
    $sql = 'SELECT p.id, p.usuario_id, p.sala_id, p.tipo, p.titulo, p.resumo, p.conteudo, p.categoria, p.arquivo_url, p.video_url, p.media_storage_key, p.media_mime, p.media_size, p.media_sha256, p.visibilidade, p.is_premium_only, p.is_official, p.education_stage, p.knowledge_area, p.subject, p.topic, p.subtopic, p.course_title, p.complementary_materials, p.related_exercises, p.captions_url, p.publicado_em, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem, '
        . '(SELECT COUNT(*) FROM publicacao_curtidas pc WHERE pc.publicacao_id = p.id) AS curtidas, '
        . '(SELECT COUNT(*) FROM publicacao_comentarios cm WHERE cm.publicacao_id = p.id) AS comentarios, '
        . 'EXISTS(SELECT 1 FROM publicacao_curtidas pc_user WHERE pc_user.publicacao_id = p.id AND pc_user.usuario_id = :feed_like_user) AS usuario_curtiu, '
        . 'EXISTS(SELECT 1 FROM publicacao_salvamentos ps_user WHERE ps_user.publicacao_id = p.id AND ps_user.usuario_id = :feed_save_user) AS usuario_salvou '
        . 'FROM publicacoes p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE ' . implode(' AND ', $where)
        . ' ORDER BY p.publicado_em DESC, p.id DESC LIMIT :feed_limit OFFSET :feed_offset';
    $params[':feed_like_user'] = $usuarioId;
    $params[':feed_save_user'] = $usuarioId;
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':feed_limit', $limit + 1, PDO::PARAM_INT);
    $statement->bindValue(':feed_offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $statement->execute();
    $publications = $statement->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($publications) > $limit;
    if ($hasMore) {
        array_pop($publications);
    }
    return $publications;
}

function feed_get_publication(PDO $pdo, int $publicationId, int $usuarioId, bool $usuarioPremium): ?array {
    if ($publicationId <= 0) {
        return null;
    }
    $scope = feed_authorization_scope($usuarioId);
    $sql = 'SELECT p.*, u.nome_completo AS autor, u.perfil AS autor_perfil, u.username AS autor_username, u.perfil_imagem AS autor_imagem FROM publicacoes p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = :feed_publication_id AND p.status = "publicado" AND ' . $scope['sql'];
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

function feed_assert_room_publication_access(PDO $pdo, int $usuarioId, array $data): void {
    $roomId = $data['sala_id'] ?? null;
    if ($roomId === null) {
        return;
    }
    $statement = $pdo->prepare(
        'SELECT id, criador_id, institution_id, privacidade FROM salas WHERE id = :room_id LIMIT 1'
    );
    $statement->execute(['room_id' => (int) $roomId]);
    $room = $statement->fetch(PDO::FETCH_ASSOC);
    $isAdmin = configured_admin_user_id() === $usuarioId;
    if (
        !is_array($room)
        || !room_user_can_access($pdo, $room, $usuarioId, $isAdmin)
        || (!$isAdmin && !room_user_is_member($pdo, (int) $roomId, $usuarioId))
    ) {
        throw new PermissionDeniedException('É necessário participar da sala para publicar nela.');
    }
}

function feed_user_can_moderate_room(PDO $pdo, int $roomId, int $usuarioId): bool {
    if ($roomId <= 0 || $usuarioId <= 0) {
        return false;
    }
    $statement = $pdo->prepare(
        'SELECT id, criador_id, institution_id, privacidade
         FROM salas WHERE id = :room_id LIMIT 1'
    );
    $statement->execute(['room_id' => $roomId]);
    $room = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($room)) {
        return false;
    }
    $isAdmin = configured_admin_user_id() === $usuarioId;
    return room_user_can_access($pdo, $room, $usuarioId, $isAdmin)
        && room_user_can_manage($pdo, $room, $usuarioId, $isAdmin);
}

function feed_create_publication(
    PDO $pdo,
    int $usuarioId,
    array $input,
    string $status = 'publicado',
    ?int $questionBankId = null,
    bool $isOfficial = false,
    ?array $upload = null
): int {
    if ($usuarioId <= 0) {
        throw new DomainException('Autenticação necessária.');
    }
    if (
        $isOfficial
        && (!function_exists('admin_user_can_manage_official_content')
            || !admin_user_can_manage_official_content($pdo, $usuarioId))
    ) {
        throw new PermissionDeniedException('A conta não está autorizada a publicar conteúdo oficial.');
    }
    if (!in_array($status, ['rascunho', 'publicado'], true)) {
        throw new InvalidArgumentException('Status de publicação inválido.');
    }
    $attachment = feed_store_attachment($upload ?? ['error' => UPLOAD_ERR_NO_FILE], (string) ($input['kind'] ?? ''));
    try {
        $data = feed_normalize_publication_input($input, $status === 'rascunho', $attachment['storage_key'] !== null);
        feed_assert_room_publication_access($pdo, $usuarioId, $data);
        $statement = $pdo->prepare('INSERT INTO publicacoes (usuario_id, sala_id, tipo, titulo, resumo, conteudo, arquivo_url, video_url, media_storage_key, media_mime, media_size, media_sha256, categoria, visibilidade, status, is_premium_only, question_bank_id, is_official, education_stage, knowledge_area, subject, topic, subtopic, course_title, complementary_materials, related_exercises, captions_url) VALUES (:usuario_id, :sala_id, :tipo, :titulo, :resumo, :conteudo, :arquivo_url, :video_url, :media_storage_key, :media_mime, :media_size, :media_sha256, :categoria, :visibilidade, :status, 0, :question_bank_id, :is_official, :education_stage, :knowledge_area, :subject, :topic, :subtopic, :course_title, :complementary_materials, :related_exercises, :captions_url)');
        $statement->execute([
        ':usuario_id' => $usuarioId,
        ':sala_id' => $data['sala_id'],
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':media_storage_key' => $attachment['storage_key'],
        ':media_mime' => $attachment['file_mime'],
        ':media_size' => $attachment['file_size'],
        ':media_sha256' => $attachment['file_sha256'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
        ':status' => $status,
        ':question_bank_id' => $questionBankId,
        ':is_official' => $isOfficial ? 1 : 0,
        ':education_stage' => $data['education_stage'] ?: null,
        ':knowledge_area' => $data['knowledge_area'] ?: null,
        ':subject' => $data['subject'] ?: null,
        ':topic' => $data['topic'] ?: null,
        ':subtopic' => $data['subtopic'] ?: null,
        ':course_title' => $data['course_title'] ?: null,
        ':complementary_materials' => $data['complementary_materials'] ?: null,
        ':related_exercises' => $data['related_exercises'] ?: null,
        ':captions_url' => $data['captions_url'] ?: null,
        ]);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $error) {
        feed_discard_attachment($attachment);
        throw $error;
    }
}

function feed_publish_draft(PDO $pdo, int $publicationId, int $usuarioId, array $input, ?int $questionBankId = null, ?array $upload = null): void {
    $previousQuery = $pdo->prepare(
        'SELECT media_storage_key, media_size, media_sha256 FROM publicacoes
         WHERE id = :id AND usuario_id = :usuario_id AND status = "rascunho" LIMIT 1'
    );
    $previousQuery->execute([':id' => $publicationId, ':usuario_id' => $usuarioId]);
    $previous = $previousQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($previous)) {
        throw new DomainException('Rascunho indisponível para publicação.');
    }
    $attachment = feed_store_attachment($upload ?? ['error' => UPLOAD_ERR_NO_FILE], (string) ($input['kind'] ?? ''));
    try {
    $hasAttachment = $attachment['storage_key'] !== null || !empty($previous['media_storage_key']);
    $data = feed_normalize_publication_input($input, false, $hasAttachment);
    feed_assert_room_publication_access($pdo, $usuarioId, $data);
    $statement = $pdo->prepare(
        'UPDATE publicacoes
         SET tipo = :tipo, titulo = :titulo, resumo = :resumo, conteudo = :conteudo,
             arquivo_url = :arquivo_url, video_url = :video_url, categoria = :categoria,
             media_storage_key = COALESCE(:media_storage_key, media_storage_key),
             media_mime = COALESCE(:media_mime, media_mime),
             media_size = COALESCE(:media_size, media_size),
             media_sha256 = COALESCE(:media_sha256, media_sha256),
             visibilidade = :visibilidade, status = "publicado", is_premium_only = 0,
             question_bank_id = :question_bank_id, publicado_em = CURRENT_TIMESTAMP,
             education_stage = :education_stage, knowledge_area = :knowledge_area,
             subject = :subject, topic = :topic, subtopic = :subtopic, course_title = :course_title,
             complementary_materials = :complementary_materials, related_exercises = :related_exercises,
             captions_url = :captions_url, sala_id = :sala_id
         WHERE id = :id AND usuario_id = :usuario_id AND status = "rascunho"'
    );
    $statement->execute([
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':media_storage_key' => $attachment['storage_key'],
        ':media_mime' => $attachment['file_mime'],
        ':media_size' => $attachment['file_size'],
        ':media_sha256' => $attachment['file_sha256'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
        ':question_bank_id' => $questionBankId,
        ':education_stage' => $data['education_stage'] ?: null,
        ':knowledge_area' => $data['knowledge_area'] ?: null,
        ':subject' => $data['subject'] ?: null,
        ':topic' => $data['topic'] ?: null,
        ':subtopic' => $data['subtopic'] ?: null,
        ':course_title' => $data['course_title'] ?: null,
        ':complementary_materials' => $data['complementary_materials'] ?: null,
        ':related_exercises' => $data['related_exercises'] ?: null,
        ':captions_url' => $data['captions_url'] ?: null,
        ':sala_id' => $data['sala_id'],
        ':id' => $publicationId,
        ':usuario_id' => $usuarioId,
    ]);
    if ($statement->rowCount() !== 1) {
        throw new DomainException('Rascunho indisponível para publicação.');
    }
    if ($attachment['storage_key'] !== null && $previous['media_storage_key'] !== null) {
        feed_remove_replaced_attachment($previous);
    }
    } catch (Throwable $error) {
        feed_discard_attachment($attachment);
        throw $error;
    }
}

function feed_update_draft(PDO $pdo, int $publicationId, int $usuarioId, array $input, ?array $upload = null): void {
    $exists = $pdo->prepare(
        'SELECT media_storage_key, media_size, media_sha256 FROM publicacoes
         WHERE id = :id AND usuario_id = :usuario_id AND status = "rascunho"'
    );
    $exists->execute([':id' => $publicationId, ':usuario_id' => $usuarioId]);
    $previous = $exists->fetch(PDO::FETCH_ASSOC);
    if (!is_array($previous)) {
        throw new DomainException('Rascunho indisponível para alteração.');
    }
    $attachment = feed_store_attachment($upload ?? ['error' => UPLOAD_ERR_NO_FILE], (string) ($input['kind'] ?? ''));
    try {
    $data = feed_normalize_publication_input($input, true, $attachment['storage_key'] !== null);
    feed_assert_room_publication_access($pdo, $usuarioId, $data);
    $statement = $pdo->prepare(
        'UPDATE publicacoes
         SET tipo = :tipo, titulo = :titulo, resumo = :resumo, conteudo = :conteudo,
             arquivo_url = :arquivo_url, video_url = :video_url, categoria = :categoria,
             media_storage_key = COALESCE(:media_storage_key, media_storage_key),
             media_mime = COALESCE(:media_mime, media_mime),
             media_size = COALESCE(:media_size, media_size),
             media_sha256 = COALESCE(:media_sha256, media_sha256),
             visibilidade = :visibilidade, education_stage = :education_stage,
             knowledge_area = :knowledge_area, subject = :subject, topic = :topic,
             subtopic = :subtopic, course_title = :course_title,
             complementary_materials = :complementary_materials,
             related_exercises = :related_exercises, captions_url = :captions_url,
             sala_id = :sala_id
         WHERE id = :id AND usuario_id = :usuario_id AND status = "rascunho"'
    );
    $statement->execute([
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':media_storage_key' => $attachment['storage_key'],
        ':media_mime' => $attachment['file_mime'],
        ':media_size' => $attachment['file_size'],
        ':media_sha256' => $attachment['file_sha256'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
        ':education_stage' => $data['education_stage'] ?: null,
        ':knowledge_area' => $data['knowledge_area'] ?: null,
        ':subject' => $data['subject'] ?: null,
        ':topic' => $data['topic'] ?: null,
        ':subtopic' => $data['subtopic'] ?: null,
        ':course_title' => $data['course_title'] ?: null,
        ':complementary_materials' => $data['complementary_materials'] ?: null,
        ':related_exercises' => $data['related_exercises'] ?: null,
        ':captions_url' => $data['captions_url'] ?: null,
        ':sala_id' => $data['sala_id'],
        ':id' => $publicationId,
        ':usuario_id' => $usuarioId,
    ]);
    if ($attachment['storage_key'] !== null && $previous['media_storage_key'] !== null) {
        feed_remove_replaced_attachment($previous);
    }
    } catch (Throwable $error) {
        feed_discard_attachment($attachment);
        throw $error;
    }
}

function feed_list_drafts(PDO $pdo, int $usuarioId): array {
    $statement = $pdo->prepare(
        'SELECT id, sala_id, tipo, titulo, resumo, conteudo, arquivo_url, video_url, categoria, visibilidade,
                media_storage_key, media_mime, media_size, media_sha256,
                education_stage, knowledge_area, subject, topic, subtopic, course_title,
                complementary_materials, related_exercises, captions_url
         FROM publicacoes
         WHERE usuario_id = :usuario_id AND status = "rascunho"
         ORDER BY atualizado_em DESC, id DESC'
    );
    $statement->execute([':usuario_id' => $usuarioId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function feed_update_publication(
    PDO $pdo,
    int $publicationId,
    int $usuarioId,
    bool $usuarioPremium,
    array $input,
    bool $adminOverride = false,
    ?array $upload = null
): void {
    $publication = feed_get_publication($pdo, $publicationId, $usuarioId, $usuarioPremium);
    $canManageOtherAuthor = $adminOverride && is_admin_user();
    if (!$publication || ((int) $publication['usuario_id'] !== $usuarioId && !$canManageOtherAuthor)) {
        throw new DomainException('Publicação indisponível para alteração.');
    }
    $attachment = feed_store_attachment($upload ?? ['error' => UPLOAD_ERR_NO_FILE], (string) ($input['kind'] ?? ''));
    try {
    $hasAttachment = $attachment['storage_key'] !== null || !empty($publication['media_storage_key']);
    $data = feed_normalize_publication_input($input, false, $hasAttachment);
    feed_assert_room_publication_access($pdo, $usuarioId, $data);
    if ($data['tipo'] !== $publication['tipo']) {
        throw new PermissionDeniedException('O tipo de uma publicação existente não pode ser alterado. Crie uma nova publicação para outra categoria.');
    }
    $wasBankQuestion = str_starts_with((string) ($publication['categoria'] ?? ''), 'Banco de questões');
    if ($wasBankQuestion !== $data['is_bank_question']) {
        throw new PermissionDeniedException('A categoria do conteúdo não pode ser alterada após a publicação.');
    }
    $data['is_premium_only'] = (int) $publication['is_premium_only'];
    if ($data['tipo'] === 'video_aula') {
        $data['visibilidade'] = (string) $publication['visibilidade'];
    }
    $statement = $pdo->prepare('UPDATE publicacoes SET sala_id = :sala_id, tipo = :tipo, titulo = :titulo, resumo = :resumo, conteudo = :conteudo, arquivo_url = :arquivo_url, video_url = :video_url, media_storage_key = COALESCE(:media_storage_key, media_storage_key), media_mime = COALESCE(:media_mime, media_mime), media_size = COALESCE(:media_size, media_size), media_sha256 = COALESCE(:media_sha256, media_sha256), categoria = :categoria, visibilidade = :visibilidade, is_premium_only = :premium_only, education_stage = :education_stage, knowledge_area = :knowledge_area, subject = :subject, topic = :topic, subtopic = :subtopic, course_title = :course_title, complementary_materials = :complementary_materials, related_exercises = :related_exercises, captions_url = :captions_url WHERE id = :id AND usuario_id = :owner_id AND status = "publicado"');
    $statement->execute([
        ':sala_id' => $data['sala_id'],
        ':tipo' => $data['tipo'],
        ':titulo' => $data['titulo'],
        ':resumo' => $data['resumo'],
        ':conteudo' => $data['conteudo'],
        ':arquivo_url' => $data['arquivo_url'],
        ':video_url' => $data['video_url'],
        ':media_storage_key' => $attachment['storage_key'],
        ':media_mime' => $attachment['file_mime'],
        ':media_size' => $attachment['file_size'],
        ':media_sha256' => $attachment['file_sha256'],
        ':categoria' => $data['categoria'],
        ':visibilidade' => $data['visibilidade'],
        ':premium_only' => $data['is_premium_only'],
        ':education_stage' => $data['education_stage'] ?: null,
        ':knowledge_area' => $data['knowledge_area'] ?: null,
        ':subject' => $data['subject'] ?: null,
        ':topic' => $data['topic'] ?: null,
        ':subtopic' => $data['subtopic'] ?: null,
        ':course_title' => $data['course_title'] ?: null,
        ':complementary_materials' => $data['complementary_materials'] ?: null,
        ':related_exercises' => $data['related_exercises'] ?: null,
        ':captions_url' => $data['captions_url'] ?: null,
        ':id' => $publicationId,
        ':owner_id' => (int) $publication['usuario_id'],
    ]);
    if ($attachment['storage_key'] !== null && !empty($publication['media_storage_key'])) {
        feed_remove_replaced_attachment($publication);
    }
    } catch (Throwable $error) {
        feed_discard_attachment($attachment);
        throw $error;
    }
}

function feed_archive_publication(PDO $pdo, int $publicationId, int $usuarioId, bool $adminOverride = false): void {
    $publicationQuery = $pdo->prepare(
        'SELECT usuario_id, sala_id FROM publicacoes
         WHERE id = :id AND status = "publicado" LIMIT 1'
    );
    $publicationQuery->execute(['id' => $publicationId]);
    $publication = $publicationQuery->fetch(PDO::FETCH_ASSOC);
    $isAuthor = is_array($publication) && (int) $publication['usuario_id'] === $usuarioId;
    $isAdmin = is_admin_user()
        || ($adminOverride && admin_user_can($pdo, $usuarioId, 'admin.feed.moderate'));
    $isRoomModerator = is_array($publication)
        && $publication['sala_id'] !== null
        && feed_user_can_moderate_room($pdo, (int) $publication['sala_id'], $usuarioId);
    if (!$isAuthor && !$isAdmin && !$isRoomModerator) {
        throw new DomainException('Publicação indisponível para remoção.');
    }
    $statement = $pdo->prepare('UPDATE publicacoes SET status = "arquivado" WHERE id = :id AND status = "publicado"');
    $statement->execute([':id' => $publicationId]);
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
    $statement = $pdo->prepare('SELECT c.usuario_id AS comment_owner, c.publicacao_id, p.usuario_id AS publication_owner, p.sala_id FROM publicacao_comentarios c INNER JOIN publicacoes p ON p.id = c.publicacao_id WHERE c.id = :id AND p.status = "publicado" LIMIT 1');
    $statement->execute([':id' => $commentId]);
    $comment = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$comment || !feed_get_publication($pdo, (int) $comment['publicacao_id'], $usuarioId, $usuarioPremium)) {
        throw new DomainException('Comentário não encontrado.');
    }
    $isAdmin = configured_admin_user_id() === $usuarioId;
    $isRoomModerator = $comment['sala_id'] !== null
        && feed_user_can_moderate_room($pdo, (int) $comment['sala_id'], $usuarioId);
    if (
        (int) $comment['comment_owner'] !== $usuarioId
        && (int) $comment['publication_owner'] !== $usuarioId
        && !$isRoomModerator
        && !$isAdmin
    ) {
        throw new DomainException('Permissão insuficiente para remover o comentário.');
    }
    $delete = $pdo->prepare('DELETE FROM publicacao_comentarios WHERE id = :id');
    $delete->execute([':id' => $commentId]);
}

function feed_report_content(
    PDO $pdo,
    int $contentId,
    int $usuarioId,
    bool $usuarioPremium,
    string $contentType,
    string $reason,
    string $details
): bool {
    if (!in_array($contentType, ['publication', 'comment'], true)) {
        throw new InvalidArgumentException('Tipo de conteúdo inválido para denúncia.');
    }
    if ($contentType === 'publication') {
        $publication = feed_get_publication($pdo, $contentId, $usuarioId, $usuarioPremium);
        if (!$publication || $publication['visibilidade'] !== 'sala' || $publication['sala_id'] === null) {
            throw new DomainException('Conteúdo indisponível para denúncia.');
        }
        return room_submit_report(
            $pdo,
            (int) $publication['sala_id'],
            $usuarioId,
            $reason,
            $details,
            'publication',
            $contentId
        );
    }

    $statement = $pdo->prepare(
        'SELECT c.publicacao_id FROM publicacao_comentarios c
         INNER JOIN publicacoes p ON p.id = c.publicacao_id
         WHERE c.id = :comment_id AND p.status = "publicado" LIMIT 1'
    );
    $statement->execute(['comment_id' => $contentId]);
    $publicationId = $statement->fetchColumn();
    $publication = $publicationId === false
        ? null
        : feed_get_publication($pdo, (int) $publicationId, $usuarioId, $usuarioPremium);
    if (!$publication || $publication['visibilidade'] !== 'sala' || $publication['sala_id'] === null) {
        throw new DomainException('Comentário indisponível para denúncia.');
    }
    return room_submit_report(
        $pdo,
        (int) $publication['sala_id'],
        $usuarioId,
        $reason,
        $details,
        'comment',
        $contentId
    );
}