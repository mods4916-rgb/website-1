<?php
// api/save-page.php - save edited page content (admin only)
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

// Support bearer token that represents PHP session id
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
  @session_write_close();
  @session_id($m[1]);
}
@session_start();

function json_ok($extra = []) { echo json_encode(array_merge(['ok'=>true], $extra)); exit; }
function json_fail($code, $msg='Error') { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

if (!isset($_SESSION['user'])) {
  json_fail(401, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_fail(405, 'Method Not Allowed');
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];
$rel = isset($body['path']) ? (string)$body['path'] : '';
$rel = preg_replace('/^[\\\/]+/', '', $rel);
if ($rel === '') json_fail(400, 'Missing path');
// Only allow saving .php inside current directory
if (!preg_match('/\.php$/i', $rel)) json_fail(400, 'Only .php files are allowed');
$baseDir = realpath(__DIR__ . '/..');
$full = realpath($baseDir . DIRECTORY_SEPARATOR . $rel) ?: ($baseDir . DIRECTORY_SEPARATOR . $rel);
if (strpos($full, $baseDir) !== 0) json_fail(400, 'Invalid path');
$html = isset($body['html']) ? (string)$body['html'] : '';

// Ensure parent directory exists
$dir = dirname($full);
if (!is_dir($dir)) {
  if (!mkdir($dir, 0775, true)) json_fail(500, 'Failed to save');
}

if (file_put_contents($full, $html) === false) {
  json_fail(500, 'Failed to save');
}
json_ok();
