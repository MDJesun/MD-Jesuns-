<?php
// not a page: block direct visits
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
/*
 * Core: storage, content loading/saving, validation, login security.
 * Shared by the public website and the Admin Panel. Works on PHP 7.4+.
 */

if (!defined('MDJ_ROOT')) {
    define('MDJ_ROOT', dirname(__DIR__));
}
define('MDJ_DATA', MDJ_ROOT . '/data');
define('MDJ_UPLOADS', MDJ_ROOT . '/uploads');
define('MDJ_GUARD', "<?php http_response_code(404); exit; ?>\n");

require_once __DIR__ . '/schema.php';

/* ------------------------------------------------------------------ storage */

function store_path(string $name): string
{
    return MDJ_DATA . '/' . $name . '.php';
}

function store_read(string $name, $default = null)
{
    $file = store_path($name);
    if (!is_file($file)) {
        return $default;
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return $default;
    }
    $pos = strpos($raw, "\n");
    $data = json_decode($pos === false ? '' : substr($raw, $pos + 1), true);
    return is_array($data) ? $data : $default;
}

function store_write(string $name, array $data): bool
{
    if (!is_dir(MDJ_DATA)) {
        @mkdir(MDJ_DATA, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    if ($json === false) {
        return false;
    }
    $file = store_path($name);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, MDJ_GUARD . $json, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    return true;
}

function mdj_secret(): string
{
    $s = store_read('secret');
    if (is_array($s) && !empty($s['key'])) {
        return (string)$s['key'];
    }
    $key = bin2hex(random_bytes(32));
    store_write('secret', ['key' => $key]);
    return $key;
}

function mdj_writable(): array
{
    $problems = [];
    foreach (['data' => MDJ_DATA, 'uploads' => MDJ_UPLOADS, 'data/backups' => MDJ_DATA . '/backups'] as $label => $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $problems[] = $label;
        }
    }
    return $problems;
}

/* ------------------------------------------------------------------ content */

function content_load(): array
{
    $defaults = mdj_defaults();
    $stored = store_read('content', []);
    $out = [];
    foreach (mdj_schema() as $key => $sec) {
        $d = $defaults[$key] ?? (!empty($sec['list']) ? [] : []);
        if (!array_key_exists($key, $stored) || !is_array($stored[$key])) {
            $out[$key] = $d;
            continue;
        }
        if (!empty($sec['list'])) {
            $out[$key] = array_values($stored[$key]);
        } else {
            $out[$key] = array_merge($d, $stored[$key]);
        }
    }
    if (!isset(mdj_themes()[$out['settings']['theme'] ?? ''])) {
        $out['settings']['theme'] = 'midnight';
    }
    return content_normalize($out);
}

/**
 * Small clean-ups applied every time content is loaded, so older saved
 * content keeps working with newer versions of the website.
 */
function content_normalize(array $c): array
{
    // Home section labels are numbered automatically now ("01 — Selected work" → "Selected work")
    foreach (['aboutEyebrow', 'workEyebrow', 'reelEyebrow', 'servicesEyebrow', 'quotesEyebrow', 'ctaEyebrow'] as $k) {
        if (isset($c['home'][$k]) && is_string($c['home'][$k])) {
            $c['home'][$k] = mdj_strip_number($c['home'][$k]);
        }
    }
    $c['home']['sections'] = mdj_clean_order($c['home']['sections'] ?? [], mdj_home_sections());
    return $c;
}

/** "01 — Selected work" → "Selected work" */
function mdj_strip_number(string $s): string
{
    $out = preg_replace('/^\s*\d{1,2}\s*[—–\-:.|·]+\s*/u', '', $s);
    return is_string($out) && $out !== '' ? $out : $s;
}

/** a list like [{k:'about', on:true}, …] containing every known key exactly once */
function mdj_clean_order($v, array $options): array
{
    $out = [];
    $seen = [];
    if (is_array($v)) {
        foreach ($v as $item) {
            if (!is_array($item)) {
                continue;
            }
            $k = isset($item['k']) && is_string($item['k']) ? $item['k'] : '';
            if ($k === '' || !isset($options[$k]) || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $on = $item['on'] ?? true;
            $out[] = ['k' => $k, 'on' => ($on === true || $on === 1 || $on === '1' || $on === 'true')];
        }
    }
    foreach (array_keys($options) as $k) {
        if (!isset($seen[$k])) {
            $out[] = ['k' => $k, 'on' => true];
        }
    }
    return $out;
}

function content_save_section(string $section, $data, array &$errors): bool
{
    $schema = mdj_schema();
    if (!isset($schema[$section])) {
        $errors[] = ['path' => $section, 'msg' => 'Unknown section.'];
        return false;
    }
    $sec = $schema[$section];
    if (!empty($sec['list'])) {
        $clean = mdj_clean_list(is_array($data) ? $data : [], $sec, $section, $errors);
    } else {
        $clean = mdj_clean_object(is_array($data) ? $data : [], $sec['fields'], $section, $errors);
    }
    if ($errors) {
        return false;
    }
    $content = store_read('content', []);
    content_backup();
    $content[$section] = $clean;
    $content['_meta'] = ['savedAt' => gmdate('c')];
    return store_write('content', $content);
}

function content_backup(bool $force = false): void
{
    $file = store_path('content');
    if (!is_file($file)) {
        return;
    }
    $dir = MDJ_DATA . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $latest = glob($dir . '/content-*.php') ?: [];
    sort($latest);
    $last = end($latest);
    // one backup per minute is plenty
    if (!$force && $last && filemtime($last) > time() - 60) {
        return;
    }
    @copy($file, $dir . '/content-' . gmdate('Ymd-His') . '.php');
    $all = glob($dir . '/content-*.php') ?: [];
    sort($all);
    while (count($all) > 25) {
        @unlink(array_shift($all));
    }
}

function content_backups(): array
{
    $all = glob(MDJ_DATA . '/backups/content-*.php') ?: [];
    rsort($all);
    $out = [];
    foreach ($all as $f) {
        $out[] = ['file' => basename($f), 'time' => gmdate('c', filemtime($f)), 'size' => filesize($f)];
    }
    return $out;
}

function content_restore(string $file): bool
{
    if (!preg_match('/^content-\d{8}-\d{6}\.php$/', $file)) {
        return false;
    }
    $path = MDJ_DATA . '/backups/' . $file;
    if (!is_file($path)) {
        return false;
    }
    $raw = file_get_contents($path);
    $pos = strpos((string)$raw, "\n");
    $data = json_decode($pos === false ? '' : substr($raw, $pos + 1), true);
    if (!is_array($data)) {
        return false;
    }
    content_backup(true);
    return store_write('content', $data);
}

/* --------------------------------------------------------------- validation */

function mdj_substr(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

function mdj_clean_str($v, int $max, bool $multiline = false): string
{
    if (is_bool($v) || is_array($v) || is_object($v) || $v === null) {
        return '';
    }
    $s = (string)$v;
    if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $s);
    $s = trim((string)$s);
    if ($multiline) {
        $s = preg_replace("/\n{3,}/", "\n\n", $s);
    }
    return mdj_substr((string)$s, $max);
}

/** returns [value, errorMessage|null] */
function mdj_clean_url($v, array $schemes = ['http', 'https']): array
{
    $s = mdj_clean_str($v, 500);
    if ($s === '') {
        return ['', null];
    }
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $s) && preg_match('~^(www\.)?[a-z0-9\-]+(\.[a-z0-9\-]+)+([/?#].*)?$~i', $s)) {
        $s = 'https://' . $s;
    }
    $scheme = strtolower((string)parse_url($s, PHP_URL_SCHEME));
    if (!in_array($scheme, $schemes, true) || filter_var($s, FILTER_VALIDATE_URL) === false) {
        return [$s, 'must be a full web address starting with https://'];
    }
    return [$s, null];
}

