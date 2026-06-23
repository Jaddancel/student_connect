# Plan: Template Wizard + OCR Camera Scanner + Signature Recognition

## Context

Three inter-related features to add to the StudentConnect Laravel/Alpine.js/Blade project (branch `ocr-and-template`):

1. **Template/Form Creation Wizard** — replace ad-hoc DOCX derivation with a multi-step wizard (accessible from Template Manager). Its AI Review step uses a **local LLM (Ollama / Qwen3.5-9B)** that interprets text extracted via the LibreOffice → PaddleOCR pipeline and returns structured JSON field definitions.
2. **OCR Camera Scanner Component** — a reusable Blade/Alpine.js component that streams the device camera (mobile + PC), captures a photo, uploads it to the existing OCR pipeline, and fires an `ocr-result` window event to autofill any form page. Replaces the student-leader-directory first step.
3. **Signature Recognition** — store every submitted signature indexed by submitter name; show a "Signature Verification" section on admin review pages that compares the current signature against stored ones using perceptual hashing.

Stack: Laravel 11 · Blade · Alpine.js 3 · Tailwind CSS 4 · PaddleOCR FastAPI sidecar (from PLAN.md) · **Ollama LLM sidecar (new)**.

### Two AI sidecars — clear division of labor
- **PaddleOCR (`ocr` service, ~3–4 GB VRAM)** — handles all **image** work: scanned handwritten forms (Feature 2) and DOCX-rendered-to-PNG text extraction (Feature 1 Step 3). Stays exactly as planned in PLAN.md; **not replaced**.
- **Ollama / Qwen3.5-9B Q4_K_M (`ollama` service, ~5 GB VRAM)** — handles **text-only** work: interpreting OCR'd DOCX text into JSON field definitions (Feature 1 Step 3), plus a future chatbot. **Never receives images.**
- The two run as separate **Laravel Queue** jobs on the RTX 4060 (8 GB) so they don't contend for VRAM simultaneously.

---

## Feature 0 — Local LLM Sidecar (prerequisite for Feature 1 Step 3)

### Docker — add `ollama` service to `so-connect/compose.yaml`
```yaml
ollama:
  image: ollama/ollama:latest
  runtime: nvidia
  environment:
    - NVIDIA_VISIBLE_DEVICES=all
  volumes:
    - ollama_models:/root/.ollama
  ports:
    - "${OLLAMA_PORT:-11434}:11434"
  networks:
    - sail
  restart: unless-stopped
# volumes: add  ollama_models: { driver: local }
```
Add `ollama` to `laravel.test.depends_on`. Pull model once: `docker compose exec ollama ollama pull qwen3.5:9b`. Internal DNS: `http://ollama:11434` (same pattern as `http://ocr:5000`).

### Config / env
- `config/services.php`: `'ollama' => ['url' => env('OLLAMA_URL', 'http://ollama:11434'), 'model' => env('OLLAMA_MODEL', 'qwen3.5:9b')]`
- `.env`: `OLLAMA_URL=http://ollama:11434`, `OLLAMA_MODEL=qwen3.5:9b`

