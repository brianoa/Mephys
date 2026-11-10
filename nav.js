/* Accessible mobile sidebar + services submenu, shared by clinic and shop pages. */
(function () {
  'use strict';
  function initNavigation(nav) {
    const toggle = nav.querySelector('.mephys-menu-toggle');
    const panel = nav.querySelector('.mephys-nav-panel');
    const close = nav.querySelector('.mephys-menu-close');
    const backdrop = nav.nextElementSibling && nav.nextElementSibling.classList.contains('mephys-nav-backdrop')
      ? nav.nextElementSibling : null;
    const submenuToggle = nav.querySelector('.mephys-services-toggle');
    const submenu = submenuToggle ? submenuToggle.closest('.mephys-has-submenu') : null;
    const mobileQuery = window.matchMedia('(max-width: 991.98px)');
    if (!toggle || !panel || !close || !backdrop) return;

    function setSubmenu(open) {
      if (!submenu) return;
      submenu.classList.toggle('is-open', open);
      submenuToggle.setAttribute('aria-expanded', String(open));
    }
    function setMenu(open, restoreFocus) {
      if (!mobileQuery.matches) open = false;
      panel.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
      backdrop.hidden = !open;
      document.body.classList.toggle('mephys-menu-open', open);
      if (!open) setSubmenu(false);
      if (open) close.focus();
      else if (restoreFocus) toggle.focus();
    }
    toggle.addEventListener('click', () => setMenu(!panel.classList.contains('is-open'), false));
    close.addEventListener('click', () => setMenu(false, true));
    backdrop.addEventListener('click', () => setMenu(false, true));
    if (submenuToggle) {
      submenuToggle.addEventListener('click', () => setSubmenu(!submenu.classList.contains('is-open')));
    }
    nav.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (link && panel.contains(link) && mobileQuery.matches) setMenu(false, false);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        if (panel.classList.contains('is-open')) { setMenu(false, true); }
        else if (submenu && submenu.classList.contains('is-open')) { setSubmenu(false); submenuToggle.focus(); }
      }
      if (event.key === 'Tab' && panel.classList.contains('is-open') && mobileQuery.matches) {
        const focusables = Array.from(panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])'))
          .filter(el => el.getClientRects().length > 0);
        if (!focusables.length) return;
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      }
    });
    document.addEventListener('click', (event) => {
      if (submenu && !submenu.contains(event.target) && !mobileQuery.matches) setSubmenu(false);
    });
    window.addEventListener('resize', () => {
      if (!mobileQuery.matches && panel.classList.contains('is-open')) setMenu(false, false);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-mobile-nav]').forEach(initNavigation));
  else document.querySelectorAll('[data-mobile-nav]').forEach(initNavigation);
})();
