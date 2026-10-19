<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'PRAGMA foreign_keys = ON',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT, perfil TEXT, username TEXT, perfil_imagem TEXT)',
    'CREATE TABLE salas (id INTEGER PRIMARY KEY, nome TEXT)',
    'CREATE TABLE salas_usuarios (id INTEGER PRIMARY KEY, sala_id INTEGER, usuario_id INTEGER, status TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER)',
    'CREATE TABLE publicacoes (id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER, tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, capa_url TEXT, arquivo_url TEXT, video_url TEXT, categoria TEXT, visibilidade TEXT, status TEXT, is_premium_only INTEGER, publicado_em TEXT DEFAULT CURRENT_TIMESTAMP, atualizado_em TEXT DEFAULT CURRENT_TIMESTAMP)',
    'CREATE TABLE publicacao_curtidas (publicacao_id INTEGER, usuario_id INTEGER, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (publicacao_id, usuario_id))',
    'CREATE TABLE publicacao_salvamentos (publicacao_id INTEGER, usuario_id INTEGER, criado_em TEXT DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (publicacao_id, usuario_id))',
    'CREATE TABLE publicacao_comentarios (id INTEGER PRIMARY KEY AUTOINCREMENT, publicacao_id INTEGER, usuario_id INTEGER, conteudo TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)',
] as $statement) {
    $pdo->exec($statement);
}

require_once __DIR__ . '/../includes/feed.php';

function assert_feed(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec("INSERT INTO usuarios VALUES (1, 'Autora', 'professor', 'autora', ''), (2, 'Estudante', 'estudante', 'estudante', ''), (3, 'Outro', 'estudante', 'outro', '')");
$pdo->exec("INSERT INTO salas VALUES (10, 'Sala de Biologia')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (1, 10, 2, 'ativo')");
$pdo->exec("INSERT INTO instituicoes VALUES (20, 1)");

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
    'video_url' => 'https://media.example.test/aula',
]);
$insertRestricted = $pdo->prepare('INSERT INTO publicacoes (usuario_id, instituicao_id, sala_id, tipo, titulo, conteudo, categoria, visibilidade, status, is_premium_only) VALUES (1, :institution_id, :room_id, :type, :title, :content, :category, :visibility, "publicado", :premium)');
$insertRestricted->execute([':institution_id' => null, ':room_id' => 10, ':type' => 'material', ':title' => 'Material da sala', ':content' => 'Para membros', ':category' => 'Biologia', ':visibility' => 'sala', ':premium' => 0]);
$roomPublicationId = (int) $pdo->lastInsertId();
$insertRestricted->execute([':institution_id' => 20, ':room_id' => null, ':type' => 'artigo', ':title' => 'Material institucional', ':content' => 'Para a responsável', ':category' => 'Biologia', ':visibility' => 'instituicao', ':premium' => 0]);
$institutionPublicationId = (int) $pdo->lastInsertId();
$insertRestricted->execute([':institution_id' => null, ':room_id' => null, ':type' => 'material', ':title' => 'Material Premium', ':content' => 'Plano ativo', ':category' => 'Biologia', ':visibility' => 'publica', ':premium' => 1]);
$premiumPublicationId = (int) $pdo->lastInsertId();

$lesson = $pdo->query('SELECT tipo, visibilidade, is_premium_only FROM publicacoes WHERE id = ' . $freeLessonId)->fetch();
assert_feed($lesson['tipo'] === 'video_aula' && $lesson['visibilidade'] === 'publica' && (int) $lesson['is_premium_only'] === 0, 'Videoaula criada no feed deve ser pública e gratuita.');
assert_feed(feed_get_publication($pdo, $privateId, 2, false) === null, 'Outro usuário não deve ler publicação privada.');
assert_feed(feed_get_publication($pdo, $privateId, 1, false) !== null, 'Autora deve ler a própria publicação privada.');
assert_feed(feed_get_publication($pdo, $roomPublicationId, 2, false) !== null, 'Membro ativo deve ler publicação da sala.');
assert_feed(feed_get_publication($pdo, $roomPublicationId, 3, false) === null, 'Usuário sem membership não deve ler publicação da sala.');
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 1, false) !== null, 'Responsável deve ler publicação institucional.');
assert_feed(feed_get_publication($pdo, $institutionPublicationId, 2, false) === null, 'Usuário alheio não deve ler publicação institucional.');
assert_feed(feed_get_publication($pdo, $premiumPublicationId, 2, false) === null, 'Conta sem entitlement não deve ler conteúdo Premium.');
assert_feed(feed_get_publication($pdo, $premiumPublicationId, 2, true) !== null, 'Entitlement Premium deve permitir conteúdo permitido pelo plano.');
assert_feed(feed_get_publication($pdo, $freeLessonId, 2, true)['is_premium_only'] == 0, 'Aula do Feed nunca deve ser Premium.');

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
assert_feed(!in_array($premiumPublicationId, $publicIds, true), 'Conta sem entitlement não deve ver publicação Premium de outra autora.');
$roomFilter = feed_list_publications($pdo, 2, false, '', '', 'sala');
assert_feed(count($roomFilter) === 1 && (int) $roomFilter[0]['id'] === $roomPublicationId, 'Filtro de sala deve usar membership ativo.');
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
    'kind' => 'question', 'title' => 'Questão revisada', 'content' => 'Contexto da questão',
    'category' => 'Ecologia', 'visibility' => 'publica',
];
feed_update_publication($pdo, $publicId, 1, false, $updatedInput);
assert_feed(feed_get_publication($pdo, $publicId, 2, false)['titulo'] === 'Questão revisada', 'Autora deve editar sua publicação.');
$premiumEdit = [
    'kind' => 'material', 'title' => 'Material Premium revisado', 'content' => 'Conteúdo atualizado',
    'category' => 'Biologia', 'visibility' => 'publica',
];
feed_update_publication($pdo, $premiumPublicationId, 1, false, $premiumEdit);
$premiumFlag = $pdo->prepare('SELECT is_premium_only FROM publicacoes WHERE id = :id');
$premiumFlag->execute([':id' => $premiumPublicationId]);
assert_feed((int) $premiumFlag->fetchColumn() === 1, 'Editar conteúdo Premium legado não deve remover seu requisito de acesso.');
try {
    feed_update_publication($pdo, $publicId, 3, false, $updatedInput);
    throw new RuntimeException('Usuário alheio não deve editar publicação.');
} catch (DomainException) {
}

feed_archive_publication($pdo, $publicId, 1);
assert_feed(feed_get_publication($pdo, $publicId, 2, false) === null, 'Publicação arquivada deve sair do Feed sem apagar registro.');

echo "OK: publicação, edição, arquivamento, gratuidade, curtida, salvamento, comentários e autorização do Feed validados.\n";