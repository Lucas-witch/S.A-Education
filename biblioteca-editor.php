<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/includes/digital_library.php';
$institutionOwner = isset($_SESSION['instituicao_usuario_id']);
if ($institutionOwner) {
    require_institution_auth();
    if (($_SESSION['instituicao_validada'] ?? false) !== true || ($_SESSION['instituicao_assinatura_ativa'] ?? false) !== true) {
        http_response_code(403);
        exit('A instituição precisa estar validada e com assinatura ativa.');
    }
    $userId = (int) $_SESSION['instituicao_usuario_id'];
} else {
    require_auth();
    $userId = (int) $_SESSION['usuario_id'];
    $permissions = resolve_account_permissions($pdo, $userId);
}

$institutionId = null;
if ($institutionOwner) {
    $institutionQuery = $pdo->prepare('SELECT id FROM instituicoes WHERE usuario_id = :user_id LIMIT 1');
    $institutionQuery->execute(['user_id' => $userId]);
    $institutionId = (int) $institutionQuery->fetchColumn();
}
$draftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$draft = null;
if ($draftId !== null && $draftId !== false) {
    $draftQuery = $pdo->prepare(
        'SELECT * FROM biblioteca
         WHERE id = :id AND owner_user_id = :owner_user_id
           AND ((instituicao_id = :institution_id) OR (instituicao_id IS NULL AND :institution_null IS NULL))
           AND revisao_status IN ("draft", "rejected")
         LIMIT 1'
    );
    $draftQuery->execute([
        'id' => $draftId,
        'owner_user_id' => $userId,
        'institution_id' => $institutionId,
        'institution_null' => $institutionId,
    ]);
    $draft = $draftQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($draft === null) {
        http_response_code(404);
        exit('Rascunho não encontrado.');
    }
}
$field = static fn (string $name): string => is_array($draft) ? (string) ($draft[$name] ?? '') : '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor da Biblioteca | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <link rel="stylesheet" href="assets/css/catalog.css">
    <link rel="stylesheet" href="assets/css/biblioteca-editor.css">
