<?php
require __DIR__ . '/includes/layout.php';
$h = $C['home'];
$s = $C['settings'];
$heroImg = $h['heroImage'] !== '' ? $h['heroImage'] : 'assets/img/hero-jesun.webp';
$featured = array_values(array_filter($C['projects'], function ($p) {
    return !empty($p['featured']);
}));
$fdata = array_map(function ($p) {
    return [
        'title' => $p['title'], 'cat' => $p['category'], 'time' => $p['runtime'], 'client' => $p['client'],
        'result' => $p['result'], 'video' => $p['video'], 'img' => img_src($p['image'], 'assets/img/work/p1.jpg'),
    ];
}, $featured);
$cats = array_values(array_unique(array_filter(array_map(function ($p) {
    return trim((string)$p['category']);
}, $featured), 'strlen')));
$marquee = csv_items($h['marquee']);
$services = $C['services'];
$currency = $C['servicesPage']['currency'];
$quotes = $C['testimonials'];
$homeVideos = (empty($h['quotesVideos']) || !testimonials_on()) ? [] : array_slice(array_values(array_filter($C['videoTestimonials'], function ($t) {
    return !empty($t['featured']);
})), 0, 3);
$ctaHref = $C['contact']['bookingUrl'] !== '' ? $C['contact']['bookingUrl'] : 'contact.php';
$star = '<svg class="mq-star" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0 14.6 9.4 24 12 14.6 14.6 12 24 9.4 14.6 0 12 9.4 9.4z" fill="currentColor"></path></svg>';
$ff = implode("\n", array_map(function ($i) { return sprintf('%02d', $i); }, range(0, 23)));
$sx = implode("\n", array_map(function ($i) { return sprintf('%02d', $i); }, range(0, 59)));
$tc = '<span class="tcd"><span class="tcs mm">' . $sx . '</span></span>:<span class="tcd"><span class="tcs ss">' . $sx . '</span></span>:<span class="tcd"><span class="tcs ff">' . $ff . '</span></span>';

/* which sections are shown, in the order chosen in the Admin Panel */
$available = [
    'about' => true,
    'work' => (bool)$C['projects'],
    'reel' => true,
    'marquee' => (bool)$marquee,
    'services' => (bool)$services,
    'quotes' => $quotes || $homeVideos,
    'cta' => true,
];
$order = [];
foreach ($h['sections'] as $sec) {
    if (!empty($sec['on']) && !empty($available[$sec['k']])) {
        $order[] = $sec['k'];
    }
}
$nums = [];
foreach ($order as $k) {
    if ($k !== 'marquee') {
        $nums[$k] = count($nums) + 1; // 01, 02, 03… (the moving banner has no number)
    }
}
$label = function (string $k, string $text) use ($nums, $h): string {
    $t = mdj_strip_number($text);
    if (!empty($h['numbered']) && isset($nums[$k]) && $t !== '') {
        return sprintf('%02d', $nums[$k]) . ' — ' . $t;
    }
    return $t;
};
$chapter = function (string $k, string $text) use ($label): string {
    return ' data-chapter="' . e($label($k, $text)) . '"';
};

function wave_bars(): string
{
    $out = '';
    for ($i = 0; $i < 180; $i++) {
        $v = 0.55 + 0.25 * sin($i * 0.21) + 0.2 * sin($i * 0.057 + 1.3) * cos($i * 0.31);
        $hgt = max(3.0, min(30.0, 5 + $v * 22 * (0.72 + 0.28 * abs(sin($i * 1.7)))));
        $out .= sprintf('<rect x="%.1f" y="%.1f" width="3" height="%.1f" rx="1"></rect>', $i * 1000 / 180, 18 - $hgt / 2, $hgt);
    }
    return $out;
}

