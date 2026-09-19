"""Offline photograph inference. Stdout is a single JSON object; no network access needed."""
import argparse
import base64
import contextlib
import hashlib
import json
import math
from pathlib import Path
import re
import statistics
import sys
import time
import warnings

ROOT = Path(__file__).resolve().parent
MODELS = ROOT / "models"
DETECTOR = "yolo-v9-s-608-license-plate-end2end"
OCR = "cct-s-v2-global-model"
MAX_PIXELS = 20_000_000


def model_paths():
    manifest = json.loads((MODELS / "manifest.json").read_text(encoding="utf-8"))
    paths = {}
    for key in ("detector", "ocr", "config"):
        item = manifest["files"][key]
        path = (MODELS / item["name"]).resolve()
        if path.parent != MODELS.resolve() or not path.is_file():
            raise ValueError("Missing local model")
        if hashlib.sha256(path.read_bytes()).hexdigest() != item["sha256"]:
            raise ValueError("Model integrity check failed")
        paths[key] = path
    if manifest["detector"] != DETECTOR or manifest["ocr"] != OCR:
        raise ValueError("Unexpected model profile")
    return paths


def load_models():
    import onnxruntime as ort
    from open_image_models import create_detector
    from fast_alpr.default_ocr import DefaultOCR

    paths = model_paths()
    options = ort.SessionOptions()
    options.intra_op_num_threads = 2
    options.inter_op_num_threads = 1
    options.log_severity_level = 3
    detector = create_detector(paths["detector"], backend="yolo_v9", class_labels=("License Plate",),
                               conf_thresh=0.4, providers=["CPUExecutionProvider"], sess_options=options)
    ocr = DefaultOCR(model_path=paths["ocr"], config_path=paths["config"], device="cpu",
                     providers=["CPUExecutionProvider"], sess_options=options)
    return detector, ocr


def read_image(path):
    import cv2
    import numpy as np
    from PIL import Image, ImageOps

    Image.MAX_IMAGE_PIXELS = MAX_PIXELS
    with warnings.catch_warnings():
        warnings.simplefilter("error", Image.DecompressionBombWarning)
        with Image.open(path) as source:
            if source.format not in ("JPEG", "PNG", "WEBP") or source.width * source.height > MAX_PIXELS:
                raise ValueError("Unsupported image")
            if getattr(source, "n_frames", 1) != 1:
                raise ValueError("Animated images are not supported")
            image = ImageOps.exif_transpose(source).convert("RGB")
            image.thumbnail((2000, 2000))
            return cv2.cvtColor(np.asarray(image), cv2.COLOR_RGB2BGR)


def jpeg(image, max_side):
    import cv2

    height, width = image.shape[:2]
    scale = min(1.0, max_side / max(width, height))
    if scale < 1:
        image = cv2.resize(image, (max(1, round(width * scale)), max(1, round(height * scale))))
    success, buffer = cv2.imencode(".jpg", image, [cv2.IMWRITE_JPEG_QUALITY, 82])
    if not success:
        raise ValueError("Cannot encode preview")
    return base64.b64encode(buffer).decode("ascii")


def score(value):
    value = float(value)
    return round(min(1.0, max(0.0, value)), 4) if math.isfinite(value) else 0.0


def recognize(path, detector, ocr):
    import cv2

    image = read_image(path)
    annotated = image.copy()
    height, width = image.shape[:2]
    detections = sorted(detector.predict(image), key=lambda d: d.confidence, reverse=True)
    candidates = []
    for detection in detections[:10]:
        box = detection.bounding_box
        x1, y1 = max(0, int(box.x1)), max(0, int(box.y1))
        x2, y2 = min(width, int(box.x2)), min(height, int(box.y2))
        if x2 <= x1 or y2 <= y1:
            continue
        crop = image[y1:y2, x1:x2]
        prediction = ocr.predict(crop)
        text, confidence = "", None
        if prediction is not None:
            # Do not guess O/0 or I/1 substitutions; the operator must review them.
            text = re.sub(r"[^A-Z0-9]", "", prediction.text.upper())[:10]
            probs = prediction.confidence
            confidence = score(statistics.mean(probs)) if isinstance(probs, list) and probs else score(probs) if not isinstance(probs, list) else None
        candidates.append({"text": text, "detection_confidence": score(detection.confidence),
                           "ocr_confidence": confidence, "crop": jpeg(crop, 420),
                           "box": [x1, y1, x2, y2]})
        cv2.rectangle(annotated, (x1, y1), (x2, y2), (0, 165, 255), 3)
        cv2.putText(annotated, str(len(candidates)), (x1, max(25, y1 - 8)),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 165, 255), 2)
    return {"version": 1, "detector": DETECTOR, "ocr": OCR, "candidates": candidates,
            "truncated": len(detections) > 10, "preview": jpeg(annotated, 1000)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", type=Path)
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    if not args.check and args.image is None:
        parser.error("--image is required unless --check is used")
    start = time.perf_counter()
    try:
        with contextlib.redirect_stdout(sys.stderr):
            detector, ocr = load_models()
            result = {"ready": True, "detector": DETECTOR, "ocr": OCR} if args.check else recognize(args.image, detector, ocr)
        result["elapsed_ms"] = round((time.perf_counter() - start) * 1000)
        print(json.dumps(result, ensure_ascii=True, allow_nan=False))
    except Exception as error:
        # No file paths or plate text in logs/errors exposed to the web application.
        print(json.dumps({"error": type(error).__name__}), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
