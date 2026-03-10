<script>
(function initLessonVibe(){
  // --- CONFIG ---
  // From /school/foundations/module-1/*.html => repo root is ../../../
  const ROOT = "../../../";

  // Pool of repo images to rotate through
  const holoPool = [
    ROOT + "playground-image-S7XSr.png",
    ROOT + "playground-image-SPhEr.png",
    ROOT + "playground-image-foiEG.png",
    ROOT + "playground-image-CwQig.png",
    ROOT + "playground-image-d7AoO.png",
  ];

  // Preload
  holoPool.forEach((src) => { const im = new Image(); im.src = src; });

  // Find a target container on the lesson page:
  // Try common wrappers; fall back to body.
  const main =
    document.querySelector(".ms-wrap") ||
    document.querySelector("main") ||
    document.body;

  // Build the Web3 “holo” block
  const wrap = document.createElement("section");
  wrap.className = "ms-card ms-holoStage";
  wrap.innerHTML = `
    <div class="ms-holoBg"></div>
    <img class="ms-holoSeal" src="${ROOT}Mindstrong.svg" alt="MindStrong Crest" />

    <div class="ms-holoCards" id="msHoloCards">
      <div class="ms-holoScan"></div>
      <img class="ms-cardImg ms-imgA" id="msImgA" src="${holoPool[0]}" alt="Holo A" />
      <img class="ms-cardImg ms-imgB" id="msImgB" src="${holoPool[1]}" alt="Holo B" />
      <img class="ms-cardImg ms-imgC" id="msImgC" src="${holoPool[2]}" alt="Holo C" />
    </div>

    <div class="ms-holoCaption">Proof-of-learning. No wallet required.</div>
  `;

  // Create a grid: left = existing content, right = holo
  // We’ll wrap the *existing* first section/card-ish content if possible.
  const hero = document.createElement("section");
  hero.className = "ms-lessonHero";
  hero.innerHTML = `<div class="ms-lessonGrid"><div class="ms-lessonFX"></div></div>`;

  const grid = hero.querySelector(".ms-lessonGrid");
  const leftCol = document.createElement("div");

  // Move first meaningful chunk into left column (keeps page intact)
  // If your lesson pages start with an h1 + paragraphs, this will grab that “top” portion.
  const firstBlock =
    main.querySelector("section") ||
    main.querySelector(".ms-card") ||
    main;

  // If firstBlock IS main, don't move the whole main—just clone structure:
  if (firstBlock === main) {
    // Put a simple “lesson intro” card on the left
    const introCard = document.createElement("section");
    introCard.className = "ms-card";
    const h1 = main.querySelector("h1");
    const p  = main.querySelector("p");
    introCard.innerHTML = `
      ${h1 ? `<h1 class="ms-h1" style="margin-top:0;">${h1.textContent}</h1>` : `<h2 style="margin-top:0;">Lesson</h2>`}
      ${p  ? `<p class="ms-sub">${p.textContent}</p>` : `<p class="ms-sub">Welcome to the lesson.</p>`}
      <div class="ms-hr"></div>
      <p class="ms-note">Scroll for Hook → Hack → Practice → Exit Check.</p>
    `;
    leftCol.appendChild(introCard);
  } else {
    leftCol.appendChild(firstBlock);
  }

  grid.appendChild(leftCol);
  grid.appendChild(wrap);

  // Insert hero at the top of main
  main.prepend(hero);

  // --- Tilt interaction ---
  const stage = wrap.querySelector("#msHoloCards");
  const cards = stage ? stage.querySelectorAll(".ms-cardImg") : [];
  if (stage && cards.length) {
    stage.addEventListener("mousemove", (e) => {
      const r = stage.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - 0.5;
      const y = (e.clientY - r.top) / r.height - 0.5;

      cards.forEach((img, i) => {
        const depth = (i + 1) * 6;
        img.style.transform = `translate3d(${x * depth}px, ${y * depth}px, 0) rotate(${x * 2}deg)`;
      });
    });
    stage.addEventListener("mouseleave", () => {
      cards.forEach((img) => img.style.transform = "translate3d(0,0,0) rotate(0deg)");
    });
  }

  // --- Rotation crossfade ---
  const ROTATE_MS = 9000;
  const FADE_MS = 650;
  const imgA = wrap.querySelector("#msImgA");
  const imgB = wrap.querySelector("#msImgB");
  const imgC = wrap.querySelector("#msImgC");

  function crossfadeSwap(imgEl, nextSrc) {
    if (!imgEl) return;
    imgEl.classList.add("is-fading");
    window.setTimeout(() => {
      imgEl.src = nextSrc;
      requestAnimationFrame(() => imgEl.classList.remove("is-fading"));
    }, FADE_MS);
  }

  if (imgA && imgB && imgC) {
    let i = 0;
    const start = [imgA.src, imgB.src, imgC.src].filter(Boolean);
    const pool = [...new Set([...start, ...holoPool])];

    setInterval(() => {
      i = (i + 1) % pool.length;
      const layer = i % 3;
      const nextSrc = pool[(i + 2) % pool.length];

      if (layer === 0) crossfadeSwap(imgA, nextSrc);
      if (layer === 1) crossfadeSwap(imgB, nextSrc);
      if (layer === 2) crossfadeSwap(imgC, nextSrc);
    }, ROTATE_MS);
  }
})();
</script>
