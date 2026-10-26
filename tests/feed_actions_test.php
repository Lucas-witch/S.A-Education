<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'PRAGMA foreign_keys = ON',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT, perfil TEXT, username TEXT, perfil_imagem TEXT, professor_status TEXT DEFAULT "not_applicable")',
    'CREATE TABLE salas (
        id INTEGER PRIMARY KEY, nome TEXT, criador_id INTEGER, institution_id INTEGER,
        privacidade TEXT DEFAULT "publica"
    )',
    'CREATE TABLE salas_usuarios (id INTEGER PRIMARY KEY, sala_id INTEGER, usuario_id INTEGER, papel TEXT, status TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, validation_status TEXT DEFAULT "validated", tipo TEXT DEFAULT "fundamental", natureza TEXT DEFAULT "publica")',
    'CREATE TABLE institution_members (id INTEGER PRIMARY KEY, institution_id INTEGER, user_id INTEGER, member_type TEXT, status TEXT)',
    'CREATE TABLE institution_class_subjects (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, teacher_user_id INTEGER)',
    'CREATE TABLE institution_enrollments (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, student_user_id INTEGER, status TEXT)',
    'CREATE TABLE institution_learning_resources (id INTEGER PRIMARY KEY, institution_id INTEGER, class_subject_id INTEGER, resource_type TEXT, resource_id INTEGER)',
    'CREATE TABLE product_plans (code TEXT PRIMARY KEY, account_type TEXT, institution_type TEXT, is_enabled INTEGER DEFAULT 1)',
    'CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, institution_id INTEGER, plan_code TEXT, status TEXT, payment_confirmed_at TEXT, starts_at TEXT, expires_at TEXT)',
    'CREATE TABLE institution_portal_suspensions (institution_id INTEGER PRIMARY KEY, suspended_by INTEGER, reason TEXT)',
    'CREATE TABLE publicacoes (id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER, tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, capa_url TEXT, arquivo_url TEXT, video_url TEXT, media_storage_key TEXT, media_mime TEXT, media_size INTEGER, media_sha256 TEXT, categoria TEXT, education_stage TEXT, knowledge_area TEXT, subject TEXT, topic TEXT, subtopic TEXT, course_title TEXT, complementary_materials TEXT, related_exercises TEXT, captions_url TEXT, visibilidade TEXT, status TEXT, is_premium_only INTEGER, question_bank_id INTEGER, is_official INTEGER NOT NULL DEFAULT 0, publicado_em TEXT DEFAULT CURRENT_TIMESTAMP, atualizado_em TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE publicacao_curtidas (publicacao_id INTEGER, usuario_id INTEGER, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (publicacao_id, usuario_id))',
    'CREATE TABLE publicacao_salvamentos (publicacao_id INTEGER, usuario_id INTEGER, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (publicacao_id, usuario_id))',
    'CREATE TABLE publicacao_comentarios (id INTEGER PRIMARY KEY AUTOINCREMENT, publicacao_id INTEGER, usuario_id INTEGER, conteudo TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE official_content_history (id INTEGER PRIMARY KEY AUTOINCREMENT, publication_id INTEGER, actor_user_id INTEGER, action TEXT, snapshot TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE official_content_editors (user_id INTEGER PRIMARY KEY, granted_by INTEGER, status TEXT, revoked_by INTEGER, granted_at TEXT, revoked_at TEXT)',
    'CREATE TABLE admin_audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, action TEXT, target_type TEXT, target_id INTEGER, details TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE room_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER, reporter_user_id INTEGER,
        target_type TEXT DEFAULT "room", target_id INTEGER DEFAULT 0, reason TEXT, details TEXT,
        status TEXT DEFAULT "pending", reviewed_by INTEGER,
        UNIQUE (room_id, reporter_user_id, target_type, target_id, status)
    )',
] as $statement) {
    $pdo->exec($statement);
}

require_once __DIR__ . '/../includes/admin_console.php';

