<?php
require __DIR__ . '/includes/layout.php';
$p = $C['portfolio'];
$projects = $C['projects'];
$cats = array_values(array_unique(array_filter(array_map(function ($x) {
    return trim((string)$x['category']);
}, $projects), 'strlen')));

page_open('portfolio', $p['title1'] . ' ' . $p['title2'] . ' — ' . $C['settings']['siteName']);
?>
<section class="phead sec" data-sec="phead" data-chapter="<?= e($p['eyebrow']) ?>">
<div class="wrap">
<?= eyebrow($p['eyebrow']) ?>
<?= title_lines($p['title1'], $p['title2'], 'display ph-title', 'h1') ?>
<?php if ($p['intro'] !== ''): ?><p class="ph-intro rv"><?= e($p['intro']) ?></p><?php endif; ?>
<div class="ph-meta mono rv"><span><b data-count="<?= count($projects) ?>"><?= count($projects) ?></b> projects</span><span><b data-count="<?= count($cats) ?>"><?= count($cats) ?></b> categories</span></div>
</div>
</section>

<section class="pf sec" data-sec="pf">
<div class="wrap">
<div class="pf-bar rv">
<div class="pills" id="pfPills">
<button class="pill pill-on" type="button" data-filter="All" aria-pressed="true"><?= e($p['allLabel']) ?></button>
<?php foreach ($cats as $c): ?>
<button class="pill" type="button" data-filter="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?></button>
<?php endforeach; ?>
</div>
<span class="mono pf-count" id="pfCount" aria-live="polite"><?= count($projects) ?> projects</span>
</div>
<div class="pf-grid" id="pfGrid">
<?php foreach ($projects as $i => $pr):
    $hasVideo = trim((string)$pr['video']) !== '';
    $tag = $hasVideo ? 'button' : 'div';
    $chip = trim($pr['category'] . ($pr['runtime'] !== '' ? ' · ' . $pr['runtime'] : '')); ?>
<<?= $tag ?> class="pcard rv rv-card<?= $hasVideo ? ' has-video' : '' ?>"<?= $hasVideo ? ' type="button" data-video="' . e($pr['video']) . '" data-video-title="' . e($pr['title']) . '" data-cur="play" aria-label="Play ' . e($pr['title']) . '"' : '' ?> data-cat="<?= e(trim((string)$pr['category'])) ?>">
<img src="<?= e(img_src($pr['image'], 'assets/img/work/p1.jpg')) ?>" alt="<?= e($pr['title']) ?>" loading="lazy" decoding="async">
<span class="pc-shade"></span>
<span class="pc-top"><?php if ($chip !== ''): ?><span class="mono pc-chip"><?= e($chip) ?></span><?php endif; ?><?php if ($pr['year'] !== ''): ?><span class="mono pc-chip"><?= e($pr['year']) ?></span><?php endif; ?></span>
<span class="pc-bot">
<span class="display pc-title"><?= e($pr['title']) ?></span>
<?php if ($pr['client'] !== ''): ?><span class="pc-client"><?= e($pr['client']) ?></span><?php endif; ?>
<?php if ($pr['result'] !== ''): ?><span class="pc-result"><?= e($pr['result']) ?></span><?php endif; ?>
</span>
<?php if ($hasVideo): ?><span class="pc-play"><?= play_svg(14) ?></span><?php endif; ?>
</<?= $tag ?>>
<?php endforeach; ?>
</div>
<div class="pf-empty<?= $projects ? '' : ' show' ?>" id="pfEmpty"><?= e($p['emptyText']) ?></div>
</div>
</section>

<section class="band sec" data-sec="band">
<div class="band-in hud rv rv-card">
<p class="band-t"><?= e($p['ctaTitle']) ?></p>
<a class="btn btn-primary" href="contact.php" style="position: relative" data-magnet="1"><?= e($p['ctaButton']) ?><?= arrow_svg() ?></a>
</div>
</section>
<?php page_close(); ?>
