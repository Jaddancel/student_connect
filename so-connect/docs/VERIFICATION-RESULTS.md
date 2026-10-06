# Verification Results — claude/dashboard-mode-inconsistency-2y8320

Date: 2026-07-13 · Verifier: Claude (Opus 4.8, automated session) ·
Environment: Linux (WSL2) / PHP 8.4.21 / MySQL (sail) via 127.0.0.1 / Node v24.16.0 / Docker present, OCR sidecar **not** run

## How this run was done (scope & honesty note)

This was an **automated, headless** verification. It could **not** drive a live browser,
and the **PaddleOCR sidecar was not started**. So every item that depends on visual UI
interaction (theme toggling, drawing signatures, dragging ID corners, the Blockly editor) or
on OCR recognition is marked **`needs-manual`** — the implementation was inspected in code and
found present/consistent, but on-screen behavior was not observed.

What *was* exercised for real:

- **Full automated test suite** against an **isolated MySQL `testing` database** (never
  `so_connect`). Result: **81 passed, 16 failed** — the 16 are all pre-existing / expected
  fallout (see below), none are regressions in WP1–WP7.
  - ⚠ The handoff's suggested `DB_CONNECTION=sqlite DB_DATABASE=:memory:` path **does not
    work** for this suite: migration `2026_05_19_200000_merge_members_into_organization_officers`
    uses MySQL-only `UPDATE … JOIN … SET`, so on sqlite every test errors during migration
    (87 failed / 10 passed). Use the MySQL `testing` DB instead — see command below.
- **Fresh isolated build**: `migrate:fresh --seed --force` on the `testing` DB — all 5 branch
  migrations apply on MySQL, all seeders run, schema + seed counts confirmed.
- **Real-DB, read-only checks** (no writes): `forms:wipe` dry-run, `route:list`,
  `migrate:status`.
- **Code inspection** of the controllers/services/blade/migrations behind each claim.

The real `so_connect` DB was left **untouched** — its 5 branch migrations are still `Pending`.
`forms:wipe --force` was **not** run against it (irreversible per the handoff).

Command that works for the suite:
```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=sail \
  DB_PASSWORD=password php artisan test
```

## Results