### New service: `app/Services/LlmService.php`
```php
public function interpretFields(string $extractedText): array
// POST {url}/api/chat (stream=false), system prompt forces JSON-only,
// user prompt = buildFieldPrompt($extractedText), json_decode(message.content) ?? []
public function chat(array $messages): string
// POST {url}/api/chat → message.content (chatbot building block; UI out of scope)
private function buildFieldPrompt(string $extractedText): string
// instructs: return ONLY a JSON array, each item
// {label, field_key(snake_case), field_type(text|textarea|checkbox|date|number|email), is_required, field_order}
```
Defensive parsing: strip any accidental ```json fences before `json_decode`; on decode failure, fall back to an empty array so the wizard degrades to a manual field table (Step 4) rather than erroring.

**Fallback model:** Phi-4-mini 3.8B (~2.5 GB) if VRAM pressure appears — swap via `OLLAMA_MODEL` only.

> **Chatbot note:** `LlmService::chat()` is included as a reusable building block, but the chatbot UI/endpoint is **out of scope** for this plan (the three requested features are the wizard, scanner, and signatures). Flag for a follow-up.

---

## Feature 1 — Form Creation Wizard

### Entry point
Add a **"Create New Form"** button to the top-right actions bar in `resources/views/pages/admin/templates/index.blade.php` (alongside the existing "Field Reference" button), linking to `route('admin.form-wizard.start')`.

### Wizard steps & routes

| Step | GET route | POST route | View |
|------|-----------|------------|------|
| 1 — Upload Template | `admin.form-wizard.start` | `admin.form-wizard.upload` | `admin/form-wizard/step1-upload.blade.php` |
| 2 — Meta Info | `admin.form-wizard.meta` | `admin.form-wizard.meta.save` | `admin/form-wizard/step2-meta.blade.php` |
| 3 — AI Review | `admin.form-wizard.ai-review` | `admin.form-wizard.ai-confirm` | `admin/form-wizard/step3-ai-review.blade.php` |
| 4 — Revise (conditional) | `admin.form-wizard.revise` | `admin.form-wizard.revise.save` | `admin/form-wizard/step4-revise.blade.php` |
| 5 — Final Review | `admin.form-wizard.final` | `admin.form-wizard.confirm` | `admin/form-wizard/step5-final.blade.php` |

After step 5 POST → redirect to `admin.templates.index` with flash success.

### State management
Use a **draft Form record** (`is_active = false, is_published = false`) created at step 1 and activated at step 5. Session stores `wizard_draft_form_id` to link steps. If user abandons, a cleanup route (`DELETE admin.form-wizard.discard`) deletes the draft (no submissions guard, same as `FormDerivationController::destroy`).

### New controller: `Admin/FormCreationWizardController.php`

| Method | Description |
|--------|-------------|
| `showStart()` | Show step 1 |
| `storeUpload(Request)` | Validate DOCX (≤10 MB), store to `storage/form-derivation/`, create draft Form, set session, redirect to step 2 |
| `showMeta()` | Show step 2 (pre-fill from draft) |
| `saveMeta(Request)` | Validate `form_title` (required), `form_purpose` (optional), update draft `Form.name` + `Form.description_text`, redirect to step 3 |
| `showAiReview()` | Convert DOCX → PNG (LibreOffice headless), POST PNG to PaddleOCR `OcrService::extract()` → text blocks, pass concatenated text to `LlmService::interpretFields()` → JSON field array, render to view. Runs as a queued job with an Alpine.js polling/spinner state since LLM inference takes a few seconds |
| `confirmAiReview(Request)` | Accept user choice: "looks good" → skip to step 5, "revise" → redirect to step 4 |
| `showRevise()` | Show editable field table (same Alpine.js table as PLAN.md `form-derivation/review`) |
| `saveRevise(Request)` | Upsert `FormDescription` rows from posted table, redirect to step 5 |
| `showFinal()` | Load draft Form + its FormDescriptions, render summary |
| `confirm()` | Set `is_active = true`, set `ocr_reference_docx`, redirect to Template Manager |
| `discard()` | Delete draft Form if no submissions, redirect to Template Manager |

### AI field detection logic (Step 3)
Pipeline: **DOCX → LibreOffice PNG → PaddleOCR text → LLM JSON fields**
1. Shell: `libreoffice --headless --convert-to png --outdir /tmp {docx_path}` (LibreOffice is in the Sail container)
2. POST PNG to `OcrService::extract()` (existing PLAN.md service) → ordered text blocks
3. Concatenate block text (top-to-bottom, left-to-right) → pass to `LlmService::interpretFields()`
4. LLM returns `[{label, field_key, field_type, is_required, field_order}, ...]`
5. Sanitize each `field_key` through `FormTemplateHelper::normalizeFieldKey()` (don't fully trust LLM key formatting); de-dupe keys

The geometric-heuristic `DocxFieldExtractorService` from the prior draft is **dropped** — the LLM replaces it. If the LLM returns an empty/invalid array, the wizard proceeds to Step 4 with an empty editable table so the admin can add fields manually (graceful degradation).

### Wizard UI
- Shared `<x-admin.wizard-progress :step="N" :total="5" />` component showing step indicator bar
- Each view extends `layouts.app` and includes the progress component
- Step 1 & 2: simple form cards (copy pattern from `admin/templates/upload.blade.php`)
- Step 3: read-only table of AI-detected fields + two CTA buttons ("Looks good →" / "I want to revise")
- Step 4: editable Alpine.js table (same pattern as PLAN.md `form-derivation/review.blade.php`)
- Step 5: final read-only summary table + "Confirm & Activate" button

### New migration
None needed — uses existing `forms.description_text` + PLAN.md migrations A/B/C.

---

## Feature 2 — OCR Camera Scanner Component

### Reusable component: `resources/views/components/form/ocr-camera-scanner.blade.php`

**Props:** `:form="$form"` (Form model)

**UX flow:**
1. Shows two tabs: **Camera** | **Upload File**
2. **Camera tab:**
   - `<video>` element with live stream from `getUserMedia({ video: { facingMode: 'environment' } })`
   - On mobile: activates rear camera. On PC: shows available cameras in a `<select>`.
   - "Capture" button → `canvas.drawImage(video)` → `canvas.toBlob()` → auto-submit
   - "Switch Camera" button (only on devices with multiple cameras)
3. **Upload File tab:** `<input type="file" accept="image/*" capture="environment">` (identical to existing `ocr-scan-uploader.blade.php` pattern)
4. After file is ready (either path): POST FormData to `/api/forms/{form_id}/scan` → receive `{scan_id}`
5. Polls `/api/form-scans/{scan_id}/result` every 2 s
6. On `status === 'done'`: fires `window.dispatchEvent(new CustomEvent('ocr-result', { detail: { fieldMap: data.ocr_result } }))`
7. Shows status states: idle → uploading → processing spinner → done checkmark / error

**Alpine.js component data:**
```js
{
  tab: 'camera',           // 'camera' | 'upload'
  stream: null,
  cameras: [],
  selectedCamera: null,
  scanStatus: 'idle',      // 'idle' | 'uploading' | 'processing' | 'done' | 'error'
  errorMsg: null,
  async startCamera() { ... },
  async capture() { ... },
  async uploadFile(file) { ... },
  async pollResult(scanId) { ... },
  stopCamera() { if (this.stream) this.stream.getTracks().forEach(t => t.stop()); }
}
```

**Camera permission denied / not available:** gracefully falls back to the Upload tab with a notice.

### Student Leader Directory integration
In `resources/views/pages/form/student-leader-directory.blade.php`:
- Wrap page in outer `x-data` with field state for all form inputs
- Add `<x-form.ocr-camera-scanner :form="$form" />` at the top, above the form sections
- Add `@ocr-result.window="applyOcrResult($event.detail.fieldMap)"` on the outer `x-data` element
- `applyOcrResult(fieldMap)` method sets field values and adds `border-yellow-400` class to autofilled inputs
- "Skip scanning" text link below the component goes straight to the form

### Existing OCR backend
No new backend needed — uses `OcrScanController::store()` and `OcrScanController::result()` from PLAN.md exactly as specified. The component is purely a frontend enhancement over the existing `ocr-scan-uploader.blade.php`.

---

## Feature 3 — Signature Recognition

### New migration: `create_signature_records_table`

```sql
id, submitter_name (varchar 255, index), user_id (FK users.user_id nullable nullOnDelete),
form_submission_id (FK form_submissions.form_submission_id nullable nullOnDelete),
signature_path (varchar 500), perceptual_hash (varchar 64), created_at, updated_at
```

### New model: `app/Models/SignatureRecord.php`
- Fillable: `submitter_name`, `user_id`, `form_submission_id`, `signature_path`, `perceptual_hash`
- BelongsTo: `User`, `FormSubmission`

### New service: `app/Services/SignatureRecordService.php`

```php
public function store(string $submitterName, string $signaturePath, ?int $submissionId, ?int $userId): SignatureRecord
// Compute perceptual hash via GD, store SignatureRecord

