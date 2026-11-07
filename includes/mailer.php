<?php
// not a page: block direct visits
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(404);
    exit;
}
/*
 * Email notifications for the contact form.
 * Sends through Gmail or any email account with SMTP (built in, no extra
 * libraries), or through the hosting's basic PHP mail(). Works on PHP 7.4+.
 * Settings live in data/mail.php; passwords are stored encrypted.
 */
require_once __DIR__ . '/core.php';

define('MDJ_CAFILE', __DIR__ . '/cacert.pem');

function mail_methods(): array
{
    return [
        'gmail' => 'Gmail',
        'smtp' => 'Email account (SMTP)',
        'php' => 'Basic hosting mail',
        'off' => 'Off',
    ];
}

function mail_defaults(): array
{
    return [
        'method' => 'php',
        'to' => '',
        'gmailUser' => '',
        'gmailPass' => '',
        'host' => '',
        'port' => 587,
        'security' => 'starttls',
        'user' => '',
        'smtpPass' => '',
        'fromEmail' => '',
        'fromName' => '',
    ];
}

function mail_config(): array
{
    $stored = store_read('mail', []);
    $cfg = array_merge(mail_defaults(), is_array($stored) ? $stored : []);
    if (!isset(mail_methods()[$cfg['method']])) {
        $cfg['method'] = 'php';
    }
    return $cfg;
}

/** settings for the Admin Panel: never includes the passwords themselves */
function mail_public(): array
{
    $cfg = mail_config();
    $out = $cfg;
    unset($out['gmailPass'], $out['smtpPass']);
    $out['hasGmailPass'] = mail_decrypt((string)$cfg['gmailPass']) !== '';
    $out['hasSmtpPass'] = mail_decrypt((string)$cfg['smtpPass']) !== '';
    $out['status'] = mail_status();
    $out['canSmtp'] = function_exists('stream_socket_client') && extension_loaded('openssl');
    return $out;
}

function mail_status(): array
{
    $s = store_read('mailstatus', []);
    return is_array($s) ? $s : [];
}

function mail_status_set(bool $ok, string $error, string $kind, string $method): void
{
    store_write('mailstatus', ['ok' => $ok, 'error' => $error, 'kind' => $kind, 'method' => $method, 'at' => gmdate('c')]);
}

/* -------------------------------------------------------------- passwords */

function mail_key(): string
{
    return hash('sha256', mdj_secret() . '|mail-v1', true);
}

function mail_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    if (function_exists('openssl_encrypt') && in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true)) {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', mail_key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct !== false && $tag !== '') {
            return 'g1:' . base64_encode($iv . $tag . $ct);
        }
    }
    return 'b1:' . base64_encode($plain);
}

