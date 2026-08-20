#!/bin/sh
# Baut das Deploy-Paket fuer view.kortschak.online.
#
#   sh deploy/paket-bauen.sh            -> Ordner + ZIP in /tmp
#   sh deploy/paket-bauen.sh /pfad/ziel -> eigener Zielordner
#
# ACHTUNG: Der Hostinger-Deploy ersetzt den KOMPLETTEN Webroot. Darum muss
# hier immer die ganze Site samt der Entwurfs-Buehnen 11-14 hinein, nicht nur
# die geaenderte Seite. Was fehlt, ist danach offline.
set -e
cd "$(dirname "$0")/.."
ZIEL="${1:-/tmp/kortschak-deploy}"
rm -rf "$ZIEL"; mkdir -p "$ZIEL"

cd designs

# Wurzel = Design 10, ohne Rohmaterial, Archiv, Backups und Arbeitsnotizen
rsync -a \
  --exclude 'assets/Fotos-Lama/' \
  --exclude '_archiv/' \
  --exclude 'hero-c/' \
  --exclude '*.bak-*' \
  --exclude '*.md' \
  --exclude '.DS_Store' \
  10-apple-motion/ "$ZIEL/"

# Entwurfs-Buehnen daneben (waren vor 08/2026 schon live)
for d in 11-scroll-hero 12-scroll-hero 13-foil-hero; do
  rsync -a --exclude '.DS_Store' "$d" "$ZIEL/"
done

# Entwurf 14 ohne die Photoshop-Quellen (116 MB, gehoeren nicht ins Netz)
rsync -a --exclude '.DS_Store' \
  --exclude 'iris-eye-isolated/' --exclude 'iris-eye-isolated-2/' \
  --exclude 'INTEGRATION.md' \
  14-iris-hero "$ZIEL/"

find "$ZIEL" -name '.DS_Store' -delete

ZIP="$(cd "$(dirname "$ZIEL")" && pwd)/view-kortschak_$(date +%Y%m%d_%H%M%S).zip"
( cd "$ZIEL" && zip -rq "$ZIP" . )

echo "Paket:  $ZIEL"
echo "Archiv: $ZIP"
du -sh "$ZIEL" "$ZIP"
echo
echo "Naechster Schritt: Hostinger-MCP hosting_deployStaticWebsite"
echo "  domain      = view.kortschak.online"
echo "  archivePath = $ZIP"
