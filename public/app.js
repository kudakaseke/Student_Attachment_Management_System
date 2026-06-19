const authCard = document.getElementById("authCard");
const dashboard = document.getElementById("dashboard");
const logoutBtn = document.getElementById("logoutBtn");
const roleSelect = document.getElementById("role");
const identifierLabel = document.getElementById("identifierLabel");
const identifierInput = document.getElementById("identifier");
const passwordWrap = document.getElementById("passwordWrap");
const el = (id) => document.getElementById(id);
const showLoginBtn = document.getElementById("showLoginBtn");
const showCreateBtn = document.getElementById("showCreateBtn");
const showForgotBtn = document.getElementById("showForgotBtn");
const showLoginFromCreate = document.getElementById("showLoginFromCreate");
const showLoginFromForgot = document.getElementById("showLoginFromForgot");
const authTitle = document.getElementById("authTitle");
const loginForm = el("loginForm");
const createForm = el("createForm");
const forgotForm = el("forgotForm");
const createRole = el("createRole");
const createIdentifierLabel = el("createIdentifierLabel");
const createIdentifier = el("createIdentifier");
const createProgramWrap = el("createProgramWrap");
const createProgram = el("createProgram");
const createPasswordWrap = el("createPasswordWrap");
const forgotRole = el("forgotRole");
const forgotIdentifierLabel = el("forgotIdentifierLabel");
const forgotIdentifier = el("forgotIdentifier");
const forgotPasswordWrap = el("forgotPasswordWrap");
const successModal = el("successModal");
const successModalMsg = el("successModalMsg");
const closeSuccessModal = el("closeSuccessModal");

const STUDENT_PAGE_KEYS = ["home", "notices", "placements", "documents", "results", "visits", "feedback"];
const LECTURER_PAGE_KEYS = ["home", "selected_students", "placements", "documents", "visits", "announcements", "analytics"];
const state = { user: null, studentPage: "home", studentRefresh: null, lecturerPage: "home", lecturerRefresh: null, zimHeatmapMap: null };
const REQUIRED_DOCUMENT_TYPES = [
  { key: "preliminary_report", label: "Preliminary Report" },
  { key: "final_report", label: "Final Report" },
  { key: "log_book", label: "Log Book" },
  { key: "system_documentation", label: "System Documentation" },
];

function normalizeStudentPage(page) {
  const value = String(page || "").trim().toLowerCase();
  return STUDENT_PAGE_KEYS.includes(value) ? value : "home";
}

function readStudentPageFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return normalizeStudentPage(params.get("student_page"));
}

function writeStudentPageToUrl(page) {
  const params = new URLSearchParams(window.location.search);
  params.set("student_page", normalizeStudentPage(page));
  const qs = params.toString();
  const nextUrl = `${window.location.pathname}${qs ? `?${qs}` : ""}${window.location.hash || ""}`;
  window.history.replaceState({}, "", nextUrl);
}

function setStudentPage(page) {
  const nextPage = normalizeStudentPage(page);
  state.studentPage = nextPage;
  writeStudentPageToUrl(nextPage);
  if (state.user?.role === "student" && typeof state.studentRefresh === "function") {
    state.studentRefresh();
  }
}

function normalizeLecturerPage(page) {
  const value = String(page || "").trim().toLowerCase();
  return LECTURER_PAGE_KEYS.includes(value) ? value : "home";
}

function readLecturerPageFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return normalizeLecturerPage(params.get("lecturer_page"));
}

function writeLecturerPageToUrl(page) {
  const params = new URLSearchParams(window.location.search);
  params.set("lecturer_page", normalizeLecturerPage(page));
  const qs = params.toString();
  const nextUrl = `${window.location.pathname}${qs ? `?${qs}` : ""}${window.location.hash || ""}`;
  window.history.replaceState({}, "", nextUrl);
}

function setLecturerPage(page) {
  const nextPage = normalizeLecturerPage(page);
  state.lecturerPage = nextPage;
  writeLecturerPageToUrl(nextPage);
  if (state.user?.role === "lecturer" && typeof state.lecturerRefresh === "function") {
    state.lecturerRefresh();
  }
}

function showStatus(message, ok = true, parent = authCard, durationMs = 3200) {
  const box = document.createElement("div");
  box.className = `status ${ok ? "ok" : "error"}`;
  box.textContent = message;
  parent.prepend(box);
  setTimeout(() => box.remove(), durationMs);
}

function openSuccessModal(message) {
  if (!successModal || !successModalMsg) return;
  successModalMsg.textContent = message;
  successModal.classList.remove("hidden");
}

function closeModal() {
  if (!successModal) return;
  successModal.classList.add("hidden");
}

async function api(path, options = {}) {
  const res = await fetch(path, options);
  const contentType = res.headers.get("content-type") || "";
  const data = contentType.includes("application/json")
    ? await res.json()
    : { ok: false, message: await res.text() };
  if (!res.ok || data.ok === false) throw new Error(data.message || "Request failed");
  return data;
}

roleSelect.addEventListener("change", () => {
  const role = roleSelect.value;
  if (role === "student") {
    identifierLabel.firstChild.textContent = "Registration Number";
    identifierInput.placeholder = "r218270v";
    passwordWrap.classList.remove("hidden");
    el("password").required = true;
  } else {
    identifierLabel.firstChild.textContent = "Email";
    identifierInput.placeholder = "name@company.com";
    passwordWrap.classList.remove("hidden");
    el("password").required = true;
  }
});

function showAuthForm(mode) {
  loginForm.classList.toggle("hidden", mode !== "login");
  createForm.classList.toggle("hidden", mode !== "create");
  forgotForm.classList.toggle("hidden", mode !== "forgot");
  if (authTitle) {
    authTitle.textContent = mode === "login" ? "Sign In" : mode === "create" ? "Create Account" : "Forgot Password";
  }
}

if (showLoginBtn) showLoginBtn.addEventListener("click", () => showAuthForm("login"));
if (showCreateBtn) showCreateBtn.addEventListener("click", () => showAuthForm("create"));
if (showForgotBtn) showForgotBtn.addEventListener("click", () => showAuthForm("forgot"));
if (showLoginFromCreate) showLoginFromCreate.addEventListener("click", () => showAuthForm("login"));
if (showLoginFromForgot) showLoginFromForgot.addEventListener("click", () => showAuthForm("login"));
if (closeSuccessModal) closeSuccessModal.addEventListener("click", closeModal);
if (successModal) {
  successModal.addEventListener("click", (e) => {
    if (e.target === successModal) closeModal();
  });
}
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeModal();
});

