<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'CREATE TABLE salas (id INTEGER PRIMARY KEY, nome TEXT, criador_id INTEGER, institution_id INTEGER, prof TEXT, materia TEXT, categoria TEXT, area_conhecimento TEXT, descricao TEXT, privacidade TEXT, criado_em TEXT, alunos INTEGER DEFAULT 0, icone TEXT)',
    'CREATE TABLE salas_usuarios (id INTEGER PRIMARY KEY, sala_id INTEGER, usuario_id INTEGER, papel TEXT, status TEXT)',
    'CREATE TABLE publicacoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER, tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, categoria TEXT, visibilidade TEXT, status TEXT, is_premium_only INTEGER, capa_url TEXT, arquivo_url TEXT, video_url TEXT)',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, validation_status TEXT DEFAULT "validated", tipo TEXT DEFAULT "fundamental", natureza TEXT DEFAULT "publica")',
    'CREATE TABLE institution_members (id INTEGER PRIMARY KEY, institution_id INTEGER, user_id INTEGER, member_type TEXT, status TEXT)',
    'CREATE TABLE institution_class_subjects (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, teacher_user_id INTEGER)',
    'CREATE TABLE institution_enrollments (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, student_user_id INTEGER, status TEXT)',
    'CREATE TABLE institution_learning_resources (id INTEGER PRIMARY KEY, institution_id INTEGER, class_subject_id INTEGER, resource_type TEXT, resource_id INTEGER)',
    'CREATE TABLE product_plans (code TEXT PRIMARY KEY, account_type TEXT, institution_type TEXT, is_enabled INTEGER DEFAULT 1)',
    'CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, institution_id INTEGER, plan_code TEXT, status TEXT, payment_confirmed_at TEXT, starts_at TEXT, expires_at TEXT)',
    'CREATE TABLE institution_portal_suspensions (institution_id INTEGER PRIMARY KEY, suspended_by INTEGER, reason TEXT)',
    'CREATE TABLE biblioteca (id INTEGER PRIMARY KEY, titulo TEXT, autor TEXT, tipo TEXT, materia TEXT, descricao TEXT, capa_url TEXT, arquivo_url TEXT, disponibilidade TEXT, status TEXT, instituicao_id INTEGER)',
] as $statement) {
    $pdo->exec($statement);
}

require_once __DIR__ . '/../includes/search.php';
require_once __DIR__ . '/../includes/room_search.php';