function assert_feed(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec("INSERT INTO usuarios (id, nome_completo, perfil, username, perfil_imagem, professor_status) VALUES
    (1, 'Autora', 'professor', 'autora', '', 'approved'),
    (2, 'Estudante', 'estudante', 'estudante', '', 'not_applicable'),
    (3, 'Outro', 'estudante', 'outro', '', 'not_applicable'),
    (4, 'Admin oficial', 'estudante', 'admin-oficial', '', 'not_applicable'),
    (5, 'Docente oficial', 'professor', 'docente-oficial', '', 'approved')");
$pdo->exec("INSERT INTO salas (id, nome, criador_id, institution_id, privacidade) VALUES (10, 'Sala de Biologia', 1, NULL, 'publica')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (1, 10, 2, 'aluno', 'ativo')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (2, 10, 1, 'professor', 'ativo')");
$pdo->exec("INSERT INTO instituicoes VALUES (20, 1, 'validated', 'fundamental', 'publica')");
$pdo->exec("INSERT INTO institution_members VALUES (1, 20, 2, 'student', 'active')");
$pdo->exec("INSERT INTO product_plans VALUES ('institution-test', 'institution', 'public_school', 1)");
$pdo->exec("INSERT INTO subscriptions VALUES (1, 20, 'institution-test', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')");

$formattedPost = feed_normalize_publication_input([
    'kind' => 'post',
    'title' => 'Artigo formatado',
    'content' => '<div style="text-align:center"><strong>Texto</strong><script>alert(1)</script></div>',
    'visibility' => 'publica',
], false);
assert_feed(
    str_contains($formattedPost['conteudo'], '<strong>Texto</strong>')
        && str_contains($formattedPost['conteudo'], 'text-align:center')
        && !str_contains($formattedPost['conteudo'], '<script'),
    'Texto rico do Feed deve manter formatação segura e remover scripts.'
);
$uploadedVideo = feed_normalize_publication_input([
    'kind' => 'free_lesson',
    'title' => 'Aula em vídeo enviado',
    'content' => 'Aula didática.',
    'visibility' => 'publica',
], false, true);
assert_feed(
    $uploadedVideo['tipo'] === 'video_aula' && $uploadedVideo['video_url'] === null,
    'Aula publicada com vídeo anexado pode dispensar uma URL externa.'
);

$publicId = feed_create_publication($pdo, 1, [
    'kind' => 'material', 'title' => 'Material público', 'summary' => 'Resumo', 'content' => 'Conteúdo',
    'category' => 'Biologia', 'visibility' => 'publica', 'resource_url' => 'materials/aula.pdf',
]);
$privateId = feed_create_publication($pdo, 1, [
    'kind' => 'material', 'title' => 'Material privado', 'content' => 'Privado',
    'category' => 'Biologia', 'visibility' => 'privada',
]);
$freeLessonId = feed_create_publication($pdo, 1, [
    'kind' => 'free_lesson', 'title' => 'Aula aberta', 'summary' => 'Resumo',
    'content' => 'Aula', 'category' => 'Biologia', 'visibility' => 'privada',
    'video_url' => 'https://media.example.test/aula.mp4',
    'education_stage' => 'Ensino Superior', 'knowledge_area' => 'Ciências da Vida',
    'subject' => 'Biologia', 'topic' => 'Ecologia', 'subtopic' => 'Cadeias alimentares',
    'course_title' => 'Ecologia para graduação',
    'complementary_materials' => 'https://media.example.test/guia.pdf',
    'related_exercises' => 'https://media.example.test/exercicios',
    'captions_url' => 'https://media.example.test/aula.vtt',
]);
$insertRestricted = $pdo->prepare('INSERT INTO publicacoes (usuario_id, instituicao_id, sala_id, tipo, titulo, conteudo, categoria, visibilidade, status, is_premium_only) VALUES (1, :institution_id, :room_id, :type, :title, :content, :category, :visibility, "publicado", :premium)');
$insertRestricted->execute([':institution_id' => null, ':room_id' => 10, ':type' => 'material', ':title' => 'Material da sala', ':content' => 'Para membros', ':category' => 'Biologia', ':visibility' => 'sala', ':premium' => 0]);
$roomPublicationId = (int) $pdo->lastInsertId();
$insertRestricted->execute([':institution_id' => 20, ':room_id' => null, ':type' => 'artigo', ':title' => 'Material institucional', ':content' => 'Para a responsável', ':category' => 'Biologia', ':visibility' => 'instituicao', ':premium' => 0]);
$institutionPublicationId = (int) $pdo->lastInsertId();
$insertRestricted->execute([':institution_id' => null, ':room_id' => null, ':type' => 'material', ':title' => 'Material Premium', ':content' => 'Plano ativo', ':category' => 'Biologia', ':visibility' => 'publica', ':premium' => 1]);
$premiumPublicationId = (int) $pdo->lastInsertId();
$insertLegacyVideo = $pdo->prepare(
    'INSERT INTO publicacoes (usuario_id, tipo, titulo, conteudo, categoria, visibilidade, status, is_premium_only, video_url)
     VALUES (1, "video_aula", :title, "Aula legada", "Curso", :visibility, "publicado", :premium, :video_url)'
);
$insertLegacyVideo->execute([
    ':title' => 'Videoaula Premium legada',
    ':visibility' => 'publica',
    ':premium' => 1,
    ':video_url' => 'https://media.example.test/premium.mp4',
]);
$premiumVideoId = (int) $pdo->lastInsertId();
$insertLegacyVideo->execute([
    ':title' => 'Videoaula privada legada',
    ':visibility' => 'privada',
    ':premium' => 0,
    ':video_url' => 'https://media.example.test/private.mp4',
]);
$privateVideoId = (int) $pdo->lastInsertId();

$lesson = $pdo->query('SELECT tipo, visibilidade, is_premium_only FROM publicacoes WHERE id = ' . $freeLessonId)->fetch();
assert_feed($lesson['tipo'] === 'video_aula' && $lesson['visibilidade'] === 'publica' && (int) $lesson['is_premium_only'] === 0, 'Videoaula criada no feed deve ser pública e gratuita.');
$lessonMetadata = $pdo->query('SELECT education_stage, knowledge_area, subject, topic, subtopic, course_title, complementary_materials, related_exercises, captions_url FROM publicacoes WHERE id = ' . $freeLessonId)->fetch();
assert_feed($lessonMetadata['education_stage'] === 'Ensino Superior' && $lessonMetadata['course_title'] === 'Ecologia para graduação', 'Videoaula deve persistir a organização acadêmica e o curso.');
assert_feed($lessonMetadata['captions_url'] === 'https://media.example.test/aula.vtt', 'Videoaula deve persistir URL de legendas.');
assert_feed(feed_get_publication($pdo, $privateId, 2, false) === null, 'Outro usuário não deve ler publicação privada.');
assert_feed(feed_get_publication($pdo, $privateId, 1, false) !== null, 'Autora deve ler a própria publicação privada.');
assert_feed(feed_get_publication($pdo, $roomPublicationId, 2, false) !== null, 'Membro ativo deve ler publicação da sala.');
assert_feed(feed_get_publication($pdo, $roomPublicationId, 3, false) === null, 'Usuário sem membership não deve ler publicação da sala.');
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 1, false) !== null, 'Responsável deve ler publicação institucional.');
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 2, false) !== null, 'Membro ativo deve ler publicação da própria instituição.');
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 3, false) === null, 'Usuário alheio não deve ler publicação institucional.');
$pdo->exec("INSERT INTO institution_portal_suspensions VALUES (20, 1, 'Revisão administrativa')");
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 2, false) === null, 'Suspensão institucional também bloqueia acesso direto a publicações.');
$pdo->exec('DELETE FROM institution_portal_suspensions WHERE institution_id = 20');