createRole.addEventListener("change", () => {
  const role = createRole.value;
  if (role === "student") {
    createIdentifierLabel.firstChild.textContent = "Registration Number";
    createIdentifier.placeholder = "r218270v";
    createIdentifier.type = "text";
    if (createProgramWrap) createProgramWrap.classList.remove("hidden");
    if (createProgram) createProgram.required = true;
    createPasswordWrap.classList.remove("hidden");
    el("createPassword").required = true;
  } else {
    createIdentifierLabel.firstChild.textContent = "Email";
    createIdentifier.placeholder = "name@msu.ac.zw";
    createIdentifier.type = "email";
    if (createProgramWrap) createProgramWrap.classList.add("hidden");
    if (createProgram) {
      createProgram.required = false;
      createProgram.value = "";
    }
    createPasswordWrap.classList.remove("hidden");
    el("createPassword").required = true;
  }
});

async function loadProgramOptions() {
  if (!createProgram) return;
  try {
    const data = await api("../api/auth.php?lookup=programs");
    const programs = Array.isArray(data.programs) ? data.programs : [];
    createProgram.innerHTML =
      '<option value="">Select Programme</option>' +
      programs.map((program) => `<option value="${Number(program.id || 0)}">${escapeHtml(program.name || "")}</option>`).join("");
  } catch (_err) {
    createProgram.innerHTML = '<option value="">Programmes unavailable</option>';
  }
}

forgotRole.addEventListener("change", () => {
  const role = forgotRole.value;
  if (role === "student") {
    forgotIdentifierLabel.firstChild.textContent = "Registration Number";
    forgotIdentifier.type = "text";
    forgotIdentifier.placeholder = "r218270v";
    forgotPasswordWrap.classList.remove("hidden");
    el("forgotPassword").required = true;
  } else {
    forgotIdentifierLabel.firstChild.textContent = "Email";
    forgotIdentifier.type = "email";
    forgotIdentifier.placeholder = "name@msu.ac.zw";
    forgotPasswordWrap.classList.remove("hidden");
    el("forgotPassword").required = true;
  }
});

loginForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const payload = {
      role: roleSelect.value,
      identifier: identifierInput.value.trim(),
      password: el("password").value,
    };
    const data = await api("../api/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    state.user = data.user;
    renderDashboard();
    showStatus(`Welcome ${data.user.first_name}`);
  } catch (err) {
    showStatus(err.message, false);
  }
});

createForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const payload = {
      action: "create_account",
      role: createRole.value,
      first_name: el("createFirstName").value.trim(),
      last_name: el("createLastName").value.trim(),
      identifier: createIdentifier.value.trim(),
      password: el("createPassword").value,
    };
    if (createRole.value === "student") {
      payload.program_id = createProgram ? String(createProgram.value || "").trim() : "";
    }
    const data = await api("../api/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    createForm.reset();
    createRole.dispatchEvent(new Event("change"));
    showAuthForm("login");
    openSuccessModal(data.message || "Account successfully created. You can now sign in.");
  } catch (err) {
    showStatus(err.message, false);
  }
});

forgotForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const payload = {
      action: "forgot_password",
      role: forgotRole.value,
      identifier: forgotIdentifier.value.trim(),
      new_password: el("forgotPassword").value,
    };
    const data = await api("../api/auth.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    forgotForm.reset();
    forgotRole.dispatchEvent(new Event("change"));
    showAuthForm("login");
    showStatus(data.message || "Password reset successful.");
  } catch (err) {
    showStatus(err.message, false);
  }
});

logoutBtn.addEventListener("click", async () => {
  await api("../api/auth.php", { method: "DELETE" });
  state.user = null;
  state.studentRefresh = null;
  state.studentPage = "home";
  state.lecturerRefresh = null;
  state.lecturerPage = "home";
  document.body.classList.remove("student-shell");
  document.body.classList.remove("lecturer-shell");
  const topbar = el("topbar");
  if (topbar) topbar.classList.add("hidden");
  dashboard.classList.add("hidden");
  authCard.classList.remove("hidden");
  logoutBtn.classList.add("hidden");
});

