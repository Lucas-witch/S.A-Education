<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_configure_security();
    session_start();
}
send_private_no_store_headers();
require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/video_learning.php';

require_auth();
$userId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0 || !authenticated_session_is_current($pdo)) {
    invalidate_current_session();
    http_response_code(401);
    exit('Sua sessão expirou. Entre novamente para assistir à aula.');
}
$permissions = refresh_user_permission_session($pdo, (int) $userId);
$publicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$lesson = $publicationId !== false && $publicationId !== null
    ? video_get_authorized_lesson(
        $pdo,
        (int) $publicationId,
        (int) $userId,
        $permissions['premium'] || $permissions['is_admin']
    )
    : null;
if ($lesson === null) {
    http_response_code(404);
    exit('Aula não encontrada ou sem permissão.');
}

$videoUrl = '';
$captionsUrl = '';
try {
    $videoUrl = feed_safe_resource_url((string) ($lesson['video_url'] ?? ''));
    $captionsUrl = feed_safe_resource_url((string) ($lesson['captions_url'] ?? ''));
} catch (InvalidArgumentException $error) {
    error_log('[S.A Education][video_player] URL de mídia inválida para aula ' . (int) $lesson['id']);
}
$supportedVideoUrl = $videoUrl !== '' && preg_match('/\.(mp4|m4v|webm|ogv|ogg)(?:[?#]|$)/i', $videoUrl) === 1;
$providerStatus = video_ad_provider_status();
$requiresAds = video_account_requires_ads($permissions['is_admin'], $permissions['premium']);
$nextLesson = null;
$courseTitle = trim((string) ($lesson['course_title'] ?? ''));
if ($courseTitle !== '') {
    $scope = feed_authorization_scope((int) $userId);
    $premiumAccessSql = $permissions['premium'] || $permissions['is_admin'] ? '1 = 1' : '1 = 0';
    $nextQuery = $pdo->prepare(
        'SELECT p.id, p.titulo
         FROM publicacoes p
         WHERE p.tipo = "video_aula" AND p.status = "publicado"
           AND (p.is_premium_only = 0 OR p.usuario_id = :next_owner_id OR ' . $premiumAccessSql . ')
           AND p.course_title = :course_title AND p.id > :current_id
           AND ' . $scope['sql'] . '
         ORDER BY p.id ASC LIMIT 1'
    );
    $nextQuery->execute([
        ':next_owner_id' => (int) $userId,
        ':course_title' => $courseTitle,
        ':current_id' => (int) $lesson['id'],
    ] + $scope['params']);
    $nextLesson = $nextQuery->fetch(PDO::FETCH_ASSOC) ?: null;
}
$responsibleName = trim((string) ($lesson['responsible_name'] ?? ''));
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
    <title><?= esc((string) $lesson['titulo']) ?> | Videoaulas S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <style>
        .lesson-shell { width: min(1180px, calc(100% - 2rem)); margin: 1.5rem auto; }
        .lesson-video { width: 100%; max-height: 72vh; background: #171421; border-radius: 1rem; }
        .lesson-player-box { padding: clamp(1rem, 3vw, 2rem); background: #fff; border-radius: 1rem; box-shadow: 0 10px 35px #26123c16; }
        .lesson-meta { display: flex; flex-wrap: wrap; gap: .5rem 1rem; color: #51465a; }
        .lesson-links { display: flex; flex-wrap: wrap; gap: .75rem; margin: 1.2rem 0; }
        .lesson-status { min-height: 1.5rem; margin: .75rem 0; }
        .lesson-status[data-error="true"] { color: #922; }
        .lesson-ad-notice { margin: 1rem 0; padding: .8rem 1rem; border-left: 4px solid #75a843; background: #f4f9ef; }
        .lesson-navigation { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.5rem; }
        .lesson-navigation a { overflow-wrap: anywhere; }
        .lesson-details { margin-top: 1rem; }
        @media (max-width: 620px) { .lesson-shell { width: calc(100% - 1rem); margin: .5rem auto; } .lesson-player-box { padding: .85rem; } .lesson-navigation { flex-direction: column; } }
    </style>
</head>
<body class="reference-shell">
<?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main lesson-shell">
    <p><a href="feed.php?tipo=video_aula">← Voltar às videoaulas</a></p>
    <article class="lesson-player-box" data-publication-id="<?= (int) $lesson['id'] ?>" data-requires-ads="<?= $requiresAds ? 'true' : 'false' ?>">
        <h1><?= esc((string) $lesson['titulo']) ?></h1>
        <div class="lesson-meta">
            <?php if ($responsibleName !== ''): ?><span>Responsável: <?= esc($responsibleName) ?></span><?php endif; ?>
            <?php foreach ([
                'education_stage' => 'Etapa',
                'knowledge_area' => 'Área',
                'subject' => 'Disciplina',
                'topic' => 'Tema',
                'subtopic' => 'Subtópico',
                'course_title' => 'Curso',
            ] as $field => $label): ?>
                <?php if (trim((string) ($lesson[$field] ?? '')) !== ''): ?><span><?= esc($label) ?>: <?= esc((string) $lesson[$field]) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if (trim((string) ($lesson['resumo'] ?? '')) !== ''): ?><p><?= esc((string) $lesson['resumo']) ?></p><?php endif; ?>
        <?php if ($supportedVideoUrl): ?>
            <video class="lesson-video" id="lesson-video" controls playsinline preload="metadata" disablepictureinpicture>
                <source src="<?= esc($videoUrl) ?>">
                <?php if ($captionsUrl !== '' && preg_match('/\.vtt(?:[?#]|$)/i', $captionsUrl) === 1): ?>
                    <track kind="captions" src="<?= esc($captionsUrl) ?>" srclang="pt-BR" label="Português">
                <?php endif; ?>
                Seu navegador não oferece suporte à reprodução deste vídeo.
            </video>
            <label for="playback-rate">Velocidade da reprodução
                <select id="playback-rate">
                    <option value="0.75">0,75×</option>
                    <option value="1" selected>1×</option>
                    <option value="1.25">1,25×</option>
                    <option value="1.5">1,5×</option>
                    <option value="2">2×</option>
                </select>
            </label>
            <div class="lesson-status" id="lesson-status" role="status" aria-live="polite">Preparando player e retomada…</div>
            <button type="button" class="btn btn-outline" id="retry-tracking" hidden>Tentar reconectar</button>
            <?php if ($requiresAds): ?>
                <aside class="lesson-ad-notice" aria-label="Informação sobre anúncios">
                    <?= esc($providerStatus['message']) ?>
                    <?php if ($providerStatus['configured']): ?><span>Anúncios serão solicitados por intervalos de reprodução.</span><?php endif; ?>
                </aside>
            <?php endif; ?>
        <?php else: ?>
            <p role="alert">Esta URL ainda não pode ser reproduzida neste player. Use um arquivo de vídeo direto compatível (MP4, WebM ou Ogg); fontes privadas/permanentes precisam ser integradas a um provedor de streaming autorizado antes de serem usadas.</p>
        <?php endif; ?>

        <div class="lesson-links">
            <?php if (trim((string) ($lesson['complementary_materials'] ?? '')) !== ''): ?>
                <a class="btn btn-outline" href="<?= esc((string) $lesson['complementary_materials']) ?>" rel="noopener noreferrer">Materiais complementares</a>
            <?php endif; ?>
            <?php if (trim((string) ($lesson['related_exercises'] ?? '')) !== ''): ?>
                <a class="btn btn-outline" href="<?= esc((string) $lesson['related_exercises']) ?>" rel="noopener noreferrer">Exercícios relacionados</a>
            <?php endif; ?>
        </div>
        <?php if (trim((string) ($lesson['conteudo'] ?? '')) !== ''): ?>
            <details class="lesson-details">
                <summary>Descrição e notas da aula</summary>
                <p><?= nl2br(esc((string) $lesson['conteudo'])) ?></p>
            </details>
        <?php endif; ?>
    </article>
    <nav class="lesson-navigation" aria-label="Navegação entre aulas">
        <span></span>
        <?php if (is_array($nextLesson)): ?>
            <a class="btn btn-outline" href="videoaula.php?id=<?= (int) $nextLesson['id'] ?>">Próxima aula: <?= esc((string) $nextLesson['titulo']) ?></a>
        <?php endif; ?>
    </nav>
</main>
<?php if ($supportedVideoUrl): ?>
<script>
(() => {
    const box = document.querySelector('.lesson-player-box');
    const video = document.getElementById('lesson-video');
    const status = document.getElementById('lesson-status');
    const retryButton = document.getElementById('retry-tracking');
    const playbackRate = document.getElementById('playback-rate');
    const api = 'api/video-aprendizado.php';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const publicationId = box.dataset.publicationId;
    let sessionToken = '';
    let heartbeatTimer = 0;
    let pending = false;
    let ready = false;
    let sequence = 0;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.dataset.error = String(isError);
    };
    const request = async (fields) => {
        const data = new FormData();
        Object.entries({ csrf, publication_id: publicationId, ...fields }).forEach(([key, value]) => data.append(key, String(value)));
        const response = await fetch(api, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Não foi possível atualizar a reprodução.');
        return result;
    };
    const stopHeartbeat = () => {
        if (heartbeatTimer) window.clearInterval(heartbeatTimer);
        heartbeatTimer = 0;
    };
    const sendHeartbeat = async (playing) => {
        if (!ready || pending || !sessionToken) return;
        pending = true;
        sequence += 1;
        try {
            const result = await request({
                action: 'heartbeat',
                session_token: sessionToken,
                sequence,
                position: Number.isFinite(video.currentTime) ? video.currentTime : 0,
                is_playing: playing ? 1 : 0
            });
            if (result.new_ad_intervals?.length) {
                const intervals = result.new_ad_intervals.join(', ');
                setStatus(`Intervalo(s) ${intervals} registrado(s). ${result.ad_provider.message}`);
            }
        } catch (error) {
            video.pause();
            stopHeartbeat();
            ready = false;
            retryButton.hidden = false;
            setStatus(error instanceof Error ? `${error.message} A reprodução foi pausada para não perder a contabilização.` : 'Falha ao registrar a reprodução.', true);
        } finally {
            pending = false;
        }
    };
    const connect = async () => {
        retryButton.hidden = true;
        setStatus('Conectando ao acompanhamento de reprodução…');
        try {
            const result = await request({ action: 'start' });
            sessionToken = result.session_token;
            sequence = 0;
            ready = true;
            const restorePosition = Math.max(0, Number(result.last_position) || 0);
            const restore = () => {
                if (restorePosition > 0 && (!Number.isFinite(video.duration) || restorePosition < video.duration)) {
                    video.currentTime = restorePosition;
                }
                video.controls = true;
            };
            if (video.readyState >= 1) restore();
            else video.addEventListener('loadedmetadata', restore, { once: true });
            setStatus(restorePosition > 0 ? `Retomada da posição salva. ${result.ad_provider.message}` : result.ad_provider.message);
        } catch (error) {
            ready = false;
            video.pause();
            video.controls = false;
            retryButton.hidden = false;
            setStatus(error instanceof Error ? error.message : 'Não foi possível iniciar o acompanhamento.', true);
        }
    };

    video.controls = false;
    playbackRate.addEventListener('change', () => {
        video.playbackRate = Number(playbackRate.value);
    });
    connect();
    video.addEventListener('play', () => {
        if (!ready) {
            video.pause();
            return;
        }
        void sendHeartbeat(true);
        stopHeartbeat();
        heartbeatTimer = window.setInterval(() => void sendHeartbeat(true), 10000);
    });
    video.addEventListener('pause', () => {
        stopHeartbeat();
        void sendHeartbeat(false);
    });
    video.addEventListener('ended', () => {
        stopHeartbeat();
        void sendHeartbeat(false);
        setStatus('Aula concluída. O progresso foi salvo.');
    });
    video.addEventListener('error', () => {
        setStatus('Não foi possível carregar esta fonte de vídeo. O acesso continua disponível quando a origem voltar a funcionar.', true);
    });
    retryButton.addEventListener('click', () => void connect());
})();
</script>
<?php endif; ?>
</body>
</html>
