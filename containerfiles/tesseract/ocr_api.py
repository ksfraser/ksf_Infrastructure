import logging
import tempfile
from pathlib import Path

import pytesseract
from fastapi import FastAPI, HTTPException, Request, UploadFile
from fastapi.responses import HTMLResponse, JSONResponse, PlainTextResponse

app = FastAPI(title="KSF OCR Service", version="1.1.0")
log = logging.getLogger("uvicorn")

HTML_FORM = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KSF OCR Service</title>
<style>
body{font-family:sans-serif;max-width:800px;margin:2rem auto;padding:0 1rem}
h1{color:#333}
form{margin:1rem 0}
input[type=file]{display:block;margin:1rem 0}
button{padding:.5rem 1rem}
pre{background:#f5f5f5;padding:1rem;border-radius:4px;overflow-x:auto;white-space:pre-wrap}
#result{margin-top:1rem}
a#download{display:none;margin-top:.5rem}
footer{margin-top:3rem;font-size:.85rem;color:#666}
</style>
</head>
<body>
<h1>KSF OCR Service</h1>
<form id="ocrForm" enctype="multipart/form-data">
<input type="file" name="file" accept="image/*" required>
<button type="submit">OCR Image</button>
</form>
<div id="result">
<pre id="textOutput"></pre>
<a id="download" href="#">Download as .txt</a>
</div>
<script>
document.getElementById('ocrForm').onsubmit=async function(e){
e.preventDefault();const f=new FormData(this);
const r=await fetch('/ocr',{method:'POST',body:f});
const d=await r.json();
document.getElementById('textOutput').textContent=d.text||'Error: '+d.detail;
const a=document.getElementById('download');
a.href='data:text/plain,'+encodeURIComponent(d.text);a.download=(d.filename||'ocr')+'.txt';a.style.display='block';
};
</script>
<footer>KSF OCR Service v1.1.0</footer>
</body>
</html>"""


@app.get("/", response_class=HTMLResponse)
async def upload_form():
    return HTML_FORM


@app.get("/health")
async def health():
    return {"status": "ok"}


async def _ocr(file: UploadFile) -> str:
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(400, "Only image files are supported")

    ext = Path(file.filename or "image.png").suffix or ".png"
    with tempfile.NamedTemporaryFile(suffix=ext, delete=True) as tmp:
        content = await file.read()
        tmp.write(content)
        tmp.flush()
        try:
            return pytesseract.image_to_string(tmp.name).strip()
        except Exception as exc:
            log.error("OCR failed: %s", exc)
            raise HTTPException(500, f"OCR processing error: {exc}")


@app.post("/ocr")
async def ocr_json(file: UploadFile):
    text = await _ocr(file)
    return JSONResponse({"text": text, "filename": file.filename})


@app.post("/ocr/download")
async def ocr_download(file: UploadFile):
    text = await _ocr(file)
    name = Path(file.filename or "ocr").stem + ".txt"
    return PlainTextResponse(
        content=text,
        headers={"Content-Disposition": f'attachment; filename="{name}"'},
    )
