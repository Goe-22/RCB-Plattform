/* Seiten-Editor der RC Bande Zwickau: Texte und Bilder direkt auf der Seite bearbeiten. */
(() => {
  const csrf = document.querySelector('meta[name="rcb-csrf"]')?.content || '';
  const adminUrl = '/admin/index.php';
  const dirty = new Map();
  const PAGES = [
    ['/', 'Startseite'], ['/verein.php', 'Verein'], ['/strecken.php', 'Strecken'], ['/onroad.php', 'Onroad'],
    ['/offroad.php', 'Offroad'], ['/termine.php', 'Termine'], ['/neuigkeiten.php', 'Neuigkeiten'],
    ['/reglement.php', 'Reglement'], ['/ergebnisse.php', 'Ergebnisse'], ['/live.php', 'Live-Timing'],
    ['/mitglied-werden.php', 'Mitglied werden'], ['/kontakt.php', 'Kontakt'], ['/downloads.php', 'Downloads'],
    ['/impressum.php', 'Impressum'], ['/datenschutz.php', 'Datenschutz'], ['/haftungsausschluss.php', 'Haftungsausschluss'],
  ];

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
  const status = el('span', { class: 'rcb-ed-status', text: 'Klicke auf markierte Texte oder Bilder, um sie zu ändern.' });
  const saveBtn = el('button', { type: 'button', class: 'rcb-ed-btn rcb-ed-primary', text: 'Speichern', disabled: 'disabled', onclick: save });
  const here = location.pathname === '/index.php' ? '/' : location.pathname;
  const pageSelect = el('select', { class: 'rcb-ed-select', 'aria-label': 'Seite wechseln', onchange: (e) => go(e.target.value) },
    PAGES.map(([url, label]) => { const o = el('option', { value: url, text: label }); if (url === here) o.selected = true; return o; }));
  const bar = el('div', { class: 'rcb-ed-bar', role: 'toolbar', 'aria-label': 'Seiten-Editor' }, [
    el('strong', { text: '✎ Bearbeiten' }), pageSelect, status,
    el('span', { class: 'rcb-ed-spacer' }),
    el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Verwerfen', onclick: () => { if (!dirty.size || confirm('Alle ungespeicherten Änderungen verwerfen?')) { dirty.clear(); location.reload(); } } }),
    saveBtn,
    el('a', { class: 'rcb-ed-btn', href: location.pathname + location.search.replace(/[?&]bearbeiten=1/, '').replace(/^&/, '?'), target: '_blank', text: 'Vorschau' }),
    el('a', { class: 'rcb-ed-btn', href: adminUrl + '?page=pages', text: 'Zum Admin' }),
  ]);
  document.body.prepend(bar);
  document.body.classList.add('rcb-editing');

  function go(url) {
    if (dirty.size && !confirm('Es gibt ungespeicherte Änderungen. Trotzdem die Seite wechseln?')) { pageSelect.value = here; return; }
    dirty.clear();
    location.href = url + (url.includes('?') ? '&' : '?') + 'bearbeiten=1';
  }

  function setStatus(text, kind = '') { status.textContent = text; status.dataset.kind = kind; }
  function markDirty(key, type, value) {
    dirty.set(key, { type, value });
    saveBtn.disabled = false;
    setStatus(dirty.size + (dirty.size === 1 ? ' ungespeicherte Änderung' : ' ungespeicherte Änderungen'), 'dirty');
  }

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
  ];
  fmtButtons.forEach(([label, title, fn]) => fmt.append(el('button', {
    type: 'button', title, 'aria-label': title, text: label,
    onmousedown: (e) => e.preventDefault(),
    onclick: () => { if (activeRich) { activeRich.focus(); fn(); richChanged(activeRich); } },
  })));
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
    const btn = el('button', { type: 'button', class: 'rcb-ed-imgbtn', text: '🖼 Bild ändern', onclick: (e) => {
      e.preventDefault(); e.stopPropagation();
      openImagePicker((url) => {
        if (node.tagName === 'IMG') node.src = url;
        else node.style.backgroundImage = 'linear-gradient(125deg,rgba(4,29,58,.78),rgba(13,71,119,.55)),url("' + url.replace(/"/g, '%22') + '")';
        markDirty(node.dataset.rcbKey, 'image', url);
      }, () => markDirty(node.dataset.rcbKey, 'image', ''));
    } });
    if (node.tagName === 'IMG') node.insertAdjacentElement('afterend', btn); else holder.prepend(btn);
  });

  const modal = el('div', { class: 'rcb-ed-modal', hidden: 'hidden', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Bild auswählen' });
  document.body.append(modal);
  function openImagePicker(onPick, onReset) {
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
    if (onReset) box.append(el('button', { type: 'button', class: 'rcb-ed-btn', text: 'Ursprüngliches Bild wiederherstellen', onclick: () => { onReset(); close(); setStatus('Ursprüngliches Bild wird nach dem Speichern wieder angezeigt.', 'dirty'); } }));
    modal.append(box);
    modal.hidden = false;
    modal.onclick = (e) => { if (e.target === modal) close(); };
    fetch(adminUrl + '?page=mediajson', { credentials: 'same-origin' }).then((r) => r.json()).then((items) => {
      grid.textContent = '';
      if (!items.length) grid.textContent = 'Noch keine Bilder vorhanden.';
      items.forEach((it) => grid.append(el('button', { type: 'button', title: it.title || it.url, onclick: () => { onPick(it.url); close(); } }, [el('img', { src: it.url, alt: it.title || '' })])));
    }).catch(() => { grid.textContent = 'Bilder konnten nicht geladen werden.'; });
  }

  /* ---------- Speichern ---------- */
  async function save() {
    if (!dirty.size) return;
    saveBtn.disabled = true;
    setStatus('Wird gespeichert …');
    const blocks = {};
    dirty.forEach((v, k) => { blocks[k] = v; });
    const fd = new FormData();
    fd.append('action', 'save_blocks'); fd.append('csrf_token', csrf); fd.append('blocks', JSON.stringify(blocks));
    try {
      const res = await fetch(adminUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'rcb-editor' }, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({ ok: false, error: res.status === 419 ? 'Sitzung abgelaufen. Bitte im Admin neu anmelden.' : 'Unerwartete Antwort vom Server.' }));
      if (!data.ok) throw new Error(data.error || 'Speichern fehlgeschlagen.');
      dirty.clear();
      setStatus('Gespeichert ✓ – die Änderungen sind jetzt online.', 'ok');
    } catch (err) {
      saveBtn.disabled = false;
      setStatus(err.message, 'error');
    }
  }
  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); } });
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