$roomPostId = feed_create_publication($pdo, 2, [
    'kind' => 'post', 'title' => 'Discussão da sala', 'content' => 'Vamos conversar sobre ecologia.',
    'category' => 'Biologia', 'visibility' => 'sala', 'room_id' => '10',
]);
assert_feed(feed_get_publication($pdo, $roomPostId, 2, false) !== null, 'Membro pode ler uma discussão publicada na sala.');
assert_feed(feed_get_publication($pdo, $roomPostId, 3, false) === null, 'Não membro não pode ler publicação de sala.');
assert_feed(count(feed_list_publications($pdo, 2, false, '', '', '', 40, 10)) === 2, 'Feed por sala deve listar somente publicações vinculadas à sala.');
try {
    feed_create_publication($pdo, 3, [
        'kind' => 'post', 'title' => 'Post não autorizado', 'content' => 'Sem membership.',
        'visibility' => 'sala', 'room_id' => '10',
    ]);
    throw new RuntimeException('Não membro não deve publicar em uma sala.');
} catch (PermissionDeniedException) {
}
assert_feed(feed_get_publication($pdo, $premiumPublicationId, 2, false) === null, 'Conta sem entitlement não deve ler conteúdo Premium.');
assert_feed(feed_get_publication($pdo, $premiumPublicationId, 2, true) !== null, 'Entitlement Premium deve permitir conteúdo permitido pelo plano.');
assert_feed(feed_get_publication($pdo, $premiumVideoId, 2, false) === null, 'Conta sem entitlement não deve ler videoaula Premium legada.');
assert_feed(feed_get_publication($pdo, $premiumVideoId, 2, true) !== null, 'Entitlement Premium deve manter leitura de videoaula Premium legada.');
assert_feed(feed_get_publication($pdo, $privateVideoId, 2, false) === null, 'Conta alheia não deve ler videoaula privada legada.');
assert_feed(feed_get_publication($pdo, $privateVideoId, 1, false) !== null, 'Autora deve continuar lendo sua videoaula privada legada.');
assert_feed(feed_get_publication($pdo, $freeLessonId, 2, true)['is_premium_only'] == 0, 'Aula do Feed nunca deve ser Premium.');
assert_feed(
    feed_normalize_publication_input(['kind' => 'question', 'title' => 'Dúvida', 'content' => 'Pergunta', 'visibility' => 'publica'])['tipo'] === 'post',
    'Dúvidas comuns devem continuar como publicações gratuitas do Feed.'
);
assert_feed(
    feed_normalize_publication_input(['kind' => 'bank_question', 'title' => 'Questão', 'content' => 'Enunciado', 'visibility' => 'publica'])['is_bank_question'],
    'Questões destinadas ao banco devem ser identificáveis separadamente de dúvidas no Feed.'
);
$publicFeed = feed_list_publications($pdo, 2, false, '', '', '', 20);
$draftId = feed_create_publication($pdo, 2, [
    'kind' => 'post', 'title' => '', 'content' => '', 'visibility' => 'publica',
], 'rascunho');
assert_feed(count(feed_list_drafts($pdo, 2)) === 1, 'Usuário deve conseguir salvar e consultar rascunhos próprios.');
assert_feed(feed_list_publications($pdo, 2, false, '', '', '') === $publicFeed, 'Rascunhos não devem aparecer no Feed público.');
feed_update_draft($pdo, $draftId, 2, [
    'kind' => 'post', 'title' => 'Rascunho completo', 'content' => 'Texto pronto', 'visibility' => 'publica',
]);
feed_publish_draft($pdo, $draftId, 2, [
    'kind' => 'post', 'title' => 'Publicação recuperada', 'content' => 'Texto publicado', 'visibility' => 'publica',
]);
assert_feed(feed_list_drafts($pdo, 2) === [], 'Publicar um rascunho deve removê-lo da lista de rascunhos.');

