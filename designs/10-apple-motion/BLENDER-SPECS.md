# Blender-Specs — Design 10 „Apple Motion"

> Abarbeitbare Render-Anleitung für die beiden Scroll-Assets.
> Kontext und Warum: [PLAN.md](PLAN.md). Basis: Blender 4.2+ LTS, Cycles GPU.
>
> **Grundprinzip: Es wird kein GLB exportiert.** Jedes Asset ist eine
> Cycles-gerenderte PNG-Sequenz mit Alpha, die zu AVIF-Frames konvertiert und im
> Browser scrollgesteuert auf ein Canvas gezeichnet wird. Frames sind
> Scroll-Schritte, keine 24-fps-Logik.

---

## 0. Gemeinsame Settings (beide Assets)

### 0.1 Welt & Licht — EIN Setup für beide Assets

Beide Assets landen auf derselben Seite; unterschiedliches Licht wirkt sofort
inkohärent. Deshalb: **eine `studio.blend` mit World + Lights, in beide
Asset-Dateien gelinkt.**

- [x] **HDRI**: neutrales Studio-HDRI, z. B. Poly Haven **`studio_small_08`**
      (CC0, polyhaven.com) — Strength **0,5–1,0**, Rotation so, dass der
      Hauptreflex auf der kamerazugewandten Flanke liegt.
      **Gewählte Werte (Strength + Rotation) notieren** — sie gehören mit in die
      Abgabe (0.5), sonst sind Testrender und Serie nicht vergleichbar.

> **ERLEDIGT 2026-07-22 — gewählte Werte:** `studio_small_08` 4k, liegt als
> `BMW-3D/hdri/studio_small_08_4k.exr` im Projekt (das Poly-Haven-Addon legt
> es sonst nur als Temp-Datei ab → Serienrender scheitert).
> **Strength 0,6 · Rotation 240° (Z)**, World `SEQ_World_studio_small_08`.
>
> Rotation wurde über die *ganze Fahrt* gewählt, nicht am Einzelframe: 300°
> war an Frame 120 minimal heller, brach aber bei Frame 1/60 auf 38–39 %
> Schwarzanteil ein; 240° liegt ab Frame 60 konstant bei ~15 %. Bei einer
> vor- und zurückscrubbbaren Sequenz zählt Konstanz mehr als der beste
> Einzelframe.
>
> **Warum das nötig war:** Die Welt der .blend war reines Schwarz — 34–65 %
> der Fahrzeugpixel lagen unter 5 % Helligkeit, Unterbau und Stoßfänger
> abgesoffen; Frame 1 (das Hero-Standbild!) war der schlechteste.
> Vollständige Messwerte: `BMW-3D/M2/settings.txt`.
- [ ] **Reflexstreifen fürs Auto**: zusätzlich 1–2 **lange, schmale Area-Lights**
      längs über der Karosserie (Softbox-Streifen) — bei Autolack macht dieser
      durchgehende Reflexstreifen den Look, das HDRI allein reicht nicht.
- [ ] **Ambient-Niveau vs. Scheinwerfer-Beat**: Das Studio darf nicht so hell
      sein, dass das Aufglimmen der Scheinwerfer unsichtbar wird — das ist ein
      Haupt-Beat der Seite. Wird in M2 explizit geprüft (Frame ~55 mit Licht
      an/aus nebeneinander). Lieber Beat/Licht anpassen als das HDRI mitten in
      der Sequenz zu dimmen.

### 0.2 Render Properties

- [ ] Engine: Cycles, GPU Compute — **OptiX bei NVIDIA** (~25–35 % schneller als
      CUDA); auf Apple Silicon Metal, AMD HIP, Intel oneAPI. Die
      Renderzeit-Schätzungen unten gelten für NVIDIA-High-End.
- [ ] Film → **Transparent** ✓
- [ ] Sampling: Adaptive ✓, Noise Threshold 0.01, Max Samples **512**
      (Glitzer-/Haut-Nahaufnahmen: 1024)
