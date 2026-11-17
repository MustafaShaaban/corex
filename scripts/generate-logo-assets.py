#!/usr/bin/env python3
"""Generate the CoreX logo set from the approved design.

This script is a *faithful mechanical extraction*, not a redrawing. The symbol geometry is the
documented locked winner ("Core X": five rounded 12u modules on a 48x48 grid, 3u gutters, 2.5u
corner radius). The wordmark glyph outlines are extracted from the self-hosted, OFL-licensed
Space Grotesk variable font at the documented shipping weight (600): no glyph is invented, traced
or redrawn. The colours and the variants are the approved logo set of spec 134
(design/handoffs/spec-134-logo-set.md, DECISIONS #637): "Core" in the neutral colour, only the
terminal "x" and the centre module in brass.

Source of truth:
  - design handoffs: "Corex Logo System.dc.html" (Core X locked winner) and the R2.2 "Logo set"
    artboard (spec-134-logo-set.md)
  - font: theme/assets/fonts/space-grotesk-latin-500-700.woff2 (wght 600 instance)

One run writes every file of plugins/corex-config/assets/brand/ and the theme's favicon copies:

  1. the SVGs, optimised with svgo (scripts/svgo-logo.config.mjs);
  2. the favicon PNGs at 16, 32, 180 and 512px, rendered with PyMuPDF. They are the one raster set
     the logo system ships, an explicit exception (spec 134, FR-021): browsers and phones ask for
     them. The 180 and 512 fill the square, since phones round the corners themselves;
  3. the favicon files copied into theme/assets/favicon/, which the CoreX theme links;
  4. logo-manifest.json, every file with its use, its colours and its hash;
  5. the print image, by running scripts/generate-logo-print.py.

Needs fontTools and PyMuPDF (`pip install fonttools brotli pymupdf`) and the repository's node
modules (`npm ci`: svgo is a pinned devDependency, run from node_modules). Run from anywhere:

    python scripts/generate-logo-assets.py
"""

from __future__ import annotations

import hashlib
import json
import os
import shutil
import subprocess
import sys

import fitz
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FONT = os.path.join(ROOT, "theme", "assets", "fonts", "space-grotesk-latin-500-700.woff2")
OUT_REL = "plugins/corex-config/assets/brand"
OUT = os.path.join(ROOT, *OUT_REL.split("/"))
THEME_COPY_REL = "theme/assets/favicon"
THEME_COPY = os.path.join(ROOT, *THEME_COPY_REL.split("/"))
MANIFEST = os.path.join(OUT, "logo-manifest.json")
SVGO_CONFIG = os.path.join(ROOT, "scripts", "svgo-logo.config.mjs")
# svgo is a pinned devDependency (package.json), run from node_modules so nothing is fetched.
SVGO = os.path.join(ROOT, "node_modules", "svgo", "bin", "svgo")
PRINT_SCRIPT = os.path.join(ROOT, "scripts", "generate-logo-print.py")

BRASS = "#c9a25e"        # brass on a dark ground (and the knockout's tile)
BRASS_AA = "#ad8643"     # brass on a light ground
GRAPHITE = "#14151a"     # the neutral on a light ground, the favicon's tile, the knockout's ink
NEAR_WHITE = "#f4f5f7"   # the neutral on a dark ground
INLINE = "currentColor"  # files drawn inline, in the colour of the text around them

# The fixed-colour inks for each ground, as the approved artboard draws them. The divider is the
# artboard's own (spec-134-logo-set.md, "Lockup, horizontal").
GROUNDS = {
    "light": {"neutral": GRAPHITE, "brass": BRASS_AA, "divider": "#c2c8d1"},
    "dark": {"neutral": NEAR_WHITE, "brass": BRASS, "divider": "#343b47"},
}

NAME = "CoreX"           # the accessible name of a lockup, an image of text
WEIGHT = 600
WORD = "Corex"
MODULES = [(3, 3), (33, 3), (18, 18), (3, 33), (33, 33)]  # the centre module is index 2

