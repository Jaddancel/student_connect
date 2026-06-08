FROM python:3.10-slim

WORKDIR /app

# libgl1 + libglib2.0-0: required by OpenCV
# libgomp1: required by PaddlePaddle (OpenMP runtime)
# curl: used by the healthcheck
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libgl1 libglib2.0-0 libgomp1 curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY main.py .

EXPOSE 5000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
