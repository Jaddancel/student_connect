# Implementation TODO

Tracker for the five-feature work on branch `wysiwyg-template-editor`.
Plan: `~/.claude/plans/re-search-function-abundant-sunset.md`.

Legend: `[ ]` not started · `[S]` in progress · `[X]` finished

## Feature 1 — Global search indexes forms by purpose (admins too)
- [X] Move published-forms loop out of the officer-only branch in `DashboardSearchHelper`
- [X] Forms appear in `/api/dashboard-search` for admin (type 2) + superadmin (type 1)
- [X] Searching a form's Purpose text returns that form (purpose already in keywords)
- [X] Officers/presidents still only see forms whose `sidebar_group` targets their role
- [ ] End-to-end verify in running app

## Feature 2 — Form-creator fields show in the PDF template palette
- [X] Root cause: parent reassigns `fields` on remove; child snapshotted it → stale
- [X] Pass live `getFields: () => fields` getter; child reads live via `getFields()`
- [X] `printableFields` robust to fields lacking `field_key`
- [ ] Rebuild assets and re-verify (Step 1 add → Step 2 palette live)

## Feature 3 — ID scan reliability + name parsing
- [X] `scannerTemplate()` falls back to most-recent active when no active+default
- [X] Saving an active template with no existing default makes it default
- [X] Distinct wizard notes (no template vs scanner unavailable)
- [X] `LAST, FIRST MIDDLE` name parser (`OcrClient::expandName/splitFullName`)
- [X] Add `OCR_SERVICE_URL` / `OCR_TIMEOUT` to `.env.example`
- [ ] End-to-end verify with sidecar running

## Feature 4 — Universal fields overhaul
### 4a Trim to fixed profile subset
- [X] Migration: `profiles.course`, `profiles.year_section` (+ backfill from `course_year`)
- [X] `Profile::$fillable` updated
- [X] `UniversalField::catalog()` trimmed to: first/middle/last name, home_address, course, year_section, sex, religion
- [X] ID editor uses profile-only scan fields + `student_id`/`full_name` (org fields excluded)
### 4b New org-scoped fields
- [X] `organization_advisers` table + `OrganizationAdviser` model + `Organization::advisers()`
- [X] Registry gains `source` (profile|org) + `organization` group: adviser, org_president, org_auditor, org_secretary
- [X] Org-aware resolution (`OrganizationField::value`) for president/auditor/secretary from officers
- [X] Thread org context through `FormRenderController::profilePrefill` + PDF path (renderer + doc-gen + preview)
- [X] Form-builder palette renders `organization` group (PDF palette auto via grouped())
- [X] Adviser field renders as datalist dropdown; new names upserted on submit; prints via data-field
- [X] Update `tests/Feature/UniversalFieldTest.php`
- [ ] Build + migrate + verify end-to-end

## Feature 5 — Google sign-in (popup pre-fill)
- [X] `composer require laravel/socialite` (^5.28)
- [X] `config/services.php` google block + `.env.example` keys
- [X] `users.google_id` migration (nullable, unique) — migrated
- [X] `GoogleLinkController` (stateless) + routes (auth), shared admin + officer callback
- [X] Bordered note + "Sign in with Google" button — shared `x-google-link-field` component on both admin-account and officer create forms
- [X] `store()` persists `google_id`, sets `email_verified_at` (both AdminAccountCreation + AdminOfficerCreation)
- [X] `GOOGLE-AUTH-SETUP.md` (in `so-connect/`) with manual Google Cloud + `.env` steps
- [X] Public landing-page "Sign Up with Google" (extends Feature 5, not in original plan):
  - Dropped `auth` middleware from the google redirect/callback routes — stateless, safe to make fully public
  - Landing page auth section: new button opens the same popup, then navigates to `/signup?google_id=&first_name=&last_name=&email=` on success
  - `StudentLeaderDirectoryController::index` reads those query params → `$googlePrefill`; Step 2 form pre-fills first/last name + email and carries a hidden `google_id`
  - `store()` validates optional `google_id`; `RequestDecisionController`'s new-officer approval path persists `google_id`/`email_verified_at` on the created User
- [ ] End-to-end verify (needs real Google OAuth creds in `.env`)

## Cross-cutting
- [S] `npm run build` — CANNOT build in this WSL shell (node only on Windows side; Linux `node_modules` rollup binary incompatible with Windows node). Run from your normal env. Feature 5 needs no build (server-rendered Blade + inline script).
- [X] `php artisan config:clear` (run with `DB_HOST=127.0.0.1`)
- [X] `php artisan migrate` (google_id migration applied; `DB_HOST=127.0.0.1`)
- [ ] Re-seed after any RefreshDatabase test run
- [ ] `php artisan test` (destructive — wipes real DB; not run to avoid the wipe; ~15 pre-existing unrelated failures)
