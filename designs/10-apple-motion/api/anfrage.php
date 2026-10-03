<?php
// Formular-Backend der Kortschak-Website.
// Nimmt drei Formulare entgegen (Startseite "kontakt", Unterseite
// "social-media", Aktionsseite /xmas26/ "geschenkpakete"), verschickt zwei
// Mails: Benachrichtigung an das Buero (Reply-To = Kunde, beim
// Geschenkpaket-Formular optional mit Logo-Datei im Anhang) und eine
// Bestaetigung an den Kunden (immer ohne Anhang).
// Versand per SMTP ueber das Hostinger-Postfach aus anfrage-config.php.

declare(strict_types=1);

date_default_timezone_set('Europe/Vienna');
header('X-Robots-Tag: noindex');

$configDatei = __DIR__ . '/anfrage-config.php';
if (!is_file($configDatei)) {
    antwort(false, 'Der Mailversand ist noch nicht konfiguriert.', 500);
}
require $configDatei;

// Geschenkpaket-Anfragen (/xmas26/) gehen zusaetzlich an den Vertrieb –
// eigene Mail mit gleichem Inhalt und Logo-Anhang (Reply-To = Kunde).
const GESCHENKPAKETE_ZUSATZ_EMPFAENGER = ['vertrieb@schriften-kortschak.at'];

// Token-Ausgabe: GET /api/anfrage.php?token
// Das Formular-JS holt sich beim Laden einen signierten Zeitstempel und
// schickt ihn beim Absenden mit (Feld "fz"). Ein Token ist erst nach ein
// paar Sekunden gueltig — Bots, die direkt posten oder sofort abschicken,
// fallen durch.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['token'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'token' => token_bauen('js')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(false, 'Nur POST-Anfragen sind erlaubt.', 405);
}

// ---------------------------------------------------------------- Eingaben

$formular = feld('formular');
if (!in_array($formular, ['kontakt', 'social-media', 'geschenkpakete'], true)) {
    antwort(false, 'Unbekanntes Formular.', 400);
}

// Honeypot: Feld ist fuer Menschen unsichtbar. Ausgefuellt = Bot.
// Bewusst "Erfolg" melden, damit der Bot nichts lernt.
if (feld('webseite') !== '') {
    antwort(true, 'Vielen Dank! Ihre Anfrage ist bei uns angekommen.');
}

$name       = feld('name', 120);
$email      = feld('email', 190);
$nachricht  = mehrzeilig('nachricht', 8000);
$datenschutz = ($_POST['datenschutz'] ?? '') !== '';

$fehler = [];
if ($name === '')      { $fehler[] = 'Bitte geben Sie Ihren Namen an.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $fehler[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.'; }
if ($nachricht === '' && $formular !== 'geschenkpakete') { $fehler[] = 'Bitte schreiben Sie uns eine Nachricht.'; }
if (!$datenschutz)     { $fehler[] = 'Bitte bestätigen Sie die Datenschutzerklärung.'; }

$zeilen = [];   // Label/Wert-Paare fuer die Mail an das Buero
$zeilen[] = ['Name', $name];
$zeilen[] = ['E-Mail', $email];
$anhang = null; // [Dateiname, MIME-Typ, Inhalt] – nur beim Geschenkpaket-Formular

