/* =============================================================
   MINDSTRONG UNIVERSE — GAME ENGINE v2.0
   Full engine: XP, levels, badges, streaks, leaderboard,
   speed math, number ninja, memory match, word scramble,
   equation builder, boss battle, daily challenge, puzzle.
   ============================================================= */

/* ── STORAGE ─────────────────────────────────────────────── */
const MS = {
  get profile() { return JSON.parse(localStorage.getItem('ms_profile') || '{}'); },
  set profile(p) { localStorage.setItem('ms_profile', JSON.stringify(p)); },
  get scores()  { return JSON.parse(localStorage.getItem('ms_scores')  || '{}'); },
  set scores(s) { localStorage.setItem('ms_scores',  JSON.stringify(s)); },
};

/* ── PROFILE / XP / LEVELS ──────────────────────────────── */
const XP_PER_LEVEL = 200;
const LEVEL_TITLES = [
  'Rookie','Scholar','Explorer','Challenger','Achiever',
  'Prodigy','Mastermind','Legend','Elite','Champion'
];

function msInitProfile() {
  const p = MS.profile;
  if (p._init) return p;
  p._init    = true;
  p.xp       = p.xp       ?? 0;
  p.level    = p.level    ?? 1;
  p.badges   = p.badges   ?? [];
  p.streak   = p.streak   ?? 1;
  p.coins    = p.coins    ?? 0;
  p.lastVisit = p.lastVisit ?? null;
  p.totalGames = p.totalGames ?? 0;
  p.highScores = p.highScores ?? {};
  MS.profile = p;
  return p;
}

function msAddXP(amount, label) {
  const p = msInitProfile();
  p.xp += amount;
  const newLevel = Math.floor(p.xp / XP_PER_LEVEL) + 1;
  const levelUp  = newLevel > p.level;
  p.level = newLevel;
  p.coins += Math.floor(amount / 10);
  MS.profile = p;
  msShowXPPopup(amount, label, levelUp ? newLevel : null);
  if (levelUp) msCheckLevelBadges(newLevel);
  return { xp: p.xp, level: p.level, levelUp };
}

function msGetLevelTitle(level) {
  return LEVEL_TITLES[Math.min(level - 1, LEVEL_TITLES.length - 1)];
}

function msXPForNextLevel(level) {
  return level * XP_PER_LEVEL;
}

/* ── STREAK ─────────────────────────────────────────────── */
function msUpdateStreak() {
  const p   = msInitProfile();
  const now = new Date().toDateString();
  if (p.lastVisit === now) return p.streak;

  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  p.streak = (p.lastVisit === yesterday.toDateString()) ? p.streak + 1 : 1;
  p.lastVisit = now;
  MS.profile = p;

  if (p.streak >= 7)  msUnlockBadge('🔥 Week Warrior');
  if (p.streak >= 30) msUnlockBadge('🏆 Month Master');
  return p.streak;
}

/* ── BADGES ─────────────────────────────────────────────── */
const ALL_BADGES = {
  // Game badges
  'speed_math_10':   { icon:'⚡', name:'Speed Demon',      desc:'Score 10 in Speed Math'         },
  'speed_math_25':   { icon:'🚀', name:'Lightning Fast',   desc:'Score 25 in Speed Math'         },
  'ninja_50':        { icon:'🥷', name:'Number Ninja',     desc:'Score 50 in Number Ninja'       },
  'memory_perfect':  { icon:'🧠', name:'Perfect Memory',   desc:'Complete Memory Match perfectly'},
  'word_5':          { icon:'📝', name:'Word Wizard',      desc:'Solve 5 Word Scrambles'         },
  'boss_first':      { icon:'👊', name:'Boss Slayer',      desc:'Defeat your first boss'         },
  'daily_7':         { icon:'📅', name:'Daily Devotee',    desc:'Complete 7 daily challenges'    },
  'puzzle_master':   { icon:'🧩', name:'Puzzle Master',    desc:'Solve 10 puzzles'               },
  // XP badges
  'xp_500':          { icon:'⭐', name:'Rising Star',      desc:'Earn 500 XP'                    },
  'xp_1000':         { icon:'🌟', name:'Star Player',      desc:'Earn 1000 XP'                   },
  'xp_5000':         { icon:'💫', name:'Galaxy Brain',     desc:'Earn 5000 XP'                   },
  // Streak badges
  '🔥 Week Warrior': { icon:'🔥', name:'Week Warrior',     desc:'7-day streak'                   },
  '🏆 Month Master': { icon:'🏆', name:'Month Master',     desc:'30-day streak'                  },
  // Level badges
  'level_5':         { icon:'🎯', name:'Level Up!',        desc:'Reach Level 5'                  },
  'level_10':        { icon:'👑', name:'Elite Learner',    desc:'Reach Level 10'                 },
  // Games played
  'games_10':        { icon:'🎮', name:'Gamer',            desc:'Play 10 games'                  },
  'games_50':        { icon:'🕹️', name:'Hardcore Gamer',  desc:'Play 50 games'                  },
};

function msUnlockBadge(key) {
  const p = msInitProfile();
  if (p.badges.includes(key)) return false;
  p.badges.push(key);
  MS.profile = p;
  const badge = ALL_BADGES[key];
  if (badge) msShowBadgePopup(badge);
  return true;
}

function msCheckXPBadges(xp) {
  if (xp >= 500)  msUnlockBadge('xp_500');
  if (xp >= 1000) msUnlockBadge('xp_1000');
  if (xp >= 5000) msUnlockBadge('xp_5000');
}

function msCheckLevelBadges(level) {
  if (level >= 5)  msUnlockBadge('level_5');
  if (level >= 10) msUnlockBadge('level_10');
}

/* ── HIGH SCORES ─────────────────────────────────────────── */
function msSaveHighScore(gameId, score) {
  const p = msInitProfile();
  if (!p.highScores) p.highScores = {};
  if ((p.highScores[gameId] ?? -1) < score) {
    p.highScores[gameId] = score;
    MS.profile = p;
    return true; // new high score
  }
  return false;
}

function msGetHighScore(gameId) {
  return msInitProfile().highScores?.[gameId] ?? 0;
}

function msRecordGamePlayed() {
  const p = msInitProfile();
  p.totalGames = (p.totalGames ?? 0) + 1;
  MS.profile = p;
  if (p.totalGames >= 10) msUnlockBadge('games_10');
  if (p.totalGames >= 50) msUnlockBadge('games_50');
}

