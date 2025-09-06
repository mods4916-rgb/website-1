<?php
// api/db.php - SQLite connection helper with graceful fallback
// Creates data/app.db if it does not exist and ensures required tables exist.

declare(strict_types=1);

// If PDO or SQLite driver is unavailable, we set DB_FALLBACK to true and let callers use filesystem JSON.
if (!class_exists('PDO')) {
  if (!defined('DB_FALLBACK')) define('DB_FALLBACK', true);
  return;
}

// Allow user override via api/db-local.php (define $DB_PATH there)
// Example Windows path: 'C:\\absolute\\path\\to\\my.db'
$DB_PATH = null;
@include __DIR__ . DIRECTORY_SEPARATOR . 'db-local.php';
if (!$DB_PATH) {
  $DB_PATH = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app.db';
}
$DB_DIR = dirname($DB_PATH);
if (!is_dir($DB_DIR)) {
  @mkdir($DB_DIR, 0775, true);
}

try {
  $pdo = new PDO('sqlite:' . $DB_PATH);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  if (!defined('DB_FALLBACK')) define('DB_FALLBACK', false);

  // Initialize schema
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

  // Ensure default admin exists if table is empty (user: sagar / pass: sagar12390)
  $row = $pdo->query('SELECT COUNT(1) AS c FROM users')->fetch();
  if (isset($row['c']) && (int)$row['c'] === 0) {
    $username = 'sagar';
    $pass = 'sagar12390';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users(username, password_hash, created_at) VALUES(?,?,?)');
    $stmt->execute([$username, $hash, time()]);
  }
} catch (Throwable $e) {
  // Fall back instead of exiting; callers can use filesystem JSON mode
  if (!defined('DB_FALLBACK')) define('DB_FALLBACK', true);
  $pdo = null;
  // Do not output; API endpoints will handle responses
  return;
}
