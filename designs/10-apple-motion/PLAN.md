# Design 10 „Apple Motion" — Projektplan

> Ziel: Design 06 (Apple-like, Kundenfavorit) + Scroll-Erlebnisse in der Qualität von
> apple.com/at/macbook-pro und apple.com/at/iphone-air — mit zwei großen 3D-Momenten:
> **BMW G21** (Kamerafahrt, Scheinwerfer an, Folierungswechsel) und **Arbeiter**,
> der im Scroll unsere Arbeitskleidung bekommt.
>
> Begleitdokument für die Blender-Arbeit: [BLENDER-SPECS.md](BLENDER-SPECS.md)
> Basis: 5 parallele Recherchen (Apple-Seiten dekompiliert, Code-Audit 06/08,
> Web-Technik 2026, Blender-Pipeline) + adversarialer Review des Plans aus
> 3 Perspektiven (Frontend, Blender-Praxis, Projekt). Kernaussagen eingearbeitet.

---

## 1. Die wichtigste Erkenntnis zuerst

**Apple rendert offline und scrubbt das Ergebnis — es gibt dort kein Live-3D für die
großen Kino-Momente.** Die Analyse der beiden Referenzseiten (Roh-HTML + JS-Bundles):

| | MacBook Pro | iPhone Air |
|---|---|---|
| Canvas-Bildsequenzen | keine | keine |
| Live-WebGL | nein (2D-Turntable) | nur der interaktive Produktviewer (Lotus/Three.js) |
| Scroll-**gescrubbte** Videos | **keine** — nur getriggerte Play-once-Videos | **4 Stück** (`video.currentTime` ↔ Scroll, H.264 mit Keyframe alle ~4 Frames, je nur 1–3 MB) |
| Freigestellte Motive | — | Alpha-Videos doppelt kodiert (WebM/VP9 + HEVC/MOV) |
| Sticky-Bühnen | 300–400vh, `position:sticky` | ebenso |
| Text-Beats | Opacity-Fenster von ~10vh, gestaffelt | ebenso + „GarageDoor"-Copy-Reveal |
| Engine | eigene rAF-Engine (rAF = requestAnimationFrame, der Zeichen-Takt des Browsers), Keyframes deklarativ in data-Attributen, Progress → CSS-Variablen | dieselbe |

*(„GarageDoor" = Apples Muster, bei dem sich die Abschluss-Copy am Ende einer Bühne
wie hinter einem hochfahrenden Tor zeilenweise freigibt.)*

Und: **Scrubbing wird sparsam eingesetzt** — nur wo man das Produkt „in der Hand
drehen" soll. Alles Erzählerische ist Play-once oder simple Einblendung. Die
Hero-Sektion ist bei Apple bewusst ruhig — das Scroll-Kino kommt erst in den
Produkt-Sektionen.

**Konsequenz für uns:** Der BMW wird nicht mehr als GLB live gerendert, sondern in
Blender/Cycles in echter Qualität (Glas mit Transmission, Innenraum, Scheinwerfer,
weiche Schatten) **vorgerendert** und im Browser gescrubbt. Du exportierst also kein
GLB mehr — **du renderst eine Bildsequenz.**

---

## 2. Grundsatzentscheidung: Alpha-Bildsequenz auf Canvas

Drei Techniken standen zur Wahl:

1. **Live-WebGL wie bisher (06/08)** — ✗ als Haupttechnik verworfen.
   Three.js-Transmission (echtes Glas) ist eine Screen-Space-Näherung: keine echten
   Mehrfachbrechungen, kein sauberer Innenraum-durch-Scheibe-Blick, teuerster
   Materialtyp (erzwingt einen zweiten Render-Pass; Praxisberichte: 100 % GPU auf
   M2-MacBooks, Lags auf Mobile). Cycles-Qualität ist so nicht erreichbar.
2. **Scroll-gescrubbtes Video (Apples iPhone-Air-Weg)** — ✗ für uns verworfen, aus
   einem Grund, den Apple nicht hat: **unser Design ist Dual-Mode (hell/dunkel).**
   Apples Scrub-Videos haben den Hintergrund eingebacken. Wir brauchen freigestellte
   Motive, die auf beiden Seiten-Hintergründen funktionieren — und Alpha-Video
   (VP9/HEVC) lässt sich nicht zuverlässig scrubben (Alpha + dichte Keyframes =
   doppelter Kostentreiber, Android ruckelt, dokumentierte iOS-WebKit-Crashes bei
   mehreren WebM-Alpha-Videos pro Seite).