$wave = '<div class="wave-wrap" aria-hidden="true">'
    . '<svg class="wave wave-back" viewBox="0 0 2880 120" preserveAspectRatio="none"><path d="M0 52 C80 34 160 34 240 52 S400 70 480 52 S640 34 720 52 S880 70 960 52 S1120 34 1200 52 S1360 70 1440 52 S1600 34 1680 52 S1840 70 1920 52 S2080 34 2160 52 S2320 70 2400 52 S2560 34 2640 52 S2800 70 2880 52 L2880 120 L0 120 Z" style="fill: rgb(var(--b) / .08)"></path><path d="M0 52 C80 34 160 34 240 52 S400 70 480 52 S640 34 720 52 S880 70 960 52 S1120 34 1200 52 S1360 70 1440 52 S1600 34 1680 52 S1840 70 1920 52 S2080 34 2160 52 S2320 70 2400 52 S2560 34 2640 52 S2800 70 2880 52" fill="none" style="stroke: rgb(var(--b) / .45)" stroke-width="1" vector-effect="non-scaling-stroke"></path></svg>'
    . '<svg class="wave wave-front" viewBox="0 0 2880 120" preserveAspectRatio="none"><defs><filter id="wglow" x="-5%" y="-60%" width="110%" height="220%"><feGaussianBlur stdDeviation="5"></feGaussianBlur></filter></defs><path d="M0 64 C120 42 240 42 360 64 S600 86 720 64 S960 42 1080 64 S1320 86 1440 64 S1680 42 1800 64 S2040 86 2160 64 S2400 42 2520 64 S2760 86 2880 64 L2880 120 L0 120 Z" style="fill: rgb(var(--bg2))"></path><path d="M0 64 C120 42 240 42 360 64 S600 86 720 64 S960 42 1080 64 S1320 86 1440 64 S1680 42 1800 64 S2040 86 2160 64 S2400 42 2520 64 S2760 86 2880 64" fill="none" style="stroke: rgb(var(--b))" stroke-width="8" opacity=".55" filter="url(#wglow)"></path><path d="M0 64 C120 42 240 42 360 64 S600 86 720 64 S960 42 1080 64 S1320 86 1440 64 S1680 42 1800 64 S2040 86 2160 64 S2400 42 2520 64 S2760 86 2880 64" fill="none" style="stroke: rgb(var(--ice))" stroke-width="1.5" vector-effect="non-scaling-stroke"></path></svg>'
    . '</div><div class="slide-bg" aria-hidden="true"><div class="stars s1"></div><div class="stars s2"></div><div class="slide-grid"></div></div>';

page_open('home', $s['seoTitle']);
?>
<div class="hero-wrap" data-chapter="Intro">
<section id="top" class="hero" aria-label="Introduction">
<div class="hero-inner" data-hero="1">
<div class="h-glow"></div>
<div class="floor"></div>
<div class="horizon"></div>
<div class="par par-rings">
<svg class="rings" viewBox="0 0 640 640" aria-hidden="true">
<g class="spin"><circle cx="320" cy="320" r="300" fill="none" style="stroke: rgb(var(--b) / .5)" stroke-width="1" stroke-dasharray="2 10"></circle></g>
<g class="spin-r"><circle cx="320" cy="320" r="262" fill="none" style="stroke: rgb(var(--b) / .85)" stroke-width="1.5" stroke-dasharray="180 40 20 40 360 80"></circle></g>
<g class="spin-f"><circle cx="320" cy="320" r="226" fill="none" style="stroke: rgb(var(--ice) / .6)" stroke-width="3" stroke-dasharray="60 1360"></circle></g>
<circle cx="320" cy="320" r="190" fill="none" style="stroke: rgb(var(--b) / .25)" stroke-width="1"></circle>
<g class="spin-r"><path d="M320 10v14M320 616v14M10 320h14M616 320h14" style="stroke: rgb(var(--b))" stroke-width="2"></path></g>
</svg>
</div>
<div class="par par-name">
<div class="h-eyebrow mono"><span style="animation-timing-function: steps(<?= max(4, letters_count($h['heroEyebrow'])) ?>)"><?= e($h['heroEyebrow']) ?></span></div>
<h1 class="giant" data-text="<?= e($h['bigName']) ?>" aria-label="<?= e($s['siteName'] . ' — ' . $s['role']) ?>"><?= e($h['bigName']) ?></h1>
</div>
<div class="par par-photo">
<div class="hero-photo" style="--hero-mask: url('<?= e(css_url($heroImg)) ?>')">
<div class="ph-feather">
<div class="ph-reveal">
<img src="<?= e(img_src($heroImg)) ?>" alt="<?= e($s['siteName']) ?>">
<div class="silo ph-tint"></div>
<div class="silo ph-screen"></div>
<div class="silo ph-sweep"></div>
</div>
<div class="silo ph-scan"></div>
</div>
</div>
<div class="kb-glow"></div>
</div>
<?php for ($i = 0; $i < 28; $i++):
    $x = 6 + (($i * 37) % 88); $y = 12 + (($i * 53) % 64); $sz = 1 + ($i % 3); ?>
