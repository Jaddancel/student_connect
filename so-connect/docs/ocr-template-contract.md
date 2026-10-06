# OCR Template Contract

This freezes the request/response schema between the Laravel app
(`App\Services\OcrClient`) and the PaddleOCR sidecar (`docker/ocr/app.py`), so
either side can be reimplemented independently as long as it honours this shape.

## Template payload

An `App\Models\IdTemplate` is serialized by `IdTemplate::toScannerPayload()`:

```json
{
  "template_id": 12,
  "name": "University X Student ID 2026",
  "reference": { "width": 1012, "height": 638 },
  "zones": [
    {
      "name": "student_id",
      "label": "Student ID Number",
      "x1": 120, "y1": 340, "x2": 560, "y2": 400,
      "regex": "\\b\\d{2}-\\d{4}-\\d{3}\\b",
      "field": "student_id",
      "type": "text"
    }
  ]
}
```

- `reference.width/height` — the native pixel dimensions the zones were authored
  against. The sidecar scales the incoming photo to these dimensions before
  cropping, so zone rectangles line up regardless of the uploaded photo's size.
- `zones[].x1,y1,x2,y2` — native-pixel rectangle (top-left / bottom-right),
  `x1 < x2`, `y1 < y2`, within `reference`.
- `zones[].name` — machine key, `^[a-z0-9_]+$`, unique within the template.
- `zones[].regex` — optional raw PCRE (no delimiters/flags); applied to that
  zone's OCR text, first match wins. Invalid patterns fall back to raw text.
- `zones[].field` — destination key. `student_id` is written through to the
  directory form's Student ID input; other snake_case keys are extraction-only.
- `zones[].type` — `"text"` (default, OCR'd) or `"signature"`: the zone's crop
  is returned as a base64 PNG under `images` instead of being OCR'd.

## Request

`POST {OCR_SERVICE_URL}/scan` — `multipart/form-data`:

| part | type | notes |
|---|---|---|
| `image` | file | the ID photo (jpg/png) |
| `template` | string | the JSON payload above |

## Response

```json
{
  "fields": { "student_id": "21-1234-567" },
  "raw":    { "student_id": "ID No 21-1234-567" },
  "images": { "signature_zone": "data:image/png;base64,..." }
}
```

- `fields[name]` — the regex-extracted value per zone `name` (empty string if the
  regex found nothing).
- `raw[name]` — the unfiltered OCR text for that zone (diagnostics).
- `images[name]` — for `type: "signature"` zones only: the zone's crop as a
  base64 PNG data-URL (such zones appear in neither `fields` nor `raw`).

`OcrClient` remaps `fields`/`images` keyed by zone `name` onto each zone's
`field`, and surfaces `student_id` explicitly.

## Signature identification

`POST {OCR_SERVICE_URL}/signature-identify` — `multipart/form-data`:

| part | type | notes |
|---|---|---|
| `probe` | file | the freshly-drawn signature (png) |
| `candidates` | string | JSON `[{"id": 7, "image": "<base64 png>"}, ...]` |

Response:

```json
{ "ok": true, "match": true, "best": { "id": 7, "score": 0.62 }, "threshold": 0.45 }
```

Each image is normalized (grayscale → Otsu ink mask → crop to ink → 320×160
frame) and scored `0.5·NCC + 0.5·ORB match ratio` against the probe; `match` is
true when the best score clears `SIGNATURE_MATCH_THRESHOLD` (env, default 0.45).
An empty/blank probe returns `match: false` with `"note": "empty probe"`.

## Health

`GET {OCR_SERVICE_URL}/health` -> `{ "status": "ok", "ocr": true }`.

## Failure behaviour

`OcrClient` treats any timeout, non-200, or unreachable sidecar as an empty
result (`{fields: [], student_id: null}`) and logs a warning — the directory
signup form still works without the sidecar running; auto-fill is simply skipped.
