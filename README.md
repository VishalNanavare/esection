# E-Section

Eligibility verification management for a distance-learning institute.

When a candidate is admitted on the strength of a qualification awarded by
another university or board, that qualification has to be verified with the
body that issued it before the admission is regularised. In practice that
means letters going out to dozens of universities, chasing the ones that never
reply, recording the confirmations that do come back, and knowing at any moment
which candidates are still unverified.

E-Section is the system of record for that process. It replaces the
spreadsheets and Word templates the work used to run on.

---

## The workflow

The modules map onto the real sequence, so the app reads in the order the work
actually happens:

```
Candidate admitted
        │
        ▼
  ┌────────────────┐  documents recorded, eligibility case number assigned
  │    Students    │
  └───────┬────────┘
          │  dispatch letter → issuing university
          ▼
  ┌────────────────┐   no reply?    ┌────────────────────────┐
  │  Universities  │───────────────▶│ Reminders — University │
  └───────┬────────┘                └────────────────────────┘
          │  reply received
          ▼
  ┌────────────────┐  documents missing?  ┌────────────────────────┐
  │  Confirmations │─────────────────────▶│ Reminders — Candidate  │
  └───────┬────────┘                      └────────────────────────┘
          │  irregularity to resolve
          ▼
  ┌────────────────┐
  │ Regularization │  eligibility regularised, case closed
  └────────────────┘
```

---

## Features

### Candidate records

- Batch entry — one row per candidate, up to 200 in a single submission
- Eligibility case numbers generated against a configurable prefix, with
  collision handling so two clerks working at once cannot claim the same number
- Full edit / delete history, with every batch retrievable
- **Excel import** — upload an `.xlsx`, map its columns to the candidate fields,
  preview what will be written, then commit. Column mappings are remembered
  between imports. Validated by magic bytes rather than MIME type, because
  Excel workbooks are zip files and report inconsistent MIME types.
- Blank import template download

### Universities and colleges

- Reference register of issuing universities with addresses, fees and
  in-favour-of details used to populate letters
- Activate / deactivate rather than delete, so historic records keep pointing
  at something real
- Searchable pickers across the app backed by shared endpoints

### Confirmations

- Record verification replies against a per-candidate checklist — migration
  certificate, previous degree, statement of marks, letter number and date
- Track where the confirmation was received from, plus clarification and
  name-change categories (gazette, marriage certificate)
- Eligibility confirmation letters as PDF

### Regularization

- Record and issue regularization letters where documents have been reviewed
  and the eligibility accepted
- Full history with export

### Reminders

- **To universities** — chase unreturned verifications in batches, with
  first/second/final reminder wording, and notes recorded per batch
- **To candidates** — chase missing original documents, listing exactly which
  documents are outstanding

### Documents

- Six letter templates rendered to PDF: dispatch, dispatch (accounts),
  confirmation eligibility, regularization, university reminder, candidate
  reminder
- Templates are **editable in the app**, not in code. Each has a subject, body
  and closing, with `{tokens}` substituted at render time
- Institute letterhead and logo upload, signatory name and designation, and a
  configurable blank space reserved for a wet-ink signature

### Bulk email

- Send a reminder to every university or every candidate in a filtered list
- SMTP settings configured in the app
- Per-recipient send log with sent/failed status and filtering

### Reporting

- Dashboard totals — candidates, confirmed, pending, universities — plus a
  per-stream breakdown
- Excel export on every list screen, generated as real `.xlsx`

---

## Access control

Two roles. **Admin** has everything. **Staff** get exactly what they are
granted.

Permissions are per action, not per page — 32 of them across six modules,
expressed as `module.action`:

| Module | Actions |
|---|---|
| Students | view, create, edit, delete, import, export, print |
| Universities | view, create, edit, toggle, export |
| Confirmations | view, create, delete, export, print |
| Regularization | view, create, edit, delete, export, print |
| Reminders — University | view, create, export, print |
| Reminders — Candidate | view, create, delete, export, print |

So a clerk can be given "record confirmations but never delete one", or "read
the student register and export it, but not edit it". Every permission maps to
a route that exists — there are no decorative checkboxes.

`app/Config/Permissions.php` is the single source of truth: the validation, the
Access Rights UI and the sidebar all derive from it.

---

## Administration

Under **Settings**:

| Screen | Purpose |
|---|---|
| Users | Create staff, reset passwords, activate / deactivate |
| Access Rights | The permission matrix |
| Institute | Name, address, contact, logo, letterhead, signatory |
| Letter Templates | Edit the six PDF letter bodies |
| Academic Years | Define years, set the current one |
| Courses | Course register |
| Numbering | Eligibility case number prefix |
| Mail | SMTP configuration |
| Features | Toggle Excel export, Excel import, bulk email and delete on or off globally |
| Backup | Database backups |
| Activity Log | Who changed what |

### Backups

- Full `mysqldump` wrapped in an **AES-256 password-protected ZIP** — the
  rescue copy. Without the password the archive cannot be opened.
