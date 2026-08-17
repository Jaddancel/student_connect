# LibreOffice conversion sidecar (docx→pdf, html→docx, …).
#
# `unoserver` keeps one LibreOffice process warm: a cold `soffice --headless`
# start costs several seconds and most of a CPU core on every call. A thin
# FastAPI wrapper fronts it so the Laravel app speaks plain HTTP rather than
# unoserver's XML-RPC.
FROM debian:bookworm-slim

# LibreOffice plus the UNO Python bindings unoserver drives it through.
#
# The font packages matter: without the same fonts the template was authored
# with, LibreOffice substitutes something of a different width and Word layouts
# reflow — line breaks and page counts drift away from the authored template.
#
# We install the *real* Microsoft Core fonts (Arial, Times New Roman, Courier,
# …) via ttf-mscorefonts-installer so the generated PDF matches what the admin
# sees in OnlyOffice/Word — not just a metric-compatible look-alike. The
# Carlito/Caladea/Liberation packs stay as fallbacks for fonts the MS set does
# not cover (e.g. Calibri → Carlito, Cambria → Caladea) and as a safety net if
# the mscorefonts download is unavailable at build time.
#
# ttf-mscorefonts-installer lives in Debian's `contrib` component, needs its
# EULA pre-accepted for a non-interactive build, and downloads the fonts from
# SourceForge during install (cabextract unpacks the .exe cabinets).
RUN echo "deb http://deb.debian.org/debian bookworm contrib" > /etc/apt/sources.list.d/contrib.list \
    && echo "ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true" | debconf-set-selections \
    && apt-get update && apt-get install -y --no-install-recommends \
        libreoffice-writer \
        libreoffice-calc \
        python3 \
        python3-pip \
        python3-uno \
        cabextract \
        ttf-mscorefonts-installer \
        fonts-crosextra-carlito \
        fonts-crosextra-caladea \
        fonts-liberation \
        fonts-dejavu-core \
    && fc-cache -f \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir --break-system-packages -r requirements.txt

COPY app.py .

# LibreOffice refuses to start without a writable HOME for its user profile.
ENV HOME=/root

EXPOSE 2003

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "2003"]
