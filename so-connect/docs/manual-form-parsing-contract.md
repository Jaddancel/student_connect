# Manual Form Parsing Contract

This document is the authoritative contract for the **Manual Filling** feature:
users print a partially filled request form, complete it by hand, upload a scan,
and a host-native Qwen 2.5 VL 3B vision model extracts the handwritten values so
they can be reviewed and submitted through the ordinary digital pipeline.

It defines three data boundaries and their JSON shapes:

1. **Template baseline schema** — generated once per explicit Form Builder save.
2. **Session schema** — generated per `Start Manual Filling` action from the
   exact frozen partial PDF.
3. **Extraction request/response** — what the app sends to / expects from the
   document-vision client when parsing a returned scan.

The vision model is dedicated to document vision only. It is **not** the Gemini
chat assistant and **not** the PaddleOCR ID/waiver scanner; those keep their own
contracts and behavior.

---

## 1. Shared primitives

### 1.1 Bounding boxes

All boxes are `[x1, y1, x2, y2]` with **normalized** coordinates in `[0, 1]`
relative to the page they belong to, origin at the top-left, `x1 <= x2` and
`y1 <= y2`. A box that falls outside `[0, 1]`, is inverted, or is not a 4-number
array is **invalid** and MUST be discarded (the field becomes `unresolved`).

### 1.2 Page references

Pages are 0-indexed integers matching the ordered rasterized pages of the frozen
partial PDF. A field references the single page its writable area sits on. Scan
alignment (see the OCR sidecar contract) maps returned scan pages back onto these
reference page indices before extraction.

### 1.3 Field types and paper support

Each field carries its Form Builder `type`. Paper support is classified centrally
(see `App\Forms\FieldType`). Only paper-supported fields ever appear in an
extraction request. Digital-only fields (passwords, generic files/images, ID/
waiver scans, computed/relationship-backed controls) are never sent to the model.

`paper_support` values:

- `extract` — a text-like value the model can read (text, number, email, date,
  time, select/radio/checkbox with static options, text-list, table cells).
- `signature` — reported as presence + bounding box only, never as text.
- `digital_only` — excluded from all schemas' extractable manifest.

### 1.4 Failure sentinels

Every client boundary returns a structured envelope, never raw model output on
error:

```json
{
    "ok": false,
    "status": "timeout|unreachable|http_error|invalid_output|unavailable",
    "error": "human-readable reason"
}
```

`status` values are a closed set. On `ok: false` the caller keeps the draft
resumable and surfaces a retry; it never renders `error` as field data.

---

## 2. Template baseline schema

**Input** (built server-side, not from the model): the saved Form Builder field
metadata for the form plus a blank render of the exact adopted Step 2 template
revision.

```json
{
    "template_id": 42,
    "template_version": 7,
    "schema_template_version": 1,
    "fields": [
        {
            "key": "activity_title",
            "label": "Activity Title",
            "type": "text",
            "required": true,
            "paper_support": "extract",
            "options": null
        },
        {
            "key": "committee",
            "label": "Committee",
            "type": "select",
            "required": false,
            "paper_support": "extract",
            "options": [
                { "value": "acad", "label": "Academic" },
                { "value": "sports", "label": "Sports" }
            ]
        }
    ]
}
```

Additionally, a locate pass records each paper field's writable area:

```json
{
    "key": "activity_title",
    "page": 0,
    "bounds": [0.12, 0.22, 0.88, 0.26],
    "writable_area": "present",
    "cell_path": { "block": 4, "row": 1, "col": 2, "colspan": 1, "rowspan": 1 }
}
```

Writable areas come from one of two sources:

- **Table cells (deterministic).** When a field's `{{key}}` placeholder sits
  inside a non-repeating table, its `page`/`bounds`/`writable_area` are computed
  directly from the OOXML cell geometry (column widths from `w:tblGrid`, row
  heights from `w:trHeight` or estimated from content). Such a field carries a
  `cell_path` locating the encapsulating cell. No vision model is involved.
- **Free-flow fields (vision).** Fields not found in a table fall back to a
  blank render read by the document-vision model; they have no `cell_path`.

`writable_area` is `present`, `missing`, or `uncertain`. For table cells the
verdict uses a minimum-size heuristic: the blank room the cell leaves (its size
minus any label text preceding the placeholder) is compared against per-field-type
minimums in `config/manual_form.php`. A **required** field with a `missing`
writable area marks manual filling **unavailable** for that template version
(ordinary online submission stays available).

**Scope:** deterministic cell geometry covers non-repeating, single-page tables.
Repeating tables (`data-field-rows` / row cloning) and fields that spill across
pages keep using the vision path.

**Status:** the template row stores `schema_status` of `pending`, `ready`, or
`failed` with an optional `schema_error`, and `schema_generated_at`.

---

## 3. Session schema

**Input:** the baseline definition + the exact frozen partial PDF's rasterized
pages + `known_values` (fields already filled at Start time) + the set of fields
**empty at Start** (the only fields the user is expected to write by hand).