- [ ] Denoise: **OpenImageDenoise**, Quality High, Passes Albedo+Normal
- [ ] **Seed: statisch lassen** (Uhr-Icon AUS) — animiertes Rauschen flackert beim
      Scrubben, statisches Rauschen + Denoiser ist ruhig
- [ ] **Motion Blur: AUS** (Scrub = Standbilder)
- [ ] Light Paths — **Total deckelt alle Einzel-Bounces**, daher je nach
      Glas-Variante (→ 1.5): Variante 1 (Durchblick): **Total 12, Transmission 12**;
      Variante 2 (opake Scheiben): **Total 8, Transmission 8**. Immer: Volume 0,
      Clamp Indirect 10, Filter Glossy 0.5
- [ ] Performance → **Persistent Data ✓** (spart Szenen-Upload pro Frame)

### 0.3 Color Management

- [ ] View Transform: **Khronos PBR Neutral** (seit 4.2 eingebaut) — sRGB-Farben
      kommen 1:1 an, Markenrot bleibt Markenrot
- [ ] Look: None. **Nie mitten in einer Sequenz den View Transform wechseln.**
- [ ] Einmalig in M2: denselben Scheinwerfer-Frame zusätzlich mit **AgX** rendern
      und vergleichen (schönerer Highlight-Rolloff, aber entsättigt kräftige
      Farben). Entscheidung dokumentieren, dann fix.

### 0.4 Boden & Schatten — separater Schatten-Pass (wichtig für Dark Mode)

> **ERLEDIGT 2026-07-22 — der Fallback (letzter Punkt unten) greift.**
> Der File-Output-Node des neuen 5.x-Group-Compositors schreibt in Blender 5.2
> **keine Dateien** (Node angelegt, verlinkt, `use_compositing` an — es entsteht
> nichts; auch nicht bei `render(animation=True)`). Statt den Schatten
> einzubacken wurde ein **Zwei-Modus-Rendering** gebaut, das den Pass-Weg
> vollwertig ersetzt: `BMW-3D/render/render_modes.py`
> * `-- auto` → Auto ohne Schatten (Master-PNGs)
> * `-- schatten` → Shadow-Catcher sichtbar, alle 8 Fahrzeug-Objekte auf
>   `is_holdout` → reine Schattensequenz mit Alpha, ~1/3 Renderzeit
>
> Dark Mode bleibt damit wie geplant bedienbar. Befund zur Abnahme: Der
> Schatten ist im Studio-Setup sehr weich (Peak-Alpha ~14 %) — ggf. im
> Frontend verstärken oder im Schatten-Modus ein hartes Zusatzlicht setzen.

Die Website ist Dual-Mode. Ein in die Frames eingebackener Schatten würde im
Dark Mode den CSS-Boden-Glow als dunklen Fleck überlagern. Deshalb:

- [ ] Plane unter dem Objekt → Object Properties → Visibility → **Shadow Catcher** ✓
- [ ] **View Layer → Passes → Light → Shadow Catcher ✓** — der Schatten kommt als
      eigener Pass
- [ ] Im Compositor **zwei File Outputs**: (1) Objekt ohne Schatten,
      (2) Schatten-Pass als eigene Graustufen-Sequenz (gleiche Frame-Nummern,
      darf deutlich kleiner/stärker komprimiert sein — er ist eh weich)
- [ ] Das Zusammenspiel (Objekt + Schatten auf hellem Grund, Objekt + Glow auf
      dunklem Grund) wird in **M2 auf der Testseite verifiziert**, bevor die Serie
      läuft
- [ ] *Fallback, falls der Pass-Weg im Test hakt:* Schatten doch einbacken und den
      Dark-Mode-Kompromiss (leicht abgedunkelter Glow unterm Auto) in M2 bewusst
      abnehmen — dann aber Entscheidung VOR dem Serienrender

### 0.5 Output