function mdj_clean_image($v): array
{
    $s = mdj_clean_str($v, 300);
    if ($s === '') {
        return ['', null];
    }
    if (preg_match('~^(uploads|assets/img)/[A-Za-z0-9._/\-]+$~', $s) && strpos($s, '..') === false) {
        return [$s, null];
    }
    list($u, $err) = mdj_clean_url($s, ['https']);
    return $err ? ['', 'must be an uploaded image'] : [$u, null];
}

/** a video: an uploaded file (uploads/…mp4) or a full https:// link (YouTube, Vimeo, Google Drive, .mp4) */
function mdj_clean_video($v): array
{
    $s = mdj_clean_str($v, 400);
    if ($s === '') {
        return ['', null];
    }
    if (preg_match('~^uploads/[A-Za-z0-9._\-]+\.(mp4|m4v|webm|mov)$~i', $s) && strpos($s, '..') === false) {
        return [$s, null];
    }
    list($u, $err) = mdj_clean_url($s, ['https']);
    return $err ? ['', 'must be a YouTube, Vimeo or Google Drive link starting with https://, or an uploaded video'] : [$u, null];
}

function mdj_new_id(): string
{
    return substr(bin2hex(random_bytes(6)), 0, 10);
}

function mdj_clean_object(array $data, array $fields, string $path, array &$errors): array
{
    $out = [];
    foreach ($fields as $f) {
        if (empty($f['k'])) {
            continue;
        }
        $k = $f['k'];
        $v = $data[$k] ?? null;
        $label = $f['label'] ?? $k;
        switch ($f['type']) {
            case 'textarea':
                $out[$k] = mdj_clean_str($v, $f['max'] ?? 1500, true);
                break;
            case 'url':
                list($val, $err) = mdj_clean_url($v);
                if ($err) {
                    $errors[] = ['path' => "$path.$k", 'msg' => "$label $err"];
                }
                $out[$k] = $val;
                break;
            case 'email':
                $s = mdj_clean_str($v, 120);
                if ($s !== '' && filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = ['path' => "$path.$k", 'msg' => "$label must be a valid email address"];
                }
                $out[$k] = $s;
                break;
            case 'tel':
                $s = mdj_clean_str($v, 30);
                $s = preg_replace('/[^0-9+\-\s()]/', '', $s);
                if ($s !== '' && strlen(preg_replace('/\D/', '', $s)) < 6) {
                    $errors[] = ['path' => "$path.$k", 'msg' => "$label looks too short. Include the country code."];
                }
                $out[$k] = trim((string)$s);
                break;
            case 'image':
                list($val, $err) = mdj_clean_image($v);
                if ($err) {
                    $errors[] = ['path' => "$path.$k", 'msg' => "$label $err"];
                }
                $out[$k] = $val;
                break;
            case 'video':
                list($val, $err) = mdj_clean_video($v);
                if ($err) {
                    $errors[] = ['path' => "$path.$k", 'msg' => "$label $err"];
                }
                $out[$k] = $val;
                break;
            case 'select':
                $s = is_string($v) ? $v : '';
                $opts = array_keys($f['options'] ?? []);
                $out[$k] = in_array($s, $opts, true) ? $s : ($opts[0] ?? '');
                break;
            case 'toggle':
                $out[$k] = ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on');
                break;
            case 'list':
                $out[$k] = mdj_clean_list(is_array($v) ? $v : [], $f, "$path.$k", $errors);
                break;
            case 'order':
                $out[$k] = mdj_clean_order($v, $f['options'] ?? []);
                break;
            default:
                $out[$k] = mdj_clean_str($v, $f['max'] ?? 200);
        }
    }
    return $out;
}

