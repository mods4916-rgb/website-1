<?php
// api/login.php - JSON login endpoint using SQLite users table

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

@session_start();

function json_ok($extra = []) {
  echo json_encode(array_merge(['ok' => true], $extra));
  exit;
}
function json_fail($code, $msg = 'Error') {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_fail(405, 'Method Not Allowed');
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];
$username = isset($body['username']) ? trim((string)$body['username']) : '';
$password = isset($body['password']) ? (string)$body['password'] : '';

require_once __DIR__ . '/db.php'; // provides $pdo or sets DB_FALLBACK

// Fallback mode: accept default credentials when DB is unavailable
if (defined('DB_FALLBACK') && DB_FALLBACK) {
  $DEF_USER = 'sagar';
  $DEF_PASS = 'sagar12390';
  if ($username === $DEF_USER && $password === $DEF_PASS) {
    $_SESSION['user'] = $DEF_USER;
    $token = session_id();
    json_ok(['token' => $token, 'fallback' => true]);
  }
  json_fail(401, 'Invalid credentials');
}

try {
  $stmt = $pdo->prepare('SELECT username, password_hash FROM users WHERE username = ? LIMIT 1');
  $stmt->execute([$username]);
  $row = $stmt->fetch();
  if ($row && password_verify($password, $row['password_hash'])) {
    $_SESSION['user'] = $row['username'];
    $token = session_id();
    json_ok(['token' => $token]);
  }
} catch (Throwable $e) {
  json_fail(500, 'Auth failed');
}

json_fail(401, 'Invalid credentials');