if ($formular === 'geschenkpakete') {
    // Aktionsseite /xmas26/: Verpackung + Inhalt wie im Bestellformular-PDF,
    // dazu Firmendaten und optional die Logo-Datei.
    $unternehmen = feld('unternehmen', 160);
    $telefon     = feld('telefon', 60);
    $anschrift   = feld('anschrift', 240);
    $stueck      = feld('stueckzahl', 10);
    $termin      = feld('liefertermin', 60);
    if ($unternehmen === '') { $fehler[] = 'Bitte gib deinen Firmennamen an.'; }
    if (!ctype_digit($stueck) || (int)$stueck < 1 || (int)$stueck > 100000) { $fehler[] = 'Bitte gib an, wie viele Pakete du brauchst.'; }

    $liste = function (string $schluessel, int $max): array {
        $werte = [];
        foreach ((array)($_POST[$schluessel] ?? []) as $w) {
            if (!is_string($w)) { continue; }
            $w = einzeilig($w, 80);
            if ($w !== '' && count($werte) < $max) { $werte[] = $w; }
        }
        return $werte;
    };
    $verpackung = $liste('verpackung', 10);
    $inhalt     = $liste('inhalt', 15);
    if (!$verpackung && !$inhalt) { $fehler[] = 'Bitte wähle mindestens eine Verpackung oder einen Inhalt aus.'; }

    // Zusatzangaben direkt an das jeweilige Produkt haengen
    $zusatz = [
        'Outdoor-Rucksack' => feld('farbe_outdoor', 30),
        'Retro-Rucksack'   => feld('farbe_retro', 30),
        'T-Shirt'          => feld('groessen_tshirt', 160),
        'Hoodie / Weste'   => feld('groessen_hoodie', 160),
        'Schneidebrett'    => implode(', ', array_intersect($liste('brett', 3), ['klein', 'mittel', 'groß'])),
    ];
    $mitZusatz = function (array $produkte) use ($zusatz): string {
        return implode("; ", array_map(function ($p) use ($zusatz) {
            foreach ($zusatz as $name => $wert) {
                if ($wert !== '' && str_contains($p, $name)) { return $p . ' (' . $wert . ')'; }
            }
            return $p;
        }, $produkte));
    };

    $zeilen[] = ['Unternehmen', $unternehmen];
    if ($telefon   !== '') { $zeilen[] = ['Telefon', $telefon]; }
    if ($anschrift !== '') { $zeilen[] = ['Anschrift', $anschrift]; }
    $zeilen[] = ['Stückzahl', $stueck . ' Pakete'];
    $zeilen[] = ['Verpackung', $verpackung ? $mitZusatz($verpackung) : '–'];
    $zeilen[] = ['Inhalt', $inhalt ? $mitZusatz($inhalt) : '–'];
    if ($termin !== '') { $zeilen[] = ['Wunschtermin', $termin]; }

    // Logo-Datei (optional). Wird nur als Mail-Anhang weitergereicht und
    // nirgends auf dem Server abgelegt.
    $datei = $_FILES['logo'] ?? null;
    if (is_array($datei) && is_int($datei['error'] ?? null) && $datei['error'] !== UPLOAD_ERR_NO_FILE) {
        $erlaubt = ['pdf' => 'application/pdf', 'eps' => 'application/postscript', 'ai' => 'application/postscript',
                    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                    'tif' => 'image/tiff', 'tiff' => 'image/tiff'];
        $endung = strtolower(pathinfo((string)($datei['name'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($datei['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($datei['size'] ?? 0) > 15 * 1024 * 1024) {
            $fehler[] = 'Die Logo-Datei ist größer als 15 MB – schick sie uns bitte per E-Mail an ' . MAIL_EMPFAENGER . '.';
        } elseif ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$datei['tmp_name'])) {
            $fehler[] = 'Die Logo-Datei konnte nicht übertragen werden. Bitte versuch es noch einmal oder schick sie per E-Mail.';
        } elseif (!isset($erlaubt[$endung])) {
            $fehler[] = 'Bitte lade dein Logo als PDF, EPS, AI, SVG, PNG, JPG oder TIF hoch.';
        } else {
            $basis = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo((string)$datei['name'], PATHINFO_FILENAME)) ?: 'logo';
            $anhang = [mb_substr($basis, 0, 60) . '.' . $endung, $erlaubt[$endung], (string)file_get_contents((string)$datei['tmp_name'])];
            $zeilen[] = ['Logo-Datei', $anhang[0] . ' (im Anhang)'];
        }
    } else {
        $zeilen[] = ['Logo-Datei', 'keine hochgeladen'];
    }
    $betreffThema = $unternehmen . ' · ' . $stueck . ' Pakete';
} elseif ($formular === 'kontakt') {
    $leistungen = [];
    foreach ((array)($_POST['leistung'] ?? []) as $l) {
        if (!is_string($l)) { continue; }
        $l = einzeilig($l, 60);
        if ($l !== '' && count($leistungen) < 12) { $leistungen[] = $l; }
    }
    if ($leistungen) { $zeilen[] = ['Anfrage zu', implode(', ', $leistungen)]; }
    $betreffThema = $leistungen ? $leistungen[0] . (count($leistungen) > 1 ? ' u. a.' : '') : '';
} else {
    $unternehmen = feld('unternehmen', 160);
    $funktion    = feld('funktion', 120);
    $telefon     = feld('telefon', 60);
    $paket       = feld('paket', 60);
    if ($unternehmen === '') { $fehler[] = 'Bitte geben Sie Ihr Unternehmen an.'; }
    $zeilen[] = ['Unternehmen', $unternehmen];
    if ($funktion !== '') { $zeilen[] = ['Funktion', $funktion]; }
    if ($telefon  !== '') { $zeilen[] = ['Telefon', $telefon]; }
    if ($paket    !== '') { $zeilen[] = ['Interesse an', $paket]; }
    $betreffThema = 'Social Media' . ($paket !== '' ? ' · ' . $paket : '');
}

if ($fehler) {
    antwort(false, implode(' ', $fehler), 422);
}

// ------------------------------------------------------------ Spam-Filter

// 1) Inhalts-Heuristik: eindeutige Bot-Muster bekommen einen stillen
//    "Erfolg" zurueck (wie beim Honeypot) — es wird aber nichts verschickt.
if (($spamGrund = spam_grund($name, $nachricht)) !== null) {
    error_log('[anfrage.php] Spam verworfen (' . $spamGrund . ') von ' . ($_SERVER['REMOTE_ADDR'] ?? '?')
        . ': ' . mb_substr($nachricht, 0, 120));
    antwort(true, 'Vielen Dank! Ihre Anfrage ist bei uns angekommen.');
}

// 2) Zeit-Token: ohne gueltigen, mindestens ein paar Sekunden alten Token
//    kommt keine Anfrage durch. Menschen ohne JavaScript bekommen eine
//    Bestaetigungsseite mit frischem Token (ein Klick), Bots posten ins Leere.
if (!token_gueltig(feld('fz', 200))) {
    $istFetch = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if ($istFetch) {
        antwort(false, 'Das hat gerade nicht geklappt. Bitte laden Sie die Seite neu und senden Sie die Anfrage noch einmal — oder schreiben Sie direkt an ' . MAIL_EMPFAENGER . '.', 400);
    }
    bestaetigungsseite();
}

// ------------------------------------------------------------- Rate-Limit

if (MAIL_TRANSPORT !== 'log' && !rate_limit_ok()) {
    antwort(false, 'Es wurden gerade sehr viele Anfragen gesendet. Bitte versuchen Sie es in einer Stunde noch einmal — oder schreiben Sie direkt an ' . MAIL_EMPFAENGER . '.', 429);
}

// -------------------------------------------------------------- Mails bauen

$istKontakt  = $formular === 'kontakt';
$istPaket    = $formular === 'geschenkpakete';
$formularOrt = $istKontakt ? 'Kontaktformular Startseite' : ($istPaket ? 'Formular Geschenkpakete (Weihnachten 2026)' : 'Formular Social-Media-Marketing');
if ($istPaket && $nachricht === '') { $nachricht = '(keine Nachricht)'; }

// Enthaelt die Nachricht Links, gibt es keine Bestaetigungsmail an die
// (moeglicherweise gefaelschte) Absenderadresse — sonst werden wir zum
// Spam-Versender fuer fremde Postfaecher. Die Anfrage selbst kommt normal an.
$mitLink = (bool)preg_match('~https?://|www\.~i', $nachricht . ' ' . $name);
if ($mitLink) {
    $zeilen[] = ['Hinweis', 'Nachricht enthält Links — es wurde keine automatische Bestätigung an den Absender geschickt.'];
}
$betreff     = $istPaket
             ? 'Geschenkpakete-Anfrage: ' . $betreffThema
             : ($istKontakt ? 'Website-Anfrage von ' : 'Social-Media-Anfrage von ') . $name
               . ($betreffThema !== '' && $istKontakt ? ' · ' . $betreffThema : '');
$vorname     = preg_split('/\s+/', trim($name))[0] ?? $name;

// 1) Benachrichtigung an das Buero
$mailBuero = mail_html(
    'Neue Anfrage über die Website',
    'Über das ' . e($formularOrt) . ' ist soeben eine Anfrage eingegangen. Antworten auf diese E-Mail gehen direkt an ' . e($name) . '.',
    $zeilen,
    $nachricht,
    null
);
$textBuero = mail_text('Neue Anfrage ueber die Website (' . $formularOrt . ')', $zeilen, $nachricht);

// 2) Bestaetigung an den Kunden
$zeilenKunde = array_values(array_filter($zeilen, fn($z) => !in_array($z[0], ['Name', 'E-Mail', 'Hinweis'], true)));
if ($istPaket) {
    // Kampagne duzt (wie Bestellformular und /xmas26/); der Anhang geht nur ans Buero
    $zeilenKunde = array_map(fn($z) => [$z[0], str_replace(' (im Anhang)', ' (erhalten)', $z[1])], $zeilenKunde);
    $mailKunde = mail_html(
        'Deine Anfrage ist bei uns angekommen',
        'Hallo ' . e($vorname) . ', danke für deine Geschenkpaket-Anfrage! Wir stellen dein Angebot zusammen und melden uns so schnell wie möglich — in der Regel innerhalb eines Werktags. Hier noch einmal alles, was du uns geschickt hast.',
        $zeilenKunde,
        $nachricht,
        'Du möchtest etwas ergänzen oder das Logo nachreichen? Antworte einfach auf diese E-Mail oder ruf uns an: +43 3847 67666.'
    );
    $textKunde = mail_text('Danke fuer deine Geschenkpaket-Anfrage! Wir melden uns in der Regel innerhalb eines Werktags.', $zeilenKunde, $nachricht);
} else {
$mailKunde = mail_html(
    'Ihre Anfrage ist bei uns angekommen',
    'Hallo ' . e($vorname) . ', vielen Dank für Ihre Nachricht! Wir haben Ihre Anfrage erhalten und melden uns so schnell wie möglich — in der Regel innerhalb eines Werktags. Zur Sicherheit fassen wir hier noch einmal zusammen, was Sie uns geschickt haben.',
    $zeilenKunde,
    $nachricht,
    'Sie möchten etwas ergänzen? Antworten Sie einfach auf diese E-Mail oder rufen Sie uns an: +43 3847 67666.'
);
$textKunde = mail_text('Vielen Dank fuer Ihre Anfrage! Wir melden uns in der Regel innerhalb eines Werktags.', $zeilenKunde, $nachricht);
}

// ------------------------------------------------------------------ Versand

try {
    mail_senden(MAIL_EMPFAENGER, 'Kortschak Website', $betreff, $mailBuero, $textBuero, [$email, $name], $anhang);
} catch (Throwable $t) {
    error_log('[anfrage.php] Versand an Buero fehlgeschlagen: ' . $t->getMessage());
    antwort(false, 'Ihre Anfrage konnte gerade nicht übermittelt werden. Bitte versuchen Sie es später noch einmal — oder schreiben Sie direkt an ' . MAIL_EMPFAENGER . '.', 502);
}

if ($istPaket) {
    foreach (GESCHENKPAKETE_ZUSATZ_EMPFAENGER as $zusatz) {
        try {
            mail_senden($zusatz, 'Kortschak Vertrieb', $betreff, $mailBuero, $textBuero, [$email, $name], $anhang);
        } catch (Throwable $t) {
            // Das Buero hat die Anfrage – eine fehlende Kopie dem Kunden nicht anlasten.
            error_log('[anfrage.php] Kopie an ' . $zusatz . ' fehlgeschlagen: ' . $t->getMessage());
        }
    }
}

if (!$mitLink) {
    try {
        mail_senden($email, $name, $istPaket ? 'Deine Geschenkpaket-Anfrage bei Kortschak' : 'Ihre Anfrage bei Kortschak — wir melden uns!', $mailKunde, $textKunde, [MAIL_ANTWORT_AN, 'Kortschak Werbeagentur']);
    } catch (Throwable $t) {
        // Anfrage ist beim Buero angekommen — Bestaetigungsfehler nicht dem Kunden anlasten.
        error_log('[anfrage.php] Bestaetigung an Kunden fehlgeschlagen: ' . $t->getMessage());
    }
}

antwort(true, $istPaket
    ? 'Danke, ' . $vorname . '! Deine Anfrage ist unterwegs — eine Bestätigung ist auf dem Weg in dein Postfach. Wir melden uns mit deinem Angebot.'
    : 'Vielen Dank, ' . $vorname . '! Ihre Anfrage ist unterwegs — eine Bestätigung ist auf dem Weg in Ihr Postfach.');

// ======================================================================
// Hilfsfunktionen
// ======================================================================

function feld(string $name, int $max = 200): string {
    $wert = $_POST[$name] ?? '';
    return is_string($wert) ? einzeilig($wert, $max) : '';
}

function einzeilig(string $wert, int $max): string {
    $wert = trim(preg_replace('/[\r\n\t\0]+/', ' ', $wert) ?? '');
    return mb_substr($wert, 0, $max);
}

function mehrzeilig(string $name, int $max): string {
    $wert = $_POST[$name] ?? '';
    if (!is_string($wert)) { return ''; }
    $wert = str_replace(["\r\n", "\r"], "\n", trim($wert));
    return mb_substr($wert, 0, $max);
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function antwort(bool $ok, string $meldung, int $status = 200): never {
    $istFetch = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    http_response_code($status);
    if ($istFetch) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'meldung' => $meldung], JSON_UNESCAPED_UNICODE);
    } else {
        // Fallback ohne JavaScript: kleine gebrandete Antwortseite.
        header('Content-Type: text/html; charset=utf-8');
        $titel = $ok ? 'Anfrage gesendet' : 'Das hat leider nicht geklappt';
        echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . e($titel) . ' — Kortschak</title>'
            . '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#fbfbfd;color:#1d1d1f;display:grid;min-height:100vh;place-items:center;padding:24px}'
            . '.karte{max-width:520px;background:#fff;border:1px solid rgba(0,0,0,.09);border-radius:28px;padding:40px;box-shadow:0 12px 48px rgba(0,0,0,.10);text-align:center}'
            . '.punkt{width:14px;height:14px;border-radius:50%;background:' . ($ok ? '#1db954' : '#FF1C20') . ';margin:0 auto 18px}'
            . 'h1{font-size:1.5rem;margin:0 0 10px}p{color:#515154;line-height:1.5;margin:0 0 24px}'
            . 'a.btn{display:inline-block;background:#FF1C20;color:#fff;text-decoration:none;padding:12px 26px;border-radius:980px;font-weight:600}</style></head>'
            . '<body><div class="karte"><div class="punkt"></div><h1>' . e($titel) . '</h1><p>' . e($meldung) . '</p>'
            . '<a class="btn" href="/">Zurück zur Website</a></div></body></html>';
    }
    exit;
}

