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
