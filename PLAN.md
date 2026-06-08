# OCR & Form Derivation Feature

## Context

StudentConnect currently handles forms purely through manual web-form entry and DOCX template generation. This feature adds two new capabilities:

1. **Form Derivation** — admin uploads a blank DOCX form and the system auto-detects its fields, creating a web form (Form + FormDescription records) without manual entry.
2. **OCR Scan → Pre-fill** — officer or allowed guest scans a filled physical form; a PaddleOCR microservice extracts text and maps it to the web form fields for user review and correction.

The hard-coded binding is: the admin can assign any derived form to the existing "Directory of Student Leaders" sign-up route (`/forms/student-leader-directory`).

The OCR service runs as a Docker sidecar inside Laravel Sail on the ASUS TUF A15 2024 (RTX 4060, 8 GB VRAM). For production, the URL is swapped to a Tailscale `100.x.x.x` address via `.env`.

---

## Architecture

```
Admin uploads blank DOCX
        │
        ▼
DocxFormStructureParser (phpWord heuristics)
        │  detects labels, tables, checkboxes
        ▼
Admin review page (Alpine.js editable table)
        │  confirms field list
        ▼
Form + FormDescription rows created
        │  ocr_reference_docx stored on Form
        │  ocr_region (x%,y%,w%,h%) per FormDescription (admin sets later)
        ▼
Form page shown with <x-form.ocr-scan-uploader>
        │
Officer/guest uploads scan image
        │
OcrScanController creates FormScan row (pending)
        │
ProcessFormScan job dispatched (async)
        │  calls FastAPI /extract → raw bbox blocks
        │  OcrFieldMatcherService maps blocks to form fields
        ▼
FormScan.status = done, ocr_result = {field_key: value, ...}
        │
Alpine.js polls /api/form-scans/{id}/result
        │  fires ocr-result event → pre-fills form fields (highlighted)
        ▼
User corrects → normal form submission
```

---

## Database Migrations

### A — `add_ocr_columns_to_forms_table`

```php
$table->boolean('allows_guest_scan')->default(false);
$table->string('ocr_reference_docx', 500)->nullable();
$table->string('directory_assignment_key', 100)->nullable();
// 'student-leader-directory' links this form to the sign-up hard-coded function
```

### B — `add_ocr_region_to_form_descriptions_table`

```php
$table->json('ocr_region')->nullable();
// {"x_pct": 0.1, "y_pct": 0.2, "width_pct": 0.4, "height_pct": 0.05, "page": 1}
```

### C — `create_form_scans_table`

```php
Schema::create('form_scans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
    $table->foreignId('uploaded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
    $table->string('scan_image_path', 500);
    $table->enum('status', ['pending','processing','done','failed'])->default('pending');
    $table->json('ocr_raw')->nullable();      // raw PaddleOCR blocks with bbox
    $table->json('ocr_result')->nullable();   // matched field_key → text
    $table->text('error_message')->nullable();
    $table->string('job_id', 255)->nullable();
    $table->timestamps();
});
```

---

## Model Changes

**`Form.php`** — add to `$fillable`: `allows_guest_scan`, `ocr_reference_docx`, `directory_assignment_key`. Cast `allows_guest_scan` to `boolean`.

**`FormDescription.php`** — add `ocr_region` to `$fillable`, cast to `array`.

**New `app/Models/FormScan.php`**

```php
protected $fillable = ['form_id','uploaded_by','scan_image_path','status','ocr_raw','ocr_result','error_message','job_id'];
protected function casts(): array { return ['ocr_raw'=>'array','ocr_result'=>'array']; }
public function form(): BelongsTo  // → Form
public function uploader(): BelongsTo  // → User
```

---

## New Services

### `app/Services/DocxFormStructureParser.php`

Parses a blank DOCX (no `{{placeholders}}`). Uses `ZipArchive` to read `word/document.xml` — same approach as `FormTemplateHelper::extractPlaceholdersFromDocx()` at line ~200 of `app/Helpers/FormTemplateHelper.php`. Reuse `FormTemplateHelper::normalizeFieldKey()` for key derivation.

Detection heuristics:

