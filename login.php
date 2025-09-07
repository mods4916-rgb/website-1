<?php
// login.php - PHP version of login.html
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #fff; }
    .navbar { display: flex; justify-content: center; gap: 150px; margin: 20px 0; }
    .navbar a { text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: 2px solid black; background: yellow; }
    .navbar a.chart { background: orange; border: none; }
    .header { text-align: center; background: linear-gradient(to right, orange, yellow); padding: 30px 10px; }
    .header h2 { margin: 0; color: #444; font-size: 22px; letter-spacing: 2px; }
    .title-header { text-align: center; background: #fff; padding: 16px 10px; border-bottom: 3px solid orange; margin-top: 8px; }
    .title-header h1 { margin: 0; font-size: 34px; color: #222; letter-spacing: 1px; }
    .card { max-width: 420px; margin: 30px auto; border: 1px solid #ddd; border-radius: 12px; padding: 20px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
    .form-row { margin-bottom: 14px; }
    label { display: block; margin-bottom: 6px; font-weight: bold; }
    input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 16px; }
    button { width: 100%; padding: 12px; font-weight: bold; border-radius: 10px; border: 2px solid black; background: orange; cursor: pointer; }
    .error { color: #b00020; margin-top: 8px; min-height: 20px; }
    .hint { color: #666; font-size: 13px; margin-top: 8px; }
  </style>
</head>
<body>
  <div class="navbar">
    <a href="index.php">HOME</a>
    <a href="chart.php" class="chart">CHART</a>
    <a href="login.php">LOGIN</a>
  </div>
  <div class="header"><h2>LUCKY-SATTA</h2></div>
  <div class="title-header"><h1>Admin Login</h1></div>
  <div class="login-card" style="max-width:420px;margin:30px auto;border:1px solid #ddd;border-radius:12px;padding:20px;box-shadow:0 6px 20px rgba(0,0,0,0.08);">
    <h2 style="margin-top:0">Admin Access</h2>
    <form id="loginForm">
      <div class="form-row">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" required />
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required />
      </div>
      <button type="submit">Login</button>
    </form>
    <div id="error" class="error" style="margin-top:12px;"></div>
  </div>
  <script>
    const API_BASE = '';
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const err = document.getElementById('error');
      err.textContent = '';
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      try {
        const res = await fetch(`${API_BASE}/api/login.php`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });
        if (!res.ok) {
          const j = await res.json().catch(() => ({}));
          throw new Error(j.error || 'Login failed');
        }
        const data = await res.json().catch(() => ({}));
        if (!data || !data.ok) { throw new Error('Login failed'); }
        const sid = data.token;
        if (sid) { window.location.href = `admin.php?sid=${encodeURIComponent(sid)}`; }
        else { window.location.href = 'admin.php'; }
      } catch (e) {
        err.textContent = e.message || 'Login failed';
      }
    });
  </script>
</body>
</html>
