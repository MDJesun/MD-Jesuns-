/*
  MD Jesun — website interactions (plain JavaScript, no libraries).
  All content comes from the Admin Panel; this file only adds motion and behaviour.
*/
(function () {
  'use strict';

  var w = window;
  var doc = document;
  var $ = function (s, r) { return (r || doc).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || doc).querySelectorAll(s)); };
  var pad = function (v) { return String(v).padStart(2, '0'); };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var site = $('#site');
  var reduced = !!(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var fine = !!(w.matchMedia && w.matchMedia('(hover: hover) and (pointer: fine)').matches);
  var cfg = { autoplay: true, cursor: true, timeline: true };
  try { cfg = Object.assign(cfg, JSON.parse(($('#mdj-config') || {}).textContent || '{}')); } catch (e) { /* defaults */ }
  var raf = w.requestAnimationFrame.bind(w);
  var now = function () { return (w.performance && w.performance.now) ? w.performance.now() : Date.now(); };

  /* -------- live refresh: when you save in the Admin Panel, open pages reload -------- */
  if ('BroadcastChannel' in w) {
    try {
      var bc = new BroadcastChannel('mdj-cms');
      bc.onmessage = function (ev) { if (ev.data && ev.data.type === 'saved') w.location.reload(); };
    } catch (e) { /* ignore */ }
  }

  /* ================================================================
     Scroll reveals — each item appears on its own as it scrolls in,
     in reading order, one after another.
     ================================================================ */
  var canAnim = !reduced && typeof doc.documentElement.animate === 'function';
  var EASE = 'cubic-bezier(.22,1,.36,1)';
  var items = $$('.rv, .rv-t, .tw');
  site.classList.add('ready');

  function play(el, frames, opts) {
    try { return el.animate(frames, opts); } catch (e) { return null; }
  }

  /* labels "decode" from random characters into the real text */
  var GLYPHS = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789#%&*+=<>/';
  function scramble(el, delay) {
    if (!canAnim || el.getAttribute('data-scr') !== '1') return;
    el.setAttribute('data-scr', 'done');
    var finalText = el.textContent;
    el.textContent = '';
    var real = doc.createElement('span');
    real.className = 'vh';
    real.textContent = finalText;
    var shown = doc.createElement('span');
    shown.setAttribute('aria-hidden', 'true');
    el.appendChild(real);
    el.appendChild(shown);
    var chars = finalText.split('');
    var total = chars.length || 1;
    var dur = Math.min(1200, 420 + total * 24);
    var t0 = 0;
    function frame(ts) {
      if (!t0) t0 = ts;
      var p = (ts - t0) / dur;
      var out = '';
      for (var i = 0; i < chars.length; i++) {
        var c = chars[i];
        if (c === ' ' || p >= 0.22 + (i / total) * 0.72) out += c;
        else out += GLYPHS.charAt((Math.random() * GLYPHS.length) | 0);
      }
      shown.textContent = out;
      if (p < 1) raf(frame); else shown.textContent = finalText;
    }
    shown.textContent = finalText.replace(/\S/g, ' ');
    setTimeout(function () { raf(frame); }, delay);
  }

  /* numbers count up: "1000+" → 0 … 1000+ (non-numbers such as "[X]+" stay as they are) */
  function countUp(el) {
    var raw = el.getAttribute('data-count') || el.textContent;
    var m = String(raw).match(/^(\D*?)(\d[\d,]*)(.*)$/);
    if (!m || !canAnim || el.getAttribute('data-counted')) return;
    el.setAttribute('data-counted', '1');
    var target = parseInt(m[2].replace(/,/g, ''), 10);
    if (!isFinite(target) || target < 2) return;
    var comma = m[2].indexOf(',') > -1;
    var dur = target > 50 ? 1800 : 1300;
    var t0 = 0;
    function fmt(n) {
      var s = String(n);
      if (comma) s = s.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      return m[1] + s + m[3];
    }
    el.textContent = fmt(0);
    function frame(ts) {
      if (!t0) t0 = ts;
      var p = Math.min(1, (ts - t0) / dur);
      el.textContent = fmt(Math.round(target * (1 - Math.pow(1 - p, 4))));
      if (p < 1) raf(frame); else el.textContent = raw;
    }
    raf(frame);
  }

  function afterReveal(el, delay) {
    var counters = el.hasAttribute('data-count') ? [el] : $$('[data-count]', el);
    if (!counters.length) return;
    setTimeout(function () { counters.forEach(countUp); }, (delay || 0) + 150);
  }

  function reveal(el, delay) {
    if (el.classList.contains('in')) return;
    var d = Math.max(0, Math.round(delay || 0));
    el.classList.add('in');
    if (!canAnim) return;
    var c = el.classList;
    if (c.contains('rv-t')) {
      $$('.ln > span', el).forEach(function (sp, i) {
        play(sp, [{ transform: 'translateY(115%)' }, { transform: 'translateY(0)' }], { duration: 1150, delay: d + i * 140, easing: EASE, fill: 'backwards' });
      });
    } else if (c.contains('tw')) {
      var n = parseInt(w.getComputedStyle(el).getPropertyValue('--n'), 10) || 20;
      play(el, [{ clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)' }], { duration: Math.min(1300, n * 34), delay: d, easing: 'steps(' + n + ')', fill: 'backwards' });
      scramble(el, d);
    } else if (c.contains('rv-wipe')) {
      play(el, [{ clipPath: 'inset(0 0 100% 0 round 10px)', transform: 'scale(1.05)' }, { clipPath: 'inset(0 0 0% 0 round 10px)', transform: 'scale(1)' }], { duration: 1500, delay: d, easing: 'cubic-bezier(.65,0,.35,1)', fill: 'backwards' });
    } else if (c.contains('rv-card')) {
      play(el, [{ opacity: 0, transform: 'translateY(60px) scale(.95)' }, { opacity: 1, transform: 'translateY(0) scale(1)' }], { duration: 1150, delay: d, easing: EASE, fill: 'backwards' });
    } else {
      play(el, [{ opacity: 0, transform: 'translateY(32px)', filter: 'blur(6px)' }, { opacity: 1, transform: 'translateY(0)', filter: 'blur(0px)' }], { duration: 1050, delay: d, easing: EASE, fill: 'backwards' });
    }
    afterReveal(el, d);
  }

  var queue = [];
  var queued = false;
  var nextAt = 0;
  function flush() {
    queued = false;
    if (!queue.length) return;
    queue.sort(function (a, b) {
      var p = a.compareDocumentPosition(b);
      return (p & 4) ? -1 : ((p & 2) ? 1 : 0);
    });
    var t = now();
    var start = Math.max(t, Math.min(nextAt, t + 380));
    var gap = queue.length > 6 ? Math.max(55, Math.round(760 / queue.length)) : 125;
    queue.forEach(function (el, i) { reveal(el, start - t + i * gap); });
    nextAt = start + queue.length * gap;
    queue = [];
  }

  if (!canAnim || !('IntersectionObserver' in w)) {
    items.forEach(function (el) { el.classList.add('in'); });
  } else {
    var rio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        rio.unobserve(en.target);
        queue.push(en.target);
      });
      if (queue.length && !queued) { queued = true; raf(flush); }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0 });
    items.forEach(function (el) {
      var r = el.getBoundingClientRect();
      if (r.bottom < 0) { el.classList.add('in'); } // already scrolled past: just show it
      else rio.observe(el);
    });
  }

  /* bigger set pieces (hologram, 3D stage, letterbox, planet) start when their section arrives */
  var secs = $$('[data-sec]');
  if (reduced || !('IntersectionObserver' in w)) {
    secs.forEach(function (s) { s.classList.add('on'); });
  } else {
    var sio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('on'); sio.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -14% 0px', threshold: 0 });
    secs.forEach(function (s) { sio.observe(s); });
  }

  /* ================================================================
     Navigation, progress line, timecode timeline
     ================================================================ */
  var sprog = $('#sprog');
  var menuBtn = $('#menuBtn');
  var menu = $('#mmenu');
  if (menuBtn && menu) {
    var setMenu = function (open) {
      menu.hidden = !open;
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    menuBtn.addEventListener('click', function () { setMenu(menu.hidden); });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); menuBtn.focus(); } });
  }

  var hud = null;
  var chapters = $$('[data-chapter]');
  var clipEls = [];
  var hudLbl, hudTc, hudHead;
  var chapterTops = [];
  var docMax = 1;

  function measure() {
    var sy = w.scrollY;
    docMax = Math.max(1, doc.documentElement.scrollHeight - w.innerHeight);
    chapterTops = chapters.map(function (c) { return Math.max(0, c.getBoundingClientRect().top + sy); });
    if (!hud) return;
    var full = doc.documentElement.scrollHeight;
    chapterTops.forEach(function (top, i) {
      var end = i < chapterTops.length - 1 ? chapterTops[i + 1] : full;
      if (clipEls[i]) clipEls[i].style.flexGrow = String(Math.max(1, end - top));
    });
  }

  if (cfg.timeline && fine && chapters.length > 1) {
    hud = doc.createElement('div');
    hud.className = 'tlhud';
    hud.innerHTML = '<div class="tlh-tc">00:00:00:00</div><div class="tlh-track"><div class="tlh-clips"></div><div class="tlh-head"><span class="tlh-tag"></span></div></div><div class="tlh-rec"><span class="rec"></span></div>';
    var clipsBox = $('.tlh-clips', hud);
    chapters.forEach(function (c, i) {
      var b = doc.createElement('button');
      b.type = 'button';
      b.className = 'tlh-clip';
      b.tabIndex = -1;
      b.title = c.getAttribute('data-chapter') || '';
      b.addEventListener('click', function () {
        var top = c.getBoundingClientRect().top + w.scrollY - (i === 0 ? 0 : 70);
        w.scrollTo({ top: Math.max(0, top), behavior: reduced ? 'auto' : 'smooth' });
      });
      clipsBox.appendChild(b);
      clipEls.push(b);
    });
    hud.setAttribute('aria-hidden', 'true');
    hudLbl = $('.tlh-tag', hud);
    hudTc = $('.tlh-tc', hud);
    hudHead = $('.tlh-head', hud);
    site.appendChild(hud);
  }

  var lastChapter = -1;
  var flashTimer = 0;
  function onScroll() {
    var sy = w.scrollY;
    site.classList.toggle('scrolled', sy > 40);
    var p = Math.min(1, Math.max(0, sy / docMax));
    if (sprog) sprog.style.transform = 'scaleX(' + p.toFixed(4) + ')';
    if (!hud) return;
    hud.classList.toggle('show', sy > 160);
    var probe = sy + w.innerHeight * 0.42;
    var cur = 0;
    for (var i = 0; i < chapterTops.length; i++) { if (chapterTops[i] <= probe) cur = i; }
    if (cur !== lastChapter) {
      var first = lastChapter === -1;
      lastChapter = cur;
      hudLbl.textContent = chapters[cur].getAttribute('data-chapter') || '';
      clipEls.forEach(function (b, j) { b.classList.toggle('on', j === cur); });
      if (!first && sy > 160) {
        // briefly show the name of the section you just entered
        hud.classList.add('flash');
        clearTimeout(flashTimer);
        flashTimer = setTimeout(function () { hud.classList.remove('flash'); }, 1800);
      }
    }
    var frames = Math.round(sy / 2.2);
    var secs2 = Math.floor(frames / 24);
    hudTc.textContent = pad(Math.floor(secs2 / 3600)) + ':' + pad(Math.floor(secs2 / 60) % 60) + ':' + pad(secs2 % 60) + ':' + pad(frames % 24);
    hudHead.style.top = (p * 100).toFixed(2) + '%';
  }
  var ticking = false;
  w.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    raf(function () { ticking = false; onScroll(); });
  }, { passive: true });
  var remeasure = function () { measure(); onScroll(); };
  w.addEventListener('resize', remeasure);
  w.addEventListener('load', remeasure);
  if ('ResizeObserver' in w) { try { new ResizeObserver(function () { remeasure(); }).observe(doc.body); } catch (e) { /* ignore */ } }
  remeasure();

  /* ================================================================
     Video pop-up player
     ================================================================ */
  var lbx = $('#lbx');
  var lbxFrame = $('#lbxFrame');
  var lastFocus = null;
  function embedFor(url) {
    var u = String(url || '').trim();
    var m = u.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
    if (m) return { type: 'iframe', src: 'https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&rel=0' };
    m = u.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (m) return { type: 'iframe', src: 'https://player.vimeo.com/video/' + m[1] + '?autoplay=1' };
    m = u.match(/drive\.google\.com\/(?:file\/d\/|open\?(?:.*&)?id=|uc\?(?:.*&)?id=)([A-Za-z0-9_-]{10,})/);
    if (m) return { type: 'iframe', src: 'https://drive.google.com/file/d/' + m[1] + '/preview' };
    if (/\.(mp4|webm|mov)(\?|#|$)/i.test(u)) return { type: 'video', src: u };
    if (/^https?:\/\//i.test(u)) return { type: 'link', src: u };
    return null;
  }
  function openVideo(url, title, format) {
    if (!lbx) return;
    var em = embedFor(url);
    if (em && em.type === 'link') { w.open(em.src, '_blank', 'noopener'); return; }
    lastFocus = doc.activeElement;
    lbxFrame.innerHTML = '';
    lbx.classList.toggle('lbx-v', format === 'vertical');
    lbx.classList.toggle('lbx-sq', format === 'square');
    $$('video.vc-prev').forEach(function (v) { try { v.pause(); } catch (e) { /* ignore */ } });
    if (!em) {
      lbxFrame.innerHTML = '<div class="lbx-msg"><b>Coming soon</b><span>' + esc(title || 'This video') + ' will be online shortly.</span></div>';
    } else if (em.type === 'iframe') {
      var f = doc.createElement('iframe');
      f.src = em.src;
      f.title = title || 'Video';
      f.allow = 'autoplay; fullscreen; picture-in-picture';
      f.allowFullscreen = true;
      lbxFrame.appendChild(f);
    } else {
      var v = doc.createElement('video');
      v.src = em.src;
      v.controls = true;
      v.autoplay = true;
      v.playsInline = true;
      lbxFrame.appendChild(v);
    }
    lbx.classList.add('open');
    lbx.setAttribute('aria-hidden', 'false');
    var c = $('#lbxClose');
    if (c) c.focus();
  }
  function closeVideo() {
    if (!lbx || !lbx.classList.contains('open')) return;
    lbx.classList.remove('open');
    lbx.setAttribute('aria-hidden', 'true');
    setTimeout(function () { lbxFrame.innerHTML = ''; }, 400);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  if (lbx) {
    $('#lbxClose').addEventListener('click', closeVideo);
    lbx.addEventListener('click', function (e) { if (e.target === lbx) closeVideo(); });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeVideo(); });
  }
  $$('[data-video]').forEach(function (el) {
    if (el.closest('#cf3d')) return;
    el.addEventListener('click', function (e) {
      var url = el.getAttribute('data-video') || '';
      var isReelFrame = el.classList.contains('reel-frame');
      if (!url && !isReelFrame) return; // e.g. hero "Showreel" button just scrolls to the showreel
      e.preventDefault();
      openVideo(url, el.getAttribute('data-video-title') || '', el.getAttribute('data-video-format') || '');
    });
  });

  /* video testimonials: uploaded videos preview silently on hover */
  if (fine) {
    $$('.vcard, .vf-screen').forEach(function (card) {
      var v = card.querySelector('video.vc-prev');
      if (!v) return;
      var timer = 0;
      card.addEventListener('mouseenter', function () {
        timer = setTimeout(function () {
          v.muted = true;
          var p = v.play();
          card.classList.add('previewing');
          if (p && p.catch) p.catch(function () { card.classList.remove('previewing'); });
        }, 260);
      });
      card.addEventListener('mouseleave', function () {
        clearTimeout(timer);
        card.classList.remove('previewing');
        try { v.pause(); v.currentTime = 0.6; } catch (e) { /* ignore */ }
      });
    });
  }

  /* running timecode on the featured testimonial screen */
  var tcEl = $('[data-tc]');
  if (tcEl && canAnim) {
    var tcOn = false, tcStart = 0;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var tick = function (ts) {
      if (!tcOn) return;
      if (!tcStart) tcStart = ts;
      var t = (ts - tcStart) / 1000;
      var fr = Math.floor((t % 1) * 25);
      var sec = Math.floor(t);
      tcEl.textContent = pad(Math.floor(sec / 3600)) + ':' + pad(Math.floor(sec / 60) % 60) + ':' + pad(sec % 60) + ':' + pad(fr);
      raf(tick);
    };
    if ('IntersectionObserver' in w) {
      new IntersectionObserver(function (ents) {
        ents.forEach(function (en) {
          var was = tcOn;
          tcOn = en.isIntersecting;
          if (tcOn && !was) { tcStart = 0; raf(tick); }
        });
      }).observe(tcEl);
    }
  }

  /* ================================================================
     Featured work carousel (Home)
     ================================================================ */
  var stage = $('#cfStage');
  var track = $('#cf3d');
  var dataEl = $('#featured-data');
  if (stage && track && dataEl) {
    var ALL = [];
    try { ALL = JSON.parse(dataEl.textContent || '[]'); } catch (e) { ALL = []; }
    var ticksEl = $('#cfTicks');
    var numEl = $('#cfNum');
    var totEl = $('#cfTot');
    var X = [0, 290, 525, 720, 880];
    var Z = [70, -150, -330, -500, -620];
    var R = [0, 26, 34, 38, 40];
    var B = [0, 4, 7, 10, 12];
    var BR = [1, 0.5, 0.36, 0.26, 0.2];
    var filter = 'All';
    var idx = Math.min(4, Math.max(0, ALL.length - 1));
    var list = [];
    var cards = [];
    var ticks = [];
    var paused = false;
    var lastAdvance = Date.now();
    var dragX = null;
    var dragged = false;
    var SLIDE_MS = 5500;
    if (!cfg.autoplay) stage.classList.add('autoplay-off');

    var cardHTML = function (p, i, n) {
      return '' +
        '<span class="cf-media">' +
          '<img class="cf-img" src="' + esc(p.img) + '" alt="" decoding="async">' +
          '<span class="cf-shade"></span>' +
          '<span class="cf-frost"><span class="cf-num">' + pad(ALL.indexOf(p) + 1) + '</span><span class="cf-ftitle">' + esc(p.title) + '</span><span class="mono cf-fcat">' + esc(p.cat) + '</span></span>' +
          '<span class="cf-top"><span class="chip-c"><span class="av"></span><span class="cname"><span class="mono cname-l">Client</span><span class="cname-v">' + esc(p.client || '—') + '</span></span></span><span class="mono cidx">' + pad(i + 1) + ' / ' + pad(n) + '</span></span>' +
          '<span class="cf-info"><span class="mono cmeta">' + esc(p.cat) + (p.time ? ' · ' + esc(p.time) : '') + '</span><span class="display ctitle">' + esc(p.title) + '</span>' +
            '<span class="crow"><span class="cbrief">' + esc(p.result) + '</span>' + (p.video ? '<span class="cplay"><svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z" fill="#FFFFFF"></path></svg></span>' : '') + '</span></span>' +
          '<span class="cf-prog"><span class="cf-bar"></span></span>' +
          '<span class="cf-ring"></span>' +
        '</span>';
    };

    var layout = function () {
      var n = list.length;
      var half = Math.floor(n / 2);
      cards.forEach(function (el, i) {
        var d = i - idx;
        if (n > 2) { d = ((d % n) + n) % n; if (d > half) d -= n; }
        var ad = Math.abs(d);
        var sg = Math.sign(d);
        var k = Math.min(ad, 4);
        var vis = ad <= 3;
        var active = ad === 0;
        el.style.transform = 'translate(-50%,-50%) translateX(' + (sg * X[k]) + 'px) translateZ(' + Z[k] + 'px) rotateY(' + (-sg * R[k]) + 'deg)';
        el.style.opacity = vis ? '1' : '0';
        el.style.zIndex = String(50 - ad);
        el.style.pointerEvents = vis ? 'auto' : 'none';
        el.tabIndex = vis ? 0 : -1;
        el.classList.toggle('is-active', active);
        el.classList.toggle('is-far', ad >= 2);
        el.setAttribute('data-cur', active ? (list[i].video ? 'play' : '') : 'view');
        el.setAttribute('aria-label', (active ? (list[i].video ? 'Play ' : '') : 'Show ') + list[i].title);
        el.querySelector('.cf-img').style.filter = 'blur(' + B[k] + 'px) brightness(' + BR[k] + ') saturate(' + (active ? 1.05 : 0.85) + ')';
      });
      ticks.forEach(function (t, i) {
        t.classList.toggle('tick-on', i === idx);
        if (i === idx) t.setAttribute('aria-current', 'true'); else t.removeAttribute('aria-current');
      });
      if (numEl) numEl.textContent = pad(idx + 1);
      if (totEl) totEl.textContent = '/ ' + pad(n);
    };
    var go = function (i) {
      var n = list.length;
      if (!n) return;
      idx = ((i % n) + n) % n;
      lastAdvance = Date.now();
      layout();
    };
    var next = function () { go(idx + 1); };
    var prev = function () { go(idx - 1); };
    var build = function () {
      list = filter === 'All' ? ALL.slice() : ALL.filter(function (p) { return p.cat === filter; });
      track.innerHTML = '';
      if (ticksEl) ticksEl.innerHTML = '';
      idx = Math.min(idx, Math.max(0, list.length - 1));
      cards = list.map(function (p, i) {
        var b = doc.createElement('button');
        b.type = 'button';
        b.className = 'cf';
        b.style.transform = 'translate(-50%,-50%) scale(.9)';
        b.style.opacity = '0';
        b.innerHTML = cardHTML(p, i, list.length);
        b.addEventListener('click', function () {
          if (dragged) return;
          if (i !== idx) { go(i); return; }
          if (p.video) openVideo(p.video, p.title);
        });
        b.querySelector('.cf-bar').addEventListener('animationend', function (e) {
          if (e.animationName === 'prog' && i === idx && cfg.autoplay && !reduced) next();
        });
        track.appendChild(b);
        return b;
      });
      ticks = list.map(function (p, i) {
        var t = doc.createElement('button');
        t.type = 'button';
        t.className = 'tick';
        t.setAttribute('aria-label', 'Go to ' + p.title);
        t.addEventListener('click', function () { go(i); });
        if (ticksEl) ticksEl.appendChild(t);
        return t;
      });
      raf(function () { raf(layout); });
      lastAdvance = Date.now();
    };
    build();
    var pb = $('#prevBtn');
    var nb = $('#nextBtn');
    if (pb) pb.addEventListener('click', prev);
    if (nb) nb.addEventListener('click', next);
    $$('#cfPills [data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        filter = btn.getAttribute('data-filter');
        idx = 0;
        $$('#cfPills [data-filter]').forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('pill-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        build();
      });
    });
    stage.addEventListener('mouseenter', function () { paused = true; stage.classList.add('paused'); });
    stage.addEventListener('mouseleave', function () { paused = false; dragX = null; lastAdvance = Date.now(); stage.classList.remove('paused'); });
    stage.addEventListener('pointerdown', function (e) { dragX = e.clientX; dragged = false; });
    stage.addEventListener('pointerup', function (e) {
      if (dragX === null) return;
      var dx = e.clientX - dragX;
      dragX = null;
      if (Math.abs(dx) > 45) {
        dragged = true;
        if (dx < 0) next(); else prev();
        setTimeout(function () { dragged = false; }, 60);
      }
    });
    stage.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); next(); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); prev(); }
    });
    setInterval(function () {
      if (cfg.autoplay && !paused && !reduced && !doc.hidden && Date.now() - lastAdvance > SLIDE_MS + 3000) next();
    }, 1000);
  }

  /* ================================================================
     Portfolio filters
     ================================================================ */
  var grid = $('#pfGrid');
  if (grid) {
    var countEl = $('#pfCount');
    var emptyEl = $('#pfEmpty');
    $$('#pfPills [data-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var f = btn.getAttribute('data-filter');
        var shown = 0;
        $$('#pfPills [data-filter]').forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('pill-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        $$('.pcard', grid).forEach(function (card) {
          var match = f === 'All' || card.getAttribute('data-cat') === f;
          card.classList.toggle('is-hidden', !match);
          card.classList.remove('pop');
          if (match) {
            shown += 1;
            card.classList.add('in'); // no need to wait for the scroll reveal any more
            void card.offsetWidth;
            card.style.animationDelay = (Math.min(shown, 8) * 0.06) + 's';
            card.classList.add('pop');
          }
        });
        if (countEl) countEl.textContent = shown + (shown === 1 ? ' project' : ' projects');
        if (emptyEl) emptyEl.classList.toggle('show', shown === 0);
        remeasure();
      });
    });
  }

  /* ================================================================
     Hologram power toggle
     ================================================================ */
  var holo = $('#holoStage');
  var holoBtn = $('#holoBtn');
  var holoState = $('#holoState');
  if (holo && holoBtn) {
    var holoOn = true;
    var holoCount = 0;
    holoBtn.addEventListener('click', function () {
      holoOn = !holoOn;
      holo.classList.toggle('is-off', !holoOn);
      if (holoOn) {
        holoCount += 1;
        holo.classList.remove('pa', 'pb');
        holo.classList.add(holoCount % 2 ? 'pa' : 'pb');
      }
      holoBtn.setAttribute('aria-pressed', holoOn ? 'true' : 'false');
      if (holoState) holoState.textContent = holoOn ? 'On' : 'Off';
    });
  }

  /* ================================================================
     Contact form: checks, sending without a reload, success panel
     ================================================================ */
  var form = $('#contactForm');
  if (form) {
    var fBody = $('.form-body', form);
    var fDone = $('#formDone');
    var fDoneText = $('#formDoneText');
    var statusEl = $('#formStatus');
    var msgBox = $('#f-message');
    var msgCount = $('#msgCount');
    var updCount = function () { if (msgBox && msgCount) msgCount.textContent = String(msgBox.value.length); };
    if (msgBox) msgBox.addEventListener('input', updCount);
    updCount();
    var selects = $$('select', form);
    var paintSelects = function () { selects.forEach(function (s) { s.classList.toggle('is-empty', !s.value); }); };
    selects.forEach(function (s) { s.addEventListener('change', paintSelects); });
    paintSelects();
    var clearError = function (fld) {
      if (!fld) return;
      fld.classList.remove('bad');
      var er = $('.ferr', fld);
      if (er) er.parentNode.removeChild(er);
    };
    form.addEventListener('input', function (e) { var f = e.target.closest && e.target.closest('.fld'); if (f && f.classList.contains('bad')) clearError(f); });
    var showErrors = function (errs) {
      var first = null;
      Object.keys(errs).forEach(function (k) {
        if (k === 'form') return;
        var input = form.querySelector('[name="' + k + '"]');
        var fld = input && input.closest('.fld');
        if (!fld) return;
        clearError(fld);
        fld.classList.add('bad');
        var s = doc.createElement('span');
        s.className = 'ferr';
        s.textContent = errs[k];
        fld.appendChild(s);
        if (!first) first = input;
      });
      if (errs.form) statusEl.innerHTML = '<div class="f-status f-err">' + esc(errs.form) + '</div>';
      if (first) first.focus();
    };
    var showDone = function (text) {
      if (text && fDoneText) fDoneText.textContent = text;
      fBody.hidden = true;
      fDone.hidden = false;
      form.classList.add('is-sent');
      // restart the tick animation
      var ico = $('.fd-ico', fDone);
      if (ico) { var copy = ico.cloneNode(true); ico.parentNode.replaceChild(copy, ico); }
      var top = form.getBoundingClientRect().top;
      if (top < 80 || top > w.innerHeight * 0.6) w.scrollTo({ top: w.scrollY + top - 110, behavior: reduced ? 'auto' : 'smooth' });
      fDone.focus({ preventScroll: true });
      remeasure();
    };
    var showForm = function () {
      fDone.hidden = true;
      fBody.hidden = false;
      form.classList.remove('is-sent');
      statusEl.innerHTML = '';
      var n = $('#f-name');
      if (n) n.focus();
      remeasure();
    };
    var again = $('#formAgain');
    if (again) again.addEventListener('click', function (e) { e.preventDefault(); showForm(); });

    if (w.fetch && w.FormData) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('.f-send', form);
        $$('.fld.bad', form).forEach(clearError);
        statusEl.innerHTML = '';
        var errs = {};
        var nameIn = $('#f-name');
        var mailIn = $('#f-email');
        if (nameIn && !nameIn.value.trim()) errs.name = 'Please add your name.';
        if (mailIn && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(mailIn.value.trim())) errs.email = 'Please add a valid email address.';
        if (msgBox && msgBox.value.trim().length < 10) errs.message = 'Please write a little more about your project.';
        if (Object.keys(errs).length) { showErrors(errs); return; }
        btn.disabled = true;
        btn.classList.add('is-busy');
        // static site: the form is delivered by FormSubmit (no PHP server needed)
        fetch((form.getAttribute('action') || '').replace('formsubmit.co/', 'formsubmit.co/ajax/'), {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (res) {
          btn.disabled = false;
          btn.classList.remove('is-busy');
          if (res.ok || res.success === true || res.success === 'true') {
            form.reset();
            updCount();
            paintSelects();
            showDone(res.message);
            return;
          }
          if (res.errors) { showErrors(res.errors); return; }
          statusEl.innerHTML = '<div class="f-status f-err">' + esc(res.message || 'Sorry, something went wrong. Please try again.') + '</div>';
        }).catch(function () {
          btn.disabled = false;
          btn.classList.remove('is-busy');
          statusEl.innerHTML = '<div class="f-status f-err">Sorry, the message could not be sent. Please email <a href="mailto:sjesun92@gmail.com">sjesun92@gmail.com</a> instead.</div>';
        });
      });
    }
  }

  /* ================================================================
     Cursor, hero parallax, card spotlight, magnetic buttons
     ================================================================ */
  if (fine && !reduced) {
    var cursorEl = $('[data-cursor-el]');
    var ring = $('[data-cur-ring]');
    var dot = $('[data-cur-dot]');
    var hero = $('[data-hero]');
    var st = { x: -200, y: -200, rx: -200, ry: -200, mx: 0, my: 0, tx: 0, ty: 0, vis: false };
    w.addEventListener('mousemove', function (e) {
      st.x = e.clientX;
      st.y = e.clientY;
      st.tx = (e.clientX / w.innerWidth - 0.5) * 2;
      st.ty = (e.clientY / w.innerHeight - 0.5) * 2;
      if (!st.vis) {
        st.vis = true;
        st.rx = st.x;
        st.ry = st.y;
        if (cursorEl) cursorEl.setAttribute('data-on', '1');
      }
      var spot = e.target && e.target.closest ? e.target.closest('[data-spot]') : null;
      if (spot) {
        var r = spot.getBoundingClientRect();
        spot.style.setProperty('--sx', (e.clientX - r.left) + 'px');
        spot.style.setProperty('--sy', (e.clientY - r.top) + 'px');
      }
    }, { passive: true });
    doc.addEventListener('mouseover', function (e) {
      if (!cursorEl || !e.target || !e.target.closest) return;
      var tagged = e.target.closest('[data-cur]');
      var link = e.target.closest('a,button');
      var s = '';
      if (tagged && tagged.getAttribute('data-cur')) s = tagged.getAttribute('data-cur');
      else if (link) s = 'link';
      cursorEl.setAttribute('data-state', s);
    }, { passive: true });
    doc.documentElement.addEventListener('mouseleave', function () {
      st.vis = false;
      if (cursorEl) cursorEl.setAttribute('data-on', '0');
    });
    (function loop() {
      st.rx += (st.x - st.rx) * 0.18;
      st.ry += (st.y - st.ry) * 0.18;
      st.mx += (st.tx - st.mx) * 0.06;
      st.my += (st.ty - st.my) * 0.06;
      if (ring) ring.style.transform = 'translate3d(' + st.rx.toFixed(1) + 'px,' + st.ry.toFixed(1) + 'px,0)';
      if (dot) dot.style.transform = 'translate3d(' + st.x + 'px,' + st.y + 'px,0)';
      if (hero && w.scrollY < 1100) {
        hero.style.setProperty('--mx', st.mx.toFixed(3));
        hero.style.setProperty('--my', st.my.toFixed(3));
      }
      raf(loop);
    })();

    /* buttons and icons lean towards the pointer a little */
    var mags = [];
    var magBusy = false;
    var magLoop = function () {
      magBusy = false;
      var moving = false;
      mags.forEach(function (m) {
        var s = m.s;
        s.x += (s.tx - s.x) * 0.2;
        s.y += (s.ty - s.y) * 0.2;
        if (Math.abs(s.tx - s.x) > 0.05 || Math.abs(s.ty - s.y) > 0.05) moving = true;
        else { s.x = s.tx; s.y = s.ty; }
        m.el.style.translate = s.x.toFixed(2) + 'px ' + s.y.toFixed(2) + 'px';
      });
      if (moving) { magBusy = true; raf(magLoop); }
    };
    var kick = function () { if (!magBusy) { magBusy = true; raf(magLoop); } };
    $$('[data-magnet]').forEach(function (el) {
      var s = { x: 0, y: 0, tx: 0, ty: 0 };
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        s.tx = Math.max(-10, Math.min(10, (e.clientX - (r.left + r.width / 2)) * 0.22));
        s.ty = Math.max(-8, Math.min(8, (e.clientY - (r.top + r.height / 2)) * 0.32));
        kick();
      });
      el.addEventListener('pointerleave', function () { s.tx = 0; s.ty = 0; kick(); });
      mags.push({ el: el, s: s });
    });
  }
})();