function mail_decrypt(string $stored): string
{
    if ($stored === '') {
        return '';
    }
    if (strpos($stored, 'b1:') === 0) {
        return (string)base64_decode(substr($stored, 3), true);
    }
    if (strpos($stored, 'g1:') === 0 && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', mail_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
    return '';
}

/* ---------------------------------------------------------------- settings */

/** validate + save settings from the Admin Panel. Empty password fields keep the saved password. */
function mail_save(array $in, array &$errors): bool
{
    $cfg = mail_config();
    $method = (string)($in['method'] ?? '');
    if (!isset(mail_methods()[$method])) {
        $errors[] = ['field' => 'method', 'msg' => 'Choose how emails should be sent.'];
        return false;
    }
    $cfg['method'] = $method;

    $to = mdj_clean_str($in['to'] ?? '', 120);
    if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = ['field' => 'to', 'msg' => 'The “Send enquiries to” address is not a valid email address.'];
    }
    $cfg['to'] = $to;

    $gUser = strtolower(mdj_clean_str($in['gmailUser'] ?? '', 120));
    $gPass = preg_replace('/\s+/', '', (string)($in['gmailPass'] ?? ''));
    if ($method === 'gmail') {
        if ($gUser === '' || filter_var($gUser, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = ['field' => 'gmailUser', 'msg' => 'Enter your full Gmail address, e.g. yourname@gmail.com.'];
        }
        if ($gPass === '' && mail_decrypt((string)$cfg['gmailPass']) === '') {
            $errors[] = ['field' => 'gmailPass', 'msg' => 'Paste your 16-character App Password.'];
        }
        if ($gPass !== '' && strlen($gPass) !== 16) {
            $errors[] = ['field' => 'gmailPass', 'msg' => 'A Gmail App Password has 16 letters (spaces don’t matter). Check that you copied all of it.'];
        }
    }
    $cfg['gmailUser'] = $gUser;
    if ($gPass !== '') {
        $cfg['gmailPass'] = mail_encrypt(substr($gPass, 0, 64));
    }

    $host = strtolower(trim((string)($in['host'] ?? '')));
    $port = (int)($in['port'] ?? 0);
    $sec = (string)($in['security'] ?? 'starttls');
    $user = mdj_clean_str($in['user'] ?? '', 200);
    $sPass = (string)($in['smtpPass'] ?? '');
    $from = mdj_clean_str($in['fromEmail'] ?? '', 120);
    if ($method === 'smtp') {
        if ($host !== 'localhost' && !preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $host)) {
            $errors[] = ['field' => 'host', 'msg' => 'Enter the outgoing mail server, e.g. smtp.hostinger.com or mail.yourdomain.com.'];
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = ['field' => 'port', 'msg' => 'Enter the port number, usually 465 or 587.'];
        }
        if ($from === '' && filter_var($user, FILTER_VALIDATE_EMAIL) !== false) {
            $from = $user;
        }
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = ['field' => 'fromEmail', 'msg' => 'Enter the email address the website sends from (usually the same as the username).'];
        }
    }
    $cfg['host'] = substr($host, 0, 190);
    $cfg['port'] = ($port >= 1 && $port <= 65535) ? $port : 587;
    $cfg['security'] = in_array($sec, ['ssl', 'starttls', 'none'], true) ? $sec : 'starttls';
    $cfg['user'] = $user;
    if ($sPass !== '') {
        $cfg['smtpPass'] = mail_encrypt(substr($sPass, 0, 200));
    }
    $cfg['fromEmail'] = $from;
    $cfg['fromName'] = mdj_clean_str($in['fromName'] ?? '', 80);

    if ($errors) {
        return false;
    }
    return store_write('mail', $cfg);
}

function mail_recipient(array $cfg, array $C): string
{
    $to = trim((string)$cfg['to']);
    if ($to === '') {
        $to = trim((string)($C['contact']['email'] ?? ''));
    }
    return filter_var($to, FILTER_VALIDATE_EMAIL) !== false ? $to : '';
}

/* ------------------------------------------------------------------ sending */

/**
 * Send one email with the saved settings.
 * Returns ['ok' => bool, 'error' => string, 'detail' => string].
 */
function mail_send(array $cfg, string $to, string $subject, string $text, string $html, string $replyTo = '', string $replyName = '', string $siteName = 'Website'): array
{
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'There is no valid email address to send to. Add one in Email notifications (or in Contact & social links).', 'detail' => ''];
    }
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
        $replyTo = '';
    }
    $method = $cfg['method'];
    if ($method === 'off') {
        return ['ok' => false, 'error' => 'Email notifications are switched off.', 'detail' => ''];
    }
    $fromName = trim((string)$cfg['fromName']) !== '' ? (string)$cfg['fromName'] : $siteName . ' website';

    if ($method === 'php') {
        if (!function_exists('mail')) {
            return ['ok' => false, 'error' => 'This hosting has switched off PHP mail. Use Gmail or an email account (SMTP) instead.', 'detail' => ''];
        }
        $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $host = preg_replace('/^www\./i', '', (string)preg_replace('/[^A-Za-z0-9.\-]/', '', (string)$host));
        $from = 'no-reply@' . ($host !== '' && strpos($host, '.') !== false ? $host : 'localhost.localdomain');
        list($headers, $body) = mime_build($from, $fromName, $to, $replyTo, $replyName, $subject, $text, $html, false);
        $ok = @mail($to, mime_header_encode($subject), $body, $headers);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Your hosting could not send the email with its basic PHP mail. Use Gmail or an email account (SMTP) instead.', 'detail' => 'mail() returned false'];
        }
        return ['ok' => true, 'error' => '', 'detail' => 'Handed to the hosting mail system. If nothing arrives (check spam too), use Gmail or an email account (SMTP) — basic mail does not work on a local computer such as MAMP.'];
    }

    if (!function_exists('stream_socket_client') || !extension_loaded('openssl')) {
        return ['ok' => false, 'error' => 'This hosting cannot open secure email connections (the PHP “openssl” extension is missing). Ask your hosting to enable it, or use Basic hosting mail.', 'detail' => ''];
    }
    if ($method === 'gmail') {
        $t = ['host' => 'smtp.gmail.com', 'port' => 587, 'security' => 'starttls', 'user' => (string)$cfg['gmailUser'], 'pass' => mail_decrypt((string)$cfg['gmailPass']), 'gmail' => true];
        $from = (string)$cfg['gmailUser'];
        if ($t['pass'] === '') {
            return ['ok' => false, 'error' => 'Your Gmail App Password is missing. Paste it again in Email notifications.', 'detail' => ''];
        }
    } else {
        $t = ['host' => (string)$cfg['host'], 'port' => (int)$cfg['port'], 'security' => (string)$cfg['security'], 'user' => (string)$cfg['user'], 'pass' => mail_decrypt((string)$cfg['smtpPass']), 'gmail' => false];
        $from = (string)$cfg['fromEmail'];
    }
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'The sender address is missing or not valid. Check Email notifications.', 'detail' => ''];
    }
    list($headers, $body) = mime_build($from, $fromName, $to, $replyTo, $replyName, $subject, $text, $html, true);
    return smtp_deliver($t, $from, $to, $headers . "\r\n\r\n" . $body);
}

