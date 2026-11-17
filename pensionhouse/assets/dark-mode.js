/*! Pension House dark mode. One file: theme + styles + toggle button.
    Load it in <head> (no defer) so there is no white flash.
    data-scope="site"  -> public site (index.php)
    data-scope="admin" -> admin/staff panel (layout.php) */
(function () {
  var KEY = 'ph-theme';
  var root = document.documentElement;
  var scope = (document.currentScript && document.currentScript.getAttribute('data-scope')) || 'site';

  /* ---------- 1. Apply the saved theme immediately ---------- */
  function initial() {
    try {
      var t = localStorage.getItem(KEY);
      if (t === 'dark' || t === 'light') return t;
    } catch (e) {}
    return (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
  }
  root.setAttribute('data-theme', initial());

  /* ---------- 2. Styles ---------- */
  var BTN_CSS = ''
    + '.theme-btn{display:grid;place-items:center;width:38px;height:38px;border:0;border-radius:50%;background:transparent;cursor:pointer;padding:0;flex-shrink:0;transition:background .15s}'
    + '.theme-btn:focus-visible{outline:3px solid #f2b63c;outline-offset:2px}'
    + '.theme-btn .i-sun{display:none}'
    + '[data-theme="dark"] .theme-btn .i-sun{display:block}'
    + '[data-theme="dark"] .theme-btn .i-moon{display:none}'
    + '.theme-btn.floating{position:fixed;right:16px;bottom:16px;z-index:300;width:44px;height:44px;border:1px solid rgba(128,128,128,.4);background:#fff;color:#123f3a;box-shadow:0 6px 18px rgba(0,0,0,.2)}'
    + '[data-theme="dark"] .theme-btn.floating{background:#152023;color:#7fd1c3}';

  /* ----- Public site (unchanged) ----- */
  var SITE_CSS = ''
    + '.theme-btn{color:var(--ink)}'
    + '.theme-btn:hover{background:rgba(31,42,36,.08)}'
    + '[data-theme="dark"] .theme-btn:hover{background:rgba(255,255,255,.1)}'
    + '[data-theme="dark"]{color-scheme:dark;--cream:#101714;--ink:#e8efe9;--muted:#9db0a4;--green:#6fbf94;--green-d:#5ab184;--green-l:#1d2f26;--line:rgba(232,239,233,.14);--surface:#17211c;--alt:#131c17}'
    + '[data-theme="dark"] .nav{background:rgba(16,23,20,.8)}'
    + '[data-theme="dark"] .nav.scrolled{background:rgba(16,23,20,.94);box-shadow:0 6px 24px rgba(0,0,0,.4)}'
    + '[data-theme="dark"] .links a:hover{background:rgba(255,255,255,.07)}'
    + '[data-theme="dark"] .eyebrow{background:rgba(111,191,148,.14)}'
    + '[data-theme="dark"] .blob{opacity:.22}'
    + '[data-theme="dark"] .scroll-cue{border-color:rgba(255,255,255,.4)}'
    + '[data-theme="dark"] .rooms,[data-theme="dark"] .why{background:var(--alt)}'
    + '[data-theme="dark"] .room,[data-theme="dark"] .est-card,[data-theme="dark"] .card{background:var(--surface)}'
    + '[data-theme="dark"] .pic{background:#1d2f26}'
    + '[data-theme="dark"] .est-card select,[data-theme="dark"] .stepper input{background:#101714;border-color:rgba(255,255,255,.18);color:var(--ink)}'
    + '[data-theme="dark"] .btn,[data-theme="dark"] .nav-cta,[data-theme="dark"] .lsubmit,[data-theme="dark"] .to-top,[data-theme="dark"] .btn.ghost:hover,[data-theme="dark"] .link-btn:hover,[data-theme="dark"] .stepper button:hover,[data-theme="dark"] details[open] summary::after{color:#0e1f17}'
    + '[data-theme="dark"] .btn.ghost{color:var(--green)}'
    + '[data-theme="dark"] .login-card{background:var(--surface);color:var(--ink);border-color:var(--line)}'
    + '[data-theme="dark"] .login-card::before{background:var(--surface);border-color:var(--line)}'
    + '[data-theme="dark"] .login-card .lsub{color:var(--muted)}'
    + '[data-theme="dark"] .login-card input{background:#101714;color:var(--ink);border-color:rgba(255,255,255,.18)}'
    + '[data-theme="dark"] .login-card .lerr{background:#3a1d1d;color:#fecaca;border-color:#7f2a2a}'
    + '[data-theme="dark"] .end{background:#1b3a2c;color:#f2eee0}'
    + '[data-theme="dark"] .end .btn{color:#1f2a24}'
    + '[data-theme="dark"] .end .btn.ghost{color:#f2eee0;box-shadow:inset 0 0 0 2px rgba(242,238,224,.5)}'
    + '[data-theme="dark"] .end .btn.ghost:hover{background:#f2eee0;color:#1b3a2c}'
    + '[data-theme="dark"] .foot-bar{background:#12251c}'
    + '@media(max-width:860px){[data-theme="dark"] .links{background:#101714}}';

  /* ----- Admin / staff panel (rebuilt) -----
     Every rule is prefixed with html[data-theme="dark"] so it always beats
     the page <style> blocks, no matter what order they load in. */
  var D = 'html[data-theme="dark"]';
  function R(selectors, body) {
    return selectors.split(',').map(function (s) { return D + ' ' + s.trim(); }).join(',') + '{' + body + '}';
  }

  var ADMIN_RULES = [
    /* --- Tokens --- */
    D + '{color-scheme:dark;background:#0b1214;'
      + '--g:#1f7a6e;--cream:#0b1214;--line:#24373b;--edge:#31494d;--ink:#e6efed;--soft:#9bb0ac;'
      + '--bg:#0b1214;--s1:#111b1e;--s2:#16242;--s3:#1d3034;--card:#111b1e;--bd:#24373b}',
    D + '{--s2:#162427}',

    /* --- Page --- */
    R('body', 'background:var(--bg);color:var(--ink)'),
    R('main', 'background:var(--bg)'),
    R('*', 'scrollbar-color:#31494d transparent'),
    R('::selection', 'background:rgba(63,181,163,.35);color:#fff'),
    R('.sub,.muted', 'color:var(--soft)'),
    R('.muted', 'color:#8aa19c'),

    /* --- Sidebar & mobile bar --- */
    R('aside', 'background:linear-gradient(180deg,#0e332f 0%,#081a1b 100%);border-right:1px solid var(--line)'),
    R('.mbar', 'background:#0e332f'),
    R('.side-user', 'background:rgba(0,0,0,.28)'),

    /* --- Surfaces (cards, panels, stat boxes, popovers) --- */
    R('.card,.panel,.stat,.kpi,.stat2,.gk,.gpn,.ak,.apn,.ou,.dk,.dp,.rv-box,.rv,.rcard,.pchip,.a-live,.bell,.bell-pop,dialog.modal,.d-tool .tabs,.theme-btn,.btn.ghost',
      'background:var(--s1);border-color:var(--line);color:var(--ink)'),
    R('.panel', 'border-color:var(--edge)'),
    R('.card,.panel,.stat,.kpi,.stat2,.gk,.gpn,.ak,.apn,.dk,.dp,.rv-box,.rv,.rcard',
      'box-shadow:0 1px 2px rgba(0,0,0,.35)'),
    R('.rcard:hover', 'box-shadow:0 10px 26px rgba(0,0,0,.5)'),
    R('.bell-pop,dialog.modal', 'box-shadow:0 24px 60px rgba(0,0,0,.6)'),
    R('dialog.modal::backdrop', 'background:rgba(0,0,0,.65)'),

    /* --- Tables --- */
    R('table', 'background:var(--s1);border-color:var(--line)'),
    R('th,.dp th', 'background:var(--s2);color:var(--soft)'),
    R('th,td', 'border-bottom-color:var(--line)'),
    R('table tr:hover td,.dp table tr:hover td', 'background:var(--s2)'),
    R('.empty-row:hover td', 'background:var(--s1)!important'),

    /* --- Forms --- */
    R('input:not([type="checkbox"]):not([type="radio"]),select,textarea,.rm-fields textarea,.search input',
      'background:#0d1719;border-color:var(--edge);color:var(--ink)'),
    R('input::placeholder,textarea::placeholder', 'color:#6d8580;opacity:1'),
    R('select option', 'background:#111b1e;color:#e6efed'),
    R('.rm-fields textarea:focus', 'border-color:#e8583f;box-shadow:none'),
    R('input:-webkit-autofill', '-webkit-box-shadow:0 0 0 100px #0d1719 inset;-webkit-text-fill-color:#e6efed'),

    /* --- Buttons & icon buttons --- */
    R('.bell,.theme-btn,.btn.ghost', 'color:#7fd1c3'),
    R('.bell:hover,.theme-btn:hover,.btn.ghost:hover', 'background:var(--s3)'),
    R('.bell-count', 'border-color:var(--s1)'),

    /* --- Tabs, segmented controls --- */
    R('.tabs a,.mtabs button', 'color:var(--ink)'),
    R('.tabs a:hover,.mtabs button:hover', 'background:var(--s3)'),
    R('.tabs a.on,.mtabs button.on', 'background:var(--g);color:#fff'),
    R('.tabs a em', 'background:var(--s3);color:var(--soft)'),
    R('.tabs a.on em', 'background:rgba(255,255,255,.22);color:#fff'),
    R('.mtabs,.seg', 'background:var(--s2)'),
    R('.seg button', 'color:var(--soft)'),
    R('.seg button.on', 'background:var(--g);color:#fff'),

    /* --- Settings / Rooms tab bars --- */
    R('.stabs,.rm-tabs,.setfoot,.a-foot,.rm-foot,.m-head,.m-actions,.bell-head', 'border-color:var(--line)'),
    R('.stabs button,.rm-tabs button', 'color:var(--soft)'),
    R('.stabs button:hover,.rm-tabs button:hover,.stabs button.on,.rm-tabs button.on', 'color:#7fd1c3'),
    R('.stabs em', 'background:var(--s3);color:var(--soft)'),
    R('.stabs em.live', 'background:#12301f;color:#86d9a0'),

    /* --- Modals, notifications, toasts --- */
    R('.m-head .x', 'background:var(--s3);color:var(--ink)'),
    R('.m-head .x:hover', 'background:#284045'),
    R('.kv dt', 'color:var(--soft)'),
    R('.bell-item', 'border-bottom-color:var(--line)'),
    R('.bell-item:hover', 'background:var(--s2)'),
    R('.bell-item.unread', 'background:rgba(238,96,72,.09)'),
    R('.bell-item small', 'color:#7f9691'),
    R('.toast', 'background:var(--s1);color:var(--ink);border-top-color:var(--line);border-right-color:var(--line);border-bottom-color:var(--line);box-shadow:0 10px 30px rgba(0,0,0,.5)'),
    R('.toast .tc', 'color:var(--soft)'),
    R('.toast .tc:hover', 'background:var(--s3)'),

    /* --- Status pills, chips, notices --- */
    R('.p-ok,.ok,.dd.good', 'background:#12301f;color:#86d9a0;border-color:#24593a'),
    R('.p-warn,.err,.dd.bad,.ac-failed', 'background:#3a1717;color:#f59a9a;border-color:#6b2a2a'),
    R('.p-info', 'background:#3a2a12;color:#f2b566;border-color:#6b4a1d'),
    R('.p-off,.dd.flat,.ac-settings,.ac-other', 'background:#232b2d;color:#a5b0b3;border-color:#3a4447'),
    R('.ok,.err', 'border:0'),
    R('.note', 'background:#2e2710;border-color:#4d4219;color:#e6d38a'),
    R('.d-alert', 'background:#2e2410;border-color:#5a4415;color:#f0d9a0'),
    R('.d-alert .ic', 'background:#4a3913'),
    R('.si.g,.rolechip,.rm-chip,.ac-staff', 'background:#16332f;color:#7fd1c3'),
    R('.si.coral,.ac-booking', 'background:#3a2019;color:#ff9a85'),
    R('.si.amber,.ac-room', 'background:#3a2f12;color:#f0c15a'),
    R('.si.blue,.ac-login', 'background:#17283d;color:#7db3ea'),
    R('.ac-guest', 'background:#2a1f3d;color:#c4a6ee'),
    R('.ac-payment', 'background:#12301f;color:#86d9a0'),
    R('.rnow', 'background:rgba(238,96,72,.09);border-color:#4a2c23'),
    R('.rrates span', 'background:var(--s3);color:var(--ink)'),

    /* --- Empty states, stars, progress bars --- */
    R('.empty .ei', 'background:var(--s3);color:#7fd1c3'),
    R('.stars i', 'color:#3a4f52'),
    R('.stars i.on', 'color:#f5b301'),
    R('.bar,.obar,.bd-row .trk', 'background:var(--s3)'),
    R('.bd-row:hover .trk', 'background:#26393d'),
    R('.pdot', 'border-color:var(--s1)'),

    /* --- Links & hovers --- */
    R('.panel h3 a,.dp-h a,.filter-note a', 'color:#ff7a62'),
    R('.ou:hover,.ulink:hover', 'background:var(--s3);border-color:var(--edge)'),
    R('.d-act:hover', 'background:var(--s2)'),
    R('.d-act b', 'background:var(--s3)'),
    R('.d-act b.hot', 'background:var(--coral);color:#fff'),
    R('.cin-sum b', 'color:#7fd1c3'),
    R('.idimg', 'border-color:var(--line)'),

    /* --- Reviews --- */
    R('.rv p', 'color:var(--soft)'),
    R('.rv-foot span', 'color:var(--soft)'),
    R('.trend line[stroke="#e8e8e8"]', 'stroke:#26373a'),
    R('.trend text[fill="#777"]', 'fill:#93a9a5'),
    R('.trend text[fill="#999"]', 'fill:#6d8580'),

    /* --- Rooms --- */
    R('.rm-sum,[style*="faf8ef"]', 'background:var(--s2);border-color:var(--line)'),
    R('.rm-sum h4', 'color:var(--ink)'),
    R('.rm-cover', 'background:var(--s3);color:var(--soft)'),
    R('.pm-help', 'color:var(--soft)'),
    R('.pm-msg', 'color:#f0b15c'),
    R('.pm-count', 'background:var(--s3);color:var(--soft)'),
    R('.pm-item', 'background:var(--s2);border-color:var(--line)'),
    R('.pm-grid.drag', 'background:rgba(238,96,72,.08)'),
    R('.pm-add', 'background:var(--s2);border-color:var(--edge);color:var(--soft)'),
    R('.pm-add:hover', 'background:var(--s3);border-color:#7fd1c3;color:#7fd1c3'),

    /* --- Charts (dashboard, guests, bookings, reviews) --- */
    R('.ax,.cin .ylab,.cin .xlab', 'fill:#93a9a5'),
    R('.cin .xlab.today', 'fill:#ff7a62'),
    R('.nodata', 'fill:#6d8580'),
    R('.cin .grid,.cbox line[stroke="#e6ecea"]', 'stroke:#26373a'),
    R('.cbox .hl', 'stroke:#4a6468'),
    R('.cin .line,.cin .guide', 'stroke:#4fb3a3'),
    R('.cin .dot', 'fill:var(--s1);stroke:#4fb3a3'),
    R('.cin .dot.today', 'stroke:#ff7a62'),
    R('.cbox circle[fill="#fff"]', 'fill:var(--s1)'),
    R('.cbox .hd2', 'fill:var(--s1)'),
    R('svg [stroke="#123f3a"]', 'stroke:#4fb3a3'),
    R('svg [fill="#123f3a"]', 'fill:#4fb3a3'),
    R('svg stop[stop-color="#123f3a"]', 'stop-color:#4fb3a3'),
    R('.legend i[style*="123f3a"]', 'background:#4fb3a3!important'),
    R('.donut .dn', 'fill:#e6efed'),
    R('.donut .dl2', 'fill:#93a9a5'),
    R('.donut circle[stroke="#eef1f0"]', 'stroke:#223437'),
    R('.tt,.cin .tip', 'background:#060d0e;border:1px solid var(--edge);color:#fff'),
    R('.chart .b', 'background:#2a3d40'),
    R('.chart .col:hover .b', 'background:#3f5a5e'),
    R('.chart .col.today .b', 'background:#3fb5a3')
  ];

  var ADMIN_CSS = ''
    + '.theme-btn{border:1px solid var(--line);background:#fff;color:var(--g);width:40px;height:40px}'
    + '.theme-btn:hover{background:#eef3f1}'
    + ADMIN_RULES.join('\n');

  var style = document.createElement('style');
  style.id = 'ph-dark-mode';
  style.textContent = BTN_CSS + (scope === 'admin' ? ADMIN_CSS : SITE_CSS);
  document.head.appendChild(style);

  /* ---------- 3. Toggle ---------- */
  function setTheme(t, save) {
    root.setAttribute('data-theme', t);
    var dark = t === 'dark';
    var btns = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < btns.length; i++) {
      btns[i].setAttribute('aria-pressed', dark ? 'true' : 'false');
      btns[i].setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      btns[i].setAttribute('title', dark ? 'Light mode' : 'Dark mode');
    }
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute('content', dark ? '#0b1214' : '#2f5d45');
    if (save) { try { localStorage.setItem(KEY, t); } catch (e) {} }
  }

  var ICONS = ''
    + '<svg class="i-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>'
    + '<svg class="i-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';

  function addButton() {
    if (document.querySelector('[data-theme-toggle]')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-btn';
    btn.setAttribute('data-theme-toggle', '');
    btn.innerHTML = ICONS;

    if (scope === 'admin') {
      var bell = document.querySelector('.top-right .bell-wrap');
      if (bell) bell.parentNode.insertBefore(btn, bell);
      else { btn.className += ' floating'; document.body.appendChild(btn); }
    } else {
      var actions = document.querySelector('.actions');
      if (actions) actions.insertBefore(btn, actions.firstChild);
      else { btn.className += ' floating'; document.body.appendChild(btn); }
    }
    setTheme(root.getAttribute('data-theme'), false);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addButton);
  else addButton();

  document.addEventListener('click', function (e) {
    if (!e.target.closest || !e.target.closest('[data-theme-toggle]')) return;
    setTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark', true);
  });

  // keep other open tabs in sync
  window.addEventListener('storage', function (e) {
    if (e.key === KEY && (e.newValue === 'dark' || e.newValue === 'light')) setTheme(e.newValue, false);
  });
})();