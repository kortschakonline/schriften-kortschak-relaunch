# Entwurf 14 — Iris-Hero (SICHTBAR glüht)

> **Status 19.08.2026 spät: INTEGRIERT.** Der Hero ist in die Startseite
> `../10-apple-motion/index.html` übernommen (heroB, Sequenz unter
> `assets/seq/iris/`) und live auf view.kortschak.online. Dieser Ordner
> bleibt als Referenz-Prototyp. Änderungen der Feedback-Runden: Neon-Kontur
> geblurrt, Kreis-Füllung mit weicher mask-image-Feder statt clip-path
> (Safari zeigte mit clip-path + background-clip:text eine Scheibe statt
> Buchstaben), Strahlen-Schein läuft hinter der Füllkante mit.

Stand 19.08.2026. Jörns Idee vom 19.08.: SICHTBAR bleibt (erst dünne Outline),
dahinter zoomt eine Iris — erst Schwarz-Weiß, dann bricht die Farbe ein, und
wenn die Iris beschnitten wird, glüht sie rot-orange von innen. SICHTBAR ist
bis dahin ebenfalls in Farbe.

## Was der Hero macht (Scroll-Fortschritt p über 420vh Track)

| Phase | Verhalten |
|---|---|
| 0,00–0,16 | Schwarz-Weiß (Canvas-Entsättigung via `saturation`-Blend), Iris kommt winzig aus dem Dunkel; SICHTBAR. als dünne Outline (Anton, 1,2 px), darüber „WIR MACHEN SIE" |
| 0,16–0,38 | Farbe bricht ein (Entsättigung läuft gegen 0), Zoom läuft weiter |
| 0,50–0,78 | SICHTBAR füllt sich **kreisförmig aus der Pupille** mit dem Verlauf aus Entwurf 12 (#FF3B3E → #e0181c → #F18700) |
| 0,62–0,92 | Glut-Overlay (radial, `mix-blend-mode:screen`) verstärkt das Rot-Orange-Glühen der Iris; ab 0,74 zündet eine geblurrte Glüh-Ebene hinter dem Wort |
| 0,80–0,94 | **Finale-Rücknahme** (Feedback 19.08. abends): die Iris verliert live auf dem Canvas Sättigung (−38 %) und Licht (−22 %), Glut fährt mit zurück — Wort und Iris tragen denselben Verlauf, ohne Rücknahme wäre es Rot auf Rot |
| 0,86 | **Neon-Kontur zündet** ums Wort (helle 1,6-px-Linie + warmer Glow, kurzes Leuchtstoffröhren-Flackern per einmaliger CSS-Animation, Hysterese: aus erst unter 0,82) — Leuchtreklamen-Anspielung |
| 0,88 | Sequenz-Ende — das letzte (glühende) Frame steht |
| 0,90–1,00 | Subline + CTAs faden ein |

## Assets & Regenerierung

- Quelle: `iris-eye-isolated-2/Iris-Zoom-01.mp4` (1920×1080, 24 fps, 8 s, 192 Frames).
  `Iris-Zoom-02.mp4` (schwebende Augenkugel) ist die verworfene Alternative.
- Sequenz neu bauen:

  ```bash
  ffmpeg -i Iris-Zoom-01.mp4 -vsync 0 png/%04d.png
  # Desktop: 192 Frames, 1600×900
  ffmpeg -i png/0001.png -vf scale=1600:900 d/0001.png   # … alle Frames
  avifenc -q 55 --speed 6 d/0001.png seq/desktop/0001.avif
  # Mobile: jedes 2. Frame, Center-Crop 810×1080 (3:4)
  ffmpeg -i png/0001.png -vf "crop=810:1080:555:0" m/0001.png
  avifenc -q 55 --speed 6 m/0001.png seq/mobile/0001.avif
  ```

- Gewichte: Desktop 12 MB (192 × AVIF q55), Mobile 3,9 MB (96 Frames).
- Renditionswahl: **Portrait → Mobile** (3:4-Crop), sonst Desktop — erst nach
  echtem Layout entschieden (Viewport kann beim Skriptstart 0 sein).

## Debug

`?p=0.65` an die URL hängen friert den Scrollzustand ein (Stage wird fixed) —
praktisch für Screenshots und Feedback („schau dir p=0.55 an"). Auf der
echten Seite entfernen.

## Eingebaute Robustheit (Review-Runde 19.08., 4 Prüf-Lupen)

- **Poster-Fallback:** schlägt Manifest-Fetch, AVIF-Decode (Safari < 16.4,
  Feature-Detect per Mini-AVIF) oder jede Frame-Ladung fehl, bekommt die Stage
  `seq/poster.jpg` als Hintergrund — nie mehr stumm schwarz. Fehl-Frames
  zählen getrennt (`erfolge`), der Loader lügt nicht mehr.
- **WebKit-Falle umschifft:** `clip-path`/`filter` liegen auf Wrappern,
  `background-clip:text` auf den Kindern — sonst verliert Safari das Clipping
  des Verlaufs auf die Glyphen (Kern-Animation!). Trotzdem vor Kundentermin
  einmal am echten iPhone gegentesten.
- **Reduced Motion:** lädt nur noch das Endframe (statt 12 MB), zeichnet nach
  Resize/Rotation neu; CTAs sind dort sofort sichtbar und bedienbar.
- **Tastatur:** CTAs sind per `visibility` aus der Tab-Reihenfolge, solange
  sie unsichtbar sind.
- **Lesbarkeit:** Scrim-Verläufe oben/unten halten die Mono-Metadaten über
  der hellen Iris lesbar.
- **Leerlauf:** Dirty-Check — ohne Scroll/Nachladen wird nicht neu gezeichnet;
  Lerp ist zeitbasiert (gleiche Anmutung bei 60 und 120 Hz); Nachbarframes
  werden vordekodiert (`img.decode()`), damit der Scrub nicht stottert.

**Bewusst offen gelassen:** Rotation lädt die Rendition nicht um (Querformat
geladen → 16:9-Frames auch nach Drehung; Querformat-Phone lädt die 12-MB-
Desktop-Sequenz). Für den Entwurf okay, vor Live-Gang entscheiden.

## Offene Punkte vor Live-Gang

1. **Anton + IBM Plex Mono lokal hosten** (kein Google-Fonts-Request).
2. **Sequenz-Diät prüfen:** 12 MB Desktop ist im Rahmen des 15-MB-Ziels aus
   Entwurf 12, aber q50 oder 168 Frames wären drin, wenn nötig.
3. **Am echten iPhone gegentesten** (WebKit-Punkte oben, 100svh-Verhalten).
4. Übernahme in `kortschak-website/` (Branch `umbau-startseite-2026-08`)
   nach dem Muster aus `../12-scroll-hero/INTEGRATION.md`; die Demo-Zeile
   „Entwurf 14 · Iris-Hero", die „Prototyp-Ende"-Sektion und der ?p-Haken
   nicht übernehmen. Die Quellordner `iris-eye-isolated*/` (PSDs, ~100 MB)
   gehören nicht in ein Deploy.
