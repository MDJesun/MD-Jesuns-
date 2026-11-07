<?php
/*
 * Admin Panel API. Every action except "session" and "login" requires a
 * logged-in session, and every POST must carry the session's CSRF token.
 */
declare(strict_types=1);
define('MDJ_ROOT', dirname(__DIR__));
require MDJ_ROOT . '/includes/core.php';
require MDJ_ROOT . '/includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $msg, int $code = 400, array $extra = []): void
{
    out(['ok' => false, 'error' => $msg] + $extra, $code);
}

function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '') {
        return 0;
    }
    $n = (int)$v;
    $u = strtolower(substr($v, -1));
    if ($u === 'g') {
        $n *= 1024 * 1024 * 1024;
    } elseif ($u === 'm') {
        $n *= 1024 * 1024;
    } elseif ($u === 'k') {
        $n *= 1024;
    }
    return $n;
}

function upload_limit(): int
{
    $limits = [10 * 1024 * 1024];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $b = ini_bytes((string)ini_get($k));
        if ($b > 0) {
            $limits[] = $b;
        }
    }
    return min($limits);
}

/** largest video the hosting accepts (videos are not capped at 10 MB like images) */
function video_upload_limit(): int
{
    $limits = [1024 * 1024 * 1024];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $b = ini_bytes((string)ini_get($k));
        if ($b > 0) {
            $limits[] = $b;
        }
    }
    return min($limits);
}

/* ---------------------------------------------------------------- session */
session_name('MDJADMIN');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => (mdj_base() === '' ? '' : mdj_base()) . '/',
    'secure' => mdj_is_https(),
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();
if (!empty($_SESSION['uid']) && time() - (int)($_SESSION['last'] ?? 0) > 7200) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (!empty($_SESSION['uid'])) {
    $_SESSION['last'] = time();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$action = (string)($_GET['action'] ?? '');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
$in = [];
if ($method === 'POST') {
    $ctype = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ctype, 'application/json') !== false) {
        $in = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($in)) {
            $in = [];
        }
    } else {
        $in = $_POST;
    }
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($tok) || !hash_equals((string)$_SESSION['csrf'], $tok)) {
        fail('Your session expired. Please reload the page and try again.', 403);
    }
}

$auth = auth_get();
// a password change logs out every other device (session version check)
$sv = (string)($auth['changedAt'] ?? 'default');
if (!empty($_SESSION['uid']) && (($_SESSION['sv'] ?? '') !== $sv || strtolower((string)$_SESSION['uid']) !== strtolower((string)$auth['username']))) {
    $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
    session_regenerate_id(true);
}
$logged = !empty($_SESSION['uid']);

/* ------------------------------------------------------- public actions */
if ($action === 'session') {
    out(['ok' => true, 'loggedIn' => $logged, 'csrf' => $_SESSION['csrf'], 'user' => $logged ? $auth['username'] : null]);
}

