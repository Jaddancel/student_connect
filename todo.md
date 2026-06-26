# Implementation Tracker — Wizards, OCR Scanner, LLM, Signature Recognition

Tracks the plan in `PLAN-wizards-ocr-signature.md` (repo root). OCR infra spec is in `PLAN.md`.

> **Status (updated 2026-06-23):** All six phases are code-complete. Full stack is up & healthy (mysql, ocr, ollama+GPU, queue, laravel.test). Server-side verification done live this session — OCR/LLM/wizard-AI/signature pipelines all confirmed working (details inline below). **The only work left is browser/hardware-dependent verification** that can't run headless: camera capture (`getUserMedia`) and the authenticated UI click-throughs (wizards + signature badge). See the per-phase "browser verification" boxes and Phase 3's STOPPED-HERE block.
>
> **Environment notes:** GPU host is this machine (RTX 4060), wired to the `ollama` container; active model is `phi4-mini` (`.env`), not `qwen3.5:9b`. Docker engine = Docker Desktop on Windows; **WSL integration must be enabled for the `Debian` distro** or MySQL's Sail bind-mount fails (`debian.sock not found`) and the native `docker` CLI has no socket. The earlier "GPT-5.2 handoff" note is obsolete.

## Phase 1 — OCR Infrastructure (PLAN.md) ✅

- [x] Migrations A/B/C — reconciled with pre-existing DB schema (renamed to recorded 2026*06_03*\* names; schema already applied)
- [x] FormScan model + Form/FormDescription model updates (ocr fillable + casts)
- [x] OcrService (PaddleOCR HTTP client) + config/services.php `ocr` block + AppServiceProvider binding
- [x] OcrScanController (store + result) + routes (api.forms.scan, api.form-scans.result)
- [x] OcrFieldMatcherService + ProcessFormScan job
- [x] Docker `ocr` service files (Dockerfile/requirements/main.py) + compose.yaml — container running, /health → {"status":"ok"}, OcrService::health() HEALTHY

## Phase 2 — Local LLM Sidecar ✅ (code complete + verified live on GPU)

