/*
  MD Jesun — Admin Panel (plain JavaScript, no libraries).
  Forms are generated from the content schema sent by api.php.
*/
(function () {
  'use strict';

  var API = 'api.php';
  var app = document.getElementById('app');
  var csrf = '';
  var S = null;          // data from the server (schema, content, themes…)
  var drafts = {};       // unsaved edits per section
  var dirty = {};        // section → has unsaved edits
  var openItems = {};    // list item id → expanded?
  var route = '';
  var bars = {};         // save bars by section
  var bc = null;
  try { bc = new BroadcastChannel('mdj-cms'); } catch (e) { bc = null; }

  /* ------------------------------------------------------------ helpers */
  var PROPS = { value: 1, checked: 1, disabled: 1, selected: 1, type: 1, multiple: 1, accept: 1 };
  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') el.className = v;
        else if (k === 'text') el.textContent = v;
        else if (k === 'html') el.innerHTML = v; // trusted icon markup only
        else if (k.indexOf('on') === 0 && typeof v === 'function') el.addEventListener(k.slice(2), v);
        else if (PROPS[k]) el[k] = v;
        else el.setAttribute(k, v === true ? '' : String(v));
      });
    }
    for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
    return el;
  }
  function add(el, kid) {
    if (kid === null || kid === undefined || kid === false) return;
    if (Array.isArray(kid)) { kid.forEach(function (k) { add(el, k); }); return; }
    el.appendChild(kid instanceof Node ? kid : document.createTextNode(String(kid)));
  }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function uid() { var s = ''; while (s.length < 10) s += Math.random().toString(36).slice(2); return s.slice(0, 10).replace(/[^a-z0-9]/g, 'x'); }
  function fmtDate(iso) { if (!iso) return ''; var d = new Date(iso); return isNaN(d) ? iso : d.toLocaleString(); }
  function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
  function imgUrl(p) { return /^https:\/\//i.test(p) ? p : '../' + p; }
  function pageUrl(p) { return '../' + (p || 'index.php'); }

  var I = {
    dash: '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
    home: '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/>',
    layers: '<path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/>',
    mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    film: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 12h4M3 16h4M17 8h4M17 12h4M17 16h4"/>',
    tag: '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
    award: '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
    quote: '<path d="M7 7h4v4c0 3-2 5-4 6M15 7h4v4c0 3-2 5-4 6"/>',
    steps: '<path d="M4 6h6M4 12h10M4 18h16"/><circle cx="20" cy="6" r="1.5"/>',
    phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
    image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
    palette: '<circle cx="12" cy="12" r="9"/><circle cx="8" cy="10" r="1.3"/><circle cx="12" cy="7.5" r="1.3"/><circle cx="16" cy="10" r="1.3"/><path d="M12 21c-1.5 0-2-1-1.5-2s1.5-1.5 3-1.5h2a3 3 0 0 0 3-3"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
    lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    inbox: '<path d="M3 13h5l2 3h4l2-3h5"/><path d="M5 5h14l2 8v6H3v-6z"/>',
    archive: '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v11h14V9M10 13h4"/>',
    out: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    upload: '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
    play: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M10 9v6l5-3z"/>',
    link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    check: '<path d="m5 12 5 5 9-10"/>',
    up: '<path d="m6 15 6-6 6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    chev: '<path d="m6 9 6 6 6-6"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    logout: '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
    hex: '<path d="M15 1.5 27 8.5v13L15 28.5 3 21.5v-13z" fill="none"/><path d="M12 10v10l8-5z" fill="currentColor" stroke="none"/>',
    send: '<path d="M21 3.5 3 10.6l7.1 2.6 2.6 7.3z"/><path d="M21 3.5 10.1 13.2"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    eyeoff: '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 4.4-1.1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>'
  };
  function ico(name, size) {
    var s = size || 18;
    var vb = name === 'hex' ? '0 0 30 30' : '0 0 24 24';
    return h('span', { style: 'display:inline-flex', 'aria-hidden': 'true', html: '<svg width="' + s + '" height="' + s + '" viewBox="' + vb + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + I[name] + '</svg>' });
  }

  /* ------------------------------------------------------------ API */
  function api(action, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: {} };
    if (init.method === 'POST') init.headers['X-CSRF-Token'] = csrf;
    if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    if (opts.form) init.body = opts.form;
    return fetch(API + '?action=' + encodeURIComponent(action), init).then(function (r) {
      return r.text().then(function (txt) {
        var data;
        try { data = JSON.parse(txt); } catch (e) { data = { ok: false, error: 'The server sent an unexpected reply (' + r.status + '). Check that your hosting runs PHP 7.4 or newer.' }; }
        if (r.status === 401 && action !== 'login' && action !== 'session') {
          showLogin('Your session ended. Please log in again.');
          return Promise.reject(new Error('auth'));
        }
        return { status: r.status, data: data };
      });
    }).catch(function (err) {
      if (err && err.message === 'auth') throw err;
      toast('Could not reach the server. Check your internet connection.', 'bad');
      throw err;
    });
  }

  /* ------------------------------------------------------------ toasts & dialogs */
  var toastBox = null;
  function toast(msg, kind, link) {
    if (!toastBox || !document.body.contains(toastBox)) { toastBox = h('div', { class: 'toasts', role: 'status', 'aria-live': 'polite' }); document.body.appendChild(toastBox); }
    var t = h('div', { class: 'toast ' + (kind || 'ok') }, msg, link ? [' ', h('a', { href: link.href, target: '_blank', rel: 'noopener' }, link.text)] : null);
    toastBox.appendChild(t);
    setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 450); }, kind === 'bad' ? 7000 : 4200);
  }
  function confirmBox(title, text, okLabel, danger) {
    return new Promise(function (resolve) {
      var bg;
      function done(v) { bg.remove(); document.removeEventListener('keydown', onKey); resolve(v); }
      function onKey(e) { if (e.key === 'Escape') done(false); }
      var ok = h('button', { class: 'btn ' + (danger ? 'btn-d' : 'btn-p'), type: 'button', onclick: function () { done(true); } }, okLabel || 'OK');
      bg = h('div', { class: 'modal-bg', onclick: function (e) { if (e.target === bg) done(false); } },
        h('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true' },
          h('h3', null, title), h('p', null, text),
          h('div', { class: 'row' }, h('button', { class: 'btn', type: 'button', onclick: function () { done(false); } }, 'Cancel'), ok)));
      document.body.appendChild(bg);
      document.addEventListener('keydown', onKey);
      ok.focus();
    });
  }
  function broadcast() { if (bc) { try { bc.postMessage({ type: 'saved', at: Date.now() }); } catch (e) { /* ignore */ } } }

  /* ------------------------------------------------------------ boot & login */
  function boot() {
    api('session').then(function (r) {
      if (!r.data.ok) {
        app.innerHTML = '';
        app.appendChild(h('div', { class: 'boot' }, r.data.error || 'The Admin Panel could not start.'));
        return;
      }
      csrf = r.data.csrf || '';
      if (r.data.loggedIn) loadApp(); else showLogin();
    }).catch(function () {
      app.innerHTML = '';
      app.appendChild(h('div', { class: 'boot' }, 'The Admin Panel could not start. Make sure the website is running on PHP hosting.'));
    });
  }

  function showLogin(msg) {
    S = null;
    app.innerHTML = '';
    var err = h('div', { class: 'alert alert-e', role: 'alert', style: msg ? '' : 'display:none' }, msg || '');
    var user = h('input', { class: 'in', id: 'lu', name: 'username', autocomplete: 'username', required: true, autocapitalize: 'none', spellcheck: 'false' });
    var pass = h('input', { class: 'in', id: 'lp', name: 'password', type: 'password', autocomplete: 'current-password', required: true });
    var showBtn = h('button', { type: 'button', onclick: function () { pass.type = pass.type === 'password' ? 'text' : 'password'; showBtn.textContent = pass.type === 'password' ? 'Show' : 'Hide'; } }, 'Show');
    var btn = h('button', { class: 'btn btn-p', type: 'submit', style: 'width:100%;height:48px;margin-top:6px' }, 'Log in');
    var form = h('form', { onsubmit: function (e) {
      e.preventDefault();
      err.style.display = 'none';
      btn.disabled = true;
      btn.textContent = 'Checking…';
      api('login', { method: 'POST', body: { username: user.value, password: pass.value } }).then(function (r) {
        btn.disabled = false;
        btn.textContent = 'Log in';
        if (r.data.ok) { csrf = r.data.csrf; loadApp(); return; }
        if (r.status === 403) { return api('session').then(function (s) { csrf = s.data.csrf; err.textContent = 'Please try again.'; err.style.display = ''; }); }
        err.textContent = r.data.error || 'Login failed.';
        err.style.display = '';
        pass.select();
      }).catch(function () { btn.disabled = false; btn.textContent = 'Log in'; });
    } },
      err,
      h('div', { class: 'field' }, h('label', { for: 'lu' }, 'Username'), user),
      h('div', { class: 'field' }, h('label', { for: 'lp' }, 'Password'), h('div', { class: 'pw' }, pass, showBtn)),
      btn);
    app.appendChild(h('div', { class: 'login' },
      h('div', { class: 'login-card' },
        h('div', { class: 'logo' }, ico('hex', 30), 'Admin Panel'),
        h('h1', null, 'Welcome back'),
        h('p', null, 'Log in to edit your website.'),
        form)));
    setTimeout(function () { user.focus(); }, 50);
  }

  function loadApp() {
    return api('bootstrap').then(function (r) {
      if (!r.data.ok) { toast(r.data.error || 'Could not load the Admin Panel.', 'bad'); return; }
      S = r.data;
      drafts = {};
      dirty = {};
      document.documentElement.setAttribute('data-theme', S.content.settings.theme);
      renderShell();
      route = '';
      onRoute();
    });
  }

  /* ------------------------------------------------------------ shell */
  var NAV = [
    ['Overview', [['dashboard', 'Dashboard', 'dash']]],
    ['Pages', [['home', 'Home page', 'home'], ['portfolio', 'Portfolio page', 'grid'], ['about', 'About page', 'user'], ['servicesPage', 'Services page', 'layers'], ['testimonialsPage', 'Testimonials page', 'play'], ['contactPage', 'Contact page', 'mail']]],
    ['Content', [['projects', 'Portfolio projects', 'film'], ['services', 'Services & prices', 'tag'], ['awards', 'Awards & achievements', 'award'], ['videoTestimonials', 'Video testimonials', 'play'], ['testimonials', 'Written testimonials', 'quote'], ['process', 'Work process steps', 'steps']]],
    ['Settings', [['contact', 'Contact & social links', 'phone'], ['mail', 'Email notifications', 'send'], ['images', 'All images', 'image'], ['theme', 'Colour theme', 'palette'], ['settings', 'Site settings', 'settings'], ['account', 'Login & password', 'lock'], ['backups', 'Backups', 'archive']]],
    ['Inbox', [['messages', 'Messages', 'inbox']]]
  ];
  var shell, sideEl, mainTitle, mainBody, topActions, viewLink, viewLbl;

  function renderShell() {
    app.innerHTML = '';
    sideEl = h('aside', { class: 'side' });
    mainTitle = h('h1', null, '');
    topActions = h('div', { style: 'display:flex;gap:8px;align-items:center' });
    mainBody = h('div', { class: 'content' });
    shell = h('div', { class: 'shell' },
      sideEl,
      h('div', { class: 'scrim', onclick: function () { shell.classList.remove('menu'); } }),
      h('div', { class: 'main' },
        h('div', { class: 'top' },
          h('button', { class: 'btn btn-s btn-i menu-t', type: 'button', 'aria-label': 'Menu', onclick: function () { shell.classList.toggle('menu'); } }, ico('menu')),
          mainTitle, h('span', { class: 'sp' }), topActions,
          viewLink = h('a', { class: 'btn btn-s view-l', href: '../index.php', target: '_blank', rel: 'noopener', title: 'Open your website in a new tab' }, ico('out', 16), viewLbl = h('span', { class: 'lb' }, 'View website'))),
        mainBody));
    app.appendChild(shell);
    drawSide();
  }

  function drawSide() {
    sideEl.innerHTML = '';
    sideEl.appendChild(h('div', { class: 'logo' }, ico('hex', 28), S.content.settings.siteName || 'Admin'));
    NAV.forEach(function (grp) {
      sideEl.appendChild(h('small', { class: 'mono' }, grp[0]));
      grp[1].forEach(function (n) {
        var extra = null;
        if (n[0] === 'messages' && S.unread) extra = h('span', { class: 'badge' }, S.unread);
        if (n[0] === 'account' && S.isDefault) extra = h('span', { class: 'dotw', title: 'Default password still in use' });
        if (n[0] === 'mail' && mailNeedsSetup()) extra = h('span', { class: 'dotw', title: 'Email notifications need attention' });
        if (n[0] === 'testimonialsPage' && S.content.testimonialsPage && S.content.testimonialsPage.enabled === false) extra = h('span', { class: 'badge badge-off', title: 'This page is switched off' }, 'Off');
        if (dirty[n[0]]) extra = h('span', { class: 'dotw', title: 'Unsaved changes' });
        sideEl.appendChild(h('a', { class: 'nav-a' + (route === n[0] ? ' on' : ''), href: '#/' + n[0], onclick: function () { shell.classList.remove('menu'); } }, ico(n[2]), n[1], extra));
      });
    });
    sideEl.appendChild(h('div', { class: 'side-foot' },
      h('span', null, 'Signed in as ', h('b', null, S.user)),
      h('button', { class: 'btn btn-s', type: 'button', onclick: logout }, ico('logout', 15), 'Log out')));
  }

  function logout() {
    var anyDirty = Object.keys(dirty).some(function (k) { return dirty[k]; });
    (anyDirty ? confirmBox('Log out?', 'You have unsaved changes. They will be lost.', 'Log out', true) : Promise.resolve(true)).then(function (ok) {
      if (!ok) return;
      api('logout', { method: 'POST' }).then(function (r) {
        csrf = r.data.csrf || csrf;
        dirty = {};
        drafts = {};
        route = '';
        history.replaceState(null, '', '#/dashboard');
        showLogin();
      });
    });
  }

  function setMain(title, nodes, actions, page) {
    mainTitle.textContent = title;
    topActions.innerHTML = '';
    add(topActions, actions || null);
    viewLink.href = page ? pageUrl(page) : '../index.php';
    viewLbl.textContent = page ? 'View this page' : 'View website';
    mainBody.innerHTML = '';
    if (S.isDefault && route !== 'dashboard' && route !== 'account') {
      mainBody.appendChild(h('div', { class: 'alert alert-w slim' }, 'You are using the test login (admin / Admin@123). ', h('a', { href: '#/account' }, 'Change it before going live →')));
    }
    add(mainBody, nodes);
    window.scrollTo(0, 0);
  }

  /* ------------------------------------------------------------ routing */
  function currentKey() { return (location.hash.replace(/^#\/?/, '') || 'dashboard'); }
  function onRoute() {
    if (!S) return;
    var key = currentKey();
    if (route && dirty[route] && key !== route) {
      var prev = route;
      confirmBox('Leave without saving?', 'You have unsaved changes on this page. If you leave now they will be lost.', 'Leave page', true).then(function (ok) {
        if (ok) { delete drafts[prev]; dirty[prev] = false; route = ''; onRoute(); }
        else { history.replaceState(null, '', '#/' + prev); }
      });
      return;
    }
    route = key;
    drawSide();
    if (key === 'dashboard') return viewDashboard();
    if (key === 'images') return viewImages();
    if (key === 'theme') return viewTheme();
    if (key === 'account') return viewAccount();
    if (key === 'messages') return viewMessages();
    if (key === 'backups') return viewBackups();
    if (key === 'mail') return viewMail();
    if (S.schema[key]) return viewSection(key);
    location.hash = '#/dashboard';
  }
  window.addEventListener('hashchange', onRoute);
  window.addEventListener('beforeunload', function (e) {
    if (Object.keys(dirty).some(function (k) { return dirty[k]; })) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ------------------------------------------------------------ dashboard */
  function tile(key, label, desc, icon) {
    return h('a', { class: 'tile', href: '#/' + key }, h('span', { class: 'ti' }, ico(icon)), h('b', null, label), h('span', null, desc));
  }
  function viewDashboard(fresh) {
    var c = S.content;
    var featured = c.projects.filter(function (p) { return p.featured; }).length;
    var nodes = [];
    nodes.push(h('p', { class: 'intro-t' }, 'Welcome back, ' + S.user + '. Pick what you want to change. Every save updates your live website straight away.'));
    if (S.isDefault) {
      nodes.push(h('div', { class: 'alert alert-w' }, h('b', null, 'You are still using the test login (admin / Admin@123). '), 'Change it before your website goes live. ', h('a', { href: '#/account' }, 'Change password now →')));
    }
    if (S.writable && S.writable.length) {
      nodes.push(h('div', { class: 'alert alert-e' }, h('b', null, 'Saving is blocked on this hosting. '), 'These folders need write permission: ' + S.writable.join(', ') + '. In your hosting File Manager set them to permission 755 (or 775).'));
    }
    var ms = (S.mail && S.mail.status) || {};
    if (S.mail && S.mail.method !== 'off' && ms.at && ms.ok === false) {
      nodes.push(h('div', { class: 'alert alert-e' }, h('b', null, 'The last enquiry email could not be sent. '), (ms.error || '') + ' Your messages are still saved in Messages. ', h('a', { href: '#/mail' }, 'Fix email notifications →')));
    } else if (mailNeedsSetup()) {
      nodes.push(h('div', { class: 'alert alert-w' }, h('b', null, 'Get every enquiry in your inbox. '), 'Messages from your contact form are saved under Messages, but they are not emailed to you yet. Connecting your Gmail takes about 2 minutes. ', h('a', { href: '#/mail' }, 'Set up email notifications →')));
    }
    nodes.push(h('div', { class: 'grid g4' },
      h('div', { class: 'stat' }, h('b', null, c.projects.length), h('span', null, 'Portfolio projects (' + featured + ' on Home)')),
      h('div', { class: 'stat' }, h('b', null, c.services.length), h('span', null, 'Services with prices')),
      h('a', { class: 'stat tile', href: '#/videoTestimonials', style: 'gap:0' }, h('b', null, (c.videoTestimonials || []).length), h('span', null, 'Video testimonials')),
      h('a', { class: 'stat tile', href: '#/messages', style: 'gap:0' }, h('b', null, S.unread), h('span', null, 'New messages (' + S.messages + ' total)'))));
    nodes.push(h('h2', { class: 'sec' }, 'Edit your pages'));
    nodes.push(h('div', { class: 'grid g3' },
      tile('home', 'Home page', 'Section order, hero, “Who’s behind the edit”, showreel, banner, call to action.', 'home'),
      tile('portfolio', 'Portfolio page', 'Heading and intro of the full portfolio.', 'grid'),
      tile('about', 'About page', 'Bio, hologram photo, stats, toolkit, awards heading.', 'user'),
      tile('servicesPage', 'Services page', 'Heading, currency, price note, process heading.', 'layers'),
      tile('testimonialsPage', 'Testimonials page', (c.testimonialsPage && c.testimonialsPage.enabled === false ? 'OFF: hidden from your website. ' : 'ON. ') + 'Switch the page on or off, menu name and texts.', 'play'),
      tile('contactPage', 'Contact page', 'Heading and every word of the enquiry form, with its choices.', 'mail')));
    nodes.push(h('h2', { class: 'sec' }, 'Add & remove content'));
    nodes.push(h('div', { class: 'grid g3' },
      tile('projects', 'Portfolio projects', 'Add, remove and reorder projects. Choose what shows on Home.', 'film'),
      tile('services', 'Services & prices', 'Add or remove services and set each price.', 'tag'),
      tile('awards', 'Awards & achievements', 'Add or remove awards on the About page.', 'award'),
      tile('videoTestimonials', 'Video testimonials', 'Add client feedback videos: YouTube, Vimeo, Drive link or MP4 upload.', 'play'),
      tile('testimonials', 'Written testimonials', 'Client quotes on the Home and Testimonials pages.', 'quote'),
      tile('process', 'Work process steps', 'The timeline on the Services page.', 'steps')));
    nodes.push(h('h2', { class: 'sec' }, 'Settings'));
    var th = S.themes[c.settings.theme] || {};
    nodes.push(h('div', { class: 'grid g3' },
      tile('contact', 'Contact & social links', 'Email, WhatsApp number, booking link and social media.', 'phone'),
      tile('mail', 'Email notifications', mailNeedsSetup() ? 'Not set up yet: get enquiries emailed to you.' : 'Enquiries are emailed to you. Send a test any time.', 'send'),
      tile('images', 'All images', 'Every image on the website in one place. Upload or replace.', 'image'),
      tile('theme', 'Colour theme', 'Current: ' + (th.label || c.settings.theme) + '. Switch the colours of the whole site.', 'palette'),
      tile('settings', 'Site settings', 'Name, Google text, share image, logo, favicon, motion.', 'settings'),
      tile('account', 'Login & password', 'Change your username and password.', 'lock'),
      tile('backups', 'Backups', 'Undo mistakes by restoring an earlier version.', 'archive')));
    if (S.savedAt) nodes.push(h('p', { class: 'muted', style: 'margin-top:22px;font-size:13px' }, 'Last saved: ' + fmtDate(S.savedAt)));
    setMain('Dashboard', nodes, null);
    if (!fresh) refreshCounts();
  }
  /* new enquiries or email problems may have happened since the panel was opened */
  function refreshCounts() {
    Promise.all([api('mail'), api('messages')]).then(function (rs) {
      if (!S || route !== 'dashboard') return;
      var before = JSON.stringify([S.mail, S.unread, S.messages]);
      if (rs[0].data && rs[0].data.mail) S.mail = rs[0].data.mail;
      var list = (rs[1].data && rs[1].data.messages) || [];
      var inbox = list.filter(function (m) { return !m.spam; });
      S.unread = inbox.filter(function (m) { return !m.read; }).length;
      S.messages = inbox.length;
      if (JSON.stringify([S.mail, S.unread, S.messages]) !== before) { drawSide(); viewDashboard(true); }
    }).catch(function () {});
  }

  /* ------------------------------------------------------------ section editor */
  var NEW_ITEM = {
    projects: function () { return { featured: true, year: String(new Date().getFullYear()) }; },
    services: function () { return { icon: 'spark', pricePrefix: 'From', priceSuffix: 'per project' }; },
    awards: function () { return { year: String(new Date().getFullYear()) }; },
    videoTestimonials: function () { return { format: 'landscape', stars: '5', featured: true }; }
  };

  function viewSection(key) {
    var sec = S.schema[key];
    // start from the live content unless there are unsaved edits
    if (!drafts[key] || !dirty[key]) drafts[key] = clone(S.content[key]);
    var ctx = { key: key, changed: function () { setDirty(key, true); } };
    var errBox = h('div', { id: 'errbox' });
    var body = sec.list ? renderList(sec, drafts[key], [key], ctx, key) : renderFields(sec.fields, drafts[key], [key], ctx, null);
    setMain(sec.label, [h('p', { class: 'intro-t' }, sec.desc), errBox, h('div', { class: 'card' }, body), saveBar(key)], null, sec.page);
  }

  function saveBar(key) {
    var st = h('span', { class: 'st' });
    var discard = h('button', { class: 'btn', type: 'button', onclick: function () {
      confirmBox('Discard changes?', 'Your unsaved edits on this page will be removed.', 'Discard', true).then(function (ok) {
        if (!ok) return;
        delete drafts[key];
        setDirty(key, false);
        viewSection(key);
      });
    } }, 'Discard');
    var save = h('button', { class: 'btn btn-p', type: 'button', onclick: function () { saveSection(key, save); } }, ico('check', 16), 'Save changes');
    var bar = h('div', { class: 'savebar' }, st, discard, save);
    bars[key] = { bar: bar, st: st, discard: discard, save: save };
    paintBar(key);
    return bar;
  }
  function paintBar(key) {
    var b = bars[key];
    if (!b) return;
    var d = !!dirty[key];
    b.bar.classList.toggle('dirty', d);
    b.st.textContent = d ? 'You have unsaved changes.' : 'All changes are saved and live.';
    b.discard.disabled = !d;
    b.save.disabled = !d;
  }
  function setDirty(key, val) {
    var was = !!dirty[key];
    dirty[key] = !!val;
    paintBar(key);
    if (was !== !!val) drawSide();
  }

  function saveSection(key, btn) {
    var data = clone(drafts[key]);
    if (btn) { btn.disabled = true; }
    var errBox = document.getElementById('errbox');
    if (errBox) errBox.innerHTML = '';
    Array.prototype.forEach.call(document.querySelectorAll('.field.err'), function (f) { f.classList.remove('err'); var m = f.querySelector('.ferr'); if (m) m.remove(); });
    return api('save', { method: 'POST', body: { section: key, data: data } }).then(function (r) {
      if (r.data.ok) {
        S.content[key] = r.data.data;
        S.savedAt = r.data.savedAt;
        delete drafts[key];
        dirty[key] = false;
        broadcast();
        toast('Saved. Your live website is updated.', 'ok', { href: pageUrl(S.schema[key].page), text: 'View page →' });
        if (route === key) { var y = window.scrollY; viewSection(key); window.scrollTo(0, y); }
        drawSide();
        return true;
      }
      if (btn) btn.disabled = false;
      if (r.status === 422 && r.data.errors) {
        if (errBox) errBox.appendChild(h('ul', { class: 'errlist' }, r.data.errors.map(function (er) { return h('li', null, er.msg); })));
        r.data.errors.forEach(function (er) {
          var el = document.querySelector('.field[data-path="' + er.path + '"]');
          if (el) {
            el.classList.add('err');
            el.appendChild(h('span', { class: 'ferr' }, er.msg));
            var item = el.closest('.item');
            if (item) item.classList.remove('closed');
          }
        });
        toast('Not saved yet. Please fix the highlighted fields.', 'bad');
      } else {
        toast(r.data.error || 'Could not save.', 'bad');
      }
      return false;
    }).catch(function () { if (btn) btn.disabled = false; return false; });
  }

  function renderFields(fields, obj, path, ctx, onTitle) {
    var frag = document.createDocumentFragment();
    fields.forEach(function (f) {
      if (f.hidden) return;
      if (f.type === 'heading') { frag.appendChild(h('div', { class: 'hd' }, f.label)); return; }
      if (f.type === 'note') { frag.appendChild(h('p', { class: 'note' }, f.label)); return; }
      frag.appendChild(renderField(f, obj, path.concat(f.k), ctx, onTitle));
    });
    return frag;
  }

  function renderField(f, obj, path, ctx, onTitle) {
    var p = path.join('.');
    var id = 'f_' + p.replace(/[^A-Za-z0-9_]/g, '_');
    var wrap = h('div', { class: 'field', 'data-path': p });
    var help = f.help ? h('span', { class: 'help' }, f.help) : null;
    var changed = function () { ctx.changed(); if (onTitle) onTitle(f.k); };
    if (f.type === 'image') return imageField(f, obj, wrap, help, changed);
    if (f.type === 'video') return videoField(f, obj, wrap, help, changed, id);
    if (f.type === 'order') return orderField(f, obj, wrap, help, changed);
    if (f.type === 'list') {
      if (!Array.isArray(obj[f.k])) obj[f.k] = [];
      wrap.appendChild(h('span', { class: 'lbl' }, f.label));
      add(wrap, help);
      wrap.appendChild(h('div', { class: 'sublist' }, renderList(f, obj[f.k], path, ctx, null)));
      return wrap;
    }
    if (f.type === 'toggle') {
      var cb = h('input', { type: 'checkbox', id: id, checked: !!obj[f.k], onchange: function () { obj[f.k] = cb.checked; changed(); } });
      wrap.appendChild(h('label', { class: 'switch', for: id }, cb, h('span', { class: 'tr' }), f.label));
      add(wrap, help);
      return wrap;
    }
    var input;
    if (f.type === 'textarea') {
      input = h('textarea', { class: 'ta', id: id, maxlength: f.max || null, rows: (f.max || 0) > 600 ? 6 : 3 });
      input.value = obj[f.k] || '';
    } else if (f.type === 'select') {
      input = h('select', { class: 'sel', id: id });
      Object.keys(f.options || {}).forEach(function (v) { input.appendChild(h('option', { value: v, selected: obj[f.k] === v }, f.options[v])); });
    } else {
      var typ = { url: 'url', email: 'email', tel: 'tel' }[f.type] || 'text';
      input = h('input', { class: 'in', id: id, type: typ, value: obj[f.k] || '', maxlength: f.max || null, placeholder: typ === 'url' ? 'https://' : null, autocomplete: 'off' });
    }
    input.addEventListener(f.type === 'select' ? 'change' : 'input', function () { obj[f.k] = input.value; changed(); });
    wrap.appendChild(h('label', { for: id }, f.label));
    wrap.appendChild(input);
    add(wrap, help);
    return wrap;
  }

  /* a fixed list of sections: move up/down, show or hide */
  function orderField(f, obj, wrap, help, changed) {
    if (!Array.isArray(obj[f.k])) obj[f.k] = Object.keys(f.options || {}).map(function (k) { return { k: k, on: true }; });
    var arr = obj[f.k];
    var box = h('div', { class: 'order' });
    function draw() {
      box.innerHTML = '';
      var n = 0;
      arr.forEach(function (it, i) {
        var numbered = it.on && it.k !== 'marquee';
        if (numbered) n += 1;
        var id = 'ord_' + f.k + '_' + it.k;
        var cb = h('input', { type: 'checkbox', id: id, checked: !!it.on, onchange: function () { it.on = cb.checked; changed(); draw(); } });
        box.appendChild(h('div', { class: 'ord-row' + (it.on ? '' : ' off') },
          h('span', { class: 'ord-n mono' }, it.on ? (numbered ? (n < 10 ? '0' + n : String(n)) : '—') : 'off'),
          h('span', { class: 'ord-t' }, (f.options || {})[it.k] || it.k, i === 0 && it.on ? h('small', null, 'Slides over the hero') : null),
          h('label', { class: 'switch sm', for: id, title: it.on ? 'Shown' : 'Hidden' }, cb, h('span', { class: 'tr' }), h('span', { class: 'vh' }, 'Show this section')),
          h('span', { class: 'tools' },
            h('button', { class: 'btn btn-s btn-i', type: 'button', title: 'Move up', 'aria-label': 'Move up', disabled: i === 0, onclick: function () { move(i, -1); } }, ico('up', 15)),
            h('button', { class: 'btn btn-s btn-i', type: 'button', title: 'Move down', 'aria-label': 'Move down', disabled: i === arr.length - 1, onclick: function () { move(i, 1); } }, ico('down', 15)))));
      });
    }
    function move(i, dir) {
      var j = i + dir;
      if (j < 0 || j >= arr.length) return;
      var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
      changed();
      draw();
    }
    draw();
    wrap.appendChild(h('span', { class: 'lbl' }, f.label));
    add(wrap, help);
    wrap.appendChild(box);
    return wrap;
  }

  function uploadFile(file, statusEl) {
    var okTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (okTypes.indexOf(file.type) === -1) { toast('Please choose a JPG, PNG, WebP or GIF image.', 'bad'); return Promise.reject(new Error('type')); }
    if (S.maxUpload && file.size > S.maxUpload) { toast('This image is ' + fmtSize(file.size) + '. The limit is ' + fmtSize(S.maxUpload) + '. Please use a smaller image.', 'bad'); return Promise.reject(new Error('size')); }
    var fd = new FormData();
    fd.append('file', file);
    if (statusEl) { statusEl.innerHTML = ''; statusEl.appendChild(h('span', { class: 'spinner' })); statusEl.appendChild(document.createTextNode(' Uploading…')); }
    return api('upload', { method: 'POST', form: fd }).then(function (r) {
      if (statusEl) statusEl.innerHTML = '';
      if (!r.data.ok) { toast(r.data.error || 'Upload failed.', 'bad'); throw new Error('upload'); }
      return r.data.url;
    }, function (e) { if (statusEl) statusEl.innerHTML = ''; throw e; });
  }

  function imageField(f, obj, wrap, help, changed) {
    var thumb = h('div', { class: 'thumb' });
    var status = h('span', { class: 'uploading' });
    var file = h('input', { type: 'file', class: 'file-in', accept: 'image/jpeg,image/png,image/webp,image/gif', tabindex: '-1', 'aria-hidden': 'true' });
    var upBtn = h('button', { class: 'btn btn-s', type: 'button', onclick: function () { file.click(); } });
    var rmBtn = h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () { obj[f.k] = ''; paint(); changed(); } }, 'Remove');
    function paint() {
      thumb.innerHTML = '';
      if (obj[f.k]) thumb.appendChild(h('img', { src: imgUrl(obj[f.k]), alt: '' }));
      else thumb.textContent = 'No image';
      upBtn.innerHTML = '';
      upBtn.appendChild(ico('upload', 15));
      upBtn.appendChild(document.createTextNode(obj[f.k] ? ' Replace image' : ' Upload image'));
      rmBtn.disabled = !obj[f.k];
    }
    file.addEventListener('change', function () {
      var fl = file.files && file.files[0];
      file.value = '';
      if (!fl) return;
      uploadFile(fl, status).then(function (url) {
        obj[f.k] = url;
        paint();
        changed();
        toast('Image uploaded. Click “Save changes” to publish it.', 'ok');
      }).catch(function () {});
    });
    paint();
    wrap.appendChild(h('span', { class: 'lbl' }, f.label));
    wrap.appendChild(h('div', { class: 'imgf' }, thumb, h('div', { class: 'acts' }, h('div', { class: 'row' }, upBtn, rmBtn), status, help), file));
    return wrap;
  }

  /* ------------------------------------------------------------ video field */
  function ytId(u) { var m = String(u || '').match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/); return m ? m[1] : ''; }
  function isVideoFile(u) { return /\.(mp4|m4v|webm|mov)(\?|#|$)/i.test(String(u || '')); }
  function videoKind(u) {
    u = String(u || '').trim();
    if (!u) return '';
    if (ytId(u)) return 'YouTube';
    if (/vimeo\.com\/(?:video\/)?\d+/.test(u)) return 'Vimeo';
    if (/drive\.google\.com\//.test(u)) return 'Google Drive';
    if (/^uploads\//.test(u)) return 'Uploaded video';
    if (isVideoFile(u)) return 'Video file link';
    return 'Link';
  }
  function videoThumb(item) {
    if (item.cover) return imgUrl(item.cover);
    var y = ytId(item.video);
    return y ? 'https://i.ytimg.com/vi/' + y + '/hqdefault.jpg' : '';
  }

  function uploadVideo(file, bar, label) {
    var okExt = /\.(mp4|m4v|mov|webm)$/i.test(file.name || '');
    var okType = /^video\/(mp4|x-m4v|quicktime|webm)$/.test(file.type || '');
    if (!okExt && !okType) { toast('Please choose an MP4, MOV or WebM video.', 'bad'); return Promise.reject(new Error('type')); }
    if (S.maxVideoUpload && file.size > S.maxVideoUpload) {
      toast('This video is ' + fmtSize(file.size) + ', but your hosting accepts up to ' + fmtSize(S.maxVideoUpload) + '. Upload it to YouTube (it can be “Unlisted”) or Vimeo and paste the link instead.', 'bad');
      return Promise.reject(new Error('size'));
    }
    return new Promise(function (resolve, reject) {
      var x = new XMLHttpRequest();
      x.open('POST', API + '?action=upload_video');
      x.withCredentials = true;
      x.setRequestHeader('X-CSRF-Token', csrf);
      x.upload.onprogress = function (e) {
        if (!e.lengthComputable) return;
        var pct = Math.round(e.loaded / e.total * 100);
        bar.style.width = pct + '%';
        label.textContent = 'Uploading… ' + pct + '%';
      };
      x.onload = function () {
        var d;
        try { d = JSON.parse(x.responseText); } catch (er) { d = { ok: false, error: 'The upload failed (' + x.status + '). The video may be larger than your hosting allows.' }; }
        if (x.status === 401) { showLogin('Your session ended. Please log in again.'); reject(new Error('auth')); return; }
        if (!d.ok) { toast(d.error || 'Upload failed.', 'bad'); reject(new Error('upload')); return; }
        resolve(d.url);
      };
      x.onerror = function () { toast('The upload stopped. Check your connection and try again.', 'bad'); reject(new Error('net')); };
      var fd = new FormData();
      fd.append('file', file);
      x.send(fd);
    });
  }

  function videoField(f, obj, wrap, help, changed, id) {
    var prev = h('div', { class: 'vthumb' });
    var kind = h('span', { class: 'vkind mono' });
    var input = h('input', { class: 'in', id: id, type: 'url', value: obj[f.k] || '', placeholder: 'Paste a YouTube, Vimeo or Google Drive link…', autocomplete: 'off', maxlength: 400 });
    var file = h('input', { type: 'file', class: 'file-in', accept: 'video/mp4,video/quicktime,video/webm,.mp4,.mov,.m4v,.webm', tabindex: '-1', 'aria-hidden': 'true' });
    var bar = h('i');
    var label = h('span', null, '');
    var prog = h('div', { class: 'vprog', hidden: true }, h('div', { class: 'vbar' }, bar), label);
    var upBtn = h('button', { class: 'btn btn-s', type: 'button', onclick: function () { file.click(); } }, ico('upload', 15), ' Upload a video file');
    var rmBtn = h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () { obj[f.k] = ''; input.value = ''; paint(); changed(); } }, 'Remove video');
    var lim = S.maxVideoUpload ? h('span', { class: 'help' }, 'Video files: MP4, MOV or WebM, up to ' + fmtSize(S.maxVideoUpload) + ' on your hosting. Bigger videos: use a YouTube or Vimeo link.') : null;
    function paint() {
      var v = obj[f.k] || '';
      prev.innerHTML = '';
      if (!v) prev.appendChild(h('span', null, ico('play', 26), h('small', null, 'No video yet')));
      else if (/^uploads\//.test(v) || isVideoFile(v)) prev.appendChild(h('video', { src: (/^https:/i.test(v) ? v : '../' + v) + '#t=0.6', controls: true, preload: 'metadata', playsinline: true }));
      else if (ytId(v)) prev.appendChild(h('img', { src: 'https://i.ytimg.com/vi/' + ytId(v) + '/hqdefault.jpg', alt: '' }));
      else prev.appendChild(h('span', null, ico('link', 26), h('small', null, videoKind(v))));
      kind.textContent = v ? '✓ ' + videoKind(v) : '';
      kind.style.display = v ? '' : 'none';
      rmBtn.disabled = !v;
    }
    input.addEventListener('input', function () { obj[f.k] = input.value.trim(); paint(); changed(); });
    file.addEventListener('change', function () {
      var fl = file.files && file.files[0];
      file.value = '';
      if (!fl) return;
      prog.hidden = false; bar.style.width = '0%'; label.textContent = 'Uploading… 0%';
      upBtn.disabled = true;
      uploadVideo(fl, bar, label).then(function (url) {
        obj[f.k] = url;
        input.value = url;
        paint();
        changed();
        toast('Video uploaded. Click “Save changes” to publish it.', 'ok');
      }).catch(function () {}).then(function () { prog.hidden = true; upBtn.disabled = false; });
    });
    paint();
    wrap.appendChild(h('label', { for: id }, f.label));
    wrap.appendChild(h('div', { class: 'vidf' }, prev, h('div', { class: 'acts' }, input, h('div', { class: 'row' }, upBtn, rmBtn, kind), prog, help, lim), file));
    return wrap;
  }

  function vtSub(item) {
    return (item.video ? videoKind(item.video) : 'No video yet') + (item.featured ? ' · on Home' : '');
  }

  function itemTitle(def, item, i) {
    var t = item[def.itemTitle];
    return (t && String(t).trim()) || ('Item ' + (i + 1));
  }

  function renderList(def, arr, path, ctx, sectionKey) {
    var wrap = h('div', { class: 'list' });
    var imgField = (def.fields || []).filter(function (f) { return f.type === 'image'; })[0];
    function draw() {
      wrap.innerHTML = '';
      if (!arr.length) wrap.appendChild(h('div', { class: 'empty' }, 'Nothing here yet. Use “' + (def.addLabel || 'Add') + '” below.'));
      arr.forEach(function (item, i) {
        if (!item.id) item.id = uid();
        var isOpen = openItems[item.id] !== undefined ? openItems[item.id] : arr.length <= 3;
        var titleEl = h('span', null, itemTitle(def, item, i));
        var sub = null;
        if (sectionKey === 'projects') sub = h('small', null, (item.category || 'No category') + (item.featured ? ' · on Home' : ''));
        if (sectionKey === 'services') sub = h('small', null, item.price ? ((item.pricePrefix ? item.pricePrefix + ' ' : '') + (S.content.servicesPage.currency || '') + item.price + (item.priceSuffix ? ' ' + item.priceSuffix : '')) : 'No price set');
        if (sectionKey === 'videoTestimonials') sub = h('small', null, vtSub(item));
        var el = h('div', { class: 'item' + (isOpen ? '' : ' closed') });
        var miniSrc = sectionKey === 'videoTestimonials' ? videoThumb(item) : (imgField && item[imgField.k] ? imgUrl(item[imgField.k]) : '');
        var mini = miniSrc ? h('img', { class: 'mini', src: miniSrc, alt: '' }) : (sectionKey === 'videoTestimonials' ? h('img', { class: 'mini', alt: '', style: 'visibility:hidden' }) : null);
        var head = h('div', { class: 'item-h', onclick: function (e) {
          if (e.target.closest('button')) return;
          var nowOpen = el.classList.contains('closed');
          el.classList.toggle('closed', !nowOpen);
          openItems[item.id] = nowOpen;
        } },
          h('span', { class: 'num' }, i + 1), mini,
          h('span', { class: 't' }, titleEl, sub),
          h('span', { class: 'tools' },
            h('button', { class: 'btn btn-s btn-i', type: 'button', title: 'Move up', 'aria-label': 'Move up', disabled: i === 0, onclick: function () { move(i, -1); } }, ico('up', 15)),
            h('button', { class: 'btn btn-s btn-i', type: 'button', title: 'Move down', 'aria-label': 'Move down', disabled: i === arr.length - 1, onclick: function () { move(i, 1); } }, ico('down', 15)),
            h('button', { class: 'btn btn-s btn-i btn-d', type: 'button', title: 'Remove', 'aria-label': 'Remove ' + itemTitle(def, item, i), onclick: function () {
              confirmBox('Remove “' + itemTitle(def, item, i) + '”?', 'It will disappear from your website after you click Save changes.', 'Remove', true).then(function (ok) {
                if (!ok) return;
                arr.splice(i, 1);
                ctx.changed();
                draw();
              });
            } }, ico('trash', 15))),
          h('span', { class: 'chev' }, ico('chev', 16)));
        var body = h('div', { class: 'item-b' }, renderFields(def.fields, item, path.concat(i), ctx, function (k) {
          if (k === def.itemTitle) titleEl.textContent = itemTitle(def, item, i);
          if (sub && sectionKey === 'videoTestimonials' && (k === 'video' || k === 'featured' || k === 'role')) sub.textContent = vtSub(item);
          if (sectionKey === 'videoTestimonials' && (k === 'video' || k === 'cover') && mini) { var ms = videoThumb(item); if (ms) { mini.src = ms; mini.style.visibility = ''; } else { mini.style.visibility = 'hidden'; } }
          if (sub && (k === 'category' || k === 'featured' || k.indexOf('price') === 0)) {
            if (sectionKey === 'projects') sub.textContent = (item.category || 'No category') + (item.featured ? ' · on Home' : '');
            if (sectionKey === 'services') sub.textContent = item.price ? ((item.pricePrefix ? item.pricePrefix + ' ' : '') + (S.content.servicesPage.currency || '') + item.price + (item.priceSuffix ? ' ' + item.priceSuffix : '')) : 'No price set';
          }
          if (imgField && k === imgField.k) draw();
        }));
        el.appendChild(head);
        el.appendChild(body);
        wrap.appendChild(el);
      });
      var full = def.max && arr.length >= def.max;
      wrap.appendChild(h('div', null, h('button', { class: 'btn', type: 'button', disabled: full, onclick: function () {
        var item = { id: uid() };
        (def.fields || []).forEach(function (f) {
          if (!f.k) return;
          if (f.type === 'toggle') item[f.k] = false;
          else if (f.type === 'list') item[f.k] = [];
          else if (f.type === 'select') item[f.k] = Object.keys(f.options || {})[0] || '';
          else item[f.k] = '';
        });
        var extra = NEW_ITEM[sectionKey] ? NEW_ITEM[sectionKey]() : {};
        Object.keys(extra).forEach(function (k) { item[k] = extra[k]; });
        arr.push(item);
        openItems[item.id] = true;
        ctx.changed();
        draw();
        var last = wrap.querySelectorAll('.item');
        last = last[last.length - 1];
        if (last) { last.scrollIntoView({ behavior: 'smooth', block: 'center' }); var inp = last.querySelector('.in,.ta'); if (inp) setTimeout(function () { inp.focus(); }, 300); }
      } }, ico('plus', 16), (def.addLabel || 'Add') + (full ? ' (limit reached)' : ''))));
    }
    function move(i, dir) {
      var j = i + dir;
      if (j < 0 || j >= arr.length) return;
      var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
      ctx.changed();
      draw();
    }
    draw();
    return wrap;
  }

  /* ------------------------------------------------------------ all images */
  function imageSlots() {
    var slots = [];
    Object.keys(S.schema).forEach(function (key) {
      var sec = S.schema[key];
      var c = S.content[key];
      if (sec.list) {
        (c || []).forEach(function (item, i) {
          sec.fields.forEach(function (f) {
            if (f.type === 'image') slots.push({ key: key, path: [i, f.k], group: sec.label, label: itemTitle(sec, item, i), field: f.label, value: item[f.k] || '' });
          });
        });
      } else {
        sec.fields.forEach(function (f) {
          if (f.type === 'image') slots.push({ key: key, path: [f.k], group: sec.label, label: f.label, field: '', value: c[f.k] || '' });
        });
      }
    });
    return slots;
  }
  function setPath(obj, path, val) {
    var o = obj;
    for (var i = 0; i < path.length - 1; i++) o = o[path[i]];
    o[path[path.length - 1]] = val;
  }
  function viewImages() {
    var slots = imageSlots();
    var groups = {};
    slots.forEach(function (s) { (groups[s.group] = groups[s.group] || []).push(s); });
    var nodes = [h('p', { class: 'intro-t' }, 'Every image on your website. Click “Replace” to upload a new one; it goes live as soon as the upload finishes. Tip: profile photos look best as PNGs with a transparent background.')];
    Object.keys(groups).forEach(function (g) {
      nodes.push(h('h2', { class: 'sec' }, g));
      nodes.push(h('div', { class: 'grid g3' }, groups[g].map(function (s) {
        var status = h('span', { class: 'uploading' });
        var file = h('input', { type: 'file', class: 'file-in', accept: 'image/jpeg,image/png,image/webp,image/gif', tabindex: '-1', 'aria-hidden': 'true' });
        file.addEventListener('change', function () {
          var fl = file.files && file.files[0];
          file.value = '';
          if (!fl) return;
          uploadFile(fl, status).then(function (url) { return replaceImage(s, url); }).catch(function () {});
        });
        return h('div', { class: 'slot' },
          h('div', { class: 'thumb' }, s.value ? h('img', { src: imgUrl(s.value), alt: '' }) : 'No image'),
          h('b', null, s.label), s.field ? h('span', null, s.field) : null,
          h('div', { class: 'row' },
            h('button', { class: 'btn btn-s', type: 'button', onclick: function () { file.click(); } }, ico('upload', 15), s.value ? ' Replace' : ' Upload'),
            s.value ? h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () {
              confirmBox('Remove this image?', 'Where an image is required, the website falls back to the built-in default.', 'Remove', true).then(function (ok) { if (ok) replaceImage(s, ''); });
            } }, 'Remove') : null),
          status, file);
      })));
    });
    var lib = h('div', null, h('p', { class: 'muted' }, 'Loading uploaded files…'));
    nodes.push(h('h2', { class: 'sec' }, 'Uploaded files'));
    nodes.push(lib);
    setMain('All images', nodes, null);
    api('media').then(function (r) {
      lib.innerHTML = '';
      var files = (r.data && r.data.files) || [];
      if (!files.length) { lib.appendChild(h('div', { class: 'empty' }, 'No uploaded files yet.')); return; }
      lib.appendChild(h('div', { class: 'grid g4' }, files.map(function (f) {
        var used = f.usedIn && f.usedIn.length;
        return h('div', { class: 'slot' },
          h('div', { class: 'thumb', style: 'height:110px' }, isVideoFile(f.url) ? h('video', { src: imgUrl(f.url) + '#t=0.6', preload: 'metadata', muted: true }) : h('img', { src: imgUrl(f.url), alt: '' })),
          h('span', null, fmtSize(f.size) + ' · ' + fmtDate(f.time)),
          h('span', null, used ? 'Used: ' + f.usedIn.join('; ') : 'Not used anywhere'),
          used ? null : h('div', { class: 'row' }, h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () {
            confirmBox('Delete this file?', 'It is not used on the website. This cannot be undone.', 'Delete', true).then(function (ok) {
              if (!ok) return;
              api('media_delete', { method: 'POST', body: { url: f.url } }).then(function (rr) {
                if (rr.data.ok) { toast('File deleted.', 'ok'); viewImages(); } else toast(rr.data.error || 'Could not delete.', 'bad');
              });
            });
          } }, ico('trash', 15), ' Delete')));
      })));
    });
  }
  function replaceImage(slot, url) {
    if (dirty[slot.key]) {
      toast('Save or discard your changes in “' + S.schema[slot.key].label + '” first.', 'bad');
      return Promise.resolve(false);
    }
    var data = clone(S.content[slot.key]);
    setPath(data, slot.path, url);
    return api('save', { method: 'POST', body: { section: slot.key, data: data } }).then(function (r) {
      if (!r.data.ok) { toast(r.data.error || 'Could not save.', 'bad'); return false; }
      S.content[slot.key] = r.data.data;
      S.savedAt = r.data.savedAt;
      broadcast();
      toast(url ? 'Image replaced. Your live website is updated.' : 'Image removed.', 'ok', { href: pageUrl(S.schema[slot.key].page), text: 'View page →' });
      viewImages();
      return true;
    });
  }

  /* ------------------------------------------------------------ theme */
  function viewTheme() {
    var cur = S.content.settings.theme;
    var cards = Object.keys(S.themes).map(function (id) {
      var t = S.themes[id];
      var on = id === cur;
      return h('button', { class: 'theme' + (on ? ' on' : ''), type: 'button', 'data-theme': id, 'aria-pressed': on ? 'true' : 'false', onclick: function () { applyTheme(id); } },
        h('div', { class: 'tp' }, h('div', { class: 'glow' }), h('div', { class: 'ttl' }, 'JESUN'), h('div', { class: 'card2' }), h('div', { class: 'pbtn' }, 'Hire me'), h('div', { class: 'hz' })),
        h('div', { class: 'tinfo' }, h('b', null, t.label), h('span', null, t.desc),
          h('div', { class: 'sw' }, t.swatch.map(function (c) { return h('i', { style: 'background:' + c }); }))),
        on ? h('span', { class: 'tag-on' }, 'Active') : null);
    });
    setMain('Colour theme', [
      h('p', { class: 'intro-t' }, 'Choose the colours for your whole website. Clicking a theme applies it to every page of the live site straight away.'),
      h('div', { class: 'grid g2' }, cards)
    ], null);
  }
  function applyTheme(id) {
    if (id === S.content.settings.theme) return;
    api('theme', { method: 'POST', body: { theme: id } }).then(function (r) {
      if (!r.data.ok) { toast(r.data.error || 'Could not change the theme.', 'bad'); return; }
      S.content.settings = r.data.data;
      if (drafts.settings) drafts.settings.theme = id;
      document.documentElement.setAttribute('data-theme', id);
      broadcast();
      toast(S.themes[id].label + ' is now live on your website.', 'ok', { href: '../index.php', text: 'See it →' });
      viewTheme();
    });
  }

  /* ------------------------------------------------------------ account */
  function viewAccount(done) {
    var msg = h('div', null, done ? h('div', { class: 'alert alert-ok' }, h('b', null, 'Your new login is saved. '), 'Use it the next time you log in. Any other device that was logged in has been logged out.') : null);
    function pwField(id, label, auto) {
      var inp = h('input', { class: 'in', id: id, type: 'password', autocomplete: auto });
      var b = h('button', { type: 'button', onclick: function () { inp.type = inp.type === 'password' ? 'text' : 'password'; b.textContent = inp.type === 'password' ? 'Show' : 'Hide'; } }, 'Show');
      return { input: inp, el: h('div', { class: 'field', 'data-k': id }, h('label', { for: id }, label), h('div', { class: 'pw' }, inp, b)) };
    }
    var cur = pwField('current', 'Current password', 'current-password');
    var user = h('input', { class: 'in', id: 'username', value: S.user, autocomplete: 'username' });
    var np = pwField('password', 'New password', 'new-password');
    var cf = pwField('confirm', 'Type the new password again', 'new-password');
    var btn = h('button', { class: 'btn btn-p', type: 'submit' }, ico('check', 16), 'Save new login');
    var form = h('form', { onsubmit: function (e) {
      e.preventDefault();
      msg.innerHTML = '';
      Array.prototype.forEach.call(form.querySelectorAll('.field'), function (f) { f.classList.remove('err'); });
      btn.disabled = true;
      api('account', { method: 'POST', body: { current: cur.input.value, username: user.value.trim(), password: np.input.value, confirm: cf.input.value } }).then(function (r) {
        btn.disabled = false;
        if (r.data.ok) {
          csrf = r.data.csrf || csrf;
          S.user = r.data.user;
          S.isDefault = false;
          drawSide();
          viewAccount(true);
          toast('Login updated.', 'ok');
        } else {
          msg.appendChild(h('div', { class: 'alert alert-e' }, r.data.error || 'Could not save.'));
          var f = r.data.field && form.querySelector('[data-k="' + r.data.field + '"]');
          if (f) f.classList.add('err');
        }
      }).catch(function () { btn.disabled = false; });
    } },
      msg,
      S.isDefault ? h('div', { class: 'alert alert-w' }, 'You are using the test login (admin / Admin@123). Choose your own before the website goes live.') : null,
      cur.el,
      h('div', { class: 'field', 'data-k': 'username' }, h('label', { for: 'username' }, 'Username'), user),
      np.el,
      h('p', { class: 'help', style: 'margin:-8px 0 16px' }, 'At least 10 characters, mixing upper-case letters, lower-case letters and numbers. Tip: 3–4 random words plus a number are strong and easy to remember. Don’t include your username.'),
      cf.el,
      btn);
    setMain('Login & password', [h('p', { class: 'intro-t' }, 'Change the username and password you use to log in here.'), h('div', { class: 'card', style: 'max-width:560px' }, form)], null);
  }

  /* ------------------------------------------------------------ messages */
  function viewMessages() {
    var box = h('div', null, h('p', { class: 'muted' }, 'Loading messages…'));
    setMain('Messages', [h('p', { class: 'intro-t' }, 'Enquiries sent from the contact form on your website. Every message is saved here, even when the email copy could not be sent.'), box], null);
    api('messages').then(function (r) { drawMessages(box, (r.data && r.data.messages) || []); });
  }
  var openMsg = {};
  var spamOpen = false;
  function drawMessages(box, list) {
    var inbox = list.filter(function (m) { return !m.spam; });
    var spam = list.filter(function (m) { return !!m.spam; });
    S.unread = inbox.filter(function (m) { return !m.read; }).length;
    S.messages = inbox.length;
    drawSide();
    box.innerHTML = '';
    function act(m, op) {
      return api('message', { method: 'POST', body: { id: m.id, op: op } }).then(function (r) {
        if (r.data.ok) {
          if (op === 'notspam') toast('Moved to your inbox.', 'ok');
          if (op === 'spam') toast('Moved to Possible spam.', 'ok');
          drawMessages(box, r.data.messages);
        }
      });
    }
    function msgEl(m) {
      var el = h('div', { class: 'msg' + (m.read || m.spam ? '' : ' unread') + (openMsg[m.id] ? ' open' : '') + (m.spam ? ' is-spam' : '') });
      var mailState = null;
      if (m.spam) mailState = h('span', { class: 'mstate warn' }, ico('shield', 13), m.spamReason || 'Caught by the anti-robot check');
      else if (m.emailed === true) mailState = h('span', { class: 'mstate ok' }, ico('check', 13), 'Emailed to you');
      else if (m.emailed === false) mailState = h('span', { class: 'mstate bad', title: m.emailError || '' }, ico('alert', 13), 'Not emailed');
      el.appendChild(h('div', { class: 'msg-h', onclick: function () {
        el.classList.toggle('open');
        openMsg[m.id] = el.classList.contains('open');
        if (!m.read && openMsg[m.id]) { m.read = true; el.classList.remove('unread'); act(m, 'read'); }
      } },
        h('div', { class: 'who' }, h('b', null, m.name || '(no name)'), h('span', null, (m.email || '') + (m.service ? ' · ' + m.service : ''))),
        mailState,
        h('span', { class: 'muted', style: 'font-size:12px' }, fmtDate(m.createdAt)), h('span', { class: 'chev' }, ico('chev', 16))));
      el.appendChild(h('div', { class: 'msg-b' },
        h('div', { class: 'kv' }, h('span', null, 'Email: ', h('b', null, m.email || '')), m.service ? h('span', null, 'Service: ', h('b', null, m.service)) : null, m.budget ? h('span', null, 'Budget: ', h('b', null, m.budget)) : null),
        h('pre', null, m.message || ''),
        m.emailed === false && m.emailError ? h('div', { class: 'alert alert-w slim' }, 'The email copy was not sent: ' + m.emailError + ' ', h('a', { href: '#/mail' }, 'Email notifications →')) : null,
        h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap' },
          m.email ? h('a', { class: 'btn btn-s btn-p', href: 'mailto:' + m.email + '?subject=' + encodeURIComponent('Re: your project enquiry') }, 'Reply by email') : null,
          m.spam ? h('button', { class: 'btn btn-s', type: 'button', onclick: function () { act(m, 'notspam'); } }, ico('check', 14), ' Not spam') : h('button', { class: 'btn btn-s', type: 'button', onclick: function () { act(m, m.read ? 'unread' : 'read'); } }, m.read ? 'Mark as unread' : 'Mark as read'),
          m.spam ? null : h('button', { class: 'btn btn-s', type: 'button', onclick: function () { act(m, 'spam'); } }, ico('shield', 14), ' Spam'),
          h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () {
            confirmBox('Delete this message?', 'This cannot be undone.', 'Delete', true).then(function (ok) { if (ok) act(m, 'delete'); });
          } }, ico('trash', 15), ' Delete'))));
      return el;
    }
    if (!inbox.length) box.appendChild(h('div', { class: 'empty' }, 'No messages yet. When someone sends the contact form, it appears here.'));
    inbox.forEach(function (m) { box.appendChild(msgEl(m)); });
    if (spam.length) {
      var det = h('details', { class: 'spam-box' },
        h('summary', null, ico('shield', 16), ' Possible spam (' + spam.length + ')'),
        h('p', { class: 'muted', style: 'font-size:13px;margin:4px 0 12px' }, 'These messages were caught by the anti-robot check, so they were not emailed to you. If one is from a real person, click “Not spam”.'),
        spam.map(msgEl),
        h('button', { class: 'btn btn-s btn-d', type: 'button', onclick: function () {
          confirmBox('Delete all spam?', 'Every message under Possible spam is removed. This cannot be undone.', 'Delete all', true).then(function (ok) {
            if (!ok) return;
            api('spam_clear', { method: 'POST' }).then(function (r) { if (r.data.ok) { toast('Spam deleted.', 'ok'); drawMessages(box, r.data.messages); } });
          });
        } }, ico('trash', 15), ' Delete all spam'));
      det.open = spamOpen;
      det.addEventListener('toggle', function () { spamOpen = det.open; });
      box.appendChild(det);
    }
  }

  /* ------------------------------------------------------------ email notifications */
  function mailNeedsSetup() {
    var m = S && S.mail;
    if (!m || m.method === 'off') return false;
    var st = m.status || {};
    if (st.at && st.ok === false) return true;
    if (m.method === 'php') return st.ok !== true;
    return false;
  }
  function viewMail(result) {
    var m = S.mail || {};
    var f = { method: m.method || 'php', to: m.to || '', gmailUser: m.gmailUser || '', gmailPass: '', host: m.host || '', port: m.port || 587, security: m.security || 'starttls', user: m.user || '', smtpPass: '', fromEmail: m.fromEmail || '', fromName: m.fromName || '' };
    var contactEmail = (S.content.contact && S.content.contact.email) || '';
    var resBox = h('div', { 'aria-live': 'polite' });
    var panel = h('div');
    function inp(k, label, opts) {
      opts = opts || {};
      var id = 'm_' + k;
      var input = h('input', { class: 'in', id: id, type: opts.type || 'text', value: f[k] == null ? '' : String(f[k]), placeholder: opts.ph || null, autocomplete: opts.auto || 'off', spellcheck: 'false', autocapitalize: 'none' });
      input.addEventListener('input', function () { f[k] = input.value; });
      var ctl = input;
      if (opts.type === 'password') {
        var sb = h('button', { type: 'button', onclick: function () { input.type = input.type === 'password' ? 'text' : 'password'; sb.textContent = input.type === 'password' ? 'Show' : 'Hide'; } }, 'Show');
        ctl = h('div', { class: 'pw' }, input, sb);
      }
      return h('div', { class: 'field', 'data-mk': k }, h('label', { for: id }, label), ctl, opts.help ? h('span', { class: 'help' }, opts.help) : null);
    }
    function sel(k, label, options) {
      var id = 'm_' + k;
      var s2 = h('select', { class: 'sel', id: id });
      Object.keys(options).forEach(function (v) { s2.appendChild(h('option', { value: v, selected: String(f[k]) === v }, options[v])); });
      s2.addEventListener('change', function () {
        f[k] = s2.value;
        if (k === 'security') {
          var port = document.getElementById('m_port');
          if (port && (port.value === '' || port.value === '465' || port.value === '587' || port.value === '25')) { port.value = s2.value === 'ssl' ? '465' : (s2.value === 'starttls' ? '587' : '25'); f.port = port.value; }
        }
      });
      return h('div', { class: 'field', 'data-mk': k }, h('label', { for: id }, label), s2);
    }
    var METHODS = [
      ['gmail', 'Gmail', 'Recommended. Free and reliable — uses a Gmail “App Password”.'],
      ['smtp', 'Email account from your hosting', 'Use an address on your own domain, e.g. hello@yourdomain.com.'],
      ['php', 'Basic hosting mail', 'Nothing to set up, but emails often land in spam, and it does not work on a local computer (MAMP).'],
      ['off', 'Off', 'Don’t send emails. Enquiries are still saved in Messages.']
    ];
    var cards = h('div', { class: 'mcards', role: 'radiogroup', 'aria-label': 'How should your website send emails?' });
    function drawCards() {
      cards.innerHTML = '';
      METHODS.forEach(function (mt) {
        var on = f.method === mt[0];
        cards.appendChild(h('button', { class: 'mcard' + (on ? ' on' : ''), type: 'button', role: 'radio', 'aria-checked': on ? 'true' : 'false', onclick: function () { f.method = mt[0]; drawCards(); drawPanel(); } },
          h('span', { class: 'mdot' }), h('b', null, mt[1]), h('span', null, mt[2])));
      });
    }
    function drawPanel() {
      panel.innerHTML = '';
      if (f.method === 'gmail') {
        panel.appendChild(h('ol', { class: 'steps' },
          h('li', null, 'In your Google Account, turn on ', h('b', null, '2-Step Verification'), ' (Security → How you sign in to Google).'),
          h('li', null, 'Open ', h('a', { href: 'https://myaccount.google.com/apppasswords', target: '_blank', rel: 'noopener' }, 'myaccount.google.com/apppasswords'), ', type a name such as “Website” and click ', h('b', null, 'Create'), '.'),
          h('li', null, 'Copy the 16-letter password Google shows you and paste it below. (Google only shows it once.)')));
        panel.appendChild(inp('gmailUser', 'Your Gmail address', { type: 'email', ph: 'yourname@gmail.com', auto: 'email' }));
        panel.appendChild(inp('gmailPass', 'App Password (16 letters)', { type: 'password', ph: m.hasGmailPass ? '•••• saved — leave empty to keep it' : 'abcd efgh ijkl mnop', auto: 'new-password', help: 'This is not your normal Gmail password. It is stored encrypted and never shown again.' }));
        panel.appendChild(inp('fromName', 'Sender name (optional)', { ph: (S.content.settings.siteName || 'Your') + ' website' }));
      } else if (f.method === 'smtp') {
        panel.appendChild(h('p', { class: 'note' }, 'Find these details in your hosting’s Email section (for example on Hostinger: server smtp.hostinger.com, port 465, SSL). The username is usually the full email address.'));
        panel.appendChild(h('div', { class: 'grid g2', style: 'gap:0 16px' },
          inp('host', 'Outgoing mail server (SMTP)', { ph: 'smtp.yourhosting.com' }),
          h('div', { class: 'grid g2', style: 'gap:0 12px' }, inp('port', 'Port', { type: 'number', ph: '465' }), sel('security', 'Security', { ssl: 'SSL / TLS', starttls: 'STARTTLS', none: 'None' }))));
        panel.appendChild(h('div', { class: 'grid g2', style: 'gap:0 16px' },
          inp('user', 'Username', { ph: 'hello@yourdomain.com', auto: 'username' }),
          inp('smtpPass', 'Password', { type: 'password', ph: m.hasSmtpPass ? '•••• saved — leave empty to keep it' : '', auto: 'new-password', help: 'Stored encrypted and never shown again.' })));
        panel.appendChild(h('div', { class: 'grid g2', style: 'gap:0 16px' },
          inp('fromEmail', 'Send from (email address)', { type: 'email', ph: 'Usually the same as the username' }),
          inp('fromName', 'Sender name (optional)', { ph: (S.content.settings.siteName || 'Your') + ' website' })));
      } else if (f.method === 'php') {
        panel.appendChild(h('div', { class: 'alert alert-w slim' }, 'Basic hosting mail is free and needs no setup, but emails often go to spam, and it never works while the website runs on your own computer (MAMP). For reliable delivery choose Gmail.'));
      } else {
        panel.appendChild(h('p', { class: 'note' }, 'No emails are sent. You will see new enquiries only under Messages in this Admin Panel.'));
      }
    }
    function statusNode() {
      var st = (S.mail && S.mail.status) || {};
      if (!st.at) return h('div', { class: 'mstatus' }, h('span', { class: 'ms-ico idle' }, ico('send', 18)), h('div', null, h('b', null, 'Not tested yet'), h('span', null, 'Choose a method below, then click “Save & send test email”.')));
      if (st.ok) return h('div', { class: 'mstatus' }, h('span', { class: 'ms-ico ok' }, ico('check', 18)), h('div', null, h('b', null, 'Working'), h('span', null, 'Last email ' + (st.kind === 'test' ? '(a test) ' : '') + 'sent ' + fmtDate(st.at) + '.')));
      return h('div', { class: 'mstatus' }, h('span', { class: 'ms-ico bad' }, ico('alert', 18)), h('div', null, h('b', null, 'Not working yet'), h('span', null, 'Last attempt ' + fmtDate(st.at) + ': ' + (st.error || 'failed') + '.')));
    }
    var statusWrap = h('div', null, statusNode());
    function markErrors(errs) {
      Array.prototype.forEach.call(document.querySelectorAll('[data-mk].err'), function (x) { x.classList.remove('err'); var fe = x.querySelector('.ferr'); if (fe) fe.remove(); });
      (errs || []).forEach(function (er) {
        var el = document.querySelector('[data-mk="' + er.field + '"]');
        if (el) { el.classList.add('err'); el.appendChild(h('span', { class: 'ferr' }, er.msg)); }
      });
    }
    function send(action, btn) {
      resBox.innerHTML = '';
      markErrors([]);
      btn.disabled = true;
      var old = btn.innerHTML;
      btn.innerHTML = '';
      btn.appendChild(h('span', { class: 'spinner' }));
      btn.appendChild(document.createTextNode(action === 'mail_test' ? ' Sending test…' : ' Saving…'));
      var body = { settings: clone(f) };
      api(action, { method: 'POST', body: body }).then(function (r) {
        btn.disabled = false;
        btn.innerHTML = old;
        if (r.data.mail) S.mail = r.data.mail;
        if (r.status === 422) {
          markErrors(r.data.errors);
          resBox.appendChild(h('div', { class: 'alert alert-e' }, r.data.error || 'Please check the highlighted fields.'));
          return;
        }
        f.gmailPass = '';
        f.smtpPass = '';
        if (action === 'mail_save') {
          if (r.data.ok) { toast('Email settings saved.', 'ok'); viewMail(); }
          else resBox.appendChild(h('div', { class: 'alert alert-e' }, r.data.error || 'Could not save.'));
          return;
        }
        viewMail(r.data);
      }).catch(function () { btn.disabled = false; btn.innerHTML = old; });
    }
    if (result) {
      if (result.ok) resBox.appendChild(h('div', { class: 'alert alert-ok' }, h('b', null, 'Test email sent to ' + result.to + '. '), 'Check your inbox — and the spam folder the first time. It should arrive within a minute. ' + (result.detail && S.mail.method === 'php' ? result.detail : '')));
      else resBox.appendChild(h('div', { class: 'alert alert-e' }, h('b', null, 'The test email was not sent. '), result.error || '', result.detail ? h('small', { class: 'tech' }, 'Technical detail: ' + result.detail) : null));
    }
    drawCards();
    drawPanel();
    var testBtn = h('button', { class: 'btn btn-p', type: 'button', onclick: function () { send('mail_test', testBtn); } }, ico('send', 16), 'Save & send test email');
    var saveBtn = h('button', { class: 'btn', type: 'button', onclick: function () { send('mail_save', saveBtn); } }, 'Save without testing');
    setMain('Email notifications', [
      h('p', { class: 'intro-t' }, 'Every enquiry from your contact form is saved in Messages. Here you choose how your website also emails it to you, so you never miss a client.'),
      statusWrap,
      resBox,
      h('div', { class: 'card' },
        h('div', { class: 'hd' }, 'Where should enquiries go?'),
        inp('to', 'Send enquiries to', { type: 'email', ph: contactEmail || 'you@example.com', help: contactEmail ? 'Leave empty to use your contact email (' + contactEmail + ').' : 'The email address that should receive enquiries.' }),
        h('div', { class: 'hd' }, 'How should your website send emails?'),
        cards,
        panel,
        h('div', { class: 'row', style: 'margin-top:20px;gap:10px;flex-wrap:wrap' }, testBtn, saveBtn))
    ], null);
  }

  /* ------------------------------------------------------------ backups */
  function viewBackups() {
    var box = h('div', null, h('p', { class: 'muted' }, 'Loading backups…'));
    setMain('Backups', [h('p', { class: 'intro-t' }, 'A copy of your website content is kept automatically before each save (the latest 25). Restore one to undo mistakes. Images are not affected.'), box], null);
    api('backups').then(function (r) {
      box.innerHTML = '';
      var list = (r.data && r.data.backups) || [];
      if (!list.length) { box.appendChild(h('div', { class: 'empty' }, 'No backups yet. They are created when you save changes.')); return; }
      box.appendChild(h('div', { class: 'card bk' }, list.map(function (b) {
        return h('div', { class: 'bk-row' },
          h('span', { class: 'bk-t' }, fmtDate(b.time)), h('span', { class: 'muted bk-s' }, fmtSize(b.size)),
          h('button', { class: 'btn btn-s', type: 'button', onclick: function () { restoreBackup(b); } }, 'Restore'));
      })));
    });
  }
  function restoreBackup(b) {
    confirmBox('Restore this version?', 'Your website content goes back to how it was on ' + fmtDate(b.time) + '. The current version is backed up first, so you can undo this.', 'Restore').then(function (ok) {
      if (!ok) return;
      api('restore', { method: 'POST', body: { file: b.file } }).then(function (rr) {
        if (!rr.data.ok) { toast(rr.data.error || 'Could not restore.', 'bad'); return; }
        S.content = rr.data.content;
        drafts = {};
        dirty = {};
        document.documentElement.setAttribute('data-theme', S.content.settings.theme);
        broadcast();
        toast('Backup restored. Your live website is updated.', 'ok', { href: '../index.php', text: 'View website →' });
        viewBackups();
      });
    });
  }

  boot();
})();
