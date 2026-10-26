<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
require_once SA_PROJECT_ROOT . '/includes/digital_library.php';

$userId = library_authenticated_user_id($pdo);
$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$material = $materialId !== false && $materialId !== null
    ? library_find_readable_material($pdo, (int) $materialId, $userId)
    : null;
if ($material === null) {
    http_response_code(404);
    exit('Material não encontrado ou sem acesso.');
}
$text = library_sanitize_rich_text((string) ($material['texto_html'] ?? ''));
$hasFile = is_string($material['storage_key'] ?? null)
    && isset(library_supported_uploads()[strtolower(pathinfo((string) $material['storage_key'], PATHINFO_EXTENSION))]);
$hasPdf = $hasFile && (string) ($material['file_mime'] ?? '') === 'application/pdf';
if ($text === '' && !$hasFile) {
    http_response_code(404);
    exit('Este material não possui conteúdo de leitura integrado.');
}
library_record_access_event($pdo, $userId, (int) $material['id'], 'open');
$annotationQuery = $pdo->prepare(
    'SELECT id, annotation_type, anchor_text, anchor_start, anchor_end, note_text, color, created_at
     FROM library_annotations WHERE user_id = :user_id AND material_id = :material_id
     ORDER BY created_at'
);
$annotationQuery->execute(['user_id' => $userId, 'material_id' => (int) $material['id']]);
$annotations = $annotationQuery->fetchAll(PDO::FETCH_ASSOC);
$progressQuery = $pdo->prepare(
    'SELECT progress_percent FROM library_reading_progress
     WHERE user_id = :user_id AND material_id = :material_id LIMIT 1'
);
$progressQuery->execute(['user_id' => $userId, 'material_id' => (int) $material['id']]);
$initialProgress = (float) ($progressQuery->fetchColumn() ?: 0);
$viewerName = (string) ($_SESSION['usuario_username'] ?? $_SESSION['instituicao_nome'] ?? $_SESSION['usuario_nome'] ?? 'Leitor');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc(csrf_token()) ?>">
    <title><?= esc((string) $material['titulo']) ?> | Biblioteca S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <script src="assets/js/biblioteca-leitura-preferences.js" defer></script>
    <style>
        .reader-shell { max-width: 1180px; margin: 1rem auto; padding: 1rem; }
        .reader-toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; padding: .75rem; background: #fffdface; backdrop-filter: blur(8px); border-radius: .75rem; }
        .reader-layout { display: grid; grid-template-columns: minmax(0,1fr) 280px; gap: 1rem; }
        .reading-content { max-width: 820px; min-height: 50vh; margin: 1rem auto; padding: clamp(1rem,4vw,3rem); background: #fffdfa; color: #15173d; font: 1.125rem/1.75 Georgia,serif; overflow-wrap: anywhere; }
        .reading-content h1,.reading-content h2,.reading-content h3 { line-height: 1.25; scroll-margin-top: 5rem; }
        .reading-content img { max-width: 100%; height: auto; }
        .reading-content table { border-collapse: collapse; max-width: 100%; }
        .reading-content td,.reading-content th { border: 1px solid #777; padding: .4rem; }
        .reading-content[data-mode="dark"] { background: #1d2030; color: #f7f2eb; }
        .reading-content[data-mode="warm"] { background: #f3e9d8; color: #292218; }
        .reading-content[data-spacing="wide"] { line-height: 2.05; }
        .reading-content[data-spacing="compact"] { line-height: 1.45; }
        .reader-side { padding: 1rem; background: #f3f0fa; border-radius: .75rem; align-self: start; }
        .reader-side li { margin: .55rem 0; }
        .reader-pdf { width: 100%; height: min(78vh,1000px); border: 0; background: #eee; }
        .reader-watermark { position: fixed; z-index: 4; inset: 0; pointer-events: none; opacity: .11; background-image: repeating-linear-gradient(-32deg,transparent 0,transparent 135px,rgba(76,35,101,.7) 136px,transparent 138px); display: flex; align-items: center; justify-content: center; font: 700 clamp(1rem,3vw,2rem)/1.4 sans-serif; color: #4c2365; text-align: center; text-shadow: 0 1px #fff; }
        .annotation { padding: .65rem; margin: .6rem 0; background: #fff; border-radius: .5rem; overflow-wrap: anywhere; }
        .annotation mark { background: #fff1a8; }
        .reader-note-form { display: grid; gap: .5rem; }
        @media (max-width: 760px) { .reader-layout { grid-template-columns: 1fr; } .reader-side { order: 2; } .reader-toolbar { position: static; } }
        @media print { .reader-toolbar,.reader-side,.reader-watermark { display:none!important; } .reading-content { max-width:none; margin:0; padding:0; } }
    </style>
</head>
<body class="reference-shell">
<main class="reader-shell">
    <a href="biblioteca.php">← Voltar à Biblioteca</a>
    <h1><?= esc((string) $material['titulo']) ?></h1>
    <p><?= esc((string) ($material['autor'] ?? '')) ?><?= $material['materia'] !== null ? ' · ' . esc((string) $material['materia']) : '' ?></p>
    <div class="reader-toolbar" role="toolbar" aria-label="Controles de leitura">
        <label>Tamanho
            <select id="font-size"><option value="1rem">16</option><option value="1.125rem" selected>18</option><option value="1.25rem">20</option><option value="1.5rem">24</option></select>
        </label>
        <label>Espaçamento
            <select id="line-spacing"><option value="normal">Normal</option><option value="wide">Amplo</option><option value="compact">Compacto</option></select>
        </label>
        <label>Modo
            <select id="reading-mode"><option value="light">Claro</option><option value="warm">Quente</option><option value="dark">Escuro</option></select>
        </label>
        <button type="button" id="highlight-button">Destacar seleção</button>
        <button type="button" id="note-button">Anotar seleção</button>
        <span id="progress-label" role="status"><?= number_format($initialProgress, 0) ?>% lido</span>
    </div>
    <div class="reader-layout">
        <article class="reading-content" id="reading-content" data-mode="light" data-spacing="normal" style="font-size:1.125rem">
            <?php if ($text !== ''): ?>
                <?= $text ?>
            <?php endif; ?>
            <?php if ($hasPdf): ?>
                <iframe class="reader-pdf" src="biblioteca-arquivo.php?id=<?= (int) $material['id'] ?>" title="Leitor PDF integrado"></iframe>
            <?php elseif ($hasFile): ?>
                <p>Este formato pode ser consultado no leitor ou aplicativo correspondente.</p>
                <a class="btn btn-primary" href="biblioteca-arquivo.php?id=<?= (int) $material['id'] ?>">Abrir arquivo autorizado</a>
            <?php endif; ?>
        </article>
        <aside class="reader-side">
            <h2>Sumário</h2>
            <nav id="table-of-contents" aria-label="Sumário do material"></nav>
            <h2>Minhas anotações</h2>
            <form class="reader-note-form" id="annotation-form">
                <input type="hidden" name="annotation_type" value="note">
                <label for="annotation-text">Nota pessoal</label>
                <textarea id="annotation-text" name="note_text" maxlength="3000" rows="3"></textarea>
                <button type="submit">Salvar anotação</button>
            </form>
            <p id="selected-text" aria-live="polite"></p>
            <div id="annotations-list"></div>
        </aside>
    </div>
</main>
<div class="reader-watermark" aria-hidden="true">Leitura autorizada · <?= esc($viewerName) ?></div>
<script>
(() => {
    const article = document.getElementById('reading-content');
    const materialId = <?= (int) $material['id'] ?>;
    const api = 'api/biblioteca-leitura.php';
    const csrf = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const list = document.getElementById('annotations-list');
    const selected = document.getElementById('selected-text');
    let selection = null;

    const textNodes = () => {
        const walker = document.createTreeWalker(article, NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => node.parentElement?.closest('iframe,script,style') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT
        });
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        return nodes;
    };
    const selectionOffsets = (range) => {
        let total = 0;
        let start = null;
        let end = null;
        for (const node of textNodes()) {
            const length = node.textContent.length;
            if (node === range.startContainer) start = total + range.startOffset;
            if (node === range.endContainer) end = total + range.endOffset;
            total += length;
        }
        return start !== null && end !== null ? { start, end } : null;
    };
    const rangeFromOffsets = (start, end) => {
        let total = 0;
        let startNode = null, endNode = null, startOffset = 0, endOffset = 0;
        for (const node of textNodes()) {
            const length = node.textContent.length;
            if (startNode === null && start <= total + length) {
                startNode = node;
                startOffset = Math.max(0, start - total);
            }
            if (startNode !== null && end <= total + length) {
                endNode = node;
                endOffset = Math.max(0, end - total);
                break;
            }
            total += length;
        }
        if (!startNode || !endNode) return null;
        const range = document.createRange();
        range.setStart(startNode, Math.min(startOffset, startNode.textContent.length));
        range.setEnd(endNode, Math.min(endOffset, endNode.textContent.length));
        return range;
    };
    const renderAnnotations = (annotations) => {
        list.replaceChildren();
        if (CSS.highlights) {
            for (const name of CSS.highlights.keys()) {
                if (name.startsWith('library-')) CSS.highlights.delete(name);
            }
        }
        document.querySelectorAll('style[data-library-highlight]').forEach((style) => style.remove());
        for (const item of annotations) {
            if (item.annotation_type === 'highlight' && item.anchor_start !== null && item.anchor_end !== null && CSS.highlights && window.Highlight) {
                const range = rangeFromOffsets(Number(item.anchor_start), Number(item.anchor_end));
                if (range) {
                    const highlight = new Highlight(range);
                    CSS.highlights.set(`library-${item.id}`, highlight);
                    const colors = { yellow: '#fff1a8', green: '#bfe7a9', purple: '#e6c9f0' };
                    const style = document.createElement('style');
                    style.dataset.libraryHighlight = String(Number(item.id));
                    style.textContent = `::highlight(library-${Number(item.id)}) { background-color: ${colors[item.color] || colors.yellow}; color: inherit; }`;
                    document.head.append(style);
                }
            }
            const card = document.createElement('div');
            card.className = 'annotation';
            const heading = document.createElement('strong');
            heading.textContent = item.annotation_type === 'highlight' ? 'Trecho destacado' : 'Anotação';
            const body = document.createElement('p');
            body.textContent = item.note_text || item.anchor_text || '';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Excluir';
            remove.addEventListener('click', () => send({ action: 'annotation_delete', annotation_id: item.id }).then(loadState));
            card.append(heading, body, remove);
            list.append(card);
        }
    };
    const send = async (values) => {
        const data = new FormData();
        data.set('csrf', csrf);
        data.set('material_id', String(materialId));
        for (const [key, value] of Object.entries(values)) data.set(key, String(value));
        const response = await fetch(api, { method: 'POST', body: data, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Não foi possível salvar.');
        return result;
    };
    const loadState = async () => {
        const response = await fetch(`${api}?material_id=${materialId}`, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Não foi possível carregar suas anotações.');
        const result = await response.json();
        renderAnnotations(result.annotations || []);
        if (result.progress) {
            const progress = Number(result.progress.progress_percent) || 0;
            document.getElementById('progress-label').textContent = `${Math.round(progress)}% lido`;
            if (progress > 0 && article.scrollHeight > article.clientHeight) {
                window.scrollTo(0, Math.round((document.documentElement.scrollHeight - innerHeight) * progress / 100));
            }
        }
    };
    const saveSelection = async (type) => {
        if (!selection || selection.range.collapsed) {
            selected.textContent = 'Selecione um trecho do texto antes de continuar.';
            return;
        }
        const note = type === 'note' ? prompt('Sua anotação pessoal:') : '';
        if (type === 'note' && note === null) return;
        const values = {
            action: 'annotation_create',
            annotation_type: type,
            anchor_text: selection.text.slice(0, 1000),
            anchor_start: selection.offsets.start,
            anchor_end: selection.offsets.end,
            note_text: note || '',
            color: 'yellow'
        };
        await send(values);
        selected.textContent = type === 'highlight' ? 'Trecho destacado e salvo.' : 'Anotação salva.';
        window.getSelection()?.removeAllRanges();
        await loadState();
    };
    document.addEventListener('selectionchange', () => {
        const current = window.getSelection();
        if (!current || current.isCollapsed || !article.contains(current.anchorNode) || !article.contains(current.focusNode)) return;
        const range = current.getRangeAt(0).cloneRange();
        const offsets = selectionOffsets(range);
        if (!offsets) return;
        selection = { range, offsets, text: current.toString() };
        document.getElementById('selected-text').textContent = `Trecho selecionado: “${selection.text.slice(0, 120)}”`;
    });
    document.getElementById('font-size').addEventListener('change', (event) => { article.style.fontSize = event.target.value; });
    document.getElementById('line-spacing').addEventListener('change', (event) => { article.dataset.spacing = event.target.value; });
    document.getElementById('reading-mode').addEventListener('change', (event) => { article.dataset.mode = event.target.value; });
    document.getElementById('highlight-button').addEventListener('click', () => saveSelection('highlight'));
    document.getElementById('note-button').addEventListener('click', () => saveSelection('note'));
    document.getElementById('annotation-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const note = document.getElementById('annotation-text');
        try {
            await send({ action: 'annotation_create', annotation_type: 'note', note_text: note.value, color: 'yellow' });
            note.value = '';
            await loadState();
        } catch (error) { selected.textContent = error.message; }
    });

    const toc = document.getElementById('table-of-contents');
    const headings = article.querySelectorAll('h1,h2,h3');
    if (headings.length === 0) toc.textContent = 'Este material não possui sumário.';
    headings.forEach((heading, index) => {
        const id = `section-${index + 1}`;
        heading.id = id;
        const link = document.createElement('a');
        link.href = `#${id}`;
        link.textContent = heading.textContent;
        const item = document.createElement('div');
        item.append(link);
        toc.append(item);
    });

    let progressTimer;
    window.addEventListener('scroll', () => {
        clearTimeout(progressTimer);
        progressTimer = setTimeout(() => {
            const total = document.documentElement.scrollHeight - innerHeight;
            const percent = total > 0 ? Math.min(100, Math.max(0, window.scrollY / total * 100)) : 100;
            const visibleHeading = [...headings].reverse().find((heading) => heading.getBoundingClientRect().top < innerHeight * .4);
            send({ action: 'progress', progress_percent: percent.toFixed(2), position_anchor: visibleHeading?.id || '' })
                .then(() => { document.getElementById('progress-label').textContent = `${Math.round(percent)}% lido`; })
                .catch((error) => { selected.textContent = error.message; });
        }, 1200);
    }, { passive: true });
    loadState().catch((error) => { selected.textContent = error.message; });
})();
</script>
</body>
</html>
