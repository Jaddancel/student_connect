# TUF A15 2024 — Dev + OCR Single Machine Setup

## Decision: Run Everything on the TUF
All services (Laravel Sail, MySQL, Adminer, Mailhog, OCR) run on the TUF A15. No separate machine needed for development.

## Hardware
| | |
|---|---|
| GPU | RTX 4060, 8GB VRAM |
| RAM | 16GB DDR5 (32GB if upgraded) |
| OS | Windows 11 + WSL2 |

## Memory Budget (16GB)
| Service | RAM |
|---|---|
| Windows + WSL2 | ~4–5GB |
| Sail + MySQL + Adminer + Mailhog | ~600MB–1GB |
| PaddleOCR (idle/inference spike) | ~300MB–2GB |
| Browser + misc | ~500MB |
| **Headroom** | **~7–9GB free** |

## VRAM Budget
- OCR inference: ~3–4GB of 8GB — comfortable
- Dev stack uses zero VRAM
- Model loads on first request, stays resident for fast subsequent calls

## Key Constraint: Heat
- Keep plugged in during dev — throttling only occurs on battery under dual CPU+GPU load
- TUF cooling (dual Arc Flow fans, 5 heatpipes) handles sustained mixed load fine

## docker-compose.yml — Add OCR as Sail Sidecar
```yaml
services:
    laravel.test:
        depends_on:
            - mysql
            - ocr
    # ... mysql, adminer, mailhog unchanged ...
    ocr:
        build:
            context: ./docker/ocr
        ports:
            - "5000:5000"
        networks:
            - sail
        deploy:
            resources:
                reservations:
                    devices:
                        - driver: nvidia
                          count: 1
                          capabilities: [gpu]
        restart: unless-stopped
```

## .env
```env
OCR_SERVICE_URL=http://ocr:5000   # Docker internal DNS — no IP, no Tailscale needed locally
```

## Tailscale
Not needed for local dev. Only relevant if OCR moves to a remote/cloud machine later — which is just a one-line `.env` change thanks to the decoupled microservice architecture.

## When to Split to a Separate Machine
- Active gaming on the TUF while app is in use (GPU contention)
- 24/7 production uptime without relying on the laptop being on
- Upgrading to Surya or Ollama vision models (higher persistent VRAM usage)
