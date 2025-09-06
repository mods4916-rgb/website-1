<?php
// year.php - PHP version of year.html
?>
<!DOCTYPE html>
<html lang="en"><head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Year Chart</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #fff; }
    .navbar { display: flex; justify-content: center; gap: 150px; margin: 20px 0; }
    .navbar a { text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: 2px solid black; background: yellow; }
    .navbar a.chart { background: orange; border: none; }
    .header { text-align: center; background: linear-gradient(to right, orange, yellow); padding: 30px 10px; }
    .header h2 { margin: 0; color: #444; font-size: 22px; letter-spacing: 2px; }
    .title-header { text-align: center; background: #fff; padding: 16px 10px; border-bottom: 3px solid orange; margin-top: 8px; }
    .title-header h1 { margin: 0; font-size: 34px; color: #222; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; text-align: center; margin: 20px auto; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background: yellow; font-weight: bold; position: sticky; top: 0; z-index: 1; }
    td:first-child { background: #ff4d00; color: #fff; font-weight: bold; text-align: left; padding-left: 15px; position: sticky; left: 0; z-index: 1; }
    .container { overflow: auto; }
    .note { text-align: right; font-size: 14px; margin: 5px 20px; }
  </style>
</head>
<body>
  <script>
    // Client-side guard: disable editability unless admin preview and auth OK
    (function(){
      function snapshotEditable(){ try { document.querySelectorAll('[contenteditable="true"]').forEach(function(el){ el.setAttribute('data-was-editable','1'); }); } catch(_){ }
      }
      function lockEditing(){ try { document.querySelectorAll('[contenteditable="true"]').forEach(function(el){ el.setAttribute('contenteditable','false'); }); } catch(_){ }
      }
      function unlockEditing(){ try { document.querySelectorAll('[data-was-editable="1"]').forEach(function(el){ el.setAttribute('contenteditable','true'); el.removeAttribute('data-was-editable'); }); } catch(_){ }
      }
      function init(){
        snapshotEditable();
        var usp = new URLSearchParams(location.search);
        var wantsAdmin = usp.get('admin') === '1';
        if (!wantsAdmin) { lockEditing(); return; }
        try {
          fetch('/api/auth-check.php', { credentials: 'include' })
            .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
            .then(function(j){ if (j && j.ok) { unlockEditing(); } else { lockEditing(); } })
            .catch(function(){ lockEditing(); });
        } catch(_) { lockEditing(); }
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
  </script>
  <div class="navbar" style="display: flex; justify-content: center; gap: 150px; margin: 20px 0px;" contenteditable="false"><a href="index.php" style="text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: 2px solid black; background: yellow;" contenteditable="true">HOME</a><a href="chart.php" class="chart" style="text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: none; background: orange;" contenteditable="true">CHART</a></div>

  <div class="header" style="background: linear-gradient(to right, orange, yellow);" contenteditable="false">
    <h2 contenteditable="true">LUCKY-SATTA</h2>
  </div>

  <div class="title-header" contenteditable="false">
    <h1 id="gameTitle" contenteditable="true">GAME</h1>
  </div>
  <script>
    // Early header set to avoid any stale content before main script runs
    (function(){
      try {
        const p = new URLSearchParams(location.search);
        const y = String(p.get('year') || new Date().getFullYear());
        const gq = (p.get('game')||'').trim();
        const label = (p.get('label')||'').trim();
        let disp = 'GAME';
        if (label) {
          disp = label;
        } else if (gq) {
          disp = gq.split('-').filter(Boolean).map(w => w.charAt(0).toUpperCase()+w.slice(1)).join(' ');
        } else {
          const m = (location.pathname||'').match(/\byear-([a-z0-9-]+)\.(html|php)$/);
          if (m && m[1]) {
            const slug = m[1];
            disp = slug.split('-').filter(Boolean).map(w => w.charAt(0).toUpperCase()+w.slice(1)).join(' ');
          }
        }
        const h1 = document.getElementById('gameTitle');
        if (h1) h1.textContent = `${disp} - ${y} CHART`;
        try { document.title = `${disp} ${y} Chart`; } catch {}
      } catch(e){}
    })();
  </script>

  <div class="container" contenteditable="false">
    <table id="yearTable" contenteditable="false"></table>
  </div>

  <script>
    (function () {
      const params = new URLSearchParams(window.location.search);
      const isAdmin = params.get('admin') === '1' || params.get('admin') === 1 || params.get('admin') === true;
      const year = String(params.get('year') || new Date().getFullYear());
      const fromQuery = params.get('game');
      const labelParam = (params.get('label') || '').trim();
      let displayGame;
      let apiGame;
      if (fromQuery && fromQuery.trim()) {
        apiGame = fromQuery.trim();
        displayGame = labelParam || apiGame.split('-').filter(Boolean).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
      } else {
        const m = (location.pathname || '').match(/\byear-([a-z0-9-]+)\.(html|php)$/);
        if (m && m[1]) {
          const slug = m[1];
          apiGame = slug;
          displayGame = labelParam || slug.split('-').filter(Boolean).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        } else {
          apiGame = 'game';
          displayGame = labelParam || 'GAME';
        }
      }

      const titleEl = document.getElementById('gameTitle');
      if (titleEl) {
        titleEl.textContent = `${displayGame} - ${year} CHART`;
      }
      try { document.title = `${displayGame} ${year} Chart`; } catch {}

      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      const table = document.getElementById('yearTable');

      function storageKey(g, y){ return `yearchart:${g.toLowerCase()}:${y}`; }
      function loadLocal(g, y){ try { return JSON.parse(localStorage.getItem(storageKey(g,y)) || '{}'); } catch { return {}; } }
      function saveLocal(g, y, obj){ try { localStorage.setItem(storageKey(g,y), JSON.stringify(obj||{})); } catch (e){} }
      function currentNumber(val){
        const s = String(val == null ? '' : val).trim();
        const matches = s.match(/\d+/g) || [];
        if (!matches.length) return '';
        return matches[matches.length - 1];
      }
      async function fetchChart(g, y){
        const local = loadLocal(g, y);
        try {
          const qs = new URLSearchParams({ game: g, year: y });
          const res = await fetch(`/api/chart.php?${qs.toString()}`, { headers: { 'Accept': 'application/json' } });
          if (!res.ok) throw new Error('net');
          const j = await res.json();
          if (j && j.ok && j.data && typeof j.data === 'object') {
            return Object.assign({}, local, j.data);
          }
        } catch(e) {}
        return local;
      }
      async function saveChart(g, y, key, value){
        const cur = loadLocal(g, y);
        if (value === '' || value == null) { delete cur[key]; } else { cur[key] = value; }
        saveLocal(g, y, cur);
        try {
          await fetch('/api/chart.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ game: g, year: y, key, value }) });
        } catch(e) {}
      }
      function buildTable(){
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        const yearTh = document.createElement('th');
        yearTh.textContent = year;
        headRow.appendChild(yearTh);
        months.forEach(m => { const th = document.createElement('th'); th.textContent = m; headRow.appendChild(th); });
        thead.appendChild(headRow);
        table.appendChild(thead);
        const tbody = document.createElement('tbody');
        for (let day = 1; day <= 31; day++) {
          const tr = document.createElement('tr');
          const dayCell = document.createElement('td');
          dayCell.textContent = String(day).padStart(2, '0');
          tr.appendChild(dayCell);
          for (let m = 0; m < 12; m++) {
            const td = document.createElement('td');
            const key = `${String(day).padStart(2,'0')}-${String(m+1).padStart(2,'0')}`; // DD-MM
            td.dataset.key = key;
            td.textContent = '-';
            tr.appendChild(td);
          }
          tbody.appendChild(tr);
        }
        table.appendChild(tbody);
      }
      function applyYearHeader(y){
        const thead = table.querySelector('thead') || table.createTHead();
        let row = thead.querySelector('tr');
        if (!row) { row = document.createElement('tr'); thead.appendChild(row); }
        let first = row.querySelector('th');
        if (!first) { first = document.createElement('th'); row.insertBefore(first, row.firstChild); }
        first.textContent = `${displayGame} - ${y}`;
      }
      function applyDataToTable(data){
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach(tr => {
          const cells = Array.from(tr.querySelectorAll('td'));
          if (cells.length === 0) return;
          for (let i = 1; i < cells.length; i++) {
            const td = cells[i];
            const key = td.dataset.key; // DD-MM
            if (!key) continue;
            const raw = Object.prototype.hasOwnProperty.call(data, key) ? data[key] : '-';
            const disp = raw === '-' ? '-' : (currentNumber(raw) || '-');
            td.textContent = disp;
          }
        });
      }
      function bindAdminEditing(g, y){
        if (!isAdmin) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        tbody.addEventListener('input', onChange);
        tbody.addEventListener('blur', onChange, true);
        async function onChange(e){
          const td = e.target && e.target.closest && e.target.closest('td[data-key]');
          if (!td) return;
          const k = td.dataset.key;
          const vRaw = (td.textContent||'').trim();
          const v = currentNumber(vRaw);
          td.textContent = v || '-';
          await saveChart(g, y, k, v);
        }
      }

      buildTable();
      applyYearHeader(year);
      (async function(){ const data = await fetchChart(apiGame, year); applyDataToTable(data); })();
      window.addEventListener('storage', (e) => {
        try {
          if (!e || e.key !== storageKey(apiGame, year)) return;
          const next = JSON.parse(e.newValue || '{}');
          if (next && typeof next === 'object') { applyDataToTable(next); }
        } catch(_){ }
      });
      bindAdminEditing(apiGame, year);
      if (isAdmin && table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(tr => {
          const tds = tr.querySelectorAll('td');
          tds.forEach((td, idx) => { if (idx !== 0) td.setAttribute('contenteditable', 'true'); });
        });
      }
    })();
  </script>
</body></html>