function mdj_clean_list(array $items, array $def, string $path, array &$errors): array
{
    $out = [];
    $max = $def['max'] ?? 100;
    $seen = [];
    foreach (array_values($items) as $i => $item) {
        if (count($out) >= $max) {
            break;
        }
        if (!is_array($item)) {
            continue;
        }
        $clean = mdj_clean_object($item, $def['fields'], "$path.$i", $errors);
        $id = (isset($item['id']) && is_string($item['id']) && preg_match('/^[a-z0-9]{2,16}$/', $item['id'])) ? $item['id'] : mdj_new_id();
        if (isset($seen[$id])) {
            $id = mdj_new_id();
        }
        $seen[$id] = true;
        $out[] = ['id' => $id] + $clean;
    }
    return $out;
}

/* ------------------------------------------------------------------ helpers */

function mdj_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** URL path of the website root, e.g. "" or "/portfolio" (works in sub-folders). */
function mdj_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    $base = ($dir === '.' || $dir === '/') ? '' : $dir;
    return $base;
}

function mdj_ip_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    return substr(hash_hmac('sha256', $ip, mdj_secret()), 0, 24);
}

/* ---------------------------------------------------------- authentication */

function auth_get(): array
{
    $a = store_read('auth');
    if (!is_array($a) || empty($a['username']) || empty($a['hash'])) {
        // first run: create the default test login (admin / Admin@123)
        $a = ['username' => 'admin', 'hash' => password_hash('Admin@123', PASSWORD_DEFAULT), 'isDefault' => true, 'changedAt' => null];
        store_write('auth', $a);
    }
    return $a;
}