function drawTable(rows, formatters = {}, hiddenColumns = []) {
  if (!rows || rows.length === 0) return "<p>No data found.</p>";
  const hidden = new Set(hiddenColumns);
  const headers = Object.keys(rows[0]).filter((h) => !hidden.has(h));
  return `
    <table>
      <thead><tr>${headers.map((h) => `<th>${h}</th>`).join("")}</tr></thead>
      <tbody>${rows
        .map((r) => `<tr>${headers.map((h) => {
          const value = r[h] ?? "";
          const formatter = formatters[h];
          return `<td>${formatter ? formatter(value, r) : value}</td>`;
        }).join("")}</tr>`)
        .join("")}</tbody>
    </table>
  `;
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function formatPercentageValue(value) {
  const raw = value === null || value === undefined ? "" : String(value).trim();
  if (raw === "") return "Pending";
  const score = Number(raw);
  if (!Number.isFinite(score)) return escapeHtml(raw);
  return `${Math.round(score * 100) / 100}%`;
}

function renderNotices(notices) {
  if (!notices || notices.length === 0) {
    return '<p class="hint">No notices yet.</p>';
  }

  return `
    <div class="notice-list">
      ${notices.map((notice) => {
        const title = escapeHtml(notice.title || "Notice");
        const message = escapeHtml(notice.message || "");
        const dueDate = notice.due_date ? escapeHtml(notice.due_date) : null;
        const postedAt = notice.created_at ? escapeHtml(notice.created_at) : "";
        const postedBy = `${escapeHtml(notice.posted_by_first_name || "")} ${escapeHtml(notice.posted_by_last_name || "")}`.trim();
        const meta = [postedBy ? `By ${postedBy}` : "", postedAt ? `Posted ${postedAt}` : ""].filter(Boolean).join(" | ");

        return `
          <article class="notice-card">
            <div class="notice-head">
              <h5>${title}</h5>
              ${dueDate ? `<span class="notice-due">Due ${dueDate}</span>` : '<span class="notice-due none">No due date</span>'}
            </div>
            <p class="notice-message">${message}</p>
            ${meta ? `<p class="notice-meta">${meta}</p>` : ""}
          </article>
        `;
      }).join("")}
    </div>
  `;
}

const ZIM_CITY_POINTS = [
  { key: "Harare", aliases: ["harare"], lat: -17.8292, lng: 31.0522 },
  { key: "Bulawayo", aliases: ["bulawayo"], lat: -20.1489, lng: 28.5331 },
  { key: "Mutare", aliases: ["mutare"], lat: -18.9707, lng: 32.6709 },
  { key: "Gweru", aliases: ["gweru"], lat: -19.4550, lng: 29.8167 },
  { key: "Kwekwe", aliases: ["kwekwe"], lat: -18.9281, lng: 29.8149 },
  { key: "Kadoma", aliases: ["kadoma"], lat: -18.3333, lng: 29.9167 },
  { key: "Masvingo", aliases: ["masvingo"], lat: -20.0740, lng: 30.8327 },
  { key: "Chinhoyi", aliases: ["chinhoyi"], lat: -17.3667, lng: 30.2 },
  { key: "Marondera", aliases: ["marondera"], lat: -18.1853, lng: 31.5519 },
  { key: "Bindura", aliases: ["bindura"], lat: -17.3019, lng: 31.3306 },
  { key: "Hwange", aliases: ["hwange"], lat: -18.3647, lng: 26.4988 },
  { key: "Victoria Falls", aliases: ["victoria falls", "victoriafalls"], lat: -17.9243, lng: 25.8560 },
  { key: "Chiredzi", aliases: ["chiredzi"], lat: -21.0450, lng: 31.6667 },
  { key: "Zvishavane", aliases: ["zvishavane"], lat: -20.3267, lng: 30.0665 },
  { key: "Beitbridge", aliases: ["beitbridge"], lat: -22.2167, lng: 30.0 },
  { key: "Kariba", aliases: ["kariba"], lat: -16.5167, lng: 28.8 },
];

function computeZimCityCounts(placements) {
  const rows = Array.isArray(placements) ? placements : [];
  const realPlacements = rows.filter((row) => Number(row.id || 0) > 0);
  const cityCounts = {};
  ZIM_CITY_POINTS.forEach((city) => { cityCounts[city.key] = 0; });
  realPlacements.forEach((row) => {
    const rawCity = String(row.city || "").toLowerCase().trim();
    const rawAddress = String(row.company_address || "").toLowerCase();
    const searchText = rawCity !== "" ? rawCity : rawAddress;
    if (!searchText) return;
    const city = ZIM_CITY_POINTS.find((item) => item.aliases.some((alias) => searchText.includes(alias)));
    if (city) cityCounts[city.key] += 1;
  });
  return cityCounts;
}

function renderPlacementAnalytics(placements, cityCounts = null) {
  const rows = Array.isArray(placements) ? placements : [];
  const realPlacements = rows.filter((row) => Number(row.id || 0) > 0);
  const total = realPlacements.length;
  const successStatuses = new Set(["approved", "confirmed", "active"]);
  const successCount = realPlacements.filter((row) => successStatuses.has(String(row.status || "").toLowerCase())).length;
  const pendingCount = realPlacements.filter((row) => String(row.status || "").toLowerCase() === "pending").length;
  const rejectedCount = realPlacements.filter((row) => String(row.status || "").toLowerCase() === "rejected").length;
  const successRate = total > 0 ? Math.round((successCount / total) * 100) : 0;

  const statusRows = [
    { label: "Successful", value: successCount, color: "#18a15d" },
    { label: "Pending", value: pendingCount, color: "#e1a21a" },
    { label: "Rejected", value: rejectedCount, color: "#d14b4b" },
  ];
  const maxStatus = Math.max(1, ...statusRows.map((row) => row.value));
  const chartHeight = 160;
  const barWidth = 68;
  const startX = 58;
  const gap = 44;
  const statusBars = statusRows.map((row, idx) => {
    const barHeight = Math.round((row.value / maxStatus) * chartHeight);
    const x = startX + idx * (barWidth + gap);
    const y = 195 - barHeight;
    return `
      <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" rx="8" fill="${row.color}"></rect>
      <text x="${x + barWidth / 2}" y="${y - 8}" text-anchor="middle" class="analytics-svg-value">${row.value}</text>
      <text x="${x + barWidth / 2}" y="214" text-anchor="middle" class="analytics-svg-label">${escapeHtml(row.label)}</text>
    `;
  }).join("");
  const statusChartSvg = `
    <svg class="analytics-svg" viewBox="0 0 360 228" role="img" aria-label="Placement status bar chart">
      <line x1="36" y1="196" x2="334" y2="196" class="analytics-svg-axis"></line>
      <line x1="36" y1="28" x2="36" y2="196" class="analytics-svg-axis"></line>
      ${statusBars}
    </svg>
  `;

  const circleRadius = 52;
  const circumference = Math.round(2 * Math.PI * circleRadius);
  const progressLength = Math.round((successRate / 100) * circumference);
  const donutSvg = `
    <svg class="analytics-donut" viewBox="0 0 160 160" role="img" aria-label="Placement success rate donut chart">
      <circle cx="80" cy="80" r="${circleRadius}" class="donut-track"></circle>
      <circle cx="80" cy="80" r="${circleRadius}" class="donut-progress" style="stroke-dasharray:${progressLength} ${circumference}"></circle>
      <text x="80" y="86" text-anchor="middle" class="donut-text">${successRate}%</text>
    </svg>
  `;

  const resolvedCityCounts = cityCounts || computeZimCityCounts(realPlacements);
  const countedCities = ZIM_CITY_POINTS.filter((city) => resolvedCityCounts[city.key] > 0);
  const heatLegend = countedCities.length > 0
    ? countedCities
      .sort((a, b) => resolvedCityCounts[b.key] - resolvedCityCounts[a.key])
      .slice(0, 8)
      .map((city) => `<p><strong>${resolvedCityCounts[city.key]}</strong> ${escapeHtml(city.key)}</p>`)
      .join("")
    : '<p class="hint">No city matches found in placement addresses yet.</p>';

  return `
    <div class="analytics-grid">
      <article class="analytics-card">
        <h5>Placement Success Analytics</h5>
        <div class="analytics-stats">
          <p><strong>Total Placements:</strong> ${total}</p>
          <p><strong>Successful:</strong> ${successCount}</p>
          <p><strong>Pending:</strong> ${pendingCount}</p>
          <p><strong>Rejected:</strong> ${rejectedCount}</p>
          <p><strong>Success Rate:</strong> ${successRate}%</p>
        </div>
        <div class="analytics-graphs">
          <div class="analytics-graph-item">
            <h6>Status Graph</h6>
            ${statusChartSvg}
          </div>
          <div class="analytics-graph-item">
            <h6>Success Rate</h6>
            ${donutSvg}
          </div>
        </div>
      </article>
      <article class="analytics-card">
        <h5>Geographic Heatmaps</h5>
        <p class="analytics-subtitle">Zimbabwe city-by-city placement intensity</p>
        <div class="zim-map-wrap">
          <div id="zimHeatmapMap" class="zim-heatmap-map" aria-label="Zimbabwe heatmap map"></div>
        </div>
        <div class="heatmap-list">${heatLegend}</div>
      </article>
    </div>
  `;
}

function drawZimbabweHeatmapMap(cityCounts) {
  const mapHost = el("zimHeatmapMap");
  if (!mapHost) return;

  if (state.zimHeatmapMap) {
    state.zimHeatmapMap.remove();
    state.zimHeatmapMap = null;
  }

  if (!window.L || typeof window.L.map !== "function") {
    mapHost.innerHTML = '<p class="hint">Map tiles unavailable right now.</p>';
    return;
  }

  const map = window.L.map(mapHost, {
    zoomControl: true,
    scrollWheelZoom: false,
    attributionControl: true,
  }).setView([-19.0154, 29.1549], 6);
  state.zimHeatmapMap = map;

  window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 13,
    attribution: "&copy; OpenStreetMap contributors",
  }).addTo(map);

  const maxCount = Math.max(1, ...Object.values(cityCounts));
  const minCount = Math.min(...Object.values(cityCounts));
  const markerColor = (count) => {
    if (count <= 0) return "#86a3d8";
    const t = maxCount === minCount ? 1 : (count - minCount) / (maxCount - minCount);
    const r = Math.round(104 + t * (228 - 104));
    const g = Math.round(165 + t * (56 - 165));
    const b = Math.round(255 + t * (56 - 255));
    return `rgb(${r},${g},${b})`;
  };

  ZIM_CITY_POINTS.forEach((city) => {
    const count = Number(cityCounts[city.key] || 0);
    const radius = count > 0 ? 6 + Math.min(14, count * 2) : 4;
    window.L.circleMarker([city.lat, city.lng], {
      radius,
      color: "#ffffff",
      weight: 1.5,
      fillColor: markerColor(count),
      fillOpacity: count > 0 ? 0.92 : 0.55,
    })
      .addTo(map)
      .bindTooltip(`${city.key}: ${count} placement${count === 1 ? "" : "s"}`, { direction: "top" });
  });

  setTimeout(() => map.invalidateSize(), 0);
}

