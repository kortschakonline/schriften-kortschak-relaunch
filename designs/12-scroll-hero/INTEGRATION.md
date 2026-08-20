# Entwurf 12 → Integration in kortschak-website

Stand 17.08.2026. Entwurf 12 („Sichtbar-Hero") ist von Jörn als Richtung
gewählt. Live-Demo: https://view.kortschak.online/12-scroll-hero/
Dieses Dokument beschreibt, was bei der Übernahme in `kortschak-website/`
(Branch `umbau-startseite-2026-08`) zu tun ist. **Nicht von hier aus
integrieren** — nur am aktuellen, gesyncten Stand arbeiten.

## Was der Hero macht (Scroll-Fortschritt p über ~280vh Track)

| Phase | Verhalten |
|---|---|
| p = 0 | Vollbild-Waberfeld (Canvas, zeitbasiert), Headline zentriert: „WIR MACHEN SIE" + SICHTBAR. als dezente Outline (Anton, 1,2 px, --umriss) |
| 0 → 0,62 | Scribble-K-Sequenz läuft komplett durch (Flug + 2 Drehungen + Strichaufbau), zentriert hinter der Typo, 92 % Viewporthöhe |
| 0,42 → 0,88 | SICHTBAR füllt sich von links (clip-path) mit Verlauf 135°: #FF1C20 → #d81216 → #F18700 (Design-10-Akzent); der **Rakel** (Inline-SVG, Buchstabenhöhe, 9° gekippt) fährt an der Füllkante mit |
| 0,80 → 0,95 | K blendet auf 28 % Alpha zurück (wird Textur) |
| ab 0,88 | Subline + CTAs faden ein |

## Bausteine

- `index.html` — alles inline (CSS + JS, keine Abhängigkeiten außer
  Google-Font Anton). Für die Website: JS-Teil in `stage.js`-Muster
  überführen oder als eigenes Modul (`stage-sichtbar.js`) danebenlegen.
- Sequenz: **`../11-scroll-hero/seq/`** (Entwurf 12 referenziert die
  Frames von Entwurf 11 — beim Integrieren nach
  `src/assets/seq/k-scribble/` kopieren und Pfad anpassen).
- Quelle/Regenerierung: `Kortschak-3D/render/k_scribble_anim.py`
  (Preview- und Final-Modus) + `k_scribble_postprocess.sh` (AVIF).
  Look „Variante A aufgepolstert v3", Seed 101 — Parameter im Skriptkopf.

## Offene Punkte vor Live-Gang

1. **Sequenz-Diät:** Desktop aktuell 34 MB (240 Frames, q50/qalpha85).
   Ziel < 15 MB. Hebel: 180 statt 240 Frames (frameStep-Muster der
   BMW-Bühne), qalpha 75, oder 1000×1143 statt 1120×1280. Erst messen,
   dann entscheiden — Linien sind alphakritisch.
2. **Anton lokal hosten** (kein Google-Fonts-Request; woff2 subset reicht:
   A–Z, Punkt).
3. **Reduced-Motion/No-JS:** Muster aus Entwurf 12 übernehmen (stehendes
   Poster + sichtbare Headline); No-JS-Fall wie bei der K-Bühne vom
   05.08. gegentesten (damals der stage.css-Bug).
4. **A11y:** `<h1>` bleibt echtes DOM („sichtbar" via aria-label), Bühne
   hat aria-label — beibehalten.
5. **Waberfeld** ersetzt ggf. das vorhandene Topo-Feld der Startseite —
   nicht doppelt laufen lassen.

## Was NICHT übernehmen

- Die Meta-Zeile „Entwurf 12 · Sichtbar-Hero" im Header (Demo-Beschriftung).
- Die „Prototyp-Ende"-Sektion.
- Der 8er-Ladeplan ist fürs Demo okay; auf der echten Seite den
  Preload-Mechanismus der bestehenden Bühnen (manifest/frameStep) nutzen.
