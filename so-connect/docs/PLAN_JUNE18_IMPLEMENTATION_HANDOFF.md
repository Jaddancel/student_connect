# Implementation Plan — June 18 Feature Set + Builder UX Fixes (student_connect)

> **Handoff document** — drafted 2026-07-18. Development branch: `claude/plan-review-e23sx3`.
> Sources: `Plan_For_Fable__June_18.md`, `PLAN_SIGNATURE_VERIFICATION.md`,
> `PLAN_STAMP_SIGNATURE_DETECTION.md`, plus product-owner decisions recorded below.
> On implementation start, commit this file and a WP-numbered `TODO.md` tracker to the branch.

## Context

The June 18 plan plus two companion docs (SigNet signature verification; waiver stamp/signature
detection) define a feature batch for the student_connect Laravel app (app root `so-connect/`).
The user added two requests mid-planning: (8) replace the janky SortableJS drag-reorder in the
form-builder canvas with explicit up/down buttons, and (9) make the builder's side cards (field
palette, field settings) float in place while the page scrolls — same treatment for the PDF
template editor.

**Stack**: Laravel 12 (custom PKs like `user_id`, many `$timestamps=false`), Blade + Alpine.js
(TailAdmin), Vite, MySQL 8.4, Sail-style compose (`laravel.test`, `mysql`, `adminer`,
`mailhog`, `ocr`), FastAPI + PaddleOCR sidecar at `docker/ocr/app.py` (CPU-only).
Roles: `user_type` 1=Super Admin, 2=Admin, 3=org officer/president.

## Decisions (confirmed with product owner)

1. **Scope**: all 8 features in one dependency-ordered plan + `TODO.md` tracker.
2. **Accreditation failure**: auto-**disable** at deadline (posts hidden, member accounts
   blocked, org invisible) → **hard purge after grace period** (default 30 days, configurable);
   super admin can restore before purge.
3. **Stamp**: inkless **embossed dry seal** → capture-guidance + CLAHE→gradient→Hough pipeline, CPU.
4. **Backups**: **DB only, local disk**, spatie/laravel-backup; restore replaces DB from chosen
   dump; super user can download dumps; manual + interval-based.
5. **Unrecognized signature** → required owner-name field; name saved **and signature
   auto-enrolled as a reference under that name** (owners may be non-users) ⇒ needs a
   signature-reference registry beyond `profiles.signature_path`.
6. **Accreditation deadline** derives from the existing `semesters` table (next semester
   `starts_at`). Danger card N days before (default 7, type-2 configurable).
7. **Waiver detail matching**: fuzzy (normalize case/whitespace/dates/times; similarity on
   name; equality on normalized date/time; tunable thresholds).
8. **Builder reorder**: remove SortableJS entirely; explicit ▲▼ (rows, fields) and ◀▶
   (fields across columns) buttons.
9. **Compliance is strict**: only an **approved** accreditation entry before the deadline
   counts; orgs whose submissions sit unreviewed past the deadline are disabled (super admin
   can restore).
10. **Sticky side panels**: the form-builder Step-1 palette and field-settings cards must
    float (sticky) while the canvas scrolls; the PDF template editor gets the same behavior.

## Key Codebase Facts (from exploration)

- **Forms**: `forms` (`layout` JSON `{rows:[{columns:[{span,fields:[key]}]}]}`, `pdf_template`
  JSON, `route_name` unique, `system_function` unique nullable, `field_kit`, `is_active/_published`),
  `form_descriptions` (unique `form_id+field_key`, `field_options` JSON, `universal_key`),
  `form_submissions.payload` JSON, `requests` (+`form_id`, `payload`), `approvals`.
  Builder = 3-step Alpine wizard (`pages/admin/form-builder/editor.blade.php` +
  `resources/js/components/form-builder.js`, `Admin/FormBuilderController`). Catalogs:
  `app/Forms/FieldType.php`, `FieldKit.php`, `SystemFunction.php` (sign_up, new_event,
  new_workplan, membership_registration → `app/Forms/Handlers/*` from `FormRenderController@submit`).
