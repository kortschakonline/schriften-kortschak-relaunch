<?php
/**
 * Trofaiach Racer – Online-Bestenliste
 * ------------------------------------
 * Ablage: neben dem Spiel auf den Webserver kopieren (z. B. /games/racer/api/bestenliste.php)
 * und im Spiel die Konstante LEADERBOARD_URL auf diese Adresse setzen.
 *
 *   GET  ?track=stadt|praebichl   → { "entries": [ { name, time, kind, date }, … ] }  (Top 20, Zeit in ms)
 *   POST { track, name, time, kind } (JSON)  → { "ok": true }
 *
 * Speicherung als JSON-Datei im Unterordner data/ (wird automatisch angelegt, per .htaccess gesperrt).
 * Einfache Plausibilitätsprüfung + Drosselung je IP gegen Spam. Keine personenbezogenen Daten außer dem Spitznamen.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Nur für die eigene Domain freigeben, falls das Spiel woanders liegt (sonst diese Zeile entfernen):
// header('Access-Control-Allow-Origin: https://www.schriften-kortschak.at');

const TRACKS = ['stadt', 'praebichl'];
const KINDS = ['race', 'time', 'duo'];
const MAX_ENTRIES = 20;
const MIN_MS = 100000;   // 3 Runden unter 1:40 sind nicht realistisch
const MAX_MS = 900000;   // über 15 Minuten wird nicht gewertet

// Ablage AUSSERHALB des Webroots: der Website-Deploy ersetzt public_html komplett, die Bestenliste bleibt so erhalten.
$dir = dirname(rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/')) . '/trofaiach-racer-bestenliste';
if (empty($_SERVER['DOCUMENT_ROOT']) || (!is_dir($dir) && !@mkdir($dir, 0750, true))) $dir = __DIR__ . '/data';
if (!is_dir($dir)) mkdir($dir, 0750, true);
if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function loadBoard(string $file): array {
    if (!is_file($file)) return [];
    $j = json_decode((string) file_get_contents($file), true);
    return is_array($j) ? $j : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $track = $_GET['track'] ?? 'stadt';
    if (!in_array($track, TRACKS, true)) fail(400, 'Unbekannte Strecke');
    echo json_encode(['entries' => array_slice(loadBoard("$dir/$track.json"), 0, MAX_ENTRIES)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') fail(405, 'Methode nicht erlaubt');

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) fail(400, 'Ungültige Daten');

$track = (string) ($in['track'] ?? '');
$kind = (string) ($in['kind'] ?? 'race');
$time = (int) ($in['time'] ?? 0);
$name = trim(preg_replace('/[^\p{L}\p{N} ._\-]/u', '', (string) ($in['name'] ?? '')));
$name = mb_substr($name, 0, 16);

if (!in_array($track, TRACKS, true)) fail(400, 'Unbekannte Strecke');
if (!in_array($kind, KINDS, true)) $kind = 'race';
if ($time < MIN_MS || $time > MAX_MS) fail(400, 'Zeit nicht plausibel');
if ($name === '') $name = 'Anonym';

// Drosselung: höchstens 1 Eintrag je IP alle 20 Sekunden
$ipKey = $dir . '/ip_' . hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'trofaiach');
if (is_file($ipKey) && time() - filemtime($ipKey) < 20) fail(429, 'Bitte kurz warten');
touch($ipKey);

$file = "$dir/$track.json";
$fp = fopen($file, 'c+');
if (!$fp) fail(500, 'Speichern nicht möglich');
flock($fp, LOCK_EX);
$board = json_decode(stream_get_contents($fp) ?: '[]', true) ?: [];
$board[] = ['name' => $name, 'time' => $time, 'kind' => $kind, 'date' => date('Y-m-d')];
usort($board, fn($a, $b) => $a['time'] <=> $b['time']);
$board = array_slice($board, 0, MAX_ENTRIES);
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($board, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['ok' => true]);
