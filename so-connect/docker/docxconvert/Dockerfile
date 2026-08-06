# LibreOffice conversion sidecar (docx→pdf, html→docx, …).
#
# `unoserver` keeps one LibreOffice process warm: a cold `soffice --headless`
# start costs several seconds and most of a CPU core on every call. A thin
# FastAPI wrapper fronts it so the Laravel app speaks plain HTTP rather than
# unoserver's XML-RPC.
FROM debian:bookworm-slim

# LibreOffice plus the UNO Python bindings unoserver drives it through.
#
# The font packages matter: Carlito and Caladea are metric-compatible stand-ins
# for Calibri and Cambria, and Liberation for Arial/Times/Courier. Without them
# LibreOffice substitutes something of a different width and Word layouts
# reflow — line breaks and page counts drift away from the authored template.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libreoffice-writer \
        libreoffice-calc \
        python3 \
        python3-pip \
        python3-uno \
        fonts-crosextra-carlito \
        fonts-crosextra-caladea \
        fonts-liberation \
        fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir --break-system-packages -r requirements.txt

COPY app.py .

# LibreOffice refuses to start without a writable HOME for its user profile.
ENV HOME=/root

EXPOSE 2003

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "2003"]
