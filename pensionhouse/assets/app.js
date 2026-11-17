(function () {
  var root = document.documentElement;
  root.classList.add('js');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var lenis = null;
  function scrollToEl(el) {
    if (lenis) lenis.scrollTo(el, { offset: -72 });
    else el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
  }

  /* =====================================================
     Interactive UI (always runs, even without animations)
     ===================================================== */

  // ----- Nav: shadow on scroll + back-to-top button -----
  var nav = $('#nav'), toTop = $('#toTop');
  function onScroll() {
    var y = window.pageYOffset || root.scrollTop;
    if (nav) nav.classList.toggle('scrolled', y > 10);
    if (toTop) toTop.classList.toggle('show', y > 800);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', function () {
    if (lenis) lenis.scrollTo(0); else window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  });

  // ----- Mobile menu -----
  var burger = $('#burger');
  function setMenu(open) {
    if (!nav || !burger) return;
    nav.classList.toggle('menu-open', open);
    burger.setAttribute('aria-expanded', open);
    burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  if (burger) burger.addEventListener('click', function () { setMenu(!nav.classList.contains('menu-open')); });

  // ----- In-page anchors -----
  $$('a[href^="#"]').forEach(function (a) {
    var id = a.getAttribute('href');
    if (id.length < 2) return;
    a.addEventListener('click', function (e) {
      var el = $(id);
      if (!el) return;
      e.preventDefault();
      setMenu(false);
      scrollToEl(el);
    });
  });

  // ----- Active nav link while scrolling -----
  var linkMap = {};
  $$('.links a').forEach(function (a) { linkMap[a.getAttribute('href').slice(1)] = a; });
  if ('IntersectionObserver' in window) {
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        $$('.links a').forEach(function (a) { a.classList.remove('active'); });
        var a = linkMap[en.target.id];
        if (a) a.classList.add('active');
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(linkMap).forEach(function (id) { var s = document.getElementById(id); if (s) spy.observe(s); });
  }

  // ----- Reveal on scroll -----
  var reveals = $$('.reveal');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { threshold: 0.15 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  // ----- Staff login dropdown -----
  var loginBtn = $('#loginBtn'), card = $('#loginCard');
  if (loginBtn && card) {
    var submit = $('#loginSubmit'), pw = $('#lPass'), pwToggle = $('#pwToggle');
    var setOpen = function (open) {
      card.classList.toggle('open', open);
      loginBtn.setAttribute('aria-expanded', open);
      if (open) { setMenu(false); var f = card.querySelector('input:not([type=hidden])'); if (f) setTimeout(function () { f.focus(); }, 60); }
    };
    loginBtn.addEventListener('click', function (e) { e.preventDefault(); setOpen(!card.classList.contains('open')); });
    document.addEventListener('click', function (e) {
      if (!card.contains(e.target) && !loginBtn.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); setMenu(false); } });

    if (pwToggle && pw) pwToggle.addEventListener('click', function () {
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      pwToggle.textContent = show ? 'Hide' : 'Show';
      pwToggle.setAttribute('aria-pressed', show);
      pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      pw.focus();
    });

    card.addEventListener('submit', function () {
      submit.disabled = true;
      submit.textContent = 'Signing in\u2026';
    });
    window.addEventListener('pageshow', function () {   // re-enable after back button (bfcache)
      submit.disabled = false;
      submit.textContent = 'Log in';
    });
  }

  // ----- Stay estimator -----
  var estRoom = $('#estRoom'), estNights = $('#estNights');
  if (estRoom && estNights) {
    var money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
    var totalBox = $('.est-total');
    var clampNights = function () {
      var n = parseInt(estNights.value, 10);
      if (isNaN(n) || n < 1) n = 1;
      if (n > 30) n = 30;
      return n;
    };
    var update = function () {
      var n = clampNights();
      var opt = estRoom.options[estRoom.selectedIndex];
      var name = opt.text.split(' \u2014 ')[0];
      $('#estLabel').textContent = name + ' \u00d7 ' + n + (n === 1 ? ' night' : ' nights');
      $('#estTotal').textContent = money.format(parseInt(estRoom.value, 10) * n);
      if (totalBox && !reduce) { totalBox.classList.remove('bump'); void totalBox.offsetWidth; totalBox.classList.add('bump'); }
    };
    estRoom.addEventListener('change', update);
    estNights.addEventListener('input', update);
    estNights.addEventListener('blur', function () { estNights.value = clampNights(); update(); });
    $('#nMinus').addEventListener('click', function () { estNights.value = Math.max(1, clampNights() - 1); update(); });
    $('#nPlus').addEventListener('click', function () { estNights.value = Math.min(30, clampNights() + 1); update(); });
    $('#estForm').addEventListener('submit', function (e) { e.preventDefault(); });

    // "Estimate stay" buttons on the room cards
    $$('[data-room]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-room');
        for (var i = 0; i < estRoom.options.length; i++) {
          if (estRoom.options[i].getAttribute('data-key') === key) { estRoom.selectedIndex = i; break; }
        }
        update();
        scrollToEl($('#estimate'));
      });
    });
  }

  /* =====================================================
     Scroll animations (skipped if GSAP is missing or the
     visitor prefers reduced motion; page stays fully usable)
     ===================================================== */
  if (!window.gsap || !window.ScrollTrigger || reduce) return;

  root.classList.add('anim');
  gsap.registerPlugin(ScrollTrigger);

  // Smooth scrolling
  if (window.Lenis) {
    lenis = new Lenis({ lerp: 0.1 });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
    gsap.ticker.lagSmoothing(0);
  }

  // Scroll progress bar
  gsap.to('.progress', { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.2 } });

  // Hero entrance
  gsap.from('.hero .l span', { yPercent: 110, duration: 1, ease: 'power4.out', stagger: 0.12, delay: 0.1 });
  gsap.from('.hero .eyebrow, .hero p, .hero-cta, .stats', {
    y: 24, opacity: 0, duration: 0.8, ease: 'power3.out', stagger: 0.12, delay: 0.5
  });
  gsap.utils.toArray('.blob').forEach(function (b) {
    var speed = parseFloat(b.getAttribute('data-speed')) || 0.2;
    gsap.to(b, {
      y: function () { return -window.innerHeight * speed; }, ease: 'none',
      scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true }
    });
  });

  // Statement: words light up as you scroll
  var wordsEl = $('.words');
  if (wordsEl) {
    var list = wordsEl.textContent.trim().split(/\s+/);
    wordsEl.textContent = '';
    list.forEach(function (w) {
      var s = document.createElement('span');
      s.className = 'w';
      s.textContent = w;
      wordsEl.appendChild(s);
      wordsEl.appendChild(document.createTextNode(' '));
    });
    gsap.fromTo('.words .w', { opacity: 0.15 }, {
      opacity: 1, ease: 'none', duration: 0.5, stagger: 0.5,
      scrollTrigger: { trigger: '.statement', start: 'top 70%', end: 'bottom 60%', scrub: true }
    });
  }

  // Scene: day to night
  var scene = $('#scene');
  if (scene) {
    var caps = scene.querySelectorAll('.caps h2');
    var tl = gsap.timeline({
      defaults: { ease: 'none' },
      scrollTrigger: { trigger: scene, start: 'top top', end: '+=300%', pin: true, scrub: 1 }
    });
    tl.to('#sky', { fill: '#F3A35C', duration: 1 }, 0)
      .to('#sun', { y: 150, duration: 1 }, 0)
      .to('.cloud', { x: function (i) { return i ? -90 : 110; }, duration: 3 }, 0)
      .to('#sky', { fill: '#0E1B3D', duration: 1 }, 1)
      .to('#sun', { y: 340, duration: 1 }, 1)
      .to('#moon', { y: 0, duration: 1.2 }, 1)
      .to('.cloud', { opacity: 0.15, duration: 1 }, 1)
      .to('#stars', { opacity: 1, duration: 0.8 }, 1.2)
      .to('.win', { fill: '#FFD98A', duration: 0.8 }, 1.2)
      .to('.led', { opacity: 1, duration: 0.8 }, 1.2)
      .to(caps[0], { opacity: 0, duration: 0.25 }, 0.7)
      .to(caps[1], { opacity: 1, duration: 0.25 }, 0.9)
      .to(caps[1], { opacity: 0, duration: 0.25 }, 1.7)
      .to(caps[2], { opacity: 1, duration: 0.25 }, 2);
  }

  // Rooms: horizontal scroll on desktop only (phones swipe natively)
  var track = $('.rooms .track');
  if (track) {
    var mm = gsap.matchMedia();
    mm.add('(min-width: 901px)', function () {
      var dist = function () { return Math.max(0, track.scrollWidth - window.innerWidth); };
      gsap.to(track, {
        x: function () { return -dist(); }, ease: 'none',
        scrollTrigger: {
          trigger: '.rooms', start: 'top top',
          end: function () { return '+=' + dist(); },
          pin: true, scrub: 1, invalidateOnRefresh: true
        }
      });
    });
  }

  // Recalculate pin positions once fonts and images have loaded
  window.addEventListener('load', function () { ScrollTrigger.refresh(); });
})();