function mountStudentSidebarNav() {
  const topbar = el("topbar");
  if (!topbar) return;

  const existing = topbar.querySelector(".student-side-nav");
  if (existing) existing.remove();

  if (state.user?.role !== "student") return;

  const nav = document.createElement("nav");
  nav.className = "student-side-nav";
  nav.innerHTML = `
    <button class="student-side-nav-btn" type="button" data-page="home">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10.5L12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/></svg></span>
      <span>Home</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="notices">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M15 18H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 8l9 6 9-6"/><path d="M17 14h4"/><path d="M19 12v4"/></svg></span>
      <span>Notices</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="placements">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg></span>
      <span>Placements</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="documents">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/></svg></span>
      <span>Documents</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="results">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20h16"/><path d="M7 20V9"/><path d="M12 20V5"/><path d="M17 20V13"/></svg></span>
      <span>Results</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="visits">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M11 19a5 5 0 0 1 10 0"/></svg></span>
      <span>Visit Reports</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-page="feedback">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v10H9l-5 4z"/><path d="M8 9h8"/><path d="M8 12h5"/></svg></span>
      <span>Feedback</span>
    </button>
  `;

  nav.addEventListener("click", (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const button = target.closest(".student-side-nav-btn");
    if (!button) return;
    const page = button.getAttribute("data-page");
    if (!page) return;
    setStudentPage(page);
  });

  topbar.appendChild(nav);
}

function mountLecturerSidebarNav() {
  const topbar = el("topbar");
  if (!topbar) return;

  const existing = topbar.querySelector(".student-side-nav");
  if (existing) existing.remove();

  if (state.user?.role !== "lecturer") return;

  const nav = document.createElement("nav");
  nav.className = "student-side-nav";
  nav.innerHTML = `
    <button class="student-side-nav-btn" type="button" data-lecturer-page="home">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10.5L12 3l9 7.5"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/></svg></span>
      <span>Home</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="selected_students">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M11 19a5 5 0 0 1 10 0"/></svg></span>
      <span>Students Who Selected You</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="placements">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg></span>
      <span>Placement</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="documents">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/></svg></span>
      <span>Documents</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="visits">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3v3"/><path d="M17 3v3"/><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16"/><path d="M8 14h4"/><path d="M8 18h7"/></svg></span>
      <span>Visit Reports</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="announcements">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3a5 5 0 0 0-5 5v2.8L5 14v1h14v-1l-2-3.2V8a5 5 0 0 0-5-5z"/><path d="M10 18a2 2 0 0 0 4 0"/></svg></span>
      <span>My Announcements</span>
    </button>
    <button class="student-side-nav-btn" type="button" data-lecturer-page="analytics">
      <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20h16"/><path d="M7 20V11"/><path d="M12 20V7"/><path d="M17 20V14"/></svg></span>
      <span>Analytics</span>
    </button>
  `;

  nav.addEventListener("click", (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const button = target.closest(".student-side-nav-btn");
    if (!button) return;
    const page = button.getAttribute("data-lecturer-page");
    if (!page) return;
    setLecturerPage(page);
  });

  topbar.appendChild(nav);
}

function renderDashboard() {
  const topbar = el("topbar");
  document.body.classList.toggle("student-shell", state.user?.role === "student");
  document.body.classList.toggle("lecturer-shell", state.user?.role === "lecturer");
  if (state.user?.role === "student") {
    state.studentPage = readStudentPageFromUrl();
    state.lecturerRefresh = null;
  } else if (state.user?.role === "lecturer") {
    state.lecturerPage = readLecturerPageFromUrl();
    state.studentRefresh = null;
  } else {
    state.studentPage = "home";
    state.studentRefresh = null;
    state.lecturerPage = "home";
    state.lecturerRefresh = null;
  }
  if (topbar) topbar.classList.remove("hidden");
  authCard.classList.add("hidden");
  dashboard.classList.remove("hidden");
  logoutBtn.classList.remove("hidden");
  dashboard.setAttribute("data-role", state.user?.role || "");
  dashboard.innerHTML = "";
  const tplId = `${state.user.role}Tpl`;
  const tpl = el(tplId);
  dashboard.appendChild(tpl.content.cloneNode(true));
  if (state.user?.role === "student") {
    mountStudentSidebarNav();
  } else if (state.user?.role === "lecturer") {
    mountLecturerSidebarNav();
  }
  bindRoleActions();
}

async function bindRoleActions() {
  const role = state.user.role;
  if (role === "student") return bindStudent();
  if (role === "lecturer") return bindLecturer();
  if (role === "supervisor") return bindSupervisor();
  if (role === "admin") return bindAdmin();
}

