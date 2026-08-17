# Prompt: Arbeiter-Sequenz auf dem Mac Mini erstellen & rendern

> Diesen Text 1:1 als Auftrag in eine Claude-Session auf dem Mac Mini geben
> (oder selbst als Checkliste abarbeiten). Eigenständig formuliert; Referenz:
> `designs/10-apple-motion/BLENDER-SPECS.md` im Repo
> (github.com/kortschakonline/schriften-kortschak-relaunch).

---

Du hilfst mir, auf diesem Mac Mini (Apple Silicon, Blender 4.2+) eine
**Cycles-Bildsequenz einer Arbeiter-Figur** für die Website „Design 10 Apple
Motion" der Kortschak Schriften GmbH zu produzieren. Story der Scroll-Bühne:
Ein normal gekleideter Arbeiter dreht sich leicht ein → seine Kleidung
verwandelt sich in Kortschak-Arbeitskleidung mit Logo → die Kamera zoomt ans
Logo auf der Brust. Der Verwandlungs-Übergang passiert NICHT im Render, sondern
auf der Website — deshalb brauchen wir **zwei pixel-deckungsgleiche Sequenzen**
derselben Szene (einmal Alltagsoutfit, einmal gebrandet).

## Schritt 0 — Figur beschaffen

Empfehlung: **Human Generator V4 (humgen3d.com), Commercial-Tier $128** —
nativ in Blender, geriggt, mit Kleidungs-/Posensystem. Achtung: das günstige
Personal-Tier ($68) deckt Agentur-/Kundenarbeit NICHT ab. Alternativen (geriggtes
Kaufmodell von TurboSquid/CGTrader 50–200 €) nur, wenn ein wirklich passender
„Arbeiter" existiert. KEINE Mixamo-Charaktere (Game-Look).
Arbeitskleidung: HG-Kleidungsbibliothek als Basis; Kortschak-Logo als
Image-Texture-Layer in der Base Color des Shirts (leichtes Bump für
Druck-Haptik). Markenrot: #FF1C20.

## Die eisernen Regeln (Scroll-Scrub verzeiht nichts)

1. **Beide Outfits auf demselben Rig, derselben Pose, derselben Kamera** —
   Kamera zwischen beiden Szenen/Dateien LINKEN. Die zwei Sequenzen müssen
   Frame für Frame deckungsgleich sein.
2. **Keine Stoffsimulation** (Sim + Scrub = Flicker). Kleidung als gefittete
   Meshes, Feinschliff über Shape Keys.
3. **Kein verdeckter Schnitt** — beim Rückwärts-Scrollen stünde der Nutzer
   genau auf dem Sprung.
4. Bewegung sparsam: eingedrehte Standpose → frontale Standpose reicht;
   Posen-Übergänge über 10–15 Frames mit Ease. Klare, stehende Silhouette.
5. **Seed statisch** (Uhr-Icon aus), Motion Blur AUS.

**Timeline (120 Frames):** 1–40 leicht eindrehen (~24° → frontal) ·
40–85 stillstehende Pose (hier macht die Website den Verwandlungs-Sweep) ·
85–120 Kamera zoomt ans Brustlogo.
**Frame-Ranges:** gebrandet (`assetB_brand`) voll **1–120**; Alltagsoutfit
(`assetB_plain`) nur **1–90** — ab dort ist es nie mehr sichtbar (~30 %
Renderzeit gespart).

## Szenen-Setup

- **Gleiches Welt-/Licht-Setup wie beim BMW-Asset** (gelinkte studio.blend:
  Poly-Haven-Studio-HDRI + Softbox-Streifen; Werte aus dessen settings.txt) —
  beide landen auf derselben Seite, das Licht muss zusammenpassen.
- Film → Transparent ✓; Boden-Plane als **Shadow Catcher**; wie beim BMW den
  **Shadow-Catcher-Pass separat** ausgeben (Figur ohne Schatten + Schatten-
  Sequenz einzeln — Dark-Mode-Thema der Website).
- Skin: Principled mit Random-Walk-SSS; Haare als Curves (Principled Hair BSDF).
- Cycles GPU = **Metal**, adaptiv, Noise Threshold 0.01, Max 512 (Haut/Haar-
  Nahaufnahmen 1024), OIDN High (Albedo+Normal), Persistent Data ✓.
- Output: **PNG, RGBA, 16 bit**, Auflösung **1600×2000** (hochkant),
  `assetB_plain_v01_####.png` / `assetB_brand_v01_####.png` — beide Stapel
  identisch nummeriert ab 0001.

## Erst Testrender (M2), dann Serie

3 Testframes zur Abnahme, BEVOR die Serie läuft: frontale Pose (~Frame 60) in
beiden Outfits + Logo-Zoom-Ende (~118), bei 256/512/1024 Samples mit
Zeitmessung pro Frame. Prüfen: Deckungsgleichheit der beiden Outfit-Frames
(übereinanderlegen!), Hautton, Alpha-Kanten, Schatten-Pass.

## Serie rendern (über Nacht, headless)

```bash
caffeinate -i "/Applications/Blender.app/Contents/MacOS/Blender" -b ~/pfad/arbeiter_brand.blend -a
caffeinate -i "/Applications/Blender.app/Contents/MacOS/Blender" -b ~/pfad/arbeiter_plain.blend -s 1 -e 90 -a
```

## Konvertierung + Abgabe

```bash
for f in render_brand/*.png; do avifenc -q 60 --qalpha 95 -d 10 -y 444 -s 4 -j all "$f" "web/brand/$(basename "${f%.png}").avif"; done
for f in render_plain/*.png; do avifenc -q 60 --qalpha 95 -d 10 -y 444 -s 4 -j all "$f" "web/plain/$(basename "${f%.png}").avif"; done
```

Abgabe: alle PNG-Master (beide Stapel + Schatten) + settings.txt + web/-Ordner
(Ziel gesamt ~8–14 MB) + `manifest.json`:

```json
{
  "asset": "arbeiter", "version": "v01", "ext": "avif",
  "sequences": { "plain": { "path": "plain/", "frames": 90 }, "brand": { "path": "brand/", "frames": 120 } },
  "renditions": { "desktop": { "width": 1600, "height": 2000, "frameStep": 1 } },
  "events": { "turnIn": [1, 40], "swapWindow": [40, 85], "logoZoom": [85, 120] }
}
```

(`events` an die tatsächlichen Keyframes anpassen — daran hängen die
Text-Einblendungen der Website. Mobile-Renditionen entstehen später aus den
PNG-Mastern.)
