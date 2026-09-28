<?php
require 'includes/auth.php';
require 'includes/data.php';
require_auth();

$publicacoes = [
    [
        'titulo' => 'Como estudar com foco em 3 passos?',
        'autor' => 'Ana Clara',
        'tipo' => 'Artigo',
        'tempo' => 'há 2h',
        'texto' => 'A disciplina melhora quando você organiza o tempo, elimina distrações e revisa o que aprendeu em ciclos curtos.',
        'tag' => 'Produtividade',
        'premium' => false,
    ],
    [
        'titulo' => 'Aula prática: revisão de funções exponenciais',
        'autor' => 'Lucas',
        'tipo' => 'Vídeo aula',
        'tempo' => 'há 6h',
        'texto' => 'Uma aula rápida para revisar conceitos fundamentais e entender padrões em gráficos e aplicações.',
        'tag' => 'Matemática',
        'premium' => true,
    ],
    [
        'titulo' => 'Redação modelo: argumentação e clareza',
        'autor' => 'Marina',
        'tipo' => 'Redação',
        'tempo' => 'há 1 dia',
        'texto' => 'Apresentamos um exemplo de redação com estrutura clara, argumento central e boas conexões entre ideias.',
        'tag' => 'Linguagens',
        'premium' => false,
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feed</title>
  <link rel="stylesheet" href="assets/css/estilo.css">
  <style>
    body{background:#f7f3ee}
    .feed-wrap{max-width:760px;margin:0 auto;padding:20px 0 90px}
    .feed-card{
      background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:18px;margin-bottom:18px
    }
    .feed-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .feed-author{display:flex;align-items:center;gap:10px}
    .avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#71308b,#a566ba);color:#fff;display:grid;place-items:center;font-weight:700}
    .feed-meta{font-size:12px;color:var(--muted)}
    .feed-tag{display:inline-block;padding:6px 10px;border-radius:999px;background:#f2eaf6;color:var(--purple);font-size:11px;font-weight:700;margin-top:8px}
    .feed-card h2{margin:8px 0 10px;font-size:20px}
    .feed-card p{margin:0;color:#3f3745;line-height:1.7;font-size:14px}
    .feed-badge{padding:6px 8px;border-radius:999px;background:#fff3d5;color:#7a5200;font-size:11px;font-weight:700}
    @media(max-width:900px){.feed-wrap{padding:12px 12px 84px}}
  </style>
</head>
<body>
  <?php require 'includes/header.php'; ?>
  <main class="main">
    <div class="feed-wrap">
      <header class="topbar">
        <div>
          <h1>Feed</h1>
          <p>Publicações recentes da comunidade</p>
        </div>
      </header>

      <?php foreach ($publicacoes as $pub): ?>
        <article class="feed-card">
          <div class="feed-head">
            <div class="feed-author">
              <div class="avatar"><?= strtoupper(substr($pub['autor'],0,1)) ?></div>
              <div>
                <strong><?= htmlspecialchars($pub['autor']) ?></strong>
                <div class="feed-meta"><?= htmlspecialchars($pub['tipo']) ?> · <?= htmlspecialchars($pub['tempo']) ?></div>
              </div>
            </div>
            <?php if ($pub['premium']): ?>
              <span class="feed-badge">Premium</span>
            <?php endif; ?>
          </div>

          <div class="feed-tag"><?= htmlspecialchars($pub['tag']) ?></div>
          <h2><?= htmlspecialchars($pub['titulo']) ?></h2>
          <p><?= htmlspecialchars($pub['texto']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </main>
  <?php require 'includes/footer.php'; ?>
</body>
</html>