/* ── POPUP NOTIFICATIONS ─────────────────────────────────── */
function msShowXPPopup(amount, label, newLevel) {
  const el = document.createElement('div');
  el.className = 'ms-xp-popup';
  el.innerHTML = `
    <span class="ms-xp-amount">+${amount} XP</span>
    ${label ? `<span class="ms-xp-label">${label}</span>` : ''}
    ${newLevel ? `<span class="ms-level-up">LEVEL UP! → ${msGetLevelTitle(newLevel)}</span>` : ''}
  `;
  document.body.appendChild(el);
  setTimeout(() => el.classList.add('ms-xp-popup--show'), 10);
  setTimeout(() => { el.classList.remove('ms-xp-popup--show'); setTimeout(() => el.remove(), 400); }, 2200);
}

function msShowBadgePopup(badge) {
  const el = document.createElement('div');
  el.className = 'ms-badge-popup';
  el.innerHTML = `
    <div class="ms-badge-icon">${badge.icon}</div>
    <div class="ms-badge-info">
      <div class="ms-badge-title">Badge Unlocked!</div>
      <div class="ms-badge-name">${badge.name}</div>
      <div class="ms-badge-desc">${badge.desc}</div>
    </div>
  `;
  document.body.appendChild(el);
  setTimeout(() => el.classList.add('ms-badge-popup--show'), 10);
  setTimeout(() => { el.classList.remove('ms-badge-popup--show'); setTimeout(() => el.remove(), 400); }, 3500);
}

/* ── HUD RENDERER ────────────────────────────────────────── */
function msRenderHUD(containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;
  const p    = msInitProfile();
  const xpInLevel  = p.xp % XP_PER_LEVEL;
  const xpNeeded   = XP_PER_LEVEL;
  const pct        = Math.min(100, Math.round((xpInLevel / xpNeeded) * 100));
  el.innerHTML = `
    <div class="ms-hud">
      <div class="ms-hud-cell">
        <span class="ms-hud-val">${p.level}</span>
        <span class="ms-hud-key">Level</span>
      </div>
      <div class="ms-hud-cell ms-hud-xp">
        <div class="ms-hud-bar-wrap">
          <div class="ms-hud-bar" style="width:${pct}%"></div>
        </div>
        <span class="ms-hud-key">${p.xp} XP • ${msGetLevelTitle(p.level)}</span>
      </div>
      <div class="ms-hud-cell">
        <span class="ms-hud-val">${p.streak}🔥</span>
        <span class="ms-hud-key">Streak</span>
      </div>
      <div class="ms-hud-cell">
        <span class="ms-hud-val">${p.coins}🪙</span>
        <span class="ms-hud-key">Coins</span>
      </div>
      <div class="ms-hud-cell">
        <span class="ms-hud-val">${p.badges.length}</span>
        <span class="ms-hud-key">Badges</span>
      </div>
    </div>
  `;
}

/* ══════════════════════════════════════════════════════════
   GAME 1 — SPEED MATH
   Fast-fire arithmetic. Answer before time runs out.
   ══════════════════════════════════════════════════════════ */
class SpeedMathGame {
  constructor(containerId, opts = {}) {
    this.el        = document.getElementById(containerId);
    this.timeLimit = opts.timeLimit ?? 30;
    this.ops       = opts.ops       ?? ['+', '-', '×', '÷'];
    this.score     = 0;
    this.combo     = 0;
    this.maxCombo  = 0;
    this.timer     = null;
    this.timeLeft  = this.timeLimit;
    this.current   = null;
    this.running   = false;
  }

  start() {
    this.score = 0; this.combo = 0; this.timeLeft = this.timeLimit; this.running = true;
    msRecordGamePlayed();
    this._render();
    this._nextQuestion();
    this.timer = setInterval(() => {
      this.timeLeft--;
      this._updateTimer();
      if (this.timeLeft <= 0) this._end();
    }, 1000);
  }

  _genQuestion() {
    const op = this.ops[Math.floor(Math.random() * this.ops.length)];
    let a, b, answer;
    switch (op) {
      case '+': a = msRandInt(1,50); b = msRandInt(1,50); answer = a + b; break;
      case '-': a = msRandInt(10,99); b = msRandInt(1,a); answer = a - b; break;
      case '×': a = msRandInt(2,12); b = msRandInt(2,12); answer = a * b; break;
      case '÷': b = msRandInt(2,12); answer = msRandInt(2,12); a = b * answer; break;
    }
    const choices = msShuffleChoices(answer, 4, 1, 144);
    return { question: `${a} ${op} ${b} = ?`, answer, choices };
  }

  _nextQuestion() {
    this.current = this._genQuestion();
    this._renderQuestion();
  }

  _answer(val) {
    if (!this.running) return;
    if (val === this.current.answer) {
      this.combo++;
      this.maxCombo = Math.max(this.maxCombo, this.combo);
      const bonus = this.combo >= 3 ? Math.min(this.combo - 2, 5) : 0;
      this.score++;
      msAddXP(10 + bonus * 2, bonus > 0 ? `${this.combo}× Combo!` : null);
      this._flash('correct');
    } else {
      this.combo = 0;
      this._flash('wrong');
    }
    this._updateScore();
    this._nextQuestion();
  }

  _end() {
    clearInterval(this.timer); this.running = false;
    const isHigh = msSaveHighScore('speed_math', this.score);
    msAddXP(this.score * 5, 'Speed Math finish');
    if (this.score >= 10) msUnlockBadge('speed_math_10');
    if (this.score >= 25) msUnlockBadge('speed_math_25');
    this._renderResult(isHigh);
  }

  _flash(cls) {
    if (!this.el) return;
    this.el.classList.add(`ms-flash-${cls}`);
    setTimeout(() => this.el.classList.remove(`ms-flash-${cls}`), 300);
  }

