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
> a bare `php artisan test` WIPES it. sqlite `:memory:` does NOT work either (the
> `2026_05_19_200000_merge_members_into_organization_officers` migration uses MySQL-only
> `UPDATE … JOIN`). Run the suite against a dedicated MySQL `testing` database instead:
>
> ```bash
> DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=sail \
>   DB_PASSWORD=password php artisan test
> ```
>
> (16 pre-existing failures are known and unrelated to this branch: the retired
> document-form workflow and old seeded-form fixtures. `tests/Feature/ScoringParityTest.php`
> guards WP6's score-parity contract and must pass.)

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
  `total_weighted_score` is **unchanged**. The parity contract is also guarded by a committed
  regression test — `tests/Feature/ScoringParityTest.php` (seeded-catalog and empty-table
  fallback paths, 200 random payloads each) — which must pass in the test suite.

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

# Batch 2 — ID scanner, Form Builder overhaul, per-form requests (July 13)

Second feature batch on the same branch (commits after `705bc96`). **No new
migrations** — everything reuses existing columns (`forms.request_type_id`,
`requests.form_id`, `form_descriptions.field_options`,
`request_types.system_key`, `approvals.rejection_reason`).

| WP | Commit subject |
|---|---|
| 9 | Fix OCR name splitting and normalize scanned birthdays to Y-m-d |
| 10 | Add ID-scan template chooser, signature capture status, and rescan discard |
| 11 | Add Organization Name universal field |
| 12 | Add Time and Date+Time field types, palette limits, and upload allowlists |
| 13 | Generate field keys from labels, frozen once the form is saved |
| 14 | Publish forms on save, drop sidebar targeting, gate forms to officers |
| 15 | Add per-field conditional visibility to builder forms |
| 16 | Give every form page its own request type |
| 17 | Route plain form submissions through the request-approval lifecycle |
| 18 | Add a generic per-form admin request page and rewire the Requests menu |

Test suite note: the new unit files (`OcrNameParsingTest`, `FieldTypeTest`,
`ConditionEvaluatorTest`) run without a DB. `FormBuilderTest` was rewritten for
the new publish/access/lifecycle semantics; new feature files:
`FormRequestTypeTest`, `FormRequestPageTest`. Same MySQL `testing`-DB command
as above; the 16 known pre-existing failures still apply.

## B1. ID scanner (WP9 + WP10)

- ☐ **Name parsing:** scan an ID printed `LAST, FIRST MIDDLE` with a compound
  first name (e.g. "DELA CRUZ, JUAN MIGUEL P.") → first name "JUAN MIGUEL",
  middle "P.", last "DELA CRUZ". A no-comma ID ("JUAN MIGUEL DELA CRUZ") keeps
  the particle surname together. Suffixes (JR/III) stay with the last name.
- ☐ **Birthday:** an ID printing "JANUARY 5, 2003" (or Jan 5, 2003 / 01/05/2003)
  lands in the birthday `<input type=date>` as 2003-01-05 (previously blank).
- ☐ **Template chooser:** with 2+ active ID templates, the signup scanner opens
  with a card grid of template photos; picking one drives the finder
  orientation and the scan; "Change ID type" resets captures. With one active
  template the chooser never appears.
- ☐ **Signature notify:** after scanning, a status line reports "Signature
  captured from your ID" (green), "No signature was detected…" (amber, when the
  template has a signature zone but nothing came back), or a muted note when
  the chosen ID carries no signature zone.
- ☐ **Universal autofill:** the scanned signature crop fills the signup form's
  signature input (marked `data-universal-key="signature"`); a signature pad
  bound to the universal Signature field receives it via the new
  `signature-set` event (code path present; the wizard is only mounted on the
  signup page today).
- ☐ **Rescan discard:** scan the front, note the autofilled fields, then rescan
  the front with a different/blank photo → the previous attempt's autofills
  (including an injected signature) are cleared; fields the user edited by
  hand are left alone. Same on "Change ID type".

## B2. Form Builder (WP11–WP15)

- ☐ Universal-field picker offers **Organization Name** (org group); mapping a
  text field to it prefills the submitter's org name; PDF token prints it.
- ☐ Palette = Text, Paragraph, Number, Email, Date, **Time**, **Date + Time**,
  Image, File, Dropdown, Radio, Checkbox, Signature — plus a separate
  **Layout** section (Section, Static text). Age is gone from the palette but
  an existing age field still renders/validates.
- ☐ Time / Date+Time fields render flatpickr pickers and validate `H:i` /
  `Y-m-d H:i`.
- ☐ Upload limits: Image fields accept JPEG/PNG/HEIC only, File fields also
  PDF; the per-field "Accepted types" checkboxes can only narrow that; a
  smuggled .docx upload is rejected server-side.
- ☐ **Field keys** generate from the label ("Event Title" → `event_title`),
  update live while typing the label, uniquify with `_2`, and freeze after the
  form is saved (caption says so; key shown read-only).
- ☐ **Publish-on-save:** no Active/Published checkmarks, no "Show in sidebar
  for". Saving with a printed template publishes immediately; without one the
  form stays unlisted until the template is added (Step 3 shows which).
- ☐ **Access:** rendered form pages, /forms directory and the sidebar
  "Organization Forms" group are visible only to user-type-3 accounts with an
  officer/president role. Admins get 403 on /forms/{route} (builder Preview
  still works). NOTE (user-approved trade-off): member-role users cannot open
  a membership-registration form.
- ☐ **Conditional fields:** add a Dropdown (e.g. kind = standard/other) and a
  text field visible only when kind equals "other" → the field
  appears/disappears live; hidden-but-required fields don't block submission;
  a value smuggled into a hidden field is stored as null. The builder rejects
  self-references and circular conditions with a friendly 422.

## B3. Requests (WP16–WP18)

- ☐ Saving a form creates a request type named "<Form name> Request" (visible
  as the type of its requests, e.g. on Request Records); renaming the form
  renames the type; two forms with the same name get a `#id` suffix.
- ☐ Submitting a plain form no longer generates the PDF immediately: the
  submitter is redirected back with "submitted for approval", and the form
  page shows "Your recent submissions" with Pending / Approved (link to
  documents) / Rejected + reason.
- ☐ Admin sidebar → Requests shows Activity, Workplan Submissions, Promotion
  Requests, then **one entry per form page** with a pending-count badge
  (membership-bound forms included; sign-up/new-event/new-workplan bound forms
  excluded). The five legacy entries (Project, Joint Statements,
  Accomplishment, Financial, Recognition) are gone from the menu; their URLs
  still respond for draining old requests.
- ☐ On a form's request page: Review shows the submission's answers labelled
  by the form's fields (file/signature values as images/links); **Approve**
  generates the document (the requester sees it under Documents); **Reject**
  (with optional reason) stores the reason, generates nothing, and the
  requester can resubmit.
- ☐ A membership-bound form's page approves action_type=1 requests — the
  requester becomes a `member` of the chosen org.
- ☐ `/admin/form-requests/{id}` for a sign-up/new-event/new-workplan-bound
  form → 404.
- ☐ **Scoring regression gate:** `ScoringParityTest` passes; an org's score
  recomputes identically after approving a plain-form submission (the request
  keeps action_type=3 + payload.submission_id + organization_id).

Batch 2 rollback: no migrations — reverting the WP commits is sufficient;
per-form request types can be cleaned with
`RequestType::where('system_key','like','form:%')->delete()` ONLY if no
requests reference them.

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