$publicFeed = feed_list_publications($pdo, 2, false, '', '', '', 20);
$publicIds = array_map(static fn (array $item): int => (int) $item['id'], $publicFeed);
assert_feed(in_array($publicId, $publicIds, true) && in_array($freeLessonId, $publicIds, true), 'Feed deve retornar publicações públicas autorizadas.');
assert_feed(!in_array($privateId, $publicIds, true), 'Feed não deve retornar publicação privada de outra conta.');
assert_feed(feed_list_publications($pdo, 2, false, 'sem correspondência', '', '') === [], 'Busca sem correspondência deve retornar estado vazio real.');
$lessonFilter = feed_list_publications($pdo, 2, false, '', 'video_aula', '');
assert_feed(count($lessonFilter) === 1 && (int) $lessonFilter[0]['id'] === $freeLessonId, 'Filtro de tipo deve restringir resultados reais.');
$authorPremiumFeed = feed_list_publications($pdo, 1, false, '', '', '');
$authorPremiumIds = array_map(static fn (array $item): int => (int) $item['id'], $authorPremiumFeed);
assert_feed(in_array($premiumPublicationId, $authorPremiumIds, true), 'Autora deve ver sua publicação Premium para poder gerenciá-la.');
assert_feed(in_array($premiumVideoId, $authorPremiumIds, true), 'Autora deve continuar vendo sua videoaula Premium legada.');
assert_feed(!in_array($premiumPublicationId, $publicIds, true), 'Conta sem entitlement não deve ver publicação Premium de outra autora.');
$roomFilter = feed_list_publications($pdo, 2, false, '', '', 'sala');
assert_feed(
    count($roomFilter) === 2
    && in_array($roomPublicationId, array_map('intval', array_column($roomFilter, 'id')), true)
    && in_array($roomPostId, array_map('intval', array_column($roomFilter, 'id')), true),
    'Filtro de sala deve usar membership ativo.'
);
$outsiderRoomFilter = feed_list_publications($pdo, 3, false, '', '', 'sala');
assert_feed($outsiderRoomFilter === [], 'Filtro de sala não deve expor publicação a não membro.');
$institutionFilter = feed_list_publications($pdo, 1, false, '', '', 'instituicao');
assert_feed(count($institutionFilter) === 1 && (int) $institutionFilter[0]['id'] === $institutionPublicationId, 'Filtro institucional deve usar o vínculo de proprietário.');