- [ ] Format: **PNG, RGBA, Color Depth 16 bit**, Compression 15 %
- [ ] Namensschema: `assetA_v01_####.png` bzw. `assetB_plain_v01_####.png` /
      `assetB_brand_v01_####.png` (vierstellig, ab 0001, lückenlos; beide
      B-Stapel **identisch nummeriert**)
- [ ] Nur EINE Master-Auflösung rendern — **die PNG-Master werden komplett an
      Claude übergeben** (Projektlaufwerk/Repo), der daraus die Mobile-Rendition
      ableitet (jeder 2. Frame, halbe Kantenlänge). Kein Re-Render für Mobile,
      kein Downscale aus AVIF (Qualitätsverlust)
- [ ] HDRI-Strength/-Rotation + gewählte Glas-Variante + View Transform in einer
      kleinen `settings.txt` neben die Master legen

---

## 1. Asset A — BMW G21

**Erzählung (eine Timeline, 150 Frames):** Kamera umfährt das Auto von der
Dreiviertelfront zum Seitenprofil → Scheinwerfer glimmen auf, **solange die Kamera
noch frontal schaut** → Folierung wandert als Wipe über die Karosserie
(**werksschwarzer BMW → echte Kortschak/Brandl-Folierung**, 1:1 nach dem realen
Fahrzeug MT 881 DO) → langsames Einrasten auf die Heldenpose.

> **Änderung 2026-07-22:** Die alte Wipe-Erzählung „Silber ‚plain' → Glitzer-Wrap
> ‚dots'" stammte aus der Live-WebGL-Ära (Design 06/08, `bmw-g21-plain.glb` /
> `-dots3.glb`) und ist ersetzt. Umsetzung + Begründung:
> `BMW-3D/docs/specs/2026-07-22-bmw-folierung-design.md`.

### 1.1 Timeline-Layout (Frames 1–150)

