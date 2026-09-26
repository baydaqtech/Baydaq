// The chess game from the approved prototype, on top of the chess.js npm package.
// chess.js v1 renamed the predicates (in_checkmate -> isCheckmate, ...), so those calls are updated.
import { Chess } from 'chess.js';

export function initChessGame() {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var LOADER_MS = document.documentElement.classList.contains('loading') ? 1400 : 0;
  /* ---------- Playable board: you are White, Baydaq plays Black ---------- */
  var boardEl = document.getElementById('board');
  var statusEl = document.getElementById('status');
  var movesEl = document.getElementById('moves');
  var FILES = 'abcdefgh';
  var NAMES = { p: 'pawn', n: 'knight', b: 'bishop', r: 'rook', q: 'queen', k: 'king' };
  var ARABIC = { p: 'بيدق', n: 'حصان', b: 'فيل', r: 'قلعة', q: 'وزير', k: 'ملك' };

  var cells = {};
  for (var rk = 8; rk >= 1; rk--) {
    for (var fi = 0; fi < 8; fi++) {
      var id = FILES[fi] + rk;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sq8' + ((fi + rk) % 2 === 1 ? ' d' : '');
      btn.setAttribute('data-sq', id);
      btn.setAttribute('aria-label', id);
      boardEl.appendChild(btn);
      cells[id] = btn;
    }
  }

  function coordsHtml(sq) {
    var h = '';
    if (sq[0] === 'a') h += '<span class="coord r">' + sq[1] + '</span>';
    if (sq[1] === '1') h += '<span class="coord f">' + sq[0] + '</span>';
    return h;
  }
  function pieceHtml(p, sq) {
    // --i staggers the opening drop-in as a diagonal wave from the far corner.
    var i = (8 - +sq[1]) + FILES.indexOf(sq[0]);
    return '<svg class="pc ' + p.color + '" style="--i:' + i + '" viewBox="0 0 45 45" aria-hidden="true"><use href="#p-' + NAMES[p.type] + '"/></svg>';
  }
  function setStatus(text, side, thinking) {
    statusEl.className = 'status' + (thinking ? ' think' : '');
    statusEl.innerHTML = '<i class="' + (side || 'w') + '"></i><span></span>';
    statusEl.lastChild.textContent = text;
  }


  var game = new Chess();
  var frameEl = boardEl.parentNode;
  var selected = null, targets = [], lastMove = null, busy = false, note = '';

  function render(anim) {
    var b = game.board();
    var checkSq = null;
    if (game.isCheck()) {
      var turn = game.turn();
      for (var r = 0; r < 8; r++) for (var c = 0; c < 8; c++) {
        var q = b[r][c];
        if (q && q.type === 'k' && q.color === turn) checkSq = FILES[c] + (8 - r);
      }
    }
    var mine = !busy && !game.isGameOver() && game.turn() === 'w';
    Object.keys(cells).forEach(function (sq) {
      var p = b[8 - +sq[1]][FILES.indexOf(sq[0])];
      var el = cells[sq];
      el.innerHTML = coordsHtml(sq) + (p ? pieceHtml(p, sq) : '');
      var t = targets.filter(function (m) { return m.to === sq; })[0];
      el.classList.toggle('last', !!lastMove && (lastMove.from === sq || lastMove.to === sq));
      el.classList.toggle('sel', selected === sq);
      el.classList.toggle('hint', !!t);
      el.classList.toggle('cap', !!t && !!p);
      el.classList.toggle('check', checkSq === sq);
      el.classList.toggle('can', mine && (!!t || (!!p && p.color === 'w')));
      el.setAttribute('aria-label', sq + (p ? '، ' + (p.color === 'w' ? 'أبيض ' : 'أسود ') + ARABIC[p.type] : ''));
    });
    if (anim && !reduce) {
      var piece = cells[anim.to].querySelector('.pc');
      if (piece) {
        var s = boardEl.clientWidth / 8;
        var dx = (FILES.indexOf(anim.from[0]) - FILES.indexOf(anim.to[0])) * s;
        var dy = (+anim.to[1] - +anim.from[1]) * s;
        piece.style.transition = 'none';
        piece.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
        piece.classList.add('moving');
        if (anim.flags && anim.flags.indexOf('p') !== -1) piece.classList.add('crown');
        void piece.getBoundingClientRect();
        piece.style.transition = '';
        piece.style.transform = '';
      }
    }
  }

  function renderLog() {
    // The log can be switched off in Hero.jsx.
    if (!movesEl) return;
    var h = game.history();
    var html = '';
    for (var i = 0; i < h.length; i += 2) {
      html += '<li><span class="n">' + (i / 2 + 1) + '.</span>' + h[i] + (h[i + 1] ? ' ' + h[i + 1] : '') + '</li>';
    }
    movesEl.innerHTML = html;
    movesEl.scrollTop = movesEl.scrollHeight;
  }

  function updateStatus() {
    if (game.isCheckmate()) {
      if (game.turn() === 'b') { frameEl.classList.add('won'); setStatus('كش مات! فزت بالمباراة. هل نبني مشروعك بنفس الدقّة؟', 'w'); }
      else setStatus('كش مات. فاز بيدق هذه المرة، جرّب لعبة جديدة.', 'b');
    } else if (game.isDraw() || game.isStalemate() || game.isThreefoldRepetition()) {
      setStatus('تعادل. لا غالب ولا مغلوب.', 'w');
    } else if (busy) {
      setStatus('بيدق يفكّر في حركته…', 'b', true);
    } else if (game.isCheck()) {
      setStatus('كش! احمِ ملكك.', 'w');
    } else {
      setStatus(note || (game.history().length ? 'دورك.' : 'جرّب! أنت تلعب بالأبيض: انقر قطعة ثم الخانة التي تريدها.'), 'w');
    }
    note = '';
  }

  // Easy opponent: looks one move ahead only (it never sees your reply, so it leaves
  // pieces hanging), adds plenty of noise, and sometimes plays a random move.
  var BLUNDER_RATE = 0.2;
  var NOISE = 150;
  var VAL = { p: 100, n: 320, b: 330, r: 500, q: 900, k: 0 };
  function evaluate() {
    var b = game.board(), s = 0;
    for (var r = 0; r < 8; r++) for (var c = 0; c < 8; c++) {
      var p = b[r][c];
      if (!p) continue;
      var v = VAL[p.type];
      var centre = 3.5 - Math.max(Math.abs(3.5 - r), Math.abs(3.5 - c));
      if (p.type === 'n' || p.type === 'b') v += centre * 8;
      if (p.type === 'p') v += (p.color === 'w' ? 6 - r : r - 1) * 6 + centre * 4;
      s += p.color === 'w' ? v : -v;
    }
    return s;
  }
  function pickMove() {
    var moves = game.moves();
    if (!moves.length) return null;
    if (Math.random() < BLUNDER_RATE) return moves[Math.floor(Math.random() * moves.length)];
    var best = null, bestScore = Infinity;
    moves.forEach(function (m) {
      game.move(m);
      var score = game.isCheckmate() ? -100000 : evaluate();
      game.undo();
      score += Math.random() * NOISE;
      if (score < bestScore) { bestScore = score; best = m; }
    });
    return best;
  }

  function play(move) {
    // chess.js v1 throws on an illegal move instead of returning null.
    var m = null;
    try { m = game.move(move); } catch (e) { return null; }
    if (!m) return null;
    lastMove = m;
    selected = null;
    targets = [];
    render(m);
    renderLog();
    return m;
  }

  function aiTurn() {
    var m = pickMove();
    busy = false;
    if (m) play(m); else render();
    updateStatus();
  }

  boardEl.addEventListener('click', function (e) {
    var el = e.target.closest('.sq8');
    if (!el || busy || game.isGameOver() || game.turn() !== 'w') return;
    var sq = el.getAttribute('data-sq');
    var hit = targets.filter(function (m) { return m.to === sq; })[0];
    if (selected && hit) {
      var m = play({ from: selected, to: sq, promotion: 'q' });
      if (m && m.flags.indexOf('p') !== -1) note = 'ترقّى بيدقك إلى وزير! هكذا نعمل: خطوة بعد خطوة حتى القمة.';
      if (!game.isGameOver()) {
        busy = true;
        var n = note;
        updateStatus();
        note = n;
        setTimeout(aiTurn, 450);
      } else {
        updateStatus();
      }
      return;
    }
    var p = game.get(sq);
    if (p && p.color === 'w' && selected !== sq) {
      selected = sq;
      targets = game.moves({ square: sq, verbose: true });
    } else {
      selected = null;
      targets = [];
    }
    render();
  });

  document.getElementById('undo').addEventListener('click', function () {
    if (busy || !game.history().length) return;
    game.undo();
    if (game.turn() === 'b') game.undo();
    frameEl.classList.remove('won');
    var h = game.history({ verbose: true });
    lastMove = h.length ? h[h.length - 1] : null;
    selected = null; targets = [];
    render(); renderLog(); updateStatus();
  });
  document.getElementById('reset').addEventListener('click', function () {
    if (busy) return;
    game.reset();
    frameEl.classList.remove('won');
    lastMove = null; selected = null; targets = [];
    render(); renderLog(); updateStatus();
  });

  if (!reduce) {
    boardEl.classList.add('intro');
    setTimeout(function () { boardEl.classList.remove('intro'); }, 2200 + LOADER_MS);
  }
  render();
  renderLog();
  updateStatus();
}
