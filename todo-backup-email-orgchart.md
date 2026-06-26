# Implementation Tracker — Backup/Restore, Email Verification Fix, Org Officer Diagram

Tracks the approved plan at `~/.claude/plans/await-for-the-plan-snazzy-cray.md`.
Three independent improvements to the StudentConnect Laravel app (`so-connect/`, Laravel 12, MySQL, Tailwind v4, `user_type` 1=superadmin).

> **Status (updated 2026-06-26):** Part 1 implemented + CLI-verified (backup:run --only-db works in container; schedule registered). Browser smoke-test pending. Parts 2 & 3 code done, browser/flow verification pending.

---

## Part 3 — Per-org two-level officer diagram in PDF export

Replaces the flat "Officer Composition Chart" in `resources/views/exports/org-data-print.blade.php`.
Structure: **President = root (top)**, every other officer on **one shared connected level** beneath, named-role officers ordered first. One diagram per org, print/PDF view only.

- [x] Add org-chart CSS (root/trunk/bus connectors + node header band + body) to the view's `<style>`
- [x] Replace `.chart-section`/`.chart-tree` markup with two-level diagram (root + ordered children, CSS connectors)
- [x] Rank/order officers (President/Chair/Head=root; VP, then Secretary/Treasurer/Auditor, then generic last); fallback to most-senior if no President
- [x] Each node shows role (header band), name, tenure (`Since {member_since}`)
- [x] Handle empty-officer orgs (existing empty-state) + `page-break-inside: avoid` per org (existing `.org-block`)
- [x] NOTE: `Officer::yearTerm()` relation is broken — used `member_since` for tenure, did NOT eager-load yearTerm
- [x] Blade compiles clean (`compileString` + `php -l`)
- [ ] Verify in browser: export `/superadmin/export` & `/admin/export` print for single org and all orgs

## Part 2 — Fix email verification delivery + MailHog test methods

Root cause: `.env` has `MAIL_MAILER=log`, `MAIL_HOST=127.0.0.1:2525` → mail never reaches MailHog (`mailhog:1025`); send errors are silently swallowed.

- [x] `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST=mailhog`, `MAIL_PORT=1025` (config:clear'd; verified `smtp mailhog:1025`)
- [x] Replace empty `catch (\Throwable) {}` with `Log::error(...)` in the 3 invitation senders:
      `RequestDecisionController.php`, `Admin/AdminAccountCreationController.php`, `Admin/AdminOfficerCreationController.php`
- [x] Confirm `GET /invitation/verify?token=...` still sets `email_verified_at` + clears token (logic OK — `InvitationController@verify`)
- [x] Added `php artisan mail:test {email}` smoke command (routes/console.php) — method #3
- [x] 3 MailHog test methods documented (see below)
- [ ] Verify: run flows + `php artisan mail:test`, confirm mail in MailHog UI (:8025), follow activation link

### 3 reliable MailHog test methods
1. **Manual UI walkthrough** — trigger an invitation flow (create admin/officer or approve a student-leader request), open http://localhost:8025, confirm the email arrived, click the activation link, confirm `email_verified_at` is set + login works.
2. **Feature test via MailHog HTTP API** — in a Pest/PHPUnit test, run the flow with smtp→mailhog, then `GET http://mailhog:8025/api/v2/messages` to assert the message + extract the activation URL and follow it; `DELETE /api/v1/messages` to reset between runs.
3. **Artisan smoke command** — `php artisan mail:test you@example.com` sends a real `AdminInvitationMail` through the configured mailer; validates SMTP/MailHog delivery + template rendering fast. (Pair with `Mail::fake()`+`assertSent` for pure-logic unit tests.)

## Part 1 — Superadmin DB backup & restore (spatie + custom restore)

`spatie/laravel-backup` for backups + thin custom restore layer (package has no restore). Superadmin-only, design matches existing admin UI.

- [x] `composer require spatie/laravel-backup` (^9.3, in vendor); published + configured `config/backup.php` (DB-only via `source.databases`, destination `disks: ['backups']`)
- [x] Added `backups` disk in `config/filesystems.php`; confirmed `mysqldump`/`mysql` present in app container (`/usr/bin/...`)
- [x] `DatabaseBackupController` (standalone, not SuperAdminController): `index`, `run` (backup:run --only-db), `download`, `destroy`, `restore` (custom; extracts .sql from zip + imports via `mysql` using Symfony Process arg array, password via `MYSQL_PWD` env, no shell interpolation; basename-only path resolution guards traversal)
- [x] Routes under `auth`+`superadmin`: `/superadmin/backups` + run/download/delete/restore (registered, verified via `route:list`)
- [x] View `pages/sidebar/superadmin-database-backup.blade.php` (131 lines, compiles clean; restore guarded by typed `RESTORE` confirmation)
- [x] `MenuHelper.php`: "Database Backup" item added to superadmin group (`/superadmin/backups`)
- [x] (Optional) scheduled `backup:run --only-db` daily 01:00 + `backup:clean` weekly Sun 02:00 in `routes/console.php` (Laravel 12 `Schedule` facade; verified via `schedule:list`)
- [x] CLI verified: `php artisan backup:run --only-db` in container → zip written to `storage/app/backups/Laravel/` (291 KB), matches controller's `listBackups()` dir
- [ ] Browser verify: create/download/delete/restore as superadmin via UI; non-superadmin gets 403
