<?php
// Formular-Backend der Kortschak-Website.
// Nimmt beide Formulare entgegen (Startseite "kontakt", Unterseite
// "social-media"), verschickt zwei Mails: Benachrichtigung an das Buero
// (Reply-To = Kunde) und eine Bestaetigung an den Kunden.
// Versand per SMTP ueber das Hostinger-Postfach aus anfrage-config.php.

declare(strict_types=1);

date_default_timezone_set('Europe/Vienna');
header('X-Robots-Tag: noindex');

$configDatei = __DIR__ . '/anfrage-config.php';
if (!is_file($configDatei)) {
    antwort(false, 'Der Mailversand ist noch nicht konfiguriert.', 500);
}
require $configDatei;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    antwort(false, 'Nur POST-Anfragen sind erlaubt.', 405);
}

// ---------------------------------------------------------------- Eingaben

$formular = feld('formular');
if (!in_array($formular, ['kontakt', 'social-media'], true)) {
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
if ($nachricht === '') { $fehler[] = 'Bitte schreiben Sie uns eine Nachricht.'; }
if (!$datenschutz)     { $fehler[] = 'Bitte bestätigen Sie die Datenschutzerklärung.'; }

$zeilen = [];   // Label/Wert-Paare fuer die Mail an das Buero
$zeilen[] = ['Name', $name];
$zeilen[] = ['E-Mail', $email];

if ($formular === 'kontakt') {
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

// ------------------------------------------------------------- Rate-Limit

if (MAIL_TRANSPORT !== 'log' && !rate_limit_ok()) {
    antwort(false, 'Es wurden gerade sehr viele Anfragen gesendet. Bitte versuchen Sie es in einer Stunde noch einmal — oder schreiben Sie direkt an ' . MAIL_EMPFAENGER . '.', 429);
}

// -------------------------------------------------------------- Mails bauen

$istKontakt  = $formular === 'kontakt';
$formularOrt = $istKontakt ? 'Kontaktformular Startseite' : 'Formular Social-Media-Marketing';
$betreff     = ($istKontakt ? 'Website-Anfrage von ' : 'Social-Media-Anfrage von ') . $name
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
$zeilenKunde = array_values(array_filter($zeilen, fn($z) => !in_array($z[0], ['Name', 'E-Mail'], true)));
$mailKunde = mail_html(
    'Ihre Anfrage ist bei uns angekommen',
    'Hallo ' . e($vorname) . ', vielen Dank für Ihre Nachricht! Wir haben Ihre Anfrage erhalten und melden uns so schnell wie möglich — in der Regel innerhalb eines Werktags. Zur Sicherheit fassen wir hier noch einmal zusammen, was Sie uns geschickt haben.',
    $zeilenKunde,
    $nachricht,
    'Sie möchten etwas ergänzen? Antworten Sie einfach auf diese E-Mail oder rufen Sie uns an: +43 3847 67666.'
);
$textKunde = mail_text('Vielen Dank fuer Ihre Anfrage! Wir melden uns in der Regel innerhalb eines Werktags.', $zeilenKunde, $nachricht);

// ------------------------------------------------------------------ Versand

try {
    mail_senden(MAIL_EMPFAENGER, 'Kortschak Website', $betreff, $mailBuero, $textBuero, [$email, $name]);
} catch (Throwable $t) {
    error_log('[anfrage.php] Versand an Buero fehlgeschlagen: ' . $t->getMessage());
    antwort(false, 'Ihre Anfrage konnte gerade nicht übermittelt werden. Bitte versuchen Sie es später noch einmal — oder schreiben Sie direkt an ' . MAIL_EMPFAENGER . '.', 502);
}

try {
    mail_senden($email, $name, 'Ihre Anfrage bei Kortschak — wir melden uns!', $mailKunde, $textKunde, [MAIL_ANTWORT_AN, 'Kortschak Werbeagentur']);
} catch (Throwable $t) {
    // Anfrage ist beim Buero angekommen — Bestaetigungsfehler nicht dem Kunden anlasten.
    error_log('[anfrage.php] Bestaetigung an Kunden fehlgeschlagen: ' . $t->getMessage());
}

antwort(true, 'Vielen Dank, ' . $vorname . '! Ihre Anfrage ist unterwegs — eine Bestätigung ist auf dem Weg in Ihr Postfach.');

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

        // Kopf: dunkle Marke
        . '<tr><td style="background:' . $ink . ';border-radius:20px 20px 0 0;padding:26px 36px;">'
        . '<span style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:21px;font-weight:800;letter-spacing:.02em;color:#ffffff;">KORTSCHAK<span style="color:' . $rot . ';">.</span></span>'
        . '<span style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.28em;color:#9a9aa1;padding-left:14px;">WERBEAGENTUR</span>'
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

function mail_senden(string $an, string $anName, string $betreff, string $html, string $text, ?array $antwortAn): void {
    $grenze  = 'grenze-' . bin2hex(random_bytes(12));
    $kopf = [
        'Date: ' . date('r'),
        'From: ' . kodiert(MAIL_ABSENDER_NAME) . ' <' . SMTP_USER . '>',
        'To: ' . kodiert($anName) . ' <' . $an . '>',
        'Subject: ' . kodiert($betreff),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@kortschak.online>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $grenze . '"',
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
