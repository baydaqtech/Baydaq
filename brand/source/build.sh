#!/usr/bin/env bash
# Builds the Baydaq brand SVGs. Usage: bash brand/source/build.sh brand
# Mark geometry lives on a 100×100 grid: ink spans x 20–80, y 10–90.
set -e
SRC="$(cd "$(dirname "$0")" && pwd)"
OUT="$1"
F900=$(base64 -w0 "$SRC/alexandria-900-arabic.woff2")
F300=$(base64 -w0 "$SRC/alexandria-300-arabic.woff2")

WALNUT="#3B2414"; BRASS="#C8902F"; IVORY="#F6EBD6"; BRASS_BRIGHT="#D6A24E"; MUTED="#6B5238"

mark() { # $1 = body colour, $2 = head colour
  printf '<circle cx="50" cy="23" r="13" fill="%s"/><g fill="%s"><rect x="41" y="40" width="18" height="6" rx="3"/><rect x="34" y="50" width="32" height="10" rx="2.5"/><rect x="27" y="64" width="46" height="10" rx="2.5"/><rect x="20" y="78" width="60" height="12" rx="2.5"/></g>' "$2" "$1"
}

fonts() {
  printf '<style>@font-face{font-family:"Baydaq Black";src:url(data:font/woff2;base64,%s) format("woff2")}@font-face{font-family:"Baydaq Light";src:url(data:font/woff2;base64,%s) format("woff2")}</style>' "$F900" "$F300"
}

svg_mark() { # file, body, head
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="512" height="512"><title>بيدق تكنولوجي</title>%s</svg>\n' "$(mark "$2" "$3")" > "$OUT/$1"
}

svg_app() { # file
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="512" height="512"><title>بيدق تكنولوجي</title><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4A2E1A"/><stop offset="1" stop-color="#2A190D"/></linearGradient></defs><rect width="100" height="100" rx="22" fill="url(#g)"/><g transform="translate(50 50) scale(.66) translate(-50 -50)">%s</g></svg>\n' "$(mark "$IVORY" "$BRASS_BRIGHT")" > "$OUT/$1"
}

svg_horizontal() { # file, word colour, head colour, sub colour, rule colour
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 120" width="1024" height="480"><title>بيدق تكنولوجي</title>%s<g transform="translate(164 7) scale(1.05)">%s</g><text x="166.2" y="58.2" text-anchor="end" font-family="Baydaq Black, Alexandria, sans-serif" font-weight="900" font-size="62" fill="%s">بيدق</text><rect x="147" y="94.8" width="16" height="2.4" rx="1.2" fill="%s"/><text x="139" y="101.3" text-anchor="end" font-family="Baydaq Light, Alexandria, sans-serif" font-weight="300" font-size="18" fill="%s">تكنولوجي</text></svg>\n' \
    "$(fonts)" "$(mark "$2" "$3")" "$2" "$5" "$4" > "$OUT/$1"
}

svg_stacked() { # file, word colour, head colour, sub colour, rule colour
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 218" width="720" height="872"><title>بيدق تكنولوجي</title>%s<g transform="translate(37.5 -2.5) scale(1.05)">%s</g><text x="90" y="156.2" text-anchor="middle" font-family="Baydaq Black, Alexandria, sans-serif" font-weight="900" font-size="62" fill="%s">بيدق</text><rect x="29.4" y="192.8" width="16" height="2.4" rx="1.2" fill="%s"/><rect x="134.6" y="192.8" width="16" height="2.4" rx="1.2" fill="%s"/><text x="90" y="199.3" text-anchor="middle" font-family="Baydaq Light, Alexandria, sans-serif" font-weight="300" font-size="18" fill="%s">تكنولوجي</text></svg>\n' \
    "$(fonts)" "$(mark "$2" "$3")" "$2" "$5" "$5" "$4" > "$OUT/$1"
}

svg_mark        baydaq-mark.svg        "$WALNUT" "$BRASS"
svg_mark        baydaq-mark-light.svg  "$IVORY"  "$BRASS_BRIGHT"
svg_mark        baydaq-mark-mono.svg   "$WALNUT" "$WALNUT"
svg_app         baydaq-app-icon.svg
cp "$OUT/baydaq-app-icon.svg" "$OUT/favicon.svg"
svg_horizontal  baydaq-logo-horizontal.svg        "$WALNUT" "$BRASS"        "$MUTED"                     "$BRASS"
svg_horizontal  baydaq-logo-horizontal-light.svg  "$IVORY"  "$BRASS_BRIGHT" "rgba(246,235,214,.72)"      "$BRASS_BRIGHT"
svg_stacked     baydaq-logo-stacked.svg           "$WALNUT" "$BRASS"        "$MUTED"                     "$BRASS"
svg_stacked     baydaq-logo-stacked-light.svg     "$IVORY"  "$BRASS_BRIGHT" "rgba(246,235,214,.72)"      "$BRASS_BRIGHT"
echo "built:"; ls -la "$OUT"
