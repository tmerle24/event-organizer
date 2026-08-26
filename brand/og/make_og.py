import os
import cairosvg
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.misc.transform import Transform

OUT = "/mnt/user-data/outputs/og"
os.makedirs(OUT, exist_ok=True)

VIOLET = "#5B4BE8"
VIOLET_DEEP = "#3A2CB8"
VIOLET_DECO = "#6D5EEC"
MINT = "#16D6A4"
INK = "#14122B"
MIST = "#F2F1FA"
SOFT = "#CECBF6"

_fonts = {}


def load(weight):
    if weight not in _fonts:
        f = TTFont("/home/claude/fonts/Outfit.ttf")
        f = instancer.instantiateVariableFont(f, {"wght": weight})
        _fonts[weight] = f
    return _fonts[weight]


def text_path(text, size, weight=600, tracking_em=0.0, x0=0.0, y0=0.0):
    f = load(weight)
    upm = f["head"].unitsPerEm
    gs = f.getGlyphSet()
    cmap = f.getBestCmap()
    scale = size / upm
    x = x0
    parts = []
    for ch in text:
        gname = cmap[ord(ch)]
        pen = SVGPathPen(gs)
        tp = TransformPen(pen, Transform(scale, 0, 0, -scale, x, y0))
        gs[gname].draw(tp)
        d = pen.getCommands()
        if d:
            parts.append(d)
        x += gs[gname].width * scale + size * tracking_em
    width = x - x0 - (size * tracking_em if tracking_em else 0)
    return " ".join(parts), width


def cap_height(size, weight=600):
    f = load(weight)
    return f["OS/2"].sCapHeight / f["head"].unitsPerEm * size


MARK = "M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"


def mark(cx, cy, size, ring, dot, stroke_ratio=0.16, dot_ratio=0.095):
    s = size / 100.0
    tx = cx - size / 2
    ty = cy - size / 2
    return (
        f'<g transform="translate({tx:.2f},{ty:.2f}) scale({s:.4f})">'
        f'<path d="{MARK}" fill="none" stroke="{ring}" '
        f'stroke-width="{stroke_ratio * 100:.2f}" stroke-linejoin="round"/>'
        f'<circle cx="50" cy="54" r="{dot_ratio * 100:.2f}" fill="{dot}"/></g>'
    )


def deco(w, h, color):
    """Tonwertgleiche Zierformen, die bewusst aus dem Format laufen."""
    return (
        mark(-40, h * 0.18, 420, color, color, 0.09, 0.0)
        + mark(w + 60, h * 0.86, 520, color, color, 0.09, 0.0)
    )


def build(w, h, bg, ring, dot, wm_color, tag_color, deco_color, tagline, wm_size, mark_size, offset=0.0):
    wm, wm_w = text_path("ORGDATE", wm_size, 600, 0.05)
    cap = cap_height(wm_size, 600)
    tag_size = round(wm_size * 0.36)
    tag, tag_w = text_path(tagline, tag_size, 400, 0.0)

    gap1 = mark_size * 0.30
    gap2 = cap * 0.75
    block_h = mark_size + gap1 + cap + gap2 + tag_size * 0.72
    top = (h - block_h) / 2 + h * offset

    mark_cy = top + mark_size / 2
    wm_baseline = top + mark_size + gap1 + cap
    tag_baseline = wm_baseline + gap2 + tag_size * 0.72

    wm_d, _ = text_path("ORGDATE", wm_size, 600, 0.05, (w - wm_w) / 2, wm_baseline)
    tag_d, _ = text_path(tagline, tag_size, 400, 0.0, (w - tag_w) / 2, tag_baseline)

    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">
<rect width="{w}" height="{h}" fill="{bg}"/>
{deco(w, h, deco_color)}
{mark(w / 2, mark_cy, mark_size, ring, dot)}
<path d="{wm_d}" fill="{wm_color}"/>
<path d="{tag_d}" fill="{tag_color}"/>
</svg>'''


TAGLINE = "Der Termin, der allen passt."

variants = [
    ("og-image", 1200, 630, VIOLET, "#FFFFFF", MINT, "#FFFFFF", SOFT, VIOLET_DECO, 92, 150),
    ("og-image-light", 1200, 630, "#FFFFFF", VIOLET, MINT, INK, "#6E6B85", MIST, 92, 150),
    ("og-image-square", 1200, 1200, VIOLET, "#FFFFFF", MINT, "#FFFFFF", SOFT, VIOLET_DECO, 116, 200, 0.035),
    ("og-image-dark", 1200, 630, INK, "#FFFFFF", MINT, "#FFFFFF", "#A5A2BC", "#221F42", 92, 150),
]

for v in variants:
    name, w, h, bg, ring, dot, wmc, tagc, dc, wms, ms = v[:11]
    off = v[11] if len(v) > 11 else 0.0
    svg = build(w, h, bg, ring, dot, wmc, tagc, dc, TAGLINE, wms, ms, off)
    with open(f"{OUT}/{name}.svg", "w") as fh:
        fh.write(svg)
    cairosvg.svg2png(bytestring=svg.encode(), write_to=f"{OUT}/{name}.png",
                     output_width=w, output_height=h)
    print(name, w, "x", h, "->", os.path.getsize(f"{OUT}/{name}.png"), "bytes")
