# Docker Setup Context

Last verified: 2026-07-13

This document is a compact reference for chatbots and developers that need to understand the current Docker layout in this repository without reading every file.

## What Runs In Docker

The project uses a Laravel Sail-style Compose setup defined in [compose.yaml](../compose.yaml).

Services:

- `laravel.test`: the main Laravel app container, built from [docker/8.5/Dockerfile](../docker/8.5/Dockerfile)
- `mysql`: MySQL 8.4 for the application database
- `adminer`: database UI on port 8080
- `mailhog`: local mail catcher for development
- `ocr`: a FastAPI + PaddleOCR sidecar built from [docker/ocr/Dockerfile](../docker/ocr/Dockerfile)

## Main App Container

The app container is based on Ubuntu 24.04 and installs PHP 8.5, Node.js 24, Composer, npm, pnpm, bun, Playwright browser dependencies, and common system packages used by the app.

The container starts Laravel with `php artisan serve --host=0.0.0.0 --port=80` via Supervisor.

Important behavior:

- The source tree is mounted into the container at `/var/www/html`
- The container is on the `sail` bridge network
- `host.docker.internal` is mapped for host access from inside the container
- Xdebug is supported through `SAIL_XDEBUG_MODE` and `SAIL_XDEBUG_CONFIG`

## Database Container

The database service is:

- Image: `mysql:8.4`
- Internal port: `3306`
- Persistent volume: `sail-mysql`
- Init script: `./vendor/laravel/sail/database/mysql/create-testing-database.sh`

Environment variables used by the database service come from the app `.env` values:

- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

The compose file also exposes the database port to the host through `FORWARD_DB_PORT` if set.

## OCR Sidecar

The OCR service is an inference-only FastAPI app in [docker/ocr/app.py](../docker/ocr/app.py).

It exposes:

- `POST /scan`
- `POST /signature-identify`
- `GET /health`

Key details:

- Service name must stay `ocr`
- The Laravel app talks to it at `http://ocr:5000` inside the Compose network
- The public port defaults to `5000`
- First build is slow because PaddleOCR models are downloaded during image startup/import

The OCR stack is intentionally not a training pipeline. It is an inference sidecar for zone-based OCR and signature matching.

## App-to-OCR Contract

Laravel sends OCR requests through [app/Services/OcrClient.php](../app/Services/OcrClient.php).

Relevant config:

- `OCR_SERVICE_URL=http://ocr:5000`
- `OCR_TIMEOUT=60`

When the app is run outside Compose, the OCR URL should point to the host port instead, for example `http://localhost:5000`.

## Ports And Defaults

- App HTTP: `APP_PORT` defaults to `80`
- Vite dev server: `VITE_PORT` defaults to `5173`
- MySQL: `FORWARD_DB_PORT` defaults to `3306`
- Adminer: `8080`
- MailHog SMTP: `FORWARD_MAILHOG_PORT` defaults to `1025`
- MailHog web UI: `FORWARD_MAILHOG_DASHBOARD_PORT` defaults to `8025`
- OCR service: `OCR_PORT` defaults to `5000`

## Common Commands

Typical Sail commands from the repository root:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test
```

## Practical Notes For Chatbots

- The project is Laravel 12 with Sail-managed containers, not a custom Docker orchestration stack.
- The Docker setup is intentionally simple: one app container, one MySQL container, and small support services.
- The OCR sidecar is separate from the Laravel app and should be treated as an inference API, not something to retrain in-place.
- If a prompt mentions Docker issues, the first files to inspect are [compose.yaml](../compose.yaml), [docker/8.5/Dockerfile](../docker/8.5/Dockerfile), [docker/ocr/Dockerfile](../docker/ocr/Dockerfile), and [app/Services/OcrClient.php](../app/Services/OcrClient.php).
