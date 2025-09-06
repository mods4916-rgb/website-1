<?php
// api/games.php - get/set display name/time per game
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function ok($extra = []) { echo json_encode(array_merge(['ok'=>true], $extra)); exit; }
function fail($code, $msg='Error') { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$slug = isset($_GET['slug']) ? strtolower(preg_replace('/[^a-z0-9-]+/i','-', $_GET['slug'])) : '';

require_once __DIR__ . '/db.php'; // may set $pdo and DB_FALLBACK
$useJson = (defined('DB_FALLBACK') && DB_FALLBACK) || !isset($pdo) || !$pdo;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if ($useJson) {
    $base = realpath(__DIR__ . '/..');
    $file = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'games.json';
    $all = [];
    if (is_file($file)) {
      $raw = @file_get_contents($file);
      $data = json_decode($raw ?: '[]', true);
      if (is_array($data)) $all = $data;
    }
    if ($slug) {
      $row = isset($all[$slug]) && is_array($all[$slug]) ? $all[$slug] : [];
      ok(['data' => [$slug => $row]]);
    }
    ok(['data' => $all]);
  }
  try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS games (
      slug TEXT PRIMARY KEY,
      display_name TEXT,
      display_time TEXT,
      updated_at INTEGER NOT NULL
    );');
    if ($slug) {
      $stmt = $pdo->prepare('SELECT slug, display_name, display_time FROM games WHERE slug = ? LIMIT 1');
      $stmt->execute([$slug]);
      $row = $stmt->fetch();
      $out = $row ? [ $row['slug'] => ['name'=>$row['display_name'], 'time'=>$row['display_time']] ] : [];
      ok(['data' => $out]);
    }
    $stmt = $pdo->query('SELECT slug, display_name, display_time FROM games');
    $out = [];
    while ($r = $stmt->fetch()) { $out[$r['slug']] = ['name'=>$r['display_name'], 'time'=>$r['display_time']]; }
    ok(['data' => $out]);
  } catch (Throwable $e) { fail(500, 'DB read failed'); }
}

// POST: upsert name/time (admin only)
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
  @session_write_close();
  @session_id($m[1]);
}
@session_start();
if (!isset($_SESSION['user'])) fail(401, 'Unauthorized');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];
$slugBody = isset($body['slug']) ? strtolower(preg_replace('/[^a-z0-9-]+/i','-', (string)$body['slug'])) : '';
$name = isset($body['name']) ? (string)$body['name'] : null;
$time = isset($body['time']) ? (string)$body['time'] : null;
if ($slugBody === '') fail(400, 'Missing slug');

if ($useJson) {
  $base = realpath(__DIR__ . '/..');
  $dir = $base . DIRECTORY_SEPARATOR . 'data';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $file = $dir . DIRECTORY_SEPARATOR . 'games.json';
  $all = [];
  if (is_file($file)) {
    $rawOld = @file_get_contents($file);
    $data = json_decode($rawOld ?: '[]', true);
    if (is_array($data)) $all = $data;
  }
  if (!isset($all[$slugBody]) || !is_array($all[$slugBody])) $all[$slugBody] = [];
  if ($name !== null) $all[$slugBody]['name'] = $name;
  if ($time !== null) $all[$slugBody]['time'] = $time;
  if (@file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT)) === false) fail(500, 'Failed to save');
  ok();
}

try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS games (
    slug TEXT PRIMARY KEY,
    display_name TEXT,
    display_time TEXT,
    updated_at INTEGER NOT NULL
  );');
  $stmt = $pdo->prepare('INSERT INTO games(slug, display_name, display_time, updated_at) VALUES(?,?,?,?)
                         ON CONFLICT(slug) DO UPDATE SET display_name=COALESCE(excluded.display_name, games.display_name), display_time=COALESCE(excluded.display_time, games.display_time), updated_at=excluded.updated_at');
  $stmt->execute([$slugBody, $name, $time, time()]);
  ok();
} catch (Throwable $e) { fail(500, 'DB write failed'); }
