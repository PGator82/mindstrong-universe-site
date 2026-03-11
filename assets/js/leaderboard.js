(function (w) {
  function readScores() {
    try { return JSON.parse(localStorage.getItem('ml_scores') || '[]'); } catch { return []; }
  }

  function readProgressXP() {
    try { return Number((JSON.parse(localStorage.getItem('ms_progress') || '{}').xp) || 0); } catch { return 0; }
  }

  function currentPlayer() {
    try {
      const s = JSON.parse(localStorage.getItem('ms_session') || '{}');
      return s.name || s.email || 'You';
    } catch { return 'You'; }
  }

  async function loadSeed(seedUrl) {
    if (!seedUrl) return [];
    try {
      const r = await fetch(seedUrl);
      const d = await r.json();
      return Array.isArray(d) ? d : [];
    } catch { return []; }
  }

  async function renderLeaderboard(containerId, seedUrl) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const xp = readProgressXP();
    const race = readScores().map((s) => ({ name: s.name, score: Number(s.score) || 0 }));
    const seed = await loadSeed(seedUrl);
    const merged = [...seed, ...race, { name: currentPlayer(), score: xp }]
      .filter((r) => r && r.name)
      .sort((a, b) => (Number(b.score) || 0) - (Number(a.score) || 0))
      .slice(0, 8);

    el.innerHTML = `<h3>🏆 Math Competition Leaderboard</h3><ol>${merged.map((r) => `<li><b>${r.name}</b> <span>${Number(r.score) || 0} pts</span></li>`).join('')}</ol>`;
  }

  w.MSLeaderboard = { renderLeaderboard };
})(window);