if ($action === 'login') {
    if ($method !== 'POST') {
        fail('Method not allowed.', 405);
    }
    $wait = throttle_locked();
    if ($wait > 0) {
        fail('Too many wrong attempts. Please wait ' . (int)ceil($wait / 60) . ' minute(s) and try again.', 429);
    }
    $u = trim((string)($in['username'] ?? ''));
    $p = (string)($in['password'] ?? '');
    if ($u === '' || $p === '' || !auth_check($u, $p)) {
        throttle_fail();
        usleep(400000);
        fail('Wrong username or password.', 401);
    }
    throttle_clear();
    session_regenerate_id(true);
    $_SESSION['uid'] = $auth['username'];
    $_SESSION['sv'] = $sv;
    $_SESSION['last'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}

if ($action === 'logout') {
    if ($method !== 'POST') {
        fail('Method not allowed.', 405);
    }
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}

if (!$logged) {
    fail('Please log in.', 401);
}

/* ---------------------------------------------------- logged-in actions */
switch ($action) {
    case 'bootstrap':
        $msgs = messages_all();
        $unread = count(array_filter($msgs, function ($m) {
            return empty($m['read']) && empty($m['spam']);
        }));
        out([
            'ok' => true,
            'schema' => mdj_schema(),
            'content' => content_load(),
            'themes' => mdj_themes(),
            'user' => $auth['username'],
            'isDefault' => !empty($auth['isDefault']),
            'unread' => $unread,
            'messages' => count(array_filter($msgs, function ($m) {
                return empty($m['spam']);
            })),
            'writable' => mdj_writable(),
            'maxUpload' => upload_limit(),
            'maxVideoUpload' => video_upload_limit(),
            'savedAt' => (store_read('content', [])['_meta']['savedAt'] ?? null),
            'mail' => mail_public(),
        ]);

    case 'mail':
        out(['ok' => true, 'mail' => mail_public()]);

    case 'mail_save':
    case 'mail_test':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        if (isset($in['settings']) && is_array($in['settings'])) {
            $errors = [];
            if (!mail_save($in['settings'], $errors)) {
                if ($errors) {
                    out(['ok' => false, 'error' => $errors[0]['msg'], 'errors' => $errors], 422);
                }
                fail('Could not save the email settings. The data folder may not be writable (see the Dashboard).', 500);
            }
        }
        if ($action === 'mail_save') {
            out(['ok' => true, 'mail' => mail_public()]);
        }
        $cfg = mail_config();
        $C = content_load();
        if ($cfg['method'] === 'off') {
            out(['ok' => false, 'error' => 'Email notifications are switched off, so no test email was sent.', 'mail' => mail_public()]);
        }
        $to = mail_recipient($cfg, $C);
        list($subject, $text, $html) = test_email($C);
        $r = mail_send($cfg, $to, $subject, $text, $html, '', '', (string)($C['settings']['siteName'] ?? 'Website'));
        mail_status_set($r['ok'], $r['ok'] ? '' : $r['error'], 'test', $cfg['method']);
        out(['ok' => $r['ok'], 'sent' => $r['ok'], 'to' => $to, 'error' => $r['ok'] ? '' : $r['error'], 'detail' => $r['detail'] ?? '', 'mail' => mail_public()]);

    case 'save':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $section = (string)($in['section'] ?? '');
        $errors = [];
        if (!content_save_section($section, $in['data'] ?? null, $errors)) {
            if ($errors) {
                out(['ok' => false, 'error' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
            }
            fail('Could not save. The website files may not be writable on your hosting (see the Dashboard).', 500);
        }
        $content = content_load();
        out(['ok' => true, 'section' => $section, 'data' => $content[$section], 'savedAt' => gmdate('c')]);

    case 'theme':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $theme = (string)($in['theme'] ?? '');
        if (!isset(mdj_themes()[$theme])) {
            fail('Unknown theme.');
        }
        $content = content_load();
        $settings = $content['settings'];
        $settings['theme'] = $theme;
        $errors = [];
        if (!content_save_section('settings', $settings, $errors)) {
            fail($errors ? $errors[0]['msg'] : 'Could not save the theme.', 500);
        }
        out(['ok' => true, 'theme' => $theme, 'data' => content_load()['settings']]);

    case 'upload':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            fail('No image was received. It may be larger than your hosting allows (' . round(upload_limit() / 1048576, 1) . ' MB).');
        }
        $f = $_FILES['file'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'This image is larger than your hosting allows (' . round(upload_limit() / 1048576, 1) . ' MB).',
                UPLOAD_ERR_FORM_SIZE => 'This image is too large.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No image was received.',
            ];
            fail($map[$f['error']] ?? 'The upload failed (code ' . (int)$f['error'] . ').');
        }
        if ((int)$f['size'] > upload_limit()) {
            fail('This image is larger than ' . round(upload_limit() / 1048576, 1) . ' MB. Please use a smaller file.');
        }
        $tmp = (string)$f['tmp_name'];
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
        }
        $info = @getimagesize($tmp);
        if ($mime === '' && $info) {
            $mime = (string)$info['mime'];
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime]) || !$info) {
            fail('Please upload a JPG, PNG, WebP or GIF image.');
        }
        $ext = $allowed[$mime];
        $stem = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo((string)$f['name'], PATHINFO_FILENAME)));
        $stem = trim(substr($stem, 0, 40), '-');
        if ($stem === '') {
            $stem = 'image';
        }
        if (!is_dir(MDJ_UPLOADS)) {
            @mkdir(MDJ_UPLOADS, 0755, true);
        }
        $name = $stem . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = MDJ_UPLOADS . '/' . $name;
        if (!@move_uploaded_file($tmp, $dest)) {
            fail('Could not save the image. The uploads folder may not be writable on your hosting.', 500);
        }
        @chmod($dest, 0644);
        optimize_image($dest, $mime);
        $dim = @getimagesize($dest) ?: [0, 0];
        out(['ok' => true, 'url' => 'uploads/' . $name, 'width' => $dim[0], 'height' => $dim[1], 'size' => filesize($dest)]);

    case 'upload_video':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $vmb = round(video_upload_limit() / 1048576);
        $tooBig = 'This video is larger than your hosting allows (' . $vmb . ' MB). Upload it to YouTube (it can be “Unlisted”) or Vimeo and paste the link instead.';
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            fail($tooBig);
        }
        $f = $_FILES['file'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => $tooBig,
                UPLOAD_ERR_FORM_SIZE => $tooBig,
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No video was received.',
            ];
            fail($map[$f['error']] ?? 'The upload failed (code ' . (int)$f['error'] . ').');
        }
        if ((int)$f['size'] > video_upload_limit()) {
            fail($tooBig);
        }
        $tmp = (string)$f['tmp_name'];
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($tmp);
        }
        $allowed = ['video/mp4' => 'mp4', 'video/x-m4v' => 'm4v', 'video/webm' => 'webm', 'video/quicktime' => 'mov'];
        if ($mime === '') {
            // no finfo on this hosting: fall back to the file's own signature
            $head = (string)@file_get_contents($tmp, false, null, 0, 16);
            if (substr($head, 4, 4) === 'ftyp') {
                $mime = substr($head, 8, 2) === 'qt' ? 'video/quicktime' : 'video/mp4';
            } elseif (substr($head, 0, 4) === "\x1A\x45\xDF\xA3") {
                $mime = 'video/webm';
            }
        }
        if (!isset($allowed[$mime])) {
            fail('Please upload an MP4, MOV or WebM video.');
        }
        $ext = $allowed[$mime];
        $stem = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo((string)$f['name'], PATHINFO_FILENAME)));
        $stem = trim(substr($stem, 0, 40), '-');
        if ($stem === '') {
            $stem = 'video';
        }
        if (!is_dir(MDJ_UPLOADS)) {
            @mkdir(MDJ_UPLOADS, 0755, true);
        }
        $name = $stem . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!@move_uploaded_file($tmp, MDJ_UPLOADS . '/' . $name)) {
            fail('Could not save the video. The uploads folder may not be writable on your hosting.', 500);
        }
        @chmod(MDJ_UPLOADS . '/' . $name, 0644);
        out(['ok' => true, 'url' => 'uploads/' . $name, 'size' => filesize(MDJ_UPLOADS . '/' . $name)]);

    case 'media':
        $used = images_in_use(content_load());
        $files = [];
        foreach (glob(MDJ_UPLOADS . '/*') ?: [] as $p) {
            $b = basename($p);
            if (!is_file($p) || $b === 'index.html' || $b[0] === '.') {
                continue;
            }
            $url = 'uploads/' . $b;
            $files[] = ['url' => $url, 'name' => $b, 'size' => filesize($p), 'time' => gmdate('c', filemtime($p)), 'usedIn' => $used[$url] ?? []];
        }
        usort($files, function ($a, $b) {
            return strcmp($b['time'], $a['time']);
        });
        out(['ok' => true, 'files' => $files]);

    case 'media_delete':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $url = (string)($in['url'] ?? '');
        if (!preg_match('~^uploads/[A-Za-z0-9._\-]+$~', $url) || strpos($url, '..') !== false) {
            fail('That file cannot be deleted.');
        }
        $used = images_in_use(content_load());
        if (!empty($used[$url])) {
            fail('This file is still used on your website: ' . implode(', ', $used[$url]) . '. Replace it there first.');
        }
        $path = MDJ_ROOT . '/' . $url;
        if (!is_file($path) || !@unlink($path)) {
            fail('Could not delete the file.', 500);
        }
        out(['ok' => true]);

    case 'account':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $current = (string)($in['current'] ?? '');
        $user = trim((string)($in['username'] ?? ''));
        $pass = (string)($in['password'] ?? '');
        $confirm = (string)($in['confirm'] ?? '');
        if (!auth_check((string)$auth['username'], $current)) {
            usleep(400000);
            fail('Your current password is not correct.', 400, ['field' => 'current']);
        }
        if (!preg_match('/^[A-Za-z0-9._@\-]{3,40}$/', $user)) {
            fail('Usernames can use 3–40 letters, numbers, dots, dashes, underscores or @.', 400, ['field' => 'username']);
        }
        $problem = password_problem($pass, $user);
        if ($problem) {
            fail($problem, 400, ['field' => 'password']);
        }
        if (!hash_equals($pass, $confirm)) {
            fail('The two new passwords do not match.', 400, ['field' => 'confirm']);
        }
        if (!auth_update($user, $pass)) {
            fail('Could not save the new login. The data folder may not be writable.', 500);
        }
        $fresh = auth_get();
        session_regenerate_id(true);
        $_SESSION['uid'] = $user;
        $_SESSION['sv'] = (string)($fresh['changedAt'] ?? 'default');
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        out(['ok' => true, 'user' => $user, 'csrf' => $_SESSION['csrf']]);

    case 'messages':
        out(['ok' => true, 'messages' => messages_all()]);

    case 'message':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        $id = (string)($in['id'] ?? '');
        $op = (string)($in['op'] ?? '');
        $all = messages_all();
        $found = false;
        foreach ($all as $i => $m) {
            if (($m['id'] ?? '') !== $id) {
                continue;
            }
            $found = true;
            if ($op === 'read' || $op === 'unread') {
                $all[$i]['read'] = ($op === 'read');
            } elseif ($op === 'notspam') {
                unset($all[$i]['spam'], $all[$i]['spamReason']);
                $all[$i]['read'] = false;
            } elseif ($op === 'spam') {
                $all[$i]['spam'] = true;
                $all[$i]['spamReason'] = 'Marked as spam by you';
            } elseif ($op === 'delete') {
                array_splice($all, $i, 1);
            }
            break;
        }
        if (!$found) {
            fail('Message not found.', 404);
        }
        messages_save($all);
        out(['ok' => true, 'messages' => messages_all()]);

    case 'spam_clear':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        messages_save(array_values(array_filter(messages_all(), function ($m) {
            return empty($m['spam']);
        })));
        out(['ok' => true, 'messages' => messages_all()]);

    case 'backups':
        out(['ok' => true, 'backups' => content_backups()]);

    case 'restore':
        if ($method !== 'POST') {
            fail('Method not allowed.', 405);
        }
        if (!content_restore((string)($in['file'] ?? ''))) {
            fail('Could not restore that backup.');
        }
        out(['ok' => true, 'content' => content_load()]);
}

