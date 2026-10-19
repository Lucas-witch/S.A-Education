<?php
require __DIR__ . '/includes/auth.php';
require_auth();

$readFilter = static function (string $name, int $limit): string {
  $value = $_GET[$name] ?? '';
  return is_string($value) && mb_strlen($value) <= $limit ? trim($value) : '';
};
$searchValue = $readFilter('q', 120);
$assuntoValue = $readFilter('assunto', 80);
$materiaValue = $readFilter('materia', 80);
$tipoValue = strtolower($readFilter('tipo', 30));
$acessoValue = strtolower($readFilter('acesso', 12));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesquisa | S.A Education</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <style>
    body { background: #f7f3ee; }
    .search-page { max-width: 1180px; margin: 0 auto; padding: 20px 0 90px; }
    .search-panel {
      background: rgba(255,255,255,0.9);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: 0 18px 36px rgba(113,48,139,0.06);
      padding: 18px 16px 10px;
      margin-top: 10px;
    }
    .search-input-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      border: 1px solid var(--border);
      border-radius: 18px;
      background: #fff;
      padding: 0 10px 0 12px;
      min-height: 54px;
    }
    .search-input-wrap:focus-within {
      border-color: rgba(113,48,139,0.42);
      box-shadow: 0 0 0 4px rgba(113,48,139,0.06);
    }
    .search-token {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      border-radius: 8px;
      background: rgba(113,48,139,0.08);
      color: var(--purple);
      font-weight: 800;
      font-size: 16px;
      flex-shrink: 0;
    }
    .search-input-wrap input {
      flex: 1;
      min-width: 0;
      border: none;
      background: transparent;
      outline: none;
      font-size: 15px;
      color: var(--text);
      padding: 12px 4px;
    }
    .search-submit { min-width: 120px; }
    .filter-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
      margin-top: 14px;
    }
    .filter-field {
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #fff;
      color: var(--text);
      padding: 10px 12px;
      min-height: 44px;
    }
    .filter-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      min-height: 26px;
      margin-top: 12px;
    }
    .filter-chip {
      border: 1px solid rgba(113,48,139,0.12);
      background: rgba(113,48,139,0.06);
      color: var(--purple);
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
    }
    .results-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin: 24px 0 16px;
    }
    .results-head h2 { margin: 0; font-size: 20px; }
    .results-meta { color: var(--muted); font-size: 13px; }
    .results-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }
    .result-card {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: var(--shadow);
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      min-height: 200px;
    }
    .result-topline {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-size: 11px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .result-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .result-badge-public {
      background: rgba(113,48,139,0.06);
      color: var(--purple);
      border: 1px solid rgba(113,48,139,0.12);
    }
    .result-badge-premium {
      background: rgba(113,48,139,0.08);
      color: var(--purple);
      border: 1px solid rgba(113,48,139,0.12);
    }
    .result-badge-private {
      background: rgba(113,48,139,0.08);
      color: var(--purple);
      border: 1px solid rgba(113,48,139,0.12);
    }
    .result-card h3 {
      margin: 0;
      font-size: 20px;
      line-height: 1.3;
    }
    .result-card p { margin: 0; color: var(--muted); line-height: 1.65; }
    .result-details {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      font-size: 12px;
      color: var(--muted);
      margin-top: auto;
    }
    .result-detail strong { display: block; color: var(--text); margin-bottom: 4px; }
    .result-price {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: var(--text);
      font-size: 13px;
    }
    .search-status {
      min-height: 24px;
      margin-top: 12px;
      color: var(--muted);
      font-size: 13px;
    }
    .search-state-empty,
    .search-state-error,
    .search-state-loading {
      background: rgba(255,255,255,0.8);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 18px 20px;
      color: var(--muted);
    }
    .search-state-error { border-color: rgba(113,48,139,0.18); color: #5d3d5d; }
    .search-pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 22px;
      flex-wrap: wrap;
    }
    .page-button {
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text);
      padding: 8px 12px;
      border-radius: 10px;
      font-size: 13px;
      cursor: pointer;
    }
    .page-button.is-active {
      background: var(--purple);
      border-color: var(--purple);
      color: #fff;
    }
    @media (max-width: 900px) {
      .search-page { padding: 12px 12px 84px; }
      .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .results-grid { grid-template-columns: 1fr; }
      .search-submit { width: 100%; }
      .search-input-wrap { flex-wrap: wrap; padding: 8px 10px; }
    }
    @media (max-width: 560px) {
      .filter-grid { grid-template-columns: 1fr; }
      .results-head { flex-direction: column; align-items: flex-start; }
      .result-details { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <div class="search-page">
      <header class="topbar">
        <div>
          <h1>Pesquisa</h1>
          <p>Encontre salas, materiais, aulas e conteúdos relevantes.</p>
        </div>
      </header>

      <form id="searchForm" method="get" action="pesquisa.php" novalidate>
        <div class="search-panel">
          <div class="search-input-wrap">
            <span class="search-token" aria-hidden="true">#</span>
            <input id="searchInput" name="q" type="search" value="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por assunto, matéria, professor ou palavra-chave" aria-label="Buscar conteúdos">
            <button class="btn btn-primary search-submit" type="submit">Buscar</button>
          </div>

          <div class="filter-grid">
            <input class="filter-field" name="assunto" maxlength="80" value="<?= htmlspecialchars($assuntoValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Assunto" aria-label="Filtrar por assunto">
            <input class="filter-field" name="materia" maxlength="80" value="<?= htmlspecialchars($materiaValue, ENT_QUOTES, 'UTF-8') ?>" placeholder="Matéria" aria-label="Filtrar por matéria">

            <select class="filter-field" name="tipo" aria-label="Filtrar por tipo de conteúdo">
              <option value="">Tipo</option>
              <option value="sala" <?= $tipoValue === 'sala' ? 'selected' : '' ?>>Sala</option>
              <option value="video_aula" <?= $tipoValue === 'video_aula' ? 'selected' : '' ?>>Videoaula</option>
              <option value="livro" <?= $tipoValue === 'livro' ? 'selected' : '' ?>>Livro</option>
              <option value="apostila" <?= $tipoValue === 'apostila' ? 'selected' : '' ?>>Apostila</option>
              <option value="pdf" <?= $tipoValue === 'pdf' ? 'selected' : '' ?>>PDF</option>
              <option value="material" <?= $tipoValue === 'material' ? 'selected' : '' ?>>Material</option>
              <option value="artigo" <?= $tipoValue === 'artigo' ? 'selected' : '' ?>>Artigo</option>
              <option value="redacao" <?= $tipoValue === 'redacao' ? 'selected' : '' ?>>Redação</option>
              <option value="post" <?= $tipoValue === 'post' ? 'selected' : '' ?>>Publicação</option>
            </select>

            <select class="filter-field" name="acesso" aria-label="Filtrar por acesso">
              <option value="">Acesso</option>
              <option value="publica" <?= $acessoValue === 'publica' ? 'selected' : '' ?>>Visibilidade pública</option>
              <option value="privada" <?= $acessoValue === 'privada' ? 'selected' : '' ?>>Visibilidade privada</option>
              <option value="premium" <?= $acessoValue === 'premium' ? 'selected' : '' ?>>Requer plano Premium</option>
            </select>
          </div>

          <div id="activeFilters" class="filter-chips" aria-live="polite"></div>
        </div>
      </form>

      <div id="searchStatus" class="search-status" aria-live="polite"></div>
      <div id="resultsGrid" class="results-grid" aria-live="polite"></div>
      <nav id="pagination" class="search-pagination" aria-label="Paginação de pesquisa"></nav>
    </div>
  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>

  <script>
    const form = document.getElementById('searchForm');
    const resultsGrid = document.getElementById('resultsGrid');
    const statusNode = document.getElementById('searchStatus');
    const paginationNode = document.getElementById('pagination');
    const activeFilters = document.getElementById('activeFilters');
    const pageValue = Number(new URLSearchParams(window.location.search).get('page') || '1');

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
      });
    }

    function formatFilterLabel(key, value) {
      const labels = {
        q: 'Busca',
        assunto: 'Assunto',
        materia: 'Matéria',
        tipo: 'Tipo',
        acesso: 'Acesso',
      };

      if (!value) return null;
      const normalized = String(value).trim();
      if (!normalized) return null;
      const label = labels[key] || key;
      return `${label}: ${normalized}`;
    }

    function updateActiveFilters() {
      const entries = [
        ['q', document.querySelector('[name="q"]').value],
        ['assunto', document.querySelector('[name="assunto"]').value],
        ['materia', document.querySelector('[name="materia"]').value],
        ['tipo', document.querySelector('[name="tipo"]').value],
        ['acesso', document.querySelector('[name="acesso"]').value],
      ].map(([key, value]) => ({ key, value, label: formatFilterLabel(key, value) })).filter(item => item.label);

      if (!entries.length) {
        activeFilters.innerHTML = '<span style="color: var(--muted); font-size: 12px;">Nenhum filtro aplicado.</span>';
        return;
      }

      activeFilters.innerHTML = entries.map(({ key, value, label }) => {
        return '<button type="button" class="filter-chip" data-clear="' + key + '">' + escapeHtml(label) + ' ×</button>';
      }).join('');

      activeFilters.querySelectorAll('[data-clear]').forEach(button => {
        button.addEventListener('click', function () {
          const field = button.getAttribute('data-clear');
          const fieldNode = form.querySelector('[name="' + field + '"]');
          if (fieldNode) {
            fieldNode.value = '';
            loadResults(1);
          }
        });
      });
    }

    function renderResults(data) {
      const items = Array.isArray(data.items) ? data.items : [];
      const meta = data.meta || {};

      if (!items.length) {
        resultsGrid.innerHTML = '<div class="search-state-empty">Nenhuma correspondência encontrada para os filtros atuais. Tente outra combinação de termos ou remova alguns filtros.</div>';
        paginationNode.innerHTML = '';
        return;
      }

      resultsGrid.innerHTML = items.map(item => {
        const label = item.accessLabel || 'Acesso não informado';
        const badgeClass = item.premiumOnly ? 'result-badge-premium' : (['privada', 'sala', 'instituicao'].includes(item.access) ? 'result-badge-private' : 'result-badge-public');
        const accessText = item.accessLabel || 'Acesso não informado';
        const url = item.url ? ' href="' + escapeHtml(item.url) + '"' : '';
        const infoLink = item.url ? '<a class="btn btn-outline" style="margin-top: 8px; display: inline-flex;" href="' + escapeHtml(item.url) + '">Visualizar</a>' : '';

        return '<article class="result-card">'
          + '<div class="result-topline">'
          + '<span>' + escapeHtml(item.typeLabel || item.type || 'Conteúdo') + '</span>'
          + '<span class="result-badge ' + badgeClass + '">' + escapeHtml(label) + '</span>'
          + '</div>'
          + '<h3>' + escapeHtml(item.title || 'Resultado') + '</h3>'
          + '<p>' + escapeHtml(item.description || 'Sem descrição disponível.') + '</p>'
          + '<div class="result-details">'
          + '<div class="result-detail"><strong>Responsável</strong>' + escapeHtml(item.responsible || 'Não informado') + '</div>'
          + '<div class="result-detail"><strong>Categoria</strong>' + escapeHtml(item.category || 'Não informada') + '</div>'
          + '<div class="result-detail"><strong>Matéria</strong>' + escapeHtml(item.subject || 'Não informada') + '</div>'
          + '<div class="result-detail"><strong>Acesso</strong>' + escapeHtml(accessText) + '</div>'
          + '</div>'
          + (item.priceText ? '<div class="result-price">' + escapeHtml(item.priceText) + '</div>' : '')
          + infoLink
          + '</article>';
      }).join('');

      const totalPages = Math.max(1, Number(meta.totalPages || 1));
      const currentPage = Math.max(1, Number(meta.page || 1));
      const prevLabel = currentPage > 1 ? '<button type="button" class="page-button" data-page="' + (currentPage - 1) + '">Anterior</button>' : '';
      const nextLabel = currentPage < totalPages ? '<button type="button" class="page-button" data-page="' + (currentPage + 1) + '">Próxima</button>' : '';
      const pages = [];
      for (let page = 1; page <= totalPages; page += 1) {
        pages.push('<button type="button" class="page-button ' + (page === currentPage ? 'is-active' : '') + '" data-page="' + page + '">' + page + '</button>');
      }
      paginationNode.innerHTML = prevLabel + pages.join('') + nextLabel;
      paginationNode.querySelectorAll('[data-page]').forEach(button => {
        button.addEventListener('click', function () {
          loadResults(Number(button.getAttribute('data-page')) || 1);
        });
      });
    }

    function loadResults(page) {
      const params = new URLSearchParams(new FormData(form));
      params.set('page', String(page || 1));
      const url = 'api/pesquisa.php?' + params.toString();

      statusNode.innerHTML = '<div class="search-state-loading">Carregando resultados...</div>';
      resultsGrid.innerHTML = '<div class="search-state-loading">Pesquisando conteúdos...</div>';
      paginationNode.innerHTML = '';

      fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      })
        .then(response => {
          if (!response.ok) {
            throw new Error('Falha ao consultar os resultados.');
          }
          return response.json();
        })
        .then(data => {
          if (data && data.error) {
            throw new Error(data.error);
          }

          const total = Number(data.meta?.total || 0);
          statusNode.textContent = total + ' resultado(s) encontrado(s).';
          renderResults(data);
          updateActiveFilters();
        })
        .catch((error) => {
          statusNode.innerHTML = '<div class="search-state-error">Não foi possível carregar os resultados no momento. Verifique sua conexão e tente novamente.</div>';
          resultsGrid.innerHTML = '<div class="search-state-error">Falha de comunicação com o servidor. Por favor, tente novamente.</div>';
          paginationNode.innerHTML = '';
        });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      loadResults(1);
    });

    form.querySelectorAll('select, input').forEach(control => {
      control.addEventListener('change', function () {
        loadResults(1);
      });
    });

    form.querySelector('input[name="q"]').addEventListener('input', function () {
      if (this.value.trim().length === 0) {
        updateActiveFilters();
      }
    });

    updateActiveFilters();
    loadResults(pageValue || 1);
  </script>
</body>
</html>
