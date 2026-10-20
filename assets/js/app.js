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

  const workerScope = new URL('.', window.location.href).pathname;

  try {
    await navigator.serviceWorker.register(new URL('./sw.js', window.location.href), {
      scope: workerScope,
      updateViaCache: 'none'
    });
  } catch (error) {
    console.warn('Service worker não foi registrado:', error);
  }
};

ensureManifestLink();
registerServiceWorker();

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
});