async function bindStudent() {
  const profile = el("studentProfile");
  const studentData = el("studentData");
  const activeLecturerSelect = el("activeLecturerSelect");
  const docIdPreview = el("docIdPreview");
  const profileCard = profile ? profile.closest(".card") : null;
  const formsGrid = profileCard ? profileCard.nextElementSibling : null;
  const statusCard = studentData ? studentData.closest(".card") : null;
  const placementForm = el("placementForm");
  const placementCard = placementForm ? placementForm.closest(".card") : null;
  const activeLecturerForm = el("activeLecturerForm");
  const activeLecturerTitle = activeLecturerForm ? activeLecturerForm.previousElementSibling : null;
  const docForm = el("docForm");
  const docFormTitle = docForm ? docForm.previousElementSibling : null;
  if (profileCard) profileCard.id = "student-home-anchor";

  const pageTitles = {
    home: "My Placement Status & Feedback",
    notices: "Notices",
    placements: "Placements",
    documents: "Documents",
    results: "Results",
    visits: "Visit Reports",
    feedback: "Feedback",
  };

  const applyStudentPageLayout = () => {
    const currentPage = normalizeStudentPage(state.studentPage);
    const isHome = currentPage === "home";
    const isPlacements = currentPage === "placements";
    const isDocuments = currentPage === "documents";
    const showForms = isPlacements || isDocuments;
    if (profileCard) profileCard.classList.toggle("hidden", !isHome);
    if (formsGrid instanceof HTMLElement) formsGrid.classList.toggle("hidden", !showForms);

    if (placementCard) {
      placementCard.classList.toggle("hidden", !isPlacements);
    }
    if (activeLecturerTitle instanceof HTMLElement) {
      activeLecturerTitle.classList.toggle("hidden", !isPlacements);
    }
    if (activeLecturerForm) {
      activeLecturerForm.classList.toggle("hidden", !isPlacements);
    }
    if (docFormTitle instanceof HTMLElement) {
      docFormTitle.classList.toggle("hidden", !isDocuments);
    }
    if (docForm) {
      docForm.classList.toggle("hidden", !isDocuments);
    }

    if (statusCard) {
      const title = statusCard.querySelector("h3");
      if (title) title.textContent = pageTitles[currentPage] || pageTitles.home;
    }
    const navButtons = document.querySelectorAll(".student-side-nav-btn");
    navButtons.forEach((btn) => {
      if (!(btn instanceof HTMLElement)) return;
      const isActive = btn.getAttribute("data-page") === currentPage;
      btn.classList.toggle("active", isActive);
      btn.setAttribute("aria-current", isActive ? "page" : "false");
    });
  };

  if (profile && state.user) {
    profile.innerHTML =
      `<p><strong>Name:</strong> ${state.user.first_name ?? ""}</p>` +
      `<p><strong>Surname:</strong> ${state.user.last_name ?? ""}</p>` +
      `<p><strong>Reg Number:</strong> ${state.user.reg_number ?? "N/A"}</p>` +
      `<p><strong>Programme:</strong> ${state.user.program ?? "N/A"}</p>`;
  }

  const refresh = async () => {
    const data = await api("../api/student.php");
    const noticeItems = Array.isArray(data.notices)
      ? data.notices
      : (Array.isArray(data.announcements) ? data.announcements : []);
    if (activeLecturerSelect) {
      const lecturers = Array.isArray(data.lecturers) ? data.lecturers : [];
      const currentActiveId = Number((data.active_lecturer && data.active_lecturer.active_lecturer_id) || 0);
      activeLecturerSelect.innerHTML =
        '<option value="">Select Active Lecturer</option>' +
        lecturers
          .map((lecturer) => {
            const lecturerId = Number(lecturer.id || 0);
            const fullName = `${lecturer.first_name || ""} ${lecturer.last_name || ""}`.trim() || "Unnamed Lecturer";
            const email = lecturer.email ? ` (${lecturer.email})` : "";
            const selected = lecturerId === currentActiveId ? " selected" : "";
            return `<option value="${lecturerId}"${selected}>${escapeHtml(fullName + email)}</option>`;
          })
          .join("");
    }
    const documentRows = (data.documents || []).map((row) => ({
      document_id: Number(row.id || 0) || "",
      ...row,
      actions: "",
    }));
    if (docIdPreview) {
      const maxDocumentId = documentRows.reduce((max, row) => {
        const id = Number(row.document_id || 0);
        return id > max ? id : max;
      }, 0);
      docIdPreview.value = String(maxDocumentId + 1);
    }
    const documentFormatters = {
      actions: (_, row) => {
        const rawScore = row?.grade_score;
        const isGraded = rawScore !== null && rawScore !== undefined && String(rawScore).trim() !== "";
        if (isGraded) return "Locked (graded)";
        return `<button class="btn secondary delete-doc-btn" type="button" data-document-id="${row.id}">Delete</button>`;
      },
      grade_score: (value) => formatPercentageValue(value),
    };
    const documentsByType = new Map();
    documentRows.forEach((row) => {
      const typeKey = String(row.document_type || "").trim().toLowerCase();
      if (!typeKey || documentsByType.has(typeKey)) return;
      documentsByType.set(typeKey, row);
    });
    const resultRows = REQUIRED_DOCUMENT_TYPES.map((type) => {
      const row = documentsByType.get(type.key);
      const rawScore = row && row.grade_score !== null && row.grade_score !== undefined && row.grade_score !== ""
        ? Number(row.grade_score)
        : NaN;
      const hasScore = Number.isFinite(rawScore);
      return {
        document_type: type.label,
        percentage: hasScore ? formatPercentageValue(rawScore) : (row ? "Pending" : "Not Submitted"),
        status: hasScore ? "Graded" : (row ? "Awaiting Grading" : "Missing"),
      };
    });
    const placementFormatters = {
      status: (value) => {
        const raw = String(value || "").trim();
        const key = raw.toLowerCase();
        const label = key === "not_submitted" ? "Not Submitted" : (raw || "N/A");
        return `<span class="placement-status placement-status-${escapeHtml(key || "unknown")}">${escapeHtml(label)}</span>`;
      },
    };
    const renderStudentPlacementTable = () =>
      `<div class="student-table-wrap placement-table-wrap">${drawTable(data.placements, placementFormatters, ["id", "placement_id", "lecturer_id"])}</div>`;
    const renderStudentTable = (rows, formatters = {}, hiddenColumns = []) =>
      `<div class="student-table-wrap">${drawTable(rows, formatters, hiddenColumns)}</div>`;
    const pages = {
      home:
        "<section id='student-notices-block'><h4>Notices</h4>" +
        renderNotices(noticeItems) +
        "</section>" +
        "<section id='student-placements-block'><h4>Placements</h4>" +
        renderStudentPlacementTable() +
        "</section>" +
        "<section id='student-documents-block'><h4>Documents</h4>" +
        renderStudentTable(documentRows, documentFormatters, ["id", "placement_id"]) +
        "</section>" +
        "<section id='student-visits-block'><h4>Visit Reports</h4>" +
        renderStudentTable(data.visit_reports || [], {}, ["placement_id"]) +
        "</section>" +
        "<section id='student-feedback-block'><h4>Feedback</h4>" +
        renderStudentTable(data.feedback, {}, ["placement_id"]) +
        "</section>",
      notices:
        "<section id='student-notices-block'><h4>Notices</h4>" +
        renderNotices(noticeItems) +
        "</section>",
      placements:
        "<section id='student-placements-block'><h4>Placements</h4>" +
        renderStudentPlacementTable() +
        "</section>",
      documents:
        "<section id='student-documents-block'><h4>Documents</h4>" +
        renderStudentTable(documentRows, documentFormatters, ["id", "placement_id"]) +
        "</section>",
      results:
        "<section id='student-results-block'><h4>Results</h4>" +
        renderStudentTable(resultRows) +
        "</section>",
      visits:
        "<section id='student-visits-block'><h4>Visit Reports</h4>" +
        renderStudentTable(data.visit_reports || [], {}, ["placement_id"]) +
        "</section>",
      feedback:
        "<section id='student-feedback-block'><h4>Feedback</h4>" +
        renderStudentTable(data.feedback, {}, ["placement_id"]) +
        "</section>",
    };
    const currentPage = normalizeStudentPage(state.studentPage);
    studentData.innerHTML = pages[currentPage] || pages.home;
    applyStudentPageLayout();
  };

  studentData.addEventListener("click", async (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const btn = target.closest(".delete-doc-btn");
    if (!btn) return;

    const documentId = Number(btn.getAttribute("data-document-id") || 0);
    if (documentId < 1) {
      return;
    }

    if (!window.confirm("Delete this document? This action cannot be undone.")) {
      return;
    }

    const fd = new FormData();
    fd.append("action", "delete_document");
    fd.append("document_id", String(documentId));

    try {
      const result = await api("../api/student.php", { method: "POST", body: fd });
      showStatus(result.message || "Document deleted successfully", true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("placementForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const selectedLecturerId = activeLecturerSelect ? String(activeLecturerSelect.value || "").trim() : "";
    if (selectedLecturerId !== "") {
      fd.append("lecturer_id", selectedLecturerId);
    }
    fd.append("action", "placement");
    try {
      const result = await api("../api/student.php", { method: "POST", body: fd });
      e.target.reset();
      const message = result.message || "Placement submitted successfully.";
      showStatus(message, true, dashboard);
      openSuccessModal(message);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("activeLecturerForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append("action", "set_active_lecturer");
    try {
      const result = await api("../api/student.php", { method: "POST", body: fd });
      const message = result.message || "Active lecturer selected successfully.";
      showStatus(message, true, dashboard);
      openSuccessModal(message);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("docForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append("action", "document");
    try {
      const result = await api("../api/student.php", { method: "POST", body: fd });
      e.target.reset();
      const message = result.message || "Document uploaded successfully.";
      showStatus(message, true, dashboard);
      openSuccessModal(message);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  state.studentRefresh = refresh;
  applyStudentPageLayout();
  refresh();
}

async function bindLecturer() {
  const profile = el("lecturerProfile");
  const lecturerAnnouncements = el("lecturerAnnouncements");
  const lecturerData = el("lecturerData");
  const profileCard = profile ? profile.closest(".card") : null;
  const formsGrid = profileCard ? profileCard.nextElementSibling : null;
  const reviewCard = el("reviewForm")?.closest(".card") || null;
  const visitCard = el("visitForm")?.closest(".card") || null;
  const docCard = el("docGradeForm")?.closest(".card") || null;
  const announcementFormCard = el("announcementForm")?.closest(".card") || null;
  const lecturerDataCard = lecturerData ? lecturerData.closest(".card") : null;
  const lecturerAnnouncementsCard = lecturerAnnouncements ? lecturerAnnouncements.closest(".card") : null;
  const pageTitles = {
    home: "Lecturer Dashboard",
    selected_students: "Students Who Selected You",
    placements: "Placement",
    documents: "Documents",
    visits: "Visit Reports",
    announcements: "My Announcements",
    analytics: "Analytics",
  };

  const applyLecturerPageLayout = () => {
    const currentPage = normalizeLecturerPage(state.lecturerPage);
    const isHome = currentPage === "home";
    const showMap = {
      home: [false, false, false, false],
      selected_students: [false, false, false, false],
      placements: [true, false, false, false],
      documents: [false, false, true, false],
      visits: [false, true, false, false],
      announcements: [false, false, false, true],
      analytics: [false, false, false, false],
    };
    const [showReview, showVisit, showDoc, showAnnouncementForm] = showMap[currentPage] || showMap.selected_students;
    if (profileCard) profileCard.classList.toggle("hidden", !isHome);
    if (formsGrid instanceof HTMLElement) {
      const hasForm = showReview || showVisit || showDoc || showAnnouncementForm;
      formsGrid.classList.toggle("hidden", !hasForm);
    }
    if (reviewCard) reviewCard.classList.toggle("hidden", !showReview);
    if (visitCard) visitCard.classList.toggle("hidden", !showVisit);
    if (docCard) docCard.classList.toggle("hidden", !showDoc);
    if (announcementFormCard) announcementFormCard.classList.toggle("hidden", !showAnnouncementForm);
    if (lecturerDataCard) {
      const title = lecturerDataCard.querySelector("h3");
      if (title) title.textContent = pageTitles[currentPage] || pageTitles.selected_students;
      lecturerDataCard.classList.toggle("hidden", currentPage === "announcements");
    }
    if (lecturerAnnouncementsCard) {
      lecturerAnnouncementsCard.classList.toggle("hidden", currentPage !== "announcements");
    }
    const navButtons = document.querySelectorAll(".student-side-nav-btn");
    navButtons.forEach((btn) => {
      if (!(btn instanceof HTMLElement)) return;
      const isActive = btn.getAttribute("data-lecturer-page") === currentPage;
      btn.classList.toggle("active", isActive);
      btn.setAttribute("aria-current", isActive ? "page" : "false");
    });
  };

  if (profile && state.user) {
    profile.innerHTML =
      `<p><strong>Name:</strong> ${state.user.first_name ?? ""}</p>` +
      `<p><strong>Surname:</strong> ${state.user.last_name ?? ""}</p>` +
      `<p><strong>Email:</strong> ${state.user.email ?? "N/A"}</p>`;
  }

  const refresh = async () => {
    const data = await api("../api/lecturer.php");
    const selectedStudentRows = (data.assigned_students || []).map((row) => ({
      reg_number: row.reg_number || "",
      name: row.first_name || "",
      surname: row.last_name || "",
      status: row.latest_placement_status || "not_submitted",
      company_name: row.latest_company_name || "",
    }));
    const placements = Array.isArray(data.placements) ? [...data.placements] : [];
    const assignedStudents = Array.isArray(data.assigned_students) ? data.assigned_students : [];
    const placementStudentIds = new Set(
      placements
        .map((row) => Number(row.student_id || 0))
        .filter((id) => id > 0)
    );
    const unsubmittedRows = assignedStudents
      .filter((student) => {
        const studentId = Number(student.id || 0);
        return studentId > 0 && !placementStudentIds.has(studentId) && !student.latest_placement_id;
      })
      .map((student) => ({
        id: "N/A",
        student_id: Number(student.id || 0),
        status: "not_submitted",
        city: "",
        company_name: "Not submitted yet",
        company_address: "",
        start_date: "",
        end_date: "",
        created_at: "",
        lecturer_id: state.user?.id ?? "",
        reg_number: student.reg_number || "",
        first_name: student.first_name || "",
        last_name: student.last_name || "",
        department: student.department || "",
        lecturer_first_name: state.user?.first_name || "",
        lecturer_last_name: state.user?.last_name || "",
      }));
    const placementRows = [...placements, ...unsubmittedRows];
    const placementDisplayRows = placementRows.map((row) => ({
      reg_number: row.reg_number || "",
      name: row.first_name || "",
      surname: row.last_name || "",
      status: row.status || "",
      city: row.city || "",
      company_name: row.company_name || "",
      company_address: row.company_address || "",
      start_date: row.start_date || "",
      end_date: row.end_date || "",
      created_at: row.created_at || "",
      department: row.placement_department || row.department || "",
      id: row.id ?? "",
      student_id: row.student_id ?? "",
      lecturer_id: row.lecturer_id ?? "",
    }));

    const lecturerFormatters = {
      status: (value) => {
        const raw = String(value || "").trim();
        const key = raw.toLowerCase();
        const label = key === "not_submitted" ? "Not Submitted" : (raw || "N/A");
        return `<span class="placement-status placement-status-${escapeHtml(key || "unknown")}">${escapeHtml(label)}</span>`;
      },
      company_address: (value) => {
        const address = String(value || "").trim();
        if (!address) return "";
        const mapUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
        return `${address} <a href="${mapUrl}" target="_blank" rel="noopener noreferrer">Open in Maps</a>`;
      },
    };
    const lecturerDocFormatters = {
      file_name: (value, row) => {
        const name = String(value || "").trim();
        const stored = String(row.file_path || "").trim();
        if (!name) return "";
        if (!stored) return name;
        const docUrl = `../uploads/${encodeURIComponent(stored)}`;
        return `<a href="${docUrl}" target="_blank" rel="noopener noreferrer">${name}</a>`;
      },
      grade_score: (value) => formatPercentageValue(value),
    };
    const currentPage = normalizeLecturerPage(state.lecturerPage);
    const cityCounts = computeZimCityCounts(placements);
    const renderLecturerPlacementTable = () =>
      `<div class="placement-table-wrap">${drawTable(placementDisplayRows, lecturerFormatters, ["id", "student_id", "lecturer_id"])}</div>`;
    const pages = {
      home:
        "<h4>Students Who Selected You</h4>" + drawTable(selectedStudentRows) +
        "<h4>Placement</h4>" + renderLecturerPlacementTable() +
        "<h4>Documents</h4>" + drawTable(data.documents, lecturerDocFormatters, ["file_path", "placement_id"]) +
        "<h4>Visit Reports</h4>" + drawTable(data.visits, {}, ["placement_id"]) +
        "<h4>My Announcements</h4>" + drawTable(data.announcements || []),
      selected_students:
        "<h4>Students Who Selected You</h4>" + drawTable(selectedStudentRows),
      placements:
        "<h4>Placement</h4>" + renderLecturerPlacementTable(),
      documents:
        "<h4>Documents</h4>" + drawTable(data.documents, lecturerDocFormatters, ["file_path", "placement_id"]),
      visits:
        "<h4>Visit Reports</h4>" + drawTable(data.visits, {}, ["placement_id"]),
      announcements: "",
      analytics:
        "<h4>Placement Success Analytics and Geographic Heatmaps</h4>" + renderPlacementAnalytics(placements, cityCounts),
    };
    if (lecturerData) {
      lecturerData.innerHTML = pages[currentPage] || pages.selected_students;
    }
    if (lecturerAnnouncements) {
      lecturerAnnouncements.innerHTML = drawTable(data.announcements || []);
    }
    if (currentPage === "analytics") {
      drawZimbabweHeatmapMap(cityCounts);
    } else if (state.zimHeatmapMap) {
      state.zimHeatmapMap.remove();
      state.zimHeatmapMap = null;
    }
    applyLecturerPageLayout();
  };

  el("reviewForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "review_placement";
    try {
      const result = await api("../api/lecturer.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const normalizedStatus = String(payload.status || "").toLowerCase();
      const actionMessage = normalizedStatus === "approved"
        ? "Placement approved"
        : normalizedStatus === "rejected"
          ? "Placement rejected"
          : "Placement updated";
      showStatus(result.message || actionMessage, true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("visitForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "visit_report";
    try {
      await api("../api/lecturer.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      showStatus("Visit report saved", true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("docGradeForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "grade_document";
    try {
      await api("../api/lecturer.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      e.target.reset();
      showStatus("Document graded successfully", true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("announcementForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "create_announcement";
    try {
      const result = await api("../api/lecturer.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      e.target.reset();
      showStatus(result.message || "Announcement created successfully", true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  state.lecturerRefresh = refresh;
  applyLecturerPageLayout();
  refresh();
}

async function bindSupervisor() {
  const refresh = async () => {
    const data = await api("../api/supervisor.php");
    el("supervisorData").innerHTML = drawTable(data.placements);
  };

  el("confirmForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "confirm_placement";
    try {
      await api("../api/supervisor.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      showStatus("Placement confirmed", true, dashboard);
      refresh();
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  el("feedbackForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(new FormData(e.target).entries());
    payload.action = "feedback";
    try {
      await api("../api/supervisor.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      showStatus("Feedback submitted", true, dashboard);
    } catch (err) {
      showStatus(err.message, false, dashboard);
    }
  });

  refresh();
}

async function bindAdmin() {
  const formatRoleTitle = (role) => {
    const value = String(role || "").trim().toLowerCase();
    if (!value) return "Unassigned";
    return value.charAt(0).toUpperCase() + value.slice(1);
  };

  const renderUsersByRole = (userRows) => {
    const users = Array.isArray(userRows) ? userRows : [];
    if (users.length === 0) return "<p>No data found.</p>";

    const preferredOrder = ["student", "lecturer", "supervisor", "admin"];
    const groups = new Map();

    users.forEach((user) => {
      const roleKey = String(user?.role || "").trim().toLowerCase() || "unassigned";
      if (!groups.has(roleKey)) groups.set(roleKey, []);
      groups.get(roleKey).push(user);
    });

    const knownRoles = preferredOrder.filter((role) => groups.has(role));
    const otherRoles = Array.from(groups.keys())
      .filter((role) => !preferredOrder.includes(role))
      .sort((a, b) => a.localeCompare(b));
    const orderedRoles = [...knownRoles, ...otherRoles];

    const hiddenColumnsByRole = {
      student: ["email", "is_active", "department", "company_phone"],
      lecturer: ["is_active", "reg_number", "department", "company", "company_phone"],
      supervisor: ["is_active", "reg_number", "department"],
      admin: ["company_phone"],
      unassigned: ["company_phone"],
    };
    const resetAllowedRoles = new Set(["student", "lecturer", "supervisor"]);

    return orderedRoles
      .map((role) => {
        const hidden = [...(hiddenColumnsByRole[role] || ["company_phone"]), "id"];
        const rows = Array.isArray(groups.get(role)) ? groups.get(role) : [];
        let displayRows = role === "admin"
          ? rows.map((row) => ({ ...row, company: "MSU" }))
          : rows;
        if (role === "admin") {
          hidden.push("reg_number", "department");
        }
        let formatters = {};
        if (resetAllowedRoles.has(role)) {
          displayRows = displayRows.map((row) => ({ ...row, reset_password: "" }));
          formatters = {
            reset_password: (_value, row) => {
              const id = Number(row?.id || 0);
              if (id < 1) return "";
              return `<button class="btn secondary" type="button" data-reset-user-id="${id}">Reset Password</button>`;
            },
          };
        }
        return `<h5>${escapeHtml(formatRoleTitle(role))}</h5>${drawTable(displayRows, formatters, hidden)}`;
      })
      .join("");
  };

  const loadDashboard = async () => {
    const [summaryRes, usersRes, companiesRes] = await Promise.allSettled([
      api("../api/admin.php?entity=dashboard"),
      api("../api/admin.php?entity=users"),
      api("../api/admin.php?entity=companies"),
    ]);

    const summary = summaryRes.status === "fulfilled" ? summaryRes.value : { summary: {} };
    const users = usersRes.status === "fulfilled" ? usersRes.value : { users: [] };
    const companies = companiesRes.status === "fulfilled" ? companiesRes.value : { companies: [] };

    const adminDataEl = el("adminData");
    if (!adminDataEl) return;
    adminDataEl.innerHTML =
      "<h4>Summary</h4>" +
      drawTable([summary.summary || {}]) +
      "<h4>Companies</h4>" + drawTable(companies.companies || []) +
      "<h4>Users</h4>" + renderUsersByRole(users.users || []);

    const failed = [summaryRes, usersRes, companiesRes]
      .filter((result) => result.status === "rejected")
      .length;
    if (failed > 0) {
      showStatus(`Admin dashboard loaded with ${failed} section(s) unavailable.`, false, dashboard);
    }
  };

  const postEntity = async (form, entity) => {
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.entity = entity;
    await api("../api/admin.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
  };

  const deptForm = el("deptForm");
  if (deptForm) {
    deptForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await postEntity(e.target, "department");
        showStatus("Department created", true, dashboard);
        e.target.reset();
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  const programForm = el("programForm");
  if (programForm) {
    programForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await postEntity(e.target, "program");
        showStatus("Program created", true, dashboard);
        e.target.reset();
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  const companyForm = el("companyForm");
  if (companyForm) {
    companyForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await postEntity(e.target, "company");
        showStatus("Company created", true, dashboard);
        e.target.reset();
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  const userForm = el("userForm");
  if (userForm) {
    userForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await postEntity(e.target, "user");
        showStatus("User created", true, dashboard);
        e.target.reset();
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  const adminPlacementForm = el("adminPlacementForm");
  if (adminPlacementForm) {
    adminPlacementForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await postEntity(e.target, "placement");
        showStatus("Student placement added", true, dashboard);
        e.target.reset();
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  document.querySelectorAll("[data-export]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const type = btn.getAttribute("data-export");
      window.open(`../api/report-export.php?type=${encodeURIComponent(type)}`, "_blank");
    });
  });

  const adminData = el("adminData");
  if (adminData) {
    adminData.addEventListener("click", async (e) => {
      const target = e.target;
      if (!(target instanceof Element)) return;
      const btn = target.closest("[data-reset-user-id]");
      if (!btn) return;
      const userId = Number(btn.getAttribute("data-reset-user-id") || 0);
      if (userId < 1) return;
      const proceed = window.confirm("Reset password for this user?");
      if (!proceed) return;
      try {
        const result = await api("../api/admin.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ entity: "reset_password", user_id: userId }),
        });
        const tempPassword = String(result.temporary_password || "").trim();
        const msg = result.message || "Password reset successful";
        showStatus(msg, true, dashboard);
        if (tempPassword) {
          openSuccessModal(`${msg}. Temporary password: ${tempPassword}`);
        }
        await loadDashboard();
      } catch (err) {
        showStatus(err.message, false, dashboard);
      }
    });
  }

  loadDashboard().catch((err) => {
    showStatus(err.message || "Failed to load admin dashboard", false, dashboard);
  });
}

async function bootstrap() {
  roleSelect.dispatchEvent(new Event("change"));
  createRole.dispatchEvent(new Event("change"));
  forgotRole.dispatchEvent(new Event("change"));
  await loadProgramOptions();
  showAuthForm("login");
  try {
    const auth = await api("../api/auth.php");
    if (auth.authenticated) {
      state.user = auth.user;
      renderDashboard();
    }
  } catch (e) {
    // No active session, ignore.
  }
}

bootstrap();
