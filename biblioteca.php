<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/data.php';
require_auth();

$perfilAtual = current_user_perfil();
$livros = [
    ['titulo' => 'Matemática para Concursos', 'autor' => 'Lucas N.', 'tipo' => 'Livro', 'status' => 'Disponível'],
    ['titulo' => 'Redação em Foco', 'autor' => 'Marina L.', 'tipo' => 'PDF', 'status' => 'Disponível'],
    ['titulo' => 'História do Brasil em Fases', 'autor' => 'Pedro A.', 'tipo' => 'Apostila', 'status' => 'Leitura online'],
    ['titulo' => 'Pensamento Crítico', 'autor' => 'Ana C.', 'tipo' => 'Artigo', 'status' => 'Disponível'],
];

$subtitulo = [
    'estudante' => 'Livros, apostilas e materiais para reforçar seus estudos.',
    'professor' => 'Materiais e recursos para apoiar sua turma e suas aulas.',
    'instituicao' => 'Biblioteca institucional com materiais de apoio às turmas.',
][$perfilAtual] ?? 'Livros, apostilas e materiais de estudo';

$botaoTexto = [
    'estudante' => 'Explorar materiais',
    'professor' => 'Adicionar material',
    'instituicao' => 'Gerenciar biblioteca',
][$perfilAtual] ?? 'Explorar materiais';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Biblioteca</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <style>
    body{background:#f7f3ee}
    .library-wrap{max-width:760px;margin:0 auto;padding:20px 0 90px}
    .library-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .book-card{background:#fff;border:1px solid var(--border);border-radius:18px;padding:16px;box-shadow:var(--shadow)}
    .book-cover{height:120px;border-radius:14px;background:linear-gradient(135deg,#71308b,#b17ad2);display:grid;place-items:center;color:#fff;font-weight:800;font-size:26px;margin-bottom:12px}
    .book-card h3{margin:0 0 8px;font-size:16px}
    .book-card p{margin:0 0 12px;color:var(--muted);font-size:12px}
    .book-badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#edf7ea;color:#2d6a3c;font-size:11px;font-weight:700}
    @media(max-width:900px){.library-wrap{padding:12px 12px 84px}.library-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <?php require __DIR__ . '/includes/header.php'; ?>
  <main class="main">
    <div class="library-wrap">
      <header class="topbar">
        <div>
          <h1>Biblioteca</h1>
          <p><?= htmlspecialchars($subtitulo) ?></p>
        </div>
        <button class="btn btn-primary" type="button"><?= htmlspecialchars($botaoTexto) ?></button>
      </header>

      <div class="library-grid">
        <?php foreach ($livros as $livro): ?>
          <article class="book-card">
            <div class="book-cover"><?= strtoupper(substr((string) $livro['titulo'], 0, 1)) ?></div>
            <h3><?= htmlspecialchars((string) $livro['titulo']) ?></h3>
            <p><?= htmlspecialchars((string) $livro['autor']) ?> · <?= htmlspecialchars((string) $livro['tipo']) ?></p>
            <span class="book-badge"><?= htmlspecialchars((string) $livro['status']) ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