/* -------------------------------------------------------------- MIME build */

function mime_header_encode(string $s): string
{
    $s = trim(str_replace(["\r", "\n"], ' ', $s));
    if (!preg_match('/[^\x20-\x7E]/', $s)) {
        return $s;
    }
    if (function_exists('mb_encode_mimeheader')) {
        $prev = mb_internal_encoding();
        mb_internal_encoding('UTF-8');
        $enc = mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n ");
        mb_internal_encoding($prev);
        return $enc;
    }
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function mime_address(string $email, string $name = ''): string
{
    $name = trim(str_replace(['"', "\r", "\n"], ['', ' ', ' '], $name));
    if ($name === '') {
        return '<' . $email . '>';
    }
    if (!preg_match('/[^\x20-\x7E]/', $name) && !preg_match('/[(),:;<>@\[\]\\\\.]/', $name)) {
        return $name . ' <' . $email . '>';
    }
    return mime_header_encode($name) . ' <' . $email . '>';
}

/** returns [headers, body]; headers include To + Subject only when $full is true (SMTP) */
function mime_build(string $from, string $fromName, string $to, string $replyTo, string $replyName, string $subject, string $text, string $html, bool $full): array
{
    $domain = substr((string)strrchr($from, '@'), 1) ?: 'localhost';
    $bnd = 'mdj_' . bin2hex(random_bytes(12));
    $h = [];
    $h[] = 'Date: ' . date('r');
    $h[] = 'From: ' . mime_address($from, $fromName);
    if ($full) {
        $h[] = 'To: <' . $to . '>';
        $h[] = 'Subject: ' . mime_header_encode($subject);
    }
    if ($replyTo !== '') {
        $h[] = 'Reply-To: ' . mime_address($replyTo, $replyName);
    }
    $h[] = 'Message-ID: <' . bin2hex(random_bytes(10)) . '.' . time() . '@' . preg_replace('/[^A-Za-z0-9.\-]/', '', $domain) . '>';
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Content-Type: multipart/alternative; boundary="' . $bnd . '"';
    $h[] = 'X-Mailer: MD Jesun Website';
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $body = 'This is a message in MIME format.' . "\r\n\r\n"
        . '--' . $bnd . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($text), 76, "\r\n")) . "\r\n\r\n"
        . '--' . $bnd . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . rtrim(chunk_split(base64_encode($html), 76, "\r\n")) . "\r\n\r\n"
        . '--' . $bnd . '--' . "\r\n";
    return [implode("\r\n", $h), $body];
}

/* -------------------------------------------------------------------- SMTP */

