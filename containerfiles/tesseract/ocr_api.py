import logging
import tempfile
from pathlib import Path

import pytesseract
from fastapi import FastAPI, HTTPException, UploadFile
from fastapi.responses import JSONResponse

app = FastAPI(title="KSF OCR Service", version="1.0.0")
log = logging.getLogger("uvicorn")


@app.get("/health")
async def health():
    return {"status": "ok"}


@app.post("/ocr")
async def ocr(file: UploadFile):
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(400, "Only image files are supported")

    ext = Path(file.filename or "image.png").suffix or ".png"
    with tempfile.NamedTemporaryFile(suffix=ext, delete=True) as tmp:
        content = await file.read()
        tmp.write(content)
        tmp.flush()

        try:
            text = pytesseract.image_to_string(tmp.name)
        except Exception as exc:
            log.error("OCR failed: %s", exc)
            raise HTTPException(500, f"OCR processing error: {exc}")

    return JSONResponse({"text": text.strip(), "filename": file.filename})
