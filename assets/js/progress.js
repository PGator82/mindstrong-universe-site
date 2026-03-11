(function (w) {
  const TRACKER_KEY = 'ms_progress';

  function loadTracker() {
    try {
      const parsed = JSON.parse(localStorage.getItem(TRACKER_KEY) || '{}');
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
      return {};
    }
  }

  function lessonKeys(lesson) {
    const keys = [lesson.key, ...(Array.isArray(lesson.aliases) ? lesson.aliases : [])]
      .filter(Boolean)
      .map(String);
    return [...new Set(keys)];
  }

  function lessonDone(lesson, tracker) {
    const lessons = tracker.lessons || {};
    return lessonKeys(lesson).some((k) => Boolean(lessons[k] && lessons[k].completedAt));
  }

  function moduleComplete(module, tracker) {
    const lessons = Array.isArray(module.lessons) ? module.lessons : [];
    return lessons.length > 0 && lessons.every((l) => lessonDone(l, tracker));
  }

  function isUnlocked(module, modulesById, tracker) {
    if (module.unlock === 'start') return true;
    const [rule, parentId] = String(module.unlock || '').split(':');
    if (rule !== 'moduleComplete' || !parentId) return false;
    const parent = modulesById[parentId];
    return parent ? moduleComplete(parent, tracker) : false;
  }

  function moduleProgress(module, tracker) {
    const lessons = Array.isArray(module.lessons) ? module.lessons : [];
    const done = lessons.filter((l) => lessonDone(l, tracker)).length;
    const total = lessons.length;
    return { done, total, pct: total ? Math.round((done / total) * 100) : 0 };
  }

  function lessonRow(lesson, unlocked, done) {
    if (!unlocked) return `<li>🔒 ${lesson.title}</li>`;
    return `<li>${done ? '✅' : '➡️'} <a href="${lesson.url}">${lesson.title}</a></li>`;
  }

  async function renderCourseEngine({ containerId, dataUrl }) {
    const el = document.getElementById(containerId);
    if (!el) return;

    try {
      const res = await fetch(dataUrl, { cache: 'no-store' });
      const data = await res.json();
      const tracker = loadTracker();
      const modules = Array.isArray(data.modules) ? data.modules : [];
      const byId = Object.fromEntries(modules.map((m) => [m.id, m]));

      const cards = modules.map((m) => {
        const unlocked = isUnlocked(m, byId, tracker);
        const p = moduleProgress(m, tracker);
        const lessons = (m.lessons || []).map((l) => lessonRow(l, unlocked, lessonDone(l, tracker))).join('');

        return `<article class="ce-card ${unlocked ? '' : 'locked'}">
          <h3>Module ${m.number}: ${m.title} ${unlocked ? '' : '<span class="chip">Locked</span>'}</h3>
          <p>${p.done}/${p.total} lessons complete</p>
          <div class="ce-bar"><span style="width:${p.pct}%"></span></div>
          <ul>${lessons}</ul>
        </article>`;
      }).join('');

      const completedModules = modules.filter((m) => moduleComplete(m, tracker)).length;
      el.innerHTML = `<div class="ce-head"><strong>Course Engine</strong><span>${completedModules}/${modules.length} modules fully complete</span></div>${cards}`;
    } catch {
      el.innerHTML = '<p class="ms-sub">Course engine unavailable right now.</p>';
    }
  }

  w.MSCourseEngine = { renderCourseEngine };
})(window);
