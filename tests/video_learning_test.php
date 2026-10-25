<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

foreach ([
    'CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nome_completo TEXT)',
    'CREATE TABLE salas (id INTEGER PRIMARY KEY, institution_id INTEGER, privacidade TEXT)',
    'CREATE TABLE salas_usuarios (sala_id INTEGER, usuario_id INTEGER, status TEXT)',
    'CREATE TABLE instituicoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, nome TEXT, validation_status TEXT, tipo TEXT, natureza TEXT)',
    'CREATE TABLE institution_members (institution_id INTEGER, user_id INTEGER, member_type TEXT, status TEXT)',
    'CREATE TABLE institution_class_subjects (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, teacher_user_id INTEGER)',
    'CREATE TABLE institution_enrollments (id INTEGER PRIMARY KEY, institution_id INTEGER, class_id INTEGER, student_user_id INTEGER, status TEXT)',
    'CREATE TABLE institution_learning_resources (id INTEGER PRIMARY KEY, institution_id INTEGER, class_subject_id INTEGER, resource_type TEXT, resource_id INTEGER)',
    'CREATE TABLE product_plans (code TEXT PRIMARY KEY, account_type TEXT, institution_type TEXT, is_enabled INTEGER)',
    'CREATE TABLE subscriptions (institution_id INTEGER, plan_code TEXT, status TEXT, payment_confirmed_at TEXT, starts_at TEXT, expires_at TEXT)',
    'CREATE TABLE institution_portal_suspensions (institution_id INTEGER PRIMARY KEY, suspended_by INTEGER, reason TEXT)',
    'CREATE TABLE publicacoes (
        id INTEGER PRIMARY KEY, usuario_id INTEGER, instituicao_id INTEGER, sala_id INTEGER,
        tipo TEXT, titulo TEXT, resumo TEXT, conteudo TEXT, categoria TEXT, visibilidade TEXT,
        status TEXT, is_premium_only INTEGER, video_url TEXT, education_stage TEXT,
        knowledge_area TEXT, subject TEXT, topic TEXT, subtopic TEXT, course_title TEXT,
        complementary_materials TEXT, related_exercises TEXT, captions_url TEXT
    )',
    'CREATE TABLE video_learning_progress (
        user_id INTEGER, publication_id INTEGER, played_seconds NUMERIC DEFAULT 0,
        last_position NUMERIC DEFAULT 0, PRIMARY KEY (user_id, publication_id)
    )',
    'CREATE TABLE video_playback_sessions (
        user_id INTEGER, publication_id INTEGER, token_hash TEXT UNIQUE, last_position NUMERIC,
        heartbeat_at INTEGER, is_playing INTEGER, client_sequence INTEGER DEFAULT 0,
        PRIMARY KEY (user_id, publication_id)
    )',
    'CREATE TABLE video_ad_intervals (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, publication_id INTEGER,
        interval_number INTEGER, status TEXT, UNIQUE (user_id, publication_id, interval_number)
    )',
] as $sql) {
    $pdo->exec($sql);
}

require_once __DIR__ . '/../includes/video_learning.php';

