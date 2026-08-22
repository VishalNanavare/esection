# own_theme Static Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `own_theme/` as a complete, 146-page, fully static (no PHP,
no templating, no build step) HTML/CSS/JS admin theme: 33 pages mirroring
esection's real business screens, plus a 113-page generic UI-kit catalog
(dashboards, app patterns, components, forms, tables, charts, layouts, misc
pages) sized against a commercial reference theme for category breadth only.

**Architecture:** Two canonical "shell" pages (one app-shell, one auth-shell)
are hand-built first and become the copy-paste template for every other
page. Every page is a fully self-contained `.html` file — no includes, no
namespacing tricks. Shared assets (Bootstrap 5.1.2, Font Awesome 4,
`esection-theme.css`/`esection-shell.css`, jQuery, Select2, Flatpickr,
SweetAlert2) are copied from `public/assets`; six new libraries (Chart.js,
DataTables, Quill, FullCalendar, SortableJS, Leaflet) are vendored
offline into `own_theme/assets/vendor/`.

**Tech Stack:** Bootstrap 5.1.2, Font Awesome 4, jQuery 3.x, Select2,
Flatpickr, SweetAlert2 (all existing), + Chart.js, DataTables, Quill,
FullCalendar (core), SortableJS, Leaflet (new, MIT/BSD licensed).

## Global Constraints

- Every page in `own_theme/` is plain `.html` — zero PHP, zero `<?php ?>`,
  zero `$this->extend()`/`$this->include()`. Confirmed per-page by the
  verification recipe below.
- Nothing is copied from `D:\webserver\www\sample_theme\` — no HTML, CSS,
  JS, or image files, no distinctive class-naming scheme, no custom icon
  set. It is reference-for-category-breadth only.
- Only these six new third-party libraries are added, each MIT or BSD
  licensed: Chart.js, DataTables (+ Bootstrap 5 styling), Quill,
  FullCalendar (core), SortableJS, Leaflet. No other new dependency without
  updating the spec first.
- Leaflet's map tiles stream from a public OpenStreetMap server at
  view-time — the one accepted exception to "fully offline." Every other
  page and every other library is 100% local.
- Visual design must stay consistent with esection's existing indigo/emerald
  "glass" design system (`esection-theme.css` / `esection-shell.css`) —
  extended, never replaced, for new component types.
- Every page must open standalone via `file://` or a static file server
  with zero console errors and zero broken local asset links.
