"""PaddleOCR FastAPI sidecar for StudentConnect.

Exposes two endpoints:
  GET  /health   → {"status": "ok"}
  POST /extract  → {"blocks": [{"text", "bbox", "confidence"}, ...]}

The OCR model is initialised once at startup and reused across requests.
"""

import io

from fastapi import FastAPI, File, HTTPException, UploadFile
from paddleocr import PaddleOCR
from PIL import Image
import numpy as np

app = FastAPI(title="StudentConnect OCR", version="1.0.0")

# Initialise once — model loading is expensive. use_angle_cls handles rotated text.
ocr_engine = PaddleOCR(use_angle_cls=True, lang="en", show_log=False)


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/extract")
async def extract(file: UploadFile = File(...)) -> dict:
    try:
        raw = await file.read()
        image = Image.open(io.BytesIO(raw)).convert("RGB")
    except Exception as exc:  # noqa: BLE001
        raise HTTPException(status_code=422, detail=f"Invalid image: {exc}")

    image_array = np.array(image)
    width, height = image.size

    result = ocr_engine.ocr(image_array, cls=True)

    blocks = []
    # PaddleOCR returns [[ [bbox, (text, confidence)], ... ]] (one entry per page/image).
    for page in result or []:
        for line in page or []:
            box, (text, confidence) = line[0], line[1]
            blocks.append(
                {
                    "text": text,
                    "bbox": [[float(x), float(y)] for x, y in box],
                    "confidence": float(confidence),
                }
            )

    return {"blocks": blocks, "image": {"width": width, "height": height}}
