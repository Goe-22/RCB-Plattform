/* Seiten-Editor der RC Bande Zwickau: Texte und Bilder direkt auf der Seite bearbeiten. */
(() => {
  const csrf = document.querySelector('meta[name="rcb-csrf"]')?.content || '';
  const MODE = document.currentScript?.dataset.mode || (new URLSearchParams(location.search).has('vorschau') ? 'preview' : 'edit');
  const MODE_PARAM = MODE === 'edit' ? 'bearbeiten=1' : 'vorschau=1';
  let pending = 0;
  const adminUrl = '/admin/index.php';
  const dirty = new Map();
  let PAGES = [];
  try { PAGES = JSON.parse(document.getElementById('rcb-pages')?.textContent || '[]'); } catch (e) { PAGES = []; }

  const el = (tag, attrs = {}, children = []) => {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'text') node.textContent = v;
      else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else node.setAttribute(k, v);
    });
    children.forEach((c) => node.append(c));
    return node;
  };

  /* ---------- Werkzeugleiste oben ---------- */
  const status = el('span', { class: 'rcb-ed-status', text: MODE === 'edit' ? 'Klicke auf markierte Texte oder Bilder, um sie zu ändern.' : 'So sieht die Seite nach dem Veröffentlichen aus.' });
  const draftInfo = el('span', { class: 'rcb-ed-draft', hidden: 'hidden' });
  const saveBtn = el('button', { type: 'button', class: 'rcb-ed-btn rcb-ed-primary', text: 'Entwurf speichern', disabled: 'disabled', title: 'Speichert die Änderungen als Entwurf – noch nicht online (Strg+S)', onclick: () => save() });
  const publishBtn = el('button', { type: 'button', class: 'rcb-ed-btn rcb-ed-publish', text: '⬆ Veröffentlichen', title: 'Alle Entwurfsänderungen online stellen', onclick: publish });
  const discardBtn = el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Entwurf verwerfen', title: 'Alle gespeicherten, noch nicht veröffentlichten Änderungen löschen', onclick: discardDraft });
  const SCOPE = document.currentScript?.dataset.scope || 'all';
  const hereParams = new URLSearchParams(location.search);
  const hereKey = location.pathname === '/seite.php' ? 'slug' : (location.pathname === '/reglement.php' ? 'klasse' : '');
  const here = location.pathname === '/index.php' ? '/' : location.pathname + (hereKey && hereParams.get(hereKey) ? '?' + hereKey + '=' + encodeURIComponent(hereParams.get(hereKey)) : (location.pathname === '/seite.php' ? '?slug=' : ''));
  const pageSelect = el('select', { class: 'rcb-ed-select', 'aria-label': 'Seite wechseln', onchange: (e) => go(e.target.value) },
    PAGES.map(([url, label]) => { const o = el('option', { value: url, text: label }); if (url === here) o.selected = true; return o; }));
  const plainUrl = location.pathname + location.search.replace(/[?&](bearbeiten|vorschau)=1/g, '').replace(/^&/, '?');
  const withParam = (param) => location.pathname + (plainUrl.includes('?') ? plainUrl.slice(plainUrl.indexOf('?')) + '&' : '?') + param;
  const controls = MODE === 'edit'
    ? [el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Rückgängig', title: 'Ungespeicherte Änderungen auf dieser Seite verwerfen', onclick: () => { if (!dirty.size || confirm('Ungespeicherte Änderungen auf dieser Seite verwerfen?')) { dirty.clear(); location.reload(); } } }),
       saveBtn,
       el('a', { class: 'rcb-ed-btn', href: withParam('vorschau=1'), text: 'Vorschau', title: 'Speichert ungespeicherte Änderungen als Entwurf und zeigt die Seite ohne Bearbeitungsrahmen', onclick: openPreview }),
       publishBtn, discardBtn]
    : [el('a', { class: 'rcb-ed-btn rcb-ed-primary', href: withParam('bearbeiten=1'), text: '← Zurück zum Bearbeiten', title: 'Vorschau schließen und weiter bearbeiten (Esc)' })];
  const bar = MODE === 'edit'
    ? el('div', { class: 'rcb-ed-bar', role: 'toolbar', 'aria-label': 'Seiten-Editor' }, [
        el('strong', { text: '✎ Bearbeiten' }), pageSelect, status, draftInfo,
        el('span', { class: 'rcb-ed-spacer' }),
        ...controls,
        el('a', { class: 'rcb-ed-btn', href: adminUrl + (location.pathname === '/reglement.php' ? '?page=reglement' : '?page=pages'), text: 'Zum Admin' }),
      ])
    : el('div', { class: 'rcb-ed-bar rcb-ed-preview', role: 'toolbar', 'aria-label': 'Entwurfsvorschau' }, [
        el('strong', { text: '👁 Entwurfsvorschau' }), status,
        el('span', { class: 'rcb-ed-spacer' }),
        ...controls,
      ]);
  document.body.prepend(bar);
  document.body.classList.add('rcb-editing');
  const syncBar = () => {
    const h = bar.offsetHeight;
    document.body.style.paddingTop = h + 'px';
    const header = document.querySelector('.site-header');
    if (header) header.style.top = h + 'px';
  };
  syncBar();
  window.addEventListener('resize', syncBar);
  new ResizeObserver(syncBar).observe(bar);

  function showPending(n) {
    pending = n;
    draftInfo.hidden = n === 0;
    draftInfo.textContent = n === 1 ? '1 Änderung im Entwurf, noch nicht online' : n + ' Änderungen im Entwurf, noch nicht online';
    publishBtn.disabled = n === 0 && dirty.size === 0;
    discardBtn.disabled = n === 0;
  }
  async function refreshPending() {
    try { const r = await fetch(adminUrl + '?page=draftstatus', { credentials: 'same-origin' }); const d = await r.json(); if (d.ok) showPending(d.count); } catch (e) { /* Anzeige ist optional */ }
  }
  refreshPending();

  async function post(action, extra = {}) {
    const fd = new FormData();
    fd.append('action', action); fd.append('csrf_token', csrf);
    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(adminUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'rcb-editor' }, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ ok: false, error: res.status === 419 ? 'Sitzung abgelaufen. Bitte im Admin neu anmelden.' : 'Unerwartete Antwort vom Server.' }));
    if (!data.ok) throw new Error(data.error || 'Aktion fehlgeschlagen.');
    return data;
  }
  // Vorschau: ungespeicherte Änderungen zuerst als Entwurf sichern, dann die Vorschau (neu) laden.
  async function openPreview(event) {
    event.preventDefault();
    if (dirty.size && !(await save())) return;
    dirty.clear();
    location.href = withParam('vorschau=1');
  }

  // In der Vorschau ist die Seite nur zum Ansehen: Links, Knöpfe und Formulare sind gesperrt.
  if (MODE === 'preview') {
    document.body.classList.add('rcb-previewing');
    let hintTimer;
    const block = (e) => {
      if (bar.contains(e.target)) return;
      const link = e.target.closest('a[href]');
      if (link && link.getAttribute('href').startsWith('#') && e.type === 'click') return; // Sprung innerhalb der Seite erlaubt
      if (e.type === 'click' && !e.target.closest('a, button, input, select, textarea, label, summary, [role="button"], [onclick]')) return;
      e.preventDefault(); e.stopPropagation();
      setStatus('Nur Vorschau – hier lässt sich nichts anklicken. Mit „Zurück zum Bearbeiten“ geht es weiter.', 'error');
      clearTimeout(hintTimer); hintTimer = setTimeout(() => setStatus('So sieht die Seite nach dem Veröffentlichen aus.'), 3500);
    };
    ['click', 'auxclick', 'submit'].forEach((t) => document.addEventListener(t, block, true));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { location.href = withParam('bearbeiten=1'); return; }
      if (!bar.contains(e.target) && ['INPUT', 'SELECT', 'TEXTAREA'].includes(e.target.tagName)) e.preventDefault();
    }, true);
    document.querySelectorAll('input, select, textarea, button').forEach((f) => { if (!bar.contains(f)) f.tabIndex = -1; });
  }

  async function publish() {
    if (dirty.size && !(await save())) return;
    if (!pending) { setStatus('Es gibt keine Änderungen zum Veröffentlichen.'); return; }
    if (!confirm(pending + (pending === 1 ? ' Änderung' : ' Änderungen') + ' jetzt veröffentlichen? Danach sind sie für alle Besucher sichtbar.')) return;
    publishBtn.disabled = true; setStatus('Wird veröffentlicht …');
    try { const d = await post('publish_drafts'); showPending(0); setStatus('Veröffentlicht ✓ – ' + d.published + (d.published === 1 ? ' Änderung ist' : ' Änderungen sind') + ' jetzt online.', 'ok'); }
    catch (err) { publishBtn.disabled = false; setStatus(err.message, 'error'); }
  }
  async function discardDraft() {
    if (!pending) return;
    if (!confirm('Alle ' + pending + ' gespeicherten, noch nicht veröffentlichten Änderungen verwerfen? ' + (SCOPE === 'reglement' ? 'Das betrifft das ganze Reglement.' : 'Das betrifft alle Seiten.'))) return;
    try { await post('discard_drafts'); dirty.clear(); location.reload(); } catch (err) { setStatus(err.message, 'error'); }
  }

  function go(url) {
    if (dirty.size && !confirm('Es gibt ungespeicherte Änderungen. Trotzdem die Seite wechseln?')) { pageSelect.value = here; return; }
    dirty.clear();
    location.href = url + (url.includes('?') ? '&' : '?') + MODE_PARAM;
  }

  function setStatus(text, kind = '') { status.textContent = text; status.dataset.kind = kind; }
  function markDirty(key, type, value) {
    dirty.set(key, { type, value });
    saveBtn.disabled = false;
    publishBtn.disabled = false;
    setStatus(dirty.size + (dirty.size === 1 ? ' ungespeicherte Änderung' : ' ungespeicherte Änderungen'), 'dirty');
  }

  if (MODE === 'edit') {
  /* ---------- Texte ---------- */
  const supportsPlain = (() => { const d = document.createElement('div'); d.contentEditable = 'plaintext-only'; return d.contentEditable === 'plaintext-only'; })();
  document.querySelectorAll('[data-rcb-type="text"]').forEach((node) => {
    node.contentEditable = supportsPlain ? 'plaintext-only' : 'true';
    node.spellcheck = true;
    node.title = 'Text bearbeiten';
    node.addEventListener('input', () => markDirty(node.dataset.rcbKey, 'text', node.innerText.replace(/\n$/, '')));
    node.addEventListener('paste', (e) => { e.preventDefault(); document.execCommand('insertText', false, e.clipboardData.getData('text/plain')); });
    node.addEventListener('keydown', (e) => { if (e.key === 'Enter' && node.closest('h1,h2,h3,.rcb-btn,.rcb-pill')) e.preventDefault(); });
  });

  /* ---------- Formatierte Texte ---------- */
  // Feste Vereinsfarben für Text (die Webseite zeigt sie in den Farben aus Admin → Design).
  const TEXT_COLORS = [['#1d2733', 'Standard'], ['#c9272c', 'Rot'], ['#062b55', 'Blau'], ['#17703a', 'Grün'], ['#657383', 'Grau']];
  const SIZE_OF = { 'rcb-fs-s': '2', 'rcb-fs-l': '4', 'rcb-fs-xl': '5' };
  const COLOR_OF = { 'rcb-c-red': '#c9272c', 'rcb-c-blue': '#062b55', 'rcb-c-green': '#17703a', 'rcb-c-gray': '#657383' };
  function fontCmd(cmd, value) { document.execCommand('styleWithCSS', false, false); document.execCommand(cmd, false, value); }
  // Gespeicherte Gestaltungsklassen in die Form bringen, die der Browser-Editor versteht (<font>, text-align).
  function toEditable(root) {
    root.querySelectorAll('span[class*="rcb-"]').forEach((span) => {
      const font = document.createElement('font');
      span.classList.forEach((c) => { if (SIZE_OF[c]) font.setAttribute('size', SIZE_OF[c]); if (COLOR_OF[c]) font.setAttribute('color', COLOR_OF[c]); });
      while (span.firstChild) font.append(span.firstChild);
      span.replaceWith(font);
    });
    root.querySelectorAll('[class*="rcb-al-"]').forEach((block) => {
      ['left', 'center', 'right'].forEach((a) => { if (block.classList.contains('rcb-al-' + a)) { block.style.textAlign = a; block.classList.remove('rcb-al-' + a); } });
      if (!block.className) block.removeAttribute('class');
    });
  }
  const fmt = el('div', { class: 'rcb-ed-format', hidden: 'hidden' });
  let activeRich = null;
  const fmtButtons = [
    ['¶', 'Absatz', () => document.execCommand('formatBlock', false, 'p')],
    ['Ü1', 'Überschrift', () => document.execCommand('formatBlock', false, 'h2')],
    ['Ü2', 'Zwischenüberschrift', () => document.execCommand('formatBlock', false, 'h3')],
    ['B', 'Fett', () => document.execCommand('bold')],
    ['I', 'Kursiv', () => document.execCommand('italic')],
    ['•', 'Aufzählung', () => document.execCommand('insertUnorderedList')],
    ['1.', 'Nummerierte Liste', () => document.execCommand('insertOrderedList')],
    ['🔗', 'Link einfügen', () => { const url = prompt('Adresse des Links (z. B. https://… oder /termine.php):'); if (url) document.execCommand('createLink', false, url.trim()); }],
    ['🖼', 'Bild einfügen', () => { const saved = saveSelection(); openImagePicker((url) => { restoreSelection(saved); document.execCommand('insertImage', false, url); richChanged(activeRich); }); }],
    ['⌫', 'Formatierung entfernen', () => { document.execCommand('removeFormat'); document.execCommand('unlink'); }],
    '|',
    ['⇤', 'Linksbündig', () => document.execCommand('justifyLeft')],
    ['⇔', 'Zentriert', () => document.execCommand('justifyCenter')],
    ['⇥', 'Rechtsbündig', () => document.execCommand('justifyRight')],
    '|',
    ['A−', 'Kleine Schrift', () => fontCmd('fontSize', '2')],
    ['A', 'Normale Schriftgröße', () => fontCmd('fontSize', '3')],
    ['A+', 'Große Schrift', () => fontCmd('fontSize', '4')],
    ['A++', 'Sehr große Schrift', () => fontCmd('fontSize', '5')],
    '|',
    ...TEXT_COLORS.map(([hex, name]) => ({ swatch: hex, title: 'Textfarbe: ' + name, fn: () => fontCmd('foreColor', hex) })),
  ];
  fmtButtons.forEach((b) => {
    if (b === '|') { fmt.append(el('span', { class: 'rcb-ed-sep', 'aria-hidden': 'true' })); return; }
    const [label, title, fn] = Array.isArray(b) ? b : ['', b.title, b.fn];
    const btn = el('button', {
      type: 'button', title, 'aria-label': title, text: label,
      onmousedown: (e) => e.preventDefault(),
      onclick: () => { if (activeRich) { activeRich.focus(); fn(); richChanged(activeRich); } },
    });
    if (b.swatch) { btn.classList.add('rcb-ed-swatch'); btn.style.background = b.swatch; }
    fmt.append(btn);
  });
  document.body.append(fmt);

  function richChanged(node) { if (node) markDirty(node.dataset.rcbKey, 'rich', node.innerHTML); }
  function placeFormatBar(node) {
    const r = node.getBoundingClientRect();
    fmt.hidden = false;
    fmt.style.top = Math.max(bar.offsetHeight + 6, r.top + window.scrollY - fmt.offsetHeight - 8) + 'px';
    fmt.style.left = Math.max(8, Math.min(r.left + window.scrollX, window.innerWidth - fmt.offsetWidth - 8)) + 'px';
  }
  document.querySelectorAll('[data-rcb-type="rich"]').forEach((node) => {
    node.contentEditable = 'true';
    node.title = 'Text bearbeiten';
    toEditable(node);
    if (!node.innerHTML.trim()) node.innerHTML = '<p><br></p>';
    node.addEventListener('focus', () => { activeRich = node; placeFormatBar(node); });
    node.addEventListener('input', () => richChanged(node));
    node.addEventListener('blur', () => setTimeout(() => { if (!fmt.contains(document.activeElement) && document.activeElement !== node) fmt.hidden = true; }, 150));
  });
  window.addEventListener('scroll', () => { if (activeRich && !fmt.hidden) placeFormatBar(activeRich); }, { passive: true });

  function saveSelection() { const s = getSelection(); return s.rangeCount ? s.getRangeAt(0).cloneRange() : null; }
  function restoreSelection(r) { if (!r) return; const s = getSelection(); s.removeAllRanges(); s.addRange(r); }

  /* ---------- Bilder ---------- */
  document.querySelectorAll('[data-rcb-type="image"]').forEach((node) => {
    const holder = node.tagName === 'IMG' ? node.parentElement : node;
    holder.classList.add('rcb-ed-imgholder');
    const inGallery = !!holder.closest('.rcb-gallery');
    const btn = el('button', { type: 'button', class: 'rcb-ed-imgbtn', text: '🖼 Bild ändern', onclick: (e) => {
      e.preventDefault(); e.stopPropagation();
      openImagePicker((url) => {
        if (node.tagName === 'IMG') node.src = url;
        else node.style.backgroundImage = 'linear-gradient(125deg,rgba(4,29,58,.78),rgba(13,71,119,.55)),url("' + url.replace(/"/g, '%22') + '")';
        markDirty(node.dataset.rcbKey, 'image', url);
      }, () => { markDirty(node.dataset.rcbKey, 'image', ''); if (inGallery) holder.classList.add('rcb-ed-removed'); }, inGallery);
    } });
    if (node.tagName === 'IMG') node.insertAdjacentElement('afterend', btn); else holder.prepend(btn);
  });

  /* ---------- Videos (Clips) ---------- */
  document.querySelectorAll('[data-rcb-type="video"]').forEach((node) => {
    const holder = node.tagName === 'VIDEO' ? node.parentElement : node;
    holder.classList.add('rcb-ed-imgholder');
    const btn = el('button', { type: 'button', class: 'rcb-ed-imgbtn', text: node.tagName === 'VIDEO' ? '🎬 Video ändern' : '🎬 Video wählen', onclick: (e) => {
      e.preventDefault(); e.stopPropagation();
      openVideoPicker((url) => {
        holder.classList.remove('rcb-ed-removed', 'rcb-gallery-empty');
        let v = holder.querySelector('video');
        if (!v) { holder.textContent = ''; v = el('video', { class: 'rcb-gallery-vid', muted: 'muted', playsinline: 'playsinline', preload: 'metadata' }); holder.append(v, btn); }
        v.src = url + '#t=0.5';
        markDirty(node.dataset.rcbKey, 'video', url);
      }, () => { markDirty(node.dataset.rcbKey, 'video', ''); holder.classList.add('rcb-ed-removed'); });
    } });
    holder.append(btn);
  });

  function openVideoPicker(onPick, onRemove) {
    const MAX = 50 * 1024 * 1024;
    modal.innerHTML = '';
    const close = () => { modal.hidden = true; };
    const grid = el('div', { class: 'rcb-ed-grid', text: 'Videos werden geladen …' });
    const file = el('input', { type: 'file', accept: 'video/mp4,video/webm,video/quicktime' });
    const info = el('p', { class: 'rcb-ed-hint', text: 'MP4, WebM oder MOV, höchstens 50 MB – ideal sind 10 bis 40 Sekunden, quer gefilmt. iPhone: Einstellungen › Kamera › Formate › „Maximale Kompatibilität“, sonst laufen Videos nicht in jedem Browser.' });
    const bar = el('progress', { max: '100', value: '0', hidden: 'hidden', style: 'width:100%' });
    file.addEventListener('change', () => {
      const f = file.files[0];
      if (!f) return;
      if (f.size > MAX) { info.textContent = 'Das Video ist ' + Math.round(f.size / 1048576) + ' MB groß – erlaubt sind höchstens 50 MB. Bitte kürzen oder in geringerer Auflösung (z. B. 1080p) exportieren.'; file.value = ''; return; }
      const fd = new FormData();
      fd.append('action', 'upload_block_video'); fd.append('csrf_token', csrf); fd.append('media_file', f);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', adminUrl + '?editor=upload_block_video');
      xhr.setRequestHeader('X-Requested-With', 'rcb-editor');
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) { bar.value = Math.round(e.loaded / e.total * 100); info.textContent = 'Wird hochgeladen … ' + bar.value + ' %'; } };
      xhr.onload = () => {
        let data; try { data = JSON.parse(xhr.responseText); } catch (_) { data = { ok: false, error: xhr.status === 413 ? 'Das Video ist zu groß für den Server.' : 'Unerwartete Antwort vom Server.' }; }
        bar.hidden = true;
        if (data.ok) { onPick(data.url); close(); } else { info.textContent = data.error || 'Upload fehlgeschlagen.'; }
      };
      xhr.onerror = () => { bar.hidden = true; info.textContent = 'Upload abgebrochen – bitte Verbindung prüfen und erneut versuchen.'; };
      bar.hidden = false; info.textContent = 'Wird hochgeladen …';
      xhr.send(fd);
    });
    const box = el('div', { class: 'rcb-ed-modal-box' }, [
      el('div', { class: 'rcb-ed-modal-head' }, [el('h2', { text: 'Video auswählen' }), el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Schließen', onclick: close })]),
      el('h3', { text: 'Neues Video hochladen' }), file, bar, info,
      el('h3', { text: 'Oder vorhandenes Video wählen' }), grid,
      el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Video aus der Galerie entfernen', onclick: () => { onRemove(); close(); setStatus('Das Video wird nach dem Speichern entfernt.', 'dirty'); } }),
    ]);
    modal.append(box);
    modal.hidden = false;
    modal.onclick = (e) => { if (e.target === modal) close(); };
    fetch(adminUrl + '?page=mediajson&type=video', { credentials: 'same-origin' }).then((r) => r.json()).then((items) => {
      grid.textContent = '';
      if (!items.length) grid.textContent = 'Noch keine Videos vorhanden.';
      items.forEach((it) => grid.append(el('button', { type: 'button', title: it.title || it.url, onclick: () => { onPick(it.url); close(); } }, [el('video', { src: it.url + '#t=0.5', muted: 'muted', preload: 'metadata', playsinline: 'playsinline' })])));
    }).catch(() => { grid.textContent = 'Videos konnten nicht geladen werden.'; });
  }

  const modal = el('div', { class: 'rcb-ed-modal', hidden: 'hidden', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Bild auswählen' });
  document.body.append(modal);
  function openImagePicker(onPick, onReset, isGallery) {
    modal.innerHTML = '';
    const grid = el('div', { class: 'rcb-ed-grid', text: 'Bilder werden geladen …' });
    const file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp' });
    const info = el('p', { class: 'rcb-ed-hint', text: 'JPG, PNG oder WebP, höchstens 8 MB. Das Bild wird auch unter „Bilder“ im Admin gespeichert.' });
    const close = () => { modal.hidden = true; };
    file.addEventListener('change', async () => {
      if (!file.files[0]) return;
      info.textContent = 'Wird hochgeladen …';
      const fd = new FormData();
      fd.append('action', 'upload_block_image'); fd.append('csrf_token', csrf); fd.append('media_file', file.files[0]);
      try {
        const res = await fetch(adminUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'rcb-editor' }, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({ ok: false, error: 'Unerwartete Antwort vom Server.' }));
        if (!data.ok) throw new Error(data.error || 'Upload fehlgeschlagen.');
        onPick(data.url); close();
      } catch (err) { info.textContent = err.message; }
    });
    const box = el('div', { class: 'rcb-ed-modal-box' }, [
      el('div', { class: 'rcb-ed-modal-head' }, [el('h2', { text: 'Bild auswählen' }), el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Schließen', onclick: close })]),
      el('h3', { text: 'Neues Bild hochladen' }), file, info,
      el('h3', { text: 'Oder vorhandenes Bild wählen' }), grid,
    ]);
    if (onReset) box.append(el('button', { type: 'button', class: 'rcb-ed-btn', text: isGallery ? 'Bild aus der Galerie entfernen' : 'Ursprüngliches Bild wiederherstellen', onclick: () => { onReset(); close(); setStatus(isGallery ? 'Das Bild wird nach dem Speichern aus der Galerie entfernt.' : 'Ursprüngliches Bild wird nach dem Speichern wieder angezeigt.', 'dirty'); } }));
    modal.append(box);
    modal.hidden = false;
    modal.onclick = (e) => { if (e.target === modal) close(); };
    fetch(adminUrl + '?page=mediajson', { credentials: 'same-origin' }).then((r) => r.json()).then((items) => {
      grid.textContent = '';
      if (!items.length) grid.textContent = 'Noch keine Bilder vorhanden.';
      items.forEach((it) => grid.append(el('button', { type: 'button', title: it.title || it.url, onclick: () => { onPick(it.url); close(); } }, [el('img', { src: it.url, alt: it.title || '' })])));
    }).catch(() => { grid.textContent = 'Bilder konnten nicht geladen werden.'; });
  }

  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); } });
  }

  /* ---------- Entwurf speichern ---------- */
  async function save() {
    if (!dirty.size) return true;
    saveBtn.disabled = true;
    setStatus('Entwurf wird gespeichert …');
    const blocks = {};
    dirty.forEach((v, k) => { blocks[k] = v; });
    try {
      const data = await post('save_blocks', { blocks: JSON.stringify(blocks) });
      dirty.clear();
      showPending(data.pending ?? pending);
      setStatus('Entwurf gespeichert ✓ – noch nicht online. Zum Online-Stellen „Veröffentlichen“ klicken.', 'ok');
      return true;
    } catch (err) {
      saveBtn.disabled = false;
      setStatus(err.message, 'error');
      return false;
    }
  }
  window.addEventListener('beforeunload', (e) => { if (dirty.size) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- Links: im Bearbeitungsmodus bleiben, Klicks in Textfeldern nicht folgen ---------- */
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a');
    if (!a || bar.contains(a)) return;
    if (a.closest('[contenteditable="true"],[contenteditable="plaintext-only"]') || a.querySelector('.rcb-edit')) { e.preventDefault(); return; }
    const url = new URL(a.href, location.href);
    if (url.origin === location.origin && !url.pathname.startsWith('/admin') && !url.pathname.startsWith('/uploads')) {
      e.preventDefault();
      go(url.pathname + url.search);
    }
  });
})();