// ------------------------------------------------------------ Spam-Abwehr

// Signierter Zeitstempel als Formular-Token. Zwei Sorten:
//   'js' — vom Formular-JS beim Seitenaufruf geholt, fruehestens nach
//          4 Sekunden gueltig (Sofort-Submit-Bots fallen durch)
//   'ok' — von der Bestaetigungsseite (No-JS-Weg), fruehestens nach
//          2 Sekunden gueltig (Lesezeit; Parse-und-Repost-Bots sind schneller)
// Der Schluessel wird aus dem SMTP-Passwort abgeleitet — kein neuer
// Konfigurationswert noetig, und er steht nie im Repo.
function token_schluessel(): string {
    return hash_hmac('sha256', 'anfrage-token-v1', SMTP_PASS);
}

function token_bauen(string $typ): string {
    $zeit = (string)time();
    return $typ . '.' . $zeit . '.' . hash_hmac('sha256', $typ . '.' . $zeit, token_schluessel());
}

function token_gueltig(string $token): bool {
    $teile = explode('.', $token);
    if (count($teile) !== 3) { return false; }
    [$typ, $zeit, $signatur] = $teile;
    $minAlter = ['js' => 4, 'ok' => 2][$typ] ?? null;
    if ($minAlter === null || !ctype_digit($zeit)) { return false; }
    if (!hash_equals(hash_hmac('sha256', $typ . '.' . $zeit, token_schluessel()), $signatur)) { return false; }
    $alter = time() - (int)$zeit;
    return $alter >= $minAlter && $alter <= 21600;   // maximal 6 Stunden
}

