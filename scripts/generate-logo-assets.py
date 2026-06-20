#!/usr/bin/env python3
"""Generate the production CoreX logo SVG package from the approved design system.

This script is a *faithful mechanical extraction*, not a redrawing. The symbol
geometry is the documented locked winner ("Core X": five rounded 12u modules on a
48x48 grid, 3u gutters, 2.5u corner radius, brass `#c9a25e` core). The wordmark
glyph outlines are extracted from the actual self-hosted, OFL-licensed Space Grotesk
variable font at the documented shipping weight (600) — no glyph is invented, traced,
or redrawn.

Source of truth:
  - design handoff: "Corex Logo System.dc.html" / README.md (Core X locked winner)
  - font: theme/assets/fonts/space-grotesk-latin-500-700.woff2 (wght 600 instance)

Outputs (raw; optimized afterwards by svgo): plugins/corex-config/assets/brand/*.svg
"""

from __future__ import annotations

import os

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FONT = os.path.join(ROOT, "theme", "assets", "fonts", "space-grotesk-latin-500-700.woff2")
OUT = os.path.join(ROOT, "plugins", "corex-config", "assets", "brand")

BRASS = "#c9a25e"        # primary brass core (dark-first)
BRASS_AA = "#ad8643"     # AA-darkened brass for light / high-contrast (README accent-on-light)
WEIGHT = 600
WORD = "Corex"


def wordmark_paths() -> tuple[str, str, float, float, float, float]:
    """Return (core_d, x_d, min_x, min_y, max_x, max_y) in y-down SVG units."""
    font = TTFont(FONT)
    instantiateVariableFont(font, {"wght": WEIGHT}, inplace=True)
    glyphs = font.getGlyphSet()
    cmap = font.getBestCmap()
    hmtx = font["hmtx"]
    letter_spacing = round(-0.035 * font["head"].unitsPerEm)  # -0.035em tracking

    core_pen = SVGPathPen(glyphs)   # "Core" — currentColor
    x_pen = SVGPathPen(glyphs)      # terminal "x" — brass
    bounds = BoundsPen(glyphs)

    x_cursor = 0.0
    for char in WORD:
        gname = cmap[ord(char)]
        target = x_pen if char == "x" else core_pen
        # Matrix flips the y-axis (font y-up -> SVG y-down) and offsets x by the cursor.
        matrix = (1, 0, 0, -1, x_cursor, 0)
        glyphs[gname].draw(TransformPen(target, matrix))
        glyphs[gname].draw(TransformPen(bounds, matrix))
        x_cursor += hmtx[gname][0] + letter_spacing

    min_x, min_y, max_x, max_y = bounds.bounds
    return core_pen.getCommands(), x_pen.getCommands(), min_x, min_y, max_x, max_y


def mark_rects(core_fill: str, corner_fill: str = "currentColor") -> str:
    coords = [(3, 3), (33, 3), (18, 18), (3, 33), (33, 33)]
    out = []
    for i, (x, y) in enumerate(coords):
        fill = core_fill if i == 2 else corner_fill
        out.append(
            f'<rect x="{x}" y="{y}" width="12" height="12" rx="2.5" fill="{fill}"/>'
        )
    return "".join(out)


def svg(view_box: str, title: str, body: str) -> str:
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{view_box}" fill="none" '
        f'role="img" aria-label="{title}"><title>{title}</title>{body}</svg>\n'
    )


def write(name: str, content: str) -> None:
    path = os.path.join(OUT, name)
    with open(path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(content)
    print("wrote", os.path.relpath(path, ROOT))


def main() -> None:
    os.makedirs(OUT, exist_ok=True)
    core_d, x_d, min_x, min_y, max_x, max_y = wordmark_paths()
    w = round(max_x - min_x, 2)
    h = round(max_y - min_y, 2)

    # The wordmark group normalises the ink box to the origin.
    def wordmark_group(core_fill: str, x_fill: str) -> str:
        return (
            f'<g transform="translate({-min_x:.2f},{-min_y:.2f})">'
            f'<path d="{core_d}" fill="{core_fill}"/>'
            f'<path d="{x_d}" fill="{x_fill}"/></g>'
        )

    # --- symbol: the bare mark, brass core (decorative icon) ---
    write("corex-symbol.svg", svg("0 0 48 48", "Corex", mark_rects(BRASS)))

    # --- wordmark: the type only, "Core" + brass "x" ---
    write(
        "corex-wordmark.svg",
        svg(f"0 0 {w} {h}", "Corex", wordmark_group("currentColor", BRASS)),
    )

    # --- lockup composition (mark | divider | wordmark), height 48 ---
    cap = 28.0                 # wordmark cap height inside the 48u lockup
    scale = cap / h
    word_w = round(w * scale, 2)
    mark_w = 48
    divider_x = 58
    word_x = 70
    total_w = round(word_x + word_w, 2)
    word_y = round((48 - cap) / 2, 2)
    divider = f'<rect x="{divider_x}" y="10" width="1.5" height="28" rx="0.75" fill="currentColor" opacity="0.35"/>'

    def lockup(core_fill: str, x_fill: str, title: str) -> str:
        word = (
            f'<g transform="translate({word_x},{word_y}) scale({scale:.5f})">'
            f'{wordmark_group(core_fill, x_fill)}</g>'
        )
        return svg(
            f"0 0 {total_w} 48",
            title,
            mark_rects(core_fill) + divider + word,
        )

    # --- lockup: full-color brass (default product logo) ---
    write("corex-lockup.svg", lockup(BRASS, BRASS, "Corex"))

    # --- monochrome: single ink, everything currentColor ---
    mono_word = (
        f'<g transform="translate({word_x},{word_y}) scale({scale:.5f})">'
        f'{wordmark_group("currentColor", "currentColor")}</g>'
    )
    write(
        "corex-monochrome.svg",
        svg(
            f"0 0 {total_w} 48",
            "Corex",
            mark_rects("currentColor") + divider + mono_word,
        ),
    )

    # --- contrast: AA-darkened brass core for light / high-contrast contexts ---
    write("corex-contrast.svg", lockup(BRASS_AA, BRASS_AA, "Corex"))


if __name__ == "__main__":
    main()
