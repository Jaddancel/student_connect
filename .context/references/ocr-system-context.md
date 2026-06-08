# Self-Hosted OCR System — Architecture & Implementation Context

> **Purpose:** This document serves as implementation memory for building a self-hosted OCR microservice integrated into a Laravel Sail project. It covers architecture decisions, hardware constraints, setup instructions, and code scaffolds.

---

## Project Overview

The goal is a two-pipeline system:

1. **Form Digitization** — Upload a `.docx` form → AI infers field types → produce a structured e-form schema (with manual review step)
2. **Handwritten Form Ingestion** — Upload a scan of a filled form → OCR extracts field values → maps them to the e-form schema → manual review before saving

This document focuses on **Pipeline 2: the self-hosted OCR approach (Approach C)**.

---

## Hardware: ASUS TUF Gaming A15 (2024) — Dedicated OCR Machine

| Component | Spec |
|---|---|
| CPU | AMD Ryzen 7/9 8000 series (8845HS / 8945HS) |
| GPU | NVIDIA RTX 4060 Laptop, **8GB VRAM** |
| RAM | Up to 32GB DDR5-5600MHz |
| OS | Windows 11 |

The RTX 4060 with 8GB VRAM is the key constraint. All engine choices below are evaluated against this.

---

## OCR Engine Options

### Option A: Tesseract (Ruled Out for Handwriting)
- ~200–500MB RAM, CPU only
- Good for printed text, poor on handwriting (~60–75% accuracy)
- Use only for structural/printed layout detection, not handwritten values

### Option B: PaddleOCR ✅ Recommended Starting Point
- ~3–4GB VRAM on RTX 4060 (comfortable fit, ~4GB headroom)
- ~0.5–1.5 seconds per page with CUDA
- CUDA support is native and well-documented
- Good handwriting accuracy (~85–92% on clean handwriting)
- Well-maintained Docker images available
- Supports multilingual text

### Option C: Surya ✅ Best Accuracy (Upgrade Path)
- ~4–5GB VRAM on RTX 4060 (fits with some headroom)
- ~2–4 seconds per page with CUDA
- State-of-the-art handwriting recognition (~90–95%)
- Newer project, more complex to Dockerize
- **Recommended upgrade if PaddleOCR accuracy is insufficient**

### Option D: Ollama + Vision LLM (Schema-Aware Alternative)
- Models like MiniCPM-V 2.6 (~8B params) fit in 8GB VRAM
- ~5–15 seconds per page
- Schema-aware: can be prompted to return structured JSON matching your form schema directly
- Closest to the Claude Vision API approach but fully local/offline
- Requires RTX 4060 or better (8GB VRAM)

### Decision Matrix

| GPU Variant | Recommended Engine |
|---|---|
| RTX 4070 8GB | Surya or Ollama Vision |
| **RTX 4060 8GB** | **PaddleOCR (start) → Surya (upgrade)** |
| RTX 4050 6GB | PaddleOCR only (safe VRAM fit) |

---

## Architecture

```
┌─────────────────────────┐        Tailscale VPN
│   Dev Machine           │◄──────────────────────►┌──────────────────────┐
│   (Laravel Sail)        │   http://100.x.x.x:5000 │   TUF A15 2024       │
│                         │                          │                      │
│  Laravel Job dispatched │                          │  Docker container:   │
│  → calls OCR service ──►│─────────────────────────►│  FastAPI + PaddleOCR │
│                         │                          │  CUDA-accelerated    │
│  Maps result to schema  │◄─────────────────────────│  Returns JSON blocks │
│  → pending_review       │                          └──────────────────────┘
└─────────────────────────┘
```

### Key Architectural Decisions
- OCR runs as a **sidecar service**, not inside the Laravel container
- Laravel communicates with the OCR service via **HTTP (FastAPI)**
- OCR is dispatched as a **Laravel Queue Job** (async) — never a synchronous request
- The TUF A15 is exposed to the dev machine via **Tailscale** (stable `100.x.x.x` IP)
- Swapping OCR engines (PaddleOCR → Surya → Ollama) requires **zero Laravel code changes**

---

## Setup: TUF A15 (Windows + WSL2 + Docker Desktop)

### Prerequisites
```
- Docker Desktop (WSL2 backend enabled)
- NVIDIA Driver ≥ 527.41
- Tailscale for Windows (install from tailscale.com)
```

### NVIDIA Container Toolkit in WSL2
```bash
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey \
  | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg

curl -s -L https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list \
  | sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

sudo apt-get update && sudo apt-get install -y nvidia-container-toolkit
sudo nvidia-ctk runtime configure --runtime=docker
```

Verify GPU is visible to Docker:
```bash
docker run --rm --gpus all nvidia/cuda:11.7-base-ubuntu20.04 nvidia-smi
```

---

## OCR Service Code (FastAPI + PaddleOCR)

### Directory Structure
```
ocr-service/
├── app/
│   └── main.py
├── Dockerfile
└── docker-compose.yml
```

### `app/main.py`
```python
from fastapi import FastAPI, UploadFile, File
from paddleocr import PaddleOCR
import io
from PIL import Image
import numpy as np

app = FastAPI()
ocr = PaddleOCR(use_angle_cls=True, lang='en', use_gpu=True)

@app.post("/extract")
async def extract(file: UploadFile = File(...)):
    img = Image.open(io.BytesIO(await file.read())).convert("RGB")
    result = ocr.ocr(np.array(img), cls=True)

    blocks = []
    for line in result[0]:
        bbox, (text, confidence) = line
        blocks.append({
            "text": text,
            "confidence": round(confidence, 3),
            "bbox": bbox  # pixel position — used for field mapping
        })

    return {"blocks": blocks, "page_count": 1}

@app.get("/health")
def health():
    return {"status": "ok"}
```

