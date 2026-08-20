#!/usr/bin/env python3
"""Prueft alle im HTML referenzierten Assets gegen die Live-Site.
Aufruf:  python3 pruef_assets.py <paketordner> <basis-url>"""
import re, sys, glob, os, urllib.request

paket, basis = sys.argv[1], sys.argv[2].rstrip('/')
muster = r'(?:src|href|content)="(/[^"]+\.(?:webp|jpg|jpeg|png|svg|mp4|avif|woff2|json|css|js))"'
muster_css = r"url\('?(/[^')\"]+\.(?:webp|jpg|jpeg|png|svg|mp4|avif|woff2))'?\)"

refs = set()
for f in glob.glob(os.path.join(paket, '**', '*.html'), recursive=True):
    s = open(f, encoding='utf-8').read()
    refs.update(re.findall(muster, s))
    refs.update(re.findall(muster_css, s))

fehler = 0
for p in sorted(refs):
    lokal = os.path.join(paket, p.lstrip('/'))
    if not os.path.isfile(lokal):
        print('FEHLT LOKAL  ', p); fehler += 1; continue
    try:
        code = urllib.request.urlopen(basis + p, timeout=20).status
    except Exception as e:
        code = getattr(e, 'code', 'ERR')
    if code != 200:
        print('%-13s%s' % (code, p)); fehler += 1

print('%d Assets geprueft, %d fehlerhaft' % (len(refs), fehler))
sys.exit(1 if fehler else 0)
