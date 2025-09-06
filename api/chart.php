<?php
// api/chart.php - get/set year chart data per game (SQLite-backed)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
  header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
  header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function json_ok($extra = []) { echo json_encode(array_merge(['ok'=>true], $extra)); exit; }
function json_fail($code, $msg='Error') { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// Normalize inputs
$game = isset($_GET['game']) ? strtolower(preg_replace('/[^a-z0-9-]+/i','-', $_GET['game'])) : '';
$year = isset($_GET['year']) ? preg_replace('/[^0-9]/','', $_GET['year']) : '';
if ($year === '') { $year = (string)date('Y'); }
 $wantLatest = !empty($_GET['latest']);

require_once __DIR__ . '/db.php'; // provides $pdo and ensures schema

// If PDO/SQLite is unavailable, fall back to filesystem JSON store
$useJson = (defined('DB_FALLBACK') && DB_FALLBACK) || !isset($pdo) || !$pdo; 

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if ($wantLatest) {
    if ($useJson) {
      // Fallback: scan data/chart/*/<year>.json and pick the latest day_key present
      $baseDir = realpath(__DIR__ . '/..');
      $chartDir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chart';
      $best = null; // ['game'=>slug,'year'=>year,'day_key'=>dd-mm,'value'=>val,'ts'=>timestamp]
      if (is_dir($chartDir)) {
        $it = @new DirectoryIterator($chartDir);
        foreach ($it as $entry) {
          if ($entry->isDot() || !$entry->isDir()) continue;
          $slug = $entry->getFilename();
          $file = $entry->getPathname() . DIRECTORY_SEPARATOR . $year . '.json';
          if (!is_file($file)) continue;
          $raw = @file_get_contents($file);
          $data = json_decode($raw ?: '[]', true);
          if (!is_array($data)) continue;
          foreach ($data as $k => $v) {
            if (!preg_match('/^\d{2}-\d{2}$/', (string)$k)) continue;
            $parts = explode('-', (string)$k);
            $d = (int)$parts[0]; $m = (int)$parts[1];
            $ts = @mktime(0,0,0,$m,$d,(int)$year) ?: 0;
            if ($v === '' || $v === '-') continue;
            if (!$best || $ts > $best['ts']) {
              $best = ['game'=>$slug,'year'=>$year,'day_key'=>(string)$k,'value'=>(string)$v,'ts'=>$ts];
            }
          }
        }
      }
      if (!$best) { json_ok(['latest' => (object)[]]); }
      unset($best['ts']);
      json_ok(['latest' => $best]);
    }
    try {
      $stmt = $pdo->prepare('SELECT game_slug, year, day_key, value, updated_at FROM results WHERE year = ? AND value != "" ORDER BY updated_at DESC LIMIT 1');
      $stmt->execute([$year]);
      $row = $stmt->fetch();
      if (!$row) { json_ok(['latest' => (object)[]]); }
      json_ok(['latest' => [
        'game' => (string)$row['game_slug'],
        'year' => (string)$row['year'],
        'day_key' => (string)$row['day_key'],
        'value' => (string)$row['value']
      ]]);
    } catch (Throwable $e) { json_fail(500, 'DB read failed'); }
  }
  if ($useJson) {
    $baseDir = realpath(__DIR__ . '/..');
    $dir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chart' . DIRECTORY_SEPARATOR . ($game ?: 'game');
    $file = $dir . DIRECTORY_SEPARATOR . $year . '.json';
    if (!is_file($file)) { json_ok(['data' => (object)[]]); }
    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) $data = [];
    json_ok(['data' => $data]);
  }
  try {
    $stmt = $pdo->prepare('SELECT day_key, value FROM results WHERE game_slug = ? AND year = ?');
    $stmt->execute([$game ?: 'game', $year]);
    $out = [];
    while ($row = $stmt->fetch()) { $out[$row['day_key']] = $row['value']; }
    json_ok(['data' => $out]);
  } catch (Throwable $e) { json_fail(500, 'DB read failed'); }
}

// For POST, authentication required
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
  @session_write_close();
  @session_id($m[1]);
}
@session_start();
if (!isset($_SESSION['user'])) { json_fail(401, 'Unauthorized'); }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];
$key = isset($body['key']) ? (string)$body['key'] : '';
$value = array_key_exists('value', $body) ? (string)$body['value'] : '';

if ($key === '') json_fail(400, 'Missing key');

if ($useJson) {
  $baseDir = realpath(__DIR__ . '/..');
  $dir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chart' . DIRECTORY_SEPARATOR . ($game ?: 'game');
  $file = $dir . DIRECTORY_SEPARATOR . $year . '.json';
  if (!is_dir($dir)) { if (!@mkdir($dir, 0775, true)) json_fail(500, 'Failed to save'); }
  $existing = [];
  if (is_file($file)) {
    $ex = json_decode(@file_get_contents($file) ?: '[]', true);
    if (is_array($ex)) $existing = $ex;
  }
  if ($value === '' || $value === '-') { unset($existing[$key]); }
  else { $existing[$key] = $value; }
  if (@file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT)) === false) {
    json_fail(500, 'Failed to save');
  }
  json_ok();
}

try {
  if ($value === '' || $value === '-') {
    $stmt = $pdo->prepare('DELETE FROM results WHERE game_slug = ? AND year = ? AND day_key = ?');
    $stmt->execute([$game ?: 'game', $year, $key]);
  } else {
    $stmt = $pdo->prepare('INSERT INTO results(game_slug, year, day_key, value, updated_at) VALUES(?,?,?,?,?)
                           ON CONFLICT(game_slug, year, day_key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at');
    $stmt->execute([$game ?: 'game', $year, $key, $value, time()]);
  }
  json_ok();
} catch (Throwable $e) { json_fail(500, 'DB write failed'); }
