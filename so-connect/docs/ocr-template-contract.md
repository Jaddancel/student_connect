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
      "field": "student_id"
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
  "raw":    { "student_id": "ID No 21-1234-567" }
}
```

- `fields[name]` — the regex-extracted value per zone `name` (empty string if the
  regex found nothing).
- `raw[name]` — the unfiltered OCR text for that zone (diagnostics).

`OcrClient` remaps `fields` keyed by zone `name` onto each zone's `field`, and
surfaces `student_id` explicitly.

## Health

`GET {OCR_SERVICE_URL}/health` -> `{ "status": "ok", "ocr": true }`.

## Failure behaviour

`OcrClient` treats any timeout, non-200, or unreachable sidecar as an empty
result (`{fields: [], student_id: null}`) and logs a warning — the directory
signup form still works without the sidecar running; auto-fill is simply skipped.
