# Prompt: BMW-Sequenz auf dem Mac Mini rendern

> Diesen Text 1:1 als Auftrag in eine Claude-Session auf dem Mac Mini geben
> (oder selbst als Checkliste abarbeiten). Er ist eigenständig — alles Nötige
> steht drin. Referenz-Doku im Repo: `designs/10-apple-motion/BLENDER-SPECS.md`
> (github.com/kortschakonline/schriften-kortschak-relaunch).

---

Du hilfst mir, auf diesem Mac Mini (Apple Silicon, Blender 4.2+) eine
**Cycles-Bildsequenz des BMW G21** für die Website „Design 10 Apple Motion" der
Kortschak Schriften GmbH zu produzieren. Es wird KEIN GLB exportiert — das
Ergebnis sind PNG-Frames mit Alpha, die im Browser scroll-gescrubbt werden.
Meine .blend-Datei ist `BMW-3D/BMW-Wrapped-2026.blend` — die Folierung ist
darin bereits fertig gebaut (Material `Livery Wrap 2026` auf dem Objekt
`Body Wrap`): werksschwarzer BMW → echte Kortschak/Brandl-Folierung, 1:1 nach
dem realen Fahrzeug. Der Wipe ist fertig geriggt: Empty `WRAP_Wipe` fährt mit
linearen Keyframes 95–135 durchs Auto; Master-Regler ist der Value-Node
`WIPE_Steuerung` im Material. NICHT neu bauen — nur verwenden.
(Doku: `BMW-3D/docs/specs/2026-07-22-bmw-folierung-design.md`.)

**Erzählung (eine Timeline, 150 Frames):**
1–90 Kamera fährt von der Dreiviertelfront zum Seitenprofil (mit kurzem
Verharren um Frame 40–55) · 40–55 Scheinwerfer glimmen auf (bewusst früh,
solange die Kamera noch frontal schaut) · 95–135 Folierung erscheint als
sichtbarer Wipe von vorn nach hinten (werksschwarz → Folierung) · 135–150
langsames Einrasten auf die Heldenpose.

## Erst M2 (Testrender!), dann Serie

**Bevor die 150 Frames laufen, brauchen wir eine Abnahme mit nur 3 Frames**
(Start ~1 / Licht an ~55 / Wrap fertig ~135):
- in **zwei Glas-Varianten**: (V1) Film → Transparent Glass ✓ mit Light Paths
  Total 12/Transmission 12 · (V2) getönte opake Scheiben mit Total 8/Transmission 8
- View Transform **Khronos PBR Neutral**; den Licht-Frame zusätzlich einmal in
  **AgX** zum Vergleich
- bei **256 / 512 / 1024 Samples** — und **miss die Renderzeit pro Frame**
  (daraus rechnen wir die Nachtplanung hoch: Zeit × 150)
- Frame ~55 einmal **mit und ohne Scheinwerfer**: Ist das Aufglimmen gegen das
  Studio-Licht deutlich sichtbar?
Diese Test-PNGs (+ Zeiten) schicke ich zur Abnahme. **Erst danach** Settings
einfrieren und die Serie über Nacht rendern.

## Szenen-Setup

**Welt & Licht (fixieren und notieren!):**
- Neutrales Studio-HDRI, z. B. Poly Haven `studio_small_08` (CC0), Strength
  0,5–1,0; Rotation so, dass der Hauptreflex auf der kamerazugewandten Flanke
  liegt. **Gewählte Strength/Rotation in eine settings.txt schreiben** —
  Testrender und Serie müssen identisch beleuchtet sein.
- Zusätzlich 1–2 **lange schmale Area-Lights** längs über der Karosserie
  (Softbox-Reflexstreifen — die machen den Autolack).
- Ambient nicht so hell, dass der Scheinwerfer-Beat untergeht.

**Kamera:** Bezier-Kurve ums Auto → Follow-Path-Constraint, im Constraint
**„Fixed Position" aktivieren**, dann Offset Factor (0–1) keyframen (Ease im
Graph Editor). Track auf ein Empty im Wagenzentrum (Fensterlinie); das Empty
darf zum Finale leicht zur Front wandern. 50–85 mm, DoF höchstens minimal (f/5.6+).

**Scheinwerfer:** Emission Strength der Leuchtmittel keyframen (0 → Ziel mit
leichtem Überschwingen), plus 1–2 reale Spots davor (Power keyframen), damit
Lichtkegel am Boden sichtbar werden.

**Folierungs-Wipe: FERTIG GEBAUT — nichts neu aufbauen.** Eine Shader-Instanz,
der Wipe steuert die Deckkraft der Beschriftungsebenen (kein Mix Shader — beide
Zustände teilen denselben Lack, so bleiben die Reflexe beim Scrubben stabil).
Empty `WRAP_Wipe` ist 95–135 linear gekeyframed; `WIPE_Steuerung` (Value-Node)
ist der manuelle Master-Regler. Falls sich die Wipe-Frames verschieben: nur die
zwei Empty-Keyframes verschieben und die Marken im Manifest nachziehen.
⚠️ Persistent Data cached Texturen: solange Textur-PNGs geändert werden,
Persistent Data AUS; erst für den Serienrender wieder AN.

