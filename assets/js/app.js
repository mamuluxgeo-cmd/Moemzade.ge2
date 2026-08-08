(() => {
  const button = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  if (button && menu) {
    button.addEventListener('click', () => {
      const open = menu.classList.toggle('open');
      button.setAttribute('aria-expanded', String(open));
    });
  }

  document.querySelectorAll('.flash').forEach((element) => {
    window.setTimeout(() => element.classList.add('flash-hidden'), 6000);
  });
})();