- **Org Recognition today**: seeded plain form `field_kit='organization_recognition'`
  (`FormPagesSeeder::seedOrganizationRecognition()`, radio options `[recognition, renewal]`,
  `workplan_id` not required), reviewed via `Admin/RecognitionRequestController`. Not a system function.
- **New-event flow**: `Handlers/NewEventHandler.php` → parent+child `event_plans` + doc-gen
  request → `Admin/ActivityRequestController` (`/admin/activity-requests`) decide → events + mail.
- **Tally editor** (UX model for condition editor): `resources/js/components/tally-editor.js` +
  `pages/admin/scoring/rules/editor.blade.php` + `Admin/ScoringRuleController`;
  AST in `scoring_rules.trigger`, validated by `TriggerValidator`, run by `ScoringRuleEngine`.
- **Scanner stack**: `id_templates` (zones JSON `{name,label,x1,y1,x2,y2,regex,field,color,type:text|signature}`,
  native px, front/back, single default; `Admin/IdTemplateController::validatePayload`);
  Konva editor `resources/js/components/id-template-editor.js` (+`lib/perspective-warp.js`);
  `id-scan-wizard.js` (getUserMedia, finder crop, ≤1600px/2MB normalize) → `POST /id-scan` →
  `app/Services/OcrClient` → sidecar `/scan` (60s timeout, soft-fail empty).
- **Sidecar** (`docker/ocr/app.py`): PaddleOCR global loaded at import; `GET /health`,
  `POST /scan` → `{fields,raw,images}`; `POST /signature-identify` (probe + candidates
  `[{id,image b64}]` → `{ok,match,best:{id,score},threshold}`) — currently classical CV
  (Otsu ink mask → 320×160 → 0.5·NCC + 0.5·ORB), env `SIGNATURE_MATCH_THRESHOLD=0.45`.
  Contract frozen in `docs/ocr-template-contract.md` (must be updated when extended).
  `requirements.txt`: fastapi/uvicorn/pillow/paddleocr/paddlepaddle. No torch.
- **Signature UX today**: `FieldType::SIGNATURE` rendered by `signature-image-field.js` (upload
  + client ink-crop) / profile pad `signature-field.js` (`signature_pad` lib); both debounce
  `POST /signature/verify` (`SignatureVerificationController`, candidates = ≤300
  `profiles.signature_path`) → advisory badge. Storage via `app/Support/SignatureImage`
  → `signatures/profiles/`; approval signatures → `form-signatures/Y/m/`.
- **Auth/infra**: custom `Auth/Login.php`; `Login`/`Logout` events → `LogAuthActivity`
  (writes `login_logs` + `action_logs`). First-login-only `PasswordChangeController`
  (strong regex rules; Alpine checklist view `pages/auth/change-password.blade.php`;
  `users.force_password_change` + `EnsurePasswordChanged`). Audit via
  `app/Services/ActionLogger::log()`. Sync Mailables (`app/Mail/*`); MailHog dev.
  **No settings page** (stubbed "Account settings" item in
  `components/header/user-dropdown.blade.php`), **no scheduler/queue worker**
  (`docker/8.5/supervisord.conf` runs php only; `routes/console.php` has no `Schedule::`),
  **no backup package**. `app/Models/Semester.php` exists (admin CRUD `/admin/semesters`,
  `starts_at`, `current()`, preparation-period helpers). Organizations have **no status flag**
  and no disable/delete flow; org-related tables enumerated below (FKs centralized in
  `2077_03_28_084433_add_foreign_keys.php`, mostly app-level integrity):
  organization_details, organization_officers(+presidents), organization_advisers,
  events(+event_details, event_plans), workplans, posts (has `status`+`is_featured`; landing
  page filters `status='published'`), forms, templates, form_submissions, requests,
  organization_scores (FK cascade), accomplishment_media, evaluations (indirect).
- **Sidebar/icons**: `app/Helpers/MenuHelper.php` builds menus in code; forms hardcode
  `'icon'=>'forms'`; `getIconSvg($name)` = hardcoded name→inline-SVG switch;
  rendered by `layouts/sidebar.blade.php`. Landing page: `LandingPage.php`.
