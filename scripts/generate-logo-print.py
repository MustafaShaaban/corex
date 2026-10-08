#!/usr/bin/env python3
"""Render the CoreX lockup as a raster image for printed documents.

A PDF export carries CoreX's signature (spec 103, FR-019). The PDF library draws the mark's
rectangles exactly and mangles the wordmark's glyph outlines, so a document cannot take the SVG.
This renders the approved light-ground lockup, `corex-contrast.svg`, to a PNG with a transparent
ground. Nothing is redrawn: the SVG is the source, `currentColor` is given the ink a page is
printed in, and the result is recorded in `logo-manifest.json` under `print`.

Needs PyMuPDF (`pip install pymupdf`). Run from anywhere:

    python scripts/generate-logo-print.py
"""

from __future__ import annotations

import hashlib
import json
import os

import fitz

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BRAND = os.path.join(ROOT, "plugins", "corex-config", "assets", "brand")
SOURCE = "corex-contrast.svg"
TARGET = "corex-lockup-print.png"
MANIFEST = os.path.join(BRAND, "logo-manifest.json")

INK = "#14151a"  # the text colour of a printed page
SCALE = 8        # 170.02 x 48 units -> 1360 x 384 px: crisp at 6mm tall on a 600dpi printer


def main() -> None:
    with open(os.path.join(BRAND, SOURCE), encoding="utf-8") as handle:
        svg = handle.read().replace("currentColor", INK)

    page = fitz.open(stream=svg.encode("utf-8"), filetype="svg")[0]
    pixmap = page.get_pixmap(matrix=fitz.Matrix(SCALE, SCALE), alpha=True)
    path = os.path.join(BRAND, TARGET)
    pixmap.save(path)

    with open(path, "rb") as handle:
        digest = hashlib.sha256(handle.read()).hexdigest()
    with open(MANIFEST, encoding="utf-8") as handle:
        manifest = json.load(handle)
    manifest["print"] = {
        "path": f"plugins/corex-config/assets/brand/{TARGET}",
        "filename": TARGET,
        "rendered_from": f"plugins/corex-config/assets/brand/{SOURCE}",
        "ink": INK,
        "pixels": [pixmap.width, pixmap.height],
        "sha256": digest,
        "generator": "scripts/generate-logo-print.py",
        "description": "The light-ground lockup as a raster image, for documents CoreX writes as PDF. "
        "The PDF library cannot draw the wordmark's outlines from the SVG.",
    }
    with open(MANIFEST, "w", encoding="utf-8", newline="\n") as handle:
        json.dump(manifest, handle, indent=2, ensure_ascii=False)
        handle.write("\n")

    print(f"wrote {os.path.relpath(path, ROOT)} {pixmap.width}x{pixmap.height} {digest}")


if __name__ == "__main__":
    main()
