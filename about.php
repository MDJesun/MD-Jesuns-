<?php
require __DIR__ . '/includes/layout.php';
$a = $C['about'];
$awards = $C['awards'];

page_open('about', $a['title1'] . ' ' . $a['title2'] . ' — ' . $C['settings']['siteName']);
?>
<section id="about" class="about-sec sec" data-sec="about" data-chapter="<?= e($a['eyebrow']) ?>">
<?= about_block($C, $a['eyebrow'], 'h1', $a['workButton'], 'portfolio.php') ?>
</section>

<?php if ($awards): ?>
<section id="awards" class="awards sec" data-sec="awards" data-chapter="<?= e($a['awardsEyebrow']) ?>">
<div class="wrap">
<div class="aw-head">
<div>
<?= eyebrow($a['awardsEyebrow']) ?>
<?= title_lines($a['awardsTitle1'], $a['awardsTitle2']) ?>
</div>
<?php if ($a['awardsIntro'] !== ''): ?><p class="rv"><?= e($a['awardsIntro']) ?></p><?php endif; ?>
</div>
<div class="aw-grid">
<?php foreach ($awards as $aw): ?>
<article class="aw hud rv rv-card">
<?php if ($aw['image'] !== ''): ?><img class="aw-img" src="<?= e(img_src($aw['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
<?php if ($aw['year'] !== ''): ?><div class="aw-year"><?= e($aw['year']) ?></div><?php endif; ?>
<h3><?= e($aw['title']) ?></h3>
<?php if ($aw['issuer'] !== ''): ?><div class="mono aw-issuer"><?= e($aw['issuer']) ?></div><?php endif; ?>
<?php if ($aw['description'] !== ''): ?><p><?= e($aw['description']) ?></p><?php endif; ?>
<?php if ($aw['link'] !== ''): ?><a class="aw-link" href="<?= e(safe_href($aw['link'])) ?>" target="_blank" rel="noopener">View<?= arrow_svg() ?></a><?php endif; ?>
</article>
<?php endforeach; ?>
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