// Eindeutige Bot-Muster. Bewusst konservativ: ein einzelner Link in der
// Nachricht ist erlaubt (Kunden nennen ihre Website), erst Haeufung und
// Markup sind verdaechtig.
function spam_grund(string $name, string $nachricht): ?string {
    if (preg_match('~https?://|www\.~i', $name)) { return 'Link im Namen'; }
    if (preg_match('~\[/?(url|link)\b|<a\s~i', $nachricht)) { return 'Markup in der Nachricht'; }
    if (preg_match_all('~https?://|www\.~i', $nachricht) >= 3) { return 'Link-Haeufung'; }
    if (preg_match('/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $name . ' ' . $nachricht)) {
        return 'fremdes Schriftsystem';
    }
    return null;
}

// No-JS-Weg: Anfrage kam ohne Token an (JavaScript aus oder Direkt-POST).
// Wir zeigen die Eingaben noch einmal und lassen den Versand mit einem
// frischen Token per Klick bestaetigen. Menschen kostet das einen Klick,
// Direkt-POST-Bots kommen hier nicht weiter.
function bestaetigungsseite(): never {
    $felder = '';
    foreach ($_POST as $feldName => $wert) {
        if (!is_string($feldName) || $feldName === 'fz' || !preg_match('/^[a-z_][a-z0-9_\-]{0,40}$/i', $feldName)) { continue; }
        foreach (is_array($wert) ? $wert : [$wert] as $einzeln) {
            if (!is_string($einzeln)) { continue; }
            $felder .= '<input type="hidden" name="' . e($feldName . (is_array($wert) ? '[]' : '')) . '" value="' . e(mb_substr($einzeln, 0, 8000)) . '">';
        }
    }
    $felder .= '<input type="hidden" name="fz" value="' . e(token_bauen('ok')) . '">';

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Anfrage bestätigen — Kortschak</title>'
        . '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#fbfbfd;color:#1d1d1f;display:grid;min-height:100vh;place-items:center;padding:24px}'
        . '.karte{max-width:520px;background:#fff;border:1px solid rgba(0,0,0,.09);border-radius:28px;padding:40px;box-shadow:0 12px 48px rgba(0,0,0,.10);text-align:center}'
        . 'h1{font-size:1.5rem;margin:0 0 10px}p{color:#515154;line-height:1.5;margin:0 0 24px}'
        . 'button{display:inline-block;background:#FF1C20;color:#fff;border:0;cursor:pointer;padding:12px 26px;border-radius:980px;font-weight:600;font-size:1rem}</style></head>'
        . '<body><div class="karte"><h1>Fast geschafft!</h1>'
        . '<p>Bitte bestätigen Sie noch kurz den Versand Ihrer Anfrage an die Kortschak Werbeagentur.</p>'
        . '<form action="/api/anfrage.php" method="post">' . $felder
        . '<button type="submit">Anfrage jetzt absenden</button></form></div></body></html>';
    exit;
}

function rate_limit_ok(): bool {
    $verzeichnis = sys_get_temp_dir() . '/kortschak-anfragen';
    if (!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0700, true)) { return true; }
    $stunde = date('YmdH');
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0';
    foreach ([['ip-' . sha1($ip . $stunde), 6], ['alle-' . $stunde, 40]] as [$schluessel, $limit]) {
        $datei = $verzeichnis . '/' . $schluessel;
        $n = (int)@file_get_contents($datei) + 1;
        if ($n > $limit) { return false; }
        @file_put_contents($datei, (string)$n, LOCK_EX);
    }
    // Alte Zaehler gelegentlich wegputzen
    if (random_int(1, 20) === 1) {
        foreach (glob($verzeichnis . '/*') ?: [] as $alt) {
            if (@filemtime($alt) < time() - 7200) { @unlink($alt); }
        }
    }
    return true;
}

