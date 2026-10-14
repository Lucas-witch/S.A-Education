document.addEventListener('DOMContentLoaded', () => {
  const guideButton = document.querySelector('[data-guide-toggle]');
  const guidePanel = document.getElementById('quick-guide-panel');
  const sidebar = document.querySelector('.sidebar');

  if (guideButton && guidePanel) {
    guideButton.addEventListener('click', () => {
      const isOpen = sidebar?.classList.contains('open');
      sidebar?.classList.toggle('open', !isOpen);
      guideButton.setAttribute('aria-expanded', String(!isOpen));
      guidePanel.toggleAttribute('hidden', isOpen);
    });
  }

  document.addEventListener('click', (event) => {
    const clickedInsideGuide = event.target.closest('[data-guide-toggle]') || event.target.closest('.quick-guide-panel') || event.target.closest('.sidebar');
    if (!clickedInsideGuide && sidebar?.classList.contains('open')) {
      sidebar.classList.remove('open');
      guideButton?.setAttribute('aria-expanded', 'false');
      guidePanel?.setAttribute('hidden', 'hidden');
    }
  });

  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
});