- `public/assets` (the live app's copy) is never modified by this work —
  only `own_theme/assets` (a separate copy) is touched.

---

## Per-Page Verification Recipe

Every task from Phase 2 onward ends with this exact three-step check
(referenced as "**Apply the Verification Recipe**" — not repeated in full
in every task):

1. **No PHP/template leftovers:**
   ```bash
   grep -c '<?php\|\$this->extend\|\$this->include\|<?=' "own_theme/pages/<path>/<file>.html"
   ```
   Expected: `0`

2. **All local asset references resolve** (run from the repo root; replace
   `<file>` with the page under test):
   ```bash
   grep -oE '(href|src)="(assets/[^"]*)"' "own_theme/pages/<path>/<file>.html" \
     | sed -E 's/^(href|src)="//; s/"$//' \
     | while read -r rel; do
         f="own_theme/pages/<path>/$rel"
         [ -f "$f" ] || echo "MISSING: $rel"
       done
   ```
   Expected: no output (no `MISSING:` lines). Any CDN/absolute
   (`https://`) reference is allowed to appear unchecked here — only
   Leaflet's tile URLs should ever be a live `https://` reference; every
   other `href`/`src` must be a relative `assets/...` path.

3. **Visual spot-check:** serve the folder (`php -S localhost:8081 -t own_theme`,
   already-available PHP CLI, used only as a static file server — no PHP
   executes) and open the page in a browser via the `run` skill. Confirm
   it renders as designed, the sidebar/topbar match the reference shell
   exactly, and the browser console is clear of errors.

Each task's final step is always:
```bash
git add own_theme/<path-just-created>
git commit -m "<task-specific message>"
```

---

## Phase 0: Foundation

### Task 0.1: Scaffold directories and copy existing assets

**Files:**
- Create directories: `own_theme/assets/{css,js,fonts,vendor}`,
  `own_theme/pages/{esection,dashboards,apps,cards,charts,components,extended,forms,layouts,maps,misc,tables,ui}`
- Copy: `public/assets/css/*.css` → `own_theme/assets/css/`
- Copy: `public/assets/js/*.js` → `own_theme/assets/js/`
- Copy: `public/assets/fonts/*` → `own_theme/assets/fonts/`
- Copy: `public/assets/vendor/{select2,flatpickr,sweetalert2}` → `own_theme/assets/vendor/`

- [ ] **Step 1: Create the directory tree**

```bash
mkdir -p own_theme/assets/css own_theme/assets/js own_theme/assets/fonts
mkdir -p own_theme/assets/vendor
mkdir -p own_theme/pages/esection own_theme/pages/dashboards own_theme/pages/apps
mkdir -p own_theme/pages/cards own_theme/pages/charts own_theme/pages/components
mkdir -p own_theme/pages/extended own_theme/pages/forms own_theme/pages/layouts
mkdir -p own_theme/pages/maps own_theme/pages/misc own_theme/pages/tables own_theme/pages/ui
```

- [ ] **Step 2: Copy existing CSS/JS/fonts/vendor assets**

```bash
cp public/assets/css/*.css own_theme/assets/css/
cp public/assets/js/*.js own_theme/assets/js/
cp public/assets/fonts/* own_theme/assets/fonts/
cp -r public/assets/vendor/select2 own_theme/assets/vendor/select2
cp -r public/assets/vendor/flatpickr own_theme/assets/vendor/flatpickr
cp -r public/assets/vendor/sweetalert2 own_theme/assets/vendor/sweetalert2
cp public/favicon.ico own_theme/favicon.ico 2>/dev/null || true
```

- [ ] **Step 3: Verify the copy**

```bash
ls own_theme/assets/css own_theme/assets/js own_theme/assets/fonts own_theme/assets/vendor
```

Expected: `esection-theme.css`, `esection-shell.css`, `bootstrap.min.css`,
`font-awesome.min.css` present in `css/`; `jquery.min.js`,
`bootstrap.bundle.min.js` in `js/`; the fontawesome webfont files present;
`select2/`, `flatpickr/`, `sweetalert2/` present under `vendor/`.

- [ ] **Step 4: Commit**

```bash
git add own_theme/assets
git commit -m "Scaffold own_theme directory tree and copy existing esection assets"
```

---

### Task 0.2: Vendor the six new libraries

**Files:**
- Create: `own_theme/assets/vendor/chartjs/chart.umd.min.js`
- Create: `own_theme/assets/vendor/datatables/dataTables.min.css`,
  `own_theme/assets/vendor/datatables/dataTables.bootstrap5.min.css`,
  `own_theme/assets/vendor/datatables/jquery.dataTables.min.js`,
  `own_theme/assets/vendor/datatables/dataTables.bootstrap5.min.js`
- Create: `own_theme/assets/vendor/quill/quill.snow.min.css`,
  `own_theme/assets/vendor/quill/quill.min.js`
- Create: `own_theme/assets/vendor/fullcalendar/index.global.min.js`
- Create: `own_theme/assets/vendor/sortablejs/Sortable.min.js`
- Create: `own_theme/assets/vendor/leaflet/leaflet.css`,
  `own_theme/assets/vendor/leaflet/leaflet.js`,
  `own_theme/assets/vendor/leaflet/images/` (marker icons)

- [ ] **Step 1: Create per-library directories**

```bash
mkdir -p own_theme/assets/vendor/chartjs own_theme/assets/vendor/datatables
mkdir -p own_theme/assets/vendor/quill own_theme/assets/vendor/fullcalendar
mkdir -p own_theme/assets/vendor/sortablejs own_theme/assets/vendor/leaflet/images
```

- [ ] **Step 2: Download each library from its official CDN**

```bash
curl -fsSL -o own_theme/assets/vendor/chartjs/chart.umd.min.js \
  https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js

curl -fsSL -o own_theme/assets/vendor/datatables/jquery.dataTables.min.js \
  https://cdn.datatables.net/2.1.8/js/dataTables.min.js
curl -fsSL -o own_theme/assets/vendor/datatables/dataTables.bootstrap5.min.js \
  https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js
curl -fsSL -o own_theme/assets/vendor/datatables/dataTables.min.css \
  https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css
curl -fsSL -o own_theme/assets/vendor/datatables/dataTables.bootstrap5.min.css \
  https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css

curl -fsSL -o own_theme/assets/vendor/quill/quill.min.js \
  https://cdn.jsdelivr.net/npm/quill@2/dist/quill.min.js
curl -fsSL -o own_theme/assets/vendor/quill/quill.snow.min.css \
  https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css

curl -fsSL -o own_theme/assets/vendor/fullcalendar/index.global.min.js \
  https://cdn.jsdelivr.net/npm/fullcalendar@6/index.global.min.js

curl -fsSL -o own_theme/assets/vendor/sortablejs/Sortable.min.js \
  https://cdn.jsdelivr.net/npm/sortablejs@1/Sortable.min.js

curl -fsSL -o own_theme/assets/vendor/leaflet/leaflet.js \
  https://unpkg.com/leaflet@1/dist/leaflet.js
curl -fsSL -o own_theme/assets/vendor/leaflet/leaflet.css \
  https://unpkg.com/leaflet@1/dist/leaflet.css
curl -fsSL -o own_theme/assets/vendor/leaflet/images/marker-icon.png \
  https://unpkg.com/leaflet@1/dist/images/marker-icon.png
curl -fsSL -o own_theme/assets/vendor/leaflet/images/marker-icon-2x.png \
  https://unpkg.com/leaflet@1/dist/images/marker-icon-2x.png
curl -fsSL -o own_theme/assets/vendor/leaflet/images/marker-shadow.png \
  https://unpkg.com/leaflet@1/dist/images/marker-shadow.png
```

- [ ] **Step 3: Verify every file downloaded and is non-empty**

```bash
find own_theme/assets/vendor/chartjs own_theme/assets/vendor/datatables \
     own_theme/assets/vendor/quill own_theme/assets/vendor/fullcalendar \
     own_theme/assets/vendor/sortablejs own_theme/assets/vendor/leaflet \
     -type f -size 0
```

Expected: no output (every downloaded file has non-zero size). If `curl`
cannot reach the network in this environment, STOP and report the exact
blocker to the user rather than fabricating placeholder files — these
libraries are load-bearing for Phases 4, 6, 8, 9, 11.

- [ ] **Step 4: Commit**

```bash
git add own_theme/assets/vendor
git commit -m "Vendor Chart.js, DataTables, Quill, FullCalendar, SortableJS and Leaflet"
```

---

### Task 0.3: Extend the theme CSS for new component types

**Files:**
- Modify: `own_theme/assets/css/esection-theme.css` (append only — do not
  touch `public/assets/css/esection-theme.css`)

- [ ] **Step 1: Append new component styles**

Append to `own_theme/assets/css/esection-theme.css`:

```css

/* ==========================================================================
   own_theme additions — new component types not used by the live app.
   Reuses existing custom properties (--es-indigo-rgb, --es-emerald-rgb,
   etc.) defined earlier in this file. Additive only.
   ========================================================================== */

/* Kanban board */
.es-kanban { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: .5rem; }
.es-kanban-column { flex: 0 0 280px; background: rgba(var(--es-indigo-rgb), .04); border-radius: .75rem; padding: .75rem; }
.es-kanban-column__title { font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .75rem; color: var(--bs-secondary); }
.es-kanban-card { background: #fff; border-radius: .5rem; padding: .75rem; margin-bottom: .5rem; box-shadow: 0 1px 2px rgba(0,0,0,.06); cursor: grab; }
.es-kanban-card.sortable-ghost { opacity: .4; }

/* Timeline */
.es-timeline { position: relative; padding-left: 1.5rem; border-left: 2px solid rgba(var(--es-indigo-rgb), .2); }
.es-timeline-item { position: relative; padding-bottom: 1.5rem; }
.es-timeline-item::before { content: ''; position: absolute; left: -1.55rem; top: .2rem; width: .65rem; height: .65rem; border-radius: 50%; background: var(--bs-indigo); }

/* Avatar */
.es-avatar { width: 2.5rem; height: 2.5rem; border-radius: 50%; object-fit: cover; display: inline-flex; align-items: center; justify-content: center; background: rgba(var(--es-indigo-rgb), .12); color: var(--bs-indigo); font-weight: 600; }
.es-avatar-group { display: flex; }
.es-avatar-group .es-avatar { margin-left: -.6rem; border: 2px solid #fff; }

/* Ratings */
.es-rating { display: inline-flex; gap: .15rem; color: var(--bs-warning); }

/* Tree view */
.es-tree, .es-tree ul { list-style: none; margin: 0; padding-left: 1.25rem; }
.es-tree > li:first-child { padding-left: 0; }
.es-tree-toggle { cursor: pointer; }
.es-tree-toggle .fa { width: 1rem; transition: transform .15s ease; }
.es-tree-toggle[aria-expanded="true"] .fa { transform: rotate(90deg); }

/* Chart / card containers reuse .glass-card as-is */

/* DataTables x Bootstrap 5 alignment with esection's existing .table look */
table.dataTable { border-collapse: collapse !important; }
.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select { border-radius: .5rem; border: 1px solid var(--bs-border-color); }

/* Quill editor skin to match .form-control */
.ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: var(--bs-border-color) !important; border-radius: .5rem; }
.ql-toolbar.ql-snow { border-top-left-radius: .5rem; border-top-right-radius: .5rem; }
.ql-container.ql-snow { border-bottom-left-radius: .5rem; border-bottom-right-radius: .5rem; }

/* FullCalendar skin */
.fc { --fc-border-color: var(--bs-border-color); --fc-today-bg-color: rgba(var(--es-indigo-rgb), .06); }
.fc .fc-button-primary { background: var(--bs-indigo); border-color: var(--bs-indigo); }
```

- [ ] **Step 2: Verify the file still parses as valid CSS (brace balance)**

```bash
node -e "const s=require('fs').readFileSync('own_theme/assets/css/esection-theme.css','utf8'); const o=(s.match(/{/g)||[]).length, c=(s.match(/}/g)||[]).length; if(o!==c){console.error('MISMATCH', o, c); process.exit(1)} console.log('OK', o, c)"
```

If `node` is unavailable, verify manually instead:
```bash
grep -o '{' own_theme/assets/css/esection-theme.css | wc -l
grep -o '}' own_theme/assets/css/esection-theme.css | wc -l
```
Expected: both counts equal.

- [ ] **Step 3: Commit**

```bash
git add own_theme/assets/css/esection-theme.css
git commit -m "Extend own_theme CSS for kanban, timeline, avatar, rating, tree, DataTables/Quill/FullCalendar skins"
```

---

## Phase 1: Canonical shell templates

These two pages are the templates every later page clones. Get these
exactly right — every downstream task depends on them.

### Task 1.1: Build the canonical app-shell page (`dashboard.html`)

**Files:**
- Create: `own_theme/pages/esection/dashboard.html`

**Interfaces:**
- Produces: the "app shell" block (everything from `<!DOCTYPE html>` through
  the sidebar `</aside>`, the `#sidebar_backdrop`, `#content-wrapper` open
  tag, `<header id="topbar">`, the Reset Password modal, and the closing
  `<script>` block that wires sidebar rail/drawer + SweetAlert2 mixin +
  logout confirm) that **every other page in this plan clones verbatim**,
  changing only: `<title>`, the active `nav-link` (`class="nav-link active"`
  on the current page's sidebar item only), the `topbar` title/subtitle
  text, and everything inside `<main class="page-body">...</main>`.

- [ ] **Step 1: Write the page**

Base this on the live app's actual shell — read
`app/Views/layouts/app.php` (the current `<html>` through the closing
`</html>`) and transcribe it into plain HTML with these changes:
- Remove all `<?php ... ?>` and `<?= ... ?>` — replace with static output.
- Remove the `es_rail` cookie-read PHP block; drop the class entirely
  (static pages start expanded).
- Sidebar: show every nav item unconditionally (no `can()`/`feature_enabled()`
  checks — this is a static reference, not a permission-gated app). Mark
  "Dashboard" as `active` here.
- Replace `<?= base_url(...) ?>` links with the page's own relative
  filename within `own_theme/pages/esection/` (e.g. `students-new.html`,
  `confirmations.html`) so cross-navigation between the 33 esection pages
  actually works when browsed locally.
- Replace `<?= asset_url('assets/...') ?>` with plain relative paths:
  `../../assets/css/bootstrap.min.css` etc. (pages live two directories
  below `own_theme/`, so assets are `../../assets/...`).
- Topbar: `<span id="live_date">` / `<span id="live_clock">` keep their
  JS-driven behavior (the inline script that renders them is untouched —
  it's already pure client-side JS with no PHP dependency). Username pill:
  static text `Priya Sharma` / role badge `admin` (sample data, consistent
  across every page in this plan for continuity of the "logged in as").
  Recall this: **every page in this theme uses the same sample user,
  "Priya Sharma" / role "admin"**, so the topbar is identical everywhere.
- Reset Password modal: keep verbatim (it's pure markup + the
  `common/ajax_account_js` behavior, which is just client-side password
  match validation — port that inline into this page's closing `<script>`
  rather than as a separate include, since this theme has no includes).
- Body content (`<main class="page-body">`): the current live dashboard
  (`app/Views/dashboard/index.php`) renders KPI stat tiles and recent
  activity. Rebuild it with 4 `.glass-card` stat tiles (Total Students,
  Pending Confirmations, Active Universities, This Month's Regularizations
  — sample numbers) and a "Recent Activity" `.glass-card` with a 6-row
  sample list (student name, action, timestamp).
- Closing scripts: keep the sidebar rail/drawer toggle script, the
  SweetAlert2 mixin + logout-confirm handler, and the live clock renderer,
  verbatim from `app/Views/layouts/app.php`'s script block (flash-message
  SweetAlert blocks are dropped — there's no session/flash data in a
  static page). Link `../../assets/js/jquery.min.js`,
  `../../assets/js/bootstrap.bundle.min.js`,
  `../../assets/vendor/select2/select2.min.js`,
  `../../assets/vendor/flatpickr/flatpickr.min.js`,
  `../../assets/vendor/sweetalert2/sweetalert2.min.js`.

- [ ] **Step 2: Apply the Verification Recipe** (serve `own_theme/` via
  `php -S localhost:8081 -t own_theme`, open
  `http://localhost:8081/pages/esection/dashboard.html`)

Confirm: sidebar renders with all nav items, "Dashboard" is highlighted
active, topbar clock ticks, account dropdown opens, Reset Password modal
opens or closes, mobile drawer opens under 992px width, zero console
errors.

- [ ] **Step 3: Commit**

```bash
git add own_theme/pages/esection/dashboard.html
git commit -m "Build canonical app-shell reference page (dashboard.html)"
```

---

### Task 1.2: Build the canonical auth-shell page (`auth-login.html`)

**Files:**
- Create: `own_theme/pages/esection/auth-login.html`

**Interfaces:**
- Produces: the "auth shell" block (centered card, no sidebar/topbar) that
  every auth-style page in this plan clones (the 3 esection auth pages in
  Phase 2, plus the 8 `page-auth-*-v1/v2.html` variants in Phase 12).

- [ ] **Step 1: Write the page**

Base on `app/Views/layouts/auth.php` + `app/Views/auth/login.php`:
transcribe to plain HTML, same asset-path and PHP-removal rules as Task
1.1. Keep the centered-card layout, the username/password fields, the
password show/hide toggle (`common/es_password_toggle_js` — port its
plain-JS logic inline into this page's own `<script>`), the submit button
motion (`common/es_form_motion_js` — likewise port inline: it just toggles
a `disabled`+spinner state on submit, harmless to keep even with
`action="#"`), and a "Forgot password?" link to `auth-forgot-password.html`.

- [ ] **Step 2: Apply the Verification Recipe**

- [ ] **Step 3: Commit**

```bash
git add own_theme/pages/esection/auth-login.html
git commit -m "Build canonical auth-shell reference page (auth-login.html)"
```

---

## Phase 2: Esection business pages (31 remaining)

Every task below: clone the app-shell from `dashboard.html` (or the
auth-shell from `auth-login.html` for the 2 remaining auth pages), keep
sidebar/topbar/modal/scripts identical, change only the active nav item,
`<title>`, and `<main class="page-body">` content. Content source for each
is the real current PHP view — read it, keep its structure/sections/labels,
replace dynamic values (`$data`, session, DB-driven rows) with realistic
sample data, drop AJAX wiring (no backend to call), keep client-side-only
behavior (Select2/Flatpickr init, Bootstrap tabs/collapse, form-motion
spinner). Apply the Verification Recipe and commit individually for each.

| # | File | Source PHP view | Content notes |
|---|------|------------------|----------------|
| 2.1 | `esection/auth-forgot-password.html` | `app/Views/auth/forgot_password.php` | Auth shell |
| 2.2 | `esection/auth-reset-password.html` | `app/Views/auth/reset_password.php` | Auth shell |
| 2.3 | `esection/students-new.html` | `app/Views/students/new_form.php` | App shell; full multi-section student form with sample field values |
| 2.4 | `esection/students-import.html` | `app/Views/students/import_form.php` | App shell; file-upload dropzone (native `<input type=file>`) + sample column-mapping table |
| 2.5 | `esection/students-history.html` | `app/Views/students/history.php` | App shell; filter row + 8-row sample table + `pagination_glass`-style pager (reuse `.es-pagebar` markup/classes from `app/Views/common/pagination_glass.php`, static sample counts) |
| 2.6 | `esection/students-batch-detail.html` | `app/Views/students/batch_detail.php` | App shell; sample batch header + row table |
| 2.7 | `esection/confirmations.html` | `app/Views/confirmations/index.php` | App shell; pending-confirmations sample list |
| 2.8 | `esection/confirmations-history.html` | `app/Views/confirmations/history.php` | App shell; filtered history table + pager |
| 2.9 | `esection/confirmations-batch-detail.html` | `app/Views/confirmations/batch_detail.php` | App shell; sample batch rows |
| 2.10 | `esection/universities.html` | `app/Views/universities/index.php` | App shell; university directory sample table + add-university form/modal |
| 2.11 | `esection/regularization.html` | `app/Views/regularization/index.php` | App shell; regularization letter form |
| 2.12 | `esection/regularization-history.html` | `app/Views/regularization/history.php` | App shell; history table + pager |
| 2.13 | `esection/reminders-university.html` | `app/Views/reminders/university.php` | App shell; university reminder queue sample table |
| 2.14 | `esection/reminders-university-history.html` | `app/Views/reminders/university_history.php` | App shell; sent-reminders history table |
| 2.15 | `esection/reminders-university-batch-detail.html` | `app/Views/reminders/university_batch_detail.php` | App shell; sample batch detail |
| 2.16 | `esection/reminders-student.html` | `app/Views/reminders/student.php` | App shell; student reminder queue |
| 2.17 | `esection/reminders-student-history.html` | `app/Views/reminders/student_history.php` | App shell; history table + pager |
| 2.18 | `esection/settings.html` | `app/Views/settings/index.php` | App shell; settings landing/nav cards |
| 2.19 | `esection/settings-users.html` | `app/Views/settings/users.php` | App shell; user list table + add/edit user modal |
| 2.20 | `esection/settings-access-rights.html` | `app/Views/settings/access_rights.php` | App shell; permission matrix table |
| 2.21 | `esection/settings-academic-years.html` | `app/Views/settings/academic_years.php` | App shell; academic year list + add form |
| 2.22 | `esection/settings-courses.html` | `app/Views/settings/courses.php` | App shell; course list + add form |
| 2.23 | `esection/settings-institute.html` | `app/Views/settings/institute.php` | App shell; institute details form incl. logo upload placeholder |
| 2.24 | `esection/settings-mail.html` | `app/Views/settings/mail.php` | App shell; SMTP config form + status card |
| 2.25 | `esection/settings-numbering.html` | `app/Views/settings/numbering.php` | App shell; numbering-scheme config form |
| 2.26 | `esection/settings-letter-templates.html` | `app/Views/settings/letter_templates.php` | App shell; template list + editor pane (plain `<textarea>`, not Quill — matches the live app) |
| 2.27 | `esection/settings-backup.html` | `app/Views/settings/backup.php` | App shell; backup history table + "Run backup" button |
| 2.28 | `esection/settings-features.html` | `app/Views/settings/features.php` | App shell; feature-flag toggle list |
| 2.29 | `esection/settings-activity-log.html` | `app/Views/settings/activity_log.php` | App shell; activity log table + pager |
| 2.30 | `esection/bulk-email.html` | `app/Views/bulk_email/index.php` | App shell; audience selector + compose form |
| 2.31 | `esection/bulk-email-log.html` | `app/Views/bulk_email/log.php` | App shell; send-log table + status filter |

- [ ] **Step: build, verify, and commit each row above individually**
  (33 total including Tasks 1.1/1.2 already done) — one commit per file,
  message pattern: `Add own_theme esection page: <file>`

---

## Phase 3: Dashboards (2)

| # | File | Content |
|---|------|---------|
| 3.1 | `dashboards/dashboard-analytics.html` | App shell; KPI tiles + two Chart.js charts (a line chart for a "signups over time"-style trend, a doughnut for a category breakdown) inside `.glass-card` containers. Load `../../assets/vendor/chartjs/chart.umd.min.js`, init both with real `new Chart(ctx, {...})` calls and inline sample datasets. |
| 3.2 | `dashboards/dashboard-ecommerce.html` | App shell; a distinct KPI/chart layout (bar chart + stat tiles + a "top items" sample table) — same Chart.js library, different chart type/config so the two dashboard pages are visually distinct. |

- [ ] Build, verify (include: chart actually renders, no console errors from Chart.js), commit each.

---

## Phase 4: Apps (18)

App-shell unless noted. Each is its own self-contained page with realistic sample data/markup — no backend, `action="#"` on any form.

| # | File | Content |
|---|------|---------|
| 4.1 | `apps/app-calendar.html` | FullCalendar month view, sample events array passed to `events: [...]` in the init call. |
| 4.2 | `apps/app-chat.html` | Two-pane layout: contact list (left, `.list-group`) + message thread (right, sample bubbles), plain `<input>` composer. |
| 4.3 | `apps/app-ecommerce-shop.html` | Product grid (`.glass-card` per product), sample prices/images (use a CSS placeholder box, no external image fetch), filter sidebar. |
| 4.4 | `apps/app-ecommerce-details.html` | Single product detail: gallery thumbnails (CSS placeholders), price, sample description, add-to-cart button. |
| 4.5 | `apps/app-ecommerce-checkout.html` | Multi-section checkout form (shipping, payment method radio group, order summary card). |
| 4.6 | `apps/app-ecommerce-wishlist.html` | Table of saved items with remove/move-to-cart buttons. |
| 4.7 | `apps/app-email.html` | Three-pane layout: folder list, message list, reading pane — sample emails. |
| 4.8 | `apps/app-file-manager.html` | Grid/list toggle of sample files+folders, each a `.glass-card` with a Font Awesome file-type icon. |
| 4.9 | `apps/app-invoice-list.html` | DataTables-powered invoice table (status badges, amount, due date). Load `../../assets/vendor/datatables/*`, init with `$('#table').DataTable({...})`. |
| 4.10 | `apps/app-invoice-add.html` | Invoice form: bill-to fields, line-item repeater (a hidden `<template>` row cloned via vanilla JS on an "Add line" button click, each clone getting its own "Remove" button — the same clone-row pattern `form-repeater.html` in Phase 9 also uses, but self-contained here, don't depend on that file existing), totals sidebar. |
| 4.11 | `apps/app-invoice-edit.html` | Same layout as 4.10, pre-filled with sample values. |
| 4.12 | `apps/app-invoice-preview.html` | Read-only invoice document layout (letterhead, line items, totals) inside a `.glass-card`, "Print" button. |
| 4.13 | `apps/app-invoice-print.html` | Same content as 4.12 but a bare print-optimized layout (no sidebar/topbar — uses the `layout-blank` pattern from Phase 10) with a `@media print` stylesheet block. |
| 4.14 | `apps/app-kanban.html` | Three-column board (`.es-kanban`/`.es-kanban-column`/`.es-kanban-card` from Task 0.3) wired with SortableJS: `new Sortable(column, {group:'kanban', animation:150})` per column so cards drag between columns. |
| 4.15 | `apps/app-todo.html` | Checklist with add/remove (vanilla JS), sample items in mixed done/pending state. |
| 4.16 | `apps/app-user-list.html` | DataTables-powered user table (avatar via `.es-avatar`, role badge, status). |
| 4.17 | `apps/app-user-view.html` | Profile-style single-user page: avatar, details card, activity timeline (`.es-timeline`). |
| 4.18 | `apps/app-user-edit.html` | Edit form for the same sample user, tabbed (`Account` / `Security` / `Notifications`) via Bootstrap nav-tabs. |

- [ ] Build, verify, commit each individually.

---

## Phase 5: Cards (5)

| # | File | Content |
|---|------|---------|
| 5.1 | `cards/card-basic.html` | Grid of plain `.glass-card` examples: title+text, with/without footer, with/without image placeholder. |
| 5.2 | `cards/card-advance.html` | Cards with overlay text, horizontal layout, colored header variants. |
| 5.3 | `cards/card-actions.html` | Cards with header dropdown menu (collapse/refresh/remove buttons wired with vanilla JS to toggle/hide the card). |
| 5.4 | `cards/card-statistics.html` | KPI stat-tile variations (icon+number, with trend arrow/percentage, with mini sparkline drawn via a tiny inline Chart.js line chart with no axes). |
| 5.5 | `cards/card-analytics.html` | Cards embedding a small Chart.js chart each (bar/line/doughnut), 3-4 examples. |

- [ ] Build, verify, commit each individually.

---

## Phase 6: Charts (2)

| # | File | Content |
|---|------|---------|
| 6.1 | `charts/charts-basic.html` | One Chart.js chart per common type: line, bar, pie, doughnut — each in its own `.glass-card`, each with a real `new Chart(...)` call and distinct sample dataset. |
| 6.2 | `charts/charts-advanced.html` | Chart.js: multi-series line chart, stacked bar chart, radar chart, mixed bar+line combo chart — demonstrating more advanced `data.datasets` configurations. |

- [ ] Build, verify (all charts render, legend/tooltips work), commit each.

---

## Phase 7: Components (23)

Bootstrap-5-native + esection CSS classes only, no new libraries. App shell wrapper (sidebar/topbar) around a page body that showcases the named component's full variant set.

| # | File | Content |
|---|------|---------|
| 7.1 | `components/component-alerts.html` | All Bootstrap contextual alert colors, dismissible variant, alert with icon+heading. |
| 7.2 | `components/component-avatar.html` | `.es-avatar` sizes, initials-only vs image, `.es-avatar-group` stack, status-dot overlay. |
| 7.3 | `components/component-badges.html` | All badge colors, pill vs square, badge-in-button, badge-in-heading. |
| 7.4 | `components/component-breadcrumbs.html` | Plain, with icons, with active-state truncation on a long path. |
| 7.5 | `components/component-bs-toast.html` | Bootstrap native toasts (success/error/info), triggered by buttons via `bootstrap.Toast`. Also covers the reference theme's "Toastr" concept — no second library added. |
| 7.6 | `components/component-buttons.html` | All variants/outlines/sizes/states (disabled, loading spinner), button groups, icon buttons. |
| 7.7 | `components/component-carousel.html` | Bootstrap native carousel: image-placeholder slides, indicators, captions, autoplay toggle. |
| 7.8 | `components/component-collapse.html` | Single collapse, accordion (multiple, one-open-at-a-time). |
| 7.9 | `components/component-divider.html` | Horizontal rule variants, text-in-divider, vertical divider between flex items. |
| 7.10 | `components/component-dropdowns.html` | Button dropdown, split button, dropdown with header/divider/disabled item, dropup. |
| 7.11 | `components/component-list-group.html` | Plain, with badges, with icons, active/disabled states, flush variant. |
| 7.12 | `components/component-media-objects.html` | Avatar+text media rows (comment-list style), nested media object. |
| 7.13 | `components/component-modals.html` | Small/default/large/fullscreen modal, scrollable-body modal, confirm-style modal. |
| 7.14 | `components/component-navs.html` | Tabs, pills, vertical pills, justified nav. |
| 7.15 | `components/component-pagination.html` | `.es-pagebar`-style pager plus plain Bootstrap pagination sizes (sm/default/lg). |
| 7.16 | `components/component-pill-badges.html` | Pill badges as filter chips (removable, with count). |
| 7.17 | `components/component-pills.html` | Pill-style nav variants (see also 7.14 — differentiate by showing pill-as-filter-tabs with content panes). |
| 7.18 | `components/component-popovers.html` | Popovers on all 4 placements, with title, dismiss-on-click-outside. |
| 7.19 | `components/component-progress.html` | Basic, striped, animated, stacked multi-bar. |
| 7.20 | `components/component-spinner.html` | Border + grow spinners, sizes, in-button loading state. |
| 7.21 | `components/component-tabs.html` | Tabs with icons, vertical tabs, tabs with a badge count. |
| 7.22 | `components/component-timeline.html` | `.es-timeline` (from Task 0.3) with 6-8 sample events, icon markers. |
| 7.23 | `components/component-tooltips.html` | Tooltips on all 4 placements, HTML-content tooltip. |

- [ ] Build, verify, commit each individually.

---

## Phase 8: Extended components (12)

Vanilla JS / native browser APIs only, except `ext-sweet-alerts.html` (already-vendored SweetAlert2). No new libraries.

| # | File | Content |
|---|------|---------|
| 8.1 | `extended/ext-block-ui.html` | Buttons that overlay a `.glass-card` with a semi-opaque layer + spinner (plain JS toggling a positioned `<div>`), auto-clears after 2s. |
| 8.2 | `extended/ext-clipboard.html` | "Copy" buttons using `navigator.clipboard.writeText()`, with a temporary "Copied!" tooltip/label swap. |
| 8.3 | `extended/ext-context-menu.html` | A sample table row with a custom right-click menu (`contextmenu` event → positioned Bootstrap dropdown-menu markup). |
| 8.4 | `extended/ext-drag-drop.html` | Native HTML5 Drag and Drop API: draggable sample cards between two drop-zone columns (distinct from the Kanban/SortableJS example in 4.14 — this one demonstrates the plain browser API). |
| 8.5 | `extended/ext-i18n.html` | A language switcher (EN/HI buttons) swapping a small in-page dictionary object via vanilla JS `textContent` updates on a handful of labels. |
| 8.6 | `extended/ext-media-player.html` | Native `<video controls>` and `<audio controls>` elements (no external media files needed — reference local sample assets or omit `src` with a poster/placeholder and note "sample media"). |
| 8.7 | `extended/ext-ratings.html` | `.es-rating` star ratings: read-only display variant + an interactive input variant (radio-input-based, CSS-only star fill). |
| 8.8 | `extended/ext-sliders.html` | Native `<input type="range">` styled with Bootstrap `.form-range`, single + dual-handle (two overlapping inputs) examples, live value readout via vanilla JS. |
| 8.9 | `extended/ext-swipe-gallery.html` | A touch/swipe image gallery built with vanilla JS `touchstart`/`touchend` delta detection plus prev/next buttons — no Swiper library. |
| 8.10 | `extended/ext-sweet-alerts.html` | SweetAlert2 variants: success/error/warning/confirm/input dialogs, using the same `Swal.mixin` button styling as the live app's layout script. |
| 8.11 | `extended/ext-tour.html` | A 3-step guided tour using sequential Bootstrap popovers (Next/Prev/Done buttons, vanilla JS step index). |
| 8.12 | `extended/ext-tree.html` | `.es-tree` (from Task 0.3) nested list with expand/collapse toggles (vanilla JS, `aria-expanded` state). |

- [ ] Build, verify, commit each individually.

---

## Phase 9: Forms (16)

| # | File | Content |
|---|------|---------|
| 9.1 | `forms/form-input.html` | Text/email/password/number/URL inputs, sizes, disabled/readonly/plaintext states, floating labels. |
| 9.2 | `forms/form-input-groups.html` | Prepend/append icon or text, buttons-in-group, multiple addons. |
| 9.3 | `forms/form-input-mask.html` | Phone/date/currency-formatted inputs using a small vanilla-JS mask function (no new library) on `input` events. |
| 9.4 | `forms/form-number-input.html` | Native `<input type=number>` with step/min/max, plus a custom increment/decrement button pair (vanilla JS). |
| 9.5 | `forms/form-textarea.html` | Basic, disabled, with character-count readout (vanilla JS on `input`). |
| 9.6 | `forms/form-checkbox.html` | Default, switch-style, indeterminate state (vanilla JS setting `.indeterminate`), inline group. |
| 9.7 | `forms/form-radio.html` | Default, button-style radio group, inline group. |
| 9.8 | `forms/form-switch.html` | Sizes, disabled, with label-driven state text ("On"/"Off" via vanilla JS `change` listener). |
| 9.9 | `forms/form-select.html` | Native `<select>` plus Select2-enhanced multi-select and tag-style select (already-vendored Select2). |
| 9.10 | `forms/form-date-time-picker.html` | Flatpickr date, time, and date-range pickers (already-vendored Flatpickr). |
| 9.11 | `forms/form-file-uploader.html` | Native `<input type=file multiple>` with a drag-over highlight (vanilla JS `dragover`/`drop`) and a selected-files list readout. |
| 9.12 | `forms/form-repeater.html` | "Add row" button that clones a hidden `<template>` row into a list, each row has its own "Remove" button (vanilla JS). |
| 9.13 | `forms/form-wizard.html` | 3-step wizard: Bootstrap nav-pills as step indicator, `Next`/`Previous` vanilla JS toggling which `<section>` is visible, a review step. |
| 9.14 | `forms/form-validation.html` | Bootstrap's native `.was-validated` pattern: required/pattern/min-length fields, custom invalid-feedback text, JS `checkValidity()` on submit (prevented, since `action="#"`). |
| 9.15 | `forms/form-layout.html` | Horizontal label+input rows, inline form, form grid (multi-column via `.row`/`.col-*`). |
| 9.16 | `forms/form-quill-editor.html` | Quill rich-text editor (`new Quill('#editor', {theme:'snow'})`) with sample starter content. |

- [ ] Build, verify, commit each individually.

---

## Phase 10: Layouts (5)

These vary the *shell itself*, not the page body — each demonstrates a structural shell variant.

| # | File | Content |
|---|------|---------|
| 10.1 | `layouts/layout-blank.html` | No sidebar/topbar at all — a single centered `.glass-card`, useful pattern reused by 4.13's print page. |
| 10.2 | `layouts/layout-boxed.html` | Full app shell, but content constrained to a max-width, centered container with visible page margins (vs. the default full-bleed shell). |
| 10.3 | `layouts/layout-collapsed-menu.html` | Full app shell rendered with the sidebar pre-set to the icon-rail (`es-rail`) state, to show that variant without requiring a click. |
| 10.4 | `layouts/layout-empty.html` | Just the topbar, no sidebar, full-width content — for pages that don't need navigation. |
| 10.5 | `layouts/layout-without-menu.html` | Sidebar hidden entirely (collapsed to zero width via a toggle), topbar spans full width. |

- [ ] Build, verify, commit each individually.

---

## Phase 11: Maps (1)

| # | File | Content |
|---|------|---------|
| 11.1 | `maps/maps-leaflet.html` | `new L.map('map').setView([lat,lng], zoom)` + `L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {...}).addTo(map)` (the one accepted live-network exception, per Global Constraints) + a sample marker with popup. Page notes visibly (a small caption under the map) that tile imagery requires network access. |

- [ ] Build, verify (map renders, pans/zooms, marker popup opens — note in the verification that this specific page requires live internet for tiles, unlike every other page), commit.

---

## Phase 12: Misc (22)

| # | File | Content |
|---|------|---------|
| 12.1 | `misc/page-account-settings.html` | Tabbed account settings (Profile / Security / Notifications), app shell. |
| 12.2 | `misc/page-profile.html` | Profile header (avatar, name, role) + stats row + activity timeline, app shell. |
| 12.3 | `misc/page-pricing.html` | 3-tier pricing card row, monthly/yearly toggle switch (vanilla JS swapping displayed price text), blank layout. |
| 12.4 | `misc/page-faq.html` | Accordion-based FAQ list (Bootstrap collapse), search input (vanilla JS client-side filter over the visible questions), blank layout. |
| 12.5 | `misc/page-blog-list.html` | Card grid of sample blog posts (title/excerpt/date/author), app shell. |
| 12.6 | `misc/page-blog-detail.html` | Single sample post body + comment list (`.list-group`/media-object style), app shell. |
| 12.7 | `misc/page-blog-edit.html` | Post edit form using the Quill editor (reuse 9.16's init pattern) for the body field, app shell. |
| 12.8 | `misc/page-knowledge-base.html` | Category card grid linking conceptually to `page-kb-category.html`, app shell. |
| 12.9 | `misc/page-kb-category.html` | List of sample articles within one category, app shell. |
| 12.10 | `misc/page-kb-question.html` | Single article body + "was this helpful" yes/no buttons (vanilla JS toggling a thank-you message), app shell. |
| 12.11 | `misc/page-misc-error.html` | 404-style centered illustration-free message card ("Page not found"), blank layout, "Back to dashboard" link. |
| 12.12 | `misc/page-misc-not-authorized.html` | 403-style message card, blank layout. |
| 12.13 | `misc/page-misc-under-maintenance.html` | Maintenance message card, blank layout. |
| 12.14 | `misc/page-misc-coming-soon.html` | Coming-soon message card with a client-side countdown timer (vanilla JS `setInterval` counting down to a fixed future sample date), blank layout. |
| 12.15 | `misc/page-auth-login-v1.html` | Alternate visual treatment of the auth-login shell from Task 1.2 (e.g. split-screen with a brand panel) — still same field set/behavior. |
| 12.16 | `misc/page-auth-login-v2.html` | A second alternate treatment (e.g. card-on-pattern-background instead of split-screen). |
| 12.17 | `misc/page-auth-register-v1.html` | Registration form (name/email/password/confirm/terms-checkbox), auth shell v1 treatment. |
| 12.18 | `misc/page-auth-register-v2.html` | Same form, v2 treatment. |
| 12.19 | `misc/page-auth-forgot-password-v1.html` | Alternate treatment of `auth-forgot-password.html`'s content, v1. |
| 12.20 | `misc/page-auth-forgot-password-v2.html` | v2 treatment. |
| 12.21 | `misc/page-auth-reset-password-v1.html` | Alternate treatment of `auth-reset-password.html`'s content, v1. |
| 12.22 | `misc/page-auth-reset-password-v2.html` | v2 treatment. |

- [ ] Build, verify, commit each individually.

---

## Phase 13: Tables (3)

| # | File | Content |
|---|------|---------|
| 13.1 | `tables/table-bootstrap.html` | Plain Bootstrap `.table` variants: striped, hover, bordered, borderless, dark, responsive, sizes — static sample rows, no JS library. |
| 13.2 | `tables/table-datatable-basic.html` | DataTables default init on a sample table: sorting, built-in search box, pagination. |
| 13.3 | `tables/table-datatable-advanced.html` | DataTables with more features enabled (column visibility toggle, per-column search inputs, custom page-length selector) — also covers the reference catalog's separate ag-Grid page; no second grid library added. |

- [ ] Build, verify (sorting/searching actually work against the sample data), commit each.

---

## Phase 14: UI reference (3)

| # | File | Content |
|---|------|---------|
| 14.1 | `ui/ui-colors.html` | Swatches for every esection design-token color (indigo/emerald/etc.) with their CSS variable name and hex value labeled — a live reference sheet, values read directly from `esection-theme.css`'s `:root` block. |
| 14.2 | `ui/ui-typography.html` | Heading scale h1-h6, body text sizes, lead paragraph, blockquote, code/kbd/pre samples, list styles. |
| 14.3 | `ui/ui-icons.html` | Grid of Font Awesome 4 icons actually used elsewhere in this theme (pull the distinct `fa fa-*` classes used across Phases 2-13 rather than FA4's entire set), each with its class name labeled for copy-paste — esection's own icon set, replacing the reference catalog's Feather-icons page. |

- [ ] Build, verify, commit each.

---

## Phase 15: Landing page and final QA

### Task 15.1: Build `own_theme/index.html`

**Files:**
- Create: `own_theme/index.html`

- [ ] **Step 1: Write the page**

A single static HTML page (its own minimal shell — reuse
`layout-blank.html`'s pattern) listing every one of the 145 other pages as
linked cards, grouped into the same 13 categories as this plan's phases
(Esection, Dashboards, Apps, Cards, Charts, Components, Extended, Forms,
Layouts, Maps, Misc, Tables, UI). Each link's `href` is the real relative
path (e.g. `pages/esection/dashboard.html`).

- [ ] **Step 2: Verify every link resolves**

```bash
grep -oE 'href="pages/[^"]*"' own_theme/index.html | sed 's/href="//; s/"$//' \
  | while read -r rel; do [ -f "own_theme/$rel" ] || echo "MISSING: $rel"; done
```
Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add own_theme/index.html
git commit -m "Add own_theme landing page linking all 145 theme pages"
```

### Task 15.2: Full QA sweep

- [ ] **Step 1: Confirm zero PHP leftovers anywhere in the theme**

```bash
grep -rl '<?php\|\$this->extend\|\$this->include\|<?=' own_theme/ || echo "CLEAN"
```
Expected: `CLEAN`.

- [ ] **Step 2: Confirm every page has zero broken local asset links**
  (loop the Verification Recipe's asset-check across all 146 files)

```bash
find own_theme/pages own_theme/index.html -name '*.html' | while read -r f; do
  dir=$(dirname "$f")
  grep -oE '(href|src)="(assets/[^"]*|pages/[^"]*)"' "$f" | sed -E 's/^(href|src)="//; s/"$//' | while read -r rel; do
    target="$dir/$rel"
    [ -f "$target" ] || echo "MISSING in $f: $rel"
  done
done
```
Expected: no output.

- [ ] **Step 3: Visual sweep** — serve `own_theme/` and, via the `run`
  skill, open at minimum: `index.html`, `dashboard.html`, one page from
  each of the 13 categories, and every page in Phase 4 and Phase 11
  (heaviest new-library integrations). Confirm sidebar/topbar consistency,
  correct active nav state per page, no console errors.

- [ ] **Step 4: Update `.gitignore`**

Remove the `/own_theme/` line (own_theme is now real, tracked source, not
scratch/ignored content).

```bash
grep -n '^/own_theme/$' .gitignore
```
Remove that exact line from `.gitignore`, then:

```bash
git add .gitignore
git commit -m "Stop ignoring own_theme now that it holds real tracked theme source"
```

- [ ] **Step 5: Final commit confirming the full theme**

```bash
git add own_theme
git commit -m "Complete own_theme: 146-page static admin theme" --allow-empty
```
