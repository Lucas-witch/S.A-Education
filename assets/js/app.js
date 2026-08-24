document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-menu]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelector('.sidebar')?.classList.toggle('open');
    });
  });

  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
});