// ------------------------------------------------------------ Mail-Aufbau

function mail_html(string $titel, string $introHtml, array $zeilen, string $nachricht, ?string $abschluss): string {
    $rot = '#FF1C20'; $orange = '#F18700'; $ink = '#1d1d1f'; $grau = '#86868b';

    $datenZeilen = '';
    foreach ($zeilen as [$label, $wert]) {
        $datenZeilen .= '<tr>'
            . '<td style="padding:9px 18px 9px 0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:' . $grau . ';white-space:nowrap;vertical-align:top;">' . e($label) . '</td>'
            . '<td style="padding:9px 0;font-size:15px;color:' . $ink . ';line-height:1.5;">' . e($wert) . '</td>'
            . '</tr>';
    }
    $datenTabelle = $datenZeilen === '' ? '' :
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:22px 0 0;border-top:1px solid #ececee;">' . $datenZeilen . '</table>';

    $nachrichtHtml = nl2br(e($nachricht));
    $abschlussHtml = $abschluss === null ? '' :
        '<p style="margin:26px 0 0;font-size:14px;line-height:1.6;color:#515154;">' . e($abschluss) . '</p>';

    return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($titel) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f5f5f7;">'
        . '<div style="display:none;max-height:0;overflow:hidden;">' . e(mb_substr(strip_tags($introHtml), 0, 140)) . '</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f5f5f7;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">'

        // Kopf: dunkle Marke mit dem echten Logo (weisses PNG @2x, auf der
        // Website gehostet – SVG koennen Mail-Clients nicht)
        . '<tr><td style="background:' . $ink . ';border-radius:20px 20px 0 0;padding:26px 36px;">'
        . '<img src="https://www.schriften-kortschak.at/assets/img/kortschak-logo-mail.png" width="200" height="49" alt="Kortschak Werbeagentur" style="display:block;border:0;width:200px;height:49px;">'
        . '</td></tr>'

        // Akzentlinie Rot -> Orange
        . '<tr><td style="height:4px;background:' . $rot . ';background:linear-gradient(90deg,' . $rot . ',' . $orange . ');font-size:0;line-height:0;">&nbsp;</td></tr>'

        // Inhalt
        . '<tr><td style="background:#ffffff;border-radius:0 0 20px 20px;padding:34px 36px 38px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
        . '<h1 style="margin:0 0 12px;font-size:22px;line-height:1.25;color:' . $ink . ';">' . e($titel) . '</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#515154;">' . $introHtml . '</p>'
        . $datenTabelle
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:22px 0 0;"><tr>'
        . '<td style="background:#f5f5f7;border-radius:14px;padding:20px 22px;">'
        . '<div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:' . $grau . ';padding-bottom:8px;">Nachricht</div>'
        . '<div style="font-size:15px;line-height:1.65;color:' . $ink . ';">' . $nachrichtHtml . '</div>'
        . '</td></tr></table>'
        . $abschlussHtml
        . '</td></tr>'

        // Fusszeile
        . '<tr><td style="padding:22px 36px 8px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.7;color:' . $grau . ';" align="center">'
        . 'Kortschak Schriften GmbH &middot; Bahnhofstraße 6 &middot; 8793 Trofaiach<br>'
        . '<a href="tel:+43384767666" style="color:' . $grau . ';text-decoration:none;">+43 3847 67666</a> &middot; '
        . '<a href="mailto:office@schriften-kortschak.at" style="color:' . $grau . ';text-decoration:none;">office@schriften-kortschak.at</a> &middot; '
        . '<a href="https://www.schriften-kortschak.at" style="color:' . $grau . ';text-decoration:underline;">schriften-kortschak.at</a>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function mail_text(string $intro, array $zeilen, string $nachricht): string {
    $t = "KORTSCHAK Werbeagentur\n\n" . $intro . "\n\n";
    foreach ($zeilen as [$label, $wert]) { $t .= $label . ': ' . $wert . "\n"; }
    $t .= "\nNachricht:\n" . $nachricht . "\n\n--\nKortschak Schriften GmbH · Bahnhofstraße 6 · 8793 Trofaiach\n+43 3847 67666 · office@schriften-kortschak.at · schriften-kortschak.at\n";
    return $t;
}