- **Builder drag today**: `wireSortables()` attaches SortableJS to `[data-col-list]`
  (group `builder-fields`, handle `[data-drag]`) + `[data-rows-list]` (`[data-row-drag]`);
  `onDrop`/`onRowDrop` splice model from DOM indices; re-wired after every change.
  Jank root cause: Sortable's DOM mutations fight Alpine keyed `x-for`. SortableJS used
  nowhere else → dep removable. Canvas markup `editor.blade.php` ~L105–153.

---

# Implementation Phases

Order rationale: settings + scheduler are consumed by backup, accreditation, and login-email;
small UI wins (buttons/panels/icons) next; accreditation before signature/waiver because it's
pure Laravel; CV features last (sidecar work, independently testable). Each phase ends green
(tests + manual QA) before the next starts. `TODO.md` (repo root) is created in Phase 0 with
a WP-numbered checklist mirroring these phases and checked off as work lands.

## Phase 0 — Tracking

- Create `TODO.md` at repo root: WP-numbered checklist per phase below (style of
  `docs/VERIFICATION-HANDOFF.md`), updated throughout implementation. Commit this handoff
  plan alongside it.

## Phase 1 — Foundations: settings storage, settings page, scheduler/queue runners

**Storage**
- Migration `create_app_settings_table`: `id`, `key` (string, unique), `value` (JSON nullable),
  timestamps. Model `app/Models/AppSetting.php` with cached static `get($key,$default)` /
  `put($key,$value)` (forget cache on write).
- Migration `add_notify_on_login_to_users_table`: `users.notify_on_login` boolean default true.
- Keys: `accreditation.conditions` (AST, Phase 5), `accreditation.notify_days` (int, default 7),
  `accreditation.purge_grace_days` (int, default 30), `backup.interval_hours` (int, default 24).

**Settings page**
- Route `GET /settings` (auth) + per-section POST routes → new
  `app/Http/Controllers/SettingsController.php`; view `resources/views/pages/settings.blade.php`
  with cards by role:
  - Account (all): password change (Phase 2), login-notification toggle (`notify_on_login`).
  - Administrator (type 2 only): accreditation condition editor (Phase 5 component).
  - Notification (type 2 only): notify-days input.
  - Backup/Restore (type 1 only): interval select + link to `/superadmin/backups` (Phase 3).
- Repoint the stubbed "Account settings" item in `components/header/user-dropdown.blade.php`;
  add a "Settings" item to the Account/General menu group in `MenuHelper`.
- Log every settings write via `ActionLogger` (new category `settings`).

**Runners** (required by Phases 3/5; used by queued mail in Phase 2)
- `docker/8.5/supervisord.conf`: add `[program:schedule]` → `php artisan schedule:work` and
  `[program:queue]` → `php artisan queue:work --tries=3` (autorestart).
- Schedules live in `routes/console.php` (added in later phases).

**Tests**: Feature `SettingsPageTest` (role-gated sections, setting writes + action_logs rows).

## Phase 2 — Password change + login-notification email

- Extract the strong-password rule block from `PasswordChangeController:20-45` into
  `app/Rules/StrongPassword.php`; reuse in both places.
- `SettingsController@updatePassword`: requires `current_password` (validated against
  `user_password` — custom check since the column is non-standard), new password:
  StrongPassword + `confirmed` + **must differ from current** (reject when
  `Hash::check(new, user_password)`). Clears `force_password_change`. Logs
  `ActionLogger` category `auth`, action `password_changed`. Reuse the Alpine requirements
  checklist from `pages/auth/change-password.blade.php` (extract to a shared partial).
- Login email: new listener `app/Listeners/SendLoginNotification.php` on
  `Illuminate\Auth\Events\Login` (registered in `AppServiceProvider` next to `LogAuthActivity`);
  respects `users.notify_on_login`; sends `app/Mail/LoginNotificationMail.php`
  (implements `ShouldQueue` — worker added in Phase 1) + view
  `resources/views/mail/login-notification.blade.php` (time, IP, user agent). try/catch so
  mail failure never blocks login.
- **Tests**: `PasswordChangeSettingsTest` (wrong current pw, weak pw, same-as-old rejected,
  success + audit row), `LoginNotificationTest` (`Mail::fake`, toggle respected).