- A separate plain Excel workbook of reference and configuration data only,
  for people to read. Candidate records are deliberately excluded from it, and
  password hashes are never exported.
- Configurable retention; only successful backups are recorded, so a row in the
  history always means the file exists and passed its integrity checks.

---

## Built with

- **CodeIgniter 4.7** on **PHP 8.4+**
- **MySQL**
- [Dompdf](https://github.com/dompdf/dompdf) — PDF letters
- [OpenSpout](https://github.com/openspout/openspout) — `.xlsx` read and write
- Bootstrap 5, jQuery, Select2, Flatpickr, SweetAlert2 (all served locally, no CDN)

## Security

**Authentication**

- bcrypt hashing; the session id is regenerated on login and destroyed on logout
- Login throttled on IP **and** username, in the database. `proxyIPs` is empty,
  so `X-Forwarded-For` cannot move the key
- Password changes throttled too — the form verifies the current password, which
  makes it a checker
- Reset tokens: 256-bit CSPRNG, stored hashed, single-use, expiring
- Session cookies are `Secure`, `HttpOnly`, `SameSite=Lax`, bound to the client
  address, with `session.use_strict_mode` forced on so PHP will not adopt a
  client-supplied id

**Authorization**

- Per-action permissions, layered: a route carries both its module's view right
  and the specific action's, and CodeIgniter runs both
- Permission-changing screens are admin-only; a user cannot alter their own role
- Refused requests are logged with the account, the path and what was required

**Input and output**

- CSRF on every state-changing request, tokens randomised
- All output escaped; a Content-Security-Policy with a per-response nonce for
  inline scripts, and `object-src` / `frame-src` / `worker-src` set to `'none'`
- Query parameters are forced to scalars before any controller reads them
- Exports write every string as a literal — a cell beginning `=` is text, never
  a formula in the recipient's Excel
- Uploads validated by content rather than filename: MIME allowlist, real image
  parse, exact dimensions, server-generated name

**Handling**

- Credential parameters are `#[\SensitiveParameter]`, so a password cannot
  reach a log through a stack trace
- Errors shown to users carry authored text only; detail goes to the log
- `HSTS`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`, `Cross-Origin-Opener-Policy`,
  `Cross-Origin-Resource-Policy`

Run `php vendor/bin/phpunit` — the suite includes tests that fail if the CSP
nonce, the header set, the sensitive-parameter attributes, the scalar-query
guard or the formula-safe export are ever removed.

---

## Requirements

- PHP 8.4 or higher, with `intl`, `mbstring`, `json`, `mysqlnd` and `libcurl`
- MySQL 5.7+ / MariaDB
- A web server pointed at `public/`, **not** the project root
- `mysqldump` available on the host, for the backup feature

## Setup

```bash
composer install
cp env .env
```

Then edit `.env`:

| Key | Notes |
|---|---|
| `CI_ENVIRONMENT` | `production` on a real deployment |
| `app.baseURL` | Your site URL, with a trailing slash |
| `database.default.*` | Use a database user scoped to this schema — not `root` |
| `encryption.key` | Generate with `php spark key:generate` |
| `backup.mysqldumpPath` | Absolute path to the `mysqldump` binary |

Nothing environment-specific belongs in `app/Config` — that is what `.env` is
for, and `.env` is never committed.

### Database schema

**Migrations and seeders are not distributed in this repository.**
`app/Database/Migrations/` and `app/Database/Seeds/` are intentionally empty,
so `php spark migrate` will not build the schema for you.

Provision the database separately — restore a dump taken from an existing
deployment via **Settings → Backup**, which produces a complete, AES-256
password-protected SQL archive for exactly this purpose. Create an empty
database first and restore into it by name; the dump is written without a
`CREATE DATABASE`/`USE` header specifically so it cannot be restored over the
wrong schema by accident.

Once the schema is in place, create the first operator account directly in the
`users` table with a bcrypt hash:

```bash
php -r "echo password_hash('choose-a-strong-passphrase', PASSWORD_BCRYPT), PHP_EOL;"
```

Insert it with `role = 'admin'` and `is_active = 1`. Every further account is
created through **Settings → Users**, and reference data (streams, courses,
academic years) through their own settings screens.

If you are adding a schema change, `php spark make:migration` still works — the
directories are kept in the repository so the tooling has somewhere to write.

### Indexes the application expects

`student_details` is queried by case number on the New Entry screen and by
college address when a university is renamed. Without these two indexes both
paths scan the whole table:

```sql
ALTER TABLE student_details ADD INDEX idx_student_case_no (eligibility_case_no);
-- clg_add is varchar(1500); at utf8mb4 that exceeds the 3072-byte key limit,
-- so index a prefix. The longest value in practice is ~180 characters.
ALTER TABLE student_details ADD INDEX idx_student_clg_add (clg_add(191));
```

## Tests

```bash
php vendor/bin/phpunit
```
