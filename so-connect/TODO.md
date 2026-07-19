# Implementation Tracker — June 18 Feature Set + Builder UX Fixes

Mirrors `docs/PLAN_JUNE18_IMPLEMENTATION_HANDOFF.md`. WP = work package. Each phase must be
green (tests + manual QA) before the next starts. Check items off as work lands.

Legend: `[ ]` todo · `[~]` in progress · `[x]` done

---

## Phase 0 — Tracking
- [x] WP0.1 Commit handoff plan → `docs/PLAN_JUNE18_IMPLEMENTATION_HANDOFF.md`
- [x] WP0.2 Create this `TODO.md`

## Phase 1 — Foundations: settings storage, settings page, runners
- [x] WP1.1 Migration `create_app_settings_table` + `App\Models\AppSetting` (cached get/put)
- [x] WP1.2 Migration `add_notify_on_login_to_users_table` (bool default true)
- [x] WP1.3 `SettingsController` + `GET /settings` + per-section POST routes
- [x] WP1.4 `pages/settings.blade.php` role-gated cards (Account / Administrator / Notification / Backup)
- [x] WP1.5 Repoint user-dropdown "Account settings" + add MenuHelper "Settings" item
- [x] WP1.6 `ActionLogger` category `settings`
- [x] WP1.7 supervisord `[program:schedule]` + `[program:queue]`
- [x] WP1.8 Test `SettingsPageTest` (role gating, writes + action_logs) — 9 green

## Phase 2 — Password change + login-notification email
- [x] WP2.1 Extract `app/Rules/StrongPassword.php`; reuse in PasswordChangeController + settings
- [x] WP2.2 `SettingsController@updatePassword` (current pw, StrongPassword, differ-from-current)
- [x] WP2.3 Shared Alpine password-requirements partial (`<x-password-requirements/>` + `partials/password-policy-script`; first-login page refactored to reuse)
- [x] WP2.4 `SendLoginNotification` listener + queued `LoginNotificationMail` + mail view (registered in AppServiceProvider; guards on notify_on_login + user_email)
- [x] WP2.5 Tests: `PasswordChangeSettingsTest` (4), `LoginNotificationTest` (2) — green

## Phase 3 — DB backup & restore (super user)
- [x] WP3.1 `spatie/laravel-backup ^9.3`; `config/backup.php` DB-only; `backups` disk
- [x] WP3.2 `Admin/BackupController` + `/superadmin/backups` routes + view (list/backup/download/delete)
- [x] WP3.3 Restore flow (`BackupService::restore`: safety backup, extract SQL, pipe to mysql, audit)
- [x] WP3.4 `AutoBackup` (`backup:auto`, interval-aware) + hourly schedule + daily `backup:clean`
- [x] WP3.5 mysql-client verified in docker/8.5/Dockerfile ($MYSQL_CLIENT)
- [x] WP3.6 Test `BackupManagementTest` (6 green)

## Phase 4 — Builder up/down buttons, sticky panels, per-form icons
- [x] WP4.1 Remove SortableJS (dep + lockfile); add `moveRow` / `moveField` / `moveFieldAcross`
- [x] WP4.2 Blade: ▲▼ (rows, fields) + ◀▶ (fields across columns), disabled at bounds
- [x] WP4.3 Sticky left palette (right panel + Step 2 PDF editor already sticky)
- [x] WP4.4 Migration `add_icon_to_forms_table`; MenuHelper `iconNames()` + per-form sidebar icon
- [x] WP4.5 Builder Details step icon picker; `FormBuilderController` validates + persists icon
- [x] WP4.6 Tests: extend `FormBuilderTest` (icon persisted/validated; layout shape); fixed a pre-existing MySQL-JSON key-order brittleness (`toBe`→`toEqual`)

## Phase 5 — Organization Accreditation (rename, system function, conditions, lifecycle)
> DONE (compliance model = "approved required-form request on/before deadline", decision #9).
> Recognition form is now bound to the org_accreditation system function; its handler reuses the
> generic doc-generation request so the existing review flow is unchanged. 20 tests green.
- [x] WP5.1 `SystemFunction::ORG_ACCREDITATION` + `OrgAccreditationHandler` (server re-check → generic doc-gen request)
- [x] WP5.2 Seeder rename → "Organization Accreditation" + bind; data migration for live installs; MenuHelper keeps it in officer+admin menus
- [x] WP5.3 Conditions AST @ `AppSetting['accreditation.conditions']` + editor (Settings admin card: pick required forms)
- [x] WP5.4 `AccreditationService` — deadline/window/grace + conditions/requiredFormIds/isCompliant/missingFormIds/evaluate (6 tests green). Compliance = approved required-form request on/before deadline (decision #9)
- [x] WP5.5 Gating via handler re-check (authoritative) + login block + danger card (standalone conditions panel folded into the danger card)
- [x] WP5.6 Migration `add_accreditation_status_to_organizations_table` (accreditation_status + accreditation_disabled_at)
- [x] WP5.7 `accreditation:enforce` command (report/disable/purge; --disable scheduled daily; purge opt-in)
- [x] WP5.8 `AccreditationService::purge()` (child-first, FK-safe) + guarded artisan + super-admin UI action
- [x] WP5.9 Login block (EnsureOrganizationAccredited + /org-suspended) + danger card (<x-accreditation-warning/>) + landing-page exclusion
- [x] WP5.10 Restore UI `/superadmin/organizations` (list/restore/purge)
- [x] WP5.11 Tests: AccreditationServiceTest + AccreditationEnforcementTest (compliance/lifecycle/enforcement/middleware/conditions/danger-card/handler) — 20 green

## Phase 6 — Signature recognition (registry + SigNet + auto-enroll)
- [ ] WP6.1 Migration `create_signature_references_table` + `SignatureReferenceService`
- [ ] WP6.2 Wire writers through service; `SignatureVerificationController` reads registry; backfill
- [ ] WP6.3 Super-admin `/superadmin/signature-references` maintenance page
- [ ] WP6.4 SigNet in sidecar (torch cpu + sigver) w/ classical fallback env-switch
- [ ] WP6.5 `/signature-identify` additive embedding fields; contract docs updated
- [ ] WP6.6 Unrecognized ⇒ name field ⇒ auto-enroll (all signature fields, server authoritative)
- [ ] WP6.7 Tests: service precedence, controller Http::fake, backfill

## Phase 7 — Waiver form recognition (template, scanner modal, stamp detection)
- [ ] WP7.1 `add_kind_to_id_templates_table`; `ZonePayloadValidator`; stamp zone type
- [ ] WP7.2 Waiver builder stage (new_event forms) w/ Konva editor; `Admin/WaiverTemplateController`
- [ ] WP7.3 `FieldType::WAIVER_SCAN` + scanner modal + shared `camera-capture.js`
- [ ] WP7.4 `POST /waiver-scan` preflight + `WaiverValidationService` + `config/waiver.php`
- [ ] WP7.5 Server re-validation at submit; store under `waivers/Y/m/`; type-2 review UI
- [ ] WP7.6 Sidecar `POST /waiver-scan` (text/signature/stamp); contract docs
- [ ] WP7.7 Tests: PHP validation/CRUD; optional python stamp pytest

---

## Verification
- Automated: Pest suite per phase; sidecar mocked with `Http::fake`. Run `php artisan test` after each phase.
- Manual QA: `docs/VERIFICATION-HANDOFF-2.md` WP checklist (written per phase).