fail('Unknown action.', 404);

/* -------------------------------------------------------- image helper */
function optimize_image(string $path, string $mime): void
{
    if (!extension_loaded('gd') || $mime === 'image/gif') {
        return;
    }
    try {
        $info = @getimagesize($path);
        if (!$info) {
            return;
        }
        list($w, $h) = $info;
        $max = 2400;
        $big = ($w > $max || $h > $max);
        $heavyJpeg = ($mime === 'image/jpeg' && filesize($path) > 1500000);
        if (!$big && !$heavyJpeg) {
            return;
        }
        $load = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
        if (!isset($load[$mime]) || !function_exists($load[$mime])) {
            return;
        }
        $src = @$load[$mime]($path);
        if (!$src) {
            return;
        }
        $scale = $big ? min($max / $w, $max / $h) : 1;
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime !== 'image/jpeg') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $tmp = $path . '.opt';
        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($dst, $tmp, 86);
        } elseif ($mime === 'image/png') {
            $ok = imagepng($dst, $tmp, 7);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ok = imagewebp($dst, $tmp, 88);
        }
        imagedestroy($src);
        imagedestroy($dst);
        if ($ok && is_file($tmp) && filesize($tmp) > 0) {
            @rename($tmp, $path);
            @chmod($path, 0644);
        } else {
            @unlink($tmp);
        }
    } catch (Throwable $e) {
        // keep the original file
    }
}
