// Ported from the approved prototype (baydaq-2d.html): mini boards, floating pieces,
// scroll reveals, marquee, pointer light, loader hand-off and the pawn-to-vizier loop.
export function initPageMotion() {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  /* ---------- Mini boards: how each piece moves ---------- */
  var rules = {
    queen:  function (dr, dc) { return dr === 0 || dc === 0 || Math.abs(dr) === Math.abs(dc); },
    rook:   function (dr, dc) { return dr === 0 || dc === 0; },
    bishop: function (dr, dc) { return Math.abs(dr) === Math.abs(dc); },
    knight: function (dr, dc) { return Math.abs(dr * dc) === 2; }
  };
  document.querySelectorAll('.mini').forEach(function (el) {
    var piece = el.getAttribute('data-piece');
    var html = '';
    for (var r = 0; r < 5; r++) {
      for (var c = 0; c < 5; c++) {
        var dr = r - 2, dc = c - 2;
        var cls = (r + c) % 2 === 0 ? 'l' : 'd';
        if (dr === 0 && dc === 0) {
          html += '<span class="' + cls + '"><svg viewBox="0 0 45 45"><use href="#p-' + piece + '"/></svg></span>';
        } else if (rules[piece](dr, dc)) {
          var dist = Math.max(Math.abs(dr), Math.abs(dc));
          html += '<span class="' + cls + ' dot" style="--dl:' + (dist * 0.07) + 's"></span>';
        } else {
          html += '<span class="' + cls + '"></span>';
        }
      }
    }
    el.innerHTML = html;
  });

  /* ---------- Loading screen hand-off ---------- */
  var root = document.documentElement;
  var LOADER_MS = root.classList.contains('loading') ? 1400 : 0;
  setTimeout(function () { root.classList.remove('loading'); }, LOADER_MS);

  /* ---------- Floating pieces across the page ---------- */
  // Seeded random so the scatter is identical on every visit.
  var seed = 64;
  function rand() {
    seed = (seed + 0x6D2B79F5) | 0;
    var t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  }
  var PIECES = ['pawn', 'pawn', 'pawn', 'knight', 'bishop', 'rook', 'queen', 'king'];
  function addFloater(sec, o) {
    var el = document.createElement('span');
    el.className = 'floaty' + (o.outline ? ' o' : '');
    el.setAttribute('aria-hidden', 'true');
    el.setAttribute('data-speed', o.speed.toFixed(2));
    el.setAttribute('data-size', Math.round(o.size));
    el.setAttribute('data-frac', o.frac.toFixed(3));
    el.setAttribute('data-side', o.side);
    el.style.top = o.top.toFixed(1) + '%';
    el.innerHTML = '<svg viewBox="0 0 45 45"><use href="#p-' + o.piece + '"/></svg>';
    el.firstChild.style.animationDuration = (6 + rand() * 5).toFixed(1) + 's';
    el.firstChild.style.animationDelay = '-' + (rand() * 8).toFixed(1) + 's';
    sec.insertBefore(el, sec.firstChild);
  }
  ['.hero', '#services', '#process', '#about', '#contact'].forEach(function (sel) {
    var sec = document.querySelector(sel);
    // Skip missing sections and bare anchors (the 3D story keeps #process as a 1px anchor).
    if (!sec || sec.offsetHeight < 100) return;
    // Roughly one piece per 120px of section height, alternating sides, spread top to bottom.
    var count = Math.max(6, Math.round(sec.offsetHeight / 120));
    for (var i = 0; i < count; i++) {
      addFloater(sec, {
        piece: PIECES[Math.floor(rand() * PIECES.length)],
        outline: rand() < .45,
        size: 38 + rand() * 82,
        top: ((i + .15 + rand() * .7) / count) * 96,
        side: i % 2 ? 'end' : 'start',
        frac: rand(),
        speed: rand() * .34 - .14
      });
    }
  });

  // Board patches: a planned rhythm down the page. Every section has a pair, but each pair takes a
  // different shape (tall, diagonal, small, reversed diagonal…) and square size, so nothing repeats.
  // [side, top %, height %, anchor (where the patch is strongest), square size px]
  var PATCH_PLAN = {
    '.hero':     [['right', 4, 92, 'center', 48], ['left', 4, 92, 'center', 48]],
    '#services': [['right', 0, 58, 'top', 48],    ['left', 42, 58, 'bottom', 48]],
    '#process':  [['right', 22, 56, 'center', 64], ['left', 22, 56, 'center', 64]],
    '#about':    [['left', 0, 58, 'top', 48],     ['right', 42, 58, 'bottom', 48]],
    '#contact':  [['right', 8, 84, 'center', 36], ['left', 8, 84, 'center', 36]]
  };
  var ANCHOR_Y = { top: '0%', center: '50%', bottom: '100%' };
  var allPatches = [];
  Object.keys(PATCH_PLAN).forEach(function (sel) {
    var sec = document.querySelector(sel);
    if (!sec || sec.offsetHeight < 100) return;
    PATCH_PLAN[sel].forEach(function (d) {
      var p = document.createElement('span');
      p.className = 'bpatch ' + d[0];
      p.setAttribute('aria-hidden', 'true');
      p.style.top = d[1] + '%';
      p.style.height = d[2] + '%';
      p.setAttribute('data-cell', d[4]);
      p.style.setProperty('--tile', (d[4] * 2) + 'px');
      // Corner-anchored patches fade from their corner; centred ones from the middle of the edge.
      var ry = d[3] === 'center' ? '50%' : '80%';
      p.style.setProperty('--mask', 'radial-gradient(ellipse 100% ' + ry + ' at ' + (d[0] === 'right' ? '100%' : '0') + ' ' + ANCHOR_Y[d[3]] + ', #000 20%, transparent 72%)');
      sec.insertBefore(p, sec.firstChild);
      allPatches.push(p);
    });
  });

  // Keep every piece inside the empty side margins, never behind the content column.
  var CONTENT_MAX = 1200, allFloaters = Array.prototype.slice.call(document.querySelectorAll('.floaty'));
  function layoutFloaters() {
    var W = document.documentElement.clientWidth;
    var gutter = (W - Math.min(W, CONTENT_MAX)) / 2 + 20 - 14;
    var show = gutter >= 44;
    // Patches reach a little past the margin and fade to nothing just inside the content edge.
    var pw = Math.round(gutter + 140);
    allPatches.forEach(function (p) {
      p.hidden = !show;
      p.style.width = pw + 'px';
      // Anchor the squares to the page edge so right patches mirror left ones
      // (+1 cell so the square touching the right edge is light, like the left edge's first square).
      var cell = +p.getAttribute('data-cell');
      p.style.setProperty('--ox', (p.classList.contains('right') ? (pw + cell) % (cell * 2) : 0) + 'px');
    });
    allFloaters.forEach(function (el) {
      el.hidden = !show;
      if (!show) return;
      var size = Math.min(+el.getAttribute('data-size'), gutter - 8);
      var inset = +el.getAttribute('data-frac') * Math.max(0, gutter - size - 4);
      el.style.width = size + 'px';
      el.style[el.getAttribute('data-side') === 'start' ? 'insetInlineStart' : 'insetInlineEnd'] = inset.toFixed(0) + 'px';
    });
  }
  layoutFloaters();
  window.addEventListener('resize', layoutFloaters);

  // Temporary: switch the floating background between chess pieces and board squares.
  var bgPieces = document.getElementById('bg-pieces'), bgSquares = document.getElementById('bg-squares');
  function setBg(mode) {
    root.classList.toggle('bg-squares', mode === 'squares');
    bgPieces.setAttribute('aria-pressed', String(mode === 'pieces'));
    bgSquares.setAttribute('aria-pressed', String(mode === 'squares'));
    try { localStorage.setItem('baydaq-bg', mode); } catch (e) {}
  }
  bgPieces.addEventListener('click', function () { setBg('pieces'); });
  bgSquares.addEventListener('click', function () { setBg('squares'); });
  var savedBg = 'squares';
  try { savedBg = localStorage.getItem('baydaq-bg') || 'squares'; } catch (e) {}
  setBg(savedBg);

  /* ---------- Page motion ---------- */
  var nav = document.querySelector('.nav');
  function onScroll() { nav.classList.toggle('scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (!reduce) {
    // Warm light follows the pointer across the hero (mouse/trackpad only).
    var hero = document.querySelector('.hero');
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      var lightFrame = 0, lx = 0, ly = 0;
      hero.addEventListener('pointermove', function (e) {
        var b = hero.getBoundingClientRect();
        lx = e.clientX - b.left; ly = e.clientY - b.top;
        if (lightFrame) return;
        lightFrame = requestAnimationFrame(function () {
          lightFrame = 0;
          hero.style.setProperty('--mx', lx + 'px');
          hero.style.setProperty('--my', ly + 'px');
          hero.classList.add('lit');
        });
      });
      hero.addEventListener('pointerleave', function () { hero.classList.remove('lit'); });
    }

    // Floating pieces drift at different speeds as the page scrolls.
    var floaters = Array.prototype.slice.call(document.querySelectorAll('.floaty'));
    var parallaxFrame = 0;
    function parallax() {
      parallaxFrame = 0;
      var mid = window.innerHeight / 2;
      floaters.forEach(function (el) {
        var r = el.parentNode.getBoundingClientRect();
        var offset = (r.top + r.height / 2 - mid) * parseFloat(el.getAttribute('data-speed'));
        el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
      });
    }
    window.addEventListener('scroll', function () {
      if (!parallaxFrame) parallaxFrame = requestAnimationFrame(parallax);
    }, { passive: true });
    parallax();

    // Tech strip: wrap the list and duplicate it so the marquee loops seamlessly.
    var list = document.querySelector('.stack ul');
    if (list) {
      var box = document.createElement('div');
      box.className = 'marquee';
      list.parentNode.insertBefore(box, list);
      box.appendChild(list);
      Array.prototype.slice.call(list.children).forEach(function (li) {
        var copy = li.cloneNode(true);
        copy.setAttribute('aria-hidden', 'true');
        list.appendChild(copy);
      });
    }
  }

  if (!reduce && 'IntersectionObserver' in window) {
    var groups = [
      ['.sec-head', 'up', 0],
      ['.svc', 'scale', 0.1],
      ['.rank .sq', 'up', 0.12],
      ['.heritage-copy .eyebrow', 'up', 0],
      ['.heritage-copy .ruqaa', 'wipe', 0],
      ['.heritage-copy > p:last-child', 'up', 0],
      ['.promo', 'scale', 0],
      ['.checker-rule', 'wipe', 0],
      ['.principles li', 'up', 0.12],
      ['.contact', 'up', 0],
      ['.wordmark', 'up', 0]
    ];
    document.documentElement.classList.add('js-reveal');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.remove('pending');
        io.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    groups.forEach(function (g) {
      document.querySelectorAll(g[0]).forEach(function (el, i) {
        el.setAttribute('data-reveal', g[1]);
        if (g[2]) el.style.setProperty('--d', (i % 4) * g[2] + 's');
        if (g[0] === '.heritage-copy > p:last-child') el.style.setProperty('--d', '.35s');
        // Anything already on screen stays visible; only content below the fold waits.
        if (el.getBoundingClientRect().top < window.innerHeight * 0.9) return;
        el.classList.add('pending');
        io.observe(el);
      });
    });
  }

  /* ---------- Pawn → vizier loop in "about" (runs only while on screen) ---------- */
  var promo = document.querySelector('.promo');
  if (promo && !reduce && 'IntersectionObserver' in window) {
    var WALK_MS = 2900, HOLD_MS = 2500, FADE_MS = 400;
    var loopTimer = null, onScreen = false;
    function replay() {
      promo.classList.add('no-anim', 'walk-reset');
      promo.classList.remove('walk-fade');
      void promo.offsetWidth;
      promo.classList.remove('no-anim');
      requestAnimationFrame(function () {
        requestAnimationFrame(function () { promo.classList.remove('walk-reset'); });
      });
      schedule();
    }
    function schedule() {
      clearTimeout(loopTimer);
      loopTimer = setTimeout(function () {
        if (!onScreen) { loopTimer = null; return; }
        promo.classList.add('walk-fade');
        loopTimer = setTimeout(replay, FADE_MS);
      }, WALK_MS + HOLD_MS);
    }
    new IntersectionObserver(function (entries) {
      onScreen = entries[0].isIntersecting;
      if (onScreen && !loopTimer) schedule();
    }, { threshold: 0.3 }).observe(promo);
  }

}