function assert_catalog(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function catalog_ids(array $result): array {
    return array_map(static fn (array $item): string => $item['source'] . ':' . $item['id'], $result['items']);
}

$pdo->exec("INSERT INTO usuarios VALUES (1, 'Dona da instituição'), (2, 'Membro de sala'), (3, 'Sem vínculo')");
$pdo->exec("INSERT INTO salas (id, nome, prof, materia, descricao, privacidade, criado_em, alunos, icone) VALUES (10, 'Sala pública', 'Prof A', 'Biologia', 'Ecologia', 'publica', '2026-01-01', 9, ''), (11, 'Grupo de Biologia', 'Prof B', 'Biologia', 'Genética', 'privada', '2026-01-02', 9, '')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (1, 11, 2, 'aluno', 'ativo')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (2, 10, 2, 'aluno', 'ativo')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (3, 10, 3, 'aluno', 'removido')");
$pdo->exec("INSERT INTO salas (id, nome, criador_id, institution_id, prof, materia, categoria, area_conhecimento, descricao, privacidade, criado_em, alunos, icone) VALUES (12, 'Comunidade da instituição', 1, 20, 'Instituição', 'Direito', 'Curso', 'Ciências Humanas', 'Direito acadêmico', 'instituicao', '2026-01-03', 1, '')");
$pdo->exec("INSERT INTO salas_usuarios VALUES (4, 12, 2, 'aluno', 'ativo')");
$pdo->exec("INSERT INTO instituicoes VALUES (20, 1, 'validated', 'fundamental', 'publica')");
$pdo->exec("INSERT INTO instituicoes VALUES (21, 9, 'validated', 'fundamental', 'publica')");
$pdo->exec("INSERT INTO product_plans VALUES ('institution-test', 'institution', 'public_school', 1)");
$pdo->exec("INSERT INTO subscriptions VALUES (1, 20, 'institution-test', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')");
$pdo->exec("INSERT INTO subscriptions VALUES (2, 21, 'institution-test', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')");
$pdo->exec("INSERT INTO institution_members VALUES (1, 20, 2, 'student', 'active')");
$pdo->exec("INSERT INTO publicacoes VALUES (100, 1, NULL, NULL, 'artigo', 'Ecologia aplicada', 'Resumo público', 'Conteúdo público', 'Biologia', 'publica', 'publicado', 0, '', 'materials/ecology.pdf', ''), (101, 1, NULL, NULL, 'artigo', 'Rascunho privado', '', '', 'Biologia', 'privada', 'publicado', 0, '', 'materials/private.pdf', ''), (102, 1, NULL, 11, 'video_aula', 'Aula de genética', '', 'Aula', 'Biologia', 'sala', 'publicado', 0, '', '', 'https://example.test/genetica'), (103, 1, 20, NULL, 'material', 'Guia institucional', '', 'Guia', 'Biologia', 'instituicao', 'publicado', 0, '', 'materials/institution.pdf', ''), (104, 1, NULL, NULL, 'video_aula', 'Aula premium', '', 'Premium', 'Biologia', 'publica', 'publicado', 1, '', '', 'https://example.test/premium'), (105, 1, NULL, NULL, 'post', 'Rascunho', '', '', 'Biologia', 'publica', 'rascunho', 0, '', '', '')");
$pdo->exec("INSERT INTO biblioteca VALUES (1, 'Atlas de Biologia', 'Autora A', 'livro', 'Biologia', 'Células e ecossistemas', '', 'materials/atlas.pdf', 'gratis', 'ativo', NULL), (2, 'Apostila de Matemática', 'Autor B', 'apostila', 'Matemática', 'Álgebra', '', '', 'gratis', 'ativo', NULL), (3, 'Guia institucional', 'Autora C', 'pdf', 'Biologia', 'Material interno', '', 'materials/institution.pdf', 'gratis', 'ativo', 20), (4, 'Material em revisão', 'Autor D', 'pdf', 'Biologia', '', '', '', 'gratis', 'em_revisao', NULL)");
$pdo->exec("INSERT INTO biblioteca VALUES (5, 'Documento de outra escola', 'Autor E', 'pdf', 'Biologia', 'Privado', '', '', 'gratis', 'ativo', 21)");
$pdo->exec("INSERT INTO publicacoes VALUES (106, 9, 21, NULL, 'artigo', 'Publicação de outra escola', '', 'Privado', 'Biologia', 'instituicao', 'publicado', 0, '', '', '')");

$unlinkedUser = pesquisar_catalogo($pdo, 3, false, '', '', '', '', '', 1, 20);
$unlinkedIds = catalog_ids($unlinkedUser);
assert_catalog(!in_array('sala:11', $unlinkedIds, true), 'Usuário sem vínculo não deve ver sala privada.');
assert_catalog(!in_array('publicacao:101', $unlinkedIds, true), 'Usuário não deve ver publicação privada alheia.');
assert_catalog(!in_array('publicacao:102', $unlinkedIds, true), 'Usuário fora da sala não deve ver publicação da sala.');
assert_catalog(!in_array('publicacao:103', $unlinkedIds, true), 'Usuário fora da instituição não deve ver publicação institucional.');
assert_catalog(!in_array('publicacao:104', $unlinkedIds, true), 'Usuário sem plano não deve ver conteúdo Premium.');
assert_catalog(!in_array('publicacao:105', $unlinkedIds, true), 'Conteúdo não publicado não deve aparecer.');
assert_catalog(!in_array('biblioteca:3', $unlinkedIds, true), 'Usuário não deve ver material institucional alheio.');
assert_catalog(!in_array('biblioteca:5', $unlinkedIds, true), 'Usuário não deve ver material de uma segunda instituição.');
assert_catalog(!in_array('publicacao:106', $unlinkedIds, true), 'Usuário não deve ver publicação de uma segunda instituição.');
assert_catalog(!in_array('biblioteca:4', $unlinkedIds, true), 'Material em revisão não deve aparecer.');

$memberResults = pesquisar_catalogo($pdo, 2, false, 'Genética', '', '', '', '', 1, 20);
$memberIds = catalog_ids($memberResults);
assert_catalog(in_array('sala:11', $memberIds, true), 'Membro ativo deve encontrar a sala privada.');
assert_catalog(in_array('publicacao:102', $memberIds, true), 'Membro ativo deve encontrar conteúdo da sala.');
$institutionMemberResults = pesquisar_catalogo($pdo, 2, false, 'Guia institucional', '', '', '', '', 1, 20);
$institutionMemberIds = catalog_ids($institutionMemberResults);
assert_catalog(in_array('biblioteca:3', $institutionMemberIds, true), 'Membro institucional ativo deve encontrar material da sua instituição.');
assert_catalog(!in_array('biblioteca:5', $institutionMemberIds, true), 'Membro não deve encontrar material de outra instituição.');
$otherInstitutionPublication = pesquisar_catalogo($pdo, 2, false, 'Publicação de outra escola', '', '', '', '', 1, 20);
assert_catalog(!in_array('publicacao:106', catalog_ids($otherInstitutionPublication), true), 'Membro não deve encontrar publicação de outra instituição.');
$institutionRoom = pesquisar_catalogo($pdo, 2, false, 'Comunidade da instituição', '', '', 'sala', '', 1, 20);
assert_catalog(in_array('sala:12', catalog_ids($institutionRoom), true), 'Membro deve encontrar comunidade da própria instituição.');
$foreignInstitutionRoom = pesquisar_catalogo($pdo, 3, false, 'Comunidade da instituição', '', '', 'sala', '', 1, 20);
assert_catalog(!in_array('sala:12', catalog_ids($foreignInstitutionRoom), true), 'Usuário de outra instituição não deve encontrar comunidade privada.');

$unlinkedRooms = buscar_salas($pdo, 3, '', '', '', '');
assert_catalog(count($unlinkedRooms) === 1 && (int) $unlinkedRooms[0]['id'] === 10, 'Listagem de salas deve ocultar privadas de não membros.');
assert_catalog((int) $unlinkedRooms[0]['alunos'] === 1, 'Listagem de salas deve contar apenas vínculos de aluno ativos.');
$memberPrivateRooms = buscar_salas($pdo, 2, '', 'Genética', 'Biologia', 'privada');
assert_catalog(count($memberPrivateRooms) === 1 && (int) $memberPrivateRooms[0]['id'] === 11, 'Filtro de assunto e acesso deve achar sala privada do membro.');
$nonmemberPrivateRooms = buscar_salas($pdo, 3, '', '', '', 'privada');
assert_catalog($nonmemberPrivateRooms === [], 'Filtro privado não deve expor sala a usuário sem vínculo.');
$roomTotal = 0;
$firstRoomPage = buscar_salas($pdo, 2, '', '', '', '', null, 1, 0, $roomTotal);
$secondRoomPage = buscar_salas($pdo, 2, '', '', '', '', null, 1, 1, $roomTotal);
assert_catalog($roomTotal === 3, 'A paginação mobile deve contar somente as salas autorizadas.');
assert_catalog(count($firstRoomPage) === 1 && count($secondRoomPage) === 1, 'A paginação mobile deve limitar resultados no banco.');
assert_catalog((int) $firstRoomPage[0]['id'] !== (int) $secondRoomPage[0]['id'], 'Páginas consecutivas não devem repetir salas.');

$ownerResults = pesquisar_catalogo($pdo, 1, false, '', '', '', '', 'privada', 1, 20);
assert_catalog(in_array('publicacao:101', catalog_ids($ownerResults), true), 'Proprietário deve encontrar sua publicação privada.');

$institutionOwner = pesquisar_catalogo($pdo, 1, false, 'Guia institucional', '', '', '', '', 1, 20);
assert_catalog(in_array('biblioteca:3', catalog_ids($institutionOwner), true), 'Responsável deve consultar material da própria instituição.');

$apostilas = pesquisar_catalogo($pdo, 3, false, '', '', 'Matemática', 'apostila', '', 1, 20);
assert_catalog(catalog_ids($apostilas) === ['biblioteca:2'], 'Filtro de tipo e matéria deve usar os dados da biblioteca.');
$libraryCatalog = pesquisar_catalogo($pdo, 3, false, '', '', '', '', '', 1, 20, 'biblioteca');
assert_catalog(
    $libraryCatalog['items'] !== []
        && array_reduce($libraryCatalog['items'], static fn (bool $onlyLibrary, array $item): bool => $onlyLibrary && $item['source'] === 'biblioteca', true),
    'Origem biblioteca deve retornar somente materiais da Biblioteca.'
);

$premium = pesquisar_catalogo($pdo, 1, true, '', '', '', 'video_aula', 'premium', 1, 20);
assert_catalog(catalog_ids($premium) === ['publicacao:104'], 'Filtro Premium deve refletir o marcador persistido e exigir plano ativo.');

$firstPage = pesquisar_catalogo($pdo, 2, false, '', '', '', '', '', 1, 2);
$secondPage = pesquisar_catalogo($pdo, 2, false, '', '', '', '', '', 2, 2);
assert_catalog($firstPage['meta']['totalPages'] > 1, 'Resultados devem informar paginação real.');
assert_catalog($firstPage['items'] !== $secondPage['items'], 'Páginas diferentes devem retornar itens diferentes.');

try {
    pesquisar_catalogo($pdo, 3, false, '', '', '', 'mentoria', '', 1, 10);
    throw new RuntimeException('Curso/mentoria sem suporte devem ser rejeitados como tipos pesquisáveis.');
} catch (InvalidArgumentException) {
}

$libraryUser = buscar_materiais_biblioteca($pdo, 3, false, '', '', '');
$libraryIds = array_map(static fn (array $item): string => $item['source_type'] . ':' . $item['id'], $libraryUser);
assert_catalog(!in_array('biblioteca:3', $libraryIds, true), 'Biblioteca não deve expor material de instituição alheia.');
assert_catalog(!in_array('biblioteca:4', $libraryIds, true), 'Biblioteca não deve exibir material em revisão.');
assert_catalog(!in_array('publicacao:101', $libraryIds, true), 'Biblioteca não deve expor publicação privada alheia.');
assert_catalog(!in_array('publicacao:102', $libraryIds, true), 'Biblioteca não deve expor publicação de sala sem membership.');
assert_catalog(!in_array('publicacao:103', $libraryIds, true), 'Biblioteca não deve expor publicação institucional alheia.');
assert_catalog(!in_array('publicacao:104', $libraryIds, true), 'Biblioteca não deve expor material Premium a conta sem plano.');

$libraryOwner = buscar_materiais_biblioteca($pdo, 1, false, 'guia', 'Biologia', '');
assert_catalog(in_array('biblioteca:3', array_map(static fn (array $item): string => $item['source_type'] . ':' . $item['id'], $libraryOwner), true), 'Responsável deve pesquisar material próprio por palavra-chave e matéria.');
$memberLibrary = buscar_materiais_biblioteca($pdo, 2, false, '', '', 'video_aula');
assert_catalog(in_array('publicacao:102', array_map(static fn (array $item): string => $item['source_type'] . ':' . $item['id'], $memberLibrary), true), 'Membro de sala deve receber videoaula autorizada.');
$premiumLibrary = buscar_materiais_biblioteca($pdo, 1, true, '', '', 'video_aula');
assert_catalog(in_array('publicacao:104', array_map(static fn (array $item): string => $item['source_type'] . ':' . $item['id'], $premiumLibrary), true), 'Conta Premium ativa deve receber conteúdo Premium publicado.');
assert_catalog(!in_array('publicacao:102', array_map(static fn (array $item): string => $item['source_type'] . ':' . $item['id'], $premiumLibrary), true), 'Plano Premium não deve substituir membership em sala privada.');
$pdo->exec("UPDATE institution_members SET status = 'revoked' WHERE institution_id = 20 AND user_id = 2");
$revokedMemberResults = pesquisar_catalogo($pdo, 2, false, 'Guia institucional', '', '', '', '', 1, 20);
assert_catalog(!in_array('biblioteca:3', catalog_ids($revokedMemberResults), true), 'Revogação deve retirar imediatamente o acesso institucional.');
$pdo->exec("UPDATE subscriptions SET status = 'expired' WHERE institution_id = 20");
$expiredSubscriptionResults = pesquisar_catalogo($pdo, 1, false, 'Guia institucional', '', '', '', '', 1, 20);
assert_catalog(!in_array('biblioteca:3', catalog_ids($expiredSubscriptionResults), true), 'Assinatura expirada deve bloquear conteúdo institucional, inclusive para a responsável.');

echo "OK: filtros, paginação e autorização do catálogo validados.\n";