function assert_video_learning(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo->exec("INSERT INTO usuarios (id, nome_completo) VALUES (1, 'Professor'), (2, 'Estudante'), (3, 'Membro'), (4, 'Reprodução estagnada')");
$pdo->exec("INSERT INTO instituicoes VALUES (10, 1, 'Escola teste', 'validated', 'fundamental', 'publica')");
$pdo->exec("INSERT INTO product_plans VALUES ('institution-school', 'institution', 'public_school', 1)");
$pdo->exec("INSERT INTO subscriptions VALUES (10, 'institution-school', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '2999-12-31 23:59:59')");
$pdo->exec("INSERT INTO institution_members VALUES (10, 3, 'student', 'active')");
$pdo->exec(
    "INSERT INTO publicacoes (id, usuario_id, instituicao_id, tipo, titulo, visibilidade, status, is_premium_only, video_url)
     VALUES
      (101, 1, NULL, 'video_aula', 'Aula pública', 'publica', 'publicado', 0, 'https://media.example.test/aula.mp4'),
      (102, 1, NULL, 'video_aula', 'Aula privada', 'privada', 'publicado', 0, 'https://media.example.test/privada.mp4'),
      (103, 1, 10, 'video_aula', 'Aula da instituição', 'instituicao', 'publicado', 0, 'https://media.example.test/instituicao.mp4'),
      (104, 1, NULL, 'video_aula', 'Aula Premium antiga', 'publica', 'publicado', 1, 'https://media.example.test/premium.mp4'),
      (105, 1, NULL, 'artigo', 'Artigo', 'publica', 'publicado', 0, '')"
);

assert_video_learning(video_get_authorized_lesson($pdo, 101, 2, false) !== null, 'Uma conta autenticada deve abrir uma aula pública.');
assert_video_learning(video_get_authorized_lesson($pdo, 102, 2, false) === null, 'Uma conta sem vínculo não deve abrir uma aula privada.');
assert_video_learning(video_get_authorized_lesson($pdo, 102, 1, false) !== null, 'A pessoa responsável deve continuar abrindo sua aula privada.');
assert_video_learning(video_get_authorized_lesson($pdo, 103, 3, false) !== null, 'Membro institucional com assinatura vigente deve abrir conteúdo da instituição.');
$pdo->exec("INSERT INTO institution_portal_suspensions VALUES (10, 1, 'Revisão administrativa')");
assert_video_learning(video_get_authorized_lesson($pdo, 103, 3, false) === null, 'Suspensão institucional bloqueia também o acesso direto a videoaulas.');
$pdo->exec('DELETE FROM institution_portal_suspensions WHERE institution_id = 10');
assert_video_learning(video_get_authorized_lesson($pdo, 104, 2, false) === null, 'Conta sem entitlement não deve abrir uma videoaula Premium legada.');
assert_video_learning(video_get_authorized_lesson($pdo, 104, 2, true) !== null, 'Conta Premium deve manter acesso a videoaula Premium legada.');
assert_video_learning(video_get_authorized_lesson($pdo, 105, 2, false) === null, 'O player não deve aceitar itens que não sejam videoaulas.');
$pdo->exec("UPDATE institution_members SET status = 'revoked' WHERE institution_id = 10 AND user_id = 3");
assert_video_learning(video_get_authorized_lesson($pdo, 103, 3, false) === null, 'Revogação de membership deve retirar acesso ao player.');
$pdo->exec("UPDATE institution_members SET status = 'active' WHERE institution_id = 10 AND user_id = 3");
$pdo->exec("UPDATE subscriptions SET expires_at = '2000-01-01 00:00:00' WHERE institution_id = 10");
assert_video_learning(video_get_authorized_lesson($pdo, 103, 3, false) === null, 'Assinatura institucional expirada deve retirar acesso ao player.');

assert_video_learning(video_account_requires_ads(false, false), 'Contas gratuitas devem receber intervalos de anúncios.');
assert_video_learning(video_account_requires_ads(false, true), 'Premium não deve ser tratado como sem anúncios antes da confirmação comercial.');
assert_video_learning(!video_account_requires_ads(true, true), 'A administração não deve receber intervalos de anúncios.');
putenv('VIDEO_PREMIUM_AD_FREE=true');
assert_video_learning(!video_account_requires_ads(false, true), 'A configuração explícita de política pode liberar Premium de anúncios.');
putenv('VIDEO_PREMIUM_AD_FREE');

$started = video_learning_start($pdo, 2, 101, 1000);
$token = $started['session_token'];
video_learning_heartbeat($pdo, 2, 101, $token, 0, true, true, 1, 1000);
$lastHeartbeat = null;
for ($second = 1; $second <= 600; $second++) {
    $lastHeartbeat = video_learning_heartbeat($pdo, 2, 101, $token, (float) $second, true, true, $second + 1, 1000 + $second);
}
assert_video_learning((float) $lastHeartbeat['played_seconds'] === 600.0, 'A contagem deve acumular apenas segundos ativos observados pelo servidor.');
assert_video_learning($lastHeartbeat['new_ad_intervals'] === [1], 'A primeira interrupção deve surgir após dez minutos de reprodução efetiva.');

$duplicate = video_learning_heartbeat($pdo, 2, 101, $token, 600, true, true, 601, 1600);
assert_video_learning($duplicate['new_ad_intervals'] === [] && (float) $duplicate['played_seconds'] === 600.0, 'Repetir heartbeat não deve duplicar tempo nem intervalo.');
$pauseResult = video_learning_heartbeat($pdo, 2, 101, $token, 601, false, true, 602, 1601);
assert_video_learning((float) $pauseResult['played_seconds'] === 601.0, 'A pausa deve contabilizar somente até a última posição reproduzida.');
$pausedResult = video_learning_heartbeat($pdo, 2, 101, $token, 601, false, true, 603, 1611);
assert_video_learning((float) $pausedResult['played_seconds'] === 601.0, 'Tempo em pausa não deve ser contabilizado.');
video_learning_heartbeat($pdo, 2, 101, $token, 601, true, true, 604, 1611);
$resumeResult = video_learning_heartbeat($pdo, 2, 101, $token, 602, true, true, 605, 1612);
assert_video_learning((float) $resumeResult['played_seconds'] === 602.0, 'Retomar a aula deve continuar o mesmo progresso.');
$seekResult = video_learning_heartbeat($pdo, 2, 101, $token, 9000, true, true, 606, 1613);
assert_video_learning((float) $seekResult['played_seconds'] === 602.0, 'Buscar diretamente para frente não deve contar como reprodução.');

$reloaded = video_learning_start($pdo, 2, 101, 2000);
assert_video_learning((float) $reloaded['played_seconds'] === 602.0 && (float) $reloaded['last_position'] === 9000.0, 'Reabrir a aula deve preservar o progresso e a posição.');
try {
    video_learning_heartbeat($pdo, 2, 101, $token, 9000, true, true, 607, 2001);
    throw new RuntimeException('Sessão substituída deveria ser rejeitada.');
} catch (DomainException) {
}
video_learning_heartbeat($pdo, 2, 101, $reloaded['session_token'], 9000, true, true, 1, 2000);
$afterReload = video_learning_heartbeat($pdo, 2, 101, $reloaded['session_token'], 9001, true, true, 2, 2001);
assert_video_learning((float) $afterReload['played_seconds'] === 603.0, 'A nova sessão deve seguir do total persistido, não reiniciar o intervalo.');
$staleSequence = video_learning_heartbeat($pdo, 2, 101, $reloaded['session_token'], 9001, true, true, 1, 2002);
assert_video_learning((float) $staleSequence['played_seconds'] === 603.0, 'Heartbeats concorrentes ou repetidos fora de ordem não devem voltar ou duplicar o estado.');

$provider = video_ad_provider_status();
assert_video_learning($provider['mode'] === 'test' && !$provider['configured'], 'O modo de teste precisa indicar que nenhum provedor real está conectado.');
$providerAdapter = video_ad_provider();
assert_video_learning(
    $providerAdapter->requestBreak(2, 101, 1) === null
    && !$providerAdapter->verifyCompletion(2, 101, 1, ''),
    'O adaptador sem configuração deve falhar aberto sem inventar anúncio ou recibo.'
);
$events = $pdo->query('SELECT interval_number, status FROM video_ad_intervals ORDER BY interval_number')->fetchAll();
assert_video_learning(count($events) === 1 && $events[0]['status'] === 'not_configured', 'O sistema deve registrar o intervalo sem fingir que mostrou anúncio.');

$stalledSession = video_learning_start($pdo, 4, 101, 2500);
video_learning_heartbeat($pdo, 4, 101, $stalledSession['session_token'], 0, true, true, 1, 2500);
$stalledProgress = video_learning_heartbeat(
    $pdo,
    4,
    101,
    $stalledSession['session_token'],
    0.1,
    true,
    true,
    2,
    2510
);
assert_video_learning(
    (float) $stalledProgress['played_seconds'] === 0.0,
    'Avanço quase nulo durante um heartbeat não deve ser contado como dez segundos reproduzidos.'
);

$shortLesson = video_learning_start($pdo, 2, 101, 3000);
video_learning_heartbeat($pdo, 2, 101, $shortLesson['session_token'], 9001, true, true, 1, 3000);
for ($second = 1; $second <= 90; $second++) {
    $shortProgress = video_learning_heartbeat(
        $pdo,
        2,
        101,
        $shortLesson['session_token'],
        9001 + $second,
        true,
        true,
        $second + 1,
        3000 + $second
    );
}
assert_video_learning($shortProgress['new_ad_intervals'] === [], 'Vídeos curtos não devem receber anúncio antes de dez minutos de reprodução.');

$longLesson = video_learning_start($pdo, 3, 101, 4000);
video_learning_heartbeat($pdo, 3, 101, $longLesson['session_token'], 0, true, true, 1, 4000);
for ($second = 1; $second <= 1200; $second++) {
    $longProgress = video_learning_heartbeat(
        $pdo,
        3,
        101,
        $longLesson['session_token'],
        (float) $second,
        true,
        true,
        $second + 1,
        4000 + $second
    );
}
assert_video_learning($longProgress['new_ad_intervals'] === [2], 'Uma aula longa deve alcançar o segundo intervalo aos vinte minutos reproduzidos.');
$longEvents = $pdo->query('SELECT interval_number FROM video_ad_intervals WHERE user_id = 3 ORDER BY interval_number')->fetchAll(PDO::FETCH_COLUMN);
assert_video_learning(array_map('intval', $longEvents) === [1, 2], 'O servidor deve manter distintos os marcos de dez e vinte minutos.');

echo "OK: autorização, anúncios em modo de teste, progresso, pausas, retomada, seeks e sessões substituídas validados.\n";
