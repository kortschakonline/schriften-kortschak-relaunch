# 16 · Winter-Hero „Sichtbar“

Die Seite selbst liegt in `../10-apple-motion/winter/` und fährt mit dem normalen Website-Deploy mit (live: www.schriften-kortschak.at/winter/, noindex).

Hier liegen nur die Quellen:
- `quelle/` – Magnific-Videos (Seedance 2.5) und die Kondenswasser-Textur
- `encode.sh` – Video → AVIF-Sequenz nach `../10-apple-motion/winter/seq/`
  (`sh encode.sh quelle/video-1_sonnenaufgang-dolly.mp4 144`)

Debug: `?p=0.5` friert den Scroll-Fortschritt ein.
