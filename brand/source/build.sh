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
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="512" height="512"><title>بيدق تك</title>%s</svg>\n' "$(mark "$2" "$3")" > "$OUT/$1"
}

svg_app() { # file
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="512" height="512"><title>بيدق تك</title><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4A2E1A"/><stop offset="1" stop-color="#2A190D"/></linearGradient></defs><rect width="100" height="100" rx="22" fill="url(#g)"/><g transform="translate(50 50) scale(.66) translate(-50 -50)">%s</g></svg>\n' "$(mark "$IVORY" "$BRASS_BRIGHT")" > "$OUT/$1"
}

# The wordmark is «بيدق تك» in one weight, as in the site header.
svg_horizontal() { # file, word colour, head colour
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s 120" width="%s" height="480"><title>بيدق تك</title>%s<g transform="translate(%s 7) scale(1.05)">%s</g><text x="%s" y="%s" text-anchor="end" font-family="Baydaq Black, Alexandria, sans-serif" font-weight="900" font-size="62" fill="%s">بيدق تك</text></svg>\n' \
    "$H_W" "$((H_W * 4))" "$(fonts)" "$H_MARK_X" "$(mark "$2" "$3")" "$H_TEXT_X" "$H_TEXT_Y" "$2" > "$OUT/$1"
}

svg_stacked() { # file, word colour, head colour
  printf '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %s" width="%s" height="%s"><title>بيدق تك</title>%s<g transform="translate(%s -2.5) scale(1.05)">%s</g><text x="%s" y="%s" text-anchor="middle" font-family="Baydaq Black, Alexandria, sans-serif" font-weight="900" font-size="%s" fill="%s">بيدق تك</text></svg>\n' \
    "$S_W" "$S_H" "$((S_W * 4))" "$((S_H * 4))" "$(fonts)" "$(echo "$S_W" | awk '{print $1/2 - 52.5}')" "$(mark "$2" "$3")" "$((S_W / 2))" "$S_TEXT_Y" "$S_SIZE" "$2" > "$OUT/$1"
}

svg_mark        baydaq-mark.svg        "$WALNUT" "$BRASS"
svg_mark        baydaq-mark-light.svg  "$IVORY"  "$BRASS_BRIGHT"
svg_mark        baydaq-mark-mono.svg   "$WALNUT" "$WALNUT"
svg_app         baydaq-app-icon.svg
cp "$OUT/baydaq-app-icon.svg" "$OUT/favicon.svg"
# Wordmark layout (viewBox units; the mark is drawn at 1.05×, so its ink is 63 wide).
# Horizontal: mark on the right, text ending just left of it, centred on the mark.
H_W=358; H_MARK_X=266; H_TEXT_X=276; H_TEXT_Y=74
# Stacked: mark on top, the name centred under it.
S_W=246; S_H=184; S_TEXT_Y=156; S_SIZE=52

svg_horizontal  baydaq-logo-horizontal.svg        "$WALNUT" "$BRASS"
svg_horizontal  baydaq-logo-horizontal-light.svg  "$IVORY"  "$BRASS_BRIGHT"
svg_stacked     baydaq-logo-stacked.svg           "$WALNUT" "$BRASS"
svg_stacked     baydaq-logo-stacked-light.svg     "$IVORY"  "$BRASS_BRIGHT"
echo "built:"; ls -la "$OUT"
