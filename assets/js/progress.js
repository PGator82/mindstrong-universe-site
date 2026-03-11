(function (w) {
  const TRACKER_KEY = 'ms_progress';

  function loadTracker() {
    try { return JSON.parse(localStorage.getItem(TRACKER_KEY) || '{}'); } catch { return {}; }
  }

  function lessonDone(lessonKey, tracker) {
    return Boolean(tracker.lessons && tracker.lessons[lessonKey] && tracker.lessons[lessonKey].completedAt);
  }

  function moduleComplete(module, tracker) {
    return module.lessons.length > 0 && module.lessons.every((l) => lessonDone(l.key, tracker));
  }

  function isUnlocked(module, modulesById, tracker) {
    if (module.unlock === 'start') return true;
    const [rule, parentId] = String(module.unlock || '').split(':');
    if (rule !== 'moduleComplete' || !parentId) return false;
    const parent = modulesById[parentId];
    return parent ? moduleComplete(parent, tracker) : false;
  }

  function progress(module, tracker) {
    const done = module.lessons.filter((l) => lessonDone(l.key, tracker)).length;
    return { done, total: module.lessons.length, pct: module.lessons.length ? Math.round((done / module.lessons.length) * 100) : 0 };
  }

  async function renderCourseEngine({ containerId, dataUrl }) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const res = await fetch(dataUrl);
    const data = await res.json();
    const tracker = loadTracker();
    const modules = data.modules || [];
    const byId = Object.fromEntries(modules.map((m) => [m.id, m]));

    const cards = modules.map((m) => {
      const unlocked = isUnlocked(m, byId, tracker);
      const p = progress(m, tracker);
      const lessons = m.lessons.map((l) => {
        const done = lessonDone(l.key, tracker);
        if (!unlocked) return `<li>🔒 ${l.title}</li>`;
        return `<li>${done ? '✅' : '➡️'} <a href="${l.url}">${l.title}</a></li>`;
      }).join('');

      return `<article class="ce-card ${unlocked ? '' : 'locked'}">
        <h3>Module ${m.number}: ${m.title} ${unlocked ? '' : '<span class="chip">Locked</span>'}</h3>
        <p>${p.done}/${p.total} lessons complete</p>
        <div class="ce-bar"><span style="width:${p.pct}%"></span></div>
        <ul>${lessons}</ul>
      </article>`;
    }).join('');

    const completedModules = modules.filter((m) => moduleComplete(m, tracker)).length;
    el.innerHTML = `<div class="ce-head"><strong>Course Engine</strong><span>${completedModules}/${modules.length} modules unlocked by progress</span></div>${cards}`;
  }

  w.MSCourseEngine = { renderCourseEngine };
})(window);