// --------------------------------------------------------------- Versand

function mail_senden(string $an, string $anName, string $betreff, string $html, string $text, ?array $antwortAn, ?array $anhang = null): void {
    $grenze  = 'grenze-' . bin2hex(random_bytes(12));
    $aussen  = 'aussen-' . bin2hex(random_bytes(12));
    $kopf = [
        'Date: ' . date('r'),
        'From: ' . kodiert(MAIL_ABSENDER_NAME) . ' <' . SMTP_USER . '>',
        'To: ' . kodiert($anName) . ' <' . $an . '>',
        'Subject: ' . kodiert($betreff),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@kortschak.online>',
        'MIME-Version: 1.0',
        $anhang === null
            ? 'Content-Type: multipart/alternative; boundary="' . $grenze . '"'
            : 'Content-Type: multipart/mixed; boundary="' . $aussen . '"',
    ];
    if ($antwortAn !== null) {
        $kopf[] = 'Reply-To: ' . kodiert($antwortAn[1]) . ' <' . $antwortAn[0] . '>';
    }
    $rumpf = '--' . $grenze . "\r\n"
        . "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode($text) . "\r\n"
        . '--' . $grenze . "\r\n"
        . "Content-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
        . quoted_printable_encode($html) . "\r\n"
        . '--' . $grenze . "--\r\n";
    if ($anhang !== null) {
        // Text/HTML als erster Teil, danach die Datei base64-kodiert
        [$dateiName, $mime, $daten] = $anhang;
        $rumpf = '--' . $aussen . "\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $grenze . '"' . "\r\n\r\n"
            . $rumpf
            . '--' . $aussen . "\r\n"
            . 'Content-Type: ' . $mime . '; name="' . $dateiName . '"' . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . $dateiName . '"' . "\r\n\r\n"
            . chunk_split(base64_encode($daten), 76, "\r\n")
            . '--' . $aussen . "--\r\n";
    }
    $roh = implode("\r\n", $kopf) . "\r\n\r\n" . $rumpf;

    if (MAIL_TRANSPORT === 'log') {
        $verzeichnis = sys_get_temp_dir() . '/kortschak-mail-log';
        @mkdir($verzeichnis, 0700, true);
        file_put_contents($verzeichnis . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . preg_replace('/[^a-z0-9]+/i', '_', $an) . '.eml', $roh);
        return;
    }
    smtp_senden($an, $roh);
}

