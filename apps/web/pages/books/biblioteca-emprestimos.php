<?php
declare(strict_types=1);

require_once SA_PROJECT_ROOT . '/includes/auth.php';
require_once SA_PROJECT_ROOT . '/conexao.php';
if (isset($_SESSION['instituicao_usuario_id'])) {
    require_institution_auth();
} else {
    require_auth();
}
$referenceShell = true;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empréstimos da Biblioteca | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <link rel="stylesheet" href="assets/css/biblioteca-emprestimos.css">
</head>
<body class="reference-shell">
<?php require SA_PROJECT_ROOT . '/includes/header.php'; ?>
<main class="main lending-main">
    <header class="lending-heading">
        <div>
            <h1>Empréstimos da Biblioteca</h1>
            <p>Consulte exemplares e acompanhe solicitações com as instituições.</p>
        </div>
        <a class="btn btn-outline" href="biblioteca.php">Voltar à Biblioteca</a>
    </header>
    <p class="lending-status" data-lending-status role="status" aria-live="polite">Carregando empréstimos...</p>
    <section class="lending-section" data-institution-panel hidden aria-labelledby="lending-manage-title">
        <h2 id="lending-manage-title">Gerenciar exemplares</h2>
        <p class="lending-note">Só cadastre materiais institucionais aprovados, com direitos declarados. Para exemplares digitais, confirme a autorização legal e registre sua evidência.</p>
        <form class="lending-form" data-copy-form>
            <input type="hidden" name="action" value="create_copy">
            <label>Material aprovado
                <select name="material_id" required data-material-select>
                    <option value="">Selecione um material</option>
                </select>
            </label>
            <label>Código do exemplar
                <input name="copy_code" maxlength="100" required>
            </label>
            <label>Formato
                <select name="format" required data-copy-format>
                    <option value="physical">Físico</option>
                    <option value="digital">Digital autorizado</option>
                </select>
            </label>
            <label class="lending-wide">Regras de acesso e empréstimo
                <textarea name="access_rules" maxlength="2000" rows="3"></textarea>
            </label>
            <label class="lending-wide" data-authorization-evidence hidden>Evidência da autorização para distribuição digital
                <textarea name="authorization_evidence" maxlength="2000" rows="3"></textarea>
            </label>
            <label class="lending-confirmation lending-wide" data-authorization-confirmation hidden>
                <input type="checkbox" name="distribution_authorized" value="1">
                <span>Confirmo que a instituição possui autorização legal para disponibilizar este exemplar digitalmente.</span>
            </label>
            <button class="btn btn-primary" type="submit">Cadastrar exemplar</button>
        </form>
        <h2>Exemplares cadastrados</h2>
        <div class="lending-list" data-copy-list></div>
        <h2>Solicitações e empréstimos</h2>
        <div class="lending-list" data-institution-loan-list></div>
    </section>
    <section class="lending-section" data-reader-panel hidden aria-labelledby="lending-available-title">
        <h2 id="lending-available-title">Exemplares disponíveis</h2>
        <div class="lending-list" data-available-copy-list></div>
        <h2>Meus empréstimos</h2>
        <div class="lending-list" data-reader-loan-list></div>
    </section>
