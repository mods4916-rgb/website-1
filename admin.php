<?php
// admin.php - PHP version of admin.html
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Editor</title>
  <style>
    html, body { height: 100%; margin: 0; background: #fff; font-family: Arial, sans-serif; }
    .navbar, .header, .title-header, .status { display: none !important; }
    /* Hide Save button and status permanently */
    .save-fab, .save-status { display: none !important; }
    .save-fab {
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 1000;
      padding: 10px 16px;
      font-weight: bold;
      border-radius: 10px;
      border: 2px solid black;
      background: orange;
      color: black;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .save-fab:disabled { opacity: 0.6; cursor: default; }
    .save-status { position: fixed; top: 10px; right: 110px; z-index: 1000; color: #2e7d32; font-weight: 600; }
    .panel { width: 100vw; height: 100vh; margin: 0; padding: 0; }
    .panel .box { width: 100%; height: 100%; border: 0; border-radius: 0; overflow: hidden; }
    .panel iframe { width: 100%; height: 100%; border: 0; background: #fff; display: block; }
  </style>
</head>
<body>
  <button id="saveBtn" class="save-fab">Save</button>
  <div id="status" class="save-status"></div>

  <div class="panel" style="grid-template-columns: 1fr;">
    <div class="box">
      <iframe id="preview"></iframe>
    </div>
  </div>

  <script>
    const preview = document.getElementById('preview');
    const statusEl = document.getElementById('status');
    const saveBtn = document.getElementById('saveBtn');
    const API_BASE = '';
    const params = new URLSearchParams(location.search);
    const sidToken = params.get('sid');
    if (sidToken) { params.delete('sid'); history.replaceState({}, '', `${location.pathname}${params.toString() ? ('?' + params.toString()) : ''}`); }
    let currentPath = (params.get('path') || 'index.php').replace(/^\/+/, '');

    function setStatus(msg, ok=true){
      statusEl.textContent = msg || '';
      statusEl.style.color = ok ? '#2e7d32' : '#b00020';
    }

    function updateUrl(p){
      const usp = new URLSearchParams(location.search);
      usp.set('path', p);
      history.replaceState({}, '', `${location.pathname}?${usp.toString()}`);
    }

    function updateSaveButtonState() {
      try {
        const p = (currentPath || '').replace(/[?#].*$/, '');
        const ok = /\.php$/i.test(p);
        saveBtn.disabled = !ok;
        saveBtn.title = ok ? 'Save this page' : 'Only .php pages can be saved';
      } catch(e) {}
    }

    async function loadPage(p){
      setStatus('Loading...');
      preview.onload = () => {
        enableDirectEdit();
        setStatus(`Loaded ${p}`);
        updateSaveButtonState();
      };
      const base = `/${p.replace(/^\/+/, '')}`;
      const joiner = p.includes('?') ? '&' : '?';
      const q = 'admin=1' + (sidToken ? ('&sid=' + encodeURIComponent(sidToken)) : '');
      const pathWithFlag = base + joiner + q;
      preview.src = pathWithFlag;
      currentPath = p;
      updateUrl(p);
      updateSaveButtonState();
    }

    async function savePage(){
      try {
        const p = currentPath || '';
        let filePath = p.replace(/[?#].*$/, '');
        try {
          const u = new URL(p, location.origin);
          const base = (u.pathname || '').replace(/^\/+/, '');
          const search = u.searchParams;
          if (/^year\.(html|php)$/i.test(base)) {
            const g = (search.get('game') || '').trim().toLowerCase();
            if (g) filePath = `year-${g}.php`;
          }
        } catch {}
        if (!/\.php$/i.test(filePath)) { setStatus('Only .php files are allowed', false); updateSaveButtonState(); return; }
        setStatus(`Saving ${filePath}...`);
        saveBtn.disabled = true;
        const doc = preview.contentWindow.document;
        const clone = doc.documentElement.cloneNode(true);
        clone.querySelectorAll('[data-admin-only]')?.forEach(el => el.remove());
        const html = '<!DOCTYPE html>\n' + clone.outerHTML;
        const headers = { 'Content-Type': 'application/json' };
        if (sidToken) headers['Authorization'] = 'Bearer ' + sidToken;
        const res = await fetch(`${API_BASE}/api/save-page.php`, {
          method: 'POST',
          credentials: 'include',
          headers,
          body: JSON.stringify({ path: filePath, html })
        });
        if (res.status === 401) { window.location.href = 'login.php'; return; }
        if (!res.ok) {
          let msg = 'Save failed';
          try { const j = await res.json(); if (j && j.error) msg = j.error; } catch {
            try { msg = (await res.text()) || msg; } catch {}
          }
          throw new Error(msg);
        }
        let ok = true;
        try {
          const ct = res.headers.get('Content-Type') || '';
          if (ct.includes('application/json')) {
            const j = await res.json();
            ok = !!(j && j.ok !== false);
          }
        } catch {}
        if (!ok) throw new Error('Failed to save');
        setStatus('Saved successfully');
      } catch(e) {
        setStatus(e.message || 'Save failed', false);
      }
      finally {
        saveBtn.disabled = false;
      }
    }

    function enableDirectEdit() {
      const doc = preview.contentWindow.document;
      if (!doc) return;
      const root = doc.body;
      if (!root) return;

      const walker = doc.createTreeWalker(root, NodeFilter.SHOW_ELEMENT, null);
      while (walker.nextNode()) {
        const el = walker.currentNode;
        if (/^(INPUT|BUTTON|TEXTAREA|SCRIPT|STYLE|IFRAME)$/.test(el.tagName)) {
          el.setAttribute('contenteditable', 'false');
          continue;
        }
        const hasElementChildren = Array.from(el.childNodes).some(n => n.nodeType === 1);
        const hasText = el.textContent && el.textContent.trim().length > 0;
        if (!hasElementChildren && hasText) {
          el.setAttribute('contenteditable', 'true');
          el.style.outline = '';
        } else {
          el.setAttribute('contenteditable', 'false');
        }
      }

      try {
        const cells = root.querySelectorAll('.tablebox1 tbody tr td:nth-child(3)');
        const unblockAncestors = (node) => {
          let cur = node.parentElement;
          while (cur && !cur.classList.contains('tablebox1')){
            if (cur.hasAttribute('contenteditable') && cur.getAttribute('contenteditable') === 'false') {
              cur.removeAttribute('contenteditable');
            }
            cur = cur.parentElement;
          }
        };
        cells.forEach(td => {
          unblockAncestors(td);
          td.setAttribute('contenteditable', 'true');
          td.setAttribute('tabindex', '0');
          td.style.outline = '1px dashed #999';
          td.style.userSelect = 'text';
          td.style.pointerEvents = 'auto';
          td.addEventListener('focusin', () => {
            if (td.querySelector('img')) td.textContent = '';
            if ((td.textContent||'').trim() === '-') td.textContent = '';
          });
        });
      } catch (e) {}

      try {
        const timeEls = root.querySelectorAll('.time, .sattaresult .time, .sattaname, .sattaname p');
        const unblockAncestorsTime = (node) => {
          let cur = node && node.parentElement;
          while (cur && !cur.isSameNode(root)){
            if (cur.hasAttribute('contenteditable') && cur.getAttribute('contenteditable') === 'false') {
              cur.removeAttribute('contenteditable');
            }
            cur = cur.parentElement;
          }
        };
        timeEls.forEach(el => {
          unblockAncestorsTime(el);
          el.setAttribute('contenteditable', 'true');
          el.setAttribute('tabindex', '0');
          el.style.outline = '1px dashed #999';
          el.style.userSelect = 'text';
          el.style.pointerEvents = 'auto';
          el.addEventListener('focusin', () => {
            if ((el.textContent||'').trim() === '-') el.textContent = '';
          });
        });
      } catch (e) {}

      try {
        const rect = root.querySelector('.featured-rect.disawer-hero .result');
        if (rect) {
          const unblockAncestors = (node) => {
            let cur = node && node.parentElement;
            while (cur && !cur.isSameNode(root)){
              if (cur.hasAttribute('contenteditable') && cur.getAttribute('contenteditable') === 'false') {
                cur.removeAttribute('contenteditable');
              }
              cur = cur.parentElement;
            }
          };
          unblockAncestors(rect);
          rect.querySelectorAll('.num').forEach(num => {
            unblockAncestors(num);
            num.setAttribute('contenteditable','true');
            num.setAttribute('tabindex','0');
            num.style.outline = '1px dashed #999';
            num.style.userSelect = 'text';
            num.style.pointerEvents = 'auto';
          });
        }
      } catch(e) {}

      const allowedInputTypes = new Set([
        'insertText','deleteContentBackward','deleteContentForward','insertFromPaste','insertTranspose'
      ]);
      doc.addEventListener('beforeinput', (e) => {
        const target = e.target;
        const isEditable = target && target.isContentEditable;
        if (!isEditable || !allowedInputTypes.has(e.inputType)) {
          e.preventDefault();
        }
      });
      doc.addEventListener('paste', (e) => {
        const target = e.target;
        if (!target || !target.isContentEditable) return;
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        doc.execCommand('insertText', false, text);
      });
      doc.addEventListener('keydown', (e) => {
        const t = e.target;
        const isEditable = t && t.isContentEditable;
        if (!isEditable) return;
        if (e.key === 'Enter') { e.preventDefault(); return; }
        if (e.key === 'Tab') {
          const rect = t.closest && t.closest('.featured-rect.disawer-hero .result');
          if (rect) {
            e.preventDefault();
            const nums = Array.from(rect.querySelectorAll('.num'));
            if (!nums.length) return;
            const idx = Math.max(0, nums.indexOf(t));
            const next = nums[(idx + (e.shiftKey ? nums.length - 1 : 1)) % nums.length];
            if (next) next.focus();
            return;
          }
          e.preventDefault();
        }
      });
      doc.addEventListener('drop', (e) => e.preventDefault());
      doc.addEventListener('dragover', (e) => e.preventDefault());

      doc.addEventListener('click', (e) => {
        const a = e.target && (e.target.closest ? e.target.closest('a') : null);
        if (!a) return;
        const rawHref = a.getAttribute('href') || '';
        if (!rawHref) { e.preventDefault(); return; }
        const hasScheme = /^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//.test(rawHref);
        const isExternal = hasScheme;
        if (isExternal) {
          e.preventDefault();
          window.open(rawHref, '_blank');
          return;
        }
        const path = rawHref.replace(/^\/+/, '');
        const isPage = /\.(html|php)(?:$|[?#])/.test(path) || path === '';
        if (isPage) {
          e.preventDefault();
          loadPage(path || 'index.php');
        }
      });
    }

    function installChartYearTools(doc) { /* omitted for brevity in PHP clone */ }

    saveBtn.addEventListener('click', savePage);

    (async function(){
      try {
        const headers = sidToken ? { 'Authorization': 'Bearer ' + sidToken } : {};
        const res = await fetch(`${API_BASE}/api/auth-check.php`, { credentials: 'include', headers });
        if (!res.ok) throw new Error('UNAUTHORIZED');
        loadPage(currentPath);
      } catch {
        window.location.href = 'login.php';
      }
    })();
  </script>
</body>
</html>