- Paragraphs ending in `:` or followed by `_____` underline runs → `text` field
- Two-column table rows (label | blank cell) → `text` field
- Cells containing `☐` / `☑` or `w:sym` checkbox elements → `checkbox` field
- Multi-line blank sections → `textarea`

```php
public function parse(string $absolutePath): array
// Returns: [['label', 'field_key', 'field_type', 'is_required', 'field_order', 'field_options'], ...]
```

### `app/Services/OcrService.php`

Thin HTTP client for the FastAPI sidecar.

```php
public function __construct(private string $serviceUrl, private int $timeout) {}
public function extract(string $imagePath): array  // POST /extract multipart
public function health(): bool                     // GET /health
```

Add to `config/services.php`: `'ocr' => ['url' => env('OCR_SERVICE_URL'), 'timeout' => 60]`.  
Bind in `AppServiceProvider::register()`.

### `app/Services/OcrFieldMatcherService.php`

```php
public function match(array $ocrBlocks, Collection $fields, array $imageDimensions): array
// Returns field_key → extracted text
// Strategy: bbox center falls inside ocr_region (converted from %)
// Fallback for fields without ocr_region: label-text proximity in bbox space
public function getImageDimensions(string $imagePath): array  // uses getimagesize()
```

---

## New Jobs

### `app/Jobs/ProcessFormScan.php`

```php
public int $tries = 3;
public int $timeout = 120;
public function __construct(public FormScan $formScan) {}

public function handle(OcrService $ocr, OcrFieldMatcherService $matcher): void
// 1. Mark status='processing'
// 2. OcrService::extract() → store to ocr_raw
// 3. Load form->fields with ocr_region
// 4. OcrFieldMatcherService::match() → store to ocr_result
// 5. Mark status='done'

public function failed(\Throwable $e): void
// Mark status='failed', store error_message
```

---

## New Controllers

### `app/Http/Controllers/Admin/FormDerivationController.php`

Mirrors `TemplateManagerController` upload→verify→confirm pattern exactly.

| Method             | Route                                        | Description                                                         |
| ------------------ | -------------------------------------------- | ------------------------------------------------------------------- |
| `showUpload()`     | GET `/admin/form-derivation`                 | Upload page for blank DOCX                                          |
| `storeUpload()`    | POST `/admin/form-derivation/upload`         | Store DOCX, create draft Form (is_active=false), redirect to review |
| `showReview(Form)` | GET `/admin/form-derivation/{form}/review`   | Parse DOCX via `DocxFormStructureParser`, show editable field table |
| `confirm(Form)`    | POST `/admin/form-derivation/{form}/confirm` | Upsert FormDescriptions, activate Form, store `ocr_reference_docx`  |
| `destroy(Form)`    | DELETE `/admin/form-derivation/{form}`       | Discard draft (no submissions)                                      |

### `app/Http/Controllers/Admin/FormSettingsController.php`

| Method         | Route                                |
| -------------- | ------------------------------------ |
| `show(Form)`   | GET `/admin/forms/{form}/settings`   |
| `update(Form)` | PATCH `/admin/forms/{form}/settings` |

Updates `allows_guest_scan` and `directory_assignment_key`. The `directory_assignment_key` dropdown lists only the key `student-leader-directory` for now.

### `app/Http/Controllers/OcrScanController.php`

No auth middleware at route level — controller checks `$form->allows_guest_scan` or `auth()->check()` and aborts 403 if neither.

| Method             | Route                               |
| ------------------ | ----------------------------------- |
| `store(Form)`      | POST `/api/forms/{form}/scan`       |
| `result(FormScan)` | GET `/api/form-scans/{scan}/result` |

`store()`: validates image (jpeg/png/jpg/webp ≤ 10 MB), saves to `storage/app/public/form-scans/{form_id}/`, creates `FormScan`, dispatches `ProcessFormScan`, returns `{ scan_id, status: 'pending' }`.

`result()`: returns `{ status, ocr_result }`. Alpine.js polls this every 2 s.

---

## New Blade Views

