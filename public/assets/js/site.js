document.addEventListener('DOMContentLoaded', () => {
  // Untermenüs (z. B. „Strecken“): per Klick öffnen, Klick daneben oder Escape schließt.
  const closeSubs = (except) => document.querySelectorAll('.rcb-has-sub.open').forEach((li) => {
    if (li !== except) { li.classList.remove('open'); li.querySelector('.rcb-sub-toggle')?.setAttribute('aria-expanded', 'false'); }
  });
  document.querySelectorAll('.rcb-sub-toggle').forEach((toggle) => {
    toggle.addEventListener('click', (event) => {
      event.stopPropagation();
      const li = toggle.closest('.rcb-has-sub');
      closeSubs(li);
      const open = li.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      if (open) li.querySelector('.rcb-sub a')?.focus({ preventScroll: true });
    });
  });
  document.addEventListener('click', (event) => { if (!event.target.closest('.rcb-has-sub')) closeSubs(); });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const open = document.querySelector('.rcb-has-sub.open');
    if (open) { closeSubs(); open.querySelector('.rcb-sub-toggle')?.focus(); }
  });

  const button = document.querySelector('.rcb-menu-button');
  const nav = document.querySelector('.rcb-nav nav');
  if (!button || !nav) return;

  button.addEventListener('click', () => {
    const isOpen = nav.classList.toggle('open');
    button.setAttribute('aria-expanded', String(isOpen));
    button.setAttribute('aria-label', isOpen ? 'Menü schließen' : 'Menü öffnen');
    button.textContent = isOpen ? '×' : '☰';
  });

  nav.addEventListener('click', (event) => {
    if (event.target instanceof HTMLAnchorElement && window.matchMedia('(max-width: 900px)').matches) {
      nav.classList.remove('open');
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Menü öffnen');
      button.textContent = '☰';
    }
  });
});
