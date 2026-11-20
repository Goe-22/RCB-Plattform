// Sicherheitsabfrage vor Löschen/Verwerfen (Inline-Skripte sind im Adminbereich aus Sicherheitsgründen gesperrt).
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
    event.preventDefault();
  }
});
