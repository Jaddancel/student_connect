"""PaddleOCR sidecar for the ID-template scanner.

Contract (frozen in docs/ocr-template-contract.md):

  POST /scan   multipart:
      image     — the student's ID photo (jpg/png)
      template  — JSON: {reference:{width,height}, zones:[{name,x1,y1,x2,y2,regex,field}, ...]}
  ->  {"fields": {"<name>": "<text>", ...}, "raw": {"<name>": "<ocr text>", ...}}

  GET /health -> {"status": "ok"}

The incoming photo is scaled to the template's reference dimensions so the
native-pixel zone rectangles line up, each zone is cropped and OCR'd, and an
optional per-zone regex extracts the first match from that zone's text.
"""

import io
import json
import re

from fastapi import FastAPI, Form, HTTPException, UploadFile
from PIL import Image

try:  # PaddleOCR is heavy; keep import failures debuggable.
    from paddleocr import PaddleOCR

    _ocr = PaddleOCR(use_angle_cls=True, lang="en", show_log=False)
except Exception as exc:  # pragma: no cover - depends on runtime image
    _ocr = None
    _ocr_error = str(exc)

app = FastAPI(title="ID Template OCR Sidecar")


@app.get("/health")
def health():
    return {"status": "ok", "ocr": _ocr is not None}


def _ocr_text(crop: Image.Image) -> str:
    """Run PaddleOCR on a cropped region and join the recognized lines."""
    if _ocr is None:
        raise HTTPException(status_code=503, detail=f"OCR engine unavailable: {_ocr_error}")

    import numpy as np

    result = _ocr.ocr(np.array(crop.convert("RGB")), cls=True)
    lines = []
    for page in result or []:
        for entry in page or []:
            # entry = [box, (text, confidence)]
            try:
                lines.append(entry[1][0])
            except (IndexError, TypeError):
                continue
    return " ".join(lines).strip()


def _apply_regex(pattern: str, text: str) -> str:
    """Return the first regex match (or full match group) within text."""
    if not pattern:
        return text
    try:
        compiled = re.compile(pattern)
    except re.error:
        # A bad pattern should never crash the request; fall back to raw text.
        return text
    match = compiled.search(text)
    if not match:
        return ""
    return match.group(0)


@app.post("/scan")
async def scan(image: UploadFile, template: str = Form(...)):
    try:
        spec = json.loads(template)
    except json.JSONDecodeError:
        raise HTTPException(status_code=422, detail="template is not valid JSON")

    reference = spec.get("reference") or {}
    ref_w = int(reference.get("width") or 0)
    ref_h = int(reference.get("height") or 0)
    zones = spec.get("zones") or []
    if ref_w <= 0 or ref_h <= 0:
        raise HTTPException(status_code=422, detail="template.reference dimensions required")

    raw = await image.read()
    try:
        photo = Image.open(io.BytesIO(raw))
    except Exception:
        raise HTTPException(status_code=422, detail="image could not be decoded")

    # Normalize the photo to the reference frame the zones were authored against.
    photo = photo.resize((ref_w, ref_h))

    fields: dict[str, str] = {}
    raw_texts: dict[str, str] = {}

    for zone in zones:
        name = zone.get("name")
        if not name:
            continue
        x1 = max(0, int(zone.get("x1", 0)))
        y1 = max(0, int(zone.get("y1", 0)))
        x2 = min(ref_w, int(zone.get("x2", 0)))
        y2 = min(ref_h, int(zone.get("y2", 0)))
        if x2 <= x1 or y2 <= y1:
            continue

        crop = photo.crop((x1, y1, x2, y2))
        text = _ocr_text(crop)
        raw_texts[name] = text
        fields[name] = _apply_regex(zone.get("regex") or "", text)

    return {"fields": fields, "raw": raw_texts}