</main>
<script>
(() => {
    const api = 'api/biblioteca-emprestimos.php';
    const csrf = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const status = document.querySelector('[data-lending-status]');
    const institutionPanel = document.querySelector('[data-institution-panel]');
    const readerPanel = document.querySelector('[data-reader-panel]');
    const esc = (value) => String(value ?? '');
    const node = (tag, text, className = '') => {
        const element = document.createElement(tag);
        element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const post = async (data) => {
        data.set('csrf', csrf);
        const response = await fetch(api, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível atualizar o empréstimo.');
        return result;
    };
    const renderEmpty = (target, text) => target.replaceChildren(node('p', text, 'lending-empty'));
    const formatCopy = (copy) => copy.format === 'digital' ? 'Digital autorizado' : 'Físico';
    const statusLabel = (value) => ({
        requested: 'Solicitado',
        on_loan: 'Emprestado',
        active: 'Emprestado',
        overdue: 'Atrasado',
        returned: 'Devolvido',
        canceled: 'Cancelado'
    }[value] || 'Indisponível');

    const renderCopies = (copies, target, isInstitution) => {
        if (!copies.length) return renderEmpty(target, isInstitution ? 'Nenhum exemplar cadastrado.' : 'Nenhum exemplar disponível no momento.');
        target.replaceChildren(...copies.map((copy) => {
            const card = node('article', '', 'lending-item');
            card.append(node('h3', `${esc(copy.titulo)} · ${esc(copy.copy_code)}`));
            card.append(node('p', `${formatCopy(copy)}${copy.institution_name ? ` · ${esc(copy.institution_name)}` : ''}`));
            if (copy.autor) card.append(node('p', `Autor: ${esc(copy.autor)}`));
            if (copy.materia) card.append(node('p', `Disciplina: ${esc(copy.materia)}`));
            if (copy.access_rules) card.append(node('p', `Regras: ${esc(copy.access_rules)}`));
            if (isInstitution) {
                card.append(node('p', `Estado: ${statusLabel(copy.status)}`));
                if (copy.format === 'digital') {
                    card.append(node('p', copy.distribution_authorized ? 'Autorização digital registrada.' : 'Sem autorização digital registrada.'));
                    if (copy.authorization_evidence) card.append(node('p', `Evidência: ${esc(copy.authorization_evidence)}`));
                }
                if (['available', 'maintenance', 'unavailable'].includes(copy.status)) {
                    const form = document.createElement('form');
                    form.className = 'lending-action-form';
                    form.dataset.copyStatus = String(copy.id);
                    const select = document.createElement('select');
                    select.name = 'status';
                    select.setAttribute('aria-label', `Disponibilidade de ${esc(copy.copy_code)}`);
                    [
                        ['available', 'Disponível'],
                        ['maintenance', 'Em manutenção'],
                        ['unavailable', 'Indisponível']
                    ].forEach(([value, label]) => {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        option.selected = value === copy.status;
                        select.append(option);
                    });
                    const button = node('button', 'Atualizar disponibilidade', 'btn btn-outline');
                    button.type = 'submit';
                    form.append(select, button);
                    card.append(form);
                }
            } else {
                const form = document.createElement('form');
                form.className = 'lending-action-form';
                form.dataset.requestCopy = String(copy.id);
                const id = document.createElement('input');
                id.type = 'hidden';
                id.name = 'copy_id';
                id.value = String(copy.id);
                form.append(id);
                const button = node('button', 'Solicitar empréstimo', 'btn btn-primary');
                button.type = 'submit';
                form.append(button);
                card.append(form);
            }
            return card;
        }));
    };
    const renderLoans = (loans, target, isInstitution) => {
        if (!loans.length) return renderEmpty(target, 'Nenhuma solicitação ou empréstimo registrado.');
        target.replaceChildren(...loans.map((loan) => {
            const card = node('article', '', 'lending-item');
            card.append(node('h3', `${esc(loan.titulo)} · ${esc(loan.copy_code)}`));
            if (isInstitution) card.append(node('p', `${esc(loan.borrower_name)} · ${esc(loan.borrower_email)}`));
            else card.append(node('p', esc(loan.institution_name)));
            card.append(node('p', `${statusLabel(loan.status)}${loan.borrowed_at ? ` · Início: ${esc(loan.borrowed_at)}` : ''}${loan.due_at ? ` · Devolução prevista: ${esc(loan.due_at)}` : ''}`));
            if (isInstitution && loan.status === 'requested') {
                const form = document.createElement('form');
                form.className = 'lending-action-form';
                form.dataset.decideLoan = String(loan.id);
                const days = document.createElement('input');
                days.type = 'number';
                days.name = 'loan_days';
                days.min = '1';
                days.max = '90';
                days.value = '14';
                days.setAttribute('aria-label', 'Prazo do empréstimo em dias');
                form.append(days);
                const issue = node('button', 'Aprovar e emprestar', 'btn btn-primary');
                issue.type = 'submit';
                issue.value = 'issue';
                const reject = node('button', 'Recusar solicitação', 'btn btn-outline');
                reject.type = 'submit';
                reject.value = 'reject';
                form.append(issue, reject);
                card.append(form);
            } else if (isInstitution && ['active', 'overdue'].includes(loan.status)) {
                const form = document.createElement('form');
                form.className = 'lending-action-form';
                form.dataset.decideLoan = String(loan.id);
                const returned = node('button', 'Registrar devolução', 'btn btn-outline');
                returned.type = 'submit';
                returned.value = 'return';
                form.append(returned);
                card.append(form);
            }
            return card;
        }));
    };
    const load = async () => {
        const response = await fetch(api, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Não foi possível carregar os empréstimos.');
        if (data.mode === 'institution') {
            institutionPanel.hidden = false;
            const select = document.querySelector('[data-material-select]');
            data.materials.forEach((material) => {
                const option = document.createElement('option');
                option.value = String(material.id);
                option.textContent = `${material.titulo}${material.autor ? ` — ${material.autor}` : ''}`;
                select.append(option);
            });
            renderCopies(data.copies, document.querySelector('[data-copy-list]'), true);
            renderLoans(data.loans, document.querySelector('[data-institution-loan-list]'), true);
        } else {
            readerPanel.hidden = false;
            renderCopies(data.copies, document.querySelector('[data-available-copy-list]'), false);
            renderLoans(data.loans, document.querySelector('[data-reader-loan-list]'), false);
        }
        status.textContent = '';
    };
    document.querySelector('[data-copy-format]')?.addEventListener('change', (event) => {
        const digital = event.target.value === 'digital';
        document.querySelector('[data-authorization-evidence]').hidden = !digital;
        document.querySelector('[data-authorization-confirmation]').hidden = !digital;
        document.querySelector('[data-authorization-confirmation] input').required = digital;
        document.querySelector('[data-authorization-evidence] textarea').required = digital;
    });
    document.querySelector('[data-copy-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const result = await post(new FormData(event.currentTarget));
            status.textContent = result.message;
            await load();
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Não foi possível cadastrar o exemplar.';
            status.setAttribute('role', 'alert');
        }
    });
    document.addEventListener('submit', async (event) => {
        const requestForm = event.target.closest('[data-request-copy]');
        const decisionForm = event.target.closest('[data-decide-loan]');
        const copyStatusForm = event.target.closest('[data-copy-status]');
        if (!requestForm && !decisionForm && !copyStatusForm) return;
        event.preventDefault();
        const data = new FormData(event.target);
        if (requestForm) data.set('action', 'request');
        if (copyStatusForm) {
            data.set('action', 'availability');
            data.set('copy_id', copyStatusForm.dataset.copyStatus);
        }
        if (decisionForm) {
            data.set('action', 'decide');
            data.set('loan_id', decisionForm.dataset.decideLoan);
            data.set('decision', event.submitter?.value || 'issue');
        }
        try {
            const result = await post(data);
            status.textContent = result.message;
            status.setAttribute('role', 'status');
            await load();
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Não foi possível atualizar o empréstimo.';
            status.setAttribute('role', 'alert');
        }
    });
    load().catch((error) => {
        status.textContent = error instanceof Error ? error.message : 'Não foi possível carregar os empréstimos.';
        status.setAttribute('role', 'alert');
    });
})();
</script>
</body>
</html>
