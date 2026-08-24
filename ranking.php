<?php require 'includes/auth.php'; require 'includes/data.php'; ?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ranking</title><link rel="stylesheet" href="assets/css/style.css"></head><body><div class="app"><?php require 'includes/header.php'; ?><main class="main">
<header class="topbar"><div><h1>Ranking</h1><p>Veja quem está no topo.</p></div></header>
<div class="grid grid-3" style="align-items:end;margin-bottom:25px">
<?php foreach([$ranking[1],$ranking[0],$ranking[2]] as $r): ?><div class="card" style="padding:18px;text-align:center"><div style="font-size:38px"><?= $r['avatar'] ?></div><strong><?= $r['nome'] ?></strong><p style="font-size:11px;color:var(--muted)"><?= number_format($r['pontos'],0,',','.') ?> pts</p></div><?php endforeach; ?>
</div>
<div class="card"><?php foreach($ranking as $r): ?><div class="rank-row"><span class="rank-num"><?= $r['pos'] ?></span><div class="rank-avatar"><?= $r['avatar'] ?></div><strong style="font-size:13px"><?= $r['nome'] ?></strong><span class="rank-points"><?= number_format($r['pontos'],0,',','.') ?> pts ›</span></div><?php endforeach; ?></div>
</main></div><?php require 'includes/footer.php'; ?>
