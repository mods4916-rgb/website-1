<?php
// chart.php - PHP version of chart.html
?>
<!DOCTYPE html>
<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lucky Satta Chart</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #fff; }
    .navbar { display: flex; justify-content: center; gap: 150px; margin: 20px 0; }
    .navbar a { text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: 2px solid black; background: yellow; }
    .navbar a.chart { background: orange; border: none; }
    .header { text-align: center; background: linear-gradient(to right, orange, yellow); padding: 30px 10px; }
    .header h2 { margin: 0; color: #444; font-size: 22px; letter-spacing: 2px; }
    .title-header { text-align: center; background: #fff; padding: 16px 10px; border-bottom: 3px solid orange; margin-top: 8px; }
    .title-header h1 { margin: 0; font-size: 34px; color: #222; letter-spacing: 1px; }
    table { width: 100%; margin: 20px auto; border-collapse: collapse; text-align: center; }
    th, td { border: 1px solid #ccc; padding: 10px; }
    th { background: yellow; font-weight: bold; position: sticky; top: 0; z-index: 1; }
    td:first-child { background: #ff4d00; color: white; font-weight: bold; text-align: left; padding-left: 15px; }
    td a { color: black; text-decoration: none; font-weight: bold; }
    td a:hover { color: red; text-decoration: underline; }
    .note { text-align: right; font-size: 14px; margin: 5px 20px; }
    @media (max-width: 767px) {
      table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
      thead, tbody, tr { display: table; width: 100%; table-layout: fixed; }
      th, td { padding: 8px 6px; font-size: 13px; white-space: nowrap; }
      th { white-space: normal !important; font-size: 12px; line-height: 1.2; word-break: break-word; }
      @media (max-width: 480px) { th { font-size: 11px; padding: 6px 4px; } }
      td:first-child { white-space: normal; }
      .navbar { gap: 12px; }
      .navbar a { padding: 10px 16px; }
      .title-header h1 { font-size: 26px; }
      th:first-child { width: auto !important; }
      td:first-child { max-width: 180px; word-break: break-word; padding: 8px 6px; }
      td:first-child { font-size: 14px; }
      td:first-child a { display: inline-block; font-size: 14px; line-height: 1.2; white-space: normal; }
    }
  </style>
</head>
<body>
  <script>
    (function(){
      function snapshotEditable(){ try { document.querySelectorAll('[contenteditable="true"]').forEach(function(el){ el.setAttribute('data-was-editable','1'); }); } catch(_){ } }
      function lockEditing(){ try { document.querySelectorAll('[contenteditable="true"]').forEach(function(el){ el.setAttribute('contenteditable','false'); }); } catch(_){ } }
      function unlockEditing(){ try { document.querySelectorAll('[data-was-editable="1"]').forEach(function(el){ el.setAttribute('contenteditable','true'); el.removeAttribute('data-was-editable'); }); } catch(_){ } }
      function apiHeaders(){
        var usp = new URLSearchParams(location.search);
        var sid = usp.get('sid') || '';
        var h = { 'Accept':'application/json', 'Content-Type':'application/json' };
        if (sid) h['Authorization'] = 'Bearer ' + sid;
        return h;
      }
      function fetchYears(){
        return fetch('/api/years.php?_=' + Date.now(), { headers: { 'Accept':'application/json' }})
          .then(function(r){ return r.ok ? r.json() : { ok:false }; })
          .then(function(j){ return (j && j.ok && Array.isArray(j.years)) ? j.years : []; })
          .catch(function(){ return []; });
      }
      function postYear(y){
        return fetch('/api/years.php', { method:'POST', credentials:'include', headers: apiHeaders(), body: JSON.stringify({ year: y }) })
          .then(function(r){ return r.ok ? r.json() : Promise.reject(); });
      }
      function collectSlug(row){
        var anchors = row.querySelectorAll('a[href*="year.php"]');
        for (var i=0; i<anchors.length; i++){
          try { var u = new URL(anchors[i].getAttribute('href'), location.origin); var g = u.searchParams.get('game'); if (g) return g; } catch(_){ }
        }
        return '';
      }
      function nameToSlug(name){
        var l = String(name||'').trim().toLowerCase();
        var map = {
          'sadar bazar': 'sadar-bazar',
          'gwalior': 'gwalior',
          'delhi bazar': 'delhi-bazar',
          'saharanpur city': 'saharanpur-city',
          'shri ganesh': 'shri-ganesh',
          'faridabad': 'faridabad',
          'shimla super': 'shimla-super',
          'gaziyabad': 'gaziyabad',
          'gaziaybad': 'gaziyabad',
          'bilaspur': 'bilaspur',
          'gali': 'gali',
          'disawer': 'disawer'
        };
        return map[l] || l.replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
      }
      var IS_ADMIN = false;
      function hardenPublic(){
        try {
          if (IS_ADMIN) return;
          document.querySelectorAll('[contenteditable="true"]').forEach(function(el){ el.setAttribute('contenteditable','false'); });
          document.querySelectorAll('a[href]').forEach(function(a){
            a.style.pointerEvents = 'auto'; a.style.userSelect = 'auto'; a.setAttribute('draggable','false');
            a.addEventListener('click', function(ev){
              try {
                var href = a.getAttribute('href') || '';
                if (!href) return;
                if (/^https?:\/\//i.test(href)) return; // external ok
                ev.preventDefault(); ev.stopPropagation();
                window.location.href = href;
              } catch(_) {}
            });
          });
        } catch(_) {}
      }
      function rebuildColumns(years){
        try {
          var table = document.querySelector('table'); if (!table) return;
          var theadRow = table.querySelector('thead tr'); if (!theadRow) return;
          // Capture stable slug per row BEFORE clearing cells
          var rows = Array.from(table.querySelectorAll('tbody tr'));
          var rowInfo = rows.map(function(row){
            var firstCell = row.querySelector('td');
            var slug = collectSlug(row);
            if (!slug) {
              // Fallback: canonicalize from first cell text
              var name = (firstCell && (firstCell.textContent||'').trim()) || '';
              slug = nameToSlug(name);
            }
            var label = (firstCell && (firstCell.textContent||'').trim()) || slug || '';
            return { row: row, slug: slug, label: label };
          });
          // Keep first TH (Games), remove the rest
          while (theadRow.children.length > 1) { theadRow.removeChild(theadRow.lastElementChild); }
          // For each body row, remove all cells after the first
          rowInfo.forEach(function(info){
            var row = info.row;
            while (row.children.length > 1) { row.removeChild(row.lastElementChild); }
          });
          // Create new columns in the desired order (descending, newest LEFT)
          years.forEach(function(year){
            var y = String(year);
            var th = document.createElement('th'); th.textContent = y + ' Charts'; th.setAttribute('contenteditable', IS_ADMIN ? 'true' : 'false');
            theadRow.appendChild(th);
            rowInfo.forEach(function(info){
              var row = info.row; var slug = info.slug; var label = info.label;
              var td = document.createElement('td');
              var a = document.createElement('a');
              a.textContent = y; a.setAttribute('contenteditable', IS_ADMIN ? 'true' : 'false');
              var qs = new URLSearchParams({ game: slug || '', year: y, label: label });
              a.setAttribute('href', 'year.php?' + qs.toString());
              td.appendChild(a);
              row.appendChild(td);
            });
          });
          hardenPublic();
        } catch(_) {}
      }
      function init(){
        snapshotEditable();
        var usp = new URLSearchParams(location.search);
        var wantsAdmin = usp.get('admin') === '1';
        IS_ADMIN = !!wantsAdmin;
        if (!wantsAdmin) { lockEditing(); }
        else {
          try {
            fetch('/api/auth-check.php', { credentials: 'include' })
              .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
              .then(function(j){ if (j && j.ok) { unlockEditing(); } else { lockEditing(); } })
              .catch(function(){ lockEditing(); });
          } catch(_) { lockEditing(); }
        }

        // Admin toolbar: Add/Remove Year (admins only)
        try {
          if (!wantsAdmin) throw new Error('not-admin');
          var bar = document.createElement('div');
          bar.style.display = 'flex'; bar.style.justifyContent = 'center'; bar.style.gap = '8px'; bar.style.margin = '10px 0';
          var addBtn = document.createElement('button');
          addBtn.textContent = 'Add Year';
          addBtn.style.padding = '8px 12px'; addBtn.style.fontWeight = 'bold'; addBtn.style.border = '2px solid #000'; addBtn.style.borderRadius = '8px'; addBtn.style.background = '#ffd54f';
          addBtn.addEventListener('click', function(){
            var y = prompt('Enter new year (e.g., 2026)');
            if (!y) return; y = y.trim();
            if (!/^\d{4}$/.test(y)) { alert('Invalid year'); return; }
            postYear(parseInt(y,10))
              .then(function(j){ var list = (j && j.years) ? j.years : []; list.sort(function(a,b){ return b - a; }); rebuildColumns(list); alert('Year ' + y + ' added'); })
              .catch(function(){ alert('Failed to add year'); });
          });
          bar.appendChild(addBtn);
          var removeBtn = document.createElement('button');
          removeBtn.textContent = 'Remove Year';
          removeBtn.style.padding = '8px 12px'; removeBtn.style.fontWeight = 'bold'; removeBtn.style.border = '2px solid #000'; removeBtn.style.borderRadius = '8px'; removeBtn.style.background = '#ffcdd2';
          removeBtn.addEventListener('click', function(){
            var y = prompt('Enter year to remove');
            if (!y) return; y = y.trim();
            if (!/^\d{4}$/.test(y)) { alert('Invalid year'); return; }
            fetch('/api/years.php', { method:'POST', credentials:'include', headers: apiHeaders(), body: JSON.stringify({ year: parseInt(y,10), remove: true }) })
              .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
              .then(function(j){ var list = (j && j.years) ? j.years : []; list.sort(function(a,b){ return b - a; }); rebuildColumns(list); alert('Year ' + y + ' removed'); })
              .catch(function(){ alert('Failed to remove year'); });
          });
          bar.appendChild(removeBtn);
          var hdr = document.querySelector('.title-header');
          if (hdr && hdr.parentNode) hdr.parentNode.insertBefore(bar, hdr.nextSibling);
        } catch(_){ }

        // Build years: server list authoritative; if empty, default to baseline
        fetchYears().then(function(list){
          try {
            var years = (list||[]).map(function(n){ return parseInt(n,10); }).filter(function(n){ return n >= 2000; });
            if (!years.length) years = [2025,2024,2023,2022,2021];
            years.sort(function(a,b){ return b - a; });
            rebuildColumns(years);
            hardenPublic();
          } catch(_){ }
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    })();
  </script>

  <div class="navbar" style="display: flex; justify-content: center; gap: 150px; margin: 20px 0px;" contenteditable="false">
    <a href="index.php" style="text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: 2px solid black; background: yellow;" contenteditable="true">HOME</a>
    <a href="chart.php" class="chart" style="text-decoration: none; padding: 12px 40px; border-radius: 12px; font-weight: bold; color: black; border: none; background: orange;" contenteditable="true">CHART</a>
  </div>

  <div class="header" style="background: linear-gradient(to right, orange, yellow);" contenteditable="false">
    <h2 contenteditable="true">LUCKY-SATTA</h2>
  </div>

  <div class="title-header" contenteditable="false">
    <h1 contenteditable="true">SATTA CHART</h1>
  </div>

  <table contenteditable="false">
    <thead contenteditable="false"><tr contenteditable="false">
      <th contenteditable="true">Games</th>
      <th contenteditable="true">2025 Charts</th>
      <th contenteditable="true">2024 Charts</th>
      <th contenteditable="true">2023 Charts</th>
      <th contenteditable="true">2022 Charts</th>
      <th contenteditable="true">2021 Charts</th>
    </tr></thead>
    <tbody contenteditable="false">
      <tr contenteditable="false">
        <td contenteditable="true">Disawer</td>
        <td><a href="year.php?game=disawer&year=2025&label=Disawer" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=disawer&year=2024&label=Disawer" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=disawer&year=2023&label=Disawer" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=disawer&year=2022&label=Disawer" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=disawer&year=2021&label=Disawer" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">SADAR BAZAR</td>
        <td><a href="year.php?game=sadar-bazar&year=2025&label=SADAR+BAZAR" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=sadar-bazar&year=2024&label=SADAR+BAZAR" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=sadar-bazar&year=2023&label=SADAR+BAZAR" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=sadar-bazar&year=2022&label=SADAR+BAZAR" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=sadar-bazar&year=2021&label=SADAR+BAZAR" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">GWALIOR</td>
        <td><a href="year.php?game=gwalior&year=2025&label=GWALIOR" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=gwalior&year=2024&label=GWALIOR" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=gwalior&year=2023&label=GWALIOR" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=gwalior&year=2022&label=GWALIOR" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=gwalior&year=2021&label=GWALIOR" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">DELHI BAZAR</td>
        <td><a href="year.php?game=delhi-bazar&year=2025&label=DELHI+BAZAR" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=delhi-bazar&year=2024&label=DELHI+BAZAR" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=delhi-bazar&year=2023&label=DELHI+BAZAR" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=delhi-bazar&year=2022&label=DELHI+BAZAR" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=delhi-bazar&year=2021&label=DELHI+BAZAR" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">SHARANPUR CITY</td>
        <td><a href="year.php?game=saharanpur-city&year=2025&label=Saharanpur+City" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=saharanpur-city&year=2024&label=Saharanpur+City" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=saharanpur-city&year=2023&label=Saharanpur+City" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=saharanpur-city&year=2022&label=Saharanpur+City" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=saharanpur-city&year=2021&label=Saharanpur+City" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">SHRI GANESH</td>
        <td><a href="year.php?game=shri-ganesh&year=2025&label=SHRI+GANESH" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=shri-ganesh&year=2024&label=SHRI+GANESH" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=shri-ganesh&year=2023&label=SHRI+GANESH" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=shri-ganesh&year=2022&label=SHRI+GANESH" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=shri-ganesh&year=2021&label=SHRI+GANESH" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">FARIDABAD</td>
        <td><a href="year.php?game=faridabad&year=2025&label=FARIDABAD" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=faridabad&year=2024&label=FARIDABAD" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=faridabad&year=2023&label=FARIDABAD" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=faridabad&year=2022&label=FARIDABAD" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=faridabad&year=2021&label=FARIDABAD" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">Shimla super</td>
        <td><a href="year.php?game=shimla-super&year=2025&label=Shimla+super" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=shimla-super&year=2024&label=Shimla+super" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=shimla-super&year=2023&label=Shimla+super" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=shimla-super&year=2022&label=Shimla+super" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=shimla-super&year=2021&label=Shimla+super" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">GAZIYABAD</td>
        <td><a href="year.php?game=gaziyabad&year=2025&label=GAZIYABAD" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=gaziyabad&year=2024&label=GAZIYABAD" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=gaziyabad&year=2023&label=GAZIYABAD" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=gaziyabad&year=2022&label=GAZIYABAD" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=gaziyabad&year=2021&label=GAZIYABAD" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">Bilaspur</td>
        <td><a href="year.php?game=bilaspur&year=2025&label=Bilaspur" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=bilaspur&year=2024&label=Bilaspur" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=bilaspur&year=2023&label=Bilaspur" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=bilaspur&year=2022&label=Bilaspur" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=bilaspur&year=2021&label=Bilaspur" contenteditable="true">2021</a></td>
      </tr>
      <tr contenteditable="false">
        <td contenteditable="true">gali</td>
        <td><a href="year.php?game=gali&year=2025&label=gali" contenteditable="true">2025</a></td>
        <td><a href="year.php?game=gali&year=2024&label=gali" contenteditable="true">2024</a></td>
        <td><a href="year.php?game=gali&year=2023&label=gali" contenteditable="true">2023</a></td>
        <td><a href="year.php?game=gali&year=2022&label=gali" contenteditable="true">2022</a></td>
        <td><a href="year.php?game=gali&year=2021&label=gali" contenteditable="true">2021</a></td>
      </tr>
    </tbody>
  </table>

  <script>
    // Auto-append label param to each year link using the display name in the first column
    document.addEventListener('DOMContentLoaded', function () {
      try {
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
          const firstCell = row.querySelector('td');
          if (!firstCell) return;
          const label = (firstCell.textContent || '').trim();
          if (!label) return;
          const anchors = row.querySelectorAll('a[href*="year.php"]');
          anchors.forEach(a => {
            try {
              const url = new URL(a.getAttribute('href'), location.origin);
              if (!url.searchParams.has('label')) {
                url.searchParams.set('label', label);
                a.setAttribute('href', url.pathname.replace(/^\/+/, '') + url.search);
              }
            } catch (e) {}
          });
        });
      } catch (e) {}
    });
  </script>
</body></html>
