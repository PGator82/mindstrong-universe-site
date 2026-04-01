/**
 * MindStrong Universe Progress Tracker
 * Remote-first persistence with local cache fallback.
 */

(function(w) {
  'use strict';

  const STORAGE_KEY = 'ms_progress';
  const TOKEN_KEY = 'ms_progress_token';
  const VERSION = 2;
  const REMOTE_APP = 'https://mindstrong-universe-school-production.up.railway.app';

  const XP = {
    COMPLETE: 100,
    PRACTICE_FULL: 30,
    EXIT_FIRST: 20,
    BOSS_WIN: 50,
    BOSS_FIRST: 25,
    BOSS_SPEED_MAX: 30
  };

  let cache = null;
  let bootstrapped = false;
  let bootOwner = null;
  let syncTimer = null;
  let syncPromise = null;

  function nowMs() {
    return Date.now();
  }

  function getApiBase() {
    try {
      if (w.location && /github\.io$/i.test(w.location.hostname)) {
        return REMOTE_APP + '/api';
      }
      return w.location.origin + '/api';
    } catch {
      return REMOTE_APP + '/api';
    }
  }

  function getToken() {
    try {
      let token = localStorage.getItem(TOKEN_KEY);
      if (!token) {
        token = 'ms_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem(TOKEN_KEY, token);
      }
      return token;
    } catch {
      return 'ms_guest_fallback';
    }
  }

  function fresh() {
    return {
      version: VERSION,
      xp: 0,
      streak: { current: 0, best: 0, lastDate: null },
      lessons: {}
    };
  }

  function normalize(data) {
    const base = fresh();
    if (!data || typeof data !== 'object') return base;

    const streak = data.streak && typeof data.streak === 'object' ? data.streak : {};
    const lessonsIn = data.lessons && typeof data.lessons === 'object' ? data.lessons : {};
    const lessons = {};

    Object.keys(lessonsIn).forEach((key) => {
      const lesson = lessonsIn[key];
      if (!lesson || typeof lesson !== 'object') return;
      const lessonKey = String(lesson.key || key);
      lessons[lessonKey] = {
        key: lessonKey,
        module: String(lesson.module || 'unknown'),
        lessonNum: Number.isFinite(+lesson.lessonNum) ? +lesson.lessonNum : 0,
        title: String(lesson.title || lessonKey),
        firstSeen: Number.isFinite(+lesson.firstSeen) ? +lesson.firstSeen : nowMs(),
        completedAt: Number.isFinite(+lesson.completedAt) ? +lesson.completedAt : null,
        practiceScore: Number.isFinite(+lesson.practiceScore) ? +lesson.practiceScore : 0,
        practiceTotal: Math.max(1, Number.isFinite(+lesson.practiceTotal) ? +lesson.practiceTotal : 3),
        practiceAttempts: Number.isFinite(+lesson.practiceAttempts) ? +lesson.practiceAttempts : 0,
        exitAttempts: Number.isFinite(+lesson.exitAttempts) ? +lesson.exitAttempts : 0,
        bossAttempts: Number.isFinite(+lesson.bossAttempts) ? +lesson.bossAttempts : 0,
        bossWon: !!lesson.bossWon,
        bossBestTime: Number.isFinite(+lesson.bossBestTime) ? +lesson.bossBestTime : null,
        bossWonAt: Number.isFinite(+lesson.bossWonAt) ? +lesson.bossWonAt : null,
        xpEarned: Number.isFinite(+lesson.xpEarned) ? +lesson.xpEarned : 0
      };
    });

    base.lessons = lessons;
    base.streak = {
      current: Number.isFinite(+streak.current) ? +streak.current : 0,
      best: Number.isFinite(+streak.best) ? +streak.best : 0,
      lastDate: streak.lastDate || null
    };
    base.xp = sumXp(base.lessons);
    return base;
  }

  function sumXp(lessons) {
    return Object.values(lessons || {}).reduce((sum, lesson) => sum + (Number.isFinite(+lesson.xpEarned) ? +lesson.xpEarned : 0), 0);
  }

  function loadLocal() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return fresh();
      return normalize(JSON.parse(raw));
    } catch {
      return fresh();
    }
  }

  function saveLocal(data) {
    const normalized = normalize(data);
    cache = normalized;
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
    } catch {}
    syncLegacyKeys(normalized);
    return normalized;
  }

  function syncLegacyKeys(data) {
    const m1Done = [];
    const engDone = [];
    const sciDone = [];
    const preAlgDone = new Set();
    let ratioRank = false;
    const unlocked = new Set([1]);

    Object.values(data.lessons || {}).forEach((lesson) => {
      if (!lesson || !lesson.completedAt) return;
      const key = lesson.key;
      let match;
      if ((match = /^m1-l(\d+)$/.exec(key))) {
        m1Done.push(Number(match[1]));
        if (Number(match[1]) >= 6) unlocked.add(2);
      } else if ((match = /^eng-m1-l(\d+)$/.exec(key))) {
        engDone.push('lesson-' + match[1]);
      } else if ((match = /^sci-m1-l(\d+)$/.exec(key))) {
        sciDone.push('lesson-' + match[1]);
      } else if (key === 'geo-ratios') {
        ratioRank = true;
      } else if (key === 'geo-proportions') {
        preAlgDone.add('proportions-1');
      } else if (key === 'geo-percents') {
        preAlgDone.add('percents-1');
      } else if (key === 'geo-expressions') {
        preAlgDone.add('expressions-1');
      } else if (key === 'geo-equations') {
        preAlgDone.add('equations-1');
      }
    });

    const preCount = (ratioRank ? 1 : 0) + preAlgDone.size;
    if (preCount >= 5) unlocked.add(3);

    try {
      localStorage.setItem('ms_m1_done', JSON.stringify(m1Done.sort((a, b) => a - b)));
      localStorage.setItem('ms_eng_m1_done', JSON.stringify(engDone.sort()));
      localStorage.setItem('ms_sci_m1_done', JSON.stringify(sciDone.sort()));
      localStorage.setItem('ms_pre_alg_done', JSON.stringify(Array.from(preAlgDone)));
      localStorage.setItem('ms_unlocked_modules', JSON.stringify(Array.from(unlocked).sort((a, b) => a - b)));
      if (ratioRank) {
        localStorage.setItem('ms_ratio_rank', 'Ratio Master');
      }
    } catch {}
  }

  function mergeLessons(a, b) {
    return {
      key: b.key || a.key,
      module: b.module || a.module || 'unknown',
      lessonNum: Math.max(a.lessonNum || 0, b.lessonNum || 0),
      title: b.title || a.title || b.key || a.key,
      firstSeen: Math.min(a.firstSeen || nowMs(), b.firstSeen || nowMs()),
      completedAt: Math.max(a.completedAt || 0, b.completedAt || 0) || null,
      practiceScore: Math.max(a.practiceScore || 0, b.practiceScore || 0),
      practiceTotal: Math.max(a.practiceTotal || 3, b.practiceTotal || 3),
      practiceAttempts: Math.max(a.practiceAttempts || 0, b.practiceAttempts || 0),
      exitAttempts: Math.max(a.exitAttempts || 0, b.exitAttempts || 0),
      bossAttempts: Math.max(a.bossAttempts || 0, b.bossAttempts || 0),
      bossWon: !!(a.bossWon || b.bossWon),
      bossBestTime: Math.max(a.bossBestTime || 0, b.bossBestTime || 0) || null,
      bossWonAt: Math.max(a.bossWonAt || 0, b.bossWonAt || 0) || null,
      xpEarned: Math.max(a.xpEarned || 0, b.xpEarned || 0)
    };
  }

  function mergeData(localData, remoteData) {
    const local = normalize(localData);
    const remote = normalize(remoteData);
    const merged = fresh();
    const keys = new Set([...Object.keys(local.lessons), ...Object.keys(remote.lessons)]);

    keys.forEach((key) => {
      if (local.lessons[key] && remote.lessons[key]) {
        merged.lessons[key] = mergeLessons(local.lessons[key], remote.lessons[key]);
      } else {
        merged.lessons[key] = local.lessons[key] || remote.lessons[key];
      }
    });

    const localDate = local.streak.lastDate || '';
    const remoteDate = remote.streak.lastDate || '';
    const newer = remoteDate >= localDate ? remote : local;
    merged.streak = {
      current: newer.streak.current || 0,
      best: Math.max(local.streak.best || 0, remote.streak.best || 0, newer.streak.current || 0),
      lastDate: newer.streak.lastDate || null
    };
    merged.xp = sumXp(merged.lessons);
    return normalize(merged);
  }

  function emitSync(reason) {
    try {
      w.dispatchEvent(new CustomEvent('ms-progress-sync', {
        detail: { reason: reason || 'sync', progress: cache, owner: bootOwner }
      }));
    } catch {}
  }

  function syncUrl() {
    return getApiBase() + '/progress?token=' + encodeURIComponent(getToken());
  }

  function saveUrl() {
    return getApiBase() + '/progress/save';
  }

  function bootstrapRemote() {
    if (bootstrapped) return cache || loadLocal();

    let local = loadLocal();
    let remote = null;
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', syncUrl(), false);
      xhr.send(null);
      if (xhr.status >= 200 && xhr.status < 300 && xhr.responseText) {
        const payload = JSON.parse(xhr.responseText);
        if (payload && payload.progress) {
          remote = payload.progress;
          bootOwner = payload.owner || null;
          if (payload.owner && payload.owner.token) {
            try { localStorage.setItem(TOKEN_KEY, payload.owner.token); } catch {}
          }
        }
      }
    } catch {}

    const merged = mergeData(local, remote);
    saveLocal(merged);
    bootstrapped = true;

    if (remote && JSON.stringify(normalize(remote)) !== JSON.stringify(merged)) {
      scheduleRemoteSave(merged, true);
    }

    emitSync('bootstrap');
    return merged;
  }

  function scheduleRemoteSave(data, immediate) {
    const payload = normalize(data || cache || loadLocal());
    const run = function() {
      syncPromise = fetch(saveUrl(), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: getToken(), progress: payload }),
        credentials: 'include',
        keepalive: true
      }).then((res) => res.json()).then((json) => {
        if (json && json.progress) {
          bootOwner = json.owner || bootOwner;
          saveLocal(mergeData(cache, json.progress));
          emitSync('save');
        }
      }).catch(() => {}).finally(() => {
        syncPromise = null;
      });
      return syncPromise;
    };

    if (immediate) return run();
    if (syncTimer) clearTimeout(syncTimer);
    syncTimer = setTimeout(run, 400);
    return Promise.resolve(payload);
  }

  function ensureLesson(data, key, meta) {
    if (!data.lessons[key]) {
      data.lessons[key] = {
        key: key,
        module: meta.module || 'unknown',
        lessonNum: meta.lessonNum || 0,
        title: meta.title || key,
        firstSeen: nowMs(),
        completedAt: null,
        practiceScore: 0,
        practiceTotal: meta.practiceTotal || 3,
        practiceAttempts: 0,
        exitAttempts: 0,
        bossAttempts: 0,
        bossWon: false,
        bossBestTime: null,
        bossWonAt: null,
        xpEarned: 0
      };
    }
    return data.lessons[key];
  }

  function updateStreak(data) {
    const today = new Date().toISOString().slice(0, 10);
    const streak = data.streak;
    if (streak.lastDate === today) return;
    const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
    streak.current = streak.lastDate === yesterday ? streak.current + 1 : 1;
    streak.best = Math.max(streak.best, streak.current);
    streak.lastDate = today;
  }

  function readData() {
    if (!bootstrapped) {
      return bootstrapRemote();
    }
    if (!cache) {
      cache = loadLocal();
    }
    return normalize(cache);
  }

  function writeData(data, immediate) {
    const saved = saveLocal(data);
    scheduleRemoteSave(saved, !!immediate);
    return saved;
  }

  const T = {};

  T.init = function(key, meta) {
    const data = readData();
    ensureLesson(data, key, meta || {});
    updateStreak(data);
    writeData(data, false);
  };

  T.practice = function(key, correct) {
    const data = readData();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.practiceAttempts += 1;
    if (correct) lesson.practiceScore = Math.min(lesson.practiceScore + 1, lesson.practiceTotal);
    writeData(data, false);
  };

  T.exit = function(key) {
    const data = readData();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.exitAttempts += 1;
    writeData(data, false);
  };

  T.boss = function(key, won, timeRemaining) {
    const data = readData();
    const lesson = data.lessons[key];
    if (!lesson) return;
    lesson.bossAttempts += 1;
    if (won) {
      lesson.bossWon = true;
      lesson.bossWonAt = nowMs();
      const remaining = Math.max(0, timeRemaining || 0);
      if (lesson.bossBestTime === null || remaining > lesson.bossBestTime) {
        lesson.bossBestTime = remaining;
      }
    }
    writeData(data, false);
  };

  T.complete = function(key) {
    const data = readData();
    const lesson = data.lessons[key];
    if (!lesson) return 0;
    if (lesson.completedAt) return lesson.xpEarned || 0;

    lesson.completedAt = nowMs();
    let xp = XP.COMPLETE;
    if (lesson.practiceScore >= lesson.practiceTotal) xp += XP.PRACTICE_FULL;
    if (lesson.exitAttempts === 1) xp += XP.EXIT_FIRST;
    if (lesson.bossWon) {
      xp += XP.BOSS_WIN;
      xp += Math.min(lesson.bossBestTime || 0, XP.BOSS_SPEED_MAX);
      if (lesson.bossAttempts === 1) xp += XP.BOSS_FIRST;
    }
    lesson.xpEarned = xp;
    data.xp = sumXp(data.lessons);
    writeData(data, true);
    return xp;
  };

  T.read = function() {
    return readData();
  };

  T.readLesson = function(key) {
    return readData().lessons[key] || null;
  };

  T.getProgress = T.readLesson;

  T.xp = function() {
    return readData().xp || 0;
  };

  T.streak = function() {
    return readData().streak || { current: 0, best: 0 };
  };

  T.reset = function() {
    const data = fresh();
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem('ms_m1_done');
      localStorage.removeItem('ms_eng_m1_done');
      localStorage.removeItem('ms_sci_m1_done');
      localStorage.removeItem('ms_pre_alg_done');
      localStorage.removeItem('ms_ratio_rank');
    } catch {}
    cache = data;
    writeData(data, true);
  };

  T.export = function() {
    return JSON.stringify(readData(), null, 2);
  };

  T.sync = function() {
    const local = readData();
    return fetch(syncUrl(), { credentials: 'include' })
      .then((res) => res.json())
      .then((json) => {
        if (json && json.progress) {
          bootOwner = json.owner || bootOwner;
          const merged = mergeData(local, json.progress);
          saveLocal(merged);
          emitSync('manual-sync');
          return merged;
        }
        return local;
      })
      .catch(() => local);
  };

  bootstrapRemote();
  w.MSTracker = T;
})(window);
