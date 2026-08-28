#!/bin/sh
# Baut das PRODUKTIONS-Paket fuer schriften-kortschak.at (Go-Live 28.08.2026).
#
#   sh deploy/paket-produktion.sh            -> Ordner + ZIP in /tmp
#
# Unterschied zu paket-bauen.sh (Staging view.kortschak.online): NUR Design 10 —
# die Entwurfs-Buehnen 11-14 gehoeren nicht auf die Kundendomain.
# ACHTUNG: Der Hostinger-Deploy ersetzt den KOMPLETTEN Webroot.
set -e
cd "$(dirname "$0")/.."
ZIEL="${1:-/tmp/kortschak-produktion}"
rm -rf "$ZIEL"; mkdir -p "$ZIEL"

cd designs

rsync -a \
  --exclude 'assets/Fotos-Lama/' \
  --exclude '_archiv/' \
  --exclude 'hero-c/' \
  --exclude '*.bak-*' \
  --exclude '*.md' \
  --exclude '.DS_Store' \
  10-apple-motion/ "$ZIEL/"

find "$ZIEL" -name '.DS_Store' -delete

# Sicherheitsnetz: die Formular-Config mit echten Zugangsdaten MUSS drin sein
test -s "$ZIEL/api/anfrage-config.php" || { echo "FEHLER: api/anfrage-config.php fehlt!"; exit 1; }
grep -q "HIER-PASSWORT" "$ZIEL/api/anfrage-config.php" && { echo "FEHLER: Platzhalter-Passwort in anfrage-config.php!"; exit 1; }

ZIP="$(cd "$(dirname "$ZIEL")" && pwd)/schriften-kortschak_$(date +%Y%m%d_%H%M%S).zip"
( cd "$ZIEL" && zip -rq "$ZIP" . )

echo "Paket:  $ZIEL"
echo "Archiv: $ZIP"
du -sh "$ZIEL" "$ZIP"
echo
echo "Naechster Schritt: Hostinger-MCP hosting_deployStaticWebsite"
echo "  domain      = schriften-kortschak.at"
echo "  archivePath = $ZIP"