function smtp_deliver(array $t, string $from, string $to, string $message): array
{
    $attempts = [[(string)$t['security'], (int)$t['port']]];
    if (!empty($t['gmail'])) {
        $attempts = [['starttls', 587], ['ssl', 465]]; // try the other port if one is blocked
    }
    $last = ['ok' => false, 'error' => 'Could not send the email.', 'detail' => '', 'stage' => 'connect'];
    foreach ($attempts as $a) {
        $r = smtp_try($t, $a[0], $a[1], $from, $to, $message, null);
        if (!$r['ok'] && $r['stage'] === 'tls' && is_file(MDJ_CAFILE)) {
            // some servers (for example MAMP on a Mac) have no list of trusted certificates: use ours
            $r2 = smtp_try($t, $a[0], $a[1], $from, $to, $message, MDJ_CAFILE);
            if ($r2['ok'] || $r2['stage'] !== 'tls') {
                $r = $r2;
            }
        }
        if ($r['ok']) {
            return $r;
        }
        $last = $r;
        if ($r['stage'] !== 'connect') {
            break; // a login or address problem will not be fixed by another port
        }
    }
    return $last;
}

function smtp_try(array $t, string $security, int $port, string $from, string $to, string $message, ?string $cafile): array
{
    $host = (string)$t['host'];
    $warn = [];
    set_error_handler(function ($no, $str) use (&$warn) {
        $warn[] = $str;
        return true;
    });
    try {
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true, 'allow_self_signed' => false];
        if ($cafile !== null) {
            $ssl['cafile'] = $cafile;
        }
        $ctx = stream_context_create(['ssl' => $ssl]);
        $errno = 0;
        $errstr = '';
        $fp = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return smtp_fail('connect', 'Could not connect to ' . $host . ' on port ' . $port . '. Check the server name and port — some hosting companies block email ports, so try the other port (465 with SSL, or 587 with STARTTLS).', $errstr !== '' ? $errstr : implode(' ', $warn));
        }
        stream_set_timeout($fp, 20);
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if ($security === 'ssl') {
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                fclose($fp);
                return smtp_fail('tls', 'A secure (SSL) connection to ' . $host . ' could not be made. If the port is 587, choose STARTTLS instead of SSL.', implode(' ', $warn));
            }
        }
        list($code, $resp) = smtp_read($fp);
        if ($code !== 220) {
            fclose($fp);
            return smtp_fail('connect', 'The email server did not say hello as expected. Check the server name and port.', $resp);
        }
        $me = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? ''));
        $me = ($me === '' || strpos($me, '.') === false) ? 'localhost' : $me;
        list($code, $caps) = smtp_cmd($fp, 'EHLO ' . $me);
        if ($code !== 250) {
            list($code, $caps) = smtp_cmd($fp, 'HELO ' . $me);
            if ($code !== 250) {
                fclose($fp);
                return smtp_fail('helo', 'The email server refused the greeting.', $caps);
            }
        }
        if ($security === 'starttls') {
            list($code, $resp) = smtp_cmd($fp, 'STARTTLS');
            if ($code !== 220) {
                fclose($fp);
                return smtp_fail('tls', 'This email server does not offer STARTTLS on port ' . $port . '. Try port 465 with SSL.', $resp);
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                fclose($fp);
                return smtp_fail('tls', 'A secure connection to ' . $host . ' could not be made.', implode(' ', $warn));
            }
            list($code, $caps) = smtp_cmd($fp, 'EHLO ' . $me);
            if ($code !== 250) {
                fclose($fp);
                return smtp_fail('helo', 'The email server refused the greeting after STARTTLS.', $caps);
            }
        }
        if ((string)$t['user'] !== '') {
            $authLine = '';
            foreach (preg_split('/\r?\n/', (string)$caps) ?: [] as $ln) {
                if (preg_match('/^250[ \-]AUTH[ =](.*)$/i', trim($ln), $m)) {
                    $authLine .= ' ' . strtoupper($m[1]);
                }
            }
            if (strpos($authLine, 'PLAIN') !== false || $authLine === '') {
                list($code, $resp) = smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $t['user'] . "\0" . $t['pass']));
            } else {
                list($code, $resp) = smtp_cmd($fp, 'AUTH LOGIN');
                if ($code === 334) {
                    list($code, $resp) = smtp_cmd($fp, base64_encode((string)$t['user']));
                }
                if ($code === 334) {
                    list($code, $resp) = smtp_cmd($fp, base64_encode((string)$t['pass']));
                }
            }
            if ($code !== 235) {
                fclose($fp);
                if ($code === 530 && stripos($resp, 'STARTTLS') !== false) {
                    return smtp_fail('auth', 'This email server needs a secure connection first: choose STARTTLS (port 587) or SSL (port 465).', $resp);
                }
                if (!empty($t['gmail'])) {
                    return smtp_fail('auth', 'Gmail did not accept the login. Use a 16-character App Password (not your normal Gmail password) and check the Gmail address. Create one at myaccount.google.com/apppasswords — it needs 2-Step Verification turned on.', $resp);
                }
                return smtp_fail('auth', 'The email server did not accept the username or password.', $resp);
            }
        }
        list($code, $resp) = smtp_cmd($fp, 'MAIL FROM:<' . $from . '>');
        if ($code !== 250) {
            fclose($fp);
            return smtp_fail('from', 'The email server did not accept the sender address ' . $from . '. Use the same address you log in with.', $resp);
        }
        list($code, $resp) = smtp_cmd($fp, 'RCPT TO:<' . $to . '>');
        if ($code !== 250 && $code !== 251) {
            fclose($fp);
            return smtp_fail('rcpt', 'The email server did not accept the address ' . $to . '.', $resp);
        }
        list($code, $resp) = smtp_cmd($fp, 'DATA');
        if ($code !== 354) {
            fclose($fp);
            return smtp_fail('data', 'The email server refused the message.', $resp);
        }
        $msg = str_replace(["\r\n", "\r"], "\n", $message);
        $msg = str_replace("\n", "\r\n", $msg);
        $msg = preg_replace('/^\./m', '..', $msg);
        if (!smtp_write($fp, $msg . "\r\n.\r\n")) {
            fclose($fp);
            return smtp_fail('data', 'The connection broke while sending the message.', '');
        }
        list($code, $resp) = smtp_read($fp);
        if ($code !== 250) {
            fclose($fp);
            return smtp_fail('data', 'The email server did not accept the message.', $resp);
        }
        smtp_cmd($fp, 'QUIT');
        fclose($fp);
        return ['ok' => true, 'error' => '', 'detail' => 'Sent through ' . $host . ' (port ' . $port . ').', 'stage' => 'done'];
    } finally {
        restore_error_handler();
    }
}