3. **Bildsequenz mit Alpha auf `<canvas>`** — ✓ **unsere Wahl.**
   Deterministisch, vorwärts wie rückwärts butterweich scrubbbar, identisches Bild
   auf jeder Engine, ein Asset für beide Themes. Das ist die klassische
   Apple-Technik (AirPods Pro: 148 JPEGs) — nur bei uns mit Alpha-Kanal.

**Eckdaten der Sequenz-Pipeline:**

- **Format:** AVIF (10 bit, `--qalpha 95`). AVIF-Support ist 2026 flächendeckend;
  Browser ohne AVIF bekommen **den statischen Fallback** (wie reduced-motion) statt
  eines WebP-Duplikats — halbiert die Ablage. (WebP-Kommandos stehen trotzdem in den
  Specs, falls der M1-Feature-Test doch nennenswerte Nicht-AVIF-Nutzung zeigt.)
- **Renditionen & Ausdünnungsregel:** Desktop = Master (BMW 150 Frames à 2000×1125,
  Arbeiter 120 à 1600×2000). Mobile = **jeder 2. Frame, halbe Kantenlänge**
  (BMW 75 à 960×540, Arbeiter 60 à 720×900 — Arbeiter-Rendition ist damit fixiert).
  Das Manifest zählt immer in Master-Frame-Nummern; der Player rechnet
  `mobileIndex = round(masterIndex / frameStep)` um.
- **Transfer-Budget:** BMW ~5–10 MB, Arbeiter ~8–14 MB (beide Stapel), Mobile
  jeweils ~3–6 MB — lazy geladen, siehe Abschnitt 4.
- **Speicher (das echte Nadelöhr — auf ALLEN Geräten):** decodierter Frame =
  Breite × Höhe × 4 Byte. Voll decodiert wären BMW ~1,3 GB und Arbeiter ~2,9 GB —
  **deshalb gilt das LRU-Decode-Fenster überall, auch auf Desktop**: komprimierte
  Blobs aller Frames bleiben im Speicher (10–15 MB), decodiert wird nur ein Fenster
  um die Scrollposition. Der Player dimensioniert das Fenster aus dem Budget:
  `Fensterbreite = Budget ÷ Bytes/Frame` — Budget **≤ ~500 MB Desktop,
  ≤ 150 MB iOS**, im Arbeiter-Überlappungsfenster (zwei Stapel aktiv) pro Stapel
  halbiert. Rechenprobe Arbeiter mobil: 720×900×4 B ≈ 2,6 MB/Frame → 2 Stapel ×
  ±12 Frames ≈ 130 MB ✓.
