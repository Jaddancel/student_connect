# Student Connect Platform — Project Context

**Last updated:** 2026-10-05

This file documents the project architecture, Docker setup, and key development patterns for Claude Code sessions.

## Project Overview

**Student Connect** is a Laravel 12 admin dashboard for managing student events, forms, and organizational workflows. It features:
- A drag-drop form builder with WYSIWYG editing and PDF export
- Event request/workplan management with role-based access
- Admin records/audit interface
- ID scanning with zone-based OCR (PaddleOCR sidecar)
- Dynamic option sources for form fields
- Google OAuth integration for admin/officer login

**Tech Stack:**
- **Backend:** Laravel 12, PHP 8.5, MySQL 8.4
- **Frontend:** Alpine.js, Tailwind CSS v4, Vite, SortableJS
- **OCR Inference:** FastAPI + PaddleOCR (Python 3.11)
- **Build:** Vite for JS/CSS, Composer for PHP, Sail for local Docker

**Repository:** https://github.com/student-connect/so-connect  
**Primary branch:** `main`; active development on feature branches like `backend_api`, `wysiwyg-template-editor`

---

## Docker & Local Development

### Running the Stack

The project uses **Laravel Sail** with `compose.yaml`. All services run containerized locally:

```bash
# Start all services (MySQL, Laravel, OCR, MailHog, Adminer)
./vendor/bin/sail up -d

# Seed the database (re-run after test wipes)
./vendor/bin/sail artisan migrate:fresh --seed

# Run tests
./vendor/bin/sail artisan test

# Stop services
./vendor/bin/sail down
```

**Important:** After running tests, the real `so_connect` database is wiped. Always re-seed with the command above before using the UI.

### Services in compose.yaml

| Service       | Image              | Port    | Purpose                              |
|---------------|--------------------|---------|--------------------------------------|
| `laravel.test`| sail-8.5/app       | 80      | Main Laravel app (HTTP)              |
| (Vite dev)    | (same container)   | 5173    | Frontend dev server (HMR)            |
| `mysql`       | mysql:8.4          | 3306    | Application database (persistent)    |
| `ocr`         | (custom)           | 5000    | PaddleOCR inference sidecar          |
| `mailhog`     | mailhog/mailhog    | 1025/8025 | Email catcher + dashboard           |
| `adminer`     | adminer            | 8080    | Database UI (query browser)          |

**Database Volume:** `sail-mysql` persists data across container restarts.

### Environment & Port Forwarding

From `.env.example`:
```bash
APP_PORT=80                  # Laravel HTTP
VITE_PORT=5173              # Frontend dev server
FORWARD_DB_PORT=3306        # MySQL on host
FORWARD_MAILHOG_PORT=1025   # MailHog SMTP
FORWARD_MAILHOG_DASHBOARD_PORT=8025  # MailHog web
OCR_PORT=5000               # OCR service
```

### Database Config in Docker

The MySQL container initializes with:
- Root password: `DB_PASSWORD` from `.env`
- Database: `DB_DATABASE` (defaults to `tailadmin_laravel`, but override in `.env`)
- User: `DB_USERNAME` with `DB_PASSWORD`

Inside the container, the app talks to `mysql:3306`; from the host, use `localhost:3306` or the forwarded port.

**Key:** The testing database is created on startup by `vendor/laravel/sail/database/mysql/create-testing-database.sh` and is wiped on each `artisan test` run.

---

## Frontend Build & Assets

### Vite Setup

The frontend uses **Vite** for hot module reloading and production bundling:

```bash
# Rebuild assets (required when checking out stale branches)
export NVM_DIR="$HOME/.nvm"; [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
./node_modules/.bin/vite build

# Or via npm
npm run build

# Dev server runs inside the container on port 5173
# Accessible at http://localhost:5173 (HMR proxies to the app)
```

**Important:** Windows + WSL: npm resolves to Windows node. Use nvm node instead:
```bash
nvm use 24 && npm run build
```

**Build output:** `public/build/` contains versioned assets. Stale commits before a rebuild mean the browser serves old JS. Always check `public/build/assets/app-*.js` bundle timestamp if drag-and-drop or new UI doesn't work.

### Tailwind CSS v4

Styling uses **Tailwind v4** with custom config. Brands/colors are in `tailwind.config.js`.

---

## OCR Sidecar Service

### Overview

`docker/ocr/` contains a FastAPI + PaddleOCR inference service for zone-based ID scanning. It is **inference-only**—not a training pipeline.

