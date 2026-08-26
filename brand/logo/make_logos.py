import os
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.misc.transform import Transform

OUT = "/mnt/user-data/outputs/logo"
os.makedirs(OUT, exist_ok=True)

VIOLET = "#5B4BE8"
VIOLET_SOFT = "#AFA9EC"
MINT = "#16D6A4"
INK = "#14122B"
WHITE = "#FFFFFF"

TRACK = 0.05
WM = "ORGDATE"

MARK = "M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"
MARK_OPEN = "M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96"
MARK_OPEN_TAIL = "M78.69 59.49A60 60 0 0 0 60.15 27.37"

VIS = 76.0
VIS_OFF = 12.0

_font = None


def font():
    global _font
    if _font is None:
        f = TTFont("/home/claude/fonts/Outfit.ttf")
        _font = instancer.instantiateVariableFont(f, {"wght": 600})
    return _font


def wordmark_path(size, x0=0.0, y0=0.0):
    f = font()
    upm = f["head"].unitsPerEm
    gs, cmap = f.getGlyphSet(), f.getBestCmap()
    sc = size / upm
    x = x0
    parts = []
    for ch in WM:
        g = cmap[ord(ch)]
        pen = SVGPathPen(gs)
        gs[g].draw(TransformPen(pen, Transform(sc, 0, 0, -sc, x, y0)))
        if pen.getCommands():
            parts.append(pen.getCommands())
        x += gs[g].width * sc + size * TRACK
    return " ".join(parts), x - x0 - size * TRACK


def cap(size):
    f = font()
    return f["OS/2"].sCapHeight / f["head"].unitsPerEm * size


def symbol(x, y, size, ring, dot, sw=16.0, r=9.5, open_variant=False, soft=None):
    s = size / 100.0
    tx = x - VIS_OFF * s
    ty = y - VIS_OFF * s
    g = f'<g transform="translate({tx:.3f},{ty:.3f}) scale({s:.5f})">'
    if open_variant:
        g += (f'<path d="{MARK_OPEN}" fill="none" stroke="{ring}" stroke-width="{sw}"'
              f' stroke-linejoin="round" stroke-linecap="round"/>')
        g += (f'<path d="{MARK_OPEN_TAIL}" fill="none" stroke="{soft}" stroke-width="{sw}"'
              f' stroke-linecap="round"/>')
    else:
        g += (f'<path d="{MARK}" fill="none" stroke="{ring}" stroke-width="{sw}"'
              f' stroke-linejoin="round"/>')
    g += f'<circle cx="50" cy="54" r="{r}" fill="{dot}"/></g>'
    return g


def svg(w, h, body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" '
            f'width="{w}" height="{h}" role="img" aria-label="ORGDATE">'
            f'<title>{title}</title>{body}</svg>')


def horizontal(ring, dot, wm_color, title):
    ms = 100.0
    vis = VIS * ms / 100.0
    gap = vis / 2
    fs = 52.0 * ms / 100.0
    c = cap(fs)
    x_wm = vis + gap
    baseline = vis / 2 + c / 2
    d, wmw = wordmark_path(fs, x_wm, baseline)
    body = symbol(0, 0, ms, ring, dot) + f'<path d="{d}" fill="{wm_color}"/>'
    return svg(round(x_wm + wmw, 2), round(vis, 2), body, title)


def stacked(ring, dot, wm_color, title):
    ms = 100.0
    vis = VIS * ms / 100.0
    fs = 34.0
    c = cap(fs)
    gap = vis / 4
    d0, wmw = wordmark_path(fs)
    w = round(wmw, 2)
    h = round(vis + gap + c, 2)
    d, _ = wordmark_path(fs, (w - wmw) / 2, vis + gap + c)
    body = symbol((w - vis) / 2, 0, ms, ring, dot) + f'<path d="{d}" fill="{wm_color}"/>'
    return svg(w, h, body, title)


files = {
    "orgdate-logo-horizontal.svg": horizontal(VIOLET, MINT, INK, "ORGDATE"),
    "orgdate-logo-stacked.svg": stacked(VIOLET, MINT, INK, "ORGDATE"),
    "orgdate-logo-inverse.svg": horizontal(WHITE, MINT, WHITE, "ORGDATE"),
    "orgdate-logo-inverse-stacked.svg": stacked(WHITE, MINT, WHITE, "ORGDATE"),
    "orgdate-logo-mono.svg": horizontal(INK, INK, INK, "ORGDATE"),
    "orgdate-logo-mono-white.svg": horizontal(WHITE, WHITE, WHITE, "ORGDATE"),
    "orgdate-symbol.svg": svg(76, 76, symbol(0, 0, 100, VIOLET, MINT), "ORGDATE Bildmarke"),
    "orgdate-symbol-white.svg": svg(76, 76, symbol(0, 0, 100, WHITE, MINT), "ORGDATE Bildmarke"),
    "orgdate-symbol-mono.svg": svg(76, 76, symbol(0, 0, 100, INK, INK), "ORGDATE Bildmarke"),
    "orgdate-symbol-open.svg": svg(
        76, 76,
        symbol(0, 0, 100, VIOLET, MINT, open_variant=True, soft=VIOLET_SOFT),
        "ORGDATE Bildmarke offen"),
    "orgdate-symbol-16.svg": svg(
        76, 76, symbol(0, 0, 100, VIOLET, MINT, sw=17.0, r=10.0),
        "ORGDATE Bildmarke klein"),
}

for name, content in files.items():
    with open(f"{OUT}/{name}", "w") as fh:
        fh.write(content)
    print(f"{name:38s} {len(content):6d} bytes")
