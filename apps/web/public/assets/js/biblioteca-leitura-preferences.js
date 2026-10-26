(() => {
  const params = new URLSearchParams(window.location.search);
  const materialId = Number(params.get('id'));
  const article = document.getElementById('reading-content');
  const fontSize = document.getElementById('font-size');
  const lineSpacing = document.getElementById('line-spacing');
  const readingMode = document.getElementById('reading-mode');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  if (!Number.isSafeInteger(materialId) || materialId <= 0 || !article || !fontSize || !lineSpacing || !readingMode) return;
  let controlsChanged = false;
  let saveTimer = 0;
  let saveQueue = Promise.resolve();

  const apply = (preferences) => {
    if (['small', 'standard', 'large', 'extra-large'].includes(preferences.font_size)) {
      fontSize.value = { small: '1rem', standard: '1.125rem', large: '1.25rem', 'extra-large': '1.5rem' }[preferences.font_size];
    }
    if (['normal', 'wide', 'compact'].includes(preferences.line_spacing)) lineSpacing.value = preferences.line_spacing;
    if (['light', 'warm', 'dark'].includes(preferences.reading_mode)) readingMode.value = preferences.reading_mode;
    article.style.fontSize = fontSize.value;
    article.dataset.spacing = lineSpacing.value;
    article.dataset.mode = readingMode.value;
  };
  const save = () => {
    const snapshot = {
      font_size: ({ '1rem': 'small', '1.125rem': 'standard', '1.25rem': 'large', '1.5rem': 'extra-large' })[fontSize.value] || 'standard',
      line_spacing: lineSpacing.value,
      reading_mode: readingMode.value
    };
    saveQueue = saveQueue.catch(() => {}).then(async () => {
      const data = new FormData();
      data.set('csrf', csrf);
      data.set('material_id', String(materialId));
      data.set('action', 'preferences');
      Object.entries(snapshot).forEach(([key, value]) => data.set(key, value));
      const response = await fetch('api/biblioteca-leitura.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar os ajustes de leitura.');
    });
    return saveQueue;
  };

  fetch(`api/biblioteca-leitura.php?material_id=${encodeURIComponent(String(materialId))}`, {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  }).then(async (response) => {
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Não foi possível carregar os ajustes de leitura.');
    if (result.progress) {
      if (!controlsChanged) apply(result.progress);
      if (result.progress.position_anchor) {
        const anchor = document.getElementById(result.progress.position_anchor);
        if (anchor && article.contains(anchor)) anchor.scrollIntoView({ block: 'start' });
      }
    }
  }).catch((error) => {
    const label = document.getElementById('progress-label');
    if (label) label.textContent = error instanceof Error ? error.message : 'Não foi possível carregar os ajustes de leitura.';
  });

  [fontSize, lineSpacing, readingMode].forEach((control) => {
    control.addEventListener('change', () => {
      controlsChanged = true;
      apply({
        font_size: ({ '1rem': 'small', '1.125rem': 'standard', '1.25rem': 'large', '1.5rem': 'extra-large' })[fontSize.value],
        line_spacing: lineSpacing.value,
        reading_mode: readingMode.value
      });
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(() => {
        void save().catch((error) => {
          const label = document.getElementById('progress-label');
          if (label) label.textContent = error instanceof Error ? error.message : 'Não foi possível salvar os ajustes de leitura.';
        });
      }, 250);
    });
  });
})();
