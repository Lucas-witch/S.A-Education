<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
if (isset($_SESSION['instituicao_usuario_id'])) {
    require_institution_auth();
} else {
    require_auth();
    if (!in_array(current_user_perfil(), ['estudante', 'professor'], true)) {
        http_response_code(403);
        exit('Perfil sem acesso ao portal acadêmico.');
    }
    if (current_user_perfil() === 'professor' && ($_SESSION['usuario_aprovado'] ?? false) !== true) {
        http_response_code(403);
        exit('O perfil docente precisa estar aprovado.');
    }
}

$referenceShell = true;
$csrfToken = csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= esc($csrfToken) ?>">
    <title>Portal acadêmico | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <link rel="stylesheet" href="assets/css/institution-portal.css">
</head>
<body class="reference-shell">
<?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main institutional-portal" data-portal>
    <header class="portal-heading">
        <div>
            <p class="portal-eyebrow">S.A Education · Ambiente institucional</p>
            <h1 data-institution-name>Portal acadêmico</h1>
            <p data-institution-type>Organização acadêmica e acompanhamento de aprendizagem.</p>
        </div>
        <a class="btn btn-outline" href="instituicao-dashboard.php" data-manager-back hidden>Gestão institucional</a>
        <a class="btn btn-outline" href="perfil-dashboard.php" data-member-back hidden>Meu perfil</a>
    </header>

    <div class="portal-alert" data-portal-status role="status" aria-live="polite">Carregando o ambiente acadêmico...</div>
    <label class="portal-institution-picker" data-institution-picker hidden>
        <span>Instituição</span>
        <select data-institution-select aria-label="Selecionar instituição"></select>
    </label>
    <section class="portal-status-card" data-plan-status hidden aria-labelledby="portal-status-title">
        <h2 id="portal-status-title">Situação institucional</h2>
        <p data-plan-message></p>
        <p class="portal-muted">A validação e a assinatura são administradas pelas regras e pelo catálogo de planos existentes. O portal não confirma pagamentos nem altera valores.</p>
    </section>

    <section class="portal-section" data-manager-section hidden aria-labelledby="portal-structure-title">
        <div class="portal-section-heading">
            <div>
                <p class="portal-eyebrow">Gestão acadêmica</p>
                <h2 id="portal-structure-title">Estrutura da instituição</h2>
            </div>
            <span class="portal-role-badge">Gestor</span>
        </div>
        <p class="portal-muted">Organize unidades, cursos, períodos, turmas e disciplinas. As categorias e os campos são flexíveis para escolas, universidades e formação continuada.</p>
        <div class="portal-forms-grid">
            <details class="portal-form-card">
                <summary>Adicionar campus ou unidade</summary>
                <form data-portal-action="create_unit">
                    <label>Tipo
                        <select name="unit_type" required>
                            <option value="campus">Campus ou unidade</option>
                            <option value="faculty">Faculdade ou escola acadêmica</option>
                            <option value="department">Departamento</option>
                            <option value="area">Área</option>
                        </select>
                    </label>
                    <label>Unidade superior (opcional)<select name="parent_unit_id" data-options="units"></select></label>
                    <label>Nome<input name="name" maxlength="180" required></label>
                    <label>Código interno (opcional)<input name="code" maxlength="60"></label>
                    <button class="btn btn-primary" type="submit">Salvar unidade</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Adicionar curso ou programa</summary>
                <form data-portal-action="create_program">
                    <label>Unidade acadêmica (opcional)<select name="academic_unit_id" data-options="units"></select></label>
                    <label>Nome<input name="name" maxlength="180" required></label>
                    <label>Código interno (opcional)<input name="code" maxlength="60"></label>
                    <label>Etapa ou nível
                        <select name="degree_level" required>
                            <option value="fundamental">Ensino Fundamental</option>
                            <option value="medio">Ensino Médio</option>
                            <option value="undergraduate">Graduação</option>
                            <option value="graduate">Pós-graduação</option>
                            <option value="continuing">Formação continuada</option>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">Salvar curso</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Criar período letivo</summary>
                <form data-portal-action="create_term">
                    <label>Nome (ex.: 1º semestre)<input name="name" maxlength="100" required></label>
                    <label>Ano<input name="year" type="number" min="2000" max="2200" required></label>
                    <label>Código ou período (opcional)<input name="period_code" maxlength="40"></label>
                    <label>Início (opcional)<input name="starts_on" type="date"></label>
                    <label>Fim (opcional)<input name="ends_on" type="date"></label>
                    <button class="btn btn-primary" type="submit">Salvar período</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Criar turma</summary>
                <form data-portal-action="create_class">
                    <label>Curso/programa<select name="program_id" data-options="programs" required></select></label>
                    <label>Período letivo<select name="term_id" data-options="terms" required></select></label>
                    <label>Nome da turma<input name="name" maxlength="150" required></label>
                    <label>Etapa (opcional)<input name="stage_label" maxlength="100"></label>
                    <label>Série/semestre (opcional)<input name="grade_label" maxlength="100"></label>
                    <label>Seção (opcional)<input name="section" maxlength="60"></label>
                    <button class="btn btn-primary" type="submit">Salvar turma</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Adicionar disciplina</summary>
                <form data-portal-action="create_subject">
                    <label>Nome<input name="name" maxlength="150" required></label>
                    <label>Área do conhecimento (opcional)<input name="knowledge_area" maxlength="120"></label>
                    <button class="btn btn-primary" type="submit">Salvar disciplina</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Associar disciplina e professor</summary>
                <form data-portal-action="assign_subject">
                    <label>Turma<select name="class_id" data-options="classes" required></select></label>
                    <label>Disciplina<select name="subject_id" data-options="subjects" required></select></label>
                    <label>Professor aprovado e vinculado<select name="teacher_user_id" data-options="teachers"></select></label>
                    <button class="btn btn-primary" type="submit">Associar disciplina</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Matricular estudante em turma</summary>
                <form data-portal-action="enroll_student">
                    <label>Turma<select name="class_id" data-options="classes" required></select></label>
                    <label>Estudante vinculado<select name="student_user_id" data-options="students" required></select></label>
                    <button class="btn btn-primary" type="submit">Matricular</button>
                </form>
            </details>
        </div>
        <div class="portal-data-grid">
            <section><h3>Unidades</h3><div data-list="units"></div></section>
            <section><h3>Cursos e programas</h3><div data-list="programs"></div></section>
            <section><h3>Períodos</h3><div data-list="terms"></div></section>
            <section><h3>Turmas</h3><div data-list="classes"></div></section>
            <section><h3>Disciplinas</h3><div data-list="subjects"></div></section>
        </div>
        <div class="portal-data-grid">
            <details class="portal-form-card">
                <summary>Criar avaliação ou atividade</summary>
                <form data-portal-action="create_assessment">
                    <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                    <label>Título<input name="title" maxlength="180" required></label>
                    <label>Tipo
                        <select name="assessment_type" required>
                            <option value="activity">Atividade</option><option value="assignment">Trabalho</option>
                            <option value="exam">Prova</option><option value="quiz">Quiz</option>
                        </select>
                    </label>
                    <label>Pontuação máxima<input name="max_score" type="number" min="0.01" max="10000" step="0.01" required></label>
                    <label>Abre em (opcional)<input name="opens_at" type="datetime-local"></label>
                    <label>Prazo (opcional)<input name="due_at" type="datetime-local"></label>
                    <label class="portal-wide">Instruções<textarea name="description" maxlength="5000" rows="3"></textarea></label>
                    <button class="btn btn-primary" type="submit">Salvar como rascunho</button>
                </form>
            </details>
            <details class="portal-form-card">
                <summary>Registrar ou atualizar uma nota</summary>
                <form data-portal-action="save_result">
                    <label>Avaliação publicada<select name="assessment_id" data-options="published_assessments" required></select></label>
                    <label>Estudante<select name="student_user_id" data-options="students" required></select></label>
                    <label>Nota<input name="score" type="number" min="0" step="0.01" required></label>
                    <label class="portal-wide">Devolutiva (opcional)<textarea name="feedback" maxlength="3000" rows="3"></textarea></label>
                    <button class="btn btn-primary" type="submit">Salvar nota como privada</button>
                </form>
            </details>
        </div>
        <div class="portal-data-grid">
            <section><h3>Avaliações</h3><div data-list="assessments"></div></section>
            <section><h3>Notas e resultados</h3><div data-list="results"></div></section>
        </div>
        <section class="portal-existing-content">
            <h3>Conteúdos já publicados pela instituição</h3>
            <p class="portal-muted">Associe publicações e materiais institucionais já existentes às disciplinas, sem duplicar cadastros ou arquivos.</p>
            <details class="portal-form-card">
                <summary>Publicar videoaula institucional</summary>
                <form data-portal-action="create_institution_lesson">
                    <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                    <label>Título<input name="title" maxlength="200" required></label>
                    <label>URL direta do vídeo<input name="video_url" type="url" maxlength="255" required></label>
                    <label>Etapa de ensino<input name="education_stage" maxlength="100"></label>
                    <label>Área do conhecimento<input name="knowledge_area" maxlength="120"></label>
                    <label>Disciplina<input name="subject" maxlength="120"></label>
                    <label>Tema<input name="topic" maxlength="180"></label>
                    <label>Subtema<input name="subtopic" maxlength="180"></label>
                    <label>Curso ou sequência<input name="course_title" maxlength="180"></label>
                    <label>Materiais complementares (URL)<input name="complementary_materials" type="url" maxlength="1000"></label>
                    <label>Exercícios relacionados (URL)<input name="related_exercises" type="url" maxlength="1000"></label>
                    <label>Legendas WebVTT (URL)<input name="captions_url" type="url" maxlength="1000"></label>
                    <label class="portal-wide">Apresentação<textarea name="summary" maxlength="1000" rows="3"></textarea></label>
                    <button class="btn btn-primary" type="submit">Publicar e disponibilizar</button>
                </form>
                <p class="portal-muted">A página do player exige autenticação institucional, mas uma URL direta hospedada por terceiros pode ser copiada e acessada fora da plataforma. Não use aqui conteúdo que dependa de proteção contra download ou compartilhamento.</p>
            </details>
            <details class="portal-form-card">
                <summary>Disponibilizar conteúdo em uma disciplina</summary>
                <form data-portal-action="attach_learning_resource">
                    <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                    <label>Conteúdo institucional<select name="resource_key" data-options="available_resources" required></select></label>
                    <button class="btn btn-primary" type="submit">Associar conteúdo</button>
                </form>
            </details>
            <h4>Conteúdos associados às disciplinas</h4>
            <div data-list="learning_resources"></div>
            <div class="portal-data-grid">
                <div><h4>Aulas e publicações</h4><div data-list="publications"></div></div>
                <div><h4>Biblioteca</h4><div data-list="library_materials"></div></div>
            </div>
        </section>
    </section>

    <section class="portal-section" data-teaching-section hidden aria-labelledby="portal-teaching-title">
        <div class="portal-section-heading">
            <div>
                <p class="portal-eyebrow">Docência</p>
                <h2 id="portal-teaching-title">Minhas turmas e avaliações</h2>
            </div>
            <span class="portal-role-badge">Professor</span>
        </div>
        <div class="portal-data-grid"><section><h3>Turmas atribuídas</h3><div data-list="classes"></div></section><section><h3>Disciplinas atribuídas</h3><div data-list="class_subjects"></div></section></div>
        <details class="portal-form-card portal-assessment-form">
            <summary>Criar avaliação ou atividade</summary>
            <form data-portal-action="create_assessment">
                <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                <label>Título<input name="title" maxlength="180" required></label>
                <label>Tipo
                    <select name="assessment_type" required>
                        <option value="activity">Atividade</option><option value="assignment">Trabalho</option>
                        <option value="exam">Prova</option><option value="quiz">Quiz</option>
                    </select>
                </label>
                <label>Pontuação máxima<input name="max_score" type="number" min="0.01" max="10000" step="0.01" required></label>
                <label>Abre em (opcional)<input name="opens_at" type="datetime-local"></label>
                <label>Prazo (opcional)<input name="due_at" type="datetime-local"></label>
                <label class="portal-wide">Instruções<textarea name="description" maxlength="5000" rows="3"></textarea></label>
                <button class="btn btn-primary" type="submit">Salvar como rascunho</button>
            </form>
        </details>
        <div class="portal-data-grid"><section><h3>Avaliações</h3><div data-list="assessments"></div></section><section><h3>Notas para registrar ou publicar</h3><div data-list="results"></div></section></div>
        <details class="portal-form-card portal-resource-form">
            <summary>Publicar videoaula institucional</summary>
            <form data-portal-action="create_institution_lesson">
                <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                <label>Título<input name="title" maxlength="200" required></label>
                <label>URL direta do vídeo<input name="video_url" type="url" maxlength="255" required></label>
                <label>Etapa de ensino<input name="education_stage" maxlength="100"></label>
                <label>Área do conhecimento<input name="knowledge_area" maxlength="120"></label>
                <label>Disciplina<input name="subject" maxlength="120"></label>
                <label>Tema<input name="topic" maxlength="180"></label>
                <label>Subtema<input name="subtopic" maxlength="180"></label>
                <label>Curso ou sequência<input name="course_title" maxlength="180"></label>
                <label>Materiais complementares (URL)<input name="complementary_materials" type="url" maxlength="1000"></label>
                <label>Exercícios relacionados (URL)<input name="related_exercises" type="url" maxlength="1000"></label>
                <label>Legendas WebVTT (URL)<input name="captions_url" type="url" maxlength="1000"></label>
                <label class="portal-wide">Apresentação<textarea name="summary" maxlength="1000" rows="3"></textarea></label>
                <button class="btn btn-primary" type="submit">Publicar e disponibilizar</button>
            </form>
            <p class="portal-muted">O player autentica a página; URLs diretas de terceiros podem ser compartilhadas fora da plataforma. Não use conteúdo que dependa de proteção contra download ou compartilhamento.</p>
        </details>
        <details class="portal-form-card portal-resource-form">
            <summary>Disponibilizar conteúdo em uma disciplina</summary>
            <form data-portal-action="attach_learning_resource">
                <label>Turma e disciplina<select name="class_subject_id" data-options="class_subjects" required></select></label>
                <label>Conteúdo institucional<select name="resource_key" data-options="available_resources" required></select></label>
                <button class="btn btn-primary" type="submit">Associar conteúdo</button>
            </form>
        </details>
        <section><h3>Conteúdos associados às minhas disciplinas</h3><div data-list="learning_resources"></div></section>
        <details class="portal-form-card portal-assessment-form">
            <summary>Registrar ou atualizar uma nota</summary>
            <form data-portal-action="save_result">
                <label>Avaliação publicada<select name="assessment_id" data-options="published_assessments" required></select></label>
                <label>Estudante da turma<select name="student_user_id" data-options="students" required></select></label>
                <label>Nota<input name="score" type="number" min="0" step="0.01" required></label>
                <label class="portal-wide">Devolutiva (opcional)<textarea name="feedback" maxlength="3000" rows="3"></textarea></label>
                <button class="btn btn-primary" type="submit">Salvar nota como privada</button>
            </form>
        </details>
    </section>

    <section class="portal-section" data-student-section hidden aria-labelledby="portal-student-title">
        <div class="portal-section-heading">
            <div><p class="portal-eyebrow">Vida acadêmica</p><h2 id="portal-student-title">Minhas turmas e resultados</h2></div>
            <span class="portal-role-badge">Estudante</span>
        </div>
        <div class="portal-data-grid"><section><h3>Turmas matriculadas</h3><div data-list="classes"></div></section><section><h3>Disciplinas</h3><div data-list="class_subjects"></div></section></div>
        <section><h3>Conteúdos das minhas disciplinas</h3><div data-list="learning_resources"></div></section>
        <section><h3>Avaliações publicadas</h3><div data-list="assessments"></div></section>
        <section><h3>Minhas notas publicadas</h3><div data-list="results"></div></section>
    </section>

    <section class="portal-section portal-live-integration" aria-labelledby="portal-live-title">
        <h2 id="portal-live-title">Aulas ao vivo</h2>
        <p data-live-integration-status>O portal está preparado para receber uma integração de videoconferência, mas nenhum provedor foi escolhido ou conectado. As aulas ao vivo não são simuladas nem iniciadas por esta página.</p>
    </section>