**Service name:** Must be exactly `ocr` (hardcoded in compose.yaml and `.env` as `OCR_SERVICE_URL=http://ocr:5000`).

### Endpoints

- `POST /scan` — scan an image for text zones
- `POST /signature-identify` — identify signature regions
- `GET /health` — health check

### App-to-OCR Communication

The Laravel app uses `app/Services/OcrClient.php` to call the sidecar:
- **Inside Docker:** `http://ocr:5000` (via bridge network)
- **Outside Docker (host OCR only):** `http://localhost:5000`

Configure via `.env`:
```bash
OCR_SERVICE_URL=http://ocr:5000   # Default for docker-compose
OCR_TIMEOUT=60                     # Request timeout in seconds
```

### First Build

**Warning:** The OCR image's first build is slow (~5–10 min). It downloads PaddleOCR detection/recognition models (hundreds of MB) during image creation. Subsequent builds/runs reuse the cached layers.

```bash
./vendor/bin/sail build ocr  # Explicit build if needed
```

### Graceful Degradation

If OCR is unreachable, the app logs a warning and continues—ID scanning fails gracefully without crashing the app.

---

## Key Development Files & Patterns

### Form Builder

- **Editor:** `resources/views/pages/admin/form-builder/editor.blade.php`
- **Alpine.js logic:** `resources/js/components/form-builder.js`
- **Route:** `admin.form-builder.*` (CRUD)

**Features:**
- Drag-and-drop field reordering (SortableJS)
- Field config panel (right sidebar, sticky)
- Field palette (left sidebar, sticky)
- Canvas-based row/column grid layout
- PDF template export/import (WYSIWYG)

**Notable:** Rows are reordered by ▲▼ buttons only; fields drag within/between columns via SortableJS.

**Image fields** accept multiple photos when the field's `multiple` option is on (`max_files`, default 5); see `FieldType::isMultiImage()`. Multi-image values are path arrays and print every image in DOCX templates (a plain `{{key}}` expands into one picture per image; a `{{key#}}` table row repeats per image).

### After Event Report (system function `after_event_report`)

- **Officer page:** `/after-event-reports` (`AfterEventReportController`, sidebar → Organization → After Event Form) lists the org's events that ended ≥ N days ago within the running semester (N = `AppSetting after_event.elapsed_days`, set on the Settings page by user type 2).
- **Rules:** `app/Services/AfterEventReportService.php` (eligibility, semester window, filed status, New Event source submission lookup: Event → EventPlan → Request.payload.submission_id).
- **Filing:** the bound form opens per event (`/forms/{route}?event=ID`); `AfterEventReportHandler` generates the document immediately (no approval). Events from an elapsed semester can't be filed.
- **Tokens:** Step 2 offers `{{eventinfo.*}}` (event record) and `{{event.<new_event_field_key>}}` (original New Event answers), resolved by `app/Forms/AfterEventTokenData.php`.
- **Notifications:** bell items (`NotificationBellHelper::afterEventReportNotifications`) until filed; `after-event:notify` (daily) emails each event's officials once (tracked in `after_event_report_notifications`).

### Report Templates (`app/Reports`, user type 2)

- **Pages:** `/admin/report-templates` (wizard editor: Data tokens → Printed template → Details; `report-template-builder.js`) and `/reports` (generate PDF/Word with "ask at generation" parameters). `/reports` is open to admins and officers; each template's "Available to" audience (`report_templates.audience`: `all` | `admins` | `officers`, default `admins`) decides who sees it (`ReportTemplate::isAvailableTo()`).
- **Data:** `SchemaCatalog` introspects the live schema (FK + `config/reports.php` relations; deny-listed tables/columns are never exposed). `ReportDefinitionValidator` whitelists every identifier; `ReportQueryEngine` compiles definitions to bound Query Builder calls, batch-loading nested groups. A value token's `mode` is `field`, `aggregate` or `compute`; compute holds an `expression` (numbers, sibling value-token names, `+ - * / %`, parentheses) parsed and evaluated by `ReportExpression` (no `eval`), run after its siblings in dependency order; blank reads as 0 and divide-by-zero prints the Else text.
- **Printing:** template slots are `templates` rows with `report_template_id`, edited in the same OnlyOffice draft editor (`shared/printed-template-draft.js`). `ReportDocxRenderer` expands `{{#group}} … {{/group}}` blocks (nested groups re-keyed per row as `group__N.child`) before the normal `{{key}}`/`{{key#}}` fill; `{{profile.*}}`/`{{system.*}}` are universal tokens.
- **Seeded:** `ReportTemplateSeeder` recreates "Registered Organizations" and "Organization Officers" (run `artisan db:seed --class=ReportTemplateSeeder` on existing installs).

