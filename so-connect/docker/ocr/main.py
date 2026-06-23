"""PaddleOCR FastAPI sidecar for StudentConnect.

Exposes:
  POST /extract  (multipart "file") -> {"blocks": [{"text", "bbox", "confidence"}, ...]}
  GET  /health                      -> {"status": "ok"}
"""

import io

from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from paddleocr import PaddleOCR
from PIL import Image

app = FastAPI(title="StudentConnect OCR")

# Initialise once at startup. use_angle_cls handles rotated text; lang='en'.
ocr = PaddleOCR(use_angle_cls=True, lang="en", show_log=False)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/extract")
async def extract(file: UploadFile = File(...)):
    raw = await file.read()

    # Normalise to RGB so PaddleOCR receives a consistent array.
    image = Image.open(io.BytesIO(raw)).convert("RGB")

    import numpy as np

    result = ocr.ocr(np.array(image), cls=True)

    blocks = []
    # PaddleOCR returns a list (per image) of [bbox, (text, confidence)] entries.
    for page in result or []:
        for line in page or []:
            bbox, (text, confidence) = line
            blocks.append(
                {
                    "text": text,
                    "bbox": [[float(x), float(y)] for x, y in bbox],
                    "confidence": float(confidence),
                }
            )

    return JSONResponse({"blocks": blocks})