function auth_check(string $user, string $pass): bool
{
    $a = auth_get();
    $okUser = hash_equals(strtolower((string)$a['username']), strtolower($user));
    $okPass = password_verify($pass, (string)$a['hash']);
    if ($okUser && $okPass && password_needs_rehash((string)$a['hash'], PASSWORD_DEFAULT)) {
        $a['hash'] = password_hash($pass, PASSWORD_DEFAULT);
        store_write('auth', $a);
    }
    return $okUser && $okPass;
}

function auth_update(string $user, string $pass): bool
{
    $a = auth_get();
    $a['username'] = $user;
    $a['hash'] = password_hash($pass, PASSWORD_DEFAULT);
    $a['isDefault'] = false;
    $a['changedAt'] = gmdate('c') . '.' . bin2hex(random_bytes(3));
    return store_write('auth', $a);
}

function password_problem(string $pass, string $user): ?string
{
    if (strlen($pass) < 10) {
        return 'Use at least 10 characters.';
    }
    if (!preg_match('/[a-z]/', $pass) || !preg_match('/[A-Z]/', $pass) || !preg_match('/\d/', $pass)) {
        return 'Mix upper-case letters, lower-case letters and numbers.';
    }
    if ($pass === 'Admin@123' || stripos($pass, $user) !== false || stripos($pass, 'password') !== false) {
        return 'Choose something less easy to guess.';
    }
    return null;
}

/* login throttling: 5 wrong tries locks that address for 15 minutes */
function throttle_state(): array
{
    $all = store_read('attempts', []);
    $now = time();
    foreach ($all as $k => $v) {
        if (($v['t'] ?? 0) < $now - 86400) {
            unset($all[$k]);
        }
    }
    return $all;
}

function throttle_locked(): int
{
    $all = throttle_state();
    $me = $all[mdj_ip_hash()] ?? null;
    if ($me && ($me['lock'] ?? 0) > time()) {
        return (int)$me['lock'] - time();
    }
    return 0;
}

function throttle_fail(): void
{
    $all = throttle_state();
    $k = mdj_ip_hash();
    $me = $all[$k] ?? ['n' => 0, 't' => time(), 'lock' => 0];
    if ($me['t'] < time() - 900) {
        $me = ['n' => 0, 't' => time(), 'lock' => 0];
    }
    $me['n']++;
    if ($me['n'] >= 5) {
        $me['lock'] = time() + 900;
        $me['n'] = 0;
        $me['t'] = time();
    }
    $all[$k] = $me;
    store_write('attempts', $all);
}

function throttle_clear(): void
{
    $all = throttle_state();
    unset($all[mdj_ip_hash()]);
    store_write('attempts', $all);
}

/* ------------------------------------------------------------- messages */

function messages_all(): array
{
    $m = store_read('messages', []);
    return is_array($m) ? array_values($m) : [];
}

function messages_save(array $list): bool
{
    return store_write('messages', array_values(array_slice($list, 0, 500)));
}

/* ------------------------------------------------------------- image usage */

function images_in_use(array $content): array
{
    $used = [];
    $walk = function ($fields, $value, $where) use (&$walk, &$used) {
        foreach ($fields as $f) {
            if (empty($f['k'])) {
                continue;
            }
            $v = $value[$f['k']] ?? null;
            if (($f['type'] === 'image' || $f['type'] === 'video') && is_string($v) && $v !== '') {
                $used[$v][] = $where . ' → ' . $f['label'];
            } elseif ($f['type'] === 'list' && is_array($v)) {
                foreach ($v as $i => $item) {
                    $walk($f['fields'], $item, $where . ' #' . ($i + 1));
                }
            }
        }
    };
    foreach (mdj_schema() as $key => $sec) {
        if (!empty($sec['list'])) {
            foreach (($content[$key] ?? []) as $i => $item) {
                $name = $item[$sec['itemTitle']] ?? ('#' . ($i + 1));
                $walk($sec['fields'], $item, $sec['label'] . ': ' . $name);
            }
        } else {
            $walk($sec['fields'], $content[$key] ?? [], $sec['label']);
        }
    }
    return $used;
}
