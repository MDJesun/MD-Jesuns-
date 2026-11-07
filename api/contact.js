/*
 * Contact form for the static MD Jesun website (Vercel Function, Node.js).
 * Emails every enquiry to you through Gmail (or any SMTP server). No packages needed.
 *
 * Set these in Vercel → your project → Settings → Environment Variables:
 *   GMAIL_USER           your Gmail address, e.g. you@gmail.com
 *   GMAIL_APP_PASSWORD   a 16-letter Google App Password (not your normal password)
 *   CONTACT_TO           (optional) where enquiries go; defaults to GMAIL_USER
 * Other email providers (optional): SMTP_HOST, SMTP_PORT (465), SMTP_USER, SMTP_PASS.
 */
'use strict';
const tls = require('tls');

const SITE = "MD Jesun";
const BRAND = "#2EB7F6";
const SUCCESS = "Thanks! Your message is on its way. I’ll get back to you within 24 hours.";

/* ------------------------------------------------------------ helpers */
function clean(v, max, multiline) {
  let s = typeof v === 'string' ? v : '';
  s = s.replace(/\r\n?/g, '\n');
  // remove control characters (keep new lines only in the message)
  s = s.replace(multiline ? /[\u0000-\u0009\u000B-\u001F\u007F]/g : /[\u0000-\u001F\u007F]/g, '');
  s = s.trim();
  return Array.from(s).slice(0, max).join('');
}
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const letters = (s) => (s.match(/[\p{L}\p{N}]/gu) || []).length;
const header = (s) => String(s).replace(/[\r\n]+/g, ' ').trim();
const encWord = (s) => (/^[\x20-\x7E]*$/.test(s) ? s : '=?UTF-8?B?' + Buffer.from(s, 'utf8').toString('base64') + '?=');
// display name for From / Reply-To: quoted when plain ASCII, encoded otherwise
const dispName = (s) => { const h = header(s); return /^[\x20-\x7E]*$/.test(h) ? '"' + h.replace(/["\\]/g, '') + '"' : encWord(h); };
const b64lines = (s) => Buffer.from(s, 'utf8').toString('base64').replace(/.{1,76}/g, '$&\r\n');

function readBody(req) {
  if (req.body && typeof req.body === 'object') return Promise.resolve(req.body);
  if (typeof req.body === 'string') return Promise.resolve(parse(req.body, req.headers['content-type']));
  return new Promise((resolve) => {
    let data = '';
    req.on('data', (c) => { data += c; if (data.length > 100000) req.destroy(); });
    req.on('end', () => resolve(parse(data, req.headers['content-type'])));
    req.on('error', () => resolve({}));
  });
}
function parse(raw, type) {
  try {
    if ((type || '').includes('application/json')) return JSON.parse(raw || '{}');
  } catch (e) { return {}; }
  const out = {};
  new URLSearchParams(raw || '').forEach((v, k) => { out[k] = v; });
  return out;
}

/* ------------------------------------------------------------ email */
function buildEmail(v) {
  const name = v.name;
  const first = name.split(/\s+/)[0] || name;
  const subject = 'New enquiry from ' + name + (v.service ? ' — ' + v.service : '');
  const rows = [['Name', v.name], ['Email', v.email]];
  if (v.service) rows.push(['Service', v.service]);
  if (v.budget) rows.push(['Budget', v.budget]);
  let text = 'New project enquiry from your website\n\n';
  rows.forEach((r) => { text += r[0] + ': ' + r[1] + '\n'; });
  text += '\nMessage:\n' + v.message + '\n\n-- \nReply to this email to answer ' + first + ' directly.';

  let inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">';
  rows.forEach((r) => {
    const val = r[0] === 'Email' ? '<a href="mailto:' + esc(r[1]) + '" style="color:' + BRAND + ';text-decoration:none">' + esc(r[1]) + '</a>' : esc(r[1]);
    inner += '<tr><td style="padding:7px 0;width:90px;color:#7b8594;vertical-align:top">' + esc(r[0]) + '</td><td style="padding:7px 0;font-weight:bold;color:#1d2530">' + val + '</td></tr>';
  });
  inner += '</table>';
  inner += '<div style="margin-top:20px;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#7b8594;font-family:Consolas,Menlo,monospace">Message</div>';
  inner += '<div style="margin-top:8px;padding:16px 18px;border-radius:12px;background:#f4f7fa;border:1px solid #e3e9ef;color:#1d2530">' + esc(v.message).replace(/\n/g, '<br>') + '</div>';
  const reply = 'mailto:' + encodeURIComponent(v.email) + '?subject=' + encodeURIComponent('Re: your project enquiry');
  inner += '<div style="margin-top:22px"><a href="' + esc(reply) + '" style="display:inline-block;background:' + BRAND + ';color:#ffffff;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:999px">Reply to ' + esc(first) + '</a></div>';
  const html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>' +
    '<body style="margin:0;padding:0;background:#eef1f5">' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:28px 12px"><tr><td align="center">' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dfe5ec;font-family:Arial,Helvetica,sans-serif">' +
    '<tr><td style="background:#070b12;padding:24px 30px;border-bottom:3px solid ' + BRAND + '">' +
    '<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' + BRAND + ';font-family:Consolas,Menlo,monospace">New project enquiry</div>' +
    '<div style="font-size:23px;font-weight:bold;margin-top:8px;color:#ffffff;line-height:1.25">' + esc(name) + '</div></td></tr>' +
    '<tr><td style="padding:26px 30px;color:#1d2530;font-size:15px;line-height:1.6">' + inner + '</td></tr>' +
    '<tr><td style="padding:16px 30px;background:#f7f9fb;color:#7b8594;font-size:12px;line-height:1.5;border-top:1px solid #e8edf2">' +
    esc('Sent from the contact form on ' + SITE + '. Just press Reply to answer — it goes straight to ' + first + '.') + '</td></tr>' +
    '</table></td></tr></table></body></html>';
  return { subject, text, html };
}

function mime(from, fromName, to, replyTo, replyName, subject, text, html) {
  const id = Date.now().toString(36) + Math.random().toString(36).slice(2);
  const domain = (from.split('@')[1] || 'localhost').replace(/[^A-Za-z0-9.-]/g, '');
  const bnd = 'mdj-' + id;
  return [
    'From: ' + dispName(fromName) + ' <' + from + '>',
    'To: <' + to + '>',
    replyTo ? 'Reply-To: ' + dispName(replyName) + ' <' + replyTo + '>' : '',
    'Subject: ' + encWord(header(subject)),
    'Date: ' + new Date().toUTCString().replace('GMT', '+0000'),
    'Message-ID: <' + id + '@' + domain + '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' + bnd + '"',
    '',
    '--' + bnd,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    b64lines(text),
    '--' + bnd,
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    b64lines(html),
    '--' + bnd + '--',
    ''
  ].filter((l, i) => l !== '' || i > 3).join('\r\n');
}

/** minimal SMTP over implicit TLS (port 465): EHLO, AUTH LOGIN, MAIL, RCPT, DATA */
function smtpSend(cfg, from, to, message) {
  return new Promise((resolve) => {
    let buf = '';
    let step = 0;
    let done = false;
    const finish = (ok, error) => { if (done) return; done = true; try { sock.end(); } catch (e) { /* ignore */ } resolve({ ok, error }); };
    const sock = tls.connect({ host: cfg.host, port: cfg.port, servername: cfg.host, rejectUnauthorized: true });
    sock.setTimeout(15000, () => finish(false, 'The email server did not answer in time.'));
    sock.on('error', (e) => finish(false, 'Could not connect to the email server (' + (e.code || e.message) + ').'));
    const send = (line) => sock.write(line + '\r\n');
    const data = message.replace(/\r\n\./g, '\r\n..');
    const steps = [
      [220, () => send('EHLO ' + (cfg.helo || 'vercel.app'))],
      [250, () => send('AUTH LOGIN')],
      [334, () => send(Buffer.from(cfg.user).toString('base64'))],
      [334, () => send(Buffer.from(cfg.pass).toString('base64'))],
      [235, () => send('MAIL FROM:<' + from + '>')],
      [250, () => send('RCPT TO:<' + to + '>')],
      [250, () => send('DATA')],
      [354, () => sock.write(data + '\r\n.\r\n')],
      [250, () => { send('QUIT'); finish(true, ''); }]
    ];
    sock.on('data', (chunk) => {
      buf += chunk.toString('utf8');
      let idx;
      while ((idx = buf.indexOf('\r\n')) > -1) {
        const line = buf.slice(0, idx);
        buf = buf.slice(idx + 2);
        if (/^\d{3}-/.test(line)) continue; // multi-line reply, wait for the last line
        const code = parseInt(line.slice(0, 3), 10);
        const want = steps[step];
        if (!want) return;
        if (code !== want[0]) {
          const login = step === 2 || step === 3 || step === 4;
          return finish(false, login ? 'Gmail did not accept the login. Check GMAIL_USER and GMAIL_APP_PASSWORD (use an App Password).' : 'The email server refused the message (' + line.slice(0, 120) + ').');
        }
        step += 1;
        want[1]();
      }
    });
  });
}

function mailConfig() {
  const env = process.env;
  const user = (env.SMTP_USER || env.GMAIL_USER || '').trim();
  const pass = (env.SMTP_PASS || env.GMAIL_APP_PASSWORD || '').replace(/\s+/g, '');
  return {
    host: (env.SMTP_HOST || 'smtp.gmail.com').trim(),
    port: parseInt(env.SMTP_PORT || '465', 10),
    user, pass,
    from: (env.MAIL_FROM || user).trim(),
    to: (env.CONTACT_TO || env.GMAIL_USER || user).trim()
  };
}

/* ------------------------------------------------------------ handler */
module.exports = async function handler(req, res) {
  const wantsJson = (req.headers['x-requested-with'] || '') === 'fetch' || (req.headers.accept || '').includes('application/json');
  const reply = (status, payload, redirect) => {
    res.setHeader('Cache-Control', 'no-store');
    if (!wantsJson && redirect) {
      res.statusCode = 303;
      res.setHeader('Location', redirect);
      return res.end();
    }
    res.statusCode = status;
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.end(JSON.stringify(payload));
  };

  if (req.method !== 'POST') {
    res.setHeader('Allow', 'POST');
    return reply(405, { ok: false, errors: { form: 'Please use the contact form.' } }, '/contact#form');
  }

  const b = await readBody(req);
  const v = {
    name: clean(b.name, 80),
    email: clean(b.email, 120),
    service: clean(b.service, 60),
    budget: clean(b.budget, 60),
    message: clean(b.message, 4000, true)
  };
  const errors = {};
  if (!v.name) errors.name = 'Please add your name.';
  if (!/^[^\s@<>()",;:]+@[^\s@<>()",;:]+\.[^\s@<>()",;:]{2,}$/.test(v.email)) errors.email = 'Please add a valid email address.';
  if (letters(v.message) < 10) errors.message = 'Please write a little more about your project.';
  if (Object.keys(errors).length) return reply(422, { ok: false, errors }, '/contact#form');

  // anti-robot checks: the hidden box must stay empty and people need more than 2 seconds to fill the form
  const trap = clean(b.hp_field || b.website || '', 200);
  const opened = parseInt(b.t || '0', 10);
  const fast = opened > 0 && Date.now() - opened < 2000;
  if (trap || fast) {
    // pretend it worked so robots learn nothing
    return reply(200, { ok: true, errors: {}, message: SUCCESS }, '/contact?sent=1#form');
  }

  const cfg = mailConfig();
  if (!cfg.user || !cfg.pass || !cfg.to) {
    console.error('Contact form: email is not set up (GMAIL_USER / GMAIL_APP_PASSWORD missing).');
    return reply(503, { ok: false, errors: { form: 'Sorry, the form is not connected yet. Please email me directly.' } }, '/contact#form');
  }
  const mail = buildEmail(v);
  const msg = mime(cfg.from, SITE + ' website', cfg.to, v.email, v.name, mail.subject, mail.text, mail.html);
  const r = await smtpSend(cfg, cfg.from, cfg.to, msg);
  if (!r.ok) {
    console.error('Contact form: ' + r.error);
    return reply(502, { ok: false, errors: { form: 'Sorry, the message could not be sent right now. Please email me directly.' } }, '/contact#form');
  }
  return reply(200, { ok: true, errors: {}, message: SUCCESS }, '/contact?sent=1#form');
};
