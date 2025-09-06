<?php
// api/db-init.php - Initialize SQLite schema and optionally migrate JSON chart data
// Auth required (admin). Returns JSON.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');
if (isset($_SERVER['HTTP_ORIGIN'])) {
  header('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
  header('Vary', 'Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

function ok($data = []) { echo json_encode(array_merge(['ok'=>true], $data)); exit; }
function fail($code, $msg='Error') { http_response_code($code); echo json_encode(['ok'=>false, 'error'=>$msg]); exit; }

// Accept Bearer <sid> as session id
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
  @session_write_close();
  @session_id($m[1]);
}
@session_start();
if (!isset($_SESSION['user'])) fail(401, 'Unauthorized');

require_once __DIR__ . '/db.php'; // may set $pdo or DB_FALLBACK

if (defined('DB_FALLBACK') && DB_FALLBACK) {
  fail(500, 'SQLite not available. Ensure pdo_sqlite & sqlite3 are enabled.');
}

if (!$pdo) fail(500, 'DB unavailable');

// Ensure schema exists (db.php normally does this already)
try {
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS results (
       game_slug TEXT NOT NULL,
       year      TEXT NOT NULL,
       day_key   TEXT NOT NULL,
       value     TEXT NOT NULL,
       updated_at INTEGER NOT NULL,
       PRIMARY KEY (game_slug, year, day_key)
     );'
  );
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS users (
       username TEXT PRIMARY KEY,
       password_hash TEXT NOT NULL,
       created_at INTEGER NOT NULL
     );'
  );
} catch (Throwable $e) {
  fail(500, 'Schema init failed');
}

$method = $_SERVER['REQUEST_METHOD'];
$doMigrate = ($method === 'POST') && (!empty($_GET['migrate']) || !empty($_POST['migrate']));

if (!$doMigrate) {
  ok(['message' => 'DB initialized', 'details' => 'Use POST ?migrate=1 to import JSON chart data']);
}

// Migrate data/chart/<game>/<year>.json into results
$baseDir = realpath(__DIR__ . '/..');
$chartDir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chart';
if (!is_dir($chartDir)) ok(['migrated' => 0, 'skipped' => 0, 'note' => 'No JSON chart directory found']);

$migrated = 0; $skipped = 0; $errors = 0; $games = 0; $files = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($chartDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fileInfo) {
  if (!$fileInfo->isFile()) continue;
  if (strtolower($fileInfo->getExtension()) !== 'json') continue;
  $files++;
  // Expect path: data/chart/<game>/<year>.json
  $path = $fileInfo->getPathname();
  $game = basename(dirname($path));
  $year = basename($path, '.json');
  if (!preg_match('/^\d{4}$/', $year)) { $skipped++; continue; }
  $raw = @file_get_contents($path);
  $data = json_decode($raw ?: '[]', true);
  if (!is_array($data)) { $skipped++; continue; }
  try {
    $pdo->beginTransaction();
    foreach ($data as $dayKey => $value) {
      $dayKey = (string)$dayKey;
      $value  = (string)$value;
      if (!preg_match('/^\d{2}-\d{2}$/', $dayKey)) continue;
      if ($value === '' || $value === '-') continue;
      $stmt = $pdo->prepare('INSERT INTO results(game_slug, year, day_key, value, updated_at) VALUES(?,?,?,?,?)
                             ON CONFLICT(game_slug, year, day_key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at');
      $stmt->execute([$game, $year, $dayKey, $value, time()]);
      $migrated++;
    }
    $pdo->commit();
    $games++;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errors++;
  }
}

ok(['message' => 'Migration completed', 'games' => $games, 'files' => $files, 'migrated' => $migrated, 'skipped' => $skipped, 'errors' => $errors]);