```json
{
    "session_id": "9f1c…uuid",
    "template_version": 7,
    "template_hash": "sha256:…",
    "pages": [{ "index": 0, "width": 1240, "height": 1754 }],
    "known_values": { "activity_title": "Robotics Expo" },
    "extractable_fields": [
        {
            "key": "venue",
            "label": "Venue",
            "type": "text",
            "paper_support": "extract",
            "options": null,
            "page": 0,
            "bounds": [0.12, 0.4, 0.88, 0.44]
        }
    ],
    "digital_only_fields": ["adviser_signature_file"]
}
```

If a required extractable field has **no** locatable writable region in the frozen
partial PDF, session-schema generation **blocks** the session (`status: failed`)
with a named-field warning; the user cannot enter the drop state for that draft.

**Writable areas are re-measured per session, not copied from the baseline.**
The partial document is populated with the real `known_values`, so a long digital
entry can grow a table row and push the fields below it down (or off the page) —
a distortion the blank template never shows. For each paper field:

- With a baseline `cell_path` → its cell is re-measured against the session's
  populated `.docx`, so the verdict reflects the actual printed layout.
- Without a `cell_path` → the document-vision model re-locates it on the frozen
  partial pages (not the blank render).

---

## 4. Extraction request / response

### 4.1 Request (app → document-vision client)

The app sends ordered `(reference_page, scan_page)` image pairs plus the session
schema. Images are base64 PNG/JPEG, longest edge clamped to
`DOCUMENT_VISION_MAX_IMAGE_EDGE`.

```json
{
    "model": "qwen2.5vl:3b",
    "known_values": { "activity_title": "Robotics Expo" },
    "fields": [
        {
            "key": "venue",
            "type": "text",
            "options": null,
            "page": 0,
            "bounds": [0.12, 0.4, 0.88, 0.44]
        },
        {
            "key": "committee",
            "type": "select",
            "options": [{ "value": "acad", "label": "Academic" }],
            "page": 0,
            "bounds": [0.12, 0.5, 0.6, 0.54]
        },
        {
            "key": "adviser_signature",
            "type": "signature",
            "page": 0,
            "bounds": [0.55, 0.8, 0.9, 0.9]
        }
    ],
    "pages": [
        { "index": 0, "reference_image": "<base64>", "scan_image": "<base64>" }
    ]
}
```

**Model instructions (system prompt), enforced verbatim:**

- Extract **only** the fields listed in `fields`. Ignore everything else.
- **Never** overwrite or re-read a key present in `known_values`; those were
  printed, not handwritten.
- Return `null` for any field you cannot read confidently. **Never infer,
  guess, or complete** a value.
- For `select`/`radio`/`checkbox`, return the option **`value`** whose `label`
  best matches the handwriting; if none matches, return `null`.
- For `signature`, report **presence and bounding box only**. Never return text
  for a signature.
- Report a `confidence` in `[0, 1]` per field.
- Respond with **strict JSON only**, no prose, matching the response shape below.

### 4.2 Response (model → app, before validation)

```json
{
    "fields": {
        "venue": {
            "value": "Gymnasium",
            "confidence": 0.91,
            "page": 0,
            "bounds": [0.13, 0.4, 0.52, 0.44]
        },
        "committee": {
            "value": "sports",
            "confidence": 0.72,
            "page": 0,
            "bounds": [0.13, 0.5, 0.4, 0.54]
        },
        "adviser_signature": {
            "present": true,
            "confidence": 0.88,
            "page": 0,
            "bounds": [0.56, 0.81, 0.89, 0.89]
        }
    },
    "warnings": ["page 1 low contrast"]
}
```

### 4.3 Normalized result (app → session, after strict validation)

The client **strictly validates** raw model JSON against the requested field
keys/types/options and returns a normalized envelope. Unknown keys are dropped,
malformed boxes discarded, option labels mapped to canonical values, out-of-range
confidence clamped, and non-declared fields ignored.

```json
{
    "ok": true,
    "model": "qwen2.5vl:3b",
    "values": { "venue": "Gymnasium", "committee": "sports" },
    "signatures": {
        "adviser_signature": {
            "present": true,
            "page": 0,
            "bounds": [0.56, 0.81, 0.89, 0.89]
        }
    },
    "confidence": {
        "venue": 0.91,
        "committee": 0.72,
        "adviser_signature": 0.88
    },
    "unresolved": ["remarks"],
    "warnings": ["page 1 low contrast"]
}
```

`values` holds only successfully read, type/option-valid fields whose confidence
is meaningful; anything below `DOCUMENT_VISION_CONFIDENCE_THRESHOLD` is surfaced
as low-confidence for review but **still shown**, never silently dropped.
`unresolved` lists requested keys the model returned `null` for or that failed
validation. Signatures never appear in `values`.

---

## 5. Provider boundary

`App\Services\DocumentVision\DocumentVisionClient` is the provider-independent
interface. `OllamaDocumentVisionClient` implements it against Ollama's
`/api/chat` with `stream: false`, `format: json`, `temperature: 0`, and base64
images. The interface leaves room for a future Gemini-vision fallback, which is
**out of scope** for this implementation.

Guarantees for every provider:

- Never throw into the request/job flow; always return the structured envelope.
- Never log image bytes or full model payloads at info level.
- Never render model output to a user without passing ordinary validation and
  authorization for the target form.