## Phase 3 — DB backup & restore (super user)

- `composer require spatie/laravel-backup` (v9.x for Laravel 12). `config/backup.php`:
  DB-only (`source.files.include = []`, databases `[mysql]`), destination disk `backups`
  (new local disk in `config/filesystems.php` → `storage/app/backups`, NOT public), sensible
  `cleanup` defaults. Verify `mysqldump`/`mysql` binaries exist in `docker/8.5/Dockerfile`
  (Sail images ship `mysql-client`; add `default-mysql-client` if absent).
- New `app/Http/Controllers/Admin/BackupController.php` + routes under
  `/superadmin/backups` (superadmin middleware) + view `pages/superadmin/backups/index.blade.php`:
  - list dumps (name/size/date), **Backup now** (`Artisan::call('backup:run --only-db')`),
    **Download**, **Delete**, **Restore**.
  - Restore flow (danger-modal, type-the-word confirmation): 1) automatic safety backup,
    2) extract SQL from chosen zip, 3) pipe into `mysql` via Symfony `Process` using
    `config('database.connections.mysql')` creds, 4) log via `ActionLogger` (category `backup`).
- Scheduling: `app/Console/Commands/AutoBackup.php` (`backup:auto`) — runs
  `backup:run --only-db` when newest dump is older than `backup.interval_hours`; scheduled
  hourly in `routes/console.php`; plus daily `backup:clean`.
- **Tests**: `BackupManagementTest` (fake disk listing/delete/download authorization;
  restore path mocked — assert safety backup ordered + process invoked; interval logic unit-tested).

## Phase 4 — Builder up/down buttons, sticky side panels, per-form sidebar icons

**Reorder buttons** (`resources/js/components/form-builder.js` + `editor.blade.php`)
- Remove: `import Sortable`, `wireSortables()`, `onDrop()`, `onRowDrop()`, every
  `$nextTick(() => this.wireSortables())`, the `[data-drag]`/`[data-row-drag]` handles and
  `data-rows-list`/`data-row-item`/`data-col-list` attributes, and `sortablejs` from `package.json`.
- Add model-only methods (Alpine re-renders cleanly from state):
  - `moveRow(rowIndex, delta)` — swap adjacent rows; disabled at bounds.
  - `moveField(key, delta)` — ▲▼: reorder within the column; at a column boundary, move into
    the adjacent row **in the same visual lane** (`min(colIndex, targetRow.columns.length-1)`),
    appending at the bottom when moving up, inserting at top when moving down; no-op at the
    very top/bottom of the canvas; rows left empty are dropped (matches old drag behavior).
  - `moveFieldAcross(key, delta)` — ◀▶ into the adjacent column of the same row; buttons
    rendered only when `row.columns.length > 1`.
- Blade: row header gets ▲▼ (replacing the ⠿ handle) beside the Columns 1-2-3 buttons; field
  chips get ▲▼(+◀▶) beside ✕, all `@click.stop`, `:disabled` + dimmed at bounds.

