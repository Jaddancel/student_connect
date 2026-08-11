"""PaddleOCR sidecar for the ID-template scanner.

Contract (frozen in docs/ocr-template-contract.md):

  POST /scan   multipart:
      image     — the student's ID photo (jpg/png)
      template  — JSON: {reference:{width,height}, zones:[{name,x1,y1,x2,y2,regex,field,type}, ...]}
  ->  {"fields": {"<name>": "<text>", ...}, "raw": {"<name>": "<ocr text>", ...},
       "images": {"<name>": "data:image/png;base64,...", ...}}

  Zones with type == "signature" are NOT OCR'd. The ink inside the zone is
  extracted and returned as a transparent base64 PNG under `images` — the ID's
  artwork, the printed signature line and the card background are discarded, so
  the caller stores the signature itself rather than a picture of the card.

  POST /signature-identify   multipart:
      probe      — the freshly-drawn signature image (png)
      candidates — JSON: [{"id": <int>, "image": "<base64 png>"}, ...]
  ->  {"ok": true, "match": bool, "best": {"id": int, "score": float} | null,
       "threshold": float}

  GET /health -> {"status": "ok"}

The incoming photo is scaled to the template's reference dimensions so the
native-pixel zone rectangles line up, each zone is cropped and OCR'd, and an
optional per-zone regex extracts the first match from that zone's text.
"""

import base64
import io
import json
import os
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


MIN_INK_PIXELS = 40
MIN_SHAPE_PIXELS = 12
MIN_SHAPE_RATIO = 0.02
MAX_INK_COVERAGE = 0.35
CROP_PADDING = 6


