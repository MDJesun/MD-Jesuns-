<?php
require __DIR__ . '/includes/layout.php';
$sp = $C['servicesPage'];
$services = $C['services'];
$steps = array_slice($C['process'], 0, 6);
$n = count($steps);
$note = trim((string)$sp['priceNote']);

page_open('services', $sp['title1'] . ' ' . $sp['title2'] . ' — ' . $C['settings']['siteName']);
?>
<section class="phead sec" data-sec="phead" data-chapter="<?= e($sp['eyebrow']) ?>">
<div class="wrap">
<?= eyebrow($sp['eyebrow']) ?>
<?= title_lines($sp['title1'], $sp['title2'], 'display ph-title', 'h1') ?>
<?php if ($sp['intro'] !== ''): ?><p class="ph-intro rv"><?= e($sp['intro']) ?></p><?php endif; ?>
<?php if (!$services && $note !== ''): ?><p class="price-note pn-top rv"><span class="pn-ico"><?= icon('info', 16) ?></span><?= e($note) ?></p><?php endif; ?>
</div>
</section>

<?php if ($services): ?>
<section class="svc-page sec" data-sec="svcp">
<div class="wrap">
<div class="svc-grid g3">
<?php foreach ($services as $i => $sv): ?>
<article class="svc hud rv rv-card" data-spot="1">
<div class="svc-top"><span class="mono svc-code">SRV-<?= sprintf('%02d', $i + 1) ?></span></div>
<span class="svc-ico"><?= icon($sv['icon']) ?></span>
<h3><?= e($sv['title']) ?></h3>
<p><?= e($sv['text']) ?></p>
<div class="svc-foot">
<?php $tags = csv_items($sv['tags']); if ($tags): ?>
<ul class="svc-list mono"><?php foreach ($tags as $tg): ?><li><?= e($tg) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?= price_html($sv, $sp['currency']) ?>
<a class="svc-quote" href="contact.php?service=<?= rawurlencode($sv['title']) ?>#form"><?= e($sp['quoteButton']) ?><?= arrow_svg() ?></a>
</div>
</article>
<?php endforeach; ?>
</div>
<?php if ($note !== ''): ?><p class="price-note rv"><span class="pn-ico"><?= icon('info', 16) ?></span><?= e($note) ?></p><?php endif; ?>
</div>
</section>
<?php endif; ?>

<?php if ($n): $win = round(100 / $n, 2); ?>
<style>
.tl-grid{grid-template-columns:repeat(<?= $n ?>,minmax(0,1fr))}
@keyframes blkN{0%,1%{border-color:rgba(255,255,255,.1);background-color:rgba(255,255,255,.03);box-shadow:none}2%,<?= max(3, $win - 3) ?>%{border-color:rgb(var(--b) / .75);background-color:rgb(var(--b) / .14);box-shadow:0 0 40px rgb(var(--b) / .22)}<?= $win + 1 ?>%,100%{border-color:rgba(255,255,255,.1);background-color:rgba(255,255,255,.03);box-shadow:none}}
.tl-grid .pblk{animation-name:blkN}
@media (max-width:900px){.tl-grid{grid-template-columns:repeat(<?= min(2, $n) ?>,minmax(0,1fr))}}
@media (max-width:560px){.tl-grid{grid-template-columns:repeat(1,minmax(0,1fr))}}
</style>
<section id="process" class="process sec" data-sec="process" data-chapter="<?= e($sp['processEyebrow']) ?>">
<div class="wrap">
<?= eyebrow($sp['processEyebrow']) ?>
<?= title_lines($sp['processTitle1'], $sp['processTitle2']) ?>
<div class="tl">
<div class="tl-ruler rv"></div>
<div class="tl-body">
<div class="scan"></div>
<div class="tl-grid">
<?php foreach ($steps as $i => $st): $delay = -(($n - $i) % $n) * (12 / $n); ?>
<div class="pblk rv rv-card" style="animation-delay: <?= number_format($delay, 2) ?>s"><div class="mono pnum"><?= e($st['label']) ?></div><h3><?= e($st['title']) ?></h3><p><?= e($st['text']) ?></p></div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
</section>
<?php endif; ?>

<section class="band sec" data-sec="band">
<div class="band-in hud rv rv-card">
<p class="band-t"><?= e($C['home']['ctaTitle1'] . ' ' . $C['home']['ctaTitle2']) ?></p>
<a class="btn btn-primary" href="contact.php" style="position: relative" data-magnet="1"><?= e($C['home']['ctaButton']) ?><?= arrow_svg() ?></a>
</div>
</section>
<?php page_close(); ?>