**Boden/Schatten (wichtig — die Website hat Dark Mode):**
- Plane als **Shadow Catcher**; Film → Transparent ✓
- **View Layer → Passes → Light → Shadow Catcher ✓** und im Compositor zwei
  File Outputs: (1) Auto OHNE Schatten, (2) Schatten-Pass als eigene
  Graustufen-Sequenz (gleiche Frame-Nummern). Die Website legt den Schatten nur
  im hellen Modus unters Auto; im dunklen ersetzt ihn ein Glow.
- Falls der Pass-Weg beim Test hakt: melden — dann entscheiden wir bewusst um.

**Render-Settings:**
- Cycles, GPU Compute = **Metal** (kein OptiX auf dem Mac); MetalRT aktivieren,
  falls verfügbar
- Sampling adaptiv, Noise Threshold 0.01, Max 512 (Wipe-Frames 1024);
  Denoise OpenImageDenoise High (Albedo+Normal); **Seed statisch (Uhr-Icon aus)**;
  Motion Blur AUS; Clamp Indirect 10, Filter Glossy 0.5;
  Performance → Persistent Data ✓
- Output: **PNG, RGBA, 16 bit**, `assetA_v01_####.png`, Frames 1–150,
  Auflösung **2000×1125** (wenn der Mac zu langsam ist: 1600×900 — vorher melden)

## Serie rendern (über Nacht, headless)

```bash
caffeinate -i "/Applications/Blender.app/Contents/MacOS/Blender" -b ~/pfad/zur/bmw.blend -a
```

(`-b` = ohne UI, `-a` = ganze Frame-Range; caffeinate verhindert Schlafmodus.
Realistisch auf Apple Silicon: je nach Chip 2–15 min/Frame — deshalb vorher die
M2-Zeitmessung; notfalls in 2–3 Nächte aufteilen, Blender rendert bei
vorhandenen Dateien einfach weiter, wenn „Overwrite" aus ist.)

## Nach dem Rendern: Web-Konvertierung

```bash
brew install libavif   # falls avifenc fehlt
mkdir -p web
for f in render/*.png; do
  avifenc -q 60 --qalpha 95 -d 10 -y 444 -s 4 -j all "$f" "web/$(basename "${f%.png}").avif"
done
```

Stichprobe prüfen: Alpha-Kanten (Fransen?), Silberlack (Banding?), ein Frame
auf weißem UND dunklem Grund. Gesamtgröße des web/-Ordners notieren
(Ziel: ~5–10 MB für 150 Frames).

## ✅ ERLEDIGT 2026-07-23 — Serie gerendert und übergeben

Die komplette Sequenz liegt in `BMW-3D/web/` (gesamt 12 MB):
`desktop/` 150×2000×1125 (8.9 MB) · `mobile/` 75×960×540 (1.9 MB) ·
`shadow/` 150×1000×563 RGBA-Maske (1.2 MB) · `manifest.json`.
PNG-16bit-Master in `BMW-3D/render/`, alle Settings + Begründungen in
`BMW-3D/M2/settings.txt`. **Neue Event-Marke `wrapVisible: [116,135]`** —
die Beschriftung erscheint auf der kamerazugewandten Flanke gemessen erst ab
Frame ~116, nicht bei Wipe-Start 95; Caption bitte daran koppeln, nicht an
`wrapWipe`. Schatten-Maske ist schwarz+Alpha (Light Mode: unters Auto legen;
Dark Mode: Frames sind schattenfrei, Glow aus derselben Silhouette).
Unten der ursprüngliche Abgabe-Plan zur Referenz.

## Abgabe (komplett übergeben)

1. Alle PNG-Master (Auto-Sequenz + Schatten-Sequenz) + settings.txt
   (HDRI-Werte, Glas-Variante, View Transform, Samples)
2. web/-Ordner mit den AVIF-Frames
3. `manifest.json`:

```json
{
  "asset": "bmw-g21", "version": "v01", "ext": "avif",
  "renditions": {
    "desktop": { "path": "desktop/", "width": 2000, "height": 1125, "frames": 150, "frameStep": 1 }
  },
  "shadow": { "path": "shadow/", "width": 2000, "height": 500, "frames": 150 },
  "events": { "lightsOn": [40, 55], "wrapWipe": [95, 135], "heroPose": 150 }
}
```

(Die `events`-Frames bitte an die TATSÄCHLICH gekeyframeten Marken anpassen —
die Website koppelt ihre Text-Einblendungen exakt daran. Mobile-Renditionen
erzeugt die Web-Seite aus den PNG-Mastern, kein zweiter Render nötig.)
