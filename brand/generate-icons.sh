#!/bin/bash
# generate-icons.sh — App-Icons und Favicons aus der Bildmarke erzeugen.
# Brand Guide Abschnitt 8: Squircle, Hintergrund Signal Violet, Bildmarke weiss,
# Punkt in Fresh Mint, Symbolbreite 66 % der Icon-Breite (maskable: 52 %).
#
# Voraussetzungen: rsvg-convert, ImageMagick (convert)
set -euo pipefail

cd "$(dirname "$0")/.."
OUT_BRAND="brand/icon"
OUT_PUBLIC="public/icons"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$OUT_BRAND" "$OUT_PUBLIC"

# $1 = Symbolbreite in Prozent der Canvas, $2 = Zieldatei
build_svg() {
    local scale=$1 file=$2
    # Symbol im 100er-Raster zentrieren und auf $scale % skalieren.
    local size=$((512 * scale / 100))
    local offset=$(( (512 - size) / 2 ))

    cat > "$file" <<SVG
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <rect width="512" height="512" rx="115" fill="#5B4BE8"/>
  <g transform="translate($offset $offset) scale($(echo "scale=6; $size/100" | bc))">
    <path d="M50 20A60 60 0 0 0 20 71.96A60 60 0 0 0 80 71.96A60 60 0 0 0 50 20Z"
          fill="none" stroke="#FFFFFF" stroke-width="16" stroke-linejoin="round"/>
    <circle cx="50" cy="54" r="9.5" fill="#16D6A4"/>
  </g>
</svg>
SVG
}

build_svg 66 "$TMP/icon.svg"
build_svg 52 "$TMP/icon-maskable.svg"

for SIZE in 16 32 48 64 76 120 152 167 180 192 512 1024; do
    rsvg-convert -w "$SIZE" -h "$SIZE" "$TMP/icon.svg" -o "$OUT_BRAND/icon-$SIZE.png"
done

rsvg-convert -w 512 -h 512 "$TMP/icon-maskable.svg" -o "$OUT_BRAND/icon-maskable-512.png"

convert "$OUT_BRAND/icon-16.png" "$OUT_BRAND/icon-32.png" "$OUT_BRAND/icon-48.png" "$OUT_BRAND/favicon.ico"

# Was die App wirklich ausliefert
cp "$OUT_BRAND/icon-192.png" "$OUT_PUBLIC/icon-192.png"
cp "$OUT_BRAND/icon-512.png" "$OUT_PUBLIC/icon-512.png"
cp "$OUT_BRAND/icon-maskable-512.png" "$OUT_PUBLIC/icon-maskable-512.png"
cp "$OUT_BRAND/icon-180.png" "$OUT_PUBLIC/apple-touch-icon.png"
cp "$OUT_BRAND/favicon.ico" "public/favicon.ico"

echo "Icons erzeugt: $OUT_BRAND und $OUT_PUBLIC"