  _render() {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span class="ms-game-score">Score: <b id="sm-score">0</b></span>
        <span class="ms-game-combo" id="sm-combo"></span>
        <span class="ms-game-timer" id="sm-timer">${this.timeLeft}s</span>
      </div>
      <div class="ms-question-box" id="sm-question"></div>
      <div class="ms-choices" id="sm-choices"></div>
    `;
  }

  _renderQuestion() {
    const q = document.getElementById('sm-question');
    const c = document.getElementById('sm-choices');
    if (q) q.textContent = this.current.question;
    if (c) {
      c.innerHTML = this.current.choices.map(ch =>
        `<button class="ms-choice-btn" onclick="window._smGame._answer(${ch})">${ch}</button>`
      ).join('');
    }
    window._smGame = this;
  }

  _updateTimer() {
    const el = document.getElementById('sm-timer');
    if (el) { el.textContent = `${this.timeLeft}s`; if (this.timeLeft <= 5) el.classList.add('ms-urgent'); }
  }

  _updateScore() {
    const s = document.getElementById('sm-score');
    const c = document.getElementById('sm-combo');
    if (s) s.textContent = this.score;
    if (c) c.textContent = this.combo >= 3 ? `🔥 ${this.combo}× Combo` : '';
  }

  _renderResult(isHigh) {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-result">
        <div class="ms-result-title">Time's Up!</div>
        <div class="ms-result-score">${this.score}</div>
        <div class="ms-result-label">correct answers</div>
        ${isHigh ? '<div class="ms-result-high">NEW HIGH SCORE!</div>' : ''}
        <div class="ms-result-meta">Best Combo: ${this.maxCombo}× | Best Ever: ${msGetHighScore('speed_math')}</div>
        <button class="ms-btn primary" onclick="window._smGame.start()">Play Again</button>
      </div>
    `;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 2 — NUMBER NINJA
   Swipe/click numbers in ascending order against the clock.
   ══════════════════════════════════════════════════════════ */
class NumberNinjaGame {
  constructor(containerId, opts = {}) {
    this.el       = document.getElementById(containerId);
    this.count    = opts.count ?? 9;
    this.score    = 0;
    this.round    = 0;
    this.running  = false;
    this.numbers  = [];
    this.nextTarget = 1;
    this.timer    = null;
    this.timeLeft = opts.timeLimit ?? 60;
  }

  start() {
    this.score = 0; this.round = 0; this.running = true; this.timeLeft = 60;
    msRecordGamePlayed();
    this._newRound();
    this.timer = setInterval(() => {
      this.timeLeft--;
      const el = document.getElementById('nn-timer');
      if (el) { el.textContent = `${this.timeLeft}s`; if (this.timeLeft <= 5) el.classList.add('ms-urgent'); }
      if (this.timeLeft <= 0) this._end();
    }, 1000);
  }

  _newRound() {
    this.round++;
    this.nextTarget = 1;
    const nums = msShuffleArray([...Array(this.count)].map((_, i) => i + 1));
    this.numbers = nums;
    this._renderGrid();
  }

  _tap(n) {
    if (!this.running) return;
    if (n === this.nextTarget) {
      document.getElementById(`nn-${n}`)?.classList.add('ms-ninja-hit');
      this.nextTarget++;
      this.score++;
      msAddXP(5, null);
      if (this.score >= 50) msUnlockBadge('ninja_50');
      if (this.nextTarget > this.count) {
        setTimeout(() => this._newRound(), 400);
      }
    } else {
      document.getElementById(`nn-${n}`)?.classList.add('ms-ninja-miss');
      setTimeout(() => document.getElementById(`nn-${n}`)?.classList.remove('ms-ninja-miss'), 300);
    }
  }

  _end() {
    clearInterval(this.timer); this.running = false;
    msSaveHighScore('number_ninja', this.score);
    msAddXP(this.score * 3, 'Number Ninja finish');
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-result">
        <div class="ms-result-title">Ninja Complete!</div>
        <div class="ms-result-score">${this.score}</div>
        <div class="ms-result-label">numbers tapped</div>
        <div class="ms-result-meta">Best Ever: ${msGetHighScore('number_ninja')}</div>
        <button class="ms-btn primary" onclick="window._nnGame.start()">Play Again</button>
      </div>
    `;
    window._nnGame = this;
  }

  _renderGrid() {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span class="ms-game-score">Score: <b>${this.score}</b> | Round: ${this.round}</span>
        <span class="ms-game-timer" id="nn-timer">${this.timeLeft}s</span>
      </div>
      <p class="ms-game-hint">Tap numbers 1 → ${this.count} in order!</p>
      <div class="ms-ninja-grid">
        ${this.numbers.map(n =>
          `<button id="nn-${n}" class="ms-ninja-btn" onclick="window._nnGame._tap(${n})">${n}</button>`
        ).join('')}
      </div>
    `;
    window._nnGame = this;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 3 — MEMORY MATCH
   Flip cards to find matching pairs.
   ══════════════════════════════════════════════════════════ */
class MemoryMatchGame {
  constructor(containerId, opts = {}) {
    this.el      = document.getElementById(containerId);
    this.pairs   = opts.pairs ?? 8;
    this.cards   = [];
    this.flipped = [];
    this.matched = 0;
    this.moves   = 0;
    this.locked  = false;
    this.startMs = 0;
    this.subject = opts.subject ?? 'math'; // 'math' | 'emoji' | 'words'
  }

  start() {
    this.matched = 0; this.moves = 0; this.flipped = []; this.locked = false;
    this.startMs = Date.now();
    msRecordGamePlayed();
    const symbols = this._getSymbols();
    this.cards = msShuffleArray([...symbols, ...symbols]).map((sym, i) => ({
      id: i, sym, revealed: false, matched: false
    }));
    this._render();
  }

  _getSymbols() {
    const math  = ['2²','π','√','∞','≠','±','Σ','∫','∆','θ','λ','φ','∂','≤','≥','∈'];
    const emoji = ['🦁','🐯','🦊','🐻','🦋','🌺','⚡','🎯','🏆','🌙','🔥','💎','🎪','🚀','🌊','🎭'];
    const words = ['sum','diff','prod','quot','var','func','loop','bool','null','int','str','arr','obj','key','val','set'];
    const pool = this.subject === 'emoji' ? emoji : this.subject === 'words' ? words : math;
    return msShuffleArray(pool).slice(0, this.pairs);
  }

  _flip(id) {
    if (this.locked) return;
    const card = this.cards[id];
    if (card.revealed || card.matched) return;
    card.revealed = true;
    this.flipped.push(id);
    this._renderCard(id);

    if (this.flipped.length === 2) {
      this.moves++;
      this.locked = true;
      const [a, b] = this.flipped;
      if (this.cards[a].sym === this.cards[b].sym) {
        this.cards[a].matched = this.cards[b].matched = true;
        this.matched++;
        this.flipped = [];
        this.locked  = false;
        msAddXP(20, 'Match!');
        this._renderCard(a); this._renderCard(b);
        if (this.matched === this.pairs) this._end();
      } else {
        setTimeout(() => {
          this.cards[a].revealed = this.cards[b].revealed = false;
          this.flipped = [];
          this.locked  = false;
          this._renderCard(a); this._renderCard(b);
        }, 900);
      }
    }
    this._updateStats();
  }

  _end() {
    const secs = Math.round((Date.now() - this.startMs) / 1000);
    const perfect = this.moves === this.pairs;
    if (perfect) msUnlockBadge('memory_perfect');
    msSaveHighScore('memory_match', this.pairs * 100 - this.moves * 5);
    msAddXP(this.pairs * 15, 'Memory Match finish');
    const statsEl = document.getElementById('mm-stats');
    if (statsEl) statsEl.innerHTML = `
      <div class="ms-result-inline">
        ✅ Finished in <b>${secs}s</b> with <b>${this.moves}</b> moves.
        ${perfect ? '<span class="ms-result-high">PERFECT!</span>' : ''}
        <button class="ms-btn primary" onclick="window._mmGame.start()">Play Again</button>
      </div>
    `;
  }

  _render() {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span id="mm-stats">Moves: <b>0</b> | Matched: <b>0</b>/${this.pairs}</span>
      </div>
      <div class="ms-memory-grid" id="mm-grid" style="--cols:${Math.ceil(Math.sqrt(this.pairs * 2))}">
        ${this.cards.map(c => `
          <div class="ms-mem-card" id="mc-${c.id}" onclick="window._mmGame._flip(${c.id})">
            <div class="ms-mem-inner">
              <div class="ms-mem-front">?</div>
              <div class="ms-mem-back">${c.sym}</div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
    window._mmGame = this;
  }

  _renderCard(id) {
    const el = document.getElementById(`mc-${id}`);
    if (!el) return;
    const c = this.cards[id];
    el.classList.toggle('is-flipped',  c.revealed || c.matched);
    el.classList.toggle('is-matched',  c.matched);
  }

  _updateStats() {
    const el = document.getElementById('mm-stats');
    if (el) el.innerHTML = `Moves: <b>${this.moves}</b> | Matched: <b>${this.matched}</b>/${this.pairs}`;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 4 — WORD SCRAMBLE
   Unscramble math/science vocabulary.
   ══════════════════════════════════════════════════════════ */
class WordScrambleGame {
  constructor(containerId, opts = {}) {
    this.el       = document.getElementById(containerId);
    this.subject  = opts.subject ?? 'math';
    this.score    = 0;
    this.solved   = 0;
    this.skipped  = 0;
    this.current  = null;
    this.WORDS    = this._wordBank();
  }

  _wordBank() {
    const banks = {
      math:    ['equation','integer','fraction','decimal','geometry','algebra','theorem','polynomial','exponent','variable','coefficient','quadratic','perimeter','diameter','parallel','tangent','quotient','numerator','denominator','absolute'],
      science: ['molecule','element','gravity','velocity','proton','neutron','electron','nucleus','photon','catalyst','isotope','compound','reaction','pressure','density','friction','momentum','wavelength','frequency','amplitude'],
      english: ['metaphor','synonym','antonym','simile','narrative','dialogue','paragraph','punctuation','grammar','adjective','adverb','pronoun','conjunction','preposition','rhyme','syllable','alliteration','onomatopoeia','personification','hyperbole'],
    };
    return banks[this.subject] ?? banks.math;
  }

  start() {
    this.score = 0; this.solved = 0; this.skipped = 0;
    msRecordGamePlayed();
    this._nextWord();
  }

  _nextWord() {
    const word = this.WORDS[Math.floor(Math.random() * this.WORDS.length)].toUpperCase();
    const scrambled = msShuffleArray(word.split('')).join('');
    this.current = { word, scrambled };
    this._render();
  }

  _check() {
    const inp = document.getElementById('ws-input');
    if (!inp) return;
    if (inp.value.toUpperCase().trim() === this.current.word) {
      this.score += 100;
      this.solved++;
      msAddXP(25, 'Word Solved!');
      if (this.solved >= 5) msUnlockBadge('word_5');
      inp.classList.add('ms-input-correct');
      setTimeout(() => this._nextWord(), 600);
    } else {
      inp.classList.add('ms-input-wrong');
      setTimeout(() => { inp.classList.remove('ms-input-wrong'); inp.value = ''; }, 400);
    }
  }

  _skip() {
    this.skipped++;
    this._nextWord();
  }

  _render() {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span class="ms-game-score">Score: <b>${this.score}</b> | Solved: ${this.solved}</span>
      </div>
      <div class="ms-scramble-word">${this.current.scrambled}</div>
      <p class="ms-game-hint">Unscramble the ${this.subject} word!</p>
      <div class="ms-scramble-form">
        <input id="ws-input" class="ms-input" type="text" placeholder="Your answer..." autocomplete="off"
          onkeydown="if(event.key==='Enter') window._wsGame._check()">
        <button class="ms-btn primary" onclick="window._wsGame._check()">Check</button>
        <button class="ms-btn" onclick="window._wsGame._skip()">Skip</button>
      </div>
    `;
    document.getElementById('ws-input')?.focus();
    window._wsGame = this;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 5 — EQUATION BUILDER
   Drag numbers/operators into the blank to make it true.
   ══════════════════════════════════════════════════════════ */
class EquationBuilderGame {
  constructor(containerId, opts = {}) {
    this.el      = document.getElementById(containerId);
    this.score   = 0;
    this.round   = 0;
    this.current = null;
  }

  start() {
    this.score = 0; this.round = 0;
    msRecordGamePlayed();
    this._nextRound();
  }

  _nextRound() {
    this.round++;
    // Generate: a __ b = c  with one missing piece from [a, op, b, c]
    const op  = ['+','-','×','÷'][Math.floor(Math.random() * 4)];
    let a, b, c;
    switch (op) {
      case '+': a = msRandInt(1,50); b = msRandInt(1,50); c = a + b; break;
      case '-': a = msRandInt(10,99); b = msRandInt(1,a); c = a - b; break;
      case '×': a = msRandInt(2,12); b = msRandInt(2,12); c = a * b; break;
      case '÷': b = msRandInt(2,12); c = msRandInt(2,12); a = b * c; break;
    }
    const missing = ['a','b','c'][Math.floor(Math.random() * 3)];
    const answer  = missing === 'a' ? a : missing === 'b' ? b : c;
    const distractors = msShuffleChoices(answer, 4, 0, 144);
    this.current = { a, b, c, op, missing, answer, choices: distractors };
    this._render();
  }

  _pick(val) {
    if (val === this.current.answer) {
      this.score++;
      msAddXP(30, 'Equation solved!');
      this._flash('correct');
      setTimeout(() => this._nextRound(), 500);
    } else {
      this._flash('wrong');
    }
  }

  _flash(cls) {
    this.el?.classList.add(`ms-flash-${cls}`);
    setTimeout(() => this.el?.classList.remove(`ms-flash-${cls}`), 300);
  }

  _render() {
    if (!this.el) return;
    const {a, b, c, op, missing} = this.current;
    const display = {
      a: `<span class="ms-blank">?</span> ${op} ${b} = ${c}`,
      b: `${a} ${op} <span class="ms-blank">?</span> = ${c}`,
      c: `${a} ${op} ${b} = <span class="ms-blank">?</span>`,
    }[missing];
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span class="ms-game-score">Score: <b>${this.score}</b> | Round: ${this.round}</span>
      </div>
      <div class="ms-equation">${display}</div>
      <p class="ms-game-hint">Pick the missing number:</p>
      <div class="ms-choices">
        ${this.current.choices.map(ch =>
          `<button class="ms-choice-btn" onclick="window._ebGame._pick(${ch})">${ch}</button>`
        ).join('')}
      </div>
    `;
    window._ebGame = this;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 6 — BOSS BATTLE
   Defeat the boss by answering a streak of hard questions.
   Each wrong answer deals damage to you. Win = boss dies.
   ══════════════════════════════════════════════════════════ */
class BossBattleGame {
  constructor(containerId, opts = {}) {
    this.el         = document.getElementById(containerId);
    this.bossHP     = opts.bossHP  ?? 10;
    this.playerHP   = opts.playerHP ?? 5;
    this.bossCurrent = this.bossHP;
    this.playerCurrent = this.playerHP;
    this.current    = null;
    this.running    = false;
    this.bosses     = [
      { name:'Algebra Ogre', icon:'👹', difficulty:1 },
      { name:'Fraction Witch', icon:'🧙', difficulty:2 },
      { name:'Geometry Dragon', icon:'🐉', difficulty:3 },
    ];
    this.bossIndex  = opts.bossIndex ?? 0;
  }

  start() {
    this.bossCurrent = this.bossHP;
    this.playerCurrent = this.playerHP;
    this.running = true;
    msRecordGamePlayed();
    this._nextQuestion();
  }

  _genQuestion() {
    const diff  = this.bosses[this.bossIndex].difficulty;
    const max   = diff * 25;
    const ops   = diff >= 2 ? ['+','-','×','÷'] : ['+','-'];
    const op    = ops[Math.floor(Math.random() * ops.length)];
    let a, b, answer;
    switch (op) {
      case '+': a = msRandInt(max/2,max); b = msRandInt(max/2,max); answer = a+b; break;
      case '-': a = msRandInt(max/2,max); b = msRandInt(1,a); answer = a-b; break;
      case '×': a = msRandInt(2,diff*6); b = msRandInt(2,diff*6); answer = a*b; break;
      case '÷': b = msRandInt(2,diff*6); answer = msRandInt(2,diff*6); a = b*answer; break;
    }
    return { question:`${a} ${op} ${b}`, answer, choices: msShuffleChoices(answer,4,0,max*2) };
  }

  _nextQuestion() {
    if (!this.running) return;
    this.current = this._genQuestion();
    this._render();
  }

  _answer(val) {
    if (!this.running) return;
    if (val === this.current.answer) {
      this.bossCurrent--;
      msAddXP(15, 'Hit!');
      if (this.bossCurrent <= 0) { this._win(); return; }
    } else {
      this.playerCurrent--;
      if (this.playerCurrent <= 0) { this._lose(); return; }
    }
    this._nextQuestion();
  }

  _win() {
    this.running = false;
    msUnlockBadge('boss_first');
    msAddXP(100, 'Boss defeated!');
    if (!this.el) return;
    const boss = this.bosses[this.bossIndex];
    this.el.innerHTML = `
      <div class="ms-result ms-result-win">
        <div class="ms-boss-icon">${boss.icon}</div>
        <div class="ms-result-title">You defeated ${boss.name}!</div>
        <div class="ms-result-high">+100 XP • Badge Unlocked!</div>
        <button class="ms-btn primary" onclick="window._bossGame.start()">Fight Again</button>
      </div>
    `;
    window._bossGame = this;
  }

  _lose() {
    this.running = false;
    if (!this.el) return;
    const boss = this.bosses[this.bossIndex];
    this.el.innerHTML = `
      <div class="ms-result ms-result-lose">
        <div class="ms-boss-icon">${boss.icon}</div>
        <div class="ms-result-title">${boss.name} defeated you!</div>
        <div class="ms-result-meta">Train harder and try again.</div>
        <button class="ms-btn primary" onclick="window._bossGame.start()">Try Again</button>
      </div>
    `;
    window._bossGame = this;
  }

  _render() {
    if (!this.el) return;
    const boss = this.bosses[this.bossIndex];
    const bossBarPct   = Math.round((this.bossCurrent / this.bossHP) * 100);
    const playerBarPct = Math.round((this.playerCurrent / this.playerHP) * 100);
    this.el.innerHTML = `
      <div class="ms-battle">
        <div class="ms-battle-side ms-battle-boss">
          <div class="ms-boss-icon">${boss.icon}</div>
          <div class="ms-battle-name">${boss.name}</div>
          <div class="ms-hp-bar-wrap"><div class="ms-hp-bar ms-hp-boss" style="width:${bossBarPct}%"></div></div>
          <div class="ms-hp-label">${this.bossCurrent} / ${this.bossHP} HP</div>
        </div>
        <div class="ms-battle-side ms-battle-player">
          <div class="ms-boss-icon">🧑‍🎓</div>
          <div class="ms-battle-name">You</div>
          <div class="ms-hp-bar-wrap"><div class="ms-hp-bar ms-hp-player" style="width:${playerBarPct}%"></div></div>
          <div class="ms-hp-label">${this.playerCurrent} / ${this.playerHP} HP</div>
        </div>
      </div>
      <div class="ms-question-box">${this.current.question} = ?</div>
      <div class="ms-choices">
        ${this.current.choices.map(ch =>
          `<button class="ms-choice-btn" onclick="window._bossGame._answer(${ch})">${ch}</button>`
        ).join('')}
      </div>
    `;
    window._bossGame = this;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 7 — DAILY CHALLENGE
   One special challenge per day. Tracks completion streak.
   ══════════════════════════════════════════════════════════ */
class DailyChallengeGame {
  constructor(containerId) {
    this.el    = document.getElementById(containerId);
    this.today = new Date().toDateString();
  }

  isDone() {
    return localStorage.getItem('ms_daily_done') === this.today;
  }

  start() {
    if (this.isDone()) { this._renderDone(); return; }
    msRecordGamePlayed();
    // Seed today's questions deterministically
    const seed    = new Date().getDate() + new Date().getMonth() * 31;
    const types   = ['speed','equation','boss'];
    const type    = types[seed % types.length];
    this._runChallenge(type);
  }

  _complete(score) {
    localStorage.setItem('ms_daily_done', this.today);
    const p = msInitProfile();
    p.dailyCount = (p.dailyCount ?? 0) + 1;
    MS.profile = p;
    msAddXP(150, 'Daily Challenge!');
    if (p.dailyCount >= 7) msUnlockBadge('daily_7');
    this._renderDone(score);
  }

  _runChallenge(type) {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-daily-header">
        <span class="ms-daily-badge">📅 Daily Challenge</span>
        <span class="ms-daily-reward">+150 XP on completion</span>
      </div>
      <div id="dc-game"></div>
    `;
    if (type === 'speed') {
      const g = new SpeedMathGame('dc-game', { timeLimit: 45 });
      const origEnd = g._end.bind(g);
      g._end = () => { origEnd(); this._complete(g.score); };
      g.start();
    } else if (type === 'boss') {
      const g = new BossBattleGame('dc-game');
      const origWin = g._win.bind(g);
      g._win = () => { origWin(); this._complete(1); };
      g.start();
    } else {
      const g = new EquationBuilderGame('dc-game');
      g.start();
      // complete after 5 correct
      const origPick = g._pick.bind(g);
      g._pick = (val) => { origPick(val); if (g.score >= 5) this._complete(g.score); };
    }
  }

  _renderDone(score) {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-result">
        <div class="ms-result-title">📅 Daily Challenge Complete!</div>
        ${score != null ? `<div class="ms-result-score">${score}</div>` : ''}
        <div class="ms-result-high">+150 XP Earned • Come back tomorrow!</div>
      </div>
    `;
  }
}

/* ══════════════════════════════════════════════════════════
   GAME 8 — MATH PUZZLE (Fill-in grid)
   A 3×3 grid where rows/columns must equal given targets.
   ══════════════════════════════════════════════════════════ */
class MathPuzzleGame {
  constructor(containerId) {
    this.el     = document.getElementById(containerId);
    this.solved = parseInt(localStorage.getItem('ms_puzzles_solved') ?? '0');
    this.grid   = [];
    this.answer = [];
  }

  start() {
    msRecordGamePlayed();
    this._generate();
    this._render();
  }

  _generate() {
    // Fill a 3x3 grid with 1-9, remove 4 cells for user to fill
    const nums = msShuffleArray([1,2,3,4,5,6,7,8,9]);
    this.answer = [
      [nums[0], nums[1], nums[2]],
      [nums[3], nums[4], nums[5]],
      [nums[6], nums[7], nums[8]],
    ];
    this.rowSums = this.answer.map(r => r.reduce((a,b) => a+b,0));
    this.colSums = [0,1,2].map(c => this.answer.reduce((a,r) => a+r[c],0));
    // Blank 4 random cells
    const blanks = msShuffleArray([...Array(9)].map((_,i) => i)).slice(0,4);
    this.grid = this.answer.map((r,ri) => r.map((v,ci) => ({
      val: blanks.includes(ri*3+ci) ? '' : v,
      blank: blanks.includes(ri*3+ci),
    })));
  }

  _check() {
    let allFilled = true;
    let allCorrect = true;
    this.grid.forEach((row, ri) => row.forEach((cell, ci) => {
      if (cell.blank) {
        const inp = document.getElementById(`mp-${ri}-${ci}`);
        const val = parseInt(inp?.value ?? '');
        if (isNaN(val)) { allFilled = false; return; }
        cell.val = val;
        if (val !== this.answer[ri][ci]) allCorrect = false;
      }
    }));
    if (!allFilled) { this._shake(); return; }
    if (allCorrect) {
      this.solved++;
      localStorage.setItem('ms_puzzles_solved', this.solved);
      msAddXP(50, 'Puzzle solved!');
      if (this.solved >= 10) msUnlockBadge('puzzle_master');
      this._renderSuccess();
    } else {
      this._renderError();
    }
  }

  _shake() {
    this.el?.classList.add('ms-shake');
    setTimeout(() => this.el?.classList.remove('ms-shake'), 500);
  }

  _renderSuccess() {
    const el = document.getElementById('mp-status');
    if (el) el.innerHTML = `<span class="ms-correct-msg">✅ Correct! Puzzles solved: ${this.solved}</span>`;
    setTimeout(() => { this.start(); }, 1500);
  }

  _renderError() {
    const el = document.getElementById('mp-status');
    if (el) el.innerHTML = `<span class="ms-wrong-msg">❌ Not quite. Check your numbers.</span>`;
  }

  _render() {
    if (!this.el) return;
    this.el.innerHTML = `
      <div class="ms-game-hdr">
        <span>Math Puzzle #${this.solved + 1}</span>
        <span>Solved: ${this.solved}</span>
      </div>
      <p class="ms-game-hint">Fill blanks so each row and column hits its target sum.</p>
      <div class="ms-puzzle-wrap">
        <table class="ms-puzzle-grid">
          ${this.grid.map((row, ri) => `
            <tr>
              ${row.map((cell, ci) => `
                <td class="ms-puzzle-cell ${cell.blank ? 'ms-puzzle-blank' : 'ms-puzzle-given'}">
                  ${cell.blank
                    ? `<input id="mp-${ri}-${ci}" class="ms-puzzle-input" type="number" min="1" max="9" placeholder="?">`
                    : `<span>${cell.val}</span>`}
                </td>
              `).join('')}
              <td class="ms-puzzle-target">= ${this.rowSums[ri]}</td>
            </tr>
          `).join('')}
          <tr>
            ${this.colSums.map(s => `<td class="ms-puzzle-target">↑${s}</td>`).join('')}
            <td></td>
          </tr>
        </table>
      </div>
      <div id="mp-status" class="ms-puzzle-status"></div>
      <div class="ms-puzzle-actions">
        <button class="ms-btn primary" onclick="window._mpGame._check()">Check Answer</button>
        <button class="ms-btn" onclick="window._mpGame.start()">New Puzzle</button>
      </div>
    `;
    window._mpGame = this;
  }
}

/* ── POPUP + HUD CSS (injected once) ─────────────────────── */
(function injectStyles() {
  if (document.getElementById('ms-engine-styles')) return;
  const s = document.createElement('style');
  s.id = 'ms-engine-styles';
  s.textContent = `
    /* ── XP Popup ── */
    .ms-xp-popup {
      position:fixed; bottom:24px; right:24px; z-index:9999;
      background:linear-gradient(135deg,#7c3aed,#2563eb);
      color:#fff; padding:12px 20px; border-radius:14px;
      font-family:'JetBrains Mono',monospace; font-size:13px;
      box-shadow:0 8px 32px rgba(124,58,237,.4);
      display:flex; flex-direction:column; gap:2px;
      opacity:0; transform:translateY(12px);
      transition:all .3s ease; pointer-events:none;
    }
    .ms-xp-popup--show { opacity:1; transform:translateY(0); }
    .ms-xp-amount { font-size:18px; font-weight:700; color:#facc15; }
    .ms-level-up  { font-size:12px; color:#86efac; font-weight:700; letter-spacing:.05em; }

    /* ── Badge Popup ── */
    .ms-badge-popup {
      position:fixed; top:24px; right:24px; z-index:9999;
      background:linear-gradient(135deg,#0f172a,#1e1b4b);
      border:1px solid rgba(168,85,247,.4);
      padding:16px 20px; border-radius:16px;
      display:flex; align-items:center; gap:14px;
      box-shadow:0 8px 40px rgba(124,58,237,.5);
      opacity:0; transform:translateX(20px);
      transition:all .35s ease; pointer-events:none; max-width:320px;
    }
    .ms-badge-popup--show { opacity:1; transform:translateX(0); }
    .ms-badge-icon  { font-size:36px; }
    .ms-badge-title { font-size:10px; letter-spacing:.15em; text-transform:uppercase; color:#a78bfa; }
    .ms-badge-name  { font-size:15px; font-weight:700; color:#fff; }
    .ms-badge-desc  { font-size:12px; color:#94a3b8; }

    /* ── HUD ── */
    .ms-hud { display:flex; gap:12px; flex-wrap:wrap; align-items:center;
      background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
      border-radius:14px; padding:10px 16px; }
    .ms-hud-cell { display:flex; flex-direction:column; align-items:center; min-width:52px; }
    .ms-hud-val  { font-size:18px; font-weight:700; color:#facc15; font-family:'JetBrains Mono',monospace; }
    .ms-hud-key  { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.1em; }
    .ms-hud-xp   { flex:1; min-width:120px; }
    .ms-hud-bar-wrap { width:100%; height:6px; background:rgba(255,255,255,.1); border-radius:4px; overflow:hidden; }
    .ms-hud-bar  { height:100%; background:linear-gradient(90deg,#7c3aed,#2563eb); border-radius:4px; transition:width .4s ease; }

    /* ── Shared game UI ── */
    .ms-game-hdr  { display:flex; justify-content:space-between; align-items:center;
      margin-bottom:14px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,.08); }
    .ms-game-score { font-weight:600; color:#e2e8f0; }
    .ms-game-timer { font-family:'JetBrains Mono',monospace; font-size:20px; color:#facc15; font-weight:700; }
    .ms-game-timer.ms-urgent { color:#ef4444; animation:ms-blink .5s infinite; }
    .ms-game-hint  { color:#94a3b8; font-size:13px; margin:0 0 12px; }
    @keyframes ms-blink { 0%,100%{opacity:1} 50%{opacity:.4} }

    /* ── Question / Equation ── */
    .ms-question-box, .ms-equation {
      font-size:clamp(22px,5vw,36px); font-weight:700; color:#f1f5f9;
      text-align:center; padding:20px; margin:10px 0;
      background:rgba(255,255,255,.04); border-radius:16px;
      font-family:'JetBrains Mono',monospace;
    }
    .ms-blank { display:inline-block; width:60px; height:40px; line-height:40px;
      background:rgba(124,58,237,.3); border:2px dashed #7c3aed;
      border-radius:8px; color:#a78bfa; text-align:center; vertical-align:middle; }

    /* ── Choices ── */
    .ms-choices { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-top:12px; }
    .ms-choice-btn {
      padding:14px; border-radius:12px; border:1px solid rgba(255,255,255,.12);
      background:rgba(255,255,255,.06); color:#e2e8f0;
      font-size:18px; font-weight:600; cursor:pointer;
      transition:all .15s ease; font-family:'JetBrains Mono',monospace;
    }
    .ms-choice-btn:hover { background:rgba(124,58,237,.3); border-color:#7c3aed; transform:scale(1.03); }

    /* ── Flash feedback ── */
    .ms-flash-correct { animation:ms-flash-green .3s ease; }
    .ms-flash-wrong   { animation:ms-flash-red .3s ease; }
    @keyframes ms-flash-green { 0%,100%{background:transparent} 50%{background:rgba(34,197,94,.15)} }
    @keyframes ms-flash-red   { 0%,100%{background:transparent} 50%{background:rgba(239,68,68,.15)} }

    /* ── Results ── */
    .ms-result { text-align:center; padding:32px 20px; display:flex; flex-direction:column;
      align-items:center; gap:10px; }
    .ms-result-title { font-size:22px; font-weight:700; color:#f1f5f9; }
    .ms-result-score { font-size:60px; font-weight:900; color:#facc15; font-family:'JetBrains Mono',monospace; line-height:1; }
    .ms-result-label { color:#94a3b8; font-size:14px; }
    .ms-result-high  { color:#34d399; font-weight:700; font-size:14px; letter-spacing:.1em; }
    .ms-result-meta  { color:#64748b; font-size:13px; }
    .ms-result-inline { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:12px;
      background:rgba(34,197,94,.08); border-radius:12px; }
    .ms-result-win  { background:rgba(34,197,94,.06); border-radius:16px; }
    .ms-result-lose { background:rgba(239,68,68,.06); border-radius:16px; }

    /* ── Number Ninja ── */
    .ms-ninja-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-top:12px; }
    .ms-ninja-btn  { padding:18px; border-radius:14px; border:1px solid rgba(255,255,255,.12);
      background:rgba(255,255,255,.06); color:#e2e8f0; font-size:22px; font-weight:700;
      cursor:pointer; transition:all .15s ease; font-family:'JetBrains Mono',monospace; }
    .ms-ninja-btn:hover { background:rgba(124,58,237,.3); transform:scale(1.05); }
    .ms-ninja-hit  { background:rgba(34,197,94,.3)!important; }
    .ms-ninja-miss { background:rgba(239,68,68,.3)!important; }

    /* ── Memory Match ── */
    .ms-memory-grid { display:grid; grid-template-columns:repeat(var(--cols,4),1fr); gap:8px; margin-top:12px; }
    .ms-mem-card { height:72px; cursor:pointer; perspective:600px; }
    .ms-mem-inner { position:relative; width:100%; height:100%; transform-style:preserve-3d;
      transition:transform .35s ease; border-radius:12px; }
    .ms-mem-card.is-flipped .ms-mem-inner { transform:rotateY(180deg); }
    .ms-mem-front, .ms-mem-back {
      position:absolute; inset:0; border-radius:12px; display:flex;
      align-items:center; justify-content:center; font-size:20px; font-weight:700;
      backface-visibility:hidden;
    }
    .ms-mem-front { background:rgba(124,58,237,.2); border:1px solid rgba(124,58,237,.3); color:#a78bfa; }
    .ms-mem-back  { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12); color:#f1f5f9; transform:rotateY(180deg); }
    .ms-mem-card.is-matched .ms-mem-back { background:rgba(34,197,94,.15); border-color:#22c55e; }

    /* ── Word Scramble ── */
    .ms-scramble-word { font-size:clamp(28px,6vw,48px); font-weight:900; letter-spacing:.2em;
      color:#facc15; text-align:center; padding:20px; font-family:'JetBrains Mono',monospace; }
    .ms-scramble-form { display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:12px; }
    .ms-input { padding:12px 16px; border-radius:10px; border:1px solid rgba(255,255,255,.15);
      background:rgba(255,255,255,.06); color:#f1f5f9; font-size:16px; flex:1; min-width:160px;
      font-family:'JetBrains Mono',monospace; outline:none; }
    .ms-input:focus { border-color:#7c3aed; background:rgba(124,58,237,.08); }
    .ms-input-correct { border-color:#22c55e!important; background:rgba(34,197,94,.12)!important; }
    .ms-input-wrong   { border-color:#ef4444!important; background:rgba(239,68,68,.12)!important; animation:ms-shake .3s ease; }
    @keyframes ms-shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
    .ms-shake { animation:ms-shake .4s ease; }

    /* ── Boss Battle ── */
    .ms-battle { display:flex; gap:20px; justify-content:space-around; margin-bottom:20px; }
    .ms-battle-side { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; }
    .ms-boss-icon   { font-size:48px; line-height:1; }
    .ms-battle-name { font-weight:700; font-size:14px; color:#e2e8f0; }
    .ms-hp-bar-wrap { width:100%; height:10px; background:rgba(255,255,255,.08); border-radius:6px; overflow:hidden; }
    .ms-hp-bar      { height:100%; border-radius:6px; transition:width .4s ease; }
    .ms-hp-boss     { background:linear-gradient(90deg,#ef4444,#f97316); }
    .ms-hp-player   { background:linear-gradient(90deg,#22c55e,#0ea5e9); }
    .ms-hp-label    { font-size:12px; color:#64748b; font-family:'JetBrains Mono',monospace; }

    /* ── Math Puzzle ── */
    .ms-puzzle-wrap { overflow-x:auto; }
    .ms-puzzle-grid { border-collapse:collapse; margin:0 auto 12px; }
    .ms-puzzle-cell { width:60px; height:60px; text-align:center; border:1px solid rgba(255,255,255,.1); }
    .ms-puzzle-given { background:rgba(255,255,255,.06); font-size:20px; font-weight:700; color:#f1f5f9;
      font-family:'JetBrains Mono',monospace; }
    .ms-puzzle-blank { background:rgba(124,58,237,.08); }
    .ms-puzzle-input { width:48px; height:48px; text-align:center; border-radius:8px;
      border:1px solid rgba(124,58,237,.4); background:transparent; color:#facc15;
      font-size:18px; font-weight:700; font-family:'JetBrains Mono',monospace; outline:none; }
    .ms-puzzle-input:focus { border-color:#7c3aed; background:rgba(124,58,237,.1); }
    .ms-puzzle-target { font-size:13px; color:#7c3aed; font-weight:700; text-align:center; padding:4px 8px; }
    .ms-puzzle-status { min-height:28px; text-align:center; margin:8px 0; }
    .ms-correct-msg { color:#22c55e; font-weight:700; }
    .ms-wrong-msg   { color:#ef4444; font-weight:700; }
    .ms-puzzle-actions { display:flex; gap:10px; justify-content:center; }

    /* ── Daily Challenge ── */
    .ms-daily-header { display:flex; justify-content:space-between; align-items:center;
      padding:12px 16px; background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(37,99,235,.15));
      border-radius:14px; margin-bottom:14px; }
    .ms-daily-badge  { font-weight:700; color:#a78bfa; font-size:14px; }
    .ms-daily-reward { font-size:12px; color:#facc15; font-family:'JetBrains Mono',monospace; }

    /* ── Urgent ── */
    .ms-urgent { color:#ef4444; animation:ms-blink .5s infinite; }
  `;
  document.head.appendChild(s);
})();

/* ── UTILITIES ──────────────────────────────────────────── */
function msRandInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function msShuffleArray(arr) {
  const a = [...arr];
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function msShuffleChoices(answer, count, min, max) {
  const set = new Set([answer]);
  let tries = 0;
  while (set.size < count && tries < 200) {
    const n = msRandInt(min, max);
    if (n !== answer) set.add(n);
    tries++;
  }
  return msShuffleArray([...set]);
}

/* ── INIT ────────────────────────────────────────────────── */
msInitProfile();
msUpdateStreak();
