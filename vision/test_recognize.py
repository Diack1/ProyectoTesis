"""Tests for geometry, bad inputs and empty OCR; no model downloads."""
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace as Obj
from unittest.mock import Mock

from PIL import Image
from recognize import read_image, recognize


class RecognitionTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.path = Path(self.directory.name) / "sample.jpg"
        Image.new("RGB", (300, 200), "white").save(self.path)

    def tearDown(self):
        self.directory.cleanup()

    def test_no_detection_does_not_invent_text(self):
        detector, ocr = Mock(), Mock()
        detector.predict.return_value = []
        result = recognize(self.path, detector, ocr)
        self.assertEqual(result["candidates"], [])
        ocr.predict.assert_not_called()

    def test_clips_boxes_preserves_missing_ocr_and_numbers_only_valid_boxes(self):
        detector, ocr = Mock(), Mock()
        detector.predict.return_value = [
            Obj(confidence=0.95, bounding_box=Obj(x1=10, y1=10, x2=5, y2=20)),
            Obj(confidence=0.8, bounding_box=Obj(x1=-10, y1=-5, x2=400, y2=250)),
        ]
        ocr.predict.return_value = None
        result = recognize(self.path, detector, ocr)
        self.assertEqual(len(result["candidates"]), 1)
        self.assertEqual(result["candidates"][0]["box"], [0, 0, 300, 200])
        self.assertEqual(result["candidates"][0]["text"], "")
        self.assertIsNone(result["candidates"][0]["ocr_confidence"])

    def test_limits_ocr_work_and_does_not_substitute_ambiguous_characters(self):
        detector, ocr = Mock(), Mock()
        detector.predict.return_value = [Obj(confidence=.8, bounding_box=Obj(x1=0, y1=0, x2=100, y2=50))] * 12
        ocr.predict.return_value = Obj(text="AOI-012_", confidence=[.8, .9])
        result = recognize(self.path, detector, ocr)
        self.assertTrue(result["truncated"])
        self.assertEqual(ocr.predict.call_count, 10)
        self.assertEqual(result["candidates"][0]["text"], "AOI012")

    def test_exif_orientation_and_corrupt_input(self):
        exif = Image.Exif()
        exif[274] = 6
        Image.new("RGB", (300, 200)).save(self.path, exif=exif)
        self.assertEqual(read_image(self.path).shape[:2], (300, 200))
        self.path.write_bytes(b"not an image")
        with self.assertRaises(Exception):
            read_image(self.path)


if __name__ == "__main__":
    unittest.main()