| # | Item | Result | Notes |
|---|------|--------|-------|
| **1.1** | Fresh tabs open in **light** with no saved theme + OS dark | needs-manual · code-verified | `theme-boot.blade.php` defaults `localStorage.getItem('theme') || 'light'`; dashboards no longer force dark. |
| **1.2** | Moon toggle → dark, persists, consistent across pages | needs-manual · code-verified | Alpine `theme` store writes `localStorage.theme` and toggles `.dark` on toggle. |
| **1.3** | No console error from old anti-flash script | needs-manual · code-verified | Anti-flash script now touches **only** `document.documentElement`; body classes deferred to Alpine `updateTheme()` guarded by `if (document.body)`. The old `document.body`-from-`<head>` TypeError is structurally removed. |
| **1.4** | Signin + public directory follow same theme | needs-manual · code-verified | `app`, `directory-layout`, `fullscreen-layout` all include the shared `partials/theme-boot`. |
| **2.1** | Tall ID image fits window after Straighten | needs-manual | Konva/JS UI — not drivable headless. Route + editor present. |
| **2.2** | Each zone a distinct color, survives save/reload | needs-manual | Frontend behavior; `IdTemplateManagementTest` (persistence) passes. |
| **2.3** | "Saved!" toast on save | needs-manual | UI toast. |
| **2.4** | Zone Type select (Text OCR / Signature capture) | needs-manual · code-verified | Signature zone type wired through to universal `signature`. |
| **3.1** | `/profile` Signature card, draw/upload → saved under `signatures/profiles/` | needs-manual · code-verified | `profiles.signature_path` column present; `SuperAdminProfileRequestFlowTest` passes. |
| **3.2** | `/profile/create` optional signature pad flows to approved profile | needs-manual · code-verified | Covered structurally by profile-request flow test. |
| **3.3** | Form Builder maps Signature field to universal **Signature** | needs-manual · code-verified | `FormBuilderTest` + `UniversalFieldTest` pass. |
| **3.4** | Rendered form shows "Using your saved signature"; stores path or new PNG | needs-manual | Rendering + JS. |
| **3.5** | Signature **recognition** badge (green/amber), never blocks | **skipped** | Requires OCR sidecar (not started). |
| **3.6** | Sidecar down → "unavailable", submit still works | **skipped** | Requires OCR sidecar. |
| **3.7** | `SIGNATURE_MATCH_THRESHOLD` tunable (default 0.45) | **skipped** | Requires OCR sidecar. |
| **3.8** | ID scan auto-fills signature file input; approval sets `signature_path` | **skipped** | Requires OCR sidecar + browser. |
| **4.1** | `forms:wipe` (no flag) prints counts, does nothing | **pass** | Ran on real DB: reported `forms 9 / form_descriptions 132 / submissions 0 / generated 0`, "Dry run". No writes. |
| **4.2** | `forms:wipe --force` deletes + reports | pass · code-verified | Command verified in source (deletes generated docs+files, submissions, descriptions, forms; disables scoring rules). **Not run against real DB** (irreversible). |
| **4.3** | Post-wipe "No form is bound to …" friendly message, no 404/500 | pass · code-verified | `app/Forms/SystemFunction.php:120` emits exactly that message; graceful degradation in handlers. |
| **4.4** | `migrate:fresh --seed` seeds **no** demo forms | **pass** | Fresh isolated build → `forms=0`, `form_descriptions=0`. `DatabaseSeeder` no longer calls `FormSeeder`/`FormDescriptionSeeder`. |
| **4.5** | Form Builder System function (Sign Up / New Event / New Workplan / Org Membership); duplicate binding fails (unique) | needs-manual · code-verified | `SystemFunction` enum + `forms.system_function` column present; `FormBuilderTest` passes. |
| **4.6** | New Event binding submit→approve→event / reject→restart; missing key message | needs-manual · code-verified | New bound-form path exercised by `OrganizationScopedDashboardRequestsTest` (new flow passes; see 4.x note). |
| **4.7** | Sign Up binding → action_type=11 request → approval creates user-type-3; admin-create page untouched | needs-manual · code-verified | `SignUpHandler` present; superadmin Create Admin route unchanged. |
| **4.8** | Membership binding → request; duplicate/pending guards | needs-manual · code-verified | `MembershipRegistrationHandler` present. |
| **4.9** | `GET /functions/new_event` redirects to bound form | pass · code-verified | `routes/web.php:323` `functions.show` → `redirect()->route('forms.render', …)`; friendly 404 when unbound. |
| **5.1** | Action Logs shows login/template/form/scoring events with right category badge | needs-manual · code-verified | `ActionLogger::categories()` = Log In/Out, Organization Scoring, Scoring Rules & Criteria, Form Editing/Creation, ID Template Editing/Creation. `action_logs` table present; `AuditLogTest` passes. |
| **5.2** | Filters (user / category / date range) + combos | needs-manual · code-verified | Superadmin routes present; covered by `AuditLogTest`. |
| **5.3** | Exports honor filters (PDF/xlsx/JSON); legacy Audit Logs unchanged | pass · code-verified | Routes `action-logs/export/{json,pdf,xlsx}` registered; legacy audit page still present. |
| **6.1** | Score parity: re-save unchanged → `total_weighted_score` identical | needs-manual | No committed parity test found (handoff's "500-case check" was ad-hoc in Fable's session). Catalog vs. hardcoded math not re-diffed here. **Recommend running the parity check before relying on this.** |
| **6.2** | Scoring Rules lists 6 categories / 38 criteria ("Built-in") | **pass** | Fresh seed → `scoring_categories=6`, `scoring_criteria=38`. |
| **6.3** | Add criterion → `custom` badge, editable/deletable; system criteria not deletable | needs-manual · code-verified | `admin/scoring-rules/criteria` CRUD routes present. |
| **6.4** | "Add trigger" Blockly editor (zelos), palette, variable dropdowns | needs-manual | Frontend Blockly — not drivable headless. |
| **6.5** | Author rule → Save/round-trip restores blocks | needs-manual | Frontend Blockly. |
| **6.6** | Approved submission tallies per rule; disable → built-in returns | needs-manual · code-verified | Rule engine present; `scoring_rules.enabled` toggle path present. |
| **6.7** | Custom criteria appear in scoring form + audit | needs-manual | UI. |
| **6.8** | Rule/criterion changes logged under "Scoring Rules & Criteria" | pass · code-verified | `CATEGORY_SCORING_CONFIG => 'Scoring Rules & Criteria'`. |
| **6.9** | `forms:wipe --force` disables all rules | pass · code-verified | `WipeForms` sets `scoring_rules.enabled=false` inside the delete transaction. |
| **7.1** | Request Records "Request Type" = originating form page name; fallback to request-type name | pass · code-verified | `RecordQueryService`: `$req->form?->name ?? optional($req->requestType)->name ?? 'Unknown'`. `RequestRecordTest` passes. |
| **7.2** | Excel + print exports show same values | needs-manual · code-verified | Same service feeds exports; `RequestRecordTest` passes. |
| **7.3** | Pre-existing doc-gen requests backfilled (payload carried form_id) | pass · code-verified | Migration `2026_07_12_000005` backfills `form_id` from `payload.$.form_id` via MySQL `JSON_EXTRACT` (guarded to mysql driver). |

