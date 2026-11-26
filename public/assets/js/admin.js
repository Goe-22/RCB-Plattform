// Sicherheitsabfrage vor Löschen/Verwerfen (Inline-Skripte sind im Adminbereich aus Sicherheitsgründen gesperrt).
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
});

// Termine: weitere Klassenzeile hinzufügen.
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-add-class]');
  if (!button) return;
  const rows = document.getElementById('class-rows');
  const template = document.getElementById('class-row-template');
  if (!rows || !template) return;
  const index = rows.querySelectorAll('.class-row').length + Date.now() % 1000;
  const html = template.innerHTML.replace(/class\[999\]/g, `class[${index}]`);
  rows.insertAdjacentHTML('beforeend', html);
  rows.lastElementChild?.querySelector('input')?.focus();
});

// Termine: „bis“-Datum beginnt beim ersten Tag statt beim heutigen Datum.
(() => {
  const start = document.getElementById('event_date');
  const end = document.getElementById('end_date');
  if (!start || !end) return;
  const sync = () => {
    if (!start.value) { end.removeAttribute('min'); return; }
    end.min = start.value;
    if (!end.value || end.value < start.value) end.value = start.value;
  };
  start.addEventListener('change', sync);
  end.addEventListener('focus', () => { if (start.value && !end.value) end.value = start.value; });
  if (start.value) end.min = start.value;
})();

// Einladungs-/Passwort-Link in die Zwischenablage kopieren.
document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy]');
  if (!button) return;
  const field = document.getElementById(button.dataset.copy);
  if (!field) return;
  field.select();
  try { await navigator.clipboard.writeText(field.value); } catch (e) { document.execCommand('copy'); }
  const old = button.textContent; button.textContent = 'Kopiert ✓';
  setTimeout(() => { button.textContent = old; }, 2000);
});

// Design: Farbvorschau live aktualisieren
document.querySelectorAll('.design-form input[type="color"]').forEach((input) => {
  input.addEventListener('input', () => {
    const code = input.parentElement.querySelector('code');
    if (code) code.textContent = input.value;
    const preview = document.querySelector('.design-preview');
    if (preview) preview.style.setProperty(input.name === 'red' ? '--p-red' : '--p-blue', input.value);
  });
});


// Strecken: eigene Steckbrief-Zeilen hinzufügen, sortieren, entfernen, ein-/ausblenden
document.querySelectorAll('[data-facts]').forEach((list) => {
  const form = list.closest('form');
  form.querySelector('[data-fact-add]')?.addEventListener('click', () => {
    const row = list.querySelector('.fact-row').cloneNode(true);
    row.querySelectorAll('input[name="fact_label[]"], input[name="fact_value[]"]').forEach((i) => { i.value = ''; });
    row.querySelector('input[name="fact_show[]"]').value = '1';
    row.querySelector('[data-fact-show]').checked = true;
    list.append(row);
    row.querySelector('input[name="fact_label[]"]').focus();
  });
  list.addEventListener('click', (e) => {
    const row = e.target.closest('.fact-row');
    if (!row) return;
    if (e.target.closest('[data-fact-up]') && row.previousElementSibling) row.previousElementSibling.before(row);
    if (e.target.closest('[data-fact-down]') && row.nextElementSibling) row.nextElementSibling.after(row);
    if (e.target.closest('[data-fact-del]')) {
      if (list.querySelectorAll('.fact-row').length > 1) row.remove();
      else row.querySelectorAll('input[name="fact_label[]"], input[name="fact_value[]"]').forEach((i) => { i.value = ''; });
    }
  });
  list.addEventListener('change', (e) => {
    if (e.target.matches('[data-fact-show]')) e.target.closest('.fact-row').querySelector('input[name="fact_show[]"]').value = e.target.checked ? '1' : '0';
  });
});


// Neuigkeiten: Laufzeit-Auswahl – Datumsfeld nur bei „Bis Datum“, Hinweis mit Enddatum
document.querySelectorAll('fieldset.expiry').forEach((box) => {
  const info = box.querySelector('[data-expiry-info]');
  const date = box.querySelector('input[name="expires_on"]');
  const fmt = (d) => d.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
  const update = () => {
    const v = box.querySelector('input[name="expiry"]:checked')?.value;
    box.classList.toggle('show-date', v === 'date');
    const plus = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return d; };
    let text = 'Bleibt auf der Startseite, bis du sie löschst oder die Laufzeit änderst.';
    if (v === '14d') text = 'Sichtbar bis ' + fmt(plus(14)) + ' – danach verschwindet sie von selbst.';
    if (v === '28d') text = 'Sichtbar bis ' + fmt(plus(28)) + ' – danach verschwindet sie von selbst.';
    if (v === 'date' && date.value) text = 'Sichtbar bis einschließlich ' + fmt(new Date(date.value + 'T12:00')) + '.';
    if (v === 'keep') text = 'Die bisherige Laufzeit bleibt bestehen.';
    info.textContent = text + ' Die Meldung bleibt hier gespeichert und lässt sich jederzeit verlängern.';
  };
  box.addEventListener('change', update);
  date?.addEventListener('input', update);
  update();
});