# The stacked lockup, from the artboard: a 64px symbol, a 12px gap and the word at 28px with a
# line height of 1. In the symbol's 48u grid that is a 9u gap and a 21u type size.
STACK_GAP = 9.0
STACK_SIZE = 21.0
KNOCKOUT_PADDING = 24.0  # half a symbol of brass around the stacked drawing

# The favicon, from the artboard: the symbol at 75% on a 48u graphite tile with a 10u radius; the
# phone sizes fill the square and hold the symbol at 65%.
FAVICON_SIZES = (16, 32, 180, 512)


def wordmark() -> dict:
    """The word's two outlines ("Core" and the "x"), their ink box and the font's metrics."""
    font = TTFont(FONT)
    instantiateVariableFont(font, {"wght": WEIGHT}, inplace=True)
    glyphs = font.getGlyphSet()
    cmap = font.getBestCmap()
    hmtx = font["hmtx"]
    letter_spacing = round(-0.035 * font["head"].unitsPerEm)  # -0.035em tracking

    core_pen = SVGPathPen(glyphs)   # "Core": the neutral
    x_pen = SVGPathPen(glyphs)      # the terminal "x": brass
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
    return {
        "core": core_pen.getCommands(),
        "x": x_pen.getCommands(),
        "min_x": min_x,
        "min_y": min_y,
        "w": round(max_x - min_x, 2),
        "h": round(max_y - min_y, 2),
        "upm": font["head"].unitsPerEm,
        # The typographic metrics: the font sets USE_TYPO_METRICS, so a browser places a line by them.
        "ascent": font["OS/2"].sTypoAscender,
        "descent": -font["OS/2"].sTypoDescender,
    }


def mark_rects(centre: str, corners: str) -> str:
    out = []
    for i, (x, y) in enumerate(MODULES):
        fill = centre if i == 2 else corners
        out.append(f'<rect x="{x}" y="{y}" width="12" height="12" rx="2.5" fill="{fill}"/>')
    return "".join(out)


def svg(view_box: str, body: str, named: bool) -> str:
    """An SVG file: a lockup is an image of text named "CoreX"; anything else is decorative."""
    if named:
        accessible = f'role="img" aria-label="{NAME}"><title>{NAME}</title>'
    else:
        accessible = 'aria-hidden="true">'
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{view_box}" fill="none" '
        f"{accessible}{body}</svg>\n"
    )


