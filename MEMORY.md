# Projekt: Website-Relaunch Kortschak Schriften GmbH

> Diese Datei ist das Projektgedächtnis & der Lern-Leitfaden. Sie dokumentiert **was** wir gebaut haben und **wie** (der Workflow mit Claude Code) — gedacht für Norbert zum Mitlesen, Nachvollziehen und Weiterspielen.

---

## 1. Worum geht's

Neubau der Website der eigenen Werbeagentur **Kortschak Schriften GmbH** (Bestand: `schriften-kortschak.at`, WordPress/Divi). Gleichzeitig ein **KI-Lernprojekt**: zeigen, wie man mit Claude Code real arbeitet — von der Datenaufnahme über Analyse, Design, SEO bis zum Live-Deployment.

**Firma / NAP (für SEO & Impressum maßgeblich):**
- Kortschak Schriften GmbH, Bahnhofstraße 6, 8793 Trofaiach, Steiermark, Österreich
- Tel +43 (0)3847 / 67666 · office@schriften-kortschak.at · UID ATU71916536
- GF: Anja Brandl, B.A. · gegründet 1958 · Bezirk Leoben, Obersteiermark
- Tagline: **Kreation. Klarheit. Fokus.**

**Live-Vorschau (Demo, bewusst `noindex`):** https://view.kortschak.online
(Startseite = finaler Entwurf „Bold Studio" + 10 Leistungs-Unterseiten)
**Entwürfe-Übersicht:** https://view.kortschak.online/entwuerfe/ — alle Entwürfe 01–10 als Unterordner (zuletzt ergänzt: `10-apple-motion` mit scrollgesteuerter BMW-G21-Sequenz aus eigenem Blender-Rendering, Juli 2026)

**Entwurf 13 (`13-foil-hero/`, 17.08.2026, live: https://view.kortschak.online/13-foil-hero/):**
NOTHIN'-Nachbau (noth.in als Referenz): dunkle Studiobühne mit Vignette + Filmkorn,
IBM-Plex-Mono-Metadaten in den Ecken, Intro-Statement mit Zeilen-Maske, dann
scrollgescrubbtes **K aus zerknitterter roter Chromfolie** („rotes Chrom", von Jörn
am Still freigegeben; Cycles + Studio-HDRI + Displace-Knitter), Abbinder mit
Zeilen-Reveal. Sequenz nur 6,8 MB Desktop / 1,7 MB Mobile (opake Folie komprimiert
viel besser als Line-Art). Produktion: `Kortschak-3D/render/k_folie_anim.py` +
`k_folie_postprocess.sh`. Lehre daraus: Ease-Kurven für Scroll-Scrub quadratisch
und bis p≈0.85 strecken, kubisch/0.75 ist frontlastig (Probe-Frames prüfen!).

**Entwurf 12 (`12-scroll-hero/`, 17.08.2026, live: https://view.kortschak.online/12-scroll-hero/):**
Reaktion auf Jörns Kritik an 11 („nicht spektakulär"): Headline zentriert in der
Bühnenmitte, **SICHTBAR in Anton als dezente Outline (1,2 px, abgedunkelt), füllt
sich beim Scrollen von links mit dem Design-10-Akzentverlauf Rot→Orange
(#FF1C20→#d81216→#F18700, 135°)** — und ein **Rakel** (Inline-SVG, oranger Filz)
fährt an der Füllkante mit: Das Wort wird „foliert". Jörn probierte erst
Silber→Orange, entschied dann Rot→Orange. K-Sequenz (geteilt aus
`11-scroll-hero/seq/` — nicht doppelt deployen!) läuft dahinter und tritt am Ende
auf 28 % Alpha zurück. Headline steht ab Sekunde null — der leere Anfang von v11
ist damit weg.

**Entwurf 11 (`11-scroll-hero/`, 17.08.2026, live: https://view.kortschak.online/11-scroll-hero/):**
(Deploy-Verfahren steht jetzt vollständig in Abschnitt 5. Kurz: Root von
view.kortschak.online = Design 10, die Entwürfe 11–14 liegen daneben, die alten
Entwürfe 01–10 sind seit 08/2026 nicht mehr live.)
Vollbild-Scribble-Scroll-Hero fürs Relaunch-Projekt. Das gespiegelte Logo-K als
reine Linien-Skulptur (Mantis-/noth.in-Referenz), beim Scrollen: Flug von hinten,
2 Drehungen, Striche zeichnen sich gestaffelt auf, Headline ab 85 %. Produktion
reproduzierbar in `Kortschak-3D/render/k_scribble_anim.py` (+ `k_scribble_postprocess.sh`);
Sequenz 240 Frames AVIF desktop (34 MB — vor Live-Gang verschlanken!) + 120 mobile
(7,6 MB) in `seq/`. Light/Dark aus einem Asset (Canvas-Invertierung), Reduced-Motion
= stehendes Poster. Lokale Vorschau: `cd designs/11-scroll-hero && python3 -m http.server 8767`.

---

## 2. Ordnerstruktur

```
schriften-kortschak/
├─ MEMORY.md                  ← diese Datei
├─ deploy/                    ← Deploy-Werkzeug, siehe Abschnitt 5
│  ├─ paket-bauen.sh          (baut Paket + ZIP, Ausschlussliste inklusive)
│  └─ pruef-assets.py         (prüft alle Asset-Referenzen gegen die Live-Site)
├─ website-backup/            ← vollständiges Mirror der alten Seite (wget)
│  ├─ www.schriften-kortschak.at/   (37 Seiten HTML + ~670 Bilder + CSS/JS)
│  ├─ _sitemaps/              (Original-Sitemaps)
│  └─ wget-log.txt
├─ content/                   ← alte Inhalte als lesbares Markdown (Textextraktion)
│  ├─ _INDEX.md               (Übersicht aller 36 Seiten)
│  └─ *.md                    (eine Datei pro Seite: Text + Bildliste)
├─ analyse/
│  └─ INHALTSANALYSE.md       ← Inhalts-/SEO-Analyse aller 36 Seiten (Ampel-System)
└─ designs/
   ├─ 01-werkstatt-grid/ … 09-monument/   (frühere Entwurfsstände)
   ├─ 04-bold-studio/        (erster gewählter Stand, inzwischen abgelöst)
   ├─ 10-apple-motion/       ← AKTUELLE Website, das ist der Stand, der live geht
   │  ├─ index.html           (Startseite mit Iris-Hero)
   │  ├─ assets/              (Bilder, Video, Fonts, Iris-Sequenz; Fotos-Lama = Rohmaterial)
   │  ├─ leistungen/<slug>/index.html   (11 Leistungs-Unterseiten)
   │  ├─ impressum/ + datenschutz/
   │  └─ _archiv/, hero-c/, *.bak-*     (Arbeitsstände – nicht deployen, Abschnitt 5)
   ├─ 11-scroll-hero/ … 14-iris-hero/   (Hero-Studien, liegen live neben der Site)
   └─ _screenshots/          (Voll-Screenshots aller Entwürfe + Unterseiten)
```

---

## 3. Der Weg — chronologisch (das ist der Lern-Teil)

1. **Daten gesichert.** `wget`-Mirror der ganzen alten Seite (Text, Bilder, Sitemap) → `website-backup/`. Danach Text jeder Seite mit Python/BeautifulSoup zu sauberem Markdown extrahiert → `content/`.
2. **Struktur visualisiert.** Sitemap/IA der Bestandsseite als Diagramm dargestellt (Leistungen-Hub + 3 Kategorien + Einzelleistungen + Unternehmen/Recht).
3. **Inhalts-Analyse** → `analyse/INHALTSANALYSE.md`. **6 Agenten parallel** bewerteten alle 36 Seiten nach einer einheitlichen Matrix. Ergebnis-Ampel: **3 übernehmen / 25 überarbeiten / 8 neu**. Kernprobleme: Markenname uneinheitlich (5 Varianten), viel „Thin Content", kein echter CTA, H1-Fehler, fehlendes lokales SEO, veraltete Datenschutzerklärung.
4. **Design-System** des Kunden gesichtet (`Kortschak Schriften Design System`): Brand-Rot `#FF1C20`, Dual-Mode, Pill-Buttons, Film-Grain, „Kreation. Klarheit. Fokus.".
5. **3 Entwürfe parallel gebaut** (je 1 Agent): 01 Werkstatt-Grid, 02 Dark Studio, 03 Bold Kinetic — mit echten Inhalten/Bildern, barrierefrei.
6. **Kombiniert zu „04 Bold Studio"** (Kundenwunsch: heller als 02, klare Sektions-Abgrenzung): heller Grundton mit vollflächigem Farbblock-Rhythmus (Hero schwarz → Marquee rot → Leistungen weiß → Unternehmen rot → Team grau → … → Kontakt schwarz).
7. **Texte + SEO eingebaut.** Eigener wiederverwendbarer Skill `seo-onpage` erstellt und angewandt: keyword-first Titles, Meta/OG/Twitter, Geo-Tags, JSON-LD (LocalBusiness + WebSite + FAQPage), neue FAQ-Sektion, echte NAP, lokale Keywords, Marken-Konsistenz. Staging bewusst `noindex`, Canonical → echte Domain.
8. **Eigenständig gemacht.** Alle Bilder lokalisiert (`assets/img/`, keine Abhängigkeit mehr von der alten Domain), Favicon ergänzt.
9. **10 Leistungs-Unterseiten gebaut** (1 + 9 parallel via Agenten, Car-Wrapping als Vorlage): jede mit echtem Text, lokalen Bildern, FAQ, voller SEO (Service + BreadcrumbList + FAQPage), WCAG-AA. Startseiten-Kacheln + Footer verlinkt.
10. **Deployed** auf `view.kortschak.online` (Hostinger) und jede Seite live verifiziert (HTTP 200, Bilder `broken=0`).

---

## 4. Werkzeuge & Muster (so wurde gearbeitet)

- **Parallele Sub-Agenten** für unabhängige Teilaufgaben (Analyse je Bereich, Entwürfe, Unterseiten) — schnell + konsistent durch gemeinsames Briefing/Vorlage. Danach immer **selbst verifiziert**.
- **Skills**: `frontend-design` (Design), eigener **`seo-onpage`** (On-Page-SEO beim Bauen; liegt unter `~/.claude/skills/seo-onpage/` — wiederverwendbar für künftige Kundenprojekte; ergänzt das vorhandene `marketing:seo-audit`).
- **Hostinger-MCP**: Domain-Check, Subdomain `view` anlegen, Static-Deploy – alles per API, kein FTP.
  ⚠️ `deployStaticWebsite` **ersetzt den kompletten Webroot**. Vollständiges Rezept samt Skripten: **Abschnitt 5**.
- **Playwright-MCP**: Seiten rendern, Screenshots, Bild-Ladekontrolle.
- **Skripte (Python/Bash)** für Fleißarbeit: Bilder lokalisieren, Links umschreiben, alles validieren.
- Prinzip durchgehend: **Behauptungen belegen** (Live-Status, JSON-LD-Validierung, Bild-Checks) statt „müsste passen".

---

## 5. Deploy-Rezept `view.kortschak.online` (Stand 20.08.2026)

**Ziel:** Hostinger, Domain `view.kortschak.online`, User `u578130405`,
Webroot `/home/u578130405/domains/view.kortschak.online/public_html`.
Werkzeug: Hostinger-MCP **`hosting_deployStaticWebsite`** – lädt das Archiv per
TUS hoch und entpackt es selbst, kein FTP und kein separater Upload-Aufruf.

> ⚠️ **Der Deploy ersetzt den kompletten Webroot.** Kein additives Entpacken,
> kein Rollback am Server. Was nicht im Archiv liegt, ist danach offline.
> Darum immer das ganze Ziel paketieren, nie nur die geänderte Seite.
> Der Rückweg ist das Git-Repo – **vor dem Deploy committen und pushen.**

### Was ins Paket gehört

| Im Archiv | Quelle |
|---|---|
| Wurzel: `index.html`, `leistungen/`, `impressum/`, `datenschutz/`, `assets/` | `designs/10-apple-motion/` |
| `11-scroll-hero/`, `12-scroll-hero/`, `13-foil-hero/`, `14-iris-hero/` | `designs/<name>/` |

Die vier Entwurfs-Bühnen waren schon vorher live und müssen mit, sonst
verschwinden sie.

### Was bewusst draußen bleibt

- `10-apple-motion/assets/Fotos-Lama/` – 219 MB Rohfotos
- `14-iris-hero/iris-eye-isolated/` + `-2/` – 116 MB Photoshop-Quellen
- `10-apple-motion/_archiv/` (12 MB), `hero-c/`
- `index.html.bak-*` sowie alle `*.md` im Seitenordner (PLAN, PROMPT-\*, BLENDER-SPECS)
- `.DS_Store`

Ohne diese Ausschlüsse wären es über 300 MB, und Rohmaterial läge öffentlich.

### Ablauf

```bash
# 1. Paket + Archiv bauen (Ausschlussliste steckt im Skript)
sh deploy/paket-bauen.sh
#    -> /tmp/kortschak-deploy  +  /tmp/view-kortschak_<zeitstempel>.zip
#    -> aktuell rund 165 MB / 2.048 Dateien

# 2. Deploy ueber den Hostinger-MCP:
#      hosting_deployStaticWebsite
#      domain      = view.kortschak.online
#      archivePath = <der ausgegebene ZIP-Pfad>
#      removeArchive = true
#    Nach rund 15 Sekunden ist der neue Stand live.

# 3. CDN-Cache leeren – der Deploy tauscht nur den Origin:
#      hosting_clearWebsiteCacheV1
#      username = u578130405, domain = view.kortschak.online
#    Hostingers Edge (server: hcdn) liefert sonst weiter gecachte Antworten
#    mit alten Inhalten/Headern aus – je Edge-Node verschieden alt, darum
#    koennen Stichproben "schon ok" zeigen, waehrend andere Dateien alt
#    bleiben (so geschehen am 20.08.: 666 von 1.896 MIME-Befunden ueberlebten
#    den Deploy als Cache-HITs). Nach dem Purge greift alles binnen ~1 Minute.

# 4. Verifizieren – nicht klicken, messen:
python3 deploy/pruef-assets.py /tmp/kortschak-deploy https://view.kortschak.online
```

`pruef-assets.py` zieht jede `src`/`href`/`content`- und CSS-`url()`-Referenz aus
allen HTML-Dateien des Pakets **plus die Frame-Sequenzen aus allen
`manifest.json`** (die lädt das JS der Scroll-Bühnen zur Laufzeit – über 1.100
AVIF-Frames, die in keinem Attribut stehen). Je Asset wird geprüft: lokal
mitgepackt, live HTTP 200, **Content-Type passend zur Endung**. Läuft parallel
(HEAD, 12 Worker, 1 Retry), meldet gesammelt je Ordner. Exit-Code 1 bei jedem
Befund.

Der MIME-Check existiert, weil Hostinger `.avif` nicht kennt und die Frames als
`text/plain` auslieferte – das trug nur, weil Browser bei `<img>` am Inhalt
schnüffeln; mit `nosniff` wäre jede Bühne schwarz. Fix: die **`.htaccess`**
(`AddType image/avif .avif`) liegt in `designs/10-apple-motion/` und wandert
automatisch mit ins Paket.

**Lauf vom 20.08.2026 (nach Deploy + Cache-Purge):** 14 Kundenseiten +
4 Entwurfs-Bühnen je HTTP 200, **1.972 Assets geprüft, 0 fehlerhaft** –
inklusive aller Frame-Sequenzen und Content-Types. PSD-Quellen erwartungsgemäß
nicht im Paket, `noindex, nofollow` steht noch.

---

## 6. Lokale Vorschau

```bash
cd designs/04-bold-studio
python3 -m http.server 8765
# dann im Browser: http://localhost:8765/
#   Unterseiten: http://localhost:8765/leistungen/<slug>/
```
Die Entwürfe 01–03 öffnet man jeweils direkt als `designs/0X-…/index.html`.

---

## 7. Offen – Checkliste fürs echte Go-Live

- [ ] **Rechtsseiten**: Impressum-Seite + **neue DSGVO-Datenschutzerklärung** (alte ist veraltet/unvollständig → war 🔴 in der Analyse)
- [ ] **Kontaktformular-Backend** (aktuell nur Frontend, kein Versand)
- [ ] Echte **Öffnungszeiten** ergänzen (im LocalBusiness-Schema + Kontakt)
- [ ] Bild-Galerien **kuratieren & komprimieren** (Performance)
- [ ] `robots` von `noindex` auf `index, follow` umstellen + **sitemap.xml** / `robots.txt`
- [ ] Markenname final fixieren (Empfehlung: „Kortschak Schriften GmbH", Marke „Kortschak", Deskriptor „Werbeagentur")
- [ ] Gründungsjahr/„68 Jahre" gegenprüfen (1958)
- [ ] Migration auf `schriften-kortschak.at` — statisch **oder** über die verbundene WordPress-Anbindung
- [ ] Optional: weitere Bestandsseiten (Team, Stundensätze-Detail, Möbelfolierung, Unternehmen/Geschichte)

---

## 8. Hinweise für Norbert

- Fang bei **`analyse/INHALTSANALYSE.md`** an (zeigt, was an der alten Seite gut/schlecht war) und schau dir dann **`designs/04-bold-studio/`** an — das ist die neue Site.
- Vergleiche `content/<seite>.md` (alt) mit der jeweiligen neuen `leistungen/<slug>/index.html` — man sieht gut, wie aus dünnem Text echte Inhalte wurden.
- Alle neuen Seiten sind **eine einzige HTML-Datei** (CSS + JS inline) — leicht zu lesen und zum Rumspielen.
- Die Bilder in den Seiten zeigen auf `/assets/img/…` (root-absolut), daher lokal immer über einen kleinen Webserver ansehen (siehe Abschnitt 5), nicht per Doppelklick.