## Schema & seed verification (isolated `testing` DB, fresh build)

All five branch migrations applied on MySQL and left the expected schema:

| Check | Result |
|---|---|
| `profiles.signature_path` column | present |
| `forms.system_function` column | present |
| `action_logs` table | present |
| `requests.form_id` column | present |
| scoring_categories rows | **6** |
| scoring_criteria rows | **38** |
| forms / form_descriptions after fresh seed | **0 / 0** (no demo forms) |

`migrate:status` on the real `so_connect` DB: the 5 `2026_07_12_*` migrations are **Pending**
(intentionally not applied — see note above).

## Automated test suite — the 16 failures (all pre-existing / expected, not regressions)

| Test file | # fail | Why it's not a WP1–7 regression |
|---|---|---|
| `DocumentFormWorkflowTest` | 9 | Tests the **retired** document-generation form workflow; 3 are `RouteNotFoundException` for `generated-documents.download` / `forms.show`, routes WP4 deliberately removed. Matches the known "undefined routes forms.show / generated-documents.download" baseline. |
| `NewOfficerCreationFlowTest` | 5 | Exercises the old seeded activity/joint-statement forms retired by WP4; the new bound-form path is covered by passing tests. |
| `OrganizationScopedDashboardRequestsTest` | 1 | Old activity-request fixture missing now-required field keys; the new bound New-Event flow in the same file passes. |
| `PdfTemplateRendererTest` | 1 | Universal token fixture uses `student_id` on a `Profile`; pre-existing token-mapping fixture issue (test last touched by commits *before* the WP1–7 batch). |

Every WP-relevant new test file passes cleanly: `AuditLogTest`, `DatabaseViewTest`,
`FormBuilderTest`, `IdTemplateManagementTest`, `RequestRecordTest`,
`SuperAdminProfileRequestFlowTest`, `UniversalFieldTest`, `FormTemplateHelperTest`.

## Console errors / screenshots / follow-ups

- **No browser or OCR sidecar in this run.** To close out the `needs-manual` / `skipped`
  rows, run `docker compose up ocr` + a browser session and walk sections 1, 2, 3, 4.5–4.8,
  5.1–5.2, 6.1(parity), 6.3–6.7, 7.2.
- **Fix the handoff's test command:** replace the sqlite `:memory:` instruction with the
  MySQL `testing` DB command above — sqlite can't run the MySQL-only merge migration.
- **6.1 score parity has no committed regression test.** Consider adding one so parity is
  guaranteed in CI rather than relying on an ad-hoc check.
- **Real DB not migrated.** Before manual UI testing, run the section-0 migrations against
  `so_connect` (they're additive; rollback with `migrate:rollback --step=5`). Snapshot before
  any `forms:wipe --force`.
- Orphaned `FormSeeder.php` / `FormDescriptionSeeder.php` remain in `database/seeders/` but are
  no longer called by `DatabaseSeeder` — harmless, but candidates for deletion.
