# own_theme: a complete static HTML theme

Date: 2026-08-22

## Goal

Build `own_theme/` as a completely static, self-contained HTML/CSS/JS admin
theme: no PHP, no templating engine, no build step. Every page is one
standalone `.html` file that opens directly in a browser. Visual design stays
100% consistent with esection's current look (Bootstrap 5.1.2 + Font Awesome
4 + the `esection-theme.css` / `esection-shell.css` indigo/emerald "glass"
design system), copied from `public/assets` into `own_theme/assets`.

Two kinds of pages:

1. **Business pages (32)** — a faithful static mirror of every real screen
   esection's staff-facing app has today.
2. **UI-kit / generic pages (113, see inventory below)** — a full breadth
   catalog of dashboards, app patterns, components, forms, tables, charts,
   layouts and misc pages, sized to match a commercial admin-theme's page
   catalog (`D:\webserver\www\sample_theme\html\ltr\vertical-menu-template-bordered`
   was used **only** to judge realistic breadth/category coverage — see IP
   posture below).

## IP / licensing posture

`sample_theme` is a paid commercial reference theme. It is used **only** to
decide which page *categories* a "complete" theme normally contains — page
categories (a dashboard, a kanban board, a pricing page) are generic UI
patterns, not copyrightable. Nothing is copied from it:

- No HTML/CSS/JS/image files are copied or adapted from `sample_theme`.
- No distinctive class-naming scheme, custom icon set, or visual identity is
  reproduced from it.
- Every page is authored fresh, in esection's own design language.

New third-party libraries added (all permissively licensed, all generic,
each used independently across thousands of unrelated projects — using them
creates no relationship to the paid reference theme):

| Library | License | Covers |
|---|---|---|
| Chart.js | MIT | Both chart pages (one library, not two) |
| DataTables (+ Bootstrap 5 styling) | MIT | All "advanced table" pages |
| Quill | BSD-3-Clause | Rich text editor page |
| FullCalendar (core) | MIT | Calendar page |
| SortableJS | MIT | Kanban board + generic drag-drop demo |
| Leaflet | BSD-2-Clause | Maps page |

**Known exception:** Leaflet's map *tiles* (the map imagery itself) stream
from a public OpenStreetMap tile server at view-time — true of every Leaflet
integration anywhere; self-hosting global tile data isn't realistic. All
library *code* and all other 112+ pages remain fully offline.

Everything else that the reference catalog covers (icons, tooltips, toasts,
carousels, ratings, sliders, clipboard, context-menu, tree view, "block UI"
overlays, tours, media player, generic drag-drop) is hand-built with vanilla
JS + Bootstrap 5's native components (already ships Popper-based
tooltips/popovers, toasts, carousel, collapse) + the existing Font Awesome 4
icon set. Zero further dependencies.

## Directory structure

```
own_theme/
  index.html                        landing/sitemap page linking every category
  assets/
    css/        bootstrap.min.css, font-awesome.min.css,
                 esection-theme.css, esection-shell.css  (copied from public/assets)
    js/         jquery.min.js, bootstrap.bundle.min.js   (copied)
    fonts/      fontawesome webfont files                (copied)
    vendor/
      select2/, flatpickr/, sweetalert2/                 (copied)
      chartjs/, datatables/, quill/, fullcalendar/,
      sortablejs/, leaflet/                               (new, vendored offline)
  pages/
    esection/        32 real business screens (see inventory A)
    dashboards/      2
    apps/            18
    cards/           5
    charts/          2
    components/      23
    extended/        11
    forms/           16
    layouts/         5
    maps/            1
    misc/            22
    tables/          3
    ui/              3
```

Total: 32 + 113 = 145 static pages.

## Design-system extensions needed

`esection-theme.css`/`esection-shell.css` today cover: shell layout, buttons,
badges, form controls, glass-card, modals, Select2/SweetAlert2 skins. Net-new
CSS needed to cover the wider catalog without visually clashing:
- Chart container card styling (reuses `.glass-card`)
- Kanban board/column/card styling
- Calendar skin (override FullCalendar defaults to match the indigo/emerald
  palette)