**Sticky side panels** (`editor.blade.php` Step 1; verify Step 2)
- Root cause found: Step 1's grid (`~L53`) correctly sets `lg:items-start`, but the sticky
  classes sit on the **inner** settings card (`~L159`) whose parent grid item (`~L158`) is
  content-height under `items-start` — sticky has zero travel room, so it never floats. The
  left palette column (`~L55`) has no sticky at all. The correct pattern (already used by the
  PDF template editor's sidebar, `components/form-builder/pdf-template.blade.php:32`) is
  sticky **on the grid item**, whose containing block is the full-height grid area.
- Fix: add `lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto` to the LEFT
  palette grid item (`~L55`); MOVE the same classes from the inner settings card (`~L159`)
  up to its grid item (`~L158`), keeping card visuals on the inner div. Panels taller than
  the viewport scroll internally (that's the `max-h` + `overflow-y-auto` pair).
- Step 2 (PDF template editor): already the target pattern — confirm it floats during manual
  QA after the Step-1 fix and align if any drift is found (same classes, same `top-24` offset
  under the sticky app header).

**Icons**
- Migration `add_icon_to_forms_table`: `forms.icon` string nullable.
- `MenuHelper`: refactor `getIconSvg()` switch into a keyed const map + add
  `iconNames(): array`; menu builders use `$form->icon ?: 'forms'` for both the officer
  "Organization Forms" items and admin per-form request queues.
- Builder Details step (final step): icon picker grid (server passes
  `MenuHelper::iconNames()` + SVGs into editor config; selected key saved with the form).
  `FormBuilderController` validates `icon` ∈ iconNames.
- **Tests**: extend `tests/Feature/FormBuilderTest.php` (icon persisted/validated; layout
  JSON from button-reordered payload unchanged shape).

## Phase 5 — Organization Accreditation (rename, system function, conditions, lifecycle)

**5a. Rename + convert to system function**
- `SystemFunction.php`: add `ORG_ACCREDITATION = 'org_accreditation'` (+label, handler class);
  new `app/Forms/Handlers/OrgAccreditationHandler.php` — server-side re-check of conditions
  (reject unmet), then existing doc-gen request path; stamps the target cycle (next semester id)
  into the submission payload.
- `FormPagesSeeder`: rename seeded form to "Organization Accreditation"
  (`route_name='organization-accreditation'`), set `system_function='org_accreditation'`,
  radio `recognition_type` options reordered **[renewal, recognition]**, `workplan_id`
  field `is_required=true` (kit default). Data migration for existing installs renames the
  live form row (name/route_name/system_function) if present.
- Relabel kit `organization_recognition` → display "Organization Accreditation" (key kept for
  data compat). Update `Admin/RecognitionRequestController` page titles/labels + MenuHelper
  entry text.

**5b. Conditions (type-2 configurable, Tally-style)**
- AST stored at `AppSetting['accreditation.conditions']`:
  `[{id, form_id, min, scope:'semester'}]` = "at least *min* approved submissions of
  *form* since the current semester started". Editor component
  `resources/js/components/accreditation-conditions-editor.js` + settings card — sentence UI
  copied from `tally-editor.js` patterns; saved via `SettingsController` with server
  validation (form exists, min ≥ 1).
- New `app/Services/AccreditationService.php`:
  - `conditions()`, `deadline(): ?Carbon` (next semester `starts_at` via `Semester`),
    `windowStart()` (current semester start),
  - `evaluate(Organization): array` — per-condition met/actual counts (approved `approvals`
    joined through `requests`/`form_submissions` filtered by org, form, window),
  - `isCompliant(Organization)` — has an **approved** accreditation submission for the
    current cycle (decision 9: pending/unreviewed does not count). The conditions panel and
    danger card distinguish "not submitted" from "submitted, awaiting review" so orgs know
    where they stand, but only approval clears the risk.

**5c. Form-page conditions section + submission gating**
- `FormRenderController@show`: for the accreditation form + type-3 user, attach the
  evaluation report. In the form page (`pages/form/render.blade.php` + a new partial):
  top-right button toggles a conditions panel; badge = green check when all met, red with
  unmet count otherwise (reuse `x-ui.alert`/badge styles).
- Submission blocked when unmet: disabled submit client-side **and** authoritative check in
  `OrgAccreditationHandler` (validation error).

**5d. Lifecycle: warn → disable → purge / restore**
- Migration `add_accreditation_status_to_organizations_table`: `status` string default
  `'active'` (`active|disabled`), `warned_at`, `disabled_at`, `purge_at` (all nullable timestamps).
- Command `app/Console/Commands/AccreditationEnforce.php` (`accreditation:enforce`, daily via
  `routes/console.php`), driven by `AccreditationService`:
  1. **Warn**: non-compliant active orgs within `notify_days` of deadline → set `warned_at`.
  2. **Disable**: at/after deadline, still non-compliant → `status='disabled'`,
     `disabled_at=now`, `purge_at=now+purge_grace_days`.
  3. **Purge**: disabled orgs past `purge_at` → `AccreditationService::purge($org)`.
- `purge()` (also exposed as guarded artisan `organizations:purge {id} --force` and a
  super-admin UI action): inside a transaction, delete the org-related rows in child-first
  order (accomplishment_media, evaluations via officers, organization_scores,
  form_submissions, requests+approvals, workplans, event_plans/event_details/events, posts
  (+stored media files), org-scoped forms/templates, organization_advisers,
  presidents/organization_officers, type-3 user accounts belonging to the org,
  organization_details, organization). Runs `backup:run --only-db` first (safety synergy with
  Phase 3), logs via `ActionLogger` (category `accreditation`).
- **Login block for disabled orgs**: after successful attempt in `Auth/Login.php`, if the
  user is type 3 and their org `status='disabled'` → logout + error message. (Accounts are
  hard-deleted only at purge.)
- **Danger card**: global partial (pattern of `partials/semester-alert` in
  `layouts/app.blade.php`) shown to type-3 users of warned/non-compliant orgs from
  `warned_at` window onward: red `x-ui.alert` listing unmet conditions + deadline. Also add
  an entry in `NotificationBellHelper`.
- **Front page**: `LandingPage.php` featured-posts query additionally joins organizations and
  excludes `status='disabled'`.
- **Restore UI**: new super-admin page `/superadmin/organizations`
  (`Admin/OrganizationLifecycleController`): list orgs with status/deadline/purge date;
  actions Restore (re-activate, clear timestamps) and Purge-now (danger-confirmed).
- **Tests**: `AccreditationConditionsTest` (evaluator math on seeded data),
  `AccreditationLifecycleTest` (time-travel: warn/disable/purge transitions, restore,
  login block, landing-page exclusion, gated submission), handler test (unmet ⇒ reject).

## Phase 6 — Signature recognition (registry + SigNet + auto-enroll)

**6a. Reference registry**
- Migration `create_signature_references_table`: `signature_reference_id` PK, `user_id` FK
  nullable (nullOnDelete, unique when set), `owner_name` string nullable (for non-user owners),
  `image_path`, `embedding` JSON nullable, `source` string
  (`manual_enrollment|id_scan|form_upload|named`), `enrolled_by` FK nullable, timestamps.
- New `app/Services/SignatureReferenceService.php` implementing the plan-doc precedence:
  `capture(imagePath, source, ?User $owner, ?string $ownerName)` —
  `manual_enrollment` always overwrites the owner's row; `id_scan`/`form_upload` enroll only
  when the owner has no row; named (non-user) enrollment inserts a `named` row.
- Wire existing writers through the service: `ProfileController::updateSignature` (=
  manual_enrollment; the profile pad is the plan's self-service enrollment — already on-demand),
  `RequestDecisionController::approveWithSignatures` / `SuperAdminController` profile approval.
- `SignatureVerificationController`: candidates now come from `signature_references`
  (id = reference id) instead of ≤300 profiles; response carries the owner display name
  (user name or `owner_name`). Backfill command `signatures:backfill` creates rows from
  existing `profiles.signature_path`.
- Super-admin maintenance page `/superadmin/signature-references`: list (owner, thumbnail,
  source, updated) + delete = the plan-doc "force-clear reference" action.

**6b. SigNet in the sidecar** (contract-compatible swap)
- `docker/ocr/requirements.txt` + `Dockerfile`: add CPU torch (install via
  `--index-url https://download.pytorch.org/whl/cpu`) and the `sigver` package
  (`git+https://github.com/luizgh/sigver`); weights (`signet.pth`) fetched by
  `docker/ocr/download_weights.py` at build (path overridable via `SIGNET_WEIGHTS_PATH`).
- `app.py`: load SigNet once at import (same try/except pattern as PaddleOCR). Shared
  normalization for **every** embedding path: grayscale → Otsu binarize → deskew →
  resize/pad to SigNet's 155×220 canvas (use sigver's preprocessing). Score = cosine
  similarity of embeddings. **Fallback**: if torch/weights unavailable, keep the current
  NCC+ORB scorer (env `SIGNATURE_ENGINE=signet|classical`, auto-fallback with a logged
  warning) — keeps dev/CI green without weights.
- `/signature-identify` extended additively (update `docs/ocr-template-contract.md` +
  `docs/DOCKER-SETUP-CONTEXT.md`): candidates accept `{id, image?, embedding?}`; response
  unchanged (`{ok,match,best,threshold}`) plus optional `probe_embedding` and per-candidate
  embeddings when `include_embeddings` is set — Laravel stores them in
  `signature_references.embedding` to skip re-embedding on later calls.
  `SIGNATURE_MATCH_THRESHOLD` default re-tuned for cosine (conservative start; env-tunable).

**6c. Unrecognized ⇒ name field ⇒ auto-enroll (all signature fields)**
- `signature-image-field.js` / `signature-field.js` + the SIGNATURE case in
  `components/form/fields/field.blade.php`: when debounced verify returns *not recognized*,
  reveal a required text input posted as `{field_key}_owner_name` (hidden + cleared when
  recognized).
- `FormRenderController` (`resolveSignature` path): server is authoritative — after storing
  the signature image, re-run identify; if matched → record match info in payload and ignore
  any name; if unmatched → require `{field_key}_owner_name` (validation error otherwise) and
  call `SignatureReferenceService` to enroll the image under that name (`named` source;
  `id_scan` source when the image came from the ID-scan wizard crop). Name + match status
  stored in `form_submissions.payload` for reviewers.
- **Tests**: `SignatureReferenceServiceTest` (precedence rules), controller tests with
  `Http::fake()` sidecar (match / no-match+name / no-match+missing-name), backfill test.

## Phase 7 — Waiver form recognition (template, scanner modal, stamp detection)

**7a. Waiver template (single, admin-defined)**
- Reuse `id_templates`: migration `add_kind_to_id_templates_table` → `kind` string default
  `'id'` (`id|waiver`); waiver templates are single-sided (no back), and zone `type` gains
  `stamp` (validation relaxed per kind; refactor `IdTemplateController::validatePayload`
  into shared `app/Support/ZonePayloadValidator.php`). `IdTemplate::scannerTemplate()/
  scannerChoices()` scoped to `kind='id'`; new `IdTemplate::waiverTemplate()`.
- Fixed zone set enforced for waiver kind: `body` (text — one big zone containing event
  name/details/date/time), `signature` (signature), `stamp` (stamp). Simpler for admins and
  robust to layout; fuzzy matching searches the OCR'd body text.
- Builder special stage: in `editor.blade.php`, when the form being edited is bound to
  `new_event`, the wizard shows a 4th step "Waiver template" (steps: Online form → Printed
  template → **Waiver template** → Details) embedding the Konva zone editor
  (`id-template-editor.js` re-used with `kind='waiver'` config: no back side, stamp type in
  the palette). Saves via new `Admin/WaiverTemplateController` (admin middleware) using the
  shared validator.

**7b. Required waiver field + scanner modal**
- `FieldType::WAIVER_SCAN = 'waiver-scan'` (special group; unlocked by the `new_event` kit;
  added to the kit's required keys as `waiver`, `is_required` default true). Render:
  `components/form/fields/waiver-scan.blade.php` — a "Scan waiver" button opening an Alpine
  modal with camera capture. New `resources/js/components/waiver-scan.js`; shared camera
  helpers (`mapCoverBoxToFrame`, `normalizePhoto`) extracted from `id-scan-wizard.js` into
  `resources/js/lib/camera-capture.js` and reused by both.
- **Capture guidance is part of the pipeline** (embossed seal): modal shows tilt/angle-light
  instructions before capture; after a failed stamp check the UI prompts "tilt the document
  or angle the light and rescan" — the retry loop is the guidance mechanism (multi-frame
  best-shadow selection deferred; noted in TODO as stretch).
- Preflight: captured photo POSTs to new `POST /waiver-scan` (auth, officer middleware) →
  `WaiverScanController` → `OcrClient::scanWaiver()` → sidecar (7c) →
  `app/Services/WaiverValidationService`:
  - signature present (ink-density result ≥ `waiver.sig_min_ink`),
  - stamp present (score ≥ `waiver.stamp_min_score`),
  - fuzzy details match against the event fields the officer already typed (sent with the
    request): normalized token-set similarity ≥ threshold for `title` within body text;
    dates/times parsed from body (reuse `OcrClient` date-normalization patterns) and compared
    to `target_date`/start-end times after normalization. Thresholds in `config/waiver.php`.
  - Response `{ok, checks:{signature,stamp,details}, reasons[]}` drives the modal UI
    (which check failed + rescan prompt).
- Submission: the captured file stays a normal upload input on the form; at
  `FormRenderController@submit` the waiver is **re-validated server-side** (authoritative,
  stateless — prevents tampering between preflight and submit). On success
  `NewEventHandler` stores it under `waivers/Y/m/` (documents disk) and records
  `waiver_path` + check summary in the submission/request payload; on failure the
  submission is rejected with the failed checks.
- Type-2 review: `pages/admin/activity-requests/show.blade.php` renders the attached waiver
  image + its validation summary.

**7c. Sidecar stamp/signature-presence endpoint**
- New `POST /waiver-scan` in `app.py` (documented in `docs/ocr-template-contract.md`):
  multipart `image` + `template` JSON (same zone-crop plumbing as `/scan`). Per zone type:
  - `text` → PaddleOCR (existing helper) → body text,
  - `signature` → ink-density / connected-components presence:
    `{present, ink_ratio, components}`,
  - `stamp` → CLAHE → Sobel gradient magnitude → Hough circle (contour/ellipse-fit fallback)
    → optional LBP texture score → `{present, score, method, circle?}`.
  All OpenCV/CPU (cv2 already present transitively); env thresholds
  `WAIVER_STAMP_MIN_SCORE`, `WAIVER_SIG_MIN_INK`. Circular seal assumed first (ellipse
  fallback covers the rest) — geometry parameters kept in one tunable block.
- **Tests**: PHP — `WaiverValidationServiceTest` (fuzzy matcher units; `Http::fake` shapes),
  `FormRenderController` waiver-required test, template CRUD/zone-validation tests
  (extend `IdTemplateManagementTest` for `kind`/`stamp`). Python — small pytest with
  synthetic fixtures (drawn ring on textured background) for the stamp scorer (optional but
  cheap, in `docker/ocr/`).

---

# Verification

- **Automated**: Pest suite per phase as listed (existing patterns:
  `tests/Feature/FormBuilderTest.php`, `IdTemplateManagementTest.php`; sidecar calls mocked
  with `Http::fake`). Run `php artisan test` (or `vendor/bin/pest`) after each phase.
- **Manual QA** (docker compose: `laravel.test`, `mysql`, `mailhog`, `ocr`): follow a new
  `docs/VERIFICATION-HANDOFF-2.md` WP-checklist written per phase — e.g. settings toggles,
  login mail visible in MailHog, backup file appears/downloads/restores on a scratch DB,
  builder reordering without drag, palette/settings cards float while the canvas scrolls
  (Step 1 and PDF template step), icon shows in sidebar, accreditation warn/disable/purge on
  a time-shifted semester, signature enroll/verify via fixture images, waiver scan via fixture
  photos POSTed to `/waiver-scan` (no camera in this environment — endpoints tested with
  fixture images; real-camera capture guidance validated on-device later).
- **Sidecar**: `GET /health` + curl fixtures against `/scan`, `/signature-identify`
  (both engines), `/waiver-scan`. SigNet fallback path verified by running without weights.
- Environment note: everything is CPU — the compose stack runs fully in this remote session;
  only real-camera UX and embossed-seal photo quality need on-device validation by the user.

# Risks / Mitigations

- **Torch image size/build time** (~800MB CPU wheels): acceptable per plan docs; fallback env
  keeps the sidecar working without weights, so CI/dev never block on the model.
- **`system_function` unique column on existing installs**: data migration guards the
  accreditation rename/binding; seeder stays idempotent.
- **Login-mail latency**: queued mail + worker; try/catch so login never fails on SMTP.
- **Restore/purge are destructive**: both take automatic safety backups first, require typed
  confirmation (UI) / `--force` (CLI), and are ActionLogger-audited.
- **Embossed-seal detection is capture-bound** (physics): retry-loop guidance UX, tunable
  thresholds, and the admin can re-check visually on the request page (waiver image attached).
- **EnsurePasswordChanged interplay**: settings password change clears `force_password_change`.
- **Purge ordering vs central FK migration** (`2077_..._add_foreign_keys.php`): deletion order
  in `purge()` follows the child-first list above inside a transaction; covered by tests.