| Frames | Ereignis |
|---|---|
| 1–90 | Kamerafahrt Dreiviertelfront → Seitenprofil (Ease-in/out im Graph Editor); **um Frame 40–55 verharrt die Fahrt kurz** (Ease-Plateau) — dort sitzt der Licht-Beat |
| 40–55 | **Scheinwerfer an**: Emission Strength 0 → Ziel mit leichtem Überschwingen („Aufglimmen"). Bewusst früh gelegt: bei Frame 60+ schaut die Kamera schon fast aufs Seitenprofil und der Beat wäre kaum sichtbar |
| 95–135 | **Folierungs-Wipe** unfoliert (werksschwarz) → volle Folierung, wandert von vorn nach hinten |
| 135–150 | Kamera rastet langsam auf die Heldenpose ein (fast stehend — das Frontend mappt diesen Bereich auf ein langes Scroll-Stück) |

Frame-Marken sind Vorschläge — **wichtig ist nur, dass die tatsächlichen Marken im
Manifest stehen** (Abschnitt 3); das Frontend koppelt die Text-Beats daran.

### 1.2 Kamera

- Bezier-Kurve um das Auto → Kamera mit **Follow Path Constraint**; im Constraint
  **„Fixed Position" ✓ aktivieren**, dann **Offset Factor (0–1) keyframen** —
  ohne Fixed Position ist der Offset Factor wirkungslos (frame-basierter Offset
  greift stattdessen)
- **Empty im Wagenzentrum** (Höhe Fensterlinie) → Kamera Track-To/Damped-Track.
  Das Empty darf zum Finale leicht Richtung Front wandern
- Brennweite 50–85 mm; DoF höchstens minimal (f/5.6+) — starke Unschärfe wirkt
  beim Scroll-Stopp matschig

### 1.3 Scheinwerfer

- Emission-Shader der Leuchtmittel: **Emission Strength keyframen**
  (Shader-Editor → Rechtsklick auf den Wert → Insert Keyframe)
- Zusätzlich 1–2 reale Spots vor den Scheinwerfern, Power ebenfalls gekeyframed —
  Emission allein wirft bei Studio-Licht kaum sichtbare Kegel auf den Boden
- Strength moderat (PBR Neutral fängt Spitzen ab; Glow lieber dezent im Frontend
  als übertrieben im Render); Sichtbarkeits-Check gegen das Ambient-Niveau in M2

### 1.4 Folierungs-Wipe (kein Objekt-Tausch!) — UMGESETZT, abweichend

**Stand 2026-07-22, in `BMW-Wrapped-2026.blend` fertig gebaut.** Abweichung vom
ursprünglichen Mix-Shader-Plan, weil beide Wipe-Zustände denselben Lack teilen
(es erscheint nur die Beschriftung/Teilfolierung auf dem Blech):

- **Eine Shader-Instanz** (Material `Livery Wrap 2026` auf `Body Wrap`); der
  Wipe steuert die **Deckkraft der Beschriftungsebenen**, nicht die Mischung
  zweier Shader. Halbe Shader-Kosten, und **garantiert keine Reflex-Sprünge**
  beim Scrubben — bei einer rückwärts-scrubbbaren Sequenz der kritische Punkt.
- Maskentechnik wie geplant: Texture Coordinate (Object → Empty `WRAP_Wipe`) →
  Längskoordinate → weiche Kante (0,3 m) → Faktor. Empty fährt von vorn nach
  hinten (**Keyframes 95–135, linear**); zusätzlich manueller Master-Regler
  (Value-Node `WIPE_Steuerung`, 0–1).
- Zweites Empty `WRAP_Projection` trägt die fünf planaren Projektionsebenen
  (links/rechts/oben/Heck/Front) der Folierungsgrafiken.
- Glitzer-Flakes sind Noise-Treiber → für die Wipe-Frames im Zweifel 1024 Samples

> ⚠️ **Persistent-Data-Falle (gilt für alle Testrender):** Bei
> `Performance → Persistent Data ✓` cached Cycles Texturen über Renders hinweg —
> ein `image.reload()` nach Änderung einer Textur-PNG wird **ignoriert**, es
> rendert der alte Stand. Während an Texturen gearbeitet wird: Persistent Data
> AUS. Für den Serienrender (unveränderte Texturen) wieder AN — dort ist es
> sicher und spart den Szenen-Upload pro Frame.

### 1.5 Glas — Entscheidung im Testrender (M2)

Zwei Varianten, beide einmal rendern und auf der Testseite (hell + dunkel)
vergleichen:

- **Variante 1: Film → Transparent Glass ✓** (Roughness Threshold 0.1 Default),
  Light Paths Total 12 / Transmission 12: echter Durchblick. Nebenwirkung:
  Glas-Reflexe werden halbtransparente helle Pixel — auf sehr hellem Grund können
  Scheiben „ausbleichen"; Scheiben leicht tönen (dunkle Transmission-Farbe)
  mildert das und ist beim G21 realistisch
- **Variante 2: Transparent Glass AUS, getönte opake Scheiben**, Light Paths
  Total 8 / Transmission 8: null Alpha-Probleme, bei dunkler Verglasung visuell
  kaum Unterschied — der Robustheits-Default

### 1.6 Auflösung & Framezahl

- **Master: 2000×1125 px, 150 Frames** (Auto quer; 2× der größten
  Darstellungsbreite auf Desktops)
- Mobile macht Claude aus den PNG-Mastern: jeder 2. Frame, 960×540

### 1.7 Renderzeit-Realität (NVIDIA-High-End)

- Auto + Glas + Studio-Setup, 2000 px, 512 adaptive Samples, OIDN:
  **~1–4 min/Frame** (Glitzer-Nahaufnahmen bis 6) ⇒ 150 Frames ≈
  **3–10 GPU-Stunden**, über Nacht machbar
- Vorher IMMER das M2-Protokoll (Abschnitt 4) — erst danach Settings einfrieren

---

## 2. Asset B — Arbeiter mit Kleidungswechsel

**Erzählung:** normal gekleideter Arbeiter → bekommt im Scroll
Kortschak-Arbeitskleidung (bzw. Shirt mit Logo) → Kamera geht ans Logo.

### 2.1 Figur-Beschaffung (Empfehlung fett)

| Option | Kosten | Aufwand | Qualität | Urteil |
|---|---|---|---|---|
| **Human Generator V4 (humgen3d.com), Commercial-Tier $128** | ~115 € | Stunden | hoch | **Empfehlung** — nativ Blender, geriggt, Kleidungs-/Posensystem. **Achtung: Personal-Tier ($68) deckt Agentur-/Kundenarbeit NICHT ab**, Upgrade möglich |
| Kaufmodell (TurboSquid/CGTrader, geriggt) | 50–200 € | Stunden | je nach Asset | gute Abkürzung, wenn ein passender „Arbeiter" existiert; Lizenz prüfen |
| Character Creator 4 | 300 €+ | Tage | sehr hoch | Overkill für ein Asset |
| MakeHuman | frei | Tage | mittel | viel Shader-Nacharbeit für Realismus |
| Photogrammetrie-Scan (echter Mitarbeiter) | Studio | Tage+ | maximal + authentisch | nur wenn ihr eine reale Person zeigen wollt |
| Mixamo-Charaktere | frei | Minuten | Game-Look | **nicht** für diesen Anspruch; Mixamo nur als Posen-/Animations-Quelle |

Arbeitskleidung: HG-Kleidungsbibliothek als Basis; Logo als Image-Texture-Layer in
der Base Color des Shirts/der Jacke, leichtes Bump für Druck-Haptik.

### 2.2 Inszenierung des Wechsels — die Regeln

1. **Beide Outfits auf demselben Rig, derselben Pose, derselben Kamera.**
   Kamera zwischen beiden Szenen/Dateien **linken**, damit die zwei Sequenzen
   pixel-deckungsgleich sind — der Übergang (Masken-Sweep) passiert im Frontend
2. **Frame-Ranges:** `assetB_brand` voll **1–120**; `assetB_plain` nur **1–90**
   (Wechselfenster endet bei 85 + Sicherheitsmarge — ab dort ist der
   Plain-Stapel nie mehr sichtbar, auch nicht beim Rückwärts-Scrubben.
   Spart ~30 % Renderzeit)
3. Wo sich der Wechsel als „Shirt bleibt, Branding erscheint" erzählen lässt:
   **Shader-Wipe im Render** (Mix Shader + Gradient-Empty, identisch zur
   Folierung) — dann reicht EINE Sequenz
4. **Kein verdeckter Schnitt** (Wechsel während Drehung): beim Rückwärts-Scrubben
   steht der Nutzer exakt auf dem Schnitt-Frame und sieht den Sprung
5. **Keine Stoffsimulation** im Scrub-Bereich (Sim + Scrub = Flicker);
   Kleidung als gefittete Meshes, Feinschliff über Shape Keys
6. Bewegung sparsam: Turntable + 2–3 gehaltene Posen mit je 10–15
   Interpolations-Frames — Scroll-Scrub belohnt klare, stehende Silhouetten.
   Optional subtiles Atmen via Mixamo-Idle retargeten

### 2.3 Timeline & Render-Settings

- Timeline: **120 Frames** — 1–40 Eindrehen, 40–85 Wechselfenster
  (Frontend-Sweep), 85–120 Logo-Zoom
- Master-Auflösung: **1600×2000 px** (Figur hochkant); Mobile macht Claude:
  jeder 2. Frame, 720×900
- Skin: Principled mit Random-Walk-SSS; Haare als Curves (Principled Hair BSDF);
  Samples 512–1024 (Haut + Haare rauschen)
- Renderzeit: ~1–3 min/Frame ⇒ 120 + 90 Frames ≈ **4–10 GPU-Stunden**

---

## 3. Abgabe — was Claude von dir braucht

Pro Asset:

1. **PNG-Master-Sequenz(en)** 16 bit RGBA — **vollständig übergeben**
   (Projektlaufwerk/Repo; daraus entstehen Mobile-Renditionen und ggf.
   Nach-Encodings ohne Re-Render). Dazu die Schatten-Pass-Sequenz (0.4) und
   `settings.txt` (0.5)
2. **Web-Frames**: AVIF, erzeugt mit:

```bash
# AVIF — Farbe q60, Alpha fast lossless, 10 bit gegen Banding im Lack
for f in render/*.png; do
  avifenc -q 60 --qalpha 95 -d 10 -y 444 -s 4 -j all "$f" "web/$(basename "${f%.png}").avif"
done
```

   (`-q` nach Sichtprüfung 55–70; finale Abgabe gern mit `-s 2` statt `-s 4`.
   Kein animiertes AVIF/WebP, kein Video-Container — Scrub braucht wahlfreien
   Frame-Zugriff, und Safari hat dokumentierte Alpha-Bugs bei animiertem AVIF.
   WebP-Fallback ist **optional** — Browser ohne AVIF bekommen den statischen
   Fallback; falls doch gewünscht: `cwebp -q 82 -alpha_q 100 -m 6 -mt`.)

3. **Frame-Manifest** (JSON — daran koppelt das Frontend die Beats; Events zählen
   IMMER in Master-Frame-Nummern, Renditionen rechnet der Player um):

```json
{
  "asset": "bmw-g21",
  "version": "v01",
  "renditions": {
    "desktop": { "path": "desktop/", "width": 2000, "height": 1125, "frames": 150, "frameStep": 1 },
    "mobile":  { "path": "mobile/",  "width": 960,  "height": 540,  "frames": 75,  "frameStep": 2 }
  },
  "shadow": { "path": "shadow/", "width": 1000, "height": 300 },
  "events": { "lightsOn": [40, 55], "wrapWipe": [95, 135], "heroPose": 150 }
}
```

```json
{
  "asset": "arbeiter",
  "version": "v01",
  "sequences": {
    "plain": { "path": "plain/", "frames": 90 },
    "brand": { "path": "brand/", "frames": 120 }
  },
  "renditions": {
    "desktop": { "width": 1600, "height": 2000, "frameStep": 1 },
    "mobile":  { "width": 720,  "height": 900,  "frameStep": 2 }
  },
  "events": { "turnIn": [1, 40], "swapWindow": [40, 85], "logoZoom": [85, 120] }
}
```

4. Stichproben-Check vor Übergabe: Alpha-Kanten (Fransen?), Silberlack (Banding?),
   ein Frame auf weißem UND dunklem Grund angesehen, **Gesamtgröße der
   AVIF-Ordner notiert** (Budget-Abgleich: BMW 5–10 MB, Arbeiter 8–14 MB gesamt).

---

## 4. Testrender M2 (bevor irgendwas in Serie geht)

**Nur 3 Frames** des BMW — Start (1) / Scheinwerfer an (~55) / Wrap fertig (~135):

- in **beiden Glas-Varianten** (1.5)
- einmal **PBR Neutral**, den Scheinwerfer-Frame zusätzlich in **AgX**
- bei **256 / 512 / 1024 Samples** (Renderzeit notieren)
- Frame ~55 einmal **mit und ohne Scheinwerfer** — ist das Aufglimmen gegen das
  Studio-Ambient deutlich sichtbar?
- **mit Schatten-Pass** (0.4): Claude setzt die Frames auf die M1-Testseite —
  hell (Objekt + Schatten) und dunkel (Objekt + Glow)
- **Encoding-Size-Check**: die 3 Frames mit den Kommandos aus Abschnitt 3
  encodieren, ×150 hochrechnen, gegen das Budget halten (Glitzer-Frames sind
  die teuersten — reißt es, `-q` senken oder Budget bewusst anheben)

Erst nach dieser Abnahme werden die Serien-Settings eingefroren und die 150 Frames
über Nacht gerendert. Gleiches Protokoll danach für den Arbeiter (Testframes:
Pose frontal / Wechselfenster-Mitte / Logo-Zoom-Ende).