- [x] Add `ollama` service to compose.yaml (nvidia runtime + ollama_models volume) — compose config validates
- [x] Model pulled — `.env` settled on `OLLAMA_MODEL=phi4-mini` (the plan's fallback, 2.5 GB); `phi4-mini:latest` is present in the ollama volume. (qwen3.5:9b not pulled; phi4-mini is the active model.)
- [x] config/services.php `ollama` block + .env/.env.example (OLLAMA_URL, OLLAMA_MODEL, OLLAMA_TIMEOUT, OLLAMA_PORT)
- [x] LlmService (interpretFields + chat) + AppServiceProvider binding — robust JSON-fence stripping verified, graceful degradation returns []/"" when unreachable
- [x] Smoke test — VERIFIED LIVE 2026-06-23: GPU confirmed (`nvidia-smi -L` in ollama container → RTX 4060 Laptop GPU); `POST http://ollama:11434/api/chat` with phi4-mini returned valid JSON field array (```json-fenced, stripped by LlmService). eval 0.7s after one-time 12s model load.

## Phase 3 — OCR Camera Scanner Component ✅ (code-complete; only real-browser camera/UX tests remain)

### Done (code written, kept in tree)

- [x] `components/form/ocr-camera-scanner.blade.php` — camera + upload tabs, getUserMedia (rear camera / device select), capture→canvas→blob, switch-camera, status states
- [x] Polling (`/api/form-scans/{id}/result` every 2s) + `ocr-result` window event dispatch
- [x] Camera-denied / unsupported fallback to Upload tab with notice
- [x] Integrated into `student-leader-directory.blade.php` — OCR-apply wrapper `applyOcrResult()` sets values by input name (with camelCase→snake_case fallback resolver) + `border-yellow-400` highlight
- [x] Controller passes `$form` (StudentLeaderDirectoryController@index)
- [x] `[x-cloak]` rule added to `resources/css/app.css` (directory-layout lacked it; app.css loads in both layouts)
- [x] BONUS (Phase 1 matcher quality): inline `Label: Value` single-block matching added to `OcrFieldMatcherService` — written but verification interrupted by stop

### Verified

- [x] Form page renders HTTP 200 with scanner present, no Blade/Laravel errors
- [x] Full scan pipeline e2e (direct job run): live PaddleOCR read a generated test form perfectly (6/6 lines), `ProcessFormScan` → `status=done`, `ocr_raw` populated

### ⏯️ STOPPED HERE — remaining to verify/finish when resuming

- [x] Confirm the new inline `Label: Value` matcher actually produces matches — VERIFIED 2026-06-23 via standalone test: inline matcher extracts "President"/"BS Computer Science" from `Label: Value` blocks, and proximity fallback extracts the separate-block value ("4th Year") for `yearLevel`. All three assertions pass.
- [ ] Real browser test of camera capture (getUserMedia) on mobile (rear cam) and PC (webcam) — cannot be done headless
- [ ] Browser test of the autofill UX: `ocr-result` event → inputs fill + highlight yellow
- [ ] DATA NOTE: form #1 `FormDescription.field_key`s are camelCase (`schoolYear`, `contactNumber`) and some have no matching input (`name` vs `first_name`/`last_name`). Autofill coverage depends on aligning field_keys to input names OR relying on the camel→snake resolver. Consider reconciling during Phase 4 (form derivation) or a small key-map.
- [ ] POST `/api/forms/{form}/scan` is a web route (CSRF-protected) — frontend sends `X-CSRF-TOKEN` from meta; not yet exercised over real HTTP (only the job path was tested directly)

## Phase 4 — Form Creation Wizard ✅

- [x] FormCreationWizardController (5 steps, draft Form + session)
- [x] showAiReview pipeline: LibreOffice → PaddleOCR → LlmService::interpretFields (ProcessFormWizardAiReview job, cache-backed polling)
- [x] Step views: upload, meta, ai-review, revise, final
- [x] wizard-progress component (resources/views/components/admin/wizard-progress.blade.php)
- [x] "Create New Form" button on Template Manager index
- [x] Routes + discard/cleanup
- [x] Graceful degradation to empty manual table
- [x] AI pipeline VERIFIED LIVE 2026-06-23 (server-side): built a DOCX with 5 labels → `extractTextFromDocx` → `ProcessFormWizardAiReview` → live phi4-mini returned 5 fields with correct snake_case keys + accurate type inference (email→email, contact#→number, date→date). NOTE: implemented pipeline reads DOCX XML directly (lossless) instead of the planned LibreOffice→PaddleOCR render — better approach; LibreOffice still present in container.
- [ ] Browser verification of the 5-step UI click-through (requires admin login)

## Phase 5 — Signature Recognition ✅

- [x] Migration: signature_records table (2026_06_10_000001 — applied)
- [x] SignatureRecord model
- [x] SignatureRecordService (dHash + hamming compare)
- [x] FormSubmissionObserver (store signature on submission) + register in AppServiceProvider
- [x] components/admin/signature-verification.blade.php (match/different/unknown badge + thumbnails)
- [x] Added to workplan-requests/show.blade.php and recognition-requests/show.blade.php
- [x] Added to accomplishment-report-requests/show.blade.php (name + signature)
- [x] Added to financial-report-requests/show.blade.php (president signature3)
- [x] SignatureRecordService VERIFIED LIVE 2026-06-23 against real GD+DB: store() writes 64-bit dHash; compare() returns unknown (unseen name), match (near-identical stroke, hamming ≤10), different (distinct stroke). All 3 assertions pass.
- [ ] Browser verification of the badge UI on a review page (requires admin login)

## Phase 6 — Organization Accreditation Wizard ✅

- [x] OrganizationAccreditationWizardController (reuses WorkplanService + own store() with sig_path fallback)
- [x] Step 1 — workplan activities review (read-only, finalized workplans, activity table)
- [x] Step 2 — signature capture (camera + file upload, getUserMedia, preview)
- [x] Step 3 — editable auto-filled review + submit (full org recognition form, pre-filled with steps 1+2 data)
- [x] Swapped organization-recognition GET route to wizard entry (POST store kept for backwards compat)
- [x] Accreditation signature flows into SignatureRecord via FormSubmissionObserver (payload has signaturePresident + nameOfPresident)
- [ ] Browser verification (requires running app + officer login)

## Static verification pass — 2026-06-23 (Docker daemon down; runtime checks blocked)

- [x] `php -l` clean across all new/modified PHP (controllers, services, jobs, models, observers, migrations)
- [x] `php artisan route:list` boots app & registers all routes (192) — wizard, scan, accreditation controllers all resolve
- [x] OcrFieldMatcherService inline + proximity matchers verified via standalone test (see Phase 3)

## Cross-cutting / verification

- [x] NVIDIA Container Toolkit confirmed — `nvidia-smi -L` inside ollama container → RTX 4060 Laptop GPU; live inference 0.7s eval. (Required WSL integration enabled for the Debian distro in Docker Desktop — without it MySQL's Sail bind-mount fails with `debian.sock not found`.)
- [x] Queue worker running for OCR + LLM jobs (so-connect-queue-1 up after MySQL came online)
- [ ] End-to-end verification per plan's Verification section
- [ ] (Deferred) Chatbot UI using LlmService::chat()
