#!/usr/bin/env python3
"""
Erzeugt die Logo-Lockups aus Brand Guide Abschnitt 2.5 als SVG.

Die Wortmarke liegt als Pfad vor (brand/logo/wordmark-path.txt, extrahiert aus
den OG-Image-Quellen), damit die Dateien ohne geladene Schriftdatei korrekt
rendern — so verlangt es Abschnitt 9.

    python3 brand/generate-lockups.py
"""

from pathlib import Path

ROOT = Path(__file__).resolve().parent
LOGO = ROOT / 'logo'

VIOLET = '#5B4BE8'
MINT = '#16D6A4'
INK = '#14122B'
WHITE = '#FFFFFF'

# Bounding-Box der Wortmarke im Quell-Koordinatensystem
WORD_X, WORD_Y = 363.58, 343.02
WORD_W, WORD_H = 472.35, 66.70          # WORD_H = Versalhöhe

# Das Symbol steht im viewBox 0..100, die sichtbare Form (inkl. Strichstärke 16)
# liegt zwischen 12 und 88 — also 76 von 100 Einheiten.
SYMBOL_VIEW = 100.0
SYMBOL_INK = 76.0
SYMBOL_OFFSET = 12.0

# Sichtbare Symbolhöhe im Verhältnis zur Versalhöhe. Entspricht dem Verhältnis,
# das Logo.vue im Web rendert.
SYMBOL_TO_CAP = 1.5

SYMBOL_SIZE = WORD_H * SYMBOL_TO_CAP     # sichtbare Kantenlänge des Symbols

WORDMARK_PATH = (LOGO / 'wordmark-path.txt').read_text().strip()


def symbol(stroke: str, dot: str) -> str:
    """Symbol so skaliert, dass die sichtbare Form bei (0,0) beginnt."""
    scale = SYMBOL_SIZE / SYMBOL_INK
    shift = -SYMBOL_OFFSET * scale

    return (
        f'<g transform="translate({shift:.4f} {shift:.4f}) scale({scale:.6f})">'
        f'<path d="M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z" '
        f'fill="none" stroke="{stroke}" stroke-width="16" stroke-linejoin="round"/>'
        f'<circle cx="50" cy="54" r="9.5" fill="{dot}"/>'
        f'</g>'
    )


def wordmark(fill: str, x: float, y: float) -> str:
    return (
        f'<g transform="translate({x - WORD_X:.4f} {y - WORD_Y:.4f})">'
        f'<path d="{WORDMARK_PATH}" fill="{fill}"/>'
        f'</g>'
    )


def horizontal(stroke: str, dot: str, word: str) -> str:
    """Symbolmitte und optische Mitte der Versalhöhe auf einer Linie,
    Abstand Symbol → Wortmarke = halbe Symbolbreite."""
    gap = SYMBOL_SIZE / 2
    width = SYMBOL_SIZE + gap + WORD_W
    height = SYMBOL_SIZE
    word_y = height / 2 - WORD_H / 2

    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.2f} {height:.2f}" '
        f'role="img" aria-label="ORGDATE">\n'
        f'  {symbol(stroke, dot)}\n'
        f'  {wordmark(word, SYMBOL_SIZE + gap, word_y)}\n'
        f'</svg>\n'
    )


def stacked(stroke: str, dot: str, word: str) -> str:
    """Abstand Symbol → Wortmarke = ein Drittel der Symbolhöhe,
    Wortmarke zentriert."""
    gap = SYMBOL_SIZE / 3
    width = max(SYMBOL_SIZE, WORD_W)
    height = SYMBOL_SIZE + gap + WORD_H
    symbol_x = (width - SYMBOL_SIZE) / 2

    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.2f} {height:.2f}" '
        f'role="img" aria-label="ORGDATE">\n'
        f'  <g transform="translate({symbol_x:.4f} 0)">{symbol(stroke, dot)}</g>\n'
        f'  {wordmark(word, (width - WORD_W) / 2, SYMBOL_SIZE + gap)}\n'
        f'</svg>\n'
    )


FILES = {
    'orgdate-logo-horizontal.svg': horizontal(VIOLET, MINT, INK),
    'orgdate-logo-stacked.svg': stacked(VIOLET, MINT, INK),
    # Negativ auf Primärfarbe: Bögen und Wortmarke weiß, Punkt bleibt Mint.
    'orgdate-logo-horizontal-inverse.svg': horizontal(WHITE, MINT, WHITE),
    'orgdate-logo-stacked-inverse.svg': stacked(WHITE, MINT, WHITE),
    # Einfarbig für Gravur, Stempel, einfarbigen Druck.
    'orgdate-logo-horizontal-mono.svg': horizontal(INK, INK, INK),
}

for name, content in FILES.items():
    (LOGO / name).write_text(content)
    print(f'{name}  ({len(content)} Bytes)')
