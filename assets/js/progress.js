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

  function actionLink(url, label, primary) {
    if (!url) return '';
    return `<a class="ms-btn${primary ? ' primary' : ''}" href="${url}">${label}</a>`;
  }

  function moduleActions(module, unlocked) {
    if (!unlocked) return '';
    const actions = [
      actionLink(module.hubUrl, module.hubLabel || 'Open Module →', true),
      actionLink(module.leagueUrl, module.leagueLabel || 'League →', false),
      actionLink(module.gamesUrl, module.gamesLabel || 'Games →', false),
    ].filter(Boolean);

    if (!actions.length) return '';
    return `<div class="ce-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 4px;">${actions.join('')}</div>`;
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
        const actions = moduleActions(m, unlocked);
        const summary = m.summary ? `<p>${m.summary}</p>` : `<p>${p.done}/${p.total} lessons complete</p>`;

        return `<article class="ce-card ${unlocked ? '' : 'locked'}">
          <h3>Module ${m.number}: ${m.title} ${unlocked ? '' : '<span class="chip">Locked</span>'}</h3>
          ${summary}
          <div class="ce-head" style="margin:8px 0 10px;"><span>${p.done}/${p.total} lessons complete</span>${m.theme ? `<span class="chip">${m.theme}</span>` : ''}</div>
          <div class="ce-bar"><span style="width:${p.pct}%"></span></div>
          ${actions}
          <ul>${lessons}</ul>
        </article>`;
      }).join('');

      const completedModules = modules.filter((m) => moduleComplete(m, tracker)).length;
      const courseActions = [
        actionLink(data.hubUrl, data.hubLabel || 'Open Course →', true),
        actionLink(data.leagueUrl, data.leagueLabel || 'League →', false),
        actionLink(data.gamesUrl, data.gamesLabel || 'Games →', false),
      ].filter(Boolean).join('');
      el.innerHTML = `<div class="ce-head"><strong>${data.course || 'Course Engine'}</strong><span>${completedModules}/${modules.length} modules fully complete</span></div>
        ${data.description ? `<p class="ms-sub" style="margin:0 0 12px;">${data.description}</p>` : ''}
        ${courseActions ? `<div class="ce-actions" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">${courseActions}</div>` : ''}
        ${cards}`;
    } catch {
      el.innerHTML = '<p class="ms-sub">Course engine unavailable right now.</p>';
    }
  }

  w.MSCourseEngine = { renderCourseEngine };
})(window);