<span class="dust" style="left: <?= $x ?>%; top: <?= $y ?>%; width: <?= $sz ?>px; height: <?= $sz ?>px; animation-duration: <?= number_format(6 + ($i % 5) * 1.6, 1) ?>s; animation-delay: -<?= number_format($i * 0.7, 1) ?>s"></span>
<?php endfor; ?>
<div class="par par-ui">
<div class="hero-left">
<div class="mono hero-tag"><span class="gdot"></span><?= e($h['availability']) ?></div>
<p class="display hero-h"><span class="ln"><span class="d19"><?= e($h['headline1']) ?></span></span><span class="ln"><span class="grad d21"><?= e($h['headline2']) ?></span></span></p>
<div class="hero-cta">
<a class="btn btn-primary" href="portfolio.php" data-magnet="1"><?= e($h['ctaPrimary']) ?><?= arrow_svg() ?></a>
<a class="btn btn-ghost" href="#showreel" data-cur="play" data-video="<?= e($h['reelVideo']) ?>" data-video-title="<?= e($h['reelTitle1'] . ' ' . $h['reelTitle2']) ?>"><svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z" fill="currentColor"></path></svg><?= e($h['ctaSecondary']) ?></a>
</div>
</div>
<div class="hero-right">
<div class="hchip d26">
<div class="glass float hchip-in">
<div class="mono" style="font-size: 10px; color: rgb(var(--mut)); display: flex; justify-content: space-between; gap: 10px"><span><?= e($h['nowEditingLabel']) ?></span><span class="tc" style="color: rgb(var(--b))">00:<?= $tc ?></span></div>
<div style="margin-top: 8px; font-size: 14px; font-weight: 600"><?= e($h['nowEditing']) ?></div>
<div class="nb"><span></span></div>
</div>
</div>
<div class="hchip d29">
<div class="glass float hchip-in" style="animation-delay: -3s; display: flex; align-items: center; gap: 14px">
<div class="display" style="font-size: 30px; font-weight: 700; color: rgb(var(--b))"><?= e($h['badgeValue']) ?></div>
<div style="font-size: 13px; line-height: 1.4; color: rgb(var(--mut))"><?= e($h['badgeText']) ?></div>
</div>
</div>
</div>
</div>
<span class="corner c-tl"></span><span class="corner c-tr"></span><span class="corner c-bl"></span><span class="corner c-br"></span>
<div class="hud-read hr-tl mono"><span class="rec"></span>REC · 4K UHD · 23.976 fps</div>
<div class="hud-read hr-tr mono"><span class="tc">TC&#160;00:<?= $tc ?></span></div>
<div class="hud-read hr-bl mono">ISO 800 · f/2.8 · 1/48</div>
<div class="hud-read hr-br mono">Scroll to explore<span class="scroll-ind"></span></div>
</div>
</section>
</div>
<?php
foreach ($order as $pos => $key):
    if ($pos === 0) {
        // the first section slides up over the hero with a glowing wave edge
        echo '<div class="slide-wrap">' . $wave;
    }
    switch ($key):
        case 'about': ?>
<section id="about" class="about-sec home-about sec" data-sec="about"<?= $chapter('about', $h['aboutEyebrow']) ?>>
<?= about_block($C, $label('about', $h['aboutEyebrow']), 'h2', $h['aboutButton'], 'about.php') ?>
</section>
<?php break;

        case 'work': ?>