public function compare(string $currentSignaturePath, string $submitterName): array
// Returns ['previous_count' => N, 'status' => 'match'|'different'|'unknown', 'previous_signatures' => [...paths]]

private function dHash(string $imagePath): string
// GD: resize to 9x8 grayscale, compute 64-bit difference hash, return hex string

private function hammingDistance(string $hash1, string $hash2): int
// XOR + bit count; distance ≤ 10 → 'match', > 10 → 'different'
```

### Observer: `app/Observers/FormSubmissionObserver.php`
- `created(FormSubmission $submission)`: checks `$submission->payload` for `signature` key; if present, extracts submitter name (looks for keys: `first_name`+`last_name`, `name`, `full_name` in payload), calls `SignatureRecordService::store()`.
- Register in `AppServiceProvider::boot()`.

### Blade component: `resources/views/components/admin/signature-verification.blade.php`

**Props:** `:submitter-name`, `:current-signature-path`

**Renders:**
- Section header: "Signature Verification"
- Current signature image (existing lightbox pattern)
- Status badge:
  - **"Matches previous signature"** (green) — hash distance ≤ 10 to any stored hash for this name
  - **"Differs from previous signature"** (amber) — name known but hashes don't match
  - **"First-time submitter"** (gray) — no prior signatures for this name
- Previous signature thumbnails (if any), with count label

### Review pages to update
Add `<x-admin.signature-verification>` to any review `show.blade.php` that displays a signature field. Key targets:
- `resources/views/pages/admin/workplan-requests/show.blade.php`
- `resources/views/pages/admin/activity-requests/show.blade.php` (if signature present)
- Student leader directory review page (once it exists)

The component is passive (read-only lookup) — no changes to approval flow.

---

## Feature 4 — Organization Accreditation Wizard

Replaces the single-page **Organization Recognition / Accreditation** form (`organization-recognition`, "Application for Recognition/Renewal of Student Organization") with a 3-step wizard. The existing `OrganizationRecognitionController` already assembles all the data needed — the wizard reorganizes it across steps and reuses Features 2 & 3.

### Existing assets to reuse
- `OrganizationRecognitionController@index` — already loads officer orgs, president name, advisers, and **finalized workplans + approved activities** via `WorkplanService::getApprovedPlansForWorkplan()`.
- `OrganizationRecognitionController@store` — payload shape, signature storage (`form-signatures/Y/m`), and `DocumentGenerationService::createDocumentGenerationRequest()` flow stay intact; only the entry UI changes.
- `resources/views/pages/form/organization-recognition.blade.php` — source of field markup for Step 3.

### Wizard steps & routes
New controller `OrganizationAccreditationWizardController` (or extend `OrganizationRecognitionController` with wizard methods); session key `accreditation_wizard_state` holds in-progress data across steps.

| Step | GET route | View | Content |
|------|-----------|------|---------|
| 1 — Workplan Activities Review | `accreditation.wizard.workplan` | `step1-workplan.blade.php` | Read-only list of the **finalized, admin-approved** workplan activities for the upcoming semester (title / date / resources). If multiple finalized workplans, a selector picks one. Reuses `WorkplanService::getApprovedPlansForWorkplan()`. "Continue" → Step 2 |
| 2 — Signature Attachment | `accreditation.wizard.signature` | `step2-signature.blade.php` | First signature field. **Reuses the `<x-form.ocr-camera-scanner>` capture UX from Feature 2** — but in signature-capture mode: upload a file *or* take a photo (mobile rear camera). Captured image held in the form (no OCR call here — capture only). "Continue" → Step 3 |
| 3 — Review Submission | `accreditation.wizard.review` | `step3-review.blade.php` | Editable Organization Accreditation form **auto-filled** from user profile + organization data (president name, advisers, org name, objectives) and carrying the Step 1 workplan + Step 2 signature. Final "Submit" POSTs to the existing `store()` logic |

POST submit → existing `store()` → `FormSubmission` + document-generation request → redirect with success flash. The signature submitted here is captured by the **Feature 3 `FormSubmissionObserver`**, so accreditation signatures participate in signature recognition automatically (submitter = president name).

### State management
Wizard is read-mostly until the final POST; carry Step 1 `workplan_id` and Step 2 signature (temp-stored under `form-signatures/tmp/`, promoted on submit, or held as a re-uploadable file input) in session/hidden fields. Use a shared `<x-admin.wizard-progress :step="N" :total="3" />` (same component as Feature 1).

### Migration
None — uses existing `forms`/`form_submissions` for `organization-recognition`.

---

## Implementation Order

| Phase | Steps |
|-------|-------|
| **1 — OCR infra (PLAN.md prerequisite)** | Apply PLAN.md migrations A/B/C → FormScan model → OcrService → OcrScanController → routes → Docker OCR service |
| **2 — LLM sidecar** | Add `ollama` service to compose.yaml → pull `qwen3.5:9b` → `config/services.php` + `.env` → `LlmService` → smoke-test `/api/chat` returns JSON |
| **3 — Camera Scanner Component** | Build `ocr-camera-scanner.blade.php` Alpine component → integrate into student-leader-directory |
| **4 — Wizard** | `FormCreationWizardController` (LibreOffice→PaddleOCR→`LlmService::interpretFields()`) → 5 wizard views → progress component → add button to Template Manager index |
| **5 — Signature Recognition** | Migration → `SignatureRecord` model → `SignatureRecordService` (dHash) → `FormSubmissionObserver` → `signature-verification` component → update review pages |
| **6 — Accreditation Wizard** | `OrganizationAccreditationWizardController` (reuse `WorkplanService` + existing `store()`) → 3 wizard views → signature step reuses Feature 2 camera UX → swap `organization-recognition` route to wizard entry |

---

## Critical Files to Reference

| File | Reason |
|------|--------|
| `app/Http/Controllers/Admin/TemplateManagerController.php` | Upload→verify→confirm pattern for wizard |
| `app/Helpers/FormTemplateHelper.php` | Reuse `normalizeFieldKey()` in `DocxFieldExtractorService` |
| `resources/views/pages/admin/templates/upload.blade.php` | Drag-drop UI pattern for step 1 |
| `resources/views/pages/admin/templates/index.blade.php` | Add "Create New Form" button here |
| `resources/views/pages/form/student-leader-directory.blade.php` | Add camera scanner + Alpine.js field state |
| `resources/views/pages/admin/workplan-requests/show.blade.php` | Pattern for adding signature-verification to review pages |
| `PLAN.md` | OCR infrastructure spec (migrations, services, jobs, routes) — implement Phase 1 first |
| `so-connect/compose.yaml` | Add OCR (PLAN.md) **and Ollama** Docker services |
| `so-connect/config/services.php` | Add `ocr` (PLAN.md) **and `ollama`** config blocks |
| `app/Services/OcrService.php` | PaddleOCR client — `LlmService` mirrors its HTTP-client shape |
| `app/Http/Controllers/OrganizationRecognitionController.php` | Accreditation wizard reuses its `index()` data-loading + `store()` submission logic |
| `app/Services/WorkplanService.php` | `getApprovedPlansForWorkplan()` feeds Accreditation Step 1 |
| `resources/views/pages/form/organization-recognition.blade.php` | Field markup source for Accreditation Step 3 |

---

## Verification

0. **LLM sidecar:** `docker compose exec ollama ollama list` shows `qwen3.5:9b`; `curl http://localhost:11434/api/chat` with a field-extraction prompt returns parseable JSON.
1. **Wizard end-to-end:** Upload a blank DOCX → fill meta → AI Review spinner runs the LibreOffice→PaddleOCR→LLM pipeline → see LLM-detected fields → confirm → verify `forms` + `form_descriptions` rows in DB → Template Manager shows new form in list. Also test the degraded path: feed a DOCX that yields no fields → Step 4 opens with an empty editable table.
2. **Camera scanner (mobile):** Open student-leader-directory on a mobile browser → camera stream appears → capture → watch OCR polling → form fields fill yellow.
3. **Camera scanner (PC):** Same page on PC → webcam stream → capture → same autofill flow.
4. **Camera fallback:** Deny camera permission → Upload tab activates automatically → file upload still works.
5. **Signature recognition:** Submit student-leader-directory form with signature twice under same name → on second submission's review page, see "Differs from previous signature" or "Matches" badge + thumbnail of first signature.
6. **First-time submitter:** New name → review page shows "First-time submitter" gray badge.
7. **Accreditation wizard:** Open `organization-recognition` as an officer → Step 1 lists the finalized/approved workplan activities → Step 2 capture/upload signature (camera works on mobile) → Step 3 shows auto-filled editable form → submit → verify `form_submissions` row + document-generation request created, and a `SignatureRecord` row exists for the president's name.

