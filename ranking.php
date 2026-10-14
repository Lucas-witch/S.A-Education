<?php
require 'includes/auth.php';
require 'includes/data.php';

$rankingNormalizado = array_map(static function (array $item): array {
    $nome = trim((string) ($item['nome'] ?? 'Usuário'));
    $nome = $nome !== '' ? $nome : 'Usuário';

    $avatar = trim((string) ($item['avatar'] ?? ''));
    if ($avatar === '') {
        $inicial = preg_replace('/[^A-Za-zÀ-ÿ]/u', '', $nome);
        $avatar = strtoupper(mb_substr($inicial, 0, 2, 'UTF-8') ?: 'U');
    }

    return [
        'nome' => $nome,
        'pontos' => (int) ($item['pontos'] ?? 0),
        'pos' => (int) ($item['pos'] ?? 0),
        'avatar' => $avatar,
    ];
}, $ranking);

$top3 = array_slice($rankingNormalizado, 0, 3);
if (count($top3) < 3) {
    while (count($top3) < 3) {
        $top3[] = ['nome' => 'Usuário', 'pontos' => 0, 'pos' => count($top3) + 1, 'avatar' => 'U'];
    }
}
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ranking</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body><div class="app"><?php require 'includes/header.php'; ?><main class="main">
<header class="topbar"><div><h1>Ranking</h1><p>Veja quem está no topo.</p></div></header>
<div class="grid grid-3" style="align-items:end;margin-bottom:25px">
<?php foreach ($top3 as $r): ?><div class="card" style="padding:18px;text-align:center"><div style="font-size:38px"><?= htmlspecialchars((string) $r['avatar'], ENT_QUOTES, 'UTF-8') ?></div><strong><?= htmlspecialchars((string) $r['nome'], ENT_QUOTES, 'UTF-8') ?></strong><p style="font-size:11px;color:var(--muted)"><?= number_format((int) $r['pontos'],0,',','.') ?> pts</p></div><?php endforeach; ?>
</div>
<div class="card"><?php foreach ($rankingNormalizado as $r): ?><div class="rank-row"><span class="rank-num"><?= (int) $r['pos'] ?></span><div class="rank-avatar"><?= htmlspecialchars((string) $r['avatar'], ENT_QUOTES, 'UTF-8') ?></div><strong style="font-size:13px"><?= htmlspecialchars((string) $r['nome'], ENT_QUOTES, 'UTF-8') ?></strong><span class="rank-points"><?= number_format((int) $r['pontos'],0,',','.') ?> pts ›</span></div><?php endforeach; ?></div>
</main></div><?php require 'includes/footer.php'; ?>