class LogoSet:
    """Draws every variant from the one mark and the one word."""

    def __init__(self) -> None:
        self.word = wordmark()
        w = self.word

        # The horizontal lockup (mark | divider | wordmark), height 48: today's geometry.
        self.cap = 28.0                      # the word's ink height inside the 48u lockup
        self.scale = self.cap / w["h"]
        self.word_x = 70
        self.word_y = round((48 - self.cap) / 2, 2)
        self.total_w = round(self.word_x + round(w["w"] * self.scale, 2), 2)

    def word_group(self, core: str, x: str) -> str:
        """The word with its ink box at the origin."""
        w = self.word
        return (
            f'<g transform="translate({-w["min_x"]:.2f},{-w["min_y"]:.2f})">'
            f'<path d="{w["core"]}" fill="{core}"/>'
            f'<path d="{w["x"]}" fill="{x}"/></g>'
        )

    # --- the files drawn inline, in currentColor ------------------------------------------------

    def symbol(self) -> str:
        return svg("0 0 48 48", mark_rects(BRASS, INLINE), named=False)

    def wordmark_only(self) -> str:
        w = self.word
        return svg(f'0 0 {w["w"]} {w["h"]}', self.word_group(INLINE, BRASS), named=True)

    def lockup(self, neutral: str, brass: str, divider: str, divider_opacity: str = "") -> str:
        opacity = f' opacity="{divider_opacity}"' if divider_opacity else ""
        rule = f'<rect x="58" y="10" width="1.5" height="28" rx="0.75" fill="{divider}"{opacity}/>'
        word = (
            f'<g transform="translate({self.word_x},{self.word_y}) scale({self.scale:.5f})">'
            f"{self.word_group(neutral, brass)}</g>"
        )
        return svg(f"0 0 {self.total_w} 48", mark_rects(brass, neutral) + rule + word, named=True)

    # --- the stacked lockup and the knockout ----------------------------------------------------

    def stacked_body(self, neutral: str, brass: str, corners: str, dx: float = 0, dy: float = 0) -> tuple[str, float, float]:
        """The stacked drawing (symbol over the word) and its width and height."""
        w = self.word
        scale = STACK_SIZE / w["upm"]
        word_w = w["w"] * scale
        width = max(48.0, word_w)
        # The word's line box starts one gap under the symbol; with a line height of 1 the
        # baseline sits half the leading (negative here) plus the ascent below its top.
        line_top = 48 + STACK_GAP
        baseline = line_top + (STACK_SIZE - (w["ascent"] + w["descent"]) * scale) / 2 + w["ascent"] * scale
        ink_top = baseline + w["min_y"] * scale
        height = round(ink_top + w["h"] * scale, 2)
        mark_x = round(dx + (width - 48) / 2, 2)
        word_x = round(dx + (width - word_w) / 2, 2)
        body = (
            f'<g transform="translate({mark_x},{round(dy, 2)})">{mark_rects(brass, corners)}</g>'
            f'<g transform="translate({word_x},{round(dy + ink_top, 2)}) scale({scale:.5f})">'
            f"{self.word_group(neutral, brass)}</g>"
        )
        return body, round(width, 2), height

    def stacked(self, neutral: str, brass: str) -> str:
        body, width, height = self.stacked_body(neutral, brass, neutral)
        return svg(f"0 0 {width} {height}", body, named=True)

    def knockout(self) -> str:
        _, width, height = self.stacked_body(GRAPHITE, GRAPHITE, GRAPHITE)
        side = round(max(width, height) + 2 * KNOCKOUT_PADDING, 2)
        body, _, _ = self.stacked_body(
            GRAPHITE, GRAPHITE, GRAPHITE, dx=(side - width) / 2, dy=(side - height) / 2
        )
        tile = f'<rect width="{side}" height="{side}" fill="{BRASS}"/>'
        return svg(f"0 0 {side} {side}", tile + body, named=True)

    # --- the symbol in fixed colours, and the favicon -------------------------------------------

    def fixed_symbol(self, ground: str) -> str:
        ink = GROUNDS[ground]
        return svg("0 0 48 48", mark_rects(ink["brass"], ink["neutral"]), named=False)

    @staticmethod
    def favicon(fill_square: bool = False) -> str:
        if fill_square:
            tile = f'<rect width="48" height="48" fill="{GRAPHITE}"/>'
            inner = '<g transform="translate(8.4 8.4) scale(0.65)">'
        else:
            tile = f'<rect width="48" height="48" rx="10" fill="{GRAPHITE}"/>'
            inner = '<g transform="translate(6 6) scale(0.75)">'
        return svg("0 0 48 48", tile + inner + mark_rects(BRASS, NEAR_WHITE) + "</g>", named=False)