| File                                                           | Description                                                                                                                                                   |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `resources/views/pages/admin/form-derivation/upload.blade.php` | Copy drag-drop pattern from `admin/templates/upload.blade.php`; adds a "Form Name" text input                                                                 |
| `resources/views/pages/admin/form-derivation/review.blade.php` | Alpine.js table of detected fields; each row: Label (editable), Key (editable), Type (select), Required (toggle), Order (number). "Confirm" button at bottom. |
| `resources/views/pages/admin/form-settings/show.blade.php`     | Toggle for `allows_guest_scan`; select for `directory_assignment_key`; numeric inputs for `ocr_region` per field (advanced section, collapsible).             |
| `resources/views/components/form/ocr-scan-uploader.blade.php`  | Reusable Alpine.js component: file input → fetch POST → poll result → fire `ocr-result` window event with `{ fieldMap: {field_key: value} }`                  |

**Modification:** `resources/views/pages/form/student-leader-directory.blade.php`

- Wrap the existing `<form>` in an Alpine.js `x-data` with field state.
- Add `<x-form.ocr-scan-uploader :form="$form" />` above the form.
- Add `@ocr-result.window="applyOcrResult($event.detail.fieldMap)"` listener.
- OCR-filled inputs get `ocr-filled` CSS class (yellow border via Tailwind `border-yellow-400`).

---

## OCR Docker Service

### Files to create

**`docker/ocr/Dockerfile`**

```dockerfile
FROM python:3.11-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY main.py .
EXPOSE 5000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
```

**`docker/ocr/requirements.txt`**

```
fastapi==0.111.0
uvicorn==0.29.0
paddlepaddle==2.6.1
paddleocr==2.7.3
python-multipart==0.0.9
Pillow==10.3.0
```

**`docker/ocr/main.py`** — FastAPI app:

- Initializes `PaddleOCR(use_angle_cls=True, lang='en')` at startup
- `POST /extract`: accepts `file` (image), returns `{"blocks": [{"text":..., "bbox":[[x1,y1],[x2,y2],[x3,y3],[x4,y4]], "confidence":0.98}, ...]}`
- `GET /health`: returns `{"status": "ok"}`

### `compose.yaml` addition (after `mailhog` service)

```yaml
ocr:
  build:
    context: "./docker/ocr"
    dockerfile: Dockerfile
  ports:
    - "${OCR_PORT:-5000}:5000"
  networks:
    - sail
  healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost:5000/health"]
    interval: 30s
    timeout: 10s
    retries: 3
  restart: unless-stopped
```

Add `ocr` to `laravel.test.depends_on`.

### `.env` additions

```
OCR_SERVICE_URL=http://ocr:5000
OCR_TIMEOUT=60
```

---

## Routes (add to `routes/web.php`)

```php
// Admin — Form Derivation
Route::middleware(['auth', /* admin middleware */])->group(function () {
    Route::get('/admin/form-derivation', [FormDerivationController::class, 'showUpload'])->name('admin.form-derivation.upload');
    Route::post('/admin/form-derivation/upload', [FormDerivationController::class, 'storeUpload'])->name('admin.form-derivation.store');
    Route::get('/admin/form-derivation/{form}/review', [FormDerivationController::class, 'showReview'])->name('admin.form-derivation.review');
    Route::post('/admin/form-derivation/{form}/confirm', [FormDerivationController::class, 'confirm'])->name('admin.form-derivation.confirm');
    Route::delete('/admin/form-derivation/{form}', [FormDerivationController::class, 'destroy'])->name('admin.form-derivation.destroy');
    Route::get('/admin/forms/{form}/settings', [FormSettingsController::class, 'show'])->name('admin.forms.settings');
    Route::patch('/admin/forms/{form}/settings', [FormSettingsController::class, 'update'])->name('admin.forms.settings.update');
});

// OCR scan (auth OR guest-allowed — checked in controller)
Route::post('/api/forms/{form}/scan', [OcrScanController::class, 'store'])->name('api.forms.scan');
Route::get('/api/form-scans/{scan}/result', [OcrScanController::class, 'result'])->name('api.form-scans.result');
```

---

## Progress Tracker

> App root is `so-connect/`. Updated live as implementation proceeds.