function kodiert(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function smtp_senden(string $an, string $roh): void {
    $fehlerNr = 0; $fehlerText = '';
    $s = stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $fehlerNr, $fehlerText, 15);
    if (!$s) { throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $fehlerText); }
    stream_set_timeout($s, 20);

    $lies = function () use ($s): string {
        $antwort = '';
        while (($zeile = fgets($s, 2048)) !== false) {
            $antwort .= $zeile;
            if (!isset($zeile[3]) || $zeile[3] !== '-') { break; }   // letzte Zeile: "250 " statt "250-"
        }
        if ($antwort === '') { throw new RuntimeException('SMTP: keine Antwort'); }
        return $antwort;
    };
    $sende = function (string $befehl, array $ok) use ($s, $lies): string {
        fwrite($s, $befehl . "\r\n");
        $antwort = $lies();
        if (!in_array((int)substr($antwort, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP: unerwartete Antwort auf "' . preg_replace('/^(AUTH|[A-Za-z0-9+\/=]{8}).*/', '$1 …', $befehl) . '": ' . trim($antwort));
        }
        return $antwort;
    };

    $lies();                                                      // Begruessung 220
    $sende('EHLO kortschak.online', [250]);
    $sende('AUTH LOGIN', [334]);
    $sende(base64_encode(SMTP_USER), [334]);
    $sende(base64_encode(SMTP_PASS), [235]);
    $sende('MAIL FROM:<' . SMTP_USER . '>', [250]);
    $sende('RCPT TO:<' . $an . '>', [250, 251]);
    $sende('DATA', [354]);
    // Punkt-Verdopplung am Zeilenanfang (SMTP-Transparenz)
    $daten = preg_replace('/^\./m', '..', $roh);
    fwrite($s, $daten . "\r\n.\r\n");
    $abschluss = $lies();
    if ((int)substr($abschluss, 0, 3) !== 250) {
        throw new RuntimeException('SMTP: Zustellung abgelehnt: ' . trim($abschluss));
    }
    fwrite($s, "QUIT\r\n");
    fclose($s);
}