$like = feed_toggle_interaction($pdo, $publicId, 2, false, 'like');
assert_feed($like === ['active' => true, 'count' => 1], 'Curtida deve persistir e atualizar contagem real.');
$unlike = feed_toggle_interaction($pdo, $publicId, 2, false, 'like');
assert_feed($unlike === ['active' => false, 'count' => 0], 'Segunda curtida deve remover a relação.');
$saved = feed_toggle_interaction($pdo, $publicId, 2, false, 'save');
assert_feed($saved === ['active' => true, 'count' => 1], 'Salvamento deve persistir.');

$comment = feed_add_comment($pdo, $publicId, 2, false, 'Comentário real');
assert_feed($comment['conteudo'] === 'Comentário real' && (int) $comment['usuario_id'] === 2, 'Comentário deve persistir com o autor da sessão.');
assert_feed(count(feed_list_comments($pdo, $publicId, 1, false)) === 1, 'Autor da publicação deve ler comentários públicos.');
try {
    feed_delete_comment($pdo, (int) $comment['id'], 3, false);
    throw new RuntimeException('Usuário alheio não deve excluir comentário.');
} catch (DomainException) {
}
feed_delete_comment($pdo, (int) $comment['id'], 1, false);
assert_feed(feed_list_comments($pdo, $publicId, 2, false) === [], 'Autor da publicação pode moderar comentário.');

try {
    feed_toggle_interaction($pdo, $privateId, 2, false, 'like');
    throw new RuntimeException('Usuário sem acesso não deve curtir publicação privada alheia.');
} catch (DomainException) {
}

$updatedInput = [
    'kind' => 'material', 'title' => 'Material revisado', 'content' => 'Conteúdo revisado',
    'category' => 'Ecologia', 'visibility' => 'publica',
];
feed_update_publication($pdo, $publicId, 1, false, $updatedInput);
assert_feed(feed_get_publication($pdo, $publicId, 2, false)['titulo'] === 'Material revisado', 'Autora deve editar sua publicação.');
try {
    feed_update_publication($pdo, $publicId, 1, false, [
        'kind' => 'bank_question', 'title' => 'Mudança de categoria', 'content' => 'Não deve ser aceita',
        'category' => 'Ecologia', 'visibility' => 'publica',
    ]);
    throw new RuntimeException('Editar publicação não deve permitir alteração de categoria e desvio de cotas.');
} catch (DomainException) {
}
$premiumEdit = [
    'kind' => 'material', 'title' => 'Material Premium revisado', 'content' => 'Conteúdo atualizado',
    'category' => 'Biologia', 'visibility' => 'publica',
];
feed_update_publication($pdo, $premiumPublicationId, 1, false, $premiumEdit);
$premiumFlag = $pdo->prepare('SELECT is_premium_only FROM publicacoes WHERE id = :id');
$premiumFlag->execute([':id' => $premiumPublicationId]);
assert_feed((int) $premiumFlag->fetchColumn() === 1, 'Editar conteúdo Premium legado não deve remover seu requisito de acesso.');
$legacyVideoEdit = [
    'kind' => 'free_lesson', 'title' => 'Videoaula revisada', 'content' => 'Notas atualizadas',
    'video_url' => 'https://media.example.test/revised.mp4', 'visibility' => 'publica',
];
feed_update_publication($pdo, $premiumVideoId, 1, false, $legacyVideoEdit);
$premiumFlag->execute([':id' => $premiumVideoId]);
assert_feed((int) $premiumFlag->fetchColumn() === 1, 'Editar videoaula Premium legada não deve remover seu requisito de acesso.');
feed_update_publication($pdo, $privateVideoId, 1, false, $legacyVideoEdit);
$videoVisibility = $pdo->prepare('SELECT visibilidade FROM publicacoes WHERE id = :id');
$videoVisibility->execute([':id' => $privateVideoId]);
assert_feed($videoVisibility->fetchColumn() === 'privada', 'Editar videoaula privada legada não deve publicá-la sem consentimento.');
assert_feed(feed_get_publication($pdo, $privateVideoId, 2, false) === null, 'A edição não deve expor a videoaula privada a outras contas.');
try {
    feed_update_publication($pdo, $publicId, 3, false, $updatedInput);
    throw new RuntimeException('Usuário alheio não deve editar publicação.');
} catch (DomainException) {
}

