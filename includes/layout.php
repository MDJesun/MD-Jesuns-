<?php
/*
 * Shared page building blocks for the public website.
 * Every page reads the latest saved content on each visit, so changes made
 * in the Admin Panel show up immediately.
 */
declare(strict_types=1);
// not a page: block direct visits
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$C = content_load();

/* ------------------------------------------------------------------ helpers */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** cache-busting URL for files we ship */
function asset(string $path): string
{
    $file = MDJ_ROOT . '/' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** URL for an image saved in content (uploads/, assets/img/ or https) */
function img_src(string $path, string $fallback = ''): string
{
    $p = $path !== '' ? $path : $fallback;
    if ($p === '') {
        return '';
    }
    if (preg_match('~^https://~i', $p)) {
        return $p;
    }
    return asset($p);
}

/** root-relative URL, safe inside CSS url('...') */
function css_url(string $path): string
{
    $u = preg_match('~^https://~i', $path) ? $path : mdj_base() . '/' . ltrim($path, '/');
    return str_replace(["'", '(', ')', ' ', '"', '\\'], ['%27', '%28', '%29', '%20', '%22', '%5C'], $u);
}

function safe_href(string $url, string $fallback = '#'): string
{
    $u = trim($url);
    if ($u === '') {
        return $fallback;
    }
    if (preg_match('~^(https?://|mailto:|tel:|#|[a-z0-9_\-]+\.php)~i', $u)) {
        return $u;
    }
    return $fallback;
}

function paragraphs(string $text, string $class = ''): string
{
    $out = '';
    foreach (preg_split("/\n\s*\n/", trim($text)) ?: [] as $p) {
        if (trim($p) === '') {
            continue;
        }
        $out .= '<p' . ($class ? ' class="' . e($class) . '"' : '') . '>' . nl2br(e(trim($p)), false) . '</p>';
    }
    return $out;
}

function csv_items(string $s): array
{
    return array_values(array_filter(array_map('trim', explode(',', $s)), 'strlen'));
}

/** one option per line → list (empty lines dropped, duplicates removed) */
function line_items(string $s, int $max = 30): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', $s) ?: [] as $ln) {
        $ln = trim($ln);
        if ($ln !== '' && !in_array($ln, $out, true)) {
            $out[] = mdj_substr($ln, 60);
        }
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

function wa_link(array $contact): string
{
    $digits = preg_replace('/\D/', '', (string)($contact['whatsapp'] ?? ''));
    if ($digits === '') {
        return '';
    }
    $msg = trim((string)($contact['whatsappMessage'] ?? ''));
    return 'https://wa.me/' . $digits . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
}

function price_html(array $svc, string $currency): string
{
    $price = trim((string)($svc['price'] ?? ''));
    if ($price === '') {
        return '';
    }
    $cur = is_numeric(str_replace([',', '.', '[', ']'], '', $price)) ? '<span class="cur">' . e($currency) . '</span>' : '';
    $h = '<div class="price">';
    if (trim((string)($svc['pricePrefix'] ?? '')) !== '') {
        $h .= '<span class="mono price-pre">' . e($svc['pricePrefix']) . '</span>';
    }
    $h .= '<span class="price-val">' . $cur . e($price) . '</span>';
    if (trim((string)($svc['priceSuffix'] ?? '')) !== '') {
        $h .= '<span class="price-suf">' . e($svc['priceSuffix']) . '</span>';
    }
    return $h . '</div>';
}

function letters_count(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function svg_icon(string $paths, int $size, float $stroke = 1.6, string $class = ''): string
{
    return '<svg' . ($class !== '' ? ' class="' . $class . '"' : '') . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

function icon(string $name, int $size = 40): string
{
    $paths = [
        'edit' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 15h18M8 15v4M15 15v4M10 9l3 2-3 2z"></path>',
        'motion' => '<path d="M4 18c4-10 12-10 16 0"></path><circle cx="12" cy="8" r="2.5"></circle><path d="M5 6h3M16 6h3M12 2v1.5"></path>',
        'color' => '<circle cx="9" cy="9" r="5.5"></circle><circle cx="15" cy="9" r="5.5"></circle><circle cx="12" cy="14.5" r="5.5"></circle>',
        'shorts' => '<rect x="7" y="2" width="10" height="20" rx="2.5"></rect><path d="M11 5h2M10.5 10l3.5 2-3.5 2z"></path>',
        'sound' => '<path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2"></path>',
        'camera' => '<rect x="2" y="7" width="14" height="11" rx="2"></rect><path d="M16 11l6-3v9l-6-3z"></path>',
        'spark' => '<path d="M12 3l1.8 5.4L19 10l-5.2 1.6L12 17l-1.8-5.4L5 10l5.2-1.6z"></path><path d="M19 17l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"></path>',
        'film' => '<rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M7 3v18M17 3v18M3 8h4M3 12h4M3 16h4M17 8h4M17 12h4M17 16h4"></path>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path>',
        'whatsapp' => '<path d="M4 19V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H8.5z"></path><path d="M8.6 10.6h.01M12 10.6h.01M15.4 10.6h.01"></path>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path>',
        'pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"></path><circle cx="12" cy="10" r="2.5"></circle>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"></path>',
        'out' => '<path d="M7 17 17 7M9 7h8v8"></path>',
        'info' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 7.5h.01"></path>',
    ];
    return svg_icon($paths[$name] ?? $paths['spark'], $size);
}

/*
 * Social and contact icons: original line-style glyphs drawn for this site
 * (not the platforms' own logos), chosen automatically from each link.
 */
function soc_paths(): array
{
    return [
        'camera' => '<rect x="3" y="7" width="18" height="13" rx="3"></rect><path d="M8 7l1.6-3h4.8L16 7"></path><circle cx="12" cy="13.5" r="3.6"></circle><path d="M18 10h.01"></path>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2.2"></rect><path d="M9 7V5.6A1.6 1.6 0 0 1 10.6 4h2.8A1.6 1.6 0 0 1 15 5.6V7M3 12.5h18"></path><path d="M10.5 12.5v1.6h3v-1.6"></path>',
        'hash' => '<path d="M9.5 4 7.5 20M16.5 4l-2 16M4.5 9h16M3.5 15h16"></path>',
        'people' => '<circle cx="9" cy="8" r="3.2"></circle><path d="M3 20c.6-3.6 3-5.6 6-5.6s5.4 2 6 5.6"></path><path d="M16 4.9a3.2 3.2 0 0 1 0 6.2M17.6 14.7c1.9.7 3 2.5 3.4 5.3"></path>',
        'screen' => '<rect x="2.5" y="4" width="19" height="13.5" rx="2.5"></rect><path d="M10 8.3v5.4l4.6-2.7z"></path><path d="M8 21h8"></path>',
        'clapper' => '<rect x="3" y="9.5" width="18" height="10.5" rx="1.6"></rect><path d="M3.4 9.5 4.6 4.8l16 2.4-.5 2.3"></path><path d="M8.8 5.4 8 9.5M14 6.2l-.9 3.3"></path>',
        'nib' => '<path d="M12 3 4.8 10.4 7.2 20h9.6l2.4-9.6z"></path><circle cx="12" cy="12.4" r="1.8"></circle><path d="M12 3v7.6"></path>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2.1-.8 2.1-1.8 0-1.3-1-1.6-1-2.7 0-1 .8-1.6 1.9-1.6H17a4 4 0 0 0 4-4C21 6.4 17 3 12 3z"></path><path d="M7.5 11.5h.01M9.8 7.4h.01M14.6 7.2h.01"></path>',
        'note' => '<path d="M9 18V5.5l11-2.2V16"></path><circle cx="6" cy="18" r="3"></circle><circle cx="17" cy="16" r="3"></circle>',
        'bookmark' => '<path d="M6.5 3.5h11v17.2L12 16.6l-5.5 4.1z"></path>',
        'at' => '<circle cx="12" cy="12" r="3.8"></circle><path d="M15.8 8.2v5.1a2.8 2.8 0 0 0 5.6 0V12a9.4 9.4 0 1 0-3.7 7.5"></path>',
        'code' => '<path d="m8 7.5-5 4.5 5 4.5M16 7.5l5 4.5-5 4.5M14 4l-4 16"></path>',
        'plane' => '<path d="M21 3.5 3 10.6l7.1 2.6 2.6 7.3z"></path><path d="M21 3.5 10.1 13.2"></path>',
        'chat' => '<path d="M4 19V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H8.5z"></path><path d="M8.6 10.6h.01M12 10.6h.01M15.4 10.6h.01"></path>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.2"></rect><path d="m3.6 7 8.4 6 8.4-6"></path>',
        'pen' => '<path d="M4 20h4.2L19.5 8.7a2.3 2.3 0 0 0-3.2-3.2L5 16.8z"></path><path d="m14.6 7.2 3.2 3.2"></path>',
        'star' => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.9l-5.2 2.8 1-5.9-4.3-4.1 5.9-.8z"></path>',
        'tv' => '<rect x="3" y="7" width="18" height="12.5" rx="2.2"></rect><path d="m8 3 4 4 4-4"></path>',
        'headphones' => '<path d="M4 15.5V12a8 8 0 0 1 16 0v3.5"></path><rect x="3" y="14" width="4.2" height="6.5" rx="1.6"></rect><rect x="16.8" y="14" width="4.2" height="6.5" rx="1.6"></rect>',
        'wave' => '<path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2"></path>',
        'doc' => '<path d="M6 3h8.5L19 7.5V21H6z"></path><path d="M14 3v5h5M9 12.5h7M9 16.5h7"></path>',
        'globe' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a13.5 13.5 0 0 1 0 18M12 3a13.5 13.5 0 0 0 0 18"></path>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"></path><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"></path>',
    ];
}

/** which glyph fits a social link, judged from its web address first, then its name */
function social_glyph(string $label, string $url): string
{
    $host = strtolower((string)parse_url(trim($url), PHP_URL_HOST));
    $host = (string)preg_replace('/^(www\.|m\.|mobile\.|web\.)/', '', $host);
    $name = strtolower(trim($label));
    $map = [
        ['camera', ['instagram.com', 'instagr.am'], ['instagram', 'insta', 'ig']],
        ['briefcase', ['linkedin.com', 'lnkd.in'], ['linkedin']],
        ['hash', ['x.com', 'twitter.com'], ['x', 'twitter', 'x (twitter)', 'twitter / x']],
        ['people', ['facebook.com', 'fb.com', 'fb.me', 'fb.watch'], ['facebook', 'fb']],
        ['screen', ['youtube.com', 'youtu.be'], ['youtube', 'yt']],
        ['clapper', ['vimeo.com'], ['vimeo']],
        ['nib', ['behance.net', 'be.net'], ['behance']],
        ['palette', ['dribbble.com', 'artstation.com'], ['dribbble', 'artstation']],
        ['note', ['tiktok.com', 'spotify.com', 'music.apple.com'], ['tiktok', 'spotify', 'apple music']],
        ['bookmark', ['pinterest.com', 'pin.it'], ['pinterest']],
        ['at', ['threads.net', 'threads.com', 'bsky.app', 'mastodon.social'], ['threads', 'bluesky', 'mastodon']],
        ['code', ['github.com', 'gitlab.com', 'codepen.io'], ['github', 'gitlab', 'codepen']],
        ['plane', ['t.me', 'telegram.me', 'telegram.org'], ['telegram']],
        ['chat', ['wa.me', 'whatsapp.com', 'api.whatsapp.com', 'discord.gg', 'discord.com', 'reddit.com', 'snapchat.com'], ['whatsapp', 'discord', 'reddit', 'snapchat']],
        ['star', ['fiverr.com', 'upwork.com', 'freelancer.com', 'contra.com', 'peopleperhour.com'], ['fiverr', 'upwork', 'freelancer', 'contra']],
        ['tv', ['twitch.tv', 'kick.com'], ['twitch', 'kick']],
        ['wave', ['soundcloud.com'], ['soundcloud']],
        ['doc', ['medium.com', 'substack.com'], ['medium', 'substack', 'blog']],
        ['film', ['imdb.com', 'frame.io'], ['imdb']],
    ];
    if ($host !== '') {
        foreach ($map as $m) {
            foreach ($m[1] as $d) {
                if ($host === $d || substr($host, -strlen('.' . $d)) === '.' . $d) {
                    return $m[0];
                }
            }
        }
    }
    foreach ($map as $m) {
        if (in_array($name, $m[2], true)) {
            return $m[0];
        }
    }
    if (strpos(strtolower(trim($url)), 'mailto:') === 0) {
        return 'mail';
    }
    return $host !== '' ? 'globe' : 'link';
}

function soc_icon(string $glyph, int $size = 18): string
{
    $p = soc_paths();
    if ($glyph === 'film') {
        return icon('film', $size);
    }
    return svg_icon($p[$glyph] ?? $p['link'], $size, 1.7);
}

function arrow_svg(): string
{
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>';
}

function play_svg(int $s = 12): string
{
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z" fill="#FFFFFF"></path></svg>';
}

function logo_mark(array $C): string
{
    $logo = (string)($C['settings']['logo'] ?? '');
    if ($logo !== '') {
        return '<img class="brand-logo" src="' . e(img_src($logo)) . '" alt="" width="30" height="30">';
    }
    return '<svg class="ico-b" width="30" height="30" viewBox="0 0 30 30" aria-hidden="true"><path d="M15 1.5 27 8.5v13L15 28.5 3 21.5v-13z" fill="none" stroke="currentColor" stroke-width="1.5"></path><path d="M12 10v10l8-5z" fill="currentColor"></path></svg>';
}

/** heading with line-by-line reveal (revealed when it scrolls into view) */
function title_lines(string $l1, string $l2, string $class = 'display h2', string $tag = 'h2'): string
{
    $h = '<' . $tag . ' class="' . e($class) . ' rv-t">';
    if ($l1 !== '') {
        $h .= '<span class="ln"><span>' . e($l1) . '</span></span>';
    }
    if ($l2 !== '') {
        $h .= '<span class="ln"><span class="grad">' . e($l2) . '</span></span>';
    }
    return $h . '</' . $tag . '>';
}

/** small mono label above a title; "decodes" into view when it is revealed */
function eyebrow(string $text, string $delay = 'd0'): string
{
    if ($text === '') {
        return '';
    }
    $n = max(4, min(48, letters_count($text)));
    return '<div class="mono eyebrow tw" data-scr="1" style="--n:' . $n . '">' . e($text) . '</div>';
}

/* --------------------------------------------------- "who's behind the edit" */

/** stat number that counts up when it scrolls into view (the final text is always in the page) */
function stat_html(array $st): string
{
    return '<div class="stat rv"><div class="stat-n grad" data-count="' . e($st['value']) . '">' . e($st['value']) . '</div><div class="mono stat-l">' . e($st['label']) . '</div></div>';
}

/** the About text + hologram block, used on the About page and on the Home page */
function about_block(array $C, string $label, string $tag, string $buttonText, string $buttonHref): string
{
    $a = $C['about'];
    $photo = $a['photo'] !== '' ? $a['photo'] : 'assets/img/holo-jesun.webp';
    ob_start();
    ?>
<div class="about-inner">
<div class="about-copy">
<?= eyebrow($label) ?>
<?= title_lines($a['title1'], $a['title2'], 'display h2', $tag) ?>
<div class="lead rv" style="margin-top: 24px; max-width: 520px"><?= paragraphs($a['bio']) ?></div>
<?php if ($a['stats']): ?>
<div class="stats">
<?php foreach ($a['stats'] as $st) { echo stat_html($st); } ?>
</div>
<?php endif; ?>
<?php if ($a['tools']): ?>
<div class="mono rv tk-l">Toolkit</div>
<div class="tools">
<?php foreach ($a['tools'] as $tl): ?>
<span class="tool glass rv"><?php if ($tl['short'] !== ''): ?><b><?= e($tl['short']) ?></b><?php endif; ?><?= e($tl['name']) ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($buttonText !== ''): ?>
<div class="rv" style="margin-top: 34px"><a class="btn btn-ghost" href="<?= e($buttonHref) ?>" data-magnet="1"><?= e($buttonText) ?><?= arrow_svg() ?></a></div>
<?php endif; ?>
</div>

<div class="holo-col">
<div class="holo-stage" id="holoStage" style="--holo-mask: url('<?= e(css_url($photo)) ?>')">
<div class="holo-glow"></div>
<div class="holo-power">
<div class="holo-cone"></div>
<div class="holo-fig">
<div class="holo-wipe">
<div class="holo-reveal">
<div class="silo-h holo-occl"></div>
<img src="<?= e(img_src($photo)) ?>" alt="Hologram portrait of <?= e($C['settings']['siteName']) ?>">
<div class="silo-h holo-tint"></div>
<div class="silo-h holo-lines"></div>
<div class="silo-h holo-sweep"></div>
</div>
</div>
<div class="silo-h holo-scan"></div>
</div>
<div class="tagw td1" style="left: 0; top: 104px"><div class="tag glass mono float">Subject // <b><?= e($a['tagSubject']) ?></b></div><span class="tline tl-r"></span></div>
<div class="tagw td2" style="right: 0; top: 214px"><div class="tag glass mono float" style="animation-delay: -2s">Role // <b><?= e($a['tagRole']) ?></b></div><span class="tline tl-l"></span></div>
<div class="tagw td3" style="left: 0; top: 470px"><div class="tag glass mono float" style="animation-delay: -4s">Status // <span class="gdot"></span><b style="color: #3BFF9A"><?= e($a['tagStatus']) ?></b></div><span class="tline tl-r"></span></div>
</div>
<div class="holo-base">
<svg viewBox="0 0 460 460" width="460" height="460" aria-hidden="true" style="overflow: visible">
<circle cx="230" cy="230" r="218" style="fill: rgb(var(--b) / .10); stroke: rgb(var(--b))" stroke-width="3"></circle>
<g style="transform-origin: 230px 230px; animation: spin 14s linear infinite"><circle cx="230" cy="230" r="188" fill="none" style="stroke: rgb(var(--ice))" stroke-width="2" stroke-dasharray="40 20 6 20"></circle></g>
<g style="transform-origin: 230px 230px; animation: spin 22s linear infinite reverse"><circle cx="230" cy="230" r="146" fill="none" style="stroke: rgb(var(--b) / .8)" stroke-width="6" stroke-dasharray="2 14"></circle></g>
<circle cx="230" cy="230" r="94" style="fill: rgb(var(--ice) / .25)"></circle>
</svg>
</div>
<div class="holo-flash"></div>
</div>
<div class="holo-ctrl rv">
<button class="holo-toggle glass" id="holoBtn" type="button" aria-pressed="true" aria-label="Hologram power"><span class="ht-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 3v8M6.3 7.2a8 8 0 1 0 11.4 0"></path></svg></span><span>Hologram</span><span class="ht-state" id="holoState">On</span><span class="ht-sw"></span></button>
</div>
</div>
</div>
<?php
    return (string)ob_get_clean();
}

/* ------------------------------------------------------- testimonials */

/** is the Testimonials page switched on in the Admin Panel? */
function testimonials_on(): bool
{
    global $C;
    return !empty($C['testimonialsPage']['enabled']);
}

/** YouTube video id from any YouTube link, or '' */
function yt_id(string $url): string
{
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
        return $m[1];
    }
    return '';
}

function is_video_file(string $url): bool
{
    return (bool)preg_match('~\.(mp4|m4v|webm|mov)(\?|#|$)~i', $url);
}

/** what to show before a video plays: ['img', url] | ['video', url] | ['', ''] */
function video_cover(array $t): array
{
    $cover = (string)($t['cover'] ?? '');
    if ($cover !== '') {
        return ['img', img_src($cover)];
    }
    $v = (string)($t['video'] ?? '');
    $yt = yt_id($v);
    if ($yt !== '') {
        return ['img', 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg'];
    }
    if (is_video_file($v)) {
        return ['video', (preg_match('~^https://~i', $v) ? $v : mdj_base() . '/' . $v) . '#t=0.6'];
    }
    return ['', ''];
}

function stars_html(string $n): string
{
    $n = (int)$n;
    if ($n < 1) {
        return '';
    }
    $one = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z" fill="currentColor"></path></svg>';
    return '<span class="vstars" role="img" aria-label="' . $n . ' out of 5 stars">' . str_repeat($one, $n) . '</span>';
}

function vt_initials(string $name): string
{
    $name = trim((string)preg_replace('/[\[\]]/', '', $name));
    $parts = preg_split('/\s+/u', $name) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($p, 0, 1)) : strtoupper(substr($p, 0, 1));
    }
    return $out !== '' ? $out : '★';
}

/** media layer of a video card (cover image, first frame of an uploaded video, or a placeholder) */
function vt_media(array $t): string
{
    list($kind, $src) = video_cover($t);
    if ($kind === 'img') {
        return '<img class="vc-img" src="' . e($src) . '" alt="" loading="lazy" decoding="async">';
    }
    if ($kind === 'video') {
        return '<video class="vc-img vc-prev" src="' . e($src) . '" muted playsinline preload="metadata" tabindex="-1" aria-hidden="true"></video>';
    }
    return '<span class="vc-ph" aria-hidden="true"><span class="display vc-init">' . e(vt_initials((string)$t['name'])) . '</span></span>';
}

/** attributes that make an element open the video player */
function vt_play_attrs(array $t, string $what = 'video testimonial'): string
{
    $v = trim((string)$t['video']);
    if ($v === '') {
        return '';
    }
    $title = trim($t['name'] . ($t['role'] !== '' ? ' · ' . $t['role'] : ''));
    return ' data-video="' . e($v) . '" data-video-title="' . e($title) . '" data-video-format="' . e($t['format'] ?? 'landscape') . '" data-cur="play" aria-label="Play the ' . e($what) . ' from ' . e($t['name']) . '"';
}

/** one video testimonial card */
function vt_card(array $t, int $i, string $class = ''): string
{
    $hasVideo = trim((string)$t['video']) !== '';
    $tag = $hasVideo ? 'button' : 'div';
    $fmt = in_array($t['format'] ?? '', ['vertical', 'square'], true) ? $t['format'] : 'landscape';
    $chips = '<span class="mono vc-chip vc-rec"><i></i>' . ($hasVideo ? 'Client story' : 'Coming soon') . ' · ' . sprintf('%02d', $i + 1) . '</span>';
    if ($t['duration'] !== '') {
        $chips .= '<span class="mono vc-chip">' . e($t['duration']) . '</span>';
    }
    $h = '<' . $tag . ' class="vcard rv rv-card fmt-' . $fmt . ($hasVideo ? ' has-video' : '') . ($class !== '' ? ' ' . e($class) : '') . '"' . ($hasVideo ? ' type="button"' . vt_play_attrs($t) : '') . '>';
    $h .= '<span class="vc-media">' . vt_media($t) . '</span><span class="vc-shade"></span><span class="vc-corners" aria-hidden="true"></span>';
    $h .= '<span class="vc-top">' . $chips . '</span>';
    if ($hasVideo) {
        $h .= '<span class="vc-play" aria-hidden="true"><span class="vc-ring"></span>' . play_svg(16) . '</span>';
    }
    $h .= '<span class="vc-bot">' . stars_html((string)($t['stars'] ?? '0'));
    if ($t['quote'] !== '') {
        $h .= '<span class="vc-quote">“' . e(trim((string)$t['quote'], " \"“”")) . '”</span>';
    }
    $h .= '<span class="vc-who"><span class="vc-av display" aria-hidden="true">' . e(vt_initials((string)$t['name'])) . '</span><span><span class="vc-name">' . e($t['name']) . '</span>';
    $sub = trim($t['role'] . ($t['project'] !== '' ? ($t['role'] !== '' ? ' · ' : '') . $t['project'] : ''));
    if ($sub !== '') {
        $h .= '<span class="mono vc-role">' . e($sub) . '</span>';
    }
    $h .= '</span></span></span></' . $tag . '>';
    return $h;
}

/** one written testimonial (same design on the Home and Testimonials pages) */
function quote_card(array $q): string
{
    $av = $q['avatar'] !== ''
        ? '<img class="q-av" src="' . e(img_src($q['avatar'])) . '" alt="" style="object-fit: cover">'
        : '<span class="q-av" style="background: linear-gradient(135deg, rgb(var(--b)), rgb(var(--deep)))"></span>';
    return '<figure class="q hud rv rv-card"><svg width="30" height="22" viewBox="0 0 30 22" aria-hidden="true"><path d="M0 22V12C0 5 4 1 11 0l1 4C8 5 6 8 6 11h5v11zm17 0V12c0-7 4-11 11-12l1 4c-4 1-6 4-6 7h5v11z" style="fill: rgb(var(--b))" opacity=".7"></path></svg><blockquote>' . e($q['quote']) . '</blockquote><figcaption>' . $av . '<span><span class="q-n">' . e($q['name']) . '</span><span class="mono q-r">' . e($q['role']) . '</span></span></figcaption></figure>';
}

/* ------------------------------------------------------------------ pages */

function nav_items(): array
{
    global $C;
    $items = [
        ['index.php', 'Home', 'home'],
        ['portfolio.php', 'Work', 'portfolio'],
        ['about.php', 'About', 'about'],
        ['services.php', 'Services', 'services'],
    ];
    $tp = $C['testimonialsPage'] ?? [];
    if (testimonials_on() && !empty($tp['inMenu'])) {
        $label = trim((string)($tp['navLabel'] ?? ''));
        $items[] = ['testimonials.php', $label !== '' ? $label : 'Testimonials', 'testimonials'];
    }
    $items[] = ['contact.php', 'Contact', 'contact'];
    return $items;
}

function page_open(string $page, string $title): void
{
    global $C;
    $s = $C['settings'];
    $theme = $s['theme'];
    $intro = ($page === 'home' && !empty($s['intro']));
    $desc = $s['seoDescription'];
    $og = $s['ogImage'] !== '' ? $s['ogImage'] : ($C['home']['heroImage'] ?? '');
    $scheme = mdj_is_https() ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    $ogAbs = $og === '' ? '' : (preg_match('~^https://~i', $og) ? $og : $scheme . '://' . $host . mdj_base() . '/' . $og);
    $fav = $s['favicon'] !== '' ? img_src($s['favicon']) : asset('assets/img/favicon.svg');
    ?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>"<?= $intro ? '' : ' class="no-intro"' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#04060B">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($ogAbs !== ''): ?><meta property="og:image" content="<?= e($ogAbs) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e($fav) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&amp;family=Inter:wght@400;500;600&amp;family=JetBrains+Mono:wght@400;500;600&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/themes.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<?php if (!empty($s['transitions'])): ?>
<style>@view-transition{navigation:auto}</style>
<?php endif; ?>
<?php if ($intro): ?>
<script>try{if(sessionStorage.getItem('mdj-intro')){document.documentElement.classList.add('no-intro')}else{sessionStorage.setItem('mdj-intro','1')}}catch(e){}</script>
<?php endif; ?>
</head>
<body>
<div class="site" id="site" data-page="<?= e($page) ?>">
<?php if ($intro): ?>
<div class="intro" aria-hidden="true">
<div class="intro-top"></div>
<div class="intro-bot"></div>
<div class="intro-core">
<svg class="intro-logo ico-b" width="64" height="64" viewBox="0 0 30 30"><path class="strk" d="M15 1.5 27 8.5v13L15 28.5 3 21.5v-13z" fill="none" stroke="currentColor" stroke-width="1.2"></path><path class="fillp" d="M12 10v10l8-5z" fill="currentColor"></path></svg>
<div class="intro-name"><?= e($s['siteName']) ?></div>
<div class="intro-bar"><span></span></div>
<div class="intro-num"></div>
</div>
<div class="intro-line"></div>
</div>
<?php endif; ?>
<div class="sprog" aria-hidden="true"><i id="sprog"></i></div>
<div class="bgfx" aria-hidden="true">
<div class="aur aur1"></div><div class="aur aur2"></div><div class="aur aur3"></div>
<div class="bggrid"></div>
<div class="stars s1"></div><div class="stars s2"></div><div class="stars s3"></div>
<div class="streak"></div><div class="streak streak2"></div>
</div>
<div class="fx-vig" aria-hidden="true"></div>
<div class="fx-scan" aria-hidden="true"></div>
<div class="fx-grain" aria-hidden="true"></div>
<?php if (!empty($s['cursor'])): ?>
<div class="cursor" data-cursor-el="1" aria-hidden="true">
<div class="cur-ring" data-cur-ring="1"><i></i><span class="cur-lbl"><span class="l-play">Play</span><span class="l-drag">Drag</span><span class="l-view">View</span></span></div>
<div class="cur-dot" data-cur-dot="1"></div>
</div>
<?php endif; ?>
<header class="nav-wrap">
<nav class="nav glass<?= count(nav_items()) > 5 ? ' nav-6' : '' ?>" aria-label="Main">
<a class="brand" href="index.php" aria-label="<?= e($s['siteName']) ?>, home">
<?= logo_mark($C) ?>
<span class="display" style="font-weight: 700; font-size: 16px; letter-spacing: .16em"><?= e($s['siteName']) ?></span>
</a>
<div class="navlinks">
<?php foreach (nav_items() as $n): ?>
<a class="navlink<?= $n[2] === $page ? ' active' : '' ?>" href="<?= e($n[0]) ?>"<?= $n[2] === $page ? ' aria-current="page"' : '' ?>><?= e($n[1]) ?></a>
<?php endforeach; ?>
</div>
<div class="nav-right">
<span class="avail mono"><span class="gdot"></span>Available</span>
<a class="btn btn-primary btn-sm" href="contact.php" data-magnet="1">Hire me<?= arrow_svg() ?></a>
<button class="menu-btn" id="menuBtn" type="button" aria-label="Menu" aria-expanded="false" aria-controls="mmenu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M4 16h16"></path></svg></button>
</div>
</nav>
<div class="mmenu glass" id="mmenu" hidden>
<?php foreach (nav_items() as $n): ?>
<a class="mlink" href="<?= e($n[0]) ?>"><?= e($n[1]) ?></a>
<?php endforeach; ?>
<a class="btn btn-primary" href="contact.php">Hire me</a>
</div>
</header>
<main style="position: relative; z-index: 1">
<?php
}

function page_close(): void
{
    global $C;
    $s = $C['settings'];
    $ct = $C['contact'];
    $wa = wa_link($ct);
    $socials = array_values(array_filter($ct['socials'] ?? [], function ($x) {
        return trim((string)($x['url'] ?? '')) !== '';
    }));
    $cfg = ['autoplay' => !empty($s['autoplay']), 'cursor' => !empty($s['cursor']), 'timeline' => !empty($s['timeline'])];
    $go = '<svg class="fl-go" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"></path></svg>';
    ?>
<footer class="footer sec" data-sec="footer">
<div class="foot-top">
<div class="rv">
<a class="brand" href="index.php" aria-label="<?= e($s['siteName']) ?>, home"><?= logo_mark($C) ?><span class="display" style="font-weight: 700; font-size: 16px; letter-spacing: .16em"><?= e($s['siteName']) ?></span></a>
<p class="foot-about"><?= e($s['footerText']) ?></p>
<div class="foot-status mono"><span class="gdot"></span><?= e($ct['responseTime']) ?></div>
</div>
<div class="rv"><div class="mono foot-h">Explore</div><div class="foot-links">
<?php foreach (nav_items() as $n): ?><a class="flink fl-nav" href="<?= e($n[0]) ?>"><span class="fl-dash" aria-hidden="true"></span><span class="fl-t"><?= e($n[1]) ?></span></a><?php endforeach; ?>
</div></div>
<?php if ($socials): ?>
<div class="rv"><div class="mono foot-h">Social</div><div class="foot-links">
<?php foreach ($socials as $so): ?><a class="flink" href="<?= e(safe_href($so['url'])) ?>" target="_blank" rel="noopener" data-magnet="1"><span class="fl-ico"><?= soc_icon(social_glyph((string)$so['label'], (string)$so['url'])) ?></span><span class="fl-t"><?= e($so['label']) ?></span><?= $go ?></a><?php endforeach; ?>
</div></div>
<?php endif; ?>
<div class="rv"><div class="mono foot-h">Contact</div><div class="foot-links">
<?php if ($ct['email'] !== ''): ?><a class="flink" href="mailto:<?= e($ct['email']) ?>" data-magnet="1"><span class="fl-ico"><?= soc_icon('mail') ?></span><span class="fl-t"><?= e($ct['email']) ?></span></a><?php endif; ?>
<?php if ($wa !== ''): ?><a class="flink" href="<?= e($wa) ?>" target="_blank" rel="noopener" data-magnet="1"><span class="fl-ico"><?= soc_icon('chat') ?></span><span class="fl-t">WhatsApp</span><?= $go ?></a><?php endif; ?>
<a class="flink" href="contact.php" data-magnet="1"><span class="fl-ico"><?= soc_icon('pen') ?></span><span class="fl-t">Send an enquiry</span></a>
</div>
<a class="totop" href="#top" aria-label="Back to top" data-magnet="1"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"></path></svg></a>
</div>
</div>
<div class="foot-bottom mono"><span>© <?= date('Y') ?> <?= e($s['siteName']) ?></span><span><?= e($s['role']) ?></span></div>
<div class="foot-giant" aria-hidden="true"><?= e($s['siteName']) ?></div>
</footer>
</main>
<div class="lbx" id="lbx" role="dialog" aria-modal="true" aria-label="Video player" aria-hidden="true">
<button class="lbx-close" id="lbxClose" type="button" aria-label="Close video"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
<div class="lbx-frame" id="lbxFrame"></div>
</div>
</div>
<script id="mdj-config" type="application/json"><?= json_encode($cfg) ?></script>
<script src="<?= e(asset('assets/js/main.js')) ?>"></script>
</body>
</html>
<?php
}

/* ------------------------------------------------------------- contact form */

function form_token(): string
{
    $ts = (string)time();
    return $ts . '.' . substr(hash_hmac('sha256', 'contact|' . $ts, mdj_secret()), 0, 32);
}

/** 'ok', 'fast' (sent within 2 seconds: a robot), 'old' (page open for more than a day) or 'bad' */
function form_token_state(string $tok): string
{
    $parts = explode('.', $tok);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return 'bad';
    }
    $sig = substr(hash_hmac('sha256', 'contact|' . $parts[0], mdj_secret()), 0, 32);
    if (!hash_equals($sig, $parts[1])) {
        return 'bad';
    }
    $age = time() - (int)$parts[0];
    if ($age < 2) {
        return 'fast';
    }
    return $age <= 86400 ? 'ok' : 'old';
}