def write(name: str, content: str) -> str:
    path = os.path.join(OUT, name)
    with open(path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(content)
    return path


def optimise(paths: list[str]) -> None:
    node = shutil.which("node")
    if node is None or not os.path.isfile(SVGO):
        sys.exit("svgo was not found in node_modules: run `npm ci` and try again (svgo optimises the files).")
    subprocess.run([node, SVGO, "--quiet", "--config", SVGO_CONFIG, *paths], check=True, cwd=ROOT)


def render_png(source: str, size: int, name: str) -> str:
    page = fitz.open(stream=source.encode("utf-8"), filetype="svg")[0]
    pixmap = page.get_pixmap(matrix=fitz.Matrix(size / 48, size / 48), alpha=True)
    path = os.path.join(OUT, name)
    pixmap.save(path)
    return path


def digest(path: str) -> str:
    with open(path, "rb") as handle:
        return hashlib.sha256(handle.read()).hexdigest()


def view_box(path: str) -> str:
    with open(path, encoding="utf-8") as handle:
        text = handle.read()
    start = text.index('viewBox="') + len('viewBox="')
    return text[start:text.index('"', start)]


def main() -> None:
    os.makedirs(OUT, exist_ok=True)
    os.makedirs(THEME_COPY, exist_ok=True)
    logos = LogoSet()
    light, dark = GROUNDS["light"], GROUNDS["dark"]
    favicon_colors = {"tile": GRAPHITE, "neutral": NEAR_WHITE, "brass": BRASS}

    # Each entry: variant, file, drawing, accessible usage, the ground it is for, its colours, its use.
    svgs = [
        ("symbol", "corex-symbol.svg", logos.symbol(), "decorative", "inline",
         {"corners": INLINE, "brass": BRASS},
         "The bare mark, drawn inline: the corners take the text colour around it, the centre is brass."),
        ("symbol-light", "corex-symbol-light.svg", logos.fixed_symbol("light"), "decorative", "light",
         {"neutral": light["neutral"], "brass": light["brass"]},
         "The bare mark in fixed colours, for a light background."),
        ("symbol-dark", "corex-symbol-dark.svg", logos.fixed_symbol("dark"), "decorative", "dark",
         {"neutral": dark["neutral"], "brass": dark["brass"]},
         "The bare mark in fixed colours, for a dark background."),
        ("wordmark", "corex-wordmark.svg", logos.wordmark_only(), "named-image", "inline",
         {"neutral": INLINE, "brass": BRASS},
         "The word alone, drawn inline: \"Core\" in the text colour, the terminal \"x\" in brass."),
        ("lockup", "corex-lockup.svg", logos.lockup(INLINE, BRASS, INLINE, ".35"), "named-image", "inline",
         {"neutral": INLINE, "brass": BRASS},
         "The horizontal lockup (mark, divider, word), drawn inline: the neutral is the text colour around it."),
        ("lockup-light", "corex-lockup-light.svg", logos.lockup(light["neutral"], light["brass"], light["divider"]),
         "named-image", "light", dict(light),
         "The horizontal lockup in fixed colours, for a light background: the sign-in in Light."),
        ("lockup-dark", "corex-lockup-dark.svg", logos.lockup(dark["neutral"], dark["brass"], dark["divider"]),
         "named-image", "dark", dict(dark),
         "The horizontal lockup in fixed colours, for a dark background: the sign-in in Dark."),
        ("stacked-light", "corex-stacked-light.svg", logos.stacked(light["neutral"], light["brass"]),
         "named-image", "light", {"neutral": light["neutral"], "brass": light["brass"]},
         "The stacked lockup (mark over word) for square places, on a light background."),
        ("stacked-dark", "corex-stacked-dark.svg", logos.stacked(dark["neutral"], dark["brass"]),
         "named-image", "dark", {"neutral": dark["neutral"], "brass": dark["brass"]},
         "The stacked lockup (mark over word) for square places, on a dark background."),
        ("knockout", "corex-knockout.svg", logos.knockout(), "named-image", "brass tile",
         {"tile": BRASS, "ink": GRAPHITE},
         "One ink, graphite, on a brass tile: the stacked lockup for print and stamps."),
        ("monochrome", "corex-monochrome.svg", logos.lockup(INLINE, INLINE, INLINE, ".35"), "named-image", "inline",
         {"ink": INLINE},
         "The horizontal lockup in one ink, the text colour: for one-colour and forced-colours contexts."),
        ("contrast", "corex-contrast.svg", logos.lockup(INLINE, BRASS_AA, INLINE, ".35"), "linked-brand", "inline",
         {"neutral": INLINE, "brass": BRASS_AA},
         "The horizontal lockup drawn inline with the brass for a light background; the print image's source."),
        ("favicon", "corex-favicon.svg", logos.favicon(), "decorative", "graphite tile", favicon_colors,
         "The favicon: the symbol on a graphite tile, so it reads in a light and a dark browser bar."),
    ]

    paths = [write(filename, content) for _, filename, content, *_ in svgs]
    optimise(paths)

    # The favicon PNGs: the 16 and 32 from the shipped favicon, the phone sizes from the drawing
    # that fills the square.
    with open(os.path.join(OUT, "corex-favicon.svg"), encoding="utf-8") as handle:
        favicon_svg = handle.read()
    pngs = []
    for size in FAVICON_SIZES:
        filled = size >= 180
        name = f"corex-favicon-{size}.png"
        render_png(LogoSet.favicon(fill_square=True) if filled else favicon_svg, size, name)
        pngs.append((size, name, filled))

    entries = []
    for variant, filename, _, usage, ground, colors, use in svgs:
        path = os.path.join(OUT, filename)
        entry = {
            "variant": variant,
            "path": f"{OUT_REL}/{filename}",
            "filename": filename,
            "view_box": view_box(path),
            "ground": ground,
            "colors": colors,
            "use": use,
            "sha256": digest(path),
            "accessible_usage": usage,
            "client_site_default": False,
        }
        if variant == "favicon":
            entry["theme_copy"] = f"{THEME_COPY_REL}/{filename}"
        entries.append(entry)

    for size, name, filled in pngs:
        drawing = "fills the square: phones round the corners" if filled else "the favicon's rounded tile"
        # The 16 and 32 are drawn from the shipped favicon. The phone sizes are drawn from the square
        # drawing (LogoSet.favicon(fill_square=True)), which is not shipped as a file of its own.
        source = (
            "LogoSet.favicon(fill_square=True) in scripts/generate-logo-assets.py: the square favicon drawing"
            if filled
            else f"{OUT_REL}/corex-favicon.svg"
        )
        entries.append({
            "variant": f"favicon-{size}",
            "path": f"{OUT_REL}/{name}",
            "filename": name,
            "pixels": [size, size],
            "ground": "graphite tile",
            "colors": favicon_colors,
            "use": f"The favicon at {size}px, {drawing}.",
            "rendered_from": source,
            "sha256": digest(os.path.join(OUT, name)),
            "accessible_usage": "decorative",
            "client_site_default": False,
            "theme_copy": f"{THEME_COPY_REL}/{name}",
        })

    # The theme's copies (DECISIONS #637): the CoreX theme links them when the site has no icon.
    for name in os.listdir(THEME_COPY):
        os.remove(os.path.join(THEME_COPY, name))
    for entry in entries:
        if "theme_copy" in entry:
            shutil.copyfile(os.path.join(OUT, entry["filename"]), os.path.join(ROOT, *entry["theme_copy"].split("/")))

    with open(MANIFEST, encoding="utf-8") as handle:
        manifest = json.load(handle)
    manifest["set_approval_date"] = "2026-11-16"
    manifest["set_handoff"] = "design/handoffs/spec-134-logo-set.md"
    manifest["provenance"]["geometry"] = (
        "Five rounded 12u modules on a 48x48 grid, 3u gutters, 2.5u corner radius, verbatim from the "
        "approved Core X locked winner. The centre module and the terminal 'x' are brass; the corners, "
        "the divider and 'Core' are the neutral."
    )
    manifest["provenance"]["wordmark_extraction"] = (
        "Glyph outlines for 'Corex' mechanically extracted (not traced or redrawn) from "
        "theme/assets/fonts/space-grotesk-latin-500-700.woff2 instanced at wght=600, letter-spacing "
        "-0.035em: 'Core' in the neutral colour, the terminal 'x' in brass."
    )
    manifest["provenance"]["generator"] = (
        "scripts/generate-logo-assets.py (deterministic) writes every file, the theme's favicon copies "
        "and this manifest; the SVGs are optimised with scripts/svgo-logo.config.mjs."
    )
    manifest["provenance"]["colors"] = {
        "brass": BRASS,
        "brass_aa_on_light": BRASS_AA,
        "neutral_on_light": GRAPHITE,
        "neutral_on_dark": NEAR_WHITE,
        "divider_on_light": light["divider"],
        "divider_on_dark": dark["divider"],
    }
    manifest["assets"] = entries
    with open(MANIFEST, "w", encoding="utf-8", newline="\n") as handle:
        json.dump(manifest, handle, indent=2, ensure_ascii=False)
        handle.write("\n")

    for entry in entries:
        print("wrote", entry["path"], entry["sha256"])

    # The print image is rendered from corex-contrast.svg, so it is drawn again with it.
    subprocess.run([sys.executable, PRINT_SCRIPT], check=True, cwd=ROOT)


if __name__ == "__main__":
    main()
