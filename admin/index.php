<?php
declare(strict_types=1);
define('MDJ_ROOT', dirname(__DIR__));
require MDJ_ROOT . '/includes/core.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Robots-Tag: noindex, nofollow');

$theme = content_load()['settings']['theme'];
$v = function (string $f): string {
    $p = __DIR__ . '/' . $f;
    return $f . (is_file($p) ? '?v=' . filemtime($p) : '');
};
$t = '../assets/css/themes.css';
$tv = is_file(MDJ_ROOT . '/assets/css/themes.css') ? '?v=' . filemtime(MDJ_ROOT . '/assets/css/themes.css') : '';
?>
<!doctype html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Panel</title>
<link rel="icon" href="../assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=JetBrains+Mono:wght@400;500&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($t . $tv, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($v('admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div id="app"><div class="boot"><span class="spinner"></span>Loading Admin Panel…</div></div>
<noscript><p style="padding: 40px; font-family: sans-serif">Please turn on JavaScript to use the Admin Panel.</p></noscript>
<script src="<?= htmlspecialchars($v('admin.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