### Phase 1 — Infrastructure
- [x] Migration A — `add_ocr_columns_to_forms_table`
- [x] Migration B — `add_ocr_region_to_form_descriptions_table`
- [x] Migration C — `create_form_scans_table`
- [x] `Form` model (fillable + cast + `scans()`)
- [x] `FormDescription` model (`ocr_region`)
- [x] `FormScan` model
- [x] `config/services.php` — `ocr`
- [x] `OcrService`
- [x] `AppServiceProvider` binding
- [x] Docker: `docker/ocr/{Dockerfile,requirements.txt,main.py}`
- [x] `compose.yaml` ocr service + `depends_on`
- [x] `.env` / `.env.example` additions

### Phase 2 — Async pipeline
- [x] `OcrFieldMatcherService`
- [x] `ProcessFormScan` job
- [x] `OcrScanController`
- [x] scan routes

### Phase 3 — Form Derivation (Admin)
- [x] `DocxFormStructureParser`
- [x] `FormDerivationController`
- [x] derivation routes
- [x] upload view
- [x] review view

> Phases 4–5 complete. Verification done: `php -l` on all new PHP files.
> **Not yet run** (needs Sail up): `php artisan migrate`, OCR Docker build,
> end-to-end scan/derivation/settings/pre-fill tests.

### Phase 4 — Form Settings (Admin)
- [x] `FormSettingsController`
- [x] settings routes
- [x] settings view (+ ocr_region calibration)

### Phase 5 — OCR Pre-fill UI
- [x] `ocr-scan-uploader` component
- [x] integrate into `student-leader-directory.blade.php`

---

## Implementation Order

| Phase                           | Steps                                                                                                                                                                             |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1 — Infrastructure**          | Migrations A/B/C → Model updates → `FormScan` model → Docker files → compose.yaml update → `.env` + `config/services.php` → `OcrService` → verify `/health` responds              |
| **2 — Async pipeline**          | `OcrFieldMatcherService` → `ProcessFormScan` job → `OcrScanController` → scan routes → manual job test (check `form_scans` row goes `done`, `ocr_raw` populated)                  |
| **3 — Form Derivation (Admin)** | `DocxFormStructureParser` → `FormDerivationController` → derivation routes → upload view → review view → end-to-end test: blank DOCX → Form + FormDescriptions created            |
| **4 — Form Settings (Admin)**   | `FormSettingsController` → settings routes → settings view → verify `allows_guest_scan` and `directory_assignment_key` save                                                       |
| **5 — OCR Pre-fill UI**         | `ocr-scan-uploader` component → add to `student-leader-directory.blade.php` → Alpine.js polling + `ocr-result` event → field highlight with `border-yellow-400` → end-to-end test |
| **6 — OCR Region Calibration**  | Numeric `ocr_region` input fields in Form Settings view → PATCH endpoint on `FormSettingsController` or a dedicated `FormDescriptionController` to save regions per field         |

---

## Critical Files to Reference During Implementation

- `app/Http/Controllers/Admin/TemplateManagerController.php` — exact pattern for upload→review→confirm
- `app/Helpers/FormTemplateHelper.php` — reuse `normalizeFieldKey()`, `extractPlaceholdersFromDocx()` ZipArchive approach
- `resources/views/pages/admin/templates/upload.blade.php` — drag-drop file input pattern to copy
- `resources/views/pages/form/student-leader-directory.blade.php` — form page to modify for OCR pre-fill
- `compose.yaml` — append `ocr` service here
- `app/Providers/AppServiceProvider.php` — add `OcrService` binding

---

## Verification

1. **Docker OCR service**: `curl http://localhost:5000/health` → `{"status":"ok"}`
2. **Form derivation**: Upload a test DOCX with labeled fields → review page shows detected rows → confirm → check DB: `forms` row created, `form_descriptions` rows per detected field
3. **Form settings**: Toggle `allows_guest_scan` on the derived form → verify DB column updates
4. **OCR scan (authenticated)**: Log in as officer → open student-leader-directory → upload a photo of a filled form → wait for poll to complete → form fields highlight yellow with pre-filled values → submit normally → check `form_submissions` payload
5. **OCR scan (guest)**: Without logging in, open student-leader-directory → upload scan → same pre-fill flow (only works if `allows_guest_scan=true`)
6. **Queue failure handling**: Stop the OCR container → upload scan → after 3 retries check `form_scans.status = 'failed'` and `error_message` populated