### `Dockerfile`
```dockerfile
FROM paddlepaddle/paddle:2.6.1-gpu-cuda11.7-cudnn8.4-trt8.4

WORKDIR /app
RUN pip install paddleocr fastapi uvicorn python-multipart Pillow

COPY app/ .

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
```

### `docker-compose.yml` (on the TUF)
```yaml
services:
  ocr:
    build: .
    ports:
      - "5000:5000"
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]
    restart: unless-stopped
```

---

## Laravel Integration

### Environment Config
```env
# .env (dev machine)
OCR_SERVICE_URL=http://100.x.x.x:5000   # TUF's Tailscale IP
```

```php
// config/services.php
'ocr' => [
    'url' => env('OCR_SERVICE_URL', 'http://localhost:5000'),
],
```

### Queue Job: `app/Jobs/ProcessFormScan.php`
```php
<?php

namespace App\Jobs;

use App\Models\FormEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessFormScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private FormEntry $entry,
        private string $imagePath
    ) {}

    public function handle(): void
    {
        $response = Http::timeout(60)
            ->attach('file', Storage::get($this->imagePath), 'scan.jpg')
            ->post(config('services.ocr.url') . '/extract');

        if ($response->failed()) {
            $this->fail('OCR service unreachable or returned error');
            return;
        }

        $blocks = $response->json('blocks');

        // Map OCR text blocks to your e-form schema fields
        $mapped = $this->mapBlocksToSchema($blocks, $this->entry->form->schema);

        $this->entry->update([
            'extracted_data' => $mapped,
            'status' => 'pending_review',  // triggers manual review UI
        ]);
    }

    private function mapBlocksToSchema(array $blocks, array $schema): array
    {
        // Strategy: match each schema field label to the nearest
        // OCR block by spatial proximity (bbox coordinates).
        // The value block is typically to the right of or below the label.
        // TODO: implement bbox-based spatial matching
        return [];
    }
}
```

### Dispatching the Job
```php
// In your controller, after storing the uploaded scan:
ProcessFormScan::dispatch($formEntry, $imagePath);
```

---

## Field Mapping Strategy (Critical for Accuracy)

Raw OCR returns a flat list of text blocks with bounding box coordinates. To map values to the correct schema fields:

### The Approach
1. **During DOCX → e-form step:** Record the approximate layout position of each field label in the form (relative coordinates as percentages of page dimensions)
2. **During OCR:** Use bbox pixel positions to find the text block that appears **spatially adjacent** (to the right of, or directly below) a known field label

### Why This Matters
Without spatial matching, you're guessing which OCR text block is "Last Name" vs "First Name". With bbox matching, you anchor each value to its label position on the page — this is the single biggest accuracy booster for structured forms.

### Suggested Schema for Storing Field Positions
```json
{
  "fields": [
    {
      "id": "field_001",
      "label": "Last Name",
      "type": "text",
      "layout": {
        "x_pct": 0.05,
        "y_pct": 0.12,
        "width_pct": 0.3,
        "height_pct": 0.04
      }
    }
  ]
}
```

---

## Tailscale Setup (Both Machines)

1. Install Tailscale on the TUF (Windows) and dev machine
2. Sign into the same Tailscale account on both
3. Both machines appear in your Tailscale admin panel with stable `100.x.x.x` IPs
4. No port forwarding, firewall rules, or TLS certificates needed
5. The OCR service on port `5000` is reachable at `http://<tuf-tailscale-ip>:5000`

**For production/remote scenario:** Spin up a GPU cloud instance (Lambda Labs, Vast.ai), install Tailscale on it, point `OCR_SERVICE_URL` to its Tailscale IP — zero Laravel code changes.

---

## Adding to Sail's `docker-compose.yml` (Same Machine Scenario)

If the OCR service runs on the **same machine** as Sail (not the TUF), add it as a Sail sidecar:

```yaml
# docker-compose.yml
services:
  laravel.test:
    # ... existing config
    depends_on:
      - ocr

  ocr:
    build:
      context: ./docker/ocr
    ports:
      - "${OCR_PORT:-5000}:5000"
    networks:
      - sail
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]
```

When running as a Sail sidecar, use `http://ocr:5000` as the service URL (Docker internal DNS), not a Tailscale IP.

---

## Upgrade Path: Switching to Surya

When ready to upgrade from PaddleOCR to Surya, only the `Dockerfile` and `main.py` change. Laravel code is untouched.

```python
# main.py (Surya version)
from surya.ocr import run_ocr
from surya.model.detection.model import load_model as load_det_model
from surya.model.detection.processor import load_processor as load_det_processor
from surya.model.recognition.model import load_model as load_rec_model
from surya.model.recognition.processor import load_processor as load_rec_processor

# Load models once at startup (not per request)
det_processor, det_model = load_det_processor(), load_det_model()
rec_model, rec_processor = load_rec_model(), load_rec_processor()
```

---

## Summary of Decisions Made

| Decision | Choice | Reason |
|---|---|---|
| OCR Engine | PaddleOCR (start) | Best fit for RTX 4060 8GB, fast, reliable |
| Upgrade path | Surya | Better handwriting accuracy when needed |
| Serving | FastAPI | Lightweight, async, easy Docker deployment |
| GPU access | NVIDIA Container Toolkit in WSL2 | Mature CUDA support on Windows |
| Networking | Tailscale | Zero-config, stable IPs, no firewall fuss |
| Laravel integration | Queue Job (async) | Avoids timeout on slow inference |
| Field matching | Spatial bbox mapping | Highest accuracy for structured forms |
| Architecture | Sidecar microservice | Decoupled, engine-swappable, scalable |
