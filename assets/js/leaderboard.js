(function (w) {
  function readJSON(key, fallback) {
    try {
      const value = JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
      return Array.isArray(fallback) ? (Array.isArray(value) ? value : fallback) : (value || fallback);
    } catch {
      return fallback;
    }
  }

  function readScores() {
    return readJSON('ml_scores', []).map((s) => ({ name: s.name, score: Number(s.score) || 0, source: 'math-league' }));
  }

  function readProgressXP() {
    const progress = readJSON('ms_progress', {});
    return Number(progress.xp) || 0;
  }

  function currentPlayer() {
    const session = readJSON('ms_session', {});
    return session.name || session.email || 'You';
  }

  async function loadSeed(seedUrl) {
    if (!seedUrl) return [];
    try {
      const response = await fetch(seedUrl, { cache: 'no-store' });
      const data = await response.json();
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  }

  function mergeBestScores(rows) {
    const bestByName = new Map();
    rows.forEach((row) => {
      const name = String(row.name || '').trim();
      if (!name) return;
      const score = Number(row.score) || 0;
      const prev = bestByName.get(name);
      if (!prev || score > prev.score) {
        bestByName.set(name, { name, score, source: row.source || prev?.source || 'combined' });
      }
    });
    return [...bestByName.values()].sort((a, b) => b.score - a.score);
  }

  async function renderLeaderboard(containerId, seedUrl) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const xp = readProgressXP();
    const seed = await loadSeed(seedUrl);
    const race = readScores();
    const combined = mergeBestScores([
      ...seed,
      ...race,
      { name: currentPlayer(), score: xp, source: 'course-xp' }
    ]).slice(0, 10);

    if (!combined.length) {
      el.innerHTML = '<h3>🏆 Math Competition Leaderboard</h3><p class="ms-sub">No scores yet — complete lessons or play Math League to rank up.</p>';
      return;
    }

    el.innerHTML = `<h3>🏆 Math Competition Leaderboard</h3><ol>${combined.map((r) => `<li><b>${r.name}</b> <span>${r.score} pts</span></li>`).join('')}</ol>`;
  }

  w.MSLeaderboard = { renderLeaderboard };
})(window);
