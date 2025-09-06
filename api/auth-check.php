<?php
// api/auth-check.php - verifies admin session
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

// If Authorization: Bearer <sid> is provided, use it as the session id
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
  @session_write_close();
  @session_id($m[1]);
}
@session_start();

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
if ($user) {
  echo json_encode(['ok' => true]);
} else {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
}