/** service choices for the enquiry form */
function form_services(array $C): array
{
    $list = line_items((string)($C['contactPage']['serviceOptions'] ?? ''));
    if (!$list) {
        foreach ($C['services'] as $sv) {
            $t = trim((string)$sv['title']);
            if ($t !== '' && !in_array($t, $list, true)) {
                $list[] = $t;
            }
        }
    }
    return $list;
}

function contact_rate_ok(): bool
{
    $rl = store_read('ratelimit', []);
    $k = mdj_ip_hash();
    $now = time();
    $hits = array_values(array_filter($rl[$k] ?? [], function ($t) use ($now) {
        return $t > $now - 3600;
    }));
    if (count($hits) >= 8) {
        return false;
    }
    $hits[] = $now;
    $rl[$k] = $hits;
    foreach ($rl as $key => $list) {
        if (!$list || max($list) < $now - 3600) {
            unset($rl[$key]);
        }
    }
    store_write('ratelimit', $rl);
    return true;
}

function contact_handle(array $C): array
{
    $v = [
        'name' => mdj_clean_str($_POST['name'] ?? '', 80),
        'email' => mdj_clean_str($_POST['email'] ?? '', 120),
        'service' => mdj_clean_str($_POST['service'] ?? '', 60),
        'budget' => mdj_clean_str($_POST['budget'] ?? '', 60),
        'message' => mdj_clean_str($_POST['message'] ?? '', 4000, true),
    ];
    $errors = [];
    // hidden "leave empty" box: people never see it, simple robots fill it in
    $trap = trim((string)($_POST['hp_field'] ?? ($_POST['website'] ?? '')));
    $tok = form_token_state((string)($_POST['t'] ?? ''));
    $spam = '';
    if ($trap !== '') {
        $spam = 'The hidden anti-robot box was filled in';
    } elseif ($tok === 'fast') {
        $spam = 'Sent less than 2 seconds after the page opened';
    } elseif ($tok === 'bad') {
        $errors['form'] = 'Something went wrong. Please reload the page and send the form again.';
    } elseif ($tok === 'old') {
        $errors['form'] = 'This page was open for a long time. Please reload it and send the form again (your text is still here — copy it first).';
    }
    if ($v['name'] === '') {
        $errors['name'] = 'Please add your name.';
    }
    if (filter_var($v['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Please add a valid email address.';
    }
    if (letters_count($v['message']) < 10) {
        $errors['message'] = 'Please write a little more about your project.';
    }
    if ($spam !== '' && !$errors) {
        // never lose a real message: keep it in the inbox under "Possible spam" instead of throwing it away
        if (contact_rate_ok()) {
            $all = messages_all();
            array_unshift($all, ['id' => mdj_new_id(), 'createdAt' => gmdate('c'), 'read' => false, 'spam' => true, 'spamReason' => $spam] + $v);
            messages_save($all);
        }
        return ['ok' => true, 'errors' => [], 'values' => [], 'name' => $v['name']];
    }
    if (!$errors && !contact_rate_ok()) {
        $errors['form'] = 'Too many messages from this connection. Please try again in an hour, or email directly.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'values' => $v];
    }
    $id = mdj_new_id();
    $msg = ['id' => $id, 'createdAt' => gmdate('c'), 'read' => false] + $v;
    $all = messages_all();
    array_unshift($all, $msg);
    if (!messages_save($all)) {
        return ['ok' => false, 'errors' => ['form' => 'Sorry, the message could not be saved. Please email directly.'], 'values' => $v];
    }
    // email a copy to the owner, then note in the inbox whether it was emailed
    $sent = notify_enquiry($C, $v);
    if ($sent['ok'] !== null) {
        $all = messages_all();
        foreach ($all as $i => $m) {
            if (($m['id'] ?? '') === $id) {
                $all[$i]['emailed'] = (bool)$sent['ok'];
                if (!$sent['ok']) {
                    $all[$i]['emailError'] = $sent['error'];
                }
                break;
            }
        }
        messages_save($all);
    }
    return ['ok' => true, 'errors' => [], 'values' => [], 'name' => $v['name']];
}
