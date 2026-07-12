# Verification Handoff — Feature Batch on `claude/dashboard-mode-inconsistency-2y8320`

This file is the hand-off script for verifying the July 2026 feature batch **on a local dev
machine** (a Claude session with this branch pulled can execute it top-to-bottom). Record the
outcome of every ☐ item in the **Results** template at the bottom and send that file back.

Branch contents (one commit per work package):

| WP | Commit subject |
|---|---|
| 1 | Fix light/dark mode inconsistency with shared theme bootstrap |
| 2 | Improve ID template editor: fit-to-window, zone colors, save toast |
| 3 | Add signature as a universal profile field with ID-scan capture and recognition |
| 4 | Bind form pages to fixed system functions and retire seeded forms |
| 5 | Add administrator action logs with superadmin viewer and exports |
| 6 | Add Scratch-like scoring trigger editor with configurable criteria |
| 7 | Name the Request Records type column after the originating form page |

---

## 0. Environment setup

```bash
git fetch origin claude/dashboard-mode-inconsistency-2y8320
git checkout claude/dashboard-mode-inconsistency-2y8320

composer install
cp .env.example .env            # if you don't already have a .env; set your MySQL creds
php artisan key:generate
php artisan migrate             # adds: profiles.signature_path, forms.system_function,
                                #       action_logs, scoring_* tables, requests.form_id
php artisan db:seed --class=ScoringConfigSeeder   # seeds the 6 categories + 38 criteria
php artisan storage:link
npm ci && npm run build         # or: npm run dev

# OCR sidecar (needed for ID-scan + signature recognition items only)
docker compose up ocr           # first build downloads PaddleOCR models — slow
curl localhost:5000/health      # -> {"status":"ok","ocr":true}
# .env: OCR_SERVICE_URL=http://localhost:5000 (when running artisan outside compose)
```

> **⚠ Automated tests:** `phpunit.xml` points at the real `so_connect` MySQL database —
> a bare `php artisan test` WIPES it. Only ever run:
> `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`
> (~15 pre-existing failures are known and unrelated to this branch).

Accounts needed: one superadmin (user_type 1), one admin (user_type 2), one regular
officer/user (user_type 3).

---

## 1. Light/Dark mode (WP1)

- ☐ In the browser devtools console run `localStorage.removeItem('theme')`, set the **OS to
  dark mode**, then open the admin dashboard (`/dashboard/admin`), the officer dashboard
  (`/dashboard/officer`), and a few other pages in fresh tabs → **all open in light mode**
  (previously the dashboards opened dark).
- ☐ Toggle dark via the header moon button → page turns dark, `localStorage.theme === 'dark'`,
  and every other page now opens dark consistently. Toggle back → everything light.
- ☐ No console errors on page load (the old anti-flash script threw a TypeError touching
  `document.body` from `<head>`).
- ☐ The signin page and public directory page follow the same theme.

## 2. ID template editor (WP2)

As superadmin → ID Templates → New:

- ☐ Upload a **tall/portrait photo** of an ID, drag the four corners, Straighten → the
  resulting zone-editor image **fits inside the window** (no vertical overflow; previously a
  tall image scaled to 900px wide and overflowed).
- ☐ Add several zones → each gets a **different color** (box, on-canvas tag, and the dot in
  the zone panel all match). Colors survive save/reload.
- ☐ Save (both sides need an image + a zone) → a **“Saved!” toast** appears top-right (on
  first save you land on the edit page with the toast; on later saves you stay on the page).
- ☐ Zone panel has a **Type** select (Text OCR / Signature image capture); choosing Signature
  hides the regex input and defaults the field to the universal `signature`.

## 3. Signature (WP3)

Profile:
- ☐ `/profile` shows a **Signature card**; draw a signature → Save → “Saved!” toast, image
  appears as the saved signature. Upload variant works too. File lands under
  `storage/app/public/signatures/profiles/`.
- ☐ `/profile/create` (a fresh user) offers an optional signature pad; after superadmin
  approves the profile request, the linked profile has the signature.

Form builder / rendering:
- ☐ In the Form Builder, a Signature field can be mapped to the universal field **Signature**.
- ☐ Rendering that form as a user with a saved signature shows a “Using your saved signature”
  preview; submitting without redrawing stores the saved path; drawing stores a new PNG.

Recognition (needs the OCR sidecar + at least one saved profile signature):
- ☐ On any rendered form's signature field, draw a signature → after ~1s a badge appears:
  “Signature recognized as <name>” (green) when it matches a stored signature, or “Not
  recognized in the system” (amber). It never blocks submission.
- ☐ Stop the sidecar → badge says recognition is unavailable; submission still works.
- ☐ Threshold is tunable via `SIGNATURE_MATCH_THRESHOLD` env on the sidecar (default 0.45).

ID scan:
- ☐ Add a **signature-type zone** over the signature area of the ID template. On the signup
  wizard (`/forms/student-leader-directory`), scanning an ID now auto-fills the form's
  signature file input with the cropped signature image (visible in the input). After admin
  approval of the sign-up, the new user's profile has `signature_path` set.

## 4. Forms wipe + system functions (WP4)

- ☐ `php artisan forms:wipe` (no flag) prints row counts and does nothing.
- ☐ `php artisan forms:wipe --force` deletes all forms, fields, submissions and generated
  documents (+ files), and reports the counts.
- ☐ After the wipe: Events → “Create direct activity request” fails with the friendly
  message *No form is bound to the “New Event” function — bind one in the Form Builder first*
  (not a 404/500). Sidebar badges and per-type admin request pages degrade to empty, no crashes.
