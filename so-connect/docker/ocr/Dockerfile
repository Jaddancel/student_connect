# PaddleOCR sidecar for the ID-template scanner.
# NOTE: first build downloads the PaddleOCR detection/recognition models
# (hundreds of MB) — expect a slow initial build/run.
FROM python:3.11-slim

# PaddleOCR/OpenCV runtime libs.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
        libgomp1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 5000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "5000"]