<section id="work" class="work sec" data-sec="work"<?= $chapter('work', $h['workEyebrow']) ?>>
<div class="wrap head-row" style="position: relative; z-index: 2">
<div>
<?= eyebrow($label('work', $h['workEyebrow'])) ?>
<?= title_lines($h['workTitle1'], $h['workTitle2']) ?>
</div>
<?php if (count($cats) > 1): ?>
<div class="pills rv" id="cfPills">
<button class="pill pill-on" type="button" data-filter="All" aria-pressed="true"><?= e($C['portfolio']['allLabel']) ?></button>
<?php foreach ($cats as $c): ?>
<button class="pill" type="button" data-filter="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?></button>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php if ($featured): ?>
<div class="cf-stage" id="cfStage" data-cur="drag" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Featured work. Use the left and right arrow keys to browse." style="position: relative; z-index: 2">
<div class="cf-beam"></div>
<div class="cf-floor"></div>
<div class="cf-pool"></div>
<div class="cf-persp"><div class="cf-3d" id="cf3d"></div></div>
</div>
<div class="cf-ctrl rv" style="position: relative; z-index: 2">
<button class="cbtn" id="prevBtn" type="button" aria-label="Previous project" data-magnet="1"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"></path></svg></button>
<div class="cf-meta">
<div class="cf-count"><span class="display cnum" id="cfNum">01</span><span class="mono ctot" id="cfTot">/ <?= sprintf('%02d', count($featured)) ?></span></div>
<div class="cf-ticks" id="cfTicks"></div>
<div class="mono cf-hint">Drag, click a card, or use the arrows</div>
</div>
<button class="cbtn" id="nextBtn" type="button" aria-label="Next project" data-magnet="1"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></button>
</div>
<script type="application/json" id="featured-data"><?= json_encode($fdata, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<div class="more-row rv"><a class="btn btn-ghost" href="portfolio.php" data-magnet="1"><?= e($h['workButton']) ?><?= arrow_svg() ?></a></div>
</section>
<?php break;

        case 'reel': ?>
<section id="showreel" class="reel sec" data-sec="reel"<?= $chapter('reel', $h['reelEyebrow']) ?>>
<div class="wrap">
<div class="reel-head">
<div>
<?= eyebrow($label('reel', $h['reelEyebrow'])) ?>
<?= title_lines($h['reelTitle1'], $h['reelTitle2']) ?>
</div>
<p class="rv"><?= e($h['reelText']) ?></p>
</div>
<div class="reel-frame rv rv-wipe" data-cur="play" data-video="<?= e($h['reelVideo']) ?>" data-video-title="<?= e($h['reelTitle1'] . ' ' . $h['reelTitle2']) ?>">
<img class="reel-img" src="<?= e(img_src($h['reelCover'], 'assets/img/reel.jpg')) ?>" alt="Showreel cover">
<div class="reel-shade"></div>
<div class="reel-safe"></div>
<div class="lb lb-top"></div>
<div class="lb lb-bot"></div>
<div class="reel-hud mono"><span style="display: flex; align-items: center; gap: 8px"><span class="rec"></span>Showreel <?= date('Y') ?></span><span style="color: rgb(var(--mut))">4K · 23.976 fps · Rec.709</span><span>00:02:00:00</span></div>
<button class="reel-play" type="button" aria-label="Play showreel"><span class="rp-ring"></span><span class="rp-ring r2"></span><?= play_svg(30) ?></button>
<div class="meters" aria-hidden="true"><span class="m"></span><span class="m m2"></span></div>
<div class="reel-tl" aria-hidden="true">
<div class="trk"><span class="mono trk-l">V1</span><div class="clips"><span class="clip" style="flex: 3"></span><span class="clip alt" style="flex: 2"></span><span class="clip" style="flex: 4"></span><span class="clip alt" style="flex: 1"></span><span class="clip dk" style="flex: 3"></span><span class="clip" style="flex: 2"></span></div></div>
<div class="trk"><span class="mono trk-l">A1</span><div class="wav"><svg viewBox="0 0 1000 36" preserveAspectRatio="none" aria-hidden="true"><g style="fill: rgb(var(--b) / .6)"><?= wave_bars() ?></g></svg></div></div>
<div class="trk-scan"><div class="scan"></div></div>
</div>
</div>
</div>
</section>
<?php break;

        case 'marquee':
            $items = '';
            foreach ($marquee as $m) {
                $items .= '<span class="mq-item">' . e($m) . '</span>' . $star;
            } ?>
<section class="mq-sec" aria-hidden="true">
<div class="mq mq-b"><div class="mq-track rev"><?= $items . $items ?></div></div>
<div class="mq mq-a"><div class="mq-track"><?= $items . $items ?></div></div>
</section>
<?php break;

        case 'services': ?>
<section id="services" class="svc-sec sec" data-sec="svc"<?= $chapter('services', $h['servicesEyebrow']) ?>>
<div class="wrap">
<div class="center-head">
<?= eyebrow($label('services', $h['servicesEyebrow'])) ?>
<?= title_lines($h['servicesTitle1'], $h['servicesTitle2']) ?>
</div>
<div class="svc-grid">
<?php foreach ($services as $i => $sv): ?>
<article class="svc hud rv rv-card" data-spot="1">
<div class="svc-top"><span class="mono svc-code">SRV-<?= sprintf('%02d', $i + 1) ?></span><a class="svc-arrow" href="services.php" aria-label="More about <?= e($sv['title']) ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"></path></svg></a></div>
<span class="svc-ico"><?= icon($sv['icon']) ?></span>
<h3><?= e($sv['title']) ?></h3>
<p><?= e($sv['text']) ?></p>
<div class="svc-foot">
<?= price_html($sv, $currency) ?>
</div>
</article>
<?php endforeach; ?>
</div>
<div class="more-row rv"><a class="btn btn-ghost" href="services.php" data-magnet="1"><?= e($h['servicesButton']) ?><?= arrow_svg() ?></a></div>
</div>
</section>
<?php break;

        case 'quotes': ?>
<section id="testimonials" class="quotes sec" data-sec="quotes"<?= $chapter('quotes', $h['quotesEyebrow']) ?>>
<div class="wrap">
<?= eyebrow($label('quotes', $h['quotesEyebrow'])) ?>
<?php if ($homeVideos): ?>
<div class="vt-grid vt-home vt-n<?= count($homeVideos) ?>">
<?php foreach ($homeVideos as $i => $t): ?>
<?= vt_card($t, $i) ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($quotes): ?>
<div class="q-grid">
<?php foreach ($quotes as $q): ?>
<?= quote_card($q) ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (testimonials_on() && trim((string)$h['quotesButton']) !== ''): ?>
<div class="more-row rv"><a class="btn btn-ghost" href="testimonials.php" data-magnet="1"><?= play_svg(12) ?><?= e($h['quotesButton']) ?><?= arrow_svg() ?></a></div>
<?php endif; ?>
</div>
</section>
<?php break;

        case 'cta': ?>
<section id="cta" class="contact sec" data-sec="contact"<?= $chapter('cta', $h['ctaEyebrow']) ?>>
<div class="cta-inner">
<?= eyebrow($label('cta', $h['ctaEyebrow'])) ?>
<?= title_lines($h['ctaTitle1'], $h['ctaTitle2'], 'display cta-h') ?>
<p class="cta-p rv"><?= e($h['ctaText']) ?></p>
<div class="cta-btns rv">
<a class="btn btn-primary" href="<?= e(safe_href($ctaHref, 'contact.php')) ?>" data-magnet="1"><?= e($h['ctaButton']) ?><?= arrow_svg() ?></a>
<?php if ($C['contact']['email'] !== ''): ?><a class="btn btn-ghost" href="mailto:<?= e($C['contact']['email']) ?>" data-magnet="1"><?= soc_icon('mail', 16) ?><?= e($C['contact']['email']) ?></a><?php endif; ?>
</div>
<div class="mono cta-note rv"><span class="gdot"></span><?= e($C['contact']['responseTime'] . ' · ' . $C['contact']['location']) ?></div>
</div>
<div class="planet-wrap" aria-hidden="true"><div class="planet"></div><div class="flare"></div><div class="sunrise"></div></div>
</section>
<?php break;
    endswitch;
    if ($pos === 0) {
        echo '</div>';
    }
endforeach;
page_close();
