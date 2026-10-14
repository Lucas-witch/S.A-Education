const ensureManifestLink = () => {
  if (document.querySelector('link[rel="manifest"]')) {
    return;
  }

  const link = document.createElement('link');
  link.rel = 'manifest';
  link.href = './manifest.json';
  document.head.appendChild(link);
};

const registerServiceWorker = async () => {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  const isLocalhost = ['localhost', '127.0.0.1', ''].includes(window.location.hostname);
  if (!isLocalhost && window.location.protocol !== 'https:') {
    return;
  }

  const workerScope = new URL('.', new URL('./assets/js/sw.js', window.location.href)).pathname;

  try {
    await navigator.serviceWorker.register(new URL('./assets/js/sw.js', window.location.href), {
      scope: workerScope
    });
  } catch (error) {
    console.warn('Service worker não foi registrado:', error);
  }
};

ensureManifestLink();
registerServiceWorker();
window.addEventListener('load', registerServiceWorker);

document.addEventListener('DOMContentLoaded', () => {
  const guideButton = document.querySelector('[data-guide-toggle]');
  const guideCloseButton = document.querySelector('[data-guide-close]');
  const guidePanel = document.getElementById('quick-guide-panel');
  const guideOverlay = document.getElementById('quick-guide-overlay');
  const sidebar = document.querySelector('.sidebar');
  const sidebarClose = document.querySelector('[data-sidebar-close]');

  const setGuideState = (open) => {
    guidePanel?.classList.toggle('open', open);
    guidePanel?.toggleAttribute('hidden', !open);
    guidePanel?.setAttribute('aria-hidden', String(!open));
    guideButton?.setAttribute('aria-expanded', String(open));
    guideOverlay?.toggleAttribute('hidden', !open);
  };

  const setSidebarState = (open) => {
    sidebar?.classList.toggle('open', open);
    sidebar?.classList.toggle('collapsed', !open);
  };

  if (guideButton) {
    guideButton.addEventListener('click', (event) => {
      event.stopPropagation();
      const isOpen = !guidePanel?.hasAttribute('hidden');
      setGuideState(!isOpen);
    });
  }

  guideCloseButton?.addEventListener('click', () => setGuideState(false));
  guideOverlay?.addEventListener('click', () => setGuideState(false));
  sidebarClose?.addEventListener('click', () => setSidebarState(false));

  document.addEventListener('click', (event) => {
    const clickedInsideSidebar = event.target.closest('.sidebar');
    const clickedGuideButton = event.target.closest('[data-guide-toggle]');
    const clickedInsideGuidePanel = event.target.closest('.quick-guide-panel');
    const clickedInsideOverlay = event.target.closest('.quick-guide-overlay');

    if (!clickedInsideSidebar && !clickedGuideButton && !clickedInsideGuidePanel && !clickedInsideOverlay && guidePanel?.classList.contains('open')) {
      setGuideState(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      if (guidePanel?.classList.contains('open')) {
        setGuideState(false);
        return;
      }
      if (sidebar?.classList.contains('open')) {
        setSidebarState(false);
      }
    }
  });

  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
});