feed_archive_publication($pdo, $publicId, 1);
assert_feed(feed_get_publication($pdo, $publicId, 2, false) === null, 'Publicação arquivada deve sair do Feed sem apagar registro.');

assert_feed(room_user_can_manage($pdo, ['id' => 10, 'criador_id' => 1, 'privacidade' => 'publica'], 1), 'Criador da sala deve poder moderar participantes.');
assert_feed(!room_user_can_manage($pdo, ['id' => 10, 'criador_id' => 1, 'privacidade' => 'publica'], 2), 'Membro comum não deve moderar participantes.');
$pdo->exec("UPDATE salas_usuarios SET papel = 'moderador' WHERE sala_id = 10 AND usuario_id = 2");
assert_feed(room_user_can_manage($pdo, ['id' => 10, 'criador_id' => 1, 'privacidade' => 'publica'], 2), 'Moderador ativo deve poder executar ações de moderação.');
$roomComment = feed_add_comment($pdo, $roomPostId, 1, false, 'Comentário moderado');
feed_delete_comment($pdo, (int) $roomComment['id'], 2, false);
assert_feed(count(feed_list_comments($pdo, $roomPostId, 1, false)) === 0, 'Moderador da sala pode remover comentários inadequados.');
$pdo->exec("INSERT INTO salas (id, nome, criador_id, institution_id, privacidade) VALUES (11, 'Comunidade institucional', 1, 20, 'instituicao')");
$pdo->exec("INSERT INTO salas_usuarios (id, sala_id, usuario_id, papel, status) VALUES (3, 11, 2, 'aluno', 'ativo')");
assert_feed(room_user_can_access($pdo, ['id' => 11, 'institution_id' => 20, 'privacidade' => 'instituicao'], 2), 'Membro ativo pode acessar a comunidade da própria instituição.');
assert_feed(!room_user_can_access($pdo, ['id' => 11, 'institution_id' => 20, 'privacidade' => 'instituicao'], 3), 'Usuário de outra instituição não pode acessar comunidade privada.');
$pdo->exec('DELETE FROM institution_members WHERE institution_id = 20 AND user_id = 2');
assert_feed(!room_user_can_access($pdo, ['id' => 11, 'institution_id' => 20, 'privacidade' => 'instituicao'], 2), 'Revogar vínculo institucional também revoga o acesso à comunidade.');

assert_feed(feed_report_content($pdo, $roomPostId, 2, false, 'publication', 'conteúdo inadequado', 'Conteúdo impróprio.'), 'Participante pode denunciar publicação da sala.');
$reportComment = feed_add_comment($pdo, $roomPostId, 1, false, 'Comentário a denunciar');
assert_feed(feed_report_content($pdo, (int) $reportComment['id'], 2, false, 'comment', 'assédio', 'Comentário inadequado.'), 'Participante pode denunciar comentário da sala.');
try {
    feed_report_content($pdo, $publicId, 2, false, 'publication', 'spam', 'Conteúdo público');
    throw new RuntimeException('Denúncia de conteúdo sem sala não deve ser gravada neste fluxo.');
} catch (DomainException) {
}

