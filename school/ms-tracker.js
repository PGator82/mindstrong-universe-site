/**
 * MindStrong Universe — Progress & Score Tracker
 * ms-tracker.js  (place at /school/ms-tracker.js)
 *
 * Schema stored under localStorage key "ms_progress" (one JSON blob):
 * {
 *   version: 2,
 *   xp: 0,
 *   streak: { current: 0, best: 0, lastDate: "YYYY-MM-DD" },
 *   lessons: {
 *     "geo-angles": {
 *       key:        "geo-angles",
 *       module:     "geometry",
 *       lessonNum:  1,
 *       title:      "Angles & Lines",
 *       firstSeen:  1234567890,        // ms timestamp
 *       completedAt: 1234567890,       // null if not complete
 *       practiceScore:  3,             // correct out of 3
 *       practiceTotal:  3,
 *       exitAttempts:   1,
 *       bossAttempts:   2,
 *       bossWon:        true,
 *       bossBestTime:   18,            // seconds remaining on win
 *       bossWonAt:      1234567890,
 *       xpEarned:       220,
 *     },
 *     ...
 *   }
 * }
 *
 * XP FORMULA per lesson:
 *   Base complete:     100 XP
 *   Practice 3/3:      +30 XP
 *   Exit first try:    +20 XP
 *   Boss won:          +50 XP
 *   Boss speed bonus:  +1 XP per second remaining (max 30)
 *   Boss first try:    +25 XP bonus
 */

(function(w) {
  'use strict';

  const STORAGE_KEY = 'ms_progress';
  const VERSION = 2;

  /* ── XP constants ── */
  const XP = {
    COMPLETE:        100,
    PRACTICE_FULL:    30,
    EXIT_FIRST:       20,
    BOSS_WIN:         50,
    BOSS_FIRST:       25,
    BOSS_SPEED_MAX:   30,  // max 1 XP per second remaining
  };

  /* ── Load / save ── */
  function load() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return fresh();
      const d = JSON.parse(raw);
      if (!d || d.version !== VERSION) return migrate(d);
      return d;
    } catch { return fresh(); }
  }

  function save(data) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch {}
  }

  function fresh() {
    return {
      version: VERSION,
      xp: 0,
      streak: { current: 0, best: 0, lastDate: null },
      lessons: {}
    };
  }

  function migrate(old) {
    // v1 or null — start fresh but preserve xp estimate
    const d = fresh();
    if (old && typeof old.xp === 'number') d.xp = old.xp;
    return d;
  }

  /* ── Ensure lesson record exists ── */
  function ensureLesson(data, key, meta) {
    if (!data.lessons[key]) {
      data.lessons[key] = {
        key,
        module:        meta.module     || 'unknown',
        lessonNum:     meta.lessonNum  || 0,
        title:         meta.title      || key,
        firstSeen:     Date.now(),
        completedAt:   null,
        practiceScore: 0,
        practiceTotal: meta.practiceTotal || 3,
        practiceAttempts: 0,
        exitAttempts:  0,
        bossAttempts:  0,
        bossWon:       false,
        bossBestTime:  null,
        bossWonAt:     null,
        xpEarned:      0,
      };
    }
    return data.lessons[key];
  }

  /* ── Streak update ── */
  function updateStreak(data) {
    const today = new Date().toISOString().slice(0, 10);
    const s = data.streak;
    if (s.lastDate === today) return;
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
    if (s.lastDate === yesterday) {
      s.current += 1;
    } else {
      s.current = 1;
    }
    if (s.current > s.best) s.best = s.current;
    s.lastDate = today;
  }

  /* ─────────────────────────────────────────
     PUBLIC API  —  window.MSTracker
  ───────────────────────────────────────── */
  const T = {};

  /**
   * Call at lesson load. Registers the lesson, updates streak.
   * meta = { module, lessonNum, title, practiceTotal }
   */
  T.init = function(key, meta) {
    const data = load();
    ensureLesson(data, key, meta);
    updateStreak(data);
    save(data);
  };

  /**
   * Record a practice answer attempt.
   * correct: boolean
   */
  T.practice = function(key, correct) {
    const data = load();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.practiceAttempts = (lesson.practiceAttempts || 0) + 1;
    if (correct) lesson.practiceScore = Math.min(lesson.practiceScore + 1, lesson.practiceTotal);
    save(data);
  };

  /**
   * Record an exit check attempt.
   * passed: boolean
   */
  T.exit = function(key, passed) {
    const data = load();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.exitAttempts = (lesson.exitAttempts || 0) + 1;
    save(data);
  };

  /**
   * Record boss fight result.
   * won: boolean, timeRemaining: number (seconds left on clock)
   */
  T.boss = function(key, won, timeRemaining) {
    const data = load();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.bossAttempts = (lesson.bossAttempts || 0) + 1;
    if (won) {
      lesson.bossWon = true;
      lesson.bossWonAt = Date.now();
      const t = Math.max(0, timeRemaining || 0);
      if (lesson.bossBestTime === null || t > lesson.bossBestTime) {
        lesson.bossBestTime = t;
      }
    }
    save(data);
  };

  /**
   * Mark lesson complete and compute final XP.
   */
  T.complete = function(key) {
    const data = load();
    const lesson = data.lessons[key];
    if (!lesson) return;
    if (lesson.completedAt) return; // already done

    lesson.completedAt = Date.now();

    // XP calculation
    let xp = XP.COMPLETE;
    if (lesson.practiceScore >= lesson.practiceTotal) xp += XP.PRACTICE_FULL;
    if (lesson.exitAttempts === 1) xp += XP.EXIT_FIRST;
    if (lesson.bossWon) {
      xp += XP.BOSS_WIN;
      const speedBonus = Math.min(lesson.bossBestTime || 0, XP.BOSS_SPEED_MAX);
      xp += speedBonus;
      if (lesson.bossAttempts === 1) xp += XP.BOSS_FIRST;
    }

    lesson.xpEarned = xp;
    data.xp = (data.xp || 0) + xp;
    save(data);
    return xp;
  };

  /** Read full progress data */
  T.read = function() { return load(); };

  /** Read a single lesson */
  T.readLesson = function(key) {
    const d = load();
    return d.lessons[key] || null;
  };

  /** Read total XP */
  T.xp = function() { return load().xp || 0; };

  /** Get streak */
  T.streak = function() { return load().streak || { current: 0, best: 0 }; };

  /** Reset everything (dev/teacher use) */
  T.reset = function() {
    try { localStorage.removeItem(STORAGE_KEY); } catch {}
  };

  /** Export all data as JSON string */
  T.export = function() { return JSON.stringify(load(), null, 2); };

  w.MSTracker = T;

})(window);
