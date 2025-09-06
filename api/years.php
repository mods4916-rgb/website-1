<?php
// api/years.php - manage list of chart years
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
  header('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
  header('Vary', 'Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function ok($extra = []) { echo json_encode(array_merge(['ok'=>true], $extra)); exit; }
function fail($code, $msg='Error') { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

require_once __DIR__ . '/db.php'; // may set $pdo and DB_FALLBACK
$useJson = (defined('DB_FALLBACK') && DB_FALLBACK) || !isset($pdo) || !$pdo;

// Returns the stored list of extra years (beyond baseline in chart.php)
function get_years_json() {
  $base = realpath(__DIR__ . '/..');
  $file = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'years.json';
  $years = [];
  if (is_file($file)) {
    $raw = @file_get_contents($file);
    $arr = json_decode($raw ?: '[]', true);
    if (is_array($arr)) $years = $arr;
  }
  return $years;
}
function put_years_json($years) {
  $base = realpath(__DIR__ . '/..');
  $dir = $base . DIRECTORY_SEPARATOR . 'data';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $file = $dir . DIRECTORY_SEPARATOR . 'years.json';
  if (@file_put_contents($file, json_encode(array_values($years), JSON_PRETTY_PRINT)) === false) return false;
  return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $baseline = [2025, 2024, 2023, 2022, 2021];
  if ($useJson) {
    $years = get_years_json();
    if (!is_array($years) || !count($years)) {
      $years = $baseline;
      put_years_json($years);
    }
    rsort($years, SORT_NUMERIC);
    ok(['years' => array_values($years)]);
  }
  try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS years (year INTEGER PRIMARY KEY)');
    $existing = [];
    $q = $pdo->query('SELECT year FROM years');
    while ($r = $q->fetch()) { $existing[] = (int)$r['year']; }
    if (!count($existing)) {
      $stmt = $pdo->prepare('INSERT OR IGNORE INTO years(year) VALUES(?)');
      foreach ($baseline as $y) { $stmt->execute([$y]); }
      $existing = $baseline;
    }
    rsort($existing, SORT_NUMERIC);
    ok(['years' => array_values($existing)]);
  } catch (Throwable $e) { fail(500, 'DB read failed'); }
}

// POST: add a new year (admin only)
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) { @session_write_close(); @session_id($m[1]); }
@session_start();
if (!isset($_SESSION['user'])) fail(401, 'Unauthorized');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];
$year = isset($body['year']) ? (int)$body['year'] : 0;
$remove = !empty($body['remove']);
if ($year < 2000 || $year > 3000) fail(400, 'Invalid year');

if ($useJson) {
  $list = get_years_json();
  if ($remove) {
    $list = array_values(array_filter($list, function($y) use ($year){ return (int)$y !== $year; }));
  } else {
    if (!in_array($year, $list, true)) { $list[] = $year; }
  }
  rsort($list, SORT_NUMERIC);
  if (!put_years_json($list)) fail(500, 'Failed to save');
  ok(['years' => $list]);
}

try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS years (year INTEGER PRIMARY KEY)');
  if ($remove) {
    $stmt = $pdo->prepare('DELETE FROM years WHERE year = ?');
    $stmt->execute([$year]);
  } else {
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO years(year) VALUES(?)');
    $stmt->execute([$year]);
  }
  $out = [];
  $q = $pdo->query('SELECT year FROM years ORDER BY year DESC');
  while ($r = $q->fetch()) { $out[] = (int)$r['year']; }
  ok(['years' => $out]);
} catch (Throwable $e) { fail(500, 'DB write failed'); }