### Database & Seeding

- **Migrations:** `database/migrations/`
- **Seeders:** `database/seeders/`
- **Key seeders:**
  - `AdminUserSeeder` — creates test admin user
  - `FieldCatalogSeeder` — field types and metadata
  - `FormSeeder` — sample forms

After git checkout or DB changes:
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

### Authentication & Authorization

- **Guard:** `web` (session-based)
- **Roles:** user_type field (1=SuperAdmin, 2=Admin, 3=Officer, etc.)
- **Authorization:** Gates and Policies in `app/Policies/`

**Google OAuth:**
- Client ID/Secret in `.env` (see `GOOGLE-AUTH-SETUP.md` for setup)
- Callback: `/admin/accounts/google/callback`
- Stored in `oauth_accounts` table

### Routes

- **Admin:** `routes/admin.php`
- **API (internal):** `routes/api.php`
- **Public/auth:** `routes/web.php`

Key admin routes:
- `/admin/form-builder` — form editor
- `/admin/form-builder/preview/{form}` — form preview
- `/admin/records` — audit/records table
- `/admin/export` — bulk export

---

## Testing

### Running Tests

```bash
./vendor/bin/sail artisan test

# Specific test file or method
./vendor/bin/sail artisan test tests/Feature/FormBuilderTest.php
./vendor/bin/sail artisan test --filter=testDragField
```

### Test Database

Tests use the `so_connect_testing` database (created by the MySQL init script). Each test run wipes and re-seeds it.

**After tests, reseed the dev DB:**
```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

### Common Test Patterns

- **JSON columns:** Assert with `toEqual`, not `toBe` (MySQL reorders keys)
- **Integration tests:** Hit real DB via Sail; prefer over mocks
- **Feature tests:** Test full request/response cycle

---

## Troubleshooting Common Issues

### Drag-and-drop doesn't work in the form builder

- **Cause:** Stale Vite build (old `app-*.js` doesn't include SortableJS)
- **Fix:** Rebuild with `./node_modules/.bin/vite build`; hard-refresh browser (Ctrl+Shift+R)

### Database appears empty after page refresh

- **Cause:** Likely ran `artisan test`, which wipes `so_connect`
- **Fix:** Reseed: `./vendor/bin/sail artisan migrate:fresh --seed`

### OCR service unreachable (form fields don't scan)

- **Cause:** OCR container not running or first build in progress
- **Fix:** `./vendor/bin/sail ps` (check status); `./vendor/bin/sail logs ocr` (watch build); wait 5–10 min for first build
- **Non-blocking:** App logs the failure; scanning fails gracefully

### Node PATH issues on Windows (npm resolves to Windows, not WSL node)

- **Cause:** WSL npm finding Windows node_modules
- **Fix:** Use nvm: `nvm use 24 && npm run build`

### Port already in use (e.g., port 80 taken)

- **Fix:** Stop the container (`./vendor/bin/sail down`) or override `APP_PORT` in `.env`

---

## Git & Branch Strategy

- **Main branch:** `main` (stable)
- **Active branches:**
  - `backend_api` — backend feature development
  - `wysiwyg-template-editor` — form builder WYSIWYG
  - Topic branches for specific features

**Commit convention:** Concise messages, e.g., "Add drag-drop field reordering" or "Fix OCR timeout config"

---

## Related Documentation

For deeper dives, see:
- `docs/DOCKER-SETUP-CONTEXT.md` — detailed Docker reference
- `docs/ocr-template-contract.md` — OCR request/response format
- `docs/PLAN_FORM_FIELD_OPTIONS_ARCH.md` — form field options architecture
- `GOOGLE-AUTH-SETUP.md` — Google OAuth setup
- `README.md` — generic TailAdmin Laravel template readme (legacy, replaced by this file)

---

## For Claude Code Sessions

**When starting work:**
1. Check `.env` and Docker status (`./vendor/bin/sail ps`)
2. If DB appears wiped, reseed: `./vendor/bin/sail artisan migrate:fresh --seed`
3. If frontend changes don't show, rebuild: `./node_modules/.bin/vite build` + hard-refresh

**Git state:** Check `git status` and `git log` before any destructive operations; prefer new commits over amends.

**Tests:** Always run `./vendor/bin/sail artisan test` before pushing; reseed the DB after.

**Permissions:** The bot should not auto-commit or push without explicit user approval. Use `git status` and ask before staging broad changes.