- **Preloading:** `fetch → blob → createImageBitmap` (im Worker, wo verfügbar);
  Checkpoint-Reihenfolge statt linear (Frame 1 → letzter → 50 % → 25/75 % …), sodass
  eine grobe Version sofort scrubbbar ist; Sequenz lädt erst, wenn die Bühne ±150vh
  nahe ist (Apples „download-area"-Muster).
- **Scroll-Kopplung:** rAF-Loop mit Lerp-Glättung (`smooth += (ziel−smooth)×0,12`),
  Frame-Index quantisieren, nur bei Indexwechsel zeichnen (DPR-Cap 2 =
  Canvas-Auflösung höchstens 2× CSS-Pixel).
- **Schatten & Dark Mode:** Der Bodenschatten wird **als separate kleine
  Schatten-Sequenz** abgegeben (Shadow-Catcher-Pass, stark komprimierbar), die
  Frames selbst sind schattenfrei. Light Mode: Schatten unters Auto zeichnen.
  Dark Mode: stattdessen weicher Boden-Glow. (Eingebackener Schatten würde im
  Dark Mode den Glow als dunklen Fleck überlagern — deshalb getrennt; Details und
  Fallback-Variante in den Blender-Specs, Verifikation in M2.)

---

## 3. Storyboard — Sektion für Sektion

Grundgerüst, Inhalte, SEO-Head, Theme-System: **1:1 aus Design 06** (bestätigt durchs
Code-Audit). Neu sind die zwei Scroll-Bühnen und die Text-Beat-Choreografie.

**Notation:** `p` = Scroll-Fortschritt innerhalb einer Bühne (0 = Einstieg,
1 = Ende der Sticky-Strecke). *Das Storyboard nimmt die Empfehlungen aus
Abschnitt 7 als Annahme (zwei Arbeiter-Sequenzen, ruhiger Hero) — andere
Entscheidungen ändern die betreffende Bühne.*

| # | Sektion | Choreografie |
|---|---|---|
| 1 | **Navbar** | Glas-Navbar aus 06, Theme-Toggle. |
| 2 | **Hero** | Apple-ruhig: große Headline + Sub + CTAs, darunter der BMW als **statischer erster Frame** der Sequenz (sofort da, `fetchpriority=high`). Nur Entrance-Fades — kein Scrub im Hero. |
| 3 | **Statement** | „Alles, was Werbung braucht." — Text-Beats per CSS Scroll-Driven Animations (`animation-timeline: view()`), Fallback IntersectionObserver. |
| 4 | **BMW-Bühne** („Ihr Fuhrpark. Ihre Botschaft.") | Sticky 100vh über **~400vh**. Ablauf siehe Mapping-Tabelle unten. |
| 5 | **Leistungen** | 10 Karten aus 06, gestaffelte Reveals. |
| 6 | **Zahlen** | 68 / 12 / 1× Count-up aus 06. |
| 7 | **Arbeiter-Bühne** („Ihr Team. Ihre Marke." → Textildruck) | Sticky über **~300vh**. Zwei **deckungsgleiche** Sequenzen (gleiches Rig, gleiche Pose, gelinkte Kamera): normal gekleidet → Kortschak-Arbeitskleidung. |
| 8 | **Unternehmen** | Anja Brandl, aus 06. |
| 9 | **Referenzen** | Rotes Gradient-Panel, weiße Logo-Karten (bewusst in beiden Themes hell). |
| 10 | **Preise / FAQ / Kontakt / Footer** | aus 06. |

**BMW-Bühne — Progress↔Frame-Mapping (die eine Quelle der Wahrheit):**
stückweise linear zwischen diesen Knoten; die Beats leiten sich daraus ab und
koppeln sich an die Event-Marken im Frame-Manifest (nicht an geratene Prozente):

| p | Frame | Phase |
|---|---|---|
| 0,00 | 1 | Einstieg, Dreiviertelfront |
| 0,45 | 90 | Orbit-Ende (Seitenprofil) — schnelle Phase |
| 0,55 | 95 | kurzes Verharren vor dem Wipe |
| 0,85 | 135 | Folierungs-Wipe fertig |
| 1,00 | 150 | Heldenpose eingerastet — langsame Phase („Einrasten" wie bei Apple) |

Beats: Caption 1 („Fahrzeugbeschriftung — entworfen, gefertigt, montiert in
Trofaiach") p 0,06–0,16 · **Scheinwerfer glimmen auf** Frames 40–55 ⇒ p ≈ 0,20–0,28,
Caption 2 dort · **Wipe** Silber → Glitzer-Wrap p 0,55–0,85, Caption 3 ·
End-Copy im GarageDoor-Stil + CTAs (Car Wrapping / Angebot) p 0,85–1.

**Arbeiter-Bühne — Mapping** (linear, 120 Frames): Eindrehen Frames 1–40
(p 0–0,33), **Verwandlungs-Wipe** Frames 40–85 (p 0,33–0,71) — das Frontend blendet
per Masken-Sweep zwischen den beiden Stapeln, das Logo „erscheint" —, Logo-Zoom
Frames 85–120 (p 0,71–1) + Caption + CTA Textildruck.

**Masken-Sweep, konkret** (Review-Auflage — nicht „irgendwie blenden"):
**ein** sichtbares Canvas; pro Draw erst Stapel A zeichnen, dann `ctx.save()` +
`clip()` entlang der Sweep-Kante + Stapel B + `ctx.restore()`. Weiche Kante über
eine kleine Offscreen-Gradient-Maske (`globalCompositeOperation:
'destination-in'`). **Kein Alpha-Crossfade** — bei zwei halbtransparenten Stapeln
addieren sich Haarkanten/Alpha zu dunklen Säumen.

Choreografie-Regeln (aus der Apple-Analyse): Captions faden in kurzen
~10vh-Fenstern, gestaffelt; kein Dauer-Parallax; Sektionen verzahnen sich über
negative Sticky-Margins; kein Scroll-Hijacking; kein Inhalt existiert NUR im
Scrub (schnelles Durchscrollen darf nichts verschlucken — End-Beats bleiben stehen).

---

## 4. Technik-Architektur (Frontend)

Weiterhin **eine HTML-Datei, Vanilla JS, kein Framework** — Apples Engine ist genau
das (rAF + deklarative Keyframes), der Scrub-Kern ist ~50–200 Zeilen.

Bausteine:

1. **ScrollStage** — Sticky-Container + `progress()`-Berechnung aus 08, plus neu:
   Lerp-Glättung, stückweises Mapping (Knoten-Tabelle aus Abschnitt 3) und der
   `#p=0.5`-Debug-Hook aus 06. Bekannte Stolpersteine übernommen:
   `overflow:hidden` auf Vorfahren bricht sticky → `overflow:clip`;
   Fixed-Header → `margin-top: calc(var(--header-h) * -1)`.
2. **SequencePlayer** — Canvas (DPR-Cap 2), AVIF-Detection, Checkpoint-Preload,
   **LRU-Decode-Fenster auf allen Geräten** (Fenster = Budget ÷ Bytes/Frame;
   ≤ ~500 MB Desktop, ≤ 150 MB iOS; im Zwei-Stapel-Fenster pro Stapel halbiert),
   Renditionen aus dem Manifest (`frameStep`-Umrechnung), zeichnet nur bei
   Indexwechsel. Liest das **Frame-Manifest** (Schema in den Blender-Specs):
   Framezahl, Auflösungen, Event-Marken → Captions koppeln sich an echte
   Render-Ereignisse.
3. **Beat-Engine** — Progress → CSS-Variablen (`--p`, `--beat-1` …), Captions rein
   in CSS animiert (Apples Muster). Text-Beats außerhalb der Bühnen per CSS
   Scroll-Driven Animations mit `@supports`-Fallback (Firefox stable hinkt noch).
4. **Gating & Fallbacks** (Apple-Pflichtprogramm):

| Situation | Verhalten |
|---|---|
| Desktop, Motion ok | Master-Sequenzen, LRU-Fenster ≤ ~500 MB |
| Mobile *(Default lt. Entscheidung 7)* | Mobile-Rendition (jeder 2. Frame, halbe Größe), LRU ≤ 150 MB |
| `prefers-reduced-motion` | **kein Preload**, statische Key-Standbilder, alle Inhalte normal |
| `Save-Data` / langsame Verbindung / kein AVIF | wie reduced-motion |
| Kein JS | statische, vollständige Seite |
| Theme-Wechsel | Frames sind schattenfrei-freigestellt: Light Mode zeichnet die Schatten-Sequenz, Dark Mode den Boden-Glow |

5. **Self-Hosting**: keine CDN-Abhängigkeiten mehr (Three.js/Draco entfallen
   komplett, da kein Live-3D).

**Performance-Budget** *(Annahme: Empfehlungen aus Abschnitt 7)*:

| Posten | Desktop | Mobile |
|---|---|---|
| Initial (HTML+CSS+JS+Hero-Frame+Bilder above fold) | < 1,5 MB | < 1 MB |
| BMW-Sequenz (Transfer, lazy ±150vh) | 5–10 MB | 3–6 MB |
| Arbeiter (2 Stapel, Transfer) | 8–14 MB | 3–6 MB |
| Decodierte Pixel gleichzeitig (LRU) | ≤ ~500 MB | ≤ 150 MB |

Zum Vergleich: Apples AirPods-Seite lag bei 55,8 MB Gesamttransfer. Wir bleiben mit
lazy geladenen ~15–25 MB Desktop deutlich darunter, initial unter 1,5 MB.
**Budget-Kontrolle ist Teil von M2**: die 3 Testframes werden mit den
Spec-Kommandos encodiert und ×150 hochgerechnet — reißt der Glitzer-Wrap das
Budget, wird `-q` gesenkt oder das Budget bewusst auf 10–15 MB angehoben.

---

## 5. Asset-Produktion (Blender — deine Seite)

Vollständige Specs mit Licht-Setup, Settings, Kommandos und Checklisten:
**[BLENDER-SPECS.md](BLENDER-SPECS.md)**. Kurzfassung:

**Asset A — BMW G21:** eine Timeline (150 Frames): Kamera auf Bezier-Pfad,
Scheinwerfer-Beat bei Frame 40–55 (solange die Kamera noch frontal schaut),
Folierung als Mix-Shader-Wipe (Gradient über animiertes Empty — kein
Objekt-Tausch). Cycles GPU, Film → Transparent, **definiertes Studio-Licht-Setup**
(HDRI + zwei Softbox-Streifen — steht jetzt explizit in den Specs), Schatten als
separater Pass, PBR-Neutral-View-Transform, OIDN High, statischer Seed.
Output PNG 16 bit → AVIF. Renderzeit: **1–4 min/Frame auf NVIDIA-High-End ⇒
3–10 h über Nacht** (andere GPUs entsprechend mehr).

**Asset B — Arbeiter:** Figur-Empfehlung **Human Generator V4, Commercial-Tier
$128** (Personal-Tier reicht für Agentur-/Kundenarbeit nicht). Zwei deckungsgleiche
Sequenzen (brand voll 120 Frames, plain nur 1–90 — danach ist er nie mehr
sichtbar, spart ~30 % Renderzeit), gelinkte Kamera, keine Stoffsimulation.
≈ 4–10 GPU-Stunden.

---

## 6. Meilensteine, Arbeitsteilung, Zeitgefühl

Der Plan ist so gebaut, dass **die Choreografie steht, bevor du eine Minute
Renderzeit investierst.**

| M | Was | Wer | Dauer (ca.) | Abnahme |
|---|---|---|---|---|
| **M0** | Offene Entscheidungen (Abschnitt 7) | wir beide | 1 Gespräch | Entscheidungsliste fix |
| **M1** | Seite bauen: 06-Basis + ScrollStage + SequencePlayer + beide Bühnen mit **Platzhalter-Sequenzen** + **Entwurf aller Caption-/End-Texte** (Beat-Fenster hängen von Textlänge ab) | Claude | 1–2 Arbeitstage | Scroll-Gefühl + Texte am echten Layout, **auch am Smartphone** |
| **M2** | **Testrender BMW**: 3 Keyframes (Start / Licht an / Wrap fertig), 2 Glas-Varianten, PBR Neutral + 1× AgX, 256/512/1024 Samples, **Encoding-Size-Check** | Jörn | 0,5–1 Tag + Abnahme | Look auf hell+dunkel (inkl. Glow-Test), Schatten-Pass, Budget |
| **M3** | BMW final: 150 Frames über Nacht → konvertieren → Manifest; Integration + Beat-Feintuning | Jörn → Claude | 1–2 Rendernächte + 1 Session | BMW-Bühne fertig |
| **M4** | Arbeiter: Figur/Kleidung/Logo bauen (2–4 Arbeitstage), Testrender-Abnahme wie M2, dann 120+90 Frames | Jörn → Claude | 2–4 Tage + 1–2 Rendernächte + 1 Session | Arbeiter-Bühne fertig |
| **M5** | Feinschliff: Mobile-Renditionen (Claude erzeugt sie aus den PNG-Mastern), Preload-Tuning, A11y, Lighthouse, echte Geräte (altes iPhone!), Deploy | Claude | 1–2 Sessions | live auf view.kortschak.online/10-apple-motion/ + /entwuerfe/ |
| **M6** *(optional, s. Entscheidung 6)* | Restyling der 10 Leistungs-Unterseiten auf 06/10-Look | Claude | 1–2 Sessions (parallelisiert wie beim 04-Bau) | Unterseiten konsistent |

**Gesamtkorridor:** bei zügigen Abnahmen **~2–4 Wochen Kalenderzeit**; deine aktive
Blender-Zeit ~3–6 Arbeitstage, Rendern läuft nachts. Kritischer Pfad: M2 (erst
danach Serienrender). M1 ist sofort startbar.

**Deploy-Realität** (Review-Hinweis): Das Live-Bundle liegt heute bei ~74 MB;
Design 10 bringt grob +30–60 MB in ~600–1000 Kleindateien. Der bisherige
ZIP-Deploy trägt das, Upload dauert entsprechend — falls es stört, deployen wir
Design 10 als eigenes Paket.

---

## 7. Offene Entscheidungen (M0)

1. **Glas-Variante BMW**: Transparent Glass (echter Durchblick, kleine
   Alpha-Nebenwirkungen auf sehr hellem Grund) vs. getönte opake Scheiben
   (robust, beim G21 realistisch). → Empfehlung: beides im M2-Testrender ansehen.
2. **Arbeiter-Figur**: Human Generator V4 Commercial ($128, Empfehlung) — oder
   Kaufmodell / Photogrammetrie-Scan eines echten Mitarbeiters (teurer, aber
   authentisch)?
3. **Verwandlungs-Erzählung**: nur Branding-Wipe (Shirt bleibt, Logo/Farbe
   erscheint — eine Sequenz, günstiger, −4–7 MB) oder echter Kleidungswechsel
   (zwei Sequenzen, stärkerer „Wow"-Moment)? → Empfehlung: echter Wechsel.
4. **Hero-Inhalt**: BMW-Standbild (ruhig, Apple-typisch — Empfehlung) oder ein
   Play-once-Moment beim Laden?
5. **Scope Startseite**: Design 10 ersetzt die komplette Startseite (Empfehlung,
   sonst nicht mit 01–09 vergleichbar) — oder nur Hero + Bühnen als Showcase?
6. **Leistungs-Unterseiten** *(im ersten Planentwurf vergessen — Review-Fund)*:
   Die 10 Unterseiten existieren live nur im Bold-Studio-Look von Design 04. Wird
   10 die Startseite, klickt man in einen Stilbruch. Für die Entwurfsphase
   akzeptieren — oder Restyling als M6 einplanen (Aufwand siehe Tabelle)?
   → Empfehlung: Entwurfsphase mit Stilbruch, M6 nach der Designentscheidung.
7. **Mobile-Verhalten der Bühnen**: volle Choreografie mit kleiner Rendition
   (Plan-Default), reduzierte Choreografie (z. B. nur BMW-Bühne), oder statische
   Key-Bilder wie in 06 (dort ist der 3D-Hero unter 820 px statisch)?
   → Empfehlung: Plan-Default, aber Entscheidung nach dem M1-Smartphone-Test.

---

## 8. Risiken & Gegenmittel

| Risiko | Gegenmittel |
|---|---|
| Sequenzen zu schwer auf Mobile (iOS-Speicher) | LRU-Decode-Fenster überall, eigene Mobile-Rendition, hartes Budget; Smartphone-Test schon in M1, Gerätetest in M5 |
| Flicker in der Sequenz (Glitzer-Wrap, Haut/Haare) | Samples hoch + statischer Seed + OIDN High (temporales OIDN ist in Blender 4.x noch nicht da — genau deshalb Testrender M2) |
| Alpha-Fransen / Banding im Silberlack | `--qalpha 95`, 10-bit-AVIF, 16-bit-PNG-Master; Sichtprüfung + Size-Check in M2 |
| Schatten kollidiert mit Dark-Mode-Glow | Schatten als separater Pass (Specs §0); Zusammenspiel wird in M2 auf beiden Themes verifiziert |
| Scheinwerfer-Beat im Studio-Licht unsichtbar | Licht-Fenster liegt frontal (Frame 40–55), Ambient-Niveau wird in M2 explizit dagegen getestet |
| Choreografie gefällt erst nach Re-Render nicht | M1-Platzhalter-Abnahme VOR dem Rendern; Beats hängen am Manifest — Feintuning ohne Re-Render |
| Firefox ohne CSS Scroll-Driven Animations | `@supports`-Fallback: Inhalte erscheinen statisch korrekt |
| Kunde scrollt schnell und überspringt Beats | kurze Beat-Fenster + End-Beats, die stehen bleiben; kein Inhalt nur im Scrub |
| Encoding sprengt Budget (Glitzer-Frames) | Size-Check in M2 (hochrechnen ×150), dann `-q` senken oder Budget bewusst anheben |

---

*Erstellt 2026-07-22; überarbeitet nach adversarialem Review (3 Kritiker:
Frontend-Machbarkeit, Blender-Praxis, Projekt-/Kundenperspektive — u. a. korrigiert:
Decode-Speicherrechnung, Arbeiter-Überlappungsfenster, Schatten/Dark-Mode,
Renditionen im Manifest, Licht-Setup, Unterseiten-Frage, Aufwands-/Zeitangaben).*