</head>
<body class="reference-shell">
<?php if (!$institutionOwner) { require __DIR__ . '/includes/header.php'; } ?>
<main class="main">
    <section class="library-editor">
        <h1><?= $draft !== null ? 'Editar rascunho da Biblioteca' : 'Criar material educacional' ?></h1>
        <p>Materiais enviados passam por revisão. Não envie obras de terceiros sem autorização adequada. A plataforma não declara automaticamente nenhum conteúdo como domínio público.</p>
        <p class="save-state" id="save-state" role="status" aria-live="polite">Alterações ainda não salvas.</p>
        <form id="library-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $draft !== null ? (int) $draft['id'] : '' ?>">
            <div class="metadata-grid">
                <label>Título
                    <input name="titulo" maxlength="180" value="<?= esc($field('titulo')) ?>" required>
                </label>
                <label>Categoria
                    <select name="tipo" required>
                        <?php foreach (['livro' => 'Livro didático/livro', 'apostila' => 'Apostila', 'pdf' => 'PDF', 'artigo' => 'Artigo', 'material' => 'Outro material / resumo'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= $field('tipo') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Autor(es)
                    <input name="autor" maxlength="500" value="<?= esc($field('autor')) ?>">
                </label>
                <label>Disciplina
                    <input name="materia" maxlength="80" value="<?= esc($field('materia')) ?>">
                </label>
                <label>Área do conhecimento
                    <input name="area_conhecimento" maxlength="120" value="<?= esc($field('area_conhecimento')) ?>">
                </label>
                <label>Etapa de ensino
                    <input name="etapa_ensino" maxlength="80" value="<?= esc($field('etapa_ensino')) ?>">
                </label>
                <label>Idioma
                    <input name="idioma" maxlength="40" value="<?= esc($field('idioma')) ?>" placeholder="Ex.: Português">
                </label>
                <label>Categoria específica
                    <input name="categoria_material" maxlength="80" value="<?= esc($field('categoria_material')) ?>" placeholder="Ex.: resumo, guia, ficha">
                </label>
                <label>Palavras-chave
                    <input name="palavras_chave" maxlength="1000" value="<?= esc($field('palavras_chave')) ?>">
                </label>
                <label>Capa (URL HTTPS autorizada)
                    <input name="capa_url" type="url" maxlength="255" value="<?= esc($field('capa_url')) ?>">
                </label>
                <label>Arquivo educacional autorizado (opcional, até 20 MB)
                    <input name="arquivo" type="file" accept=".pdf,.epub,.docx,.pptx,.xlsx,.odt,.odp,.ods,.txt,.rtf,.csv,application/pdf,application/epub+zip,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation">
                    <small>PDF, EPUB, DOCX, PPTX, XLSX, ODT, ODP, ODS, TXT, RTF e CSV. Vídeos e imagens são publicados no Feed.</small>
                </label>
            </div>
            <label>Descrição ou resumo
                <textarea name="descricao" maxlength="5000" rows="4"><?= esc($field('descricao')) ?></textarea>
            </label>
            <fieldset>
                <legend>Texto educacional</legend>
                <div class="editor-toolbar" role="toolbar" aria-label="Formatação do texto">
                    <button type="button" data-command="undo" aria-label="Desfazer">Desfazer</button>
                    <button type="button" data-command="redo" aria-label="Refazer">Refazer</button>
                    <button type="button" data-command="formatBlock" data-value="h1">Título</button>
                    <button type="button" data-command="formatBlock" data-value="h2">Subtítulo</button>
                    <button type="button" data-command="formatBlock" data-value="h3">Seção</button>
                    <button type="button" data-command="formatBlock" data-value="p">Parágrafo</button>
                    <button type="button" data-command="bold"><strong>Negrito</strong></button>
                    <button type="button" data-command="italic"><em>Itálico</em></button>
                    <button type="button" data-command="underline"><u>Sublinhado</u></button>
                    <button type="button" data-command="strikeThrough"><s>Riscado</s></button>
                    <button type="button" data-command="insertUnorderedList">Lista</button>
                    <button type="button" data-command="insertOrderedList">Lista numerada</button>
                    <button type="button" data-command="formatBlock" data-value="blockquote">Citação</button>
                    <button type="button" data-command="justifyLeft" aria-label="Alinhar à esquerda">Esquerda</button>
                    <button type="button" data-command="justifyCenter" aria-label="Centralizar">Centralizar</button>
                    <button type="button" data-command="justifyRight" aria-label="Alinhar à direita">Direita</button>
                    <button type="button" data-command="justifyFull" aria-label="Justificar">Justificar</button>
                    <label class="editor-toolbar-select">Tamanho
                        <select id="editor-font-size" aria-label="Tamanho da fonte">
                            <option value="">Fonte</option><option value="12">12</option><option value="14">14</option>
                            <option value="16">16</option><option value="18">18</option><option value="20">20</option>
                            <option value="24">24</option>
                        </select>
                    </label>
                    <label class="editor-toolbar-select">Cor
                        <input type="color" id="editor-text-color" value="#25233b" aria-label="Cor do texto">
                    </label>
                    <label class="editor-toolbar-select">Realce
                        <input type="color" id="editor-highlight-color" value="#fff1a8" aria-label="Cor de realce">
                    </label>
                    <button type="button" id="insert-link">Link</button>
                    <button type="button" id="insert-image">Imagem HTTPS</button>
                    <button type="button" id="insert-table">Tabela</button>
                    <button type="button" data-command="removeFormat">Limpar formatação</button>
                </div>
                <div id="rich-editor" class="rich-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Texto educacional"><?= $draft !== null ? library_sanitize_rich_text((string) $draft['texto_html']) : '' ?></div>
                <textarea hidden name="texto_html" id="texto-html"><?= esc($field('texto_html')) ?></textarea>
            </fieldset>
            <div class="metadata-grid">
                <label>Fonte de origem
                    <input name="fonte_origem" maxlength="500" value="<?= esc($field('fonte_origem')) ?>" placeholder="URL, instituição ou forma de obtenção">
                </label>
                <label>Referência bibliográfica
                    <textarea name="referencia_bibliografica" maxlength="3000" rows="3"><?= esc($field('referencia_bibliografica')) ?></textarea>
                </label>
                <label>Situação de direitos
                    <select name="direitos_status" required>
                        <?php foreach (['unknown' => 'Ainda não verificado', 'original' => 'Conteúdo de minha autoria', 'licensed' => 'Licença/autorização de distribuição', 'public_domain' => 'Domínio público (exige evidência)', 'institution_authorized' => 'Autorizado pela instituição'] as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= $field('direitos_status') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Evidência da licença/autorização (obrigatória para direitos de terceiros)
                    <textarea name="direitos_evidencia" maxlength="2000" rows="3"><?= esc($field('direitos_evidencia')) ?></textarea>
                </label>
            </div>
            <label>
                <span><input type="checkbox" name="direitos_declarados" value="1" <?= (int) ($draft['direitos_declarados'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Declaro que sou autor ou que possuo direitos/autorização adequados para distribuir este conteúdo.</span>
            </label>
            <div class="library-editor-actions">
                <button class="btn btn-secondary" type="button" id="preview-button">Pré-visualizar</button>
                <button class="btn btn-outline" type="button" id="save-draft">Salvar rascunho</button>
                <button class="btn btn-primary" type="submit">Enviar para revisão</button>
                <a class="btn btn-outline" href="biblioteca.php">Voltar à Biblioteca</a>
            </div>
            <iframe class="preview-pane" id="preview-pane" title="Pré-visualização do material" sandbox referrerpolicy="no-referrer" hidden></iframe>
        </form>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('library-form');
    const editor = document.getElementById('rich-editor');
    const htmlField = document.getElementById('texto-html');
    const state = document.getElementById('save-state');
    const preview = document.getElementById('preview-pane');
    const draftId = form.elements.id;
    let timer;
    let saving = false;
    let dirty = false;
    let savedRange = null;

    const setState = (message, error = false) => {
        state.textContent = message;
        state.setAttribute('role', error ? 'alert' : 'status');
    };
    const syncHtml = () => { htmlField.value = editor.innerHTML; };
    const rememberSelection = () => {
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
            savedRange = selection.getRangeAt(0).cloneRange();
        }
    };
    const restoreSelection = () => {
        editor.focus();
        if (savedRange) {
            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(savedRange);
        }
    };
    const save = async (action) => {
        if (saving) return false;
        syncHtml();
        saving = true;
        setState(action === 'submit_review' ? 'Enviando para revisão...' : 'Salvando...');
        const data = new FormData(form);
        data.set('action', action);
        try {
            const response = await fetch('api/biblioteca-editor.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json().catch(() => null);
            if (!result) throw new Error(response.status === 413
                ? 'O arquivo excede o limite configurado pelo servidor.'
                : 'O servidor não retornou uma resposta válida. Confira os limites de envio e tente novamente.');
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar.');
            draftId.value = String(result.id);
            htmlField.value = result.html;
            form.querySelector('input[type="file"][name="arquivo"]').value = '';
            dirty = false;
            setState(result.message);
            if (action === 'submit_review') location.href = 'biblioteca.php';
            return true;
        } catch (error) {
            setState(error.message || 'Não foi possível salvar. Verifique a conexão.', true);
            return false;
        } finally {
            saving = false;
        }
    };
    document.addEventListener('selectionchange', rememberSelection);
    editor.addEventListener('keyup', rememberSelection);
    editor.addEventListener('mouseup', rememberSelection);
    document.querySelectorAll('.editor-toolbar button').forEach((button) => {
        button.addEventListener('mousedown', event => event.preventDefault());
    });
    document.querySelectorAll('.editor-toolbar [data-command]').forEach((button) => {
        button.addEventListener('click', () => {
            restoreSelection();
            document.execCommand(button.dataset.command, false, button.dataset.value || null);
            rememberSelection();
            syncHtml();
            dirty = true;
        });
    });
    document.getElementById('editor-font-size').addEventListener('change', event => {
        const size = event.currentTarget.value;
        if (!size) return;
        restoreSelection();
        document.execCommand('styleWithCSS', false, false);
        document.execCommand('fontSize', false, '7');
        editor.querySelectorAll('font[size="7"]').forEach(font => {
            const span = document.createElement('span');
            span.style.fontSize = `${size}px`;
            span.innerHTML = font.innerHTML;
            font.replaceWith(span);
        });
        syncHtml();
        rememberSelection();
        dirty = true;
        event.currentTarget.value = '';
    });
    document.getElementById('editor-text-color').addEventListener('input', event => {
        restoreSelection();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand('foreColor', false, event.currentTarget.value);
        syncHtml();
        rememberSelection();
        dirty = true;
    });
    document.getElementById('editor-highlight-color').addEventListener('input', event => {
        restoreSelection();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand('hiliteColor', false, event.currentTarget.value);
        syncHtml();
        rememberSelection();
        dirty = true;
    });
    document.getElementById('insert-link').addEventListener('click', () => {
        const url = prompt('Informe um link HTTPS autorizado:');
        if (!url || !/^https:\/\/[^\\s]+$/i.test(url)) return;
        restoreSelection();
        document.execCommand('createLink', false, url);
        syncHtml();
        rememberSelection();
        dirty = true;
    });
    document.getElementById('insert-image').addEventListener('click', () => {
        const source = prompt('Informe a URL HTTPS de uma imagem autorizada:');
        if (!source) return;
        let imageUrl;
        try {
            imageUrl = new URL(source);
        } catch {
            return;
        }
        if (imageUrl.protocol !== 'https:' || imageUrl.username || imageUrl.password) return;
        const alt = prompt('Descreva a imagem para leitores de tela:');
        if (!alt || alt.trim() === '') return;
        const image = document.createElement('img');
        image.src = imageUrl.href;
        image.alt = alt.trim().slice(0, 300);
        restoreSelection();
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(image);
        } else {
            editor.append(image);
        }
        syncHtml();
        rememberSelection();
        dirty = true;
    });
    document.getElementById('insert-table').addEventListener('click', () => {
        const table = document.createElement('table');
        const body = document.createElement('tbody');
        for (let rowIndex = 0; rowIndex < 3; rowIndex += 1) {
            const row = document.createElement('tr');
            for (let columnIndex = 0; columnIndex < 2; columnIndex += 1) {
                const cell = document.createElement(rowIndex === 0 ? 'th' : 'td');
                cell.textContent = rowIndex === 0 ? `Coluna ${columnIndex + 1}` : '';
                row.append(cell);
            }
            body.append(row);
        }
        table.append(body);
        restoreSelection();
        document.execCommand('insertHTML', false, table.outerHTML);
        syncHtml();
        rememberSelection();
        dirty = true;
    });
    editor.addEventListener('input', () => {
        syncHtml();
        rememberSelection();
        dirty = true;
        setState('Alterações pendentes...');
        clearTimeout(timer);
        timer = setTimeout(() => { if (dirty) save('save_draft'); }, 1200);
    });
    form.querySelectorAll('input, select, textarea').forEach((field) => {
        field.addEventListener('input', () => {
            if (field.type === 'hidden' || field.name === 'csrf') return;
            dirty = true;
            setState('Alterações pendentes...');
            clearTimeout(timer);
            timer = setTimeout(() => { if (dirty) save('save_draft'); }, field.type === 'file' ? 100 : 1200);
        });
        field.addEventListener('change', () => {
            if (field.type === 'file') {
                if (field.files?.length) {
                    dirty = true;
                    setState(`Arquivo selecionado: ${field.files[0].name}. Salvando rascunho...`);
                    clearTimeout(timer);
                    timer = setTimeout(() => { if (dirty) save('save_draft'); }, 100);
                }
                return;
            }
            dirty = true;
            setState('Alterações pendentes...');
            clearTimeout(timer);
            timer = setTimeout(() => { if (dirty) save('save_draft'); }, 1200);
        });
    });
    document.getElementById('save-draft').addEventListener('click', () => {
        clearTimeout(timer);
        save('save_draft');
    });
    document.getElementById('preview-button').addEventListener('click', async () => {
        clearTimeout(timer);
        if (dirty && !(await save('save_draft'))) return;
        const content = `<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="referrer" content="no-referrer"><style>body{font:18px/1.7 Georgia,serif;max-width:760px;margin:2rem auto;padding:0 1rem}img{max-width:100%;height:auto}blockquote{border-left:4px solid #4c2365;padding-left:1rem}</style><body>${htmlField.value}</body></html>`;
        preview.srcdoc = content;
        preview.hidden = false;
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearTimeout(timer);
        await save('submit_review');
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
})();
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
