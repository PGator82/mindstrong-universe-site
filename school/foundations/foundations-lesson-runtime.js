(function () {
  const cfg = window.FOUNDATION_LESSON;
  if (!cfg) return;

  const modeOptions = [
    { key: "arcade", label: "Arcade" },
    { key: "rpg", label: "RPG" },
    { key: "academic", label: "Academic" },
    { key: "lms", label: "LMS" }
  ];

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function getUnlockedModules() {
    try {
      return JSON.parse(localStorage.getItem("ms_unlocked_modules") || "[1]");
    } catch {
      return [1];
    }
  }

  function isUnlocked(moduleId) {
    if (!moduleId || moduleId === 1) return true;
    return getUnlockedModules().includes(moduleId);
  }

  function readDoneTokens() {
    try {
      return JSON.parse(localStorage.getItem(cfg.storageKey) || "[]");
    } catch {
      return [];
    }
  }

  function isDone() {
    return readDoneTokens().includes(cfg.doneToken);
  }

  function saveDone() {
    const arr = readDoneTokens();
    if (!arr.includes(cfg.doneToken)) {
      arr.push(cfg.doneToken);
      localStorage.setItem(cfg.storageKey, JSON.stringify(arr));
    }
  }

  function currentMode() {
    try {
      return localStorage.getItem("ms_xp_mode") || "academic";
    } catch {
      return "academic";
    }
  }

  function setMode(mode) {
    document.body.setAttribute("data-mode", mode);
    try {
      localStorage.setItem("ms_xp_mode", mode);
    } catch {}
    document.querySelectorAll(".ms-modeBtn").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.mode === mode);
    });
  }

  function initTracker() {
    if (!window.MSTracker || typeof window.MSTracker.init !== "function") return;
    try {
      window.MSTracker.init(cfg.trackerKey, {
        module: cfg.moduleTitle,
        lessonNum: cfg.lessonNumber || 1,
        title: cfg.title,
        practiceTotal: 1
      });
      if (typeof window.MSTracker.setMeta === "function") {
        window.MSTracker.setMeta("lastLessonUrl", window.location.pathname, false);
      }
    } catch {}
  }

  function markComplete() {
    saveDone();
    try {
      localStorage.setItem("ms_last_lesson_url", window.location.pathname);
    } catch {}
    if (window.MSTracker && typeof window.MSTracker.complete === "function") {
      try {
        window.MSTracker.complete(cfg.trackerKey);
      } catch {}
    }
    renderCompletionState(true);
  }

  function renderLockedState() {
    document.body.innerHTML = [
      '<div class="ms-pageFX"></div>',
      '<main class="ms-wrap" style="max-width:760px;margin:0 auto;padding:48px 18px 60px;">',
      '  <section class="ms-card" style="padding:28px;border-radius:18px;text-align:center;">',
      '    <div style="font-size:38px;margin-bottom:10px;">&#128274;</div>',
      '    <h1 class="ms-h1" style="margin-bottom:10px;">Module Locked</h1>',
      '    <p class="ms-sub" style="margin-bottom:18px;">A teacher needs to unlock this Foundations module from the main Foundations dashboard first.</p>',
      '    <a class="ms-btn primary" href="' + escapeHtml(cfg.foundationsHref) + '">Back to Foundations</a>',
      "  </section>",
      "</main>"
    ].join("");
  }

  function buildBullets() {
    return (cfg.points || [])
      .map(function (point) {
        return '<li style="margin-bottom:8px;">' + escapeHtml(point) + "</li>";
      })
      .join("");
  }

  function buildModeButtons() {
    return modeOptions
      .map(function (mode) {
        return '<button class="ms-modeBtn' +
          (mode.key === currentMode() ? " is-active" : "") +
          '" data-mode="' + mode.key + '">' + mode.label + "</button>";
      })
      .join("");
  }

  function buildNextButton() {
    if (!cfg.nextHref) {
      return '<a class="ms-btn" href="' + escapeHtml(cfg.backHref) + '">Back to Module</a>';
    }
    return '<a class="ms-btn primary" href="' + escapeHtml(cfg.nextHref) + '">' +
      escapeHtml(cfg.nextLabel || "Next Lesson") + " &rarr;</a>";
  }

  function injectMarkup() {
    document.body.innerHTML = [
      '<div class="ms-pageFX"></div>',
      '<header class="ms-top">',
      '  <div class="ms-nav">',
      '    <div class="ms-brand"><span class="ms-dot"></span><span>MindStrong Universe</span></div>',
      '    <nav class="ms-links">',
      '      <a class="ms-link" href="' + escapeHtml(cfg.homeHref) + '">Home</a>',
      '      <a class="ms-link" href="' + escapeHtml(cfg.schoolHref) + '">School</a>',
      '      <a class="ms-link" href="' + escapeHtml(cfg.foundationsHref) + '">Foundations</a>',
      '      <a class="ms-link" href="' + escapeHtml(cfg.backHref) + '">' + escapeHtml(cfg.moduleTitle) + "</a>",
      '      <a class="ms-link is-active" href="#">' + escapeHtml(cfg.title) + "</a>",
      "    </nav>",
      "  </div>",
      "</header>",
      '<main class="ms-wrap" style="max-width:760px;margin:0 auto;padding:24px 18px 60px;">',
      '  <section class="ms-hero ms-reveal">',
      '    <div class="lesson-chip">' + escapeHtml(cfg.lessonLabel) + "</div>",
      '    <h1 class="ms-h1">' + escapeHtml(cfg.title) + "</h1>",
      '    <p class="ms-sub">' + escapeHtml(cfg.subtitle) + "</p>",
      '    <div class="ms-actions">',
      '      <button id="completeBtn" class="ms-btn primary">Mark Complete</button>',
      '      <a class="ms-btn" href="' + escapeHtml(cfg.backHref) + '">&larr; Back to Module</a>',
      "    </div>",
      '    <div style="margin-top:10px;"><span class="ms-chip" id="statusChip">Status: In progress</span></div>',
      "  </section>",
      '  <div class="ms-modebar ms-reveal">',
      '    <span class="ms-modebar-label">Mode:</span>',
      buildModeButtons(),
      "  </div>",
      '  <section class="ms-card ms-reveal" style="padding:24px;border-radius:18px;margin-bottom:18px;">',
      '    <h2 class="ms-h2" style="margin-bottom:10px;">Big Idea</h2>',
      '    <p style="font-size:14px;line-height:1.7;color:#c8d3e8;">' + escapeHtml(cfg.summary) + "</p>",
      "  </section>",
      '  <section class="ms-card ms-reveal" style="padding:24px;border-radius:18px;margin-bottom:18px;">',
      '    <h2 class="ms-h2" style="margin-bottom:10px;">Key Moves</h2>',
      '    <ul style="padding-left:20px;color:#c8d3e8;line-height:1.7;">' + buildBullets() + "</ul>",
      "  </section>",
      '  <section class="ms-card ms-reveal" style="padding:24px;border-radius:18px;margin-bottom:18px;">',
      '    <h2 class="ms-h2" style="margin-bottom:10px;">Worked Example</h2>',
      '    <p style="font-size:14px;line-height:1.7;color:#c8d3e8;">' + escapeHtml(cfg.example) + "</p>",
      "  </section>",
      '  <div class="ms-completeCard" id="completeCard">',
      '    <div style="font-size:46px;margin-bottom:12px;">&#127942;</div>',
      '    <h2 style="font-family:\'Orbitron\',sans-serif;font-size:20px;color:var(--xp-a);margin-bottom:8px;">Lesson Complete</h2>',
      '    <p style="font-size:13px;color:#c8d3e8;margin-bottom:16px;">' + escapeHtml(cfg.title) + " is now marked complete.</p>",
      '    <div class="ms-actions" style="justify-content:center;">',
      '      <a class="ms-btn" href="' + escapeHtml(cfg.backHref) + '">Back to Module</a>',
      buildNextButton(),
      "    </div>",
      "  </div>",
      "</main>"
    ].join("");

    const style = document.createElement("style");
    style.textContent = [
      "body{font-family:'Syne',sans-serif;}",
      ":root{--xp-a:#22d3ee;--xp-b:#3b82f6;--xp-glow:rgba(34,211,238,.15);}",
      "body[data-mode='arcade']{--xp-a:#facc15;--xp-b:#f97316;--xp-glow:rgba(250,204,21,.15);}",
      "body[data-mode='rpg']{--xp-a:#a855f7;--xp-b:#7c3aed;--xp-glow:rgba(168,85,247,.16);}",
      "body[data-mode='academic']{--xp-a:#22d3ee;--xp-b:#3b82f6;--xp-glow:rgba(34,211,238,.15);}",
      "body[data-mode='lms']{--xp-a:#34d399;--xp-b:#059669;--xp-glow:rgba(52,211,153,.14);}",
      ".ms-pageFX{position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(700px 500px at 20% 0%,var(--xp-glow),transparent 60%),radial-gradient(500px 400px at 85% 85%,rgba(124,58,237,.07),transparent 60%);}",
      ".ms-wrap{position:relative;z-index:1;}",
      ".lesson-chip{display:inline-flex;align-items:center;gap:6px;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;padding:4px 12px;border-radius:20px;border:1px solid var(--xp-a);color:var(--xp-a);margin-bottom:12px;}",
      ".ms-modebar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:24px;padding:12px 16px;border:1px solid rgba(255,255,255,.08);border-radius:14px;background:rgba(255,255,255,.03);}",
      ".ms-modebar-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.15em;text-transform:uppercase;color:var(--muted,#6b7a99);margin-right:4px;flex-shrink:0;}",
      ".ms-modeBtn{font-family:'JetBrains Mono',monospace;font-size:11px;padding:6px 13px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:var(--muted,#6b7a99);cursor:pointer;transition:all .2s;white-space:nowrap;}",
      ".ms-modeBtn:hover{color:#e8edf5;border-color:rgba(255,255,255,.22);}",
      ".ms-modeBtn.is-active{color:var(--xp-a);border-color:var(--xp-a);background:rgba(255,255,255,.07);box-shadow:0 0 10px var(--xp-glow);}",
      ".ms-btn{font-family:'Syne',sans-serif;font-size:13px;font-weight:700;padding:9px 18px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#e8edf5;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block;transition:all .2s;}",
      ".ms-btn.primary{background:linear-gradient(135deg,var(--xp-b),var(--xp-a));border-color:transparent;color:#06080e;box-shadow:0 4px 18px var(--xp-glow);}",
      ".ms-btn.primary:hover,.ms-btn:hover{transform:translateY(-2px);}",
      ".ms-chip{display:inline-flex;padding:6px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.04);color:var(--muted,#6b7a99);font-size:12px;transition:all .3s ease;}",
      ".ms-chip.done{border-color:var(--xp-a);color:var(--xp-a);background:rgba(255,255,255,.07);}",
      ".ms-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;}",
      ".ms-completeCard{display:none;padding:28px;border-radius:18px;text-align:center;border:2px solid var(--xp-a);background:rgba(255,255,255,.04);margin-top:24px;box-shadow:0 0 30px var(--xp-glow);}",
      ".ms-completeCard.is-visible{display:block;}",
      ".ms-reveal{opacity:0;transform:translateY(12px);transition:opacity .5s,transform .5s;}",
      ".ms-reveal.is-in{opacity:1;transform:none;}"
    ].join("");
    document.head.appendChild(style);
  }

  function renderCompletionState(showCard) {
    const done = isDone();
    const chip = document.getElementById("statusChip");
    const button = document.getElementById("completeBtn");
    const card = document.getElementById("completeCard");
    if (chip) {
      chip.textContent = done ? "Status: Complete" : "Status: In progress";
      chip.classList.toggle("done", done);
    }
    if (button) {
      button.textContent = done ? "Completed" : "Mark Complete";
      button.disabled = done;
    }
    if (card) {
      card.classList.toggle("is-visible", !!showCard);
    }
  }

  if (!isUnlocked(cfg.moduleId)) {
    renderLockedState();
    return;
  }

  injectMarkup();
  setMode(currentMode());
  initTracker();
  renderCompletionState(false);

  document.getElementById("completeBtn").addEventListener("click", markComplete);
  document.querySelectorAll(".ms-modeBtn").forEach((btn) => {
    btn.addEventListener("click", function () { setMode(btn.dataset.mode); });
  });

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) entry.target.classList.add("is-in");
    });
  }, { threshold: 0.08 });
  document.querySelectorAll(".ms-reveal").forEach(function (el) {
    observer.observe(el);
  });
})();
