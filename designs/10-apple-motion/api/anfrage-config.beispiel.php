<?php
// Konfiguration Formularversand — Kopie als anfrage-config.php anlegen und
// das SMTP-Passwort eintragen. anfrage-config.php ist per .gitignore vom
// Repo ausgeschlossen (oeffentliches GitHub-Repo!) und wandert nur ins
// Deploy-Paket.

const SMTP_HOST = 'smtp.hostinger.com';
const SMTP_PORT = 465;                       // SSL
const SMTP_USER = 'mail@kortschak.online';
const SMTP_PASS = 'HIER-PASSWORT-EINTRAGEN';

// Anzeige-Absender der Mails (Postfach muss SMTP_USER sein, sonst lehnt
// Hostinger den Versand ab).
const MAIL_ABSENDER_NAME = 'Kortschak Werbeagentur';

// Wohin die Anfragen gehen:
const MAIL_EMPFAENGER = 'office@schriften-kortschak.at';

// Antworten auf die Bestaetigungs-Mail an den Kunden landen hier:
const MAIL_ANTWORT_AN = 'office@schriften-kortschak.at';

// 'smtp' = echter Versand · 'log' = Mails nur als .eml-Dateien ablegen
// (lokales Testen ohne Zugangsdaten; Rate-Limit ist im log-Modus aus)
const MAIL_TRANSPORT = 'smtp';