---

## todo.md (to be created at repo root on execution)

> Plan mode allows editing only the plan file, so `todo.md` is **not yet written**. Its full intended contents are below; it will be created as the first execution step once the plan is approved.

```markdown
# Implementation Tracker — Wizards, OCR Scanner, LLM, Signature Recognition

## Phase 1 — OCR Infrastructure (PLAN.md)
- [ ] Migrations A/B/C (ocr columns on forms, ocr_region on form_descriptions, form_scans table)
- [ ] FormScan model
- [ ] OcrService (PaddleOCR HTTP client) + config/services.php `ocr` block
- [ ] OcrScanController (store + result) + routes
- [ ] OcrFieldMatcherService + ProcessFormScan job
- [ ] Docker `ocr` service (FastAPI/PaddleOCR) — /health responds

## Phase 2 — Local LLM Sidecar
- [ ] Add `ollama` service to compose.yaml (nvidia runtime)
- [ ] Pull qwen3.5:9b model
- [ ] config/services.php `ollama` block + .env (OLLAMA_URL, OLLAMA_MODEL)
- [ ] LlmService (interpretFields + chat) with JSON-only parsing + fallback
- [ ] Smoke test: /api/chat returns parseable JSON

## Phase 3 — OCR Camera Scanner Component
- [ ] components/form/ocr-camera-scanner.blade.php (camera + upload tabs, getUserMedia, capture→canvas→blob)
- [ ] Polling + ocr-result event dispatch
- [ ] Camera-denied fallback to upload tab
- [ ] Integrate into student-leader-directory.blade.php (Alpine field state + applyOcrResult + yellow highlight)

## Phase 4 — Form Creation Wizard
- [ ] FormCreationWizardController (5 steps, draft Form + session)
- [ ] showAiReview pipeline: LibreOffice → PaddleOCR → LlmService::interpretFields (queued + spinner)
- [ ] Step views: upload, meta, ai-review, revise, final
- [ ] wizard-progress component
- [ ] "Create New Form" button on Template Manager index
- [ ] Routes + discard/cleanup
- [ ] Graceful degradation to empty manual table

## Phase 5 — Signature Recognition
- [ ] Migration: signature_records table
- [ ] SignatureRecord model
- [ ] SignatureRecordService (dHash + hamming compare)
- [ ] FormSubmissionObserver (store signature on submission) + register in AppServiceProvider
- [ ] components/admin/signature-verification.blade.php (match/different/unknown badge + thumbnails)
- [ ] Add component to review show pages (workplan, activity, accreditation, student-leader)

## Phase 6 — Organization Accreditation Wizard
- [ ] OrganizationAccreditationWizardController (reuse WorkplanService + existing store())
- [ ] Step 1 — workplan activities review (read-only, approved activities)
- [ ] Step 2 — signature attachment (reuse camera capture UX)
- [ ] Step 3 — editable auto-filled review + submit
- [ ] Swap organization-recognition route to wizard entry
- [ ] Confirm accreditation signature flows into SignatureRecord

## Cross-cutting / verification
- [ ] NVIDIA Container Toolkit confirmed on WSL2 host (required for ollama nvidia runtime)
- [ ] Queue worker running for OCR + LLM jobs
- [ ] End-to-end verification per plan's Verification section
- [ ] (Deferred) Chatbot UI using LlmService::chat()
```
