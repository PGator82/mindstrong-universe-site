/* ===============================
   MINDSTRONG GAME ENGINE
   =============================== */

function getProfile(){
  return JSON.parse(localStorage.getItem("ms_profile") || "{}");
}

function saveProfile(p){
  localStorage.setItem("ms_profile", JSON.stringify(p));
}

function initProfile(){
  const p = getProfile();
  p.xp = p.xp || 0;
  p.level = p.level || 1;
  p.badges = p.badges || [];
  p.streak = p.streak || 1;
  saveProfile(p);
}

function addXP(amount){
  const p = getProfile();
  p.xp += amount;
  p.level = Math.floor(p.xp / 200) + 1;
  saveProfile(p);
}

function unlockBadge(name){
  const p = getProfile();
  if(!p.badges.includes(name)){
    p.badges.push(name);
    saveProfile(p);
  }
}

function updateStreak(){
  const p = getProfile();
  const today = new Date().toDateString();

  if(p.lastVisit === today) return;

  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate()-1);

  if(p.lastVisit === yesterday.toDateString()){
    p.streak += 1;
  } else {
    p.streak = 1;
  }

  p.lastVisit = today;
  saveProfile(p);
}

function renderProfilePanel(containerId){
  const p = getProfile();
  const el = document.getElementById(containerId);
  if(!el) return;

  el.innerHTML = `
    <div><strong>Level:</strong> ${p.level}</div>
    <div><strong>XP:</strong> ${p.xp}</div>
    <div><strong>Streak:</strong> ${p.streak} 🔥</div>
    <div><strong>Badges:</strong> ${(p.badges.join(", ") || "None")}</div>
  `;
}

initProfile();
updateStreak();
