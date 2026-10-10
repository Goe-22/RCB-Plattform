document.addEventListener('DOMContentLoaded', () => {
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