- DataTables Bootstrap 5 skin adjustments (align with `.table` styling
  already used by esection's history/batch tables)
- Rich text editor (Quill) skin to match `.form-control` styling
- Timeline, avatar, ratings, tree-view, kanban, and a couple of small
  components with no existing esection equivalent

These extensions are additive to `esection-theme.css`/`esection-shell.css`
copies inside `own_theme/assets/css` — the live app's copies under
`public/assets` are untouched by this work.

## Page inventory

### A. Business pages (32) — mirrors `app/Views`

Auth: `auth-login.html`, `auth-forgot-password.html`, `auth-reset-password.html`
Dashboard: `dashboard.html`
Students: `students-new.html`, `students-import.html`, `students-history.html`, `students-batch-detail.html`
Confirmations: `confirmations.html`, `confirmations-history.html`, `confirmations-batch-detail.html`
Universities: `universities.html`
Regularization: `regularization.html`, `regularization-history.html`
Reminders: `reminders-university.html`, `reminders-university-history.html`, `reminders-university-batch-detail.html`, `reminders-student.html`, `reminders-student-history.html`
Settings: `settings.html`, `settings-users.html`, `settings-access-rights.html`, `settings-academic-years.html`, `settings-courses.html`, `settings-institute.html`, `settings-mail.html`, `settings-numbering.html`, `settings-letter-templates.html`, `settings-backup.html`, `settings-features.html`, `settings-activity-log.html`
Bulk email: `bulk-email.html`, `bulk-email-log.html`

### B. Dashboards (2)
`dashboard-analytics.html`, `dashboard-ecommerce.html`

### C. Apps (18)
`app-calendar.html` (FullCalendar), `app-chat.html`, `app-ecommerce-checkout.html`,
`app-ecommerce-details.html`, `app-ecommerce-shop.html`, `app-ecommerce-wishlist.html`,
`app-email.html`, `app-file-manager.html`, `app-invoice-add.html`, `app-invoice-edit.html`,
`app-invoice-list.html`, `app-invoice-preview.html`, `app-invoice-print.html`,
`app-kanban.html` (SortableJS), `app-todo.html`, `app-user-edit.html`, `app-user-list.html`,
`app-user-view.html`

### D. Cards (5)
`card-basic.html`, `card-advance.html`, `card-actions.html`, `card-statistics.html`, `card-analytics.html`

### E. Charts (2) — both Chart.js
`charts-basic.html`, `charts-advanced.html`

### F. Components (23) — Bootstrap 5 native + vanilla JS, no new deps
`component-alerts.html`, `component-avatar.html`, `component-badges.html`,
`component-breadcrumbs.html`, `component-bs-toast.html`, `component-buttons.html`,
`component-carousel.html`, `component-collapse.html`, `component-divider.html`,
`component-dropdowns.html`, `component-list-group.html`, `component-media-objects.html`,
`component-modals.html`, `component-navs.html`, `component-pagination.html`,
`component-pill-badges.html`, `component-pills.html`, `component-popovers.html`,
`component-progress.html`, `component-spinner.html`, `component-tabs.html`,
`component-timeline.html`, `component-tooltips.html`

### G. Extended components (12) — vanilla JS / native browser APIs, no new deps
`ext-block-ui.html`, `ext-clipboard.html`, `ext-context-menu.html`,
`ext-drag-drop.html`, `ext-i18n.html`, `ext-media-player.html`, `ext-ratings.html`,
`ext-sliders.html`, `ext-swipe-gallery.html`, `ext-sweet-alerts.html`
(SweetAlert2, already vendored), `ext-tour.html`, `ext-tree.html`
(Reference's dedicated Toastr page is not ported separately — its concept is
already covered by `component-bs-toast.html`, Bootstrap's native toast. Its
Swiper page becomes `ext-swipe-gallery.html`, a vanilla-JS touch/swipe image
gallery — same concept, no new library.)

### H. Forms (16)
`form-input.html`, `form-input-groups.html`, `form-input-mask.html`,
`form-number-input.html`, `form-textarea.html`, `form-checkbox.html`,
`form-radio.html`, `form-switch.html`, `form-select.html` (Select2, already
vendored), `form-date-time-picker.html` (Flatpickr, already vendored),
`form-file-uploader.html`, `form-repeater.html`, `form-wizard.html`,
`form-validation.html`, `form-layout.html`, `form-quill-editor.html` (Quill)

### I. Layouts (5)
`layout-blank.html`, `layout-boxed.html`, `layout-collapsed-menu.html`,
`layout-empty.html`, `layout-without-menu.html`

### J. Maps (1)
`maps-leaflet.html` (Leaflet — see tile caveat above)

### K. Misc (22)
`page-account-settings.html`, `page-profile.html`, `page-pricing.html`, `page-faq.html`,
`page-blog-list.html`, `page-blog-detail.html`, `page-blog-edit.html`,
`page-knowledge-base.html`, `page-kb-category.html`, `page-kb-question.html`,
`page-misc-error.html`, `page-misc-not-authorized.html`,
`page-misc-under-maintenance.html`, `page-misc-coming-soon.html`,
`page-auth-login-v1.html`, `page-auth-login-v2.html`,
`page-auth-register-v1.html`, `page-auth-register-v2.html`,
`page-auth-forgot-password-v1.html`, `page-auth-forgot-password-v2.html`,
`page-auth-reset-password-v1.html`, `page-auth-reset-password-v2.html`

### L. Tables (3)
`table-bootstrap.html`, `table-datatable-basic.html`, `table-datatable-advanced.html`
(reference's separate ag-Grid page is folded into `table-datatable-advanced.html`
— a second, heavier grid library isn't warranted for one showcase page)

### M. UI reference (3)
`ui-colors.html`, `ui-typography.html`, `ui-icons.html` (esection's own Font
Awesome 4 set, in place of the reference's Feather-icons page)

## Content rules (all pages)

- Full markup per page (sidebar with all nav items + correct active state,
  topbar, page body) — no includes, no templating, fully self-contained.
- Business-page dynamic content → realistic placeholder/sample data.
- Forms: every field present, `action="#"`, no real submission.
- JS: generic chrome interactivity (sidebar rail/drawer, dropdowns, modals,
  Select2/Flatpickr/SweetAlert2 init) wired everywhere. Page-specific AJAX
  business logic (`ajax_students_new_js` etc.) is *not* ported — there's no
  backend for it to call.
- Professional, polished visual design throughout — this is a portfolio-
  quality deliverable, not a rough scaffold.

## Verification

- Every page opens standalone (file:// or a static server) with no console
  errors, no missing asset 404s.
- Nav active-state correct per page; sidebar/topbar identical in every file
  within the same shell variant.
- Responsive behavior (mobile drawer, icon rail) works on every page that
  uses the full app shell.
- Spot-check each new library integration (chart renders, table sorts,
  calendar navigates, kanban drags, editor types, map loads tiles) at least
  once per library.

## Next step

Detailed phase-by-phase implementation plan via the writing-plans skill.
