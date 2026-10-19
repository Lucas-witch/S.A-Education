<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

foreach ([
    'CREATE TABLE salas (id INTEGER PRIMARY KEY, nome TEXT, prof TEXT, materia TEXT, descricao TEXT, privacidade TEXT, criado_em TEXT, alunos INTEGER DEFAULT 0, icone TEXT)',
    'CREATE TABLE salas_usuarios (id INTEGER PRIMARY KEY, sala_id INTEGER, usuario_id INTEGER, papel TEXT, status TEXT)',
    'CREATE TABLE publicacoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER, tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, categoria TEXT, visibilidade TEXT, status TEXT, is_premium_only INTEGER, capa_url TEXT, arquivo_url TEXT, video_url TEXT)',
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER)',
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
$pdo->exec("INSERT INTO instituicoes VALUES (20, 1)");
$pdo->exec("INSERT INTO publicacoes VALUES (100, 1, NULL, NULL, 'artigo', 'Ecologia aplicada', 'Resumo público', 'Conteúdo público', 'Biologia', 'publica', 'publicado', 0, '', 'materials/ecology.pdf', ''), (101, 1, NULL, NULL, 'artigo', 'Rascunho privado', '', '', 'Biologia', 'privada', 'publicado', 0, '', 'materials/private.pdf', ''), (102, 1, NULL, 11, 'video_aula', 'Aula de genética', '', 'Aula', 'Biologia', 'sala', 'publicado', 0, '', '', 'https://example.test/genetica'), (103, 1, 20, NULL, 'material', 'Guia institucional', '', 'Guia', 'Biologia', 'instituicao', 'publicado', 0, '', 'materials/institution.pdf', ''), (104, 1, NULL, NULL, 'video_aula', 'Aula premium', '', 'Premium', 'Biologia', 'publica', 'publicado', 1, '', '', 'https://example.test/premium'), (105, 1, NULL, NULL, 'post', 'Rascunho', '', '', 'Biologia', 'publica', 'rascunho', 0, '', '', '')");
$pdo->exec("INSERT INTO biblioteca VALUES (1, 'Atlas de Biologia', 'Autora A', 'livro', 'Biologia', 'Células e ecossistemas', '', 'materials/atlas.pdf', 'gratis', 'ativo', NULL), (2, 'Apostila de Matemática', 'Autor B', 'apostila', 'Matemática', 'Álgebra', '', '', 'gratis', 'ativo', NULL), (3, 'Guia institucional', 'Autora C', 'pdf', 'Biologia', 'Material interno', '', 'materials/institution.pdf', 'gratis', 'ativo', 20), (4, 'Material em revisão', 'Autor D', 'pdf', 'Biologia', '', '', '', 'gratis', 'em_revisao', NULL)");

$unlinkedUser = pesquisar_catalogo($pdo, 3, false, '', '', '', '', '', 1, 20);
$unlinkedIds = catalog_ids($unlinkedUser);
assert_catalog(!in_array('sala:11', $unlinkedIds, true), 'Usuário sem vínculo não deve ver sala privada.');
assert_catalog(!in_array('publicacao:101', $unlinkedIds, true), 'Usuário não deve ver publicação privada alheia.');
assert_catalog(!in_array('publicacao:102', $unlinkedIds, true), 'Usuário fora da sala não deve ver publicação da sala.');
assert_catalog(!in_array('publicacao:103', $unlinkedIds, true), 'Usuário fora da instituição não deve ver publicação institucional.');
assert_catalog(!in_array('publicacao:104', $unlinkedIds, true), 'Usuário sem plano não deve ver conteúdo Premium.');
assert_catalog(!in_array('publicacao:105', $unlinkedIds, true), 'Conteúdo não publicado não deve aparecer.');
assert_catalog(!in_array('biblioteca:3', $unlinkedIds, true), 'Usuário não deve ver material institucional alheio.');
assert_catalog(!in_array('biblioteca:4', $unlinkedIds, true), 'Material em revisão não deve aparecer.');

$memberResults = pesquisar_catalogo($pdo, 2, false, 'Genética', '', '', '', '', 1, 20);
$memberIds = catalog_ids($memberResults);
assert_catalog(in_array('sala:11', $memberIds, true), 'Membro ativo deve encontrar a sala privada.');
assert_catalog(in_array('publicacao:102', $memberIds, true), 'Membro ativo deve encontrar conteúdo da sala.');

$unlinkedRooms = buscar_salas($pdo, 3, '', '', '', '');
assert_catalog(count($unlinkedRooms) === 1 && (int) $unlinkedRooms[0]['id'] === 10, 'Listagem de salas deve ocultar privadas de não membros.');
assert_catalog((int) $unlinkedRooms[0]['alunos'] === 1, 'Listagem de salas deve contar apenas vínculos de aluno ativos.');
$memberPrivateRooms = buscar_salas($pdo, 2, '', 'Genética', 'Biologia', 'privada');
assert_catalog(count($memberPrivateRooms) === 1 && (int) $memberPrivateRooms[0]['id'] === 11, 'Filtro de assunto e acesso deve achar sala privada do membro.');
$nonmemberPrivateRooms = buscar_salas($pdo, 3, '', '', '', 'privada');
assert_catalog($nonmemberPrivateRooms === [], 'Filtro privado não deve expor sala a usuário sem vínculo.');

$ownerResults = pesquisar_catalogo($pdo, 1, false, '', '', '', '', 'privada', 1, 20);
assert_catalog(in_array('publicacao:101', catalog_ids($ownerResults), true), 'Proprietário deve encontrar sua publicação privada.');

$institutionOwner = pesquisar_catalogo($pdo, 1, false, 'Guia institucional', '', '', '', '', 1, 20);
assert_catalog(in_array('biblioteca:3', catalog_ids($institutionOwner), true), 'Responsável deve consultar material da própria instituição.');

$apostilas = pesquisar_catalogo($pdo, 3, false, '', '', 'Matemática', 'apostila', '', 1, 20);
assert_catalog(catalog_ids($apostilas) === ['biblioteca:2'], 'Filtro de tipo e matéria deve usar os dados da biblioteca.');

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

echo "OK: filtros, paginação e autorização do catálogo validados.\n";