- ☐ Fresh `php artisan migrate:fresh --seed` seeds **no** demo forms (the two `demo-*` forms
  and the 8 legacy seeded forms are gone for good).
- ☐ In the Form Builder create a form and set **System function** (Step 3) — options: Sign Up,
  New Event, New Workplan, Org Membership Registration. Binding the same function to a second
  form fails validation (unique).
- ☐ **New Event binding:** build a form with field keys `organization_id`, `title`,
  `target_date`, `event_location`, `event_start_time`, `event_end_time` (+ a PDF template),
  publish, bind to New Event. Submit it as an officer → redirected back with “Awaiting admin
  approval”; a pending request appears for the admin; **approve** → event is created (calendar);
  **reject** → requester can start over from the form. Omitting a required key produces the
  “missing required field key(s)” message naming the keys.
- ☐ **Sign Up binding:** a bound form with `email`, `first_name`, `last_name` (mappable via
  universal fields) creates an action_type=11 request on submit; admin approval creates the
  user-type-3 account (activation email if no password field). The **admin-account creation
  form is untouched** (superadmin → Create Admin still its own page).
- ☐ **Membership binding:** a bound form with `organization_id` creates a membership request;
  duplicate membership / pending-request guards respond with friendly errors.
- ☐ `GET /functions/new_event` redirects to the bound form's page.

## 5. Administrator action logs (WP5)

- ☐ Log out/in, save an ID template, save a form in the builder, save an organization score,
  and save a scoring rule → superadmin → **Action Logs** shows each with the right category
  badge (Log In / Log Out, ID Template…, Form…, Organization Scoring, Scoring Rules & Criteria).
- ☐ Filters work: user (name or email substring), category dropdown, from/to date range —
  and combinations.
- ☐ Exports honor the active filters: **PDF** downloads a real dompdf file, **Excel** a valid
  .xlsx, **JSON** an attachment. The legacy admin “Audit Logs” page still works unchanged.

## 6. Scoring trigger editor (WP6)

Score parity (do this FIRST, before authoring any rules):
- ☐ For an existing organization score, open its edit page and re-save without changes →
  `total_weighted_score` is **unchanged** (catalog-driven math must equal the old hardcoded
  math; an automated 500-case parity check already passed in CI-less form).

Editor:
- ☐ Admin sidebar → Evaluation → **Scoring Rules** lists the 6 categories with the 38 seeded
  criteria (“Built-in behavior”).
- ☐ **Add criterion** (e.g. “Community Outreach Events”, Category IV, 5 pts) → appears with a
  `custom` badge; it can be edited/deleted; system criteria cannot be deleted.
- ☐ “Add trigger” opens the **Blockly editor** (Scratch-look zelos blocks): a fixed
  *when/if/then* rule block, palette with comparison / and-or-not / add-instances blocks.
  Variable dropdowns list every form page's fields, universal fields, and event-plan columns.
- ☐ Author: *when “X” submission is approved, if members_attended > 15, then add 1 instance* →
  Save → “Saved!”; reopening restores the exact blocks (workspace round-trip).
- ☐ Approve a submission matching the rule, then open the org's scoring page → that
  criterion's instances reflect the rule's tally (rules override the built-in tally only for
  criteria that have an enabled rule). Disable the trigger → built-in behavior returns.
- ☐ Custom criteria appear in a “Custom Criteria” section of the scoring form and in the audit.
- ☐ Every rule/criterion change shows in Action Logs under **Scoring Rules & Criteria**.
- ☐ `php artisan forms:wipe --force` disables all rules (they reference deleted forms).

## 7. Request Records type column (WP7)

- ☐ Admin → Request Records: the **Request Type** column shows the **name of the form page**
  the request was made from (e.g. the bound New Event form's name) for form-originated
  requests; non-form requests (e.g. profile match) fall back to the request-type name.
- ☐ The page's Excel and print exports show the same values.
- ☐ Pre-existing document-generation requests were backfilled (their payload carried form_id).

---

## Rollback notes

All schema changes roll back with `php artisan migrate:rollback --step=5`:

| Migration | Reverses |
|---|---|
| `2026_07_12_000005_add_form_id_to_requests_table` | drops `requests.form_id` |
| `2026_07_12_000004_create_scoring_config_tables` | drops scoring_rules/criteria/categories (scoring falls back to hardcoded values automatically) |
| `2026_07_12_000003_create_action_logs_table` | drops action_logs (login_logs unaffected) |
| `2026_07_12_000002_add_system_function_to_forms_table` | drops `forms.system_function` |
| `2026_07_12_000001_add_signature_path_to_profiles_table` | drops `profiles.signature_path` |

`forms:wipe --force` is **not reversible** — snapshot the DB before running it if the data
matters.

---

## Results

Copy this block into a new file (e.g. `docs/VERIFICATION-RESULTS.md`), fill it in, and send
it back.

```markdown
# Verification Results — claude/dashboard-mode-inconsistency-2y8320
Date: __ · Verifier: __ · Environment: (OS / PHP / MySQL / Node / docker?)

| # | Item | Result (pass/fail/skipped) | Notes |
|---|------|----------------------------|-------|
| 1.x | Theme … | | |
| 2.x | ID editor … | | |
| 3.x | Signature … | | |
| 4.x | Forms/functions … | | |
| 5.x | Action logs … | | |
| 6.x | Scoring rules … | | |
| 7.x | Request records … | | |

Console errors / screenshots / follow-ups:
- …
```
