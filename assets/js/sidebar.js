document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('#site-navigation');
  const closeButton = document.querySelector('[data-sidebar-close]');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');
  const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  if (!menuButton || !sidebar || !closeButton || !backdrop) {
    return;
  }

  let scrollY = 0;
  let previousBodyStyles = null;
  let previousBackgroundStates = [];

  const getFocusableElements = () => [...sidebar.querySelectorAll(focusableSelector)]
    .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');

  const openSidebar = () => {
    if (menuButton.getAttribute('aria-expanded') === 'true') {
      return;
    }

    scrollY = window.scrollY;
    previousBodyStyles = {
      position: document.body.style.position,
      top: document.body.style.top,
      width: document.body.style.width,
      overflow: document.body.style.overflow
    };
    previousBackgroundStates = [...document.querySelectorAll('.top-header-shell, .main, .app-footer')]
      .map((element) => ({
        element,
        inert: element.inert,
        ariaHidden: element.getAttribute('aria-hidden')
      }));
    previousBackgroundStates.forEach(({ element }) => {
      element.inert = true;
      element.setAttribute('aria-hidden', 'true');
    });
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';

    sidebar.inert = false;
    sidebar.setAttribute('aria-hidden', 'false');
    sidebar.classList.add('open');
    backdrop.hidden = false;
    backdrop.setAttribute('aria-hidden', 'false');
    menuButton.setAttribute('aria-expanded', 'true');
    menuButton.setAttribute('aria-label', 'Fechar menu');
    document.documentElement.classList.add('sidebar-open');
    closeButton.focus();
  };

  const closeSidebar = () => {
    if (menuButton.getAttribute('aria-expanded') !== 'true') {
      return;
    }

    sidebar.classList.remove('open');
    sidebar.setAttribute('aria-hidden', 'true');
    sidebar.inert = true;
    backdrop.hidden = true;
    backdrop.setAttribute('aria-hidden', 'true');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Abrir menu');
    document.documentElement.classList.remove('sidebar-open');

    if (previousBodyStyles) {
      document.body.style.position = previousBodyStyles.position;
      document.body.style.top = previousBodyStyles.top;
      document.body.style.width = previousBodyStyles.width;
      document.body.style.overflow = previousBodyStyles.overflow;
      previousBodyStyles = null;
    }
    previousBackgroundStates.forEach(({ element, inert, ariaHidden }) => {
      element.inert = inert;
      if (ariaHidden === null) {
        element.removeAttribute('aria-hidden');
      } else {
        element.setAttribute('aria-hidden', ariaHidden);
      }
    });
    previousBackgroundStates = [];
    window.scrollTo(0, scrollY);
    menuButton.focus();
  };

  menuButton.addEventListener('click', () => {
    if (menuButton.getAttribute('aria-expanded') === 'true') {
      closeSidebar();
    } else {
      openSidebar();
    }
  });
  closeButton.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', (event) => {
    if (menuButton.getAttribute('aria-expanded') !== 'true') {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      closeSidebar();
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    const focusableElements = getFocusableElements();
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];
    if (!firstFocusable || !lastFocusable) {
      event.preventDefault();
      closeButton.focus();
      return;
    }

    if (event.shiftKey && (document.activeElement === firstFocusable || !sidebar.contains(document.activeElement))) {
      event.preventDefault();
      lastFocusable.focus();
    } else if (!event.shiftKey && (document.activeElement === lastFocusable || !sidebar.contains(document.activeElement))) {
      event.preventDefault();
      firstFocusable.focus();
    }
  });
});