putenv('APP_ADMIN_USER_ID=4');
$_SESSION['usuario_id'] = 2;
try {
    admin_create_official_content($pdo, [
        'kind' => 'post', 'title' => 'Falso conteúdo oficial', 'content' => 'Não autorizado',
    ]);
    throw new RuntimeException('Conta comum não deve poder marcar conteúdo como oficial.');
} catch (DomainException) {
}
$_SESSION['usuario_id'] = 4;
$officialId = admin_create_official_content($pdo, [
    'kind' => 'free_lesson',
    'title' => 'Guia oficial de estudos',
    'content' => 'Conteúdo institucional produzido pela conta administrativa.',
    'category' => 'Orientação',
    'video_url' => 'https://media.example.test/guia-oficial.mp4',
]);
$officialRecord = $pdo->query('SELECT usuario_id, is_official FROM publicacoes WHERE id = ' . $officialId)->fetch();
assert_feed(
    (int) $officialRecord['usuario_id'] === 4 && (int) $officialRecord['is_official'] === 1,
    'Conteúdo oficial identifica a publicação sem atribuir autoria a terceiros.'
);
admin_update_official_content($pdo, $officialId, [
    'kind' => 'free_lesson',
    'title' => 'Guia oficial revisado',
    'content' => 'Versão revisada do material institucional.',
    'category' => 'Orientação',
    'video_url' => 'https://media.example.test/guia-oficial.mp4',
]);
admin_archive_official_content($pdo, $officialId);
assert_feed(
    (int) $pdo->query('SELECT COUNT(*) FROM official_content_history WHERE publication_id = ' . $officialId)->fetchColumn() === 3
    && $pdo->query('SELECT status FROM publicacoes WHERE id = ' . $officialId)->fetchColumn() === 'arquivado',
    'Criação, edição e arquivamento oficial devem manter snapshots do histórico.'
);
admin_set_official_content_editor($pdo, 5, true);
$_SESSION['usuario_id'] = 5;
assert_feed(admin_user_can_manage_official_content($pdo, 5), 'Professor aprovado com permissão explícita pode produzir conteúdo oficial.');
$teacherOfficialId = admin_create_official_content($pdo, [
    'kind' => 'post',
    'title' => 'Orientação docente oficial',
    'content' => 'Material oficial preparado por docente autorizado.',
]);
$_SESSION['usuario_id'] = 4;
admin_update_official_content($pdo, $teacherOfficialId, [
    'kind' => 'post',
    'title' => 'Orientação docente oficial revisada',
    'content' => 'Revisão pela administração, preservando o autor.',
]);
$officialAuthor = $pdo->query('SELECT usuario_id FROM publicacoes WHERE id = ' . $teacherOfficialId)->fetchColumn();
assert_feed((int) $officialAuthor === 5, 'Edição administrativa mantém a autoria real do professor produtor.');
admin_set_official_content_editor($pdo, 5, false);
assert_feed(!admin_user_can_manage_official_content($pdo, 5), 'Revogação remove imediatamente permissão de produção oficial.');

$searchableLessonId = feed_create_publication($pdo, 1, [
    'kind' => 'free_lesson',
    'title' => 'Aula de biomas',
    'content' => 'Estudo com #biomasbrasileiros',
    'visibility' => 'publica',
    'video_url' => 'https://media.example.test/biomas.mp4',
    'subject' => 'Ciências da Natureza',
    'topic' => 'Ecologia tropical',
    'subtopic' => 'Biomas brasileiros',
    'course_title' => 'Ecologia de campo',
]);
foreach ([
    '#biomasbrasileiros',
    'Ciências da Natureza',
    'Ecologia tropical',
    'Biomas brasileiros',
    'autora',
] as $term) {
    $searchResults = feed_list_publications($pdo, 2, false, $term, '', '');
    assert_feed(
        in_array($searchableLessonId, array_map('intval', array_column($searchResults, 'id')), true),
        'A busca do Feed deve localizar hashtags, disciplina, tema, assunto e username.'
    );
}

for ($index = 1; $index <= 42; $index++) {
    feed_create_publication($pdo, 1, [
        'kind' => 'post',
        'title' => 'Publicação paginada ' . $index,
        'content' => 'Conteúdo de paginação ' . $index,
        'visibility' => 'publica',
    ]);
}
$pageOneHasMore = false;
$pageTwoHasMore = false;
$pageThreeHasMore = false;
$feedPageOne = feed_list_publications($pdo, 2, false, 'Publicação paginada', '', '', 20, null, 1, $pageOneHasMore);
$feedPageTwo = feed_list_publications($pdo, 2, false, 'Publicação paginada', '', '', 20, null, 2, $pageTwoHasMore);
$feedPageThree = feed_list_publications($pdo, 2, false, 'Publicação paginada', '', '', 20, null, 3, $pageThreeHasMore);
assert_feed(
    count($feedPageOne) === 20 && $pageOneHasMore
        && count($feedPageTwo) === 20 && $pageTwoHasMore
        && count($feedPageThree) === 2 && !$pageThreeHasMore,
    'A paginação deve usar páginas estáveis e indicar corretamente quando há resultados seguintes.'
);
putenv('APP_ADMIN_USER_ID');

echo "OK: publicação, edição, arquivamento, busca, paginação, gratuidade, interações e autorização do Feed validados.\n";