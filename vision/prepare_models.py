"""Explicit installation only. Recognition never downloads weights on a web request."""
import hashlib
import json
from pathlib import Path
from datetime import datetime, timezone

from open_image_models.detection.core.hub import download_model as download_detector, DETECTION_MODELS
from fast_plate_ocr.inference.hub import download_model as download_ocr, AVAILABLE_ONNX_MODELS
from recognize import DETECTOR, OCR, MODELS, load_models


def main():
    MODELS.mkdir(exist_ok=True)
    detector = download_detector(DETECTOR, save_directory=MODELS)
    ocr, config = download_ocr(OCR, save_directory=MODELS)
    urls = [DETECTION_MODELS[DETECTOR].url, *AVAILABLE_ONNX_MODELS[OCR]]
    files = {}
    for key, path, url in zip(("detector", "ocr", "config"), (detector, ocr, config), urls):
        files[key] = {"name": path.name, "sha256": hashlib.sha256(path.read_bytes()).hexdigest(), "source": url}
    expected = json.loads((Path(__file__).parent / "model-profile.json").read_text(encoding="utf-8"))
    if expected["detector"] != DETECTOR or expected["ocr"] != OCR or files != expected["files"]:
        raise ValueError("Downloaded models differ from the recorded profile; review before updating it.")
    manifest = {"detector": DETECTOR, "ocr": OCR, "prepared_at": datetime.now(timezone.utc).isoformat(), "files": files}
    # Record hashes for local corruption detection and experiment reproducibility.
    target = MODELS / "manifest.json"
    target.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    try:
        load_models()
    except Exception:
        target.unlink(missing_ok=True)
        raise
    print("Modelos preparados y cargados correctamente en CPU.")


if __name__ == "__main__":
    main()
