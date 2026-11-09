#!/bin/sh
# Video -> AVIF-Bildsequenz (desktop + mobile) + Poster + Manifest
# Aufruf: sh encode.sh quelle/video-1_sonnenaufgang-dolly.mp4 [frames]
set -e
HIER="$(cd "$(dirname "$0")" && pwd)"; SRC="$1"; N="${2:-144}"  # Ausgabe: ../10-apple-motion/winter/seq
TMP="$(mktemp -d)"; ZIEL="$HIER/../10-apple-motion/winter/seq"
rm -rf "$ZIEL"; mkdir -p "$ZIEL/desktop" "$ZIEL/mobile"
DUR=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$SRC")
FPS=$(echo "$N / $DUR" | bc -l)
ffmpeg -loglevel error -i "$SRC" -vf "fps=$FPS,scale=1920:-2:flags=lanczos" -frames:v "$N" "$TMP/%04d.png"
for f in "$TMP"/*.png; do
  b=$(basename "$f" .png)
  avifenc -q 60 -s 6 -j all --yuv 420 "$f" "$ZIEL/desktop/$b.avif" >/dev/null
  ffmpeg -loglevel error -y -i "$f" -vf scale=1280:-2:flags=lanczos "$TMP/m.png"
  avifenc -q 56 -s 6 -j all --yuv 420 "$TMP/m.png" "$ZIEL/mobile/$b.avif" >/dev/null
done
n=$(ls "$ZIEL/desktop" | wc -l | tr -d ' ')
first=$(ls "$TMP"/0*.png | head -1); last=$(ls "$TMP"/0*.png | tail -1)
ffmpeg -loglevel error -y -i "$first" -q:v 4 "$ZIEL/poster-start.jpg"
ffmpeg -loglevel error -y -i "$last" -q:v 3 "$ZIEL/poster-ende.jpg"
W=$(ffprobe -v error -select_streams v -show_entries stream=width,height -of csv=p=0 "$first")
cat > "$ZIEL/manifest.json" <<JSON
{ "asset": "winter-sichtbar-video", "ext": "avif", "quelle": "$(basename "$SRC") (Magnific, Seedance 2.5)",
  "renditions": { "desktop": { "path": "desktop/", "frames": $n, "size": "1920x1072" },
                  "mobile":  { "path": "mobile/",  "frames": $n, "size": "1280x714" } } }
JSON
rm -rf "$TMP"; du -sh "$ZIEL"
