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

---

## 2. Ordnerstruktur

```
schriften-kortschak/
├─ MEMORY.md                  ← diese Datei
├─ website-backup/            ← vollständiges Mirror der alten Seite (wget)
│  ├─ www.schriften-kortschak.at/   (37 Seiten HTML + ~670 Bilder + CSS/JS)
│  ├─ _sitemaps/              (Original-Sitemaps)
│  └─ wget-log.txt
├─ content/                   ← alte Inhalte als lesbares Markdown (Textextraktion)
│  ├─ _INDEX.md               (Übersicht aller 36 Seiten)
│  └─ *.md                    (eine Datei pro Seite: Text + Bildliste)
├─ analyse/
│  └─ INHALTSANALYSE.md       ← Inhalts-/SEO-Analyse aller 36 Seiten (Ampel-System)
└─ designs/                   ← die neuen Entwürfe
   ├─ 01-werkstatt-grid/index.html   (hell, editorial)
   ├─ 02-dark-studio/index.html      (dunkel, cinematic)
   ├─ 03-bold-kinetic/index.html     (laut, rot/schwarz)
   ├─ 04-bold-studio/        ← GEWÄHLTE, finale Website
   │  ├─ index.html           (Startseite)
   │  ├─ assets/img/          (54 lokale Bilder)
   │  └─ leistungen/<slug>/index.html  (10 Leistungs-Unterseiten)
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
- **Hostinger-MCP**: Domain-Check, Subdomain `view` anlegen, Static-Deploy — alles per API, kein FTP.
- **Playwright-MCP**: Seiten rendern, Screenshots, Bild-Ladekontrolle.
- **Skripte (Python/Bash)** für Fleißarbeit: Bilder lokalisieren, Links umschreiben, alles validieren.
- Prinzip durchgehend: **Behauptungen belegen** (Live-Status, JSON-LD-Validierung, Bild-Checks) statt „müsste passen".

---

## 5. Lokale Vorschau

```bash
cd designs/04-bold-studio
python3 -m http.server 8765
# dann im Browser: http://localhost:8765/
#   Unterseiten: http://localhost:8765/leistungen/<slug>/
```
Die Entwürfe 01–03 öffnet man jeweils direkt als `designs/0X-…/index.html`.

---

## 6. Offen — Checkliste fürs echte Go-Live

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

## 7. Hinweise für Norbert

- Fang bei **`analyse/INHALTSANALYSE.md`** an (zeigt, was an der alten Seite gut/schlecht war) und schau dir dann **`designs/04-bold-studio/`** an — das ist die neue Site.
- Vergleiche `content/<seite>.md` (alt) mit der jeweiligen neuen `leistungen/<slug>/index.html` — man sieht gut, wie aus dünnem Text echte Inhalte wurden.
- Alle neuen Seiten sind **eine einzige HTML-Datei** (CSS + JS inline) — leicht zu lesen und zum Rumspielen.
- Die Bilder in den Seiten zeigen auf `/assets/img/…` (root-absolut), daher lokal immer über einen kleinen Webserver ansehen (siehe Abschnitt 5), nicht per Doppelklick.
