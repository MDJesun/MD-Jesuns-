<?php
require __DIR__ . '/includes/layout.php';
$p = $C['testimonialsPage'];
if (!testimonials_on()) {
    // switched off in the Admin Panel: send visitors to the Home page
    header('Location: index.php', true, 302);
    exit;
}
$videos = array_values($C['videoTestimonials']);
$quotes = !empty($p['showQuotes']) ? $C['testimonials'] : [];
$lead = $videos ? $videos[0] : null;
$rest = $videos ? array_slice($videos, 1) : [];

$rated = array_values(array_filter(array_map(function ($t) {
    return (int)($t['stars'] ?? 0);
}, $videos)));
$avg = $rated ? number_format(array_sum($rated) / count($rated), 1) : '';

page_open('testimonials', $p['title1'] . ' ' . $p['title2'] . ' — ' . $C['settings']['siteName']);
?>
<section class="phead sec vt-head" data-sec="phead" data-chapter="<?= e($p['eyebrow']) ?>">
<div class="wrap">
<?= eyebrow($p['eyebrow']) ?>
<?= title_lines($p['title1'], $p['title2'], 'display ph-title', 'h1') ?>
<?php if ($p['intro'] !== ''): ?><p class="ph-intro rv"><?= e($p['intro']) ?></p><?php endif; ?>
<div class="ph-meta mono rv">
<span><b data-count="<?= count($videos) ?>"><?= count($videos) ?></b> video <?= count($videos) === 1 ? 'testimonial' : 'testimonials' ?></span>
<?php if ($quotes): ?><span><b data-count="<?= count($quotes) ?>"><?= count($quotes) ?></b> written <?= count($quotes) === 1 ? 'review' : 'reviews' ?></span><?php endif; ?>
<?php if ($avg !== ''): ?><span class="vt-avg"><?= stars_html('5') ?><b><?= e($avg) ?></b> average rating</span><?php endif; ?>
</div>
</div>
</section>

<?php if ($lead): $lf = in_array($lead['format'] ?? '', ['vertical', 'square'], true) ? $lead['format'] : 'landscape';
    $leadVideo = trim((string)$lead['video']) !== ''; ?>
<section class="vt-feature sec" data-sec="vt-feature" data-chapter="Featured story">
<div class="wrap">
<div class="vf-grid fmt-<?= e($lf) ?>">
<<?= $leadVideo ? 'button type="button"' . vt_play_attrs($lead) : 'div' ?> class="vf-screen rv rv-wipe fmt-<?= e($lf) ?><?= $leadVideo ? ' has-video' : '' ?>">
<span class="vc-media"><?= vt_media($lead) ?></span>
<span class="vc-shade"></span>
<span class="vc-corners" aria-hidden="true"></span>
<span class="vf-hud mono" aria-hidden="true"><span class="vf-rec"><i></i>REC</span><span class="vf-tc" data-tc="1">00:00:00:00</span></span>
<?php if ($leadVideo): ?><span class="vc-play vf-play" aria-hidden="true"><span class="vc-ring"></span><span class="vc-ring r2"></span><?= play_svg(22) ?></span><?php endif; ?>
<span class="vf-scan" aria-hidden="true"></span>
</<?= $leadVideo ? 'button' : 'div' ?>>
<div class="vf-info">
<div class="mono vf-k rv">Featured story · 01 / <?= sprintf('%02d', count($videos)) ?></div>
<?php $st = stars_html((string)($lead['stars'] ?? '0')); if ($st !== ''): ?><div class="rv"><?= $st ?></div><?php endif; ?>
<?php if ($lead['quote'] !== ''): ?><blockquote class="display vf-quote rv">“<?= e(trim((string)$lead['quote'], " \"“”")) ?>”</blockquote><?php endif; ?>
<div class="vc-who vf-who rv"><span class="vc-av display" aria-hidden="true"><?= e(vt_initials((string)$lead['name'])) ?></span><span><span class="vc-name"><?= e($lead['name']) ?></span><?php if ($lead['role'] !== ''): ?><span class="mono vc-role"><?= e($lead['role']) ?></span><?php endif; ?></span></div>
<div class="vf-tags rv">
<?php if ($lead['project'] !== ''): ?><span class="mono pc-chip"><?= e($lead['project']) ?></span><?php endif; ?>
<?php if ($lead['duration'] !== ''): ?><span class="mono pc-chip"><?= e($lead['duration']) ?></span><?php endif; ?>
</div>
<?php if ($leadVideo): ?><div class="rv"><button class="btn btn-primary" type="button"<?= vt_play_attrs($lead) ?> data-magnet="1"><?= play_svg(12) ?><?= e($p['playLabel']) ?></button></div><?php endif; ?>
</div>
</div>
</div>
</section>
<?php endif; ?>

<?php if ($rest): ?>
<section class="vt-all sec" data-sec="vt-all" data-chapter="All client stories">
<div class="wrap">
<div class="vt-grid vt-n<?= min(3, count($rest)) ?>">
<?php foreach ($rest as $i => $t): ?>
<?= vt_card($t, $i + 1) ?>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<?php if (!$videos): ?>
<section class="vt-all sec" data-sec="vt-all">
<div class="wrap"><div class="vt-empty hud rv rv-card"><span class="vc-play" aria-hidden="true"><span class="vc-ring"></span><?= play_svg(16) ?></span><p><?= e($p['emptyText']) ?></p></div></div>
</section>
<?php endif; ?>

<?php if ($quotes): ?>
<section class="quotes vt-quotes sec" data-sec="quotes" data-chapter="<?= e($p['quotesEyebrow']) ?>">
<div class="wrap">
<?= eyebrow($p['quotesEyebrow']) ?>
<?= title_lines($p['quotesTitle1'], $p['quotesTitle2']) ?>
<div class="q-grid">
<?php foreach ($quotes as $q): ?>
<?= quote_card($q) ?>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<section class="band sec" data-sec="band">
<div class="band-in hud rv rv-card">
<p class="band-t"><?= e($p['ctaTitle']) ?></p>
<a class="btn btn-primary" href="contact.php" style="position: relative" data-magnet="1"><?= e($p['ctaButton']) ?><?= arrow_svg() ?></a>
</div>
</section>
<?php page_close(); ?>