function smtp_fail(string $stage, string $error, string $detail): array
{
    $detail = trim(preg_replace('/\s+/', ' ', $detail));
    return ['ok' => false, 'error' => $error, 'detail' => substr($detail, 0, 400), 'stage' => $stage];
}

function smtp_write($fp, string $data): bool
{
    $len = strlen($data);
    $done = 0;
    while ($done < $len) {
        $n = fwrite($fp, substr($data, $done, 8192));
        if ($n === false || $n === 0) {
            return false;
        }
        $done += $n;
    }
    return true;
}

function smtp_read($fp): array
{
    $text = '';
    $code = 0;
    while (true) {
        $line = fgets($fp, 2048);
        if ($line === false) {
            $meta = stream_get_meta_data($fp);
            return [0, trim($text . (!empty($meta['timed_out']) ? ' (the server did not answer in time)' : ' (the connection was closed)'))];
        }
        $text .= $line;
        $code = (int)substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return [$code, trim($text)];
}

function smtp_cmd($fp, string $cmd): array
{
    if (!smtp_write($fp, $cmd . "\r\n")) {
        return [0, 'the connection was closed'];
    }
    return smtp_read($fp);
}

/* ------------------------------------------------------- email templates */

function mail_brand_hex(array $C): string
{
    $t = mdj_themes()[$C['settings']['theme'] ?? 'midnight'] ?? mdj_themes()['midnight'];
    return (string)$t['swatch'][0];
}

function mail_html(array $C, string $kicker, string $title, string $inner, string $foot): string
{
    $b = htmlspecialchars(mail_brand_hex($C), ENT_QUOTES, 'UTF-8');
    $e = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#eef1f5">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:28px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dfe5ec;font-family:Arial,Helvetica,sans-serif">'
        . '<tr><td style="background:#070b12;padding:24px 30px;border-bottom:3px solid ' . $b . '">'
        . '<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . $b . ';font-family:Consolas,Menlo,monospace">' . $e($kicker) . '</div>'
        . '<div style="font-size:23px;font-weight:bold;margin-top:8px;color:#ffffff;line-height:1.25">' . $e($title) . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:26px 30px;color:#1d2530;font-size:15px;line-height:1.6">' . $inner . '</td></tr>'
        . '<tr><td style="padding:16px 30px;background:#f7f9fb;color:#7b8594;font-size:12px;line-height:1.5;border-top:1px solid #e8edf2">' . $e($foot) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** the notification for a new enquiry: [subject, text, html] */
function enquiry_email(array $C, array $v): array
{
    $site = (string)($C['settings']['siteName'] ?? 'Website');
    $name = trim((string)$v['name']);
    $first = trim((string)strtok($name, ' ')) ?: $name;
    $subject = 'New enquiry from ' . $name . (trim((string)$v['service']) !== '' ? ' — ' . $v['service'] : '');
    $rows = [['Name', $v['name']], ['Email', $v['email']]];
    if (trim((string)$v['service']) !== '') {
        $rows[] = ['Service', $v['service']];
    }
    if (trim((string)$v['budget']) !== '') {
        $rows[] = ['Budget', $v['budget']];
    }
    $text = "New project enquiry from your website\n\n";
    foreach ($rows as $r) {
        $text .= $r[0] . ': ' . $r[1] . "\n";
    }
    $text .= "\nMessage:\n" . $v['message'] . "\n\n-- \nReply to this email to answer " . $first . " directly. The enquiry is also saved in your Admin Panel under Messages.";

    $e = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $b = $e(mail_brand_hex($C));
    $inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">';
    foreach ($rows as $r) {
        $val = $r[0] === 'Email' ? '<a href="mailto:' . $e($r[1]) . '" style="color:' . $b . ';text-decoration:none">' . $e($r[1]) . '</a>' : $e($r[1]);
        $inner .= '<tr><td style="padding:7px 0;width:90px;color:#7b8594;vertical-align:top">' . $e($r[0]) . '</td><td style="padding:7px 0;font-weight:bold;color:#1d2530">' . $val . '</td></tr>';
    }
    $inner .= '</table>';
    $inner .= '<div style="margin-top:20px;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#7b8594;font-family:Consolas,Menlo,monospace">Message</div>';
    $inner .= '<div style="margin-top:8px;padding:16px 18px;border-radius:12px;background:#f4f7fa;border:1px solid #e3e9ef;color:#1d2530">' . nl2br($e($v['message']), false) . '</div>';
    $reply = 'mailto:' . rawurlencode((string)$v['email']) . '?subject=' . rawurlencode('Re: your project enquiry');
    $inner .= '<div style="margin-top:22px"><a href="' . $e($reply) . '" style="display:inline-block;background:' . $b . ';color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:999px">Reply to ' . $e($first) . '</a></div>';
    $html = mail_html($C, 'New project enquiry', $name, $inner, 'Sent from the contact form on ' . $site . '. Just press Reply to answer — it goes straight to ' . $first . '. The enquiry is also saved in your Admin Panel → Messages.');
    return [$subject, $text, $html];
}

function test_email(array $C): array
{
    $site = (string)($C['settings']['siteName'] ?? 'Website');
    $subject = 'Test email from your website ✓';
    $text = "It works!\n\nThis is a test email from " . $site . "'s website. From now on, every enquiry from your contact form is emailed to you here.\n\nSent " . gmdate('j M Y, H:i') . ' UTC.';
    $inner = '<p style="margin:0 0 12px">It works! 🎉</p><p style="margin:0 0 12px">From now on, every enquiry from your contact form is emailed to you here, and you can answer it with the Reply button.</p><p style="margin:0;color:#7b8594;font-size:13px">Sent ' . gmdate('j M Y, H:i') . ' UTC.</p>';
    return [$subject, $text, mail_html($C, 'Email notifications', 'Your website can email you', $inner, 'You received this because someone clicked “Send test email” in the Admin Panel of ' . $site . '.')];
}

/** email a new enquiry to the owner; returns ['ok' => bool|null, 'error' => string] (null = switched off) */
function notify_enquiry(array $C, array $v): array
{
    $cfg = mail_config();
    if ($cfg['method'] === 'off') {
        return ['ok' => null, 'error' => ''];
    }
    list($subject, $text, $html) = enquiry_email($C, $v);
    $r = mail_send($cfg, mail_recipient($cfg, $C), $subject, $text, $html, (string)$v['email'], (string)$v['name'], (string)($C['settings']['siteName'] ?? 'Website'));
    mail_status_set($r['ok'], $r['ok'] ? '' : $r['error'], 'enquiry', $cfg['method']);
    return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : $r['error']];
}