</main>
<script>
(() => {
    const api = 'api/instituicao-portal.php';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const status = document.querySelector('[data-portal-status]');
    const institutionName = document.querySelector('[data-institution-name]');
    const institutionType = document.querySelector('[data-institution-type]');
    const managerSection = document.querySelector('[data-manager-section]');
    const teachingSection = document.querySelector('[data-teaching-section]');
    const studentSection = document.querySelector('[data-student-section]');
    let currentInstitutionId = new URLSearchParams(location.search).get('institution_id') || '';
    let snapshot = null;

    const element = (tag, text, className = '') => {
        const node = document.createElement(tag);
        node.textContent = text ?? '';
        if (className) node.className = className;
        return node;
    };
    const labelForUnit = (value) => ({
        campus: 'Campus/unidade', faculty: 'Faculdade/escola', department: 'Departamento', area: 'Área',
        fundamental: 'Ensino Fundamental', medio: 'Ensino Médio', undergraduate: 'Graduação',
        graduate: 'Pós-graduação', continuing: 'Formação continuada',
        faculdade: 'Faculdade', universidade: 'Universidade', outro: 'Instituição'
    }[value] || value);
    const optionList = (name) => {
        if (name === 'units') return (snapshot.units || []).map((item) => [item.id, `${labelForUnit(item.type)} · ${item.name}`]);
        if (name === 'programs') return (snapshot.programs || []).map((item) => [item.id, `${item.name} · ${labelForUnit(item.degree_level)}`]);
        if (name === 'terms') return (snapshot.terms || []).map((item) => [item.id, `${item.name} · ${item.year}`]);
        if (name === 'classes') return (snapshot.classes || []).map((item) => [item.id, item.name]);
        if (name === 'subjects') return (snapshot.subjects || []).map((item) => [item.id, item.name]);
        if (name === 'class_subjects') return (snapshot.class_subjects || []).map((item) => [item.id, `${item.class_name} · ${item.subject_name}`]);
        if (name === 'published_assessments') return (snapshot.assessments || []).filter((item) => item.status !== 'draft').map((item) => [item.id, `${item.class_name} · ${item.subject_name} · ${item.title} (máx. ${item.max_score})`]);
        if (name === 'teachers') return [['', 'Sem atribuição'], ...(snapshot.members || []).filter((item) => item.member_type === 'teacher').map((item) => [item.user_id, `${item.nome_completo} · ${item.email}`])];
        if (name === 'students') return (snapshot.members || []).filter((item) => item.member_type === 'student').map((item) => [item.user_id, `${item.nome_completo} · ${item.email}`]);
        if (name === 'available_resources') return (snapshot.available_resources || []).map((item) => [`${item.resource_type}:${item.resource_id}`, `${item.title} · ${item.resource_kind}`]);
        return [];
    };
    const fillOptions = () => {
        document.querySelectorAll('[data-options]').forEach((select) => {
            const previous = select.value;
            const options = optionList(select.dataset.options);
            const placeholder = select.required ? 'Selecione' : 'Nenhuma';
            select.replaceChildren(new Option(placeholder, ''));
            options.forEach(([value, text]) => select.add(new Option(text, value)));
            if (options.some(([value]) => String(value) === previous)) select.value = previous;
        });
    };
    const renderList = (key) => {
        const target = document.querySelector(`[data-list="${key}"]`);
        if (!target) return;
        const values = snapshot[key] || [];
        target.replaceChildren();
        if (!values.length) {
            target.append(element('p', 'Nenhum registro disponível.', 'portal-muted'));
            return;
        }
        values.forEach((item) => {
            let title = '';
            let detail = '';
            if (key === 'units') {
                title = item.name; detail = `${labelForUnit(item.type)}${item.code ? ` · ${item.code}` : ''}`;
            } else if (key === 'programs') {
                title = item.name; detail = `${labelForUnit(item.degree_level)}${item.code ? ` · ${item.code}` : ''}`;
            } else if (key === 'terms') {
                title = `${item.name} · ${item.year}`; detail = `${item.starts_on || 'Data inicial não definida'} — ${item.ends_on || 'data final não definida'}`;
            } else if (key === 'classes') {
                title = item.name; detail = [item.stage_label, item.grade_label, item.section].filter(Boolean).join(' · ');
            } else if (key === 'subjects') {
                title = item.name; detail = item.knowledge_area || '';
            } else if (key === 'class_subjects') {
                title = `${item.class_name} · ${item.subject_name}`; detail = item.teacher_name ? `Professor: ${item.teacher_name}` : 'Professor ainda não atribuído';
            } else if (key === 'assessments') {
                title = item.title; detail = `${item.class_name} · ${item.subject_name} · ${item.status === 'draft' ? 'Rascunho' : `Nota máxima ${item.max_score}`}${item.due_at ? ` · Prazo ${item.due_at}` : ''}`;
            } else if (key === 'results') {
                title = `${item.assessment_title} · ${item.score}/${item.max_score}`;
                detail = `${item.class_name} · ${item.subject_name}${item.student_name ? ` · ${item.student_name}` : ''}${item.feedback ? ` · ${item.feedback}` : ''}`;
            } else if (key === 'publications') {
                title = item.titulo; detail = `${item.tipo}${item.subject ? ` · ${item.subject}` : ''}${item.course_title ? ` · ${item.course_title}` : ''} · ${item.status}`;
            } else if (key === 'library_materials') {
                title = item.titulo; detail = `${item.autor || 'Autoria não informada'}${item.materia ? ` · ${item.materia}` : ''} · ${item.revisao_status}`;
            } else if (key === 'learning_resources') {
                title = item.title;
                detail = `${item.class_name} · ${item.subject_name} · ${item.resource_type === 'publication' ? item.publication_kind : 'Biblioteca'}`;
            }
            const card = element('article', '', 'portal-record');
            card.append(element('strong', title));
            if (detail) card.append(element('span', detail));
            if (key === 'learning_resources') {
                const link = element('a', 'Abrir conteúdo', 'btn btn-outline');
                link.href = item.url;
                card.append(link);
                if (snapshot.context.role !== 'student') {
                    const button = element('button', 'Remover da disciplina', 'btn btn-outline');
                    button.type = 'button';
                    button.addEventListener('click', () => send({
                        action: 'detach_learning_resource',
                        class_subject_id: item.class_subject_id,
                        resource_type: item.resource_type,
                        resource_id: item.resource_id
                    }).catch((error) => showStatus(error instanceof Error ? error.message : 'Não foi possível remover o conteúdo.', true)));
                    card.append(button);
                }
            }
            if (key === 'assessments' && snapshot.context.role !== 'student' && item.status === 'draft') {
                const button = element('button', 'Publicar avaliação', 'btn btn-outline');
                button.type = 'button';
                button.addEventListener('click', () => send({ action: 'publish_assessment', assessment_id: item.id }));
                card.append(button);
            }
            if (key === 'results' && snapshot.context.role !== 'student' && !item.published_at) {
                const button = element('button', 'Publicar nota', 'btn btn-outline');
                button.type = 'button';
                button.addEventListener('click', () => send({ action: 'publish_result', result_id: item.id }));
                card.append(button);
            }
            target.append(card);
        });
    };
    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.setAttribute('role', isError ? 'alert' : 'status');
    };
    const send = async (values) => {
        const data = new FormData();
        Object.entries(values).forEach(([key, value]) => data.set(key, String(value)));
        data.set('csrf', csrf);
        data.set('institution_id', String(currentInstitutionId || snapshot?.context.institution_id || ''));
        const response = await fetch(api, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar os dados acadêmicos.');
        showStatus(result.message);
        await load();
    };
    const load = async () => {
        const url = new URL(api, location.href);
        if (currentInstitutionId) url.searchParams.set('institution_id', currentInstitutionId);
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Não foi possível carregar o portal acadêmico.');
        snapshot = data;
        currentInstitutionId = String(data.context.institution_id);
        institutionName.textContent = data.context.institution_name;
        institutionType.textContent = `${labelForUnit(data.context.institution_type)} · ${data.context.institution_nature === 'publica' ? 'Pública' : 'Particular'}`;
        const portalEnabled = !data.context.suspended
            && data.context.validation_status === 'validated'
            && data.context.subscription_active;
        managerSection.hidden = data.context.role !== 'manager' || !portalEnabled;
        teachingSection.hidden = data.context.role !== 'teacher' || !portalEnabled;
        studentSection.hidden = data.context.role !== 'student' || !portalEnabled;
        document.querySelector('[data-manager-back]').hidden = data.context.role !== 'manager';
        document.querySelector('[data-member-back]').hidden = data.context.role === 'manager';
        const message = data.context.suspended
            ? 'A administração suspendeu o acesso institucional. O ambiente acadêmico está bloqueado.'
            : data.context.validation_status === 'pending'
            ? 'A instituição aguarda validação administrativa. Os recursos acadêmicos estão bloqueados.'
            : data.context.validation_status === 'rejected'
                ? 'A validação da instituição foi rejeitada. O ambiente acadêmico está bloqueado; contate a administração.'
                : !data.context.subscription_active
            ? ({
                expired: 'A assinatura institucional expirou. O acesso acadêmico está bloqueado até a regularização.',
                pending: 'A assinatura aguarda confirmação de pagamento. O ambiente acadêmico está bloqueado.',
                canceled: 'A assinatura institucional foi cancelada e o acesso acadêmico está bloqueado.',
                none: 'A instituição ainda não possui uma assinatura ativa confirmada. O ambiente acadêmico está bloqueado.'
            }[data.context.subscription_state] || 'A assinatura institucional não está ativa. O ambiente acadêmico está bloqueado.')
            : 'Instituição validada e assinatura ativa.';
        document.querySelector('[data-plan-message]').textContent = message;
        document.querySelector('[data-plan-status]').hidden = data.context.role !== 'manager' || (!data.context.suspended && data.context.validation_status === 'validated' && data.context.subscription_active);
        const liveStatus = data.live_class_integration;
        document.querySelector('[data-live-integration-status]').textContent = liveStatus.configured
            ? liveStatus.message
            : 'O portal está preparado para receber uma integração de videoconferência, mas nenhum provedor foi escolhido ou conectado. As aulas ao vivo não são simuladas nem iniciadas por esta página.';
        const picker = document.querySelector('[data-institution-picker]');
        picker.hidden = data.available_institutions.length < 2;
        const pickerSelect = document.querySelector('[data-institution-select]');
        pickerSelect.replaceChildren(...data.available_institutions.map((item) => new Option(item.nome, item.id)));
        pickerSelect.value = currentInstitutionId;
        fillOptions();
        ['units', 'programs', 'terms', 'classes', 'subjects', 'class_subjects', 'assessments', 'results', 'publications', 'library_materials', 'learning_resources'].forEach(renderList);
        document.querySelectorAll('[data-portal-action="attach_learning_resource"]').forEach((form) => {
            form.closest('details').hidden = data.context.role === 'student'
                || !portalEnabled || (data.available_resources || []).length === 0;
        });
        document.querySelectorAll('[data-portal-action="create_assessment"], [data-portal-action="save_result"]').forEach((form) => {
            form.closest('details').hidden = data.context.role === 'student';
        });
        showStatus(data.context.role !== 'manager' && !portalEnabled ? message : '');
    };
    document.querySelector('[data-institution-select]').addEventListener('change', (event) => {
        currentInstitutionId = event.target.value;
        const url = new URL(location.href);
        url.searchParams.set('institution_id', currentInstitutionId);
        location.assign(url);
    });
    document.querySelectorAll('[data-portal-action]').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(form).entries());
        values.action = form.dataset.portalAction;
        if (values.action === 'attach_learning_resource') {
            const [resourceType, resourceId] = String(values.resource_key || '').split(':');
            values.resource_type = resourceType;
            values.resource_id = resourceId;
            delete values.resource_key;
        }
        try {
            await send(values);
            form.reset();
        } catch (error) {
            showStatus(error instanceof Error ? error.message : 'Não foi possível salvar os dados acadêmicos.', true);
        }
    }));
    load().catch((error) => showStatus(error instanceof Error ? error.message : 'Não foi possível carregar o portal acadêmico.', true));
})();
</script>
</body>
</html>
