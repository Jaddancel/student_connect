"""
Document conversion sidecar for Student Connect.

Wraps ``unoserver`` — which keeps a LibreOffice instance warm — behind a small
HTTP API, so the Laravel app can convert documents without shipping an XML-RPC
client. Consumed by ``App\\Services\\DocxConverter``, which falls back to the
app container's own ``soffice`` binary whenever this service is unreachable.

    GET  /health   -> {"status": "ok", "listener": true}
    POST /convert  -> converted bytes (multipart: file=<document>, to=<format>)
"""

from __future__ import annotations

import logging
import os
import re
import socket
import subprocess
import tempfile
import threading
import time
from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import JSONResponse, Response

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("docxconvert")

# unoserver's XML-RPC listener and the UNO bridge behind it both stay on
# loopback — only the FastAPI port is published. The XML-RPC port is moved off
# unoserver's own 2003 default because FastAPI occupies that one.
UNOSERVER_HOST = "127.0.0.1"
UNOSERVER_PORT = int(os.environ.get("UNOSERVER_PORT", "2004"))
UNO_BRIDGE_PORT = int(os.environ.get("UNO_BRIDGE_PORT", "2002"))

STARTUP_TIMEOUT = int(os.environ.get("UNOSERVER_STARTUP_TIMEOUT", "120"))
CONVERT_TIMEOUT = int(os.environ.get("CONVERT_TIMEOUT", "120"))

# Explicit allow-list: keeps a caller from steering LibreOffice into an
# unexpected export filter via the `to` field.
ALLOWED_FORMATS = {
    "pdf", "docx", "doc", "odt", "rtf", "txt", "html",
    "xlsx", "ods", "csv", "pptx", "odp", "png",
}

_lock = threading.Lock()
_process: subprocess.Popen | None = None


def _listening() -> bool:
    """Is unoserver accepting XML-RPC connections?"""
    with socket.socket() as probe:
        probe.settimeout(1)
        return probe.connect_ex((UNOSERVER_HOST, UNOSERVER_PORT)) == 0


def _spawn() -> subprocess.Popen:
    log.info("starting unoserver on %s:%s", UNOSERVER_HOST, UNOSERVER_PORT)

    return subprocess.Popen([
        "unoserver",
        "--interface", UNOSERVER_HOST,
        "--port", str(UNOSERVER_PORT),
        "--uno-port", str(UNO_BRIDGE_PORT),
    ])


def _ensure_listener(timeout: int = STARTUP_TIMEOUT) -> bool:
    """Start unoserver if it is down, then wait until it accepts connections."""
    global _process

    with _lock:
        if _process is not None and _process.poll() is not None:
            log.warning("unoserver exited with %s; restarting", _process.returncode)
            _process = None

        if _process is None:
            _process = _spawn()

    deadline = time.monotonic() + timeout

    while time.monotonic() < deadline:
        if _listening():
            return True

        # Died during startup — report failure and let the next call respawn.
        if _process is not None and _process.poll() is not None:
            return False

        time.sleep(0.5)

    log.error("unoserver did not come up within %ss", timeout)

    return False


@asynccontextmanager
async def lifespan(_: FastAPI):
    # Warm up at boot so the first real conversion isn't the request that pays
    # for LibreOffice's start-up.
    threading.Thread(target=_ensure_listener, daemon=True).start()

    yield

    with _lock:
        if _process is not None:
            _process.terminate()


app = FastAPI(title="Student Connect docxconvert", lifespan=lifespan)


@app.get("/health")
def health() -> JSONResponse:
    # Always 200 while the HTTP wrapper is up; `listener` reports whether
    # LibreOffice itself is warm yet, since /convert can still start it.
    return JSONResponse({"status": "ok", "listener": _listening()})


@app.post("/convert")
def convert(file: UploadFile = File(...), to: str = Form("pdf")) -> Response:
    target = to.lower().strip().lstrip(".")

    if target not in ALLOWED_FORMATS:
        raise HTTPException(status_code=400, detail=f"Unsupported target format: {to}")

    payload = file.file.read()

    if not payload:
        raise HTTPException(status_code=400, detail="Empty upload.")

    if not _ensure_listener():
        raise HTTPException(status_code=503, detail="LibreOffice listener unavailable.")

    with tempfile.TemporaryDirectory(prefix="convert-") as workdir:
        # Only the extension matters to LibreOffice, so the caller's filename is
        # reduced to a sanitized suffix rather than trusted as a path.
        suffix = Path(file.filename or "").suffix
        suffix = suffix if re.fullmatch(r"\.[A-Za-z0-9]{1,8}", suffix) else ".bin"

        source = Path(workdir) / f"input{suffix}"
        destination = Path(workdir) / f"output.{target}"
        source.write_bytes(payload)

        try:
            result = subprocess.run(
                [
                    "unoconvert",
                    "--host", UNOSERVER_HOST,
                    "--port", str(UNOSERVER_PORT),
                    "--host-location", "local",
                    "--convert-to", target,
                    str(source),
                    str(destination),
                ],
                capture_output=True,
                timeout=CONVERT_TIMEOUT,
            )
        except subprocess.TimeoutExpired:
            log.error("conversion to %s timed out after %ss", target, CONVERT_TIMEOUT)
            raise HTTPException(status_code=504, detail="Conversion timed out.")

        if result.returncode != 0 or not destination.is_file():
            detail = result.stderr.decode("utf-8", "replace").strip() or "conversion failed"
            log.error("unoconvert exited %s: %s", result.returncode, detail)
            raise HTTPException(status_code=500, detail=detail[:500])

        return Response(
            content=destination.read_bytes(),
            media_type="application/octet-stream",
            headers={"Content-Disposition": f'attachment; filename="output.{target}"'},
        )