def _extract_signature(crop: Image.Image) -> str | None:
    """Isolate the ink in a signature zone and encode it as a transparent PNG.

    A zone rectangle drawn on an ID template always contains more than the
    signature — card artwork, the printed rule the holder signs on, a slice of
    the photo next to it. Returning that whole rectangle stored a picture of the
    card, and gave the verifier a background to match on instead of a signature.

    The ink is separated from whatever is behind it by comparing each pixel with
    a blurred copy of its own surroundings (so a card printed on a coloured or
    shaded panel still works), then dropping shapes that run off the edge of the
    zone — the printed rule and the neighbouring artwork do, the signature does
    not. Returns None when the zone holds no legible ink.
    """
    import cv2
    import numpy as np

    gray = np.array(crop.convert("L"))
    height, width = gray.shape
    if height < 8 or width < 8:
        return None

    # Local background: a heavy blur over a window wider than any pen stroke.
    window = max(3, (min(height, width) // 4) | 1)
    background = cv2.GaussianBlur(gray, (window, window), 0)
    mask = ((background.astype("int16") - gray.astype("int16")) >= 26).astype("uint8")

    ink = int(mask.sum())
    if ink < MIN_INK_PIXELS or ink / float(height * width) > MAX_INK_COVERAGE:
        return None

    count, labels, stats, _ = cv2.connectedComponentsWithStats(mask, connectivity=8)
    keep = np.zeros_like(mask)
    largest = max((stats[i, cv2.CC_STAT_AREA] for i in range(1, count)), default=0)
    minimum = max(MIN_SHAPE_PIXELS, int(largest * MIN_SHAPE_RATIO))

    for i in range(1, count):
        x, y, w, h, area = (
            stats[i, cv2.CC_STAT_LEFT], stats[i, cv2.CC_STAT_TOP],
            stats[i, cv2.CC_STAT_WIDTH], stats[i, cv2.CC_STAT_HEIGHT],
            stats[i, cv2.CC_STAT_AREA],
        )
        if area < minimum:
            continue
        # Runs off the edge of the zone: the printed line or adjacent artwork.
        if x == 0 or y == 0 or x + w >= width or y + h >= height:
            continue
        keep[labels == i] = 1

    if int(keep.sum()) < MIN_INK_PIXELS:
        return None

    ys, xs = np.nonzero(keep)
    x1 = max(0, int(xs.min()) - CROP_PADDING)
    x2 = min(width, int(xs.max()) + 1 + CROP_PADDING)
    y1 = max(0, int(ys.min()) - CROP_PADDING)
    y2 = min(height, int(ys.max()) + 1 + CROP_PADDING)

    cropped = keep[y1:y2, x1:x2]
    rgba = np.zeros((cropped.shape[0], cropped.shape[1], 4), dtype="uint8")
    rgba[..., 0], rgba[..., 1], rgba[..., 2] = 20, 20, 30
    rgba[..., 3] = cropped * 255

    buf = io.BytesIO()
    Image.fromarray(rgba, mode="RGBA").save(buf, format="PNG")
    return "data:image/png;base64," + base64.b64encode(buf.getvalue()).decode("ascii")


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
    images: dict[str, str] = {}

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

        # Signature zones return extracted ink, not text — OCR'ing a signature
        # yields garbage, and the caller wants the signature itself to store and
        # compare. A zone with nothing legible in it is simply omitted.
        if (zone.get("type") or "text") == "signature":
            extracted = _extract_signature(crop)
            if extracted is not None:
                images[name] = extracted
            continue

        text = _ocr_text(crop)
        raw_texts[name] = text
        fields[name] = _apply_regex(zone.get("regex") or "", text)

    return {"fields": fields, "raw": raw_texts, "images": images}


# --- signature identification ------------------------------------------------

# Measured on the three-signature scenario suite (tests/Feature/
# SignatureRecognitionScenarioTest.php): a signature captured a second time —
# re-photographed at another angle, scale and exposure — scores 0.41..0.52
# against its own stored reference, while a different person's signature never
# passes 0.09. 0.45 sat inside the genuine range and rejected half of the real
# re-captures; 0.30 clears every genuine pairing and still leaves 3x headroom
# over the closest impostor. Override with SIGNATURE_MATCH_THRESHOLD.
SIGNATURE_MATCH_THRESHOLD = float(os.environ.get("SIGNATURE_MATCH_THRESHOLD", "0.30"))
_SIG_SIZE = (320, 160)  # normalized (w, h) frame every signature is compared in


def _normalize_signature(img: Image.Image):
    """Grayscale → Otsu ink mask → crop to ink bbox → resize to a fixed frame.

    Returns a float32 numpy array in [0,1] (ink=1), or None when the image has
    no discernible ink (blank canvas, undecodable content).
    """
    import cv2
    import numpy as np

    # Stored references are transparent-background ink PNGs (that is how every
    # capture surface saves a signature), while probes arrive opaque — a pad
    # drawing on white, or a photo. `convert("L")` drops alpha, which turned a
    # transparent reference into near-black ink on a black ground: Otsu then
    # returned the background instead of the strokes and no genuine signature
    # ever cleared the threshold. Flatten onto white first so both sides reach
    # the threshold as dark ink on a light ground.
    if img.mode in ("RGBA", "LA", "P"):
        opaque = Image.new("RGBA", img.size, (255, 255, 255, 255))
        img = Image.alpha_composite(opaque, img.convert("RGBA"))

    gray = np.array(img.convert("L"))
    # Otsu splits ink from paper regardless of pen darkness / jpeg noise.
    _, mask = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)

    ys, xs = np.nonzero(mask)
    if len(xs) < 30:  # fewer than ~30 ink pixels: nothing was drawn
        return None
    x1, x2 = xs.min(), xs.max() + 1
    y1, y2 = ys.min(), ys.max() + 1
    cropped = mask[y1:y2, x1:x2]

    # Pad to the target aspect so resizing never stretches the strokes.
    target_w, target_h = _SIG_SIZE
    h, w = cropped.shape
    scale = min(target_w / w, target_h / h)
    new_w, new_h = max(1, int(w * scale)), max(1, int(h * scale))
    resized = cv2.resize(cropped, (new_w, new_h), interpolation=cv2.INTER_AREA)
    canvas = np.zeros((target_h, target_w), dtype=np.uint8)
    ox, oy = (target_w - new_w) // 2, (target_h - new_h) // 2
    canvas[oy:oy + new_h, ox:ox + new_w] = resized

    # A light blur makes the correlation tolerant of stroke jitter.
    blurred = cv2.GaussianBlur(canvas, (5, 5), 0)
    return blurred.astype("float32") / 255.0


def _signature_score(probe, candidate) -> float:
    """Similarity in [0,1]: half normalized cross-correlation of the ink maps,
    half ORB keypoint agreement (Lowe ratio test)."""
    import cv2
    import numpy as np

    # Pearson correlation over the normalized ink maps.
    a = probe - probe.mean()
    b = candidate - candidate.mean()
    denom = float(np.sqrt((a * a).sum()) * np.sqrt((b * b).sum()))
    ncc = float((a * b).sum() / denom) if denom > 1e-6 else 0.0
    ncc = max(0.0, min(1.0, ncc))

    # ORB feature agreement on the 8-bit maps.
    orb = cv2.ORB_create(nfeatures=300)
    img_a = (probe * 255).astype("uint8")
    img_b = (candidate * 255).astype("uint8")
    kp_a, des_a = orb.detectAndCompute(img_a, None)
    kp_b, des_b = orb.detectAndCompute(img_b, None)
    orb_ratio = 0.0
    if des_a is not None and des_b is not None and len(kp_a) >= 2 and len(kp_b) >= 2:
        matcher = cv2.BFMatcher(cv2.NORM_HAMMING)
        good = 0
        for pair in matcher.knnMatch(des_a, des_b, k=2):
            if len(pair) == 2 and pair[0].distance < 0.75 * pair[1].distance:
                good += 1
        orb_ratio = min(1.0, good / max(1, min(len(kp_a), len(kp_b))))

    return max(0.0, min(1.0, 0.5 * ncc + 0.5 * orb_ratio))


# --- SigNet (deep) scoring, optional -----------------------------------------
#
# SIGNATURE_ENGINE=signet switches scoring to a SigNet CNN embedding (cosine
# similarity) via the `sigver` package + a CPU torch build. It is fully
# additive: if torch / the weights are unavailable, or the engine is left at
# "classical", the NCC+ORB scorer above is used instead. When embeddings are
# produced they are also returned so callers can cache them.

SIGNATURE_ENGINE = os.environ.get("SIGNATURE_ENGINE", "classical").lower()
SIGNET_WEIGHTS = os.environ.get("SIGNET_WEIGHTS", "/models/signet.pth")
_signet_state = {"tried": False, "model": None}


def _load_signet():
    """Lazily load the SigNet model once; return it or None if unavailable."""
    if _signet_state["tried"]:
        return _signet_state["model"]
    _signet_state["tried"] = True
    try:
        import torch
        from sigver.featurelearning.models import SigNet

        model = SigNet()
        state = torch.load(SIGNET_WEIGHTS, map_location="cpu")
        model.load_state_dict(state.get("model") if isinstance(state, dict) and "model" in state else state)
        model.eval()
        _signet_state["model"] = model
    except Exception:
        _signet_state["model"] = None
    return _signet_state["model"]


def _signet_embedding(img: Image.Image):
    """Return an L2-normalized SigNet embedding (list of floats) or None."""
    model = _load_signet()
    if model is None:
        return None
    try:
        import numpy as np
        import torch

        norm = _normalize_signature(img)
        if norm is None:
            return None
        # SigNet expects a 150x220 single-channel input; reuse the ink map.
        import cv2
        resized = cv2.resize((norm * 255).astype("uint8"), (220, 150))
        tensor = torch.from_numpy(resized).float().div(255.0).unsqueeze(0).unsqueeze(0)
        with torch.no_grad():
            feats = model(tensor).squeeze(0).numpy()
        n = float(np.linalg.norm(feats))
        if n < 1e-8:
            return None
        return (feats / n).astype("float32").tolist()
    except Exception:
        return None


def _cosine(a, b) -> float:
    import numpy as np

    va, vb = np.asarray(a, dtype="float32"), np.asarray(b, dtype="float32")
    denom = float(np.linalg.norm(va) * np.linalg.norm(vb))
    if denom < 1e-8:
        return 0.0
    # Map cosine [-1,1] → [0,1] to match the classical score range.
    return max(0.0, min(1.0, (float(va.dot(vb) / denom) + 1.0) / 2.0))


@app.post("/signature-identify")
async def signature_identify(probe: UploadFile, candidates: str = Form(...)):
    try:
        entries = json.loads(candidates)
    except json.JSONDecodeError:
        raise HTTPException(status_code=422, detail="candidates is not valid JSON")
    if not isinstance(entries, list):
        raise HTTPException(status_code=422, detail="candidates must be a list")

    raw = await probe.read()
    try:
        probe_img = Image.open(io.BytesIO(raw))
    except Exception:
        raise HTTPException(status_code=422, detail="probe could not be decoded")

    probe_norm = _normalize_signature(probe_img)
    if probe_norm is None:
        return {"ok": True, "match": False, "best": None,
                "threshold": SIGNATURE_MATCH_THRESHOLD, "note": "empty probe"}

    # Deep path when SIGNATURE_ENGINE=signet and torch/weights are present;
    # otherwise probe_emb stays None and every candidate uses the classical scorer.
    probe_emb = _signet_embedding(probe_img) if SIGNATURE_ENGINE == "signet" else None

    best_id = None
    best_score = 0.0
    for entry in entries:
        # SigNet: cosine of embeddings, reusing a candidate's cached embedding
        # when the caller supplied one.
        if probe_emb is not None:
            cand_emb = entry.get("embedding")
            if not cand_emb:
                try:
                    image_bytes = base64.b64decode(str(entry.get("image") or ""), validate=False)
                    cand_emb = _signet_embedding(Image.open(io.BytesIO(image_bytes)))
                except Exception:
                    cand_emb = None
            if cand_emb:
                score = _cosine(probe_emb, cand_emb)
                if score > best_score:
                    best_score = score
                    best_id = entry.get("id")
                continue

        # Classical NCC+ORB fallback.
        try:
            image_bytes = base64.b64decode(str(entry.get("image") or ""), validate=False)
            candidate_img = Image.open(io.BytesIO(image_bytes))
        except Exception:
            continue
        candidate_norm = _normalize_signature(candidate_img)
        if candidate_norm is None:
            continue
        score = _signature_score(probe_norm, candidate_norm)
        if score > best_score:
            best_score = score
            best_id = entry.get("id")

    return {
        "ok": True,
        "match": best_id is not None and best_score >= SIGNATURE_MATCH_THRESHOLD,
        "best": None if best_id is None else {"id": best_id, "score": round(best_score, 4)},
        "threshold": SIGNATURE_MATCH_THRESHOLD,
        # Additive fields (existing callers ignore them).
        "engine": "signet" if probe_emb is not None else "classical",
        "embedding": probe_emb,
    }


# --- waiver scanning ---------------------------------------------------------
#
# Waiver templates declare text / signature / stamp zones (x,y,w,h in the
# reference frame). Text zones are OCR'd, signature zones returned as crops (and
# flagged present), stamp zones checked for an embossed dry seal.


def _detect_stamp(crop: "Image.Image"):
    """Detect an embossed dry seal in a crop: CLAHE contrast → gradient
    magnitude → Hough circle. Returns (present: bool, confidence: float)."""
    import cv2
    import numpy as np

    gray = np.array(crop.convert("L"))
    if gray.size == 0 or min(gray.shape) < 16:
        return False, 0.0

    clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
    eq = clahe.apply(gray)
    gx = cv2.Sobel(eq, cv2.CV_32F, 1, 0, ksize=3)
    gy = cv2.Sobel(eq, cv2.CV_32F, 0, 1, ksize=3)
    mag = cv2.magnitude(gx, gy)
    mag = cv2.normalize(mag, None, 0, 255, cv2.NORM_MINMAX).astype("uint8")
    blurred = cv2.GaussianBlur(mag, (5, 5), 0)

    h, w = blurred.shape
    min_r = max(8, int(min(h, w) * 0.15))
    max_r = max(min_r + 1, int(min(h, w) * 0.6))
    circles = cv2.HoughCircles(
        blurred, cv2.HOUGH_GRADIENT, dp=1.2, minDist=max(h, w),
        param1=120, param2=30, minRadius=min_r, maxRadius=max_r,
    )
    if circles is not None and len(circles[0]) > 0:
        confidence = min(1.0, float(mag.mean()) / 128.0)
        return True, round(confidence, 4)
    return False, 0.0


@app.post("/waiver-scan")
async def waiver_scan(image: UploadFile, template: str = Form(...)):
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
    photo = photo.resize((ref_w, ref_h))

    fields: dict[str, str] = {}
    images: dict[str, str] = {}
    signature = False
    stamp = False
    stamp_confidence = 0.0

    for zone in zones:
        name = zone.get("name")
        if not name:
            continue
        x = max(0, int(zone.get("x", 0)))
        y = max(0, int(zone.get("y", 0)))
        x2 = min(ref_w, x + int(zone.get("w", 0)))
        y2 = min(ref_h, y + int(zone.get("h", 0)))
        if x2 <= x or y2 <= y:
            continue

        crop = photo.crop((x, y, x2, y2))
        ztype = zone.get("type") or "text"

        if ztype == "signature":
            extracted = _extract_signature(crop)
            if extracted is not None:
                images[name] = extracted
                signature = True
            continue

        if ztype == "stamp":
            present, conf = _detect_stamp(crop)
            stamp = stamp or present
            stamp_confidence = max(stamp_confidence, conf)
            continue

        fields[name] = _ocr_text(crop)

    return {
        "ok": True,
        "fields": fields,
        "images": images,
        "signature": signature,
        "stamp": stamp,
        "stamp_confidence": stamp_confidence,
    }
