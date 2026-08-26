#!/usr/bin/env python3
"""Prueft alle referenzierten Assets gegen die Live-Site.

Zwei Quellen: (1) HTML-Attribute + CSS-url() wie bisher, (2) die
Frame-Sequenzen aus den manifest.json — die laedt das JS der Scroll-Buehnen
zur Laufzeit, sie stehen in keinem src-Attribut und waeren sonst unsichtbar
(1.100+ AVIF-Frames, der eigentliche Kern der Site).

Geprueft wird je Asset: liegt es im Paket, antwortet es live mit 200, und
stimmt der Content-Type (Hostinger lieferte AVIF als text/plain — harmlos
nur, solange nirgends nosniff gesetzt ist; Fix: .htaccess mit AddType).

Aufruf:  python3 pruef-assets.py <paketordner> <basis-url>"""
import re, sys, glob, os, json, urllib.request
from concurrent.futures import ThreadPoolExecutor
from collections import defaultdict

paket, basis = sys.argv[1], sys.argv[2].rstrip('/')
muster = r'(?:src|href|content)="(/[^"]+\.(?:webp|jpg|jpeg|png|svg|mp4|avif|woff2|json|css|js))"'
muster_css = r"url\('?(/[^')\"]+\.(?:webp|jpg|jpeg|png|svg|mp4|avif|woff2))'?\)"

refs = set()
for f in glob.glob(os.path.join(paket, '**', '*.html'), recursive=True):
    s = open(f, encoding='utf-8').read()
    refs.update(re.findall(muster, s))
    refs.update(re.findall(muster_css, s))

# Frame-Sequenzen + Poster aus den Manifesten
for mf_pfad in glob.glob(os.path.join(paket, '**', 'manifest.json'), recursive=True):
    mf = json.load(open(mf_pfad, encoding='utf-8'))
    seq_url = '/' + os.path.relpath(os.path.dirname(mf_pfad), paket).replace(os.sep, '/') + '/'
    refs.add(seq_url + 'manifest.json')
    for pf in glob.glob(os.path.join(os.path.dirname(mf_pfad), 'poster.*')):
        refs.add(seq_url + os.path.basename(pf))
    bloecke = list(mf.get('renditions', {}).values())
    if 'shadow' in mf:
        bloecke.append(mf['shadow'])
    for r in bloecke:
        for i in range(1, int(r['frames']) + 1):
            refs.add('%s%s%04d.%s' % (seq_url, r['path'], i, mf.get('ext', 'avif')))

ERWARTET = {  # Endung -> erlaubte Content-Types
    'avif': ('image/avif',), 'webp': ('image/webp',), 'jpg': ('image/jpeg',),
    'jpeg': ('image/jpeg',), 'png': ('image/png',), 'svg': ('image/svg+xml',),
    'mp4': ('video/mp4',), 'woff2': ('font/woff2', 'application/font-woff2'),
    'json': ('application/json',), 'css': ('text/css',),
    'js': ('application/javascript', 'text/javascript'),
}

def pruefe(p):
    """None = in Ordnung, sonst (pfad, problem, detail)."""
    if not os.path.isfile(os.path.join(paket, p.lstrip('/'))):
        return (p, 'FEHLT LOKAL', '')
    for versuch in (1, 2):  # bei ~2000 parallelen HEADs faellt vereinzelt einer um
        try:
            antw = urllib.request.urlopen(
                urllib.request.Request(basis + p, method='HEAD'), timeout=20)
            code = antw.status
            ctype = antw.headers.get('Content-Type', '').split(';')[0].strip()
            break
        except Exception as e:
            if versuch == 2:
                return (p, str(getattr(e, 'code', 'ERR')), '')
    if code != 200:
        return (p, str(code), '')
    soll = ERWARTET.get(p.rsplit('.', 1)[-1].lower())
    if soll and ctype not in soll:
        return (p, 'MIME', '%s statt %s' % (ctype or 'leer', soll[0]))
    return None

with ThreadPoolExecutor(max_workers=12) as pool:
    probleme = [r for r in pool.map(pruefe, sorted(refs)) if r]

# Sammelmeldung je Ordner + Problemart statt hunderter Einzelzeilen
gruppen = defaultdict(list)
for p, art, detail in probleme:
    gruppen[(os.path.dirname(p) + '/', art, detail)].append(p)
for (ordner, art, detail), liste in sorted(gruppen.items()):
    zusatz = ' (%s)' % detail if detail else ''
    if len(liste) == 1:
        print('%-12s %s%s' % (art, liste[0], zusatz))
    else:
        print('%-12s %4d Dateien unter %s%s' % (art, len(liste), ordner, zusatz))

print('%d Assets geprueft, %d fehlerhaft' % (len(refs), len(probleme)))
sys.exit(1 if probleme else 0)
