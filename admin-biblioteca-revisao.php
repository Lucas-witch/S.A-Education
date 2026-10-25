<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/conexao.php';
require_auth();
require_admin_permission();

$query = $pdo->query(
    'SELECT id, titulo, autor, tipo, categoria_material, materia, fonte_origem,
            referencia_bibliografica, direitos_status, direitos_evidencia,
            revisao_status, owner_user_id, instituicao_id
     FROM biblioteca WHERE revisao_status = "pending_review"
     ORDER BY atualizado_em ASC, id ASC LIMIT 100'
);
$pendingMaterials = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Revisão da Biblioteca | S.A Education</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/reference-shell.css">
    <link rel="stylesheet" href="assets/css/catalog.css">
    <style>
        .library-review { max-width: 1100px; margin: 2rem auto; padding: 1rem; }
        .library-review-item { margin: 1rem 0; padding: 1rem; background: #fff; border-radius: .75rem; }
        .library-review-item form { display: grid; gap: .75rem; margin-top: 1rem; }
        .library-review-item textarea { width: 100%; min-height: 5rem; }
        .library-review-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
    </style>
</head>
<body class="reference-shell">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="main library-review">
    <h1>Revisão da Biblioteca</h1>
    <p>A aprovação exige verificação humana de autoria, fonte e evidências de distribuição. Marcar domínio público não é prova por si só.</p>
    <p id="review-status" role="status" aria-live="polite"></p>
    <?php if ($pendingMaterials === []): ?>
        <p>Nenhum material aguardando revisão.</p>
    <?php else: ?>
        <?php foreach ($pendingMaterials as $material): ?>
            <article class="library-review-item">
                <h2><?= esc((string) $material['titulo']) ?></h2>
                <p>
                    Autor: <?= esc((string) ($material['autor'] ?? 'Não informado')) ?> ·
                    Categoria: <?= esc((string) ($material['categoria_material'] ?: $material['tipo'])) ?> ·
                    Disciplina: <?= esc((string) ($material['materia'] ?? 'Não informada')) ?>
                </p>
                <p>Direitos declarados: <?= esc((string) $material['direitos_status']) ?></p>
                <p>Fonte: <?= esc((string) ($material['fonte_origem'] ?? 'Não informada')) ?></p>
                <p>Referência: <?= esc((string) ($material['referencia_bibliografica'] ?? 'Não informada')) ?></p>
                <p>Evidência informada: <?= esc((string) ($material['direitos_evidencia'] ?? 'Não informada')) ?></p>
                <p><a href="biblioteca-leitor.php?id=<?= (int) $material['id'] ?>">Ver conteúdo no leitor autenticado</a></p>
                <form class="review-form" action="api/biblioteca-revisao.php" method="post">
                    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>">
                    <label>Motivo da recusa ou arquivamento (obrigatório nessas decisões)
                        <textarea name="reason" maxlength="2000"></textarea>
                    </label>
                    <div class="library-review-actions">
                        <button class="btn btn-primary" name="decision" value="approve">Aprovar publicação</button>
                        <button class="btn btn-outline" name="decision" value="reject">Recusar e devolver ao autor</button>
                        <button class="btn btn-outline" name="decision" value="archive">Arquivar</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('.review-form').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const submitter = event.submitter;
        if (!(submitter instanceof HTMLButtonElement)) return;
        const data = new FormData(form);
        data.set('decision', submitter.value);
        const status = document.getElementById('review-status');
        submitter.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Não foi possível registrar a revisão.');
            status.textContent = 'Revisão registrada.';
            form.closest('.library-review-item').remove();
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Não foi possível registrar a revisão.';
            submitter.disabled = false;
        }
    });
});
</script>
</body>
</html>
