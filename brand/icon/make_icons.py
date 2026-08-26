import os
import cairosvg
from PIL import Image

OUT = "/mnt/user-data/outputs/icon"
os.makedirs(OUT, exist_ok=True)

VIOLET = "#5B4BE8"
MINT = "#16D6A4"
WHITE = "#FFFFFF"
INK = "#14122B"

MARK = "M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"
VIS = 76.0
VIS_OFF = 12.0
IOS_RADIUS = 0.2237


def tile(size, bg, ring, dot, coverage=0.66, rounded=True, transparent=False):
    """Ein Icon-SVG in der Kantenlaenge size.

    Unter 64 Pixel werden Strichstaerke, Punktradius und Flaechenanteil
    angehoben, sonst laufen Ring und Punkt beim Rastern zu.
    """
    if size <= 16:
        sw, r, coverage = 22.0, 12.0, 0.74
    elif size <= 32:
        sw, r, coverage = 19.0, 11.0, 0.70
    elif size <= 48:
        sw, r, coverage = 17.0, 10.0, 0.68
    else:
        sw, r = 16.0, 9.5
    scale = coverage * size / VIS
    off = (size - VIS * scale) / 2 - VIS_OFF * scale

    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {size} {size}" '
             f'width="{size}" height="{size}">']
    if not transparent:
        rx = f' rx="{size * IOS_RADIUS:.3f}"' if rounded else ""
        parts.append(f'<rect width="{size}" height="{size}"{rx} fill="{bg}"/>')
    parts.append(f'<g transform="translate({off:.4f},{off:.4f}) scale({scale / 1:.6f})">')
    parts.append(f'<path d="{MARK}" fill="none" stroke="{ring}" stroke-width="{sw}" '
                 f'stroke-linejoin="round"/>')
    parts.append(f'<circle cx="50" cy="54" r="{r}" fill="{dot}"/></g></svg>')
    return "".join(parts)


def render(svg, path, size):
    cairosvg.svg2png(bytestring=svg.encode(), write_to=path,
                     output_width=size, output_height=size)


made = []

# Standard-Kacheln, violetter Grund
for s in [16, 32, 48, 64, 76, 120, 152, 167, 192, 512]:
    name = f"icon-{s}.png"
    render(tile(s, VIOLET, WHITE, MINT), f"{OUT}/{name}", s)
    made.append(name)

# Favicon-PNGs als Alias der kleinen Groessen
for s in [16, 32, 48]:
    Image.open(f"{OUT}/icon-{s}.png").save(f"{OUT}/favicon-{s}.png")
    made.append(f"favicon-{s}.png")

# favicon.ico mit drei eingebetteten Groessen
ico_frames = [Image.open(f"{OUT}/icon-{n}.png") for n in (16, 32, 48)]
ico_frames[2].save(f"{OUT}/favicon.ico", format="ICO",
                   sizes=[(48, 48), (32, 32), (16, 16)],
                   append_images=ico_frames[:2])
made.append("favicon.ico")

# iOS: quadratisch ohne Rundung, iOS maskiert selbst
render(tile(180, VIOLET, WHITE, MINT, rounded=False),
       f"{OUT}/apple-touch-icon.png", 180)
made.append("apple-touch-icon.png")

# Store-Asset: quadratisch, ohne Transparenz
render(tile(1024, VIOLET, WHITE, MINT, rounded=False),
       f"{OUT}/icon-1024.png", 1024)
made.append("icon-1024.png")

# Android maskable: Symbol auf 52 Prozent, volle Flaeche
for s in [192, 512]:
    render(tile(s, VIOLET, WHITE, MINT, coverage=0.52, rounded=False),
           f"{OUT}/icon-maskable-{s}.png", s)
    made.append(f"icon-maskable-{s}.png")

# Helle Alternative
for s in [192, 512]:
    render(tile(s, WHITE, VIOLET, MINT), f"{OUT}/icon-light-{s}.png", s)
    made.append(f"icon-light-{s}.png")

# Transparente Bildmarke fuer Overlays
render(tile(512, None, VIOLET, MINT, transparent=True),
       f"{OUT}/icon-transparent-512.png", 512)
made.append("icon-transparent-512.png")

# SVG-Favicon fuer moderne Browser
with open(f"{OUT}/favicon.svg", "w") as fh:
    fh.write(tile(64, VIOLET, WHITE, MINT))
made.append("favicon.svg")

for n in sorted(made):
    print(f"{n:28s} {os.path.getsize(f'{OUT}/{n}'):7d} bytes")
