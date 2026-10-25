(() => {
  const root = document.documentElement;
  const settingsPage = document.querySelector('[data-settings-page]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const defaults = {
    theme: 'light',
    font_size: 'standard',
    high_contrast: false,
    reduce_motion: false,
    underline_links: false,
    line_spacing: 'normal'
  };
  let preferences = { ...defaults };
  let saveTimer = 0;
  let saveQueue = Promise.resolve();
  let lastAppliedFontSize = null;
  let themeQuery = null;
  let themeListener = null;

  const fontScale = {
    small: 0.875,
    standard: 1,
    large: 1.125,
    'extra-large': 1.25
  };

  const captureBaseFontSizes = (nodes) => {
    nodes.forEach((node) => {
      if (!(node instanceof Element) || node.hasAttribute('data-settings-font-captured')) return;
      node.style.setProperty('--settings-base-font-size', getComputedStyle(node).fontSize);
      node.setAttribute('data-settings-font-captured', '');
      node.querySelectorAll('*').forEach((child) => {
        if (child.hasAttribute('data-settings-font-captured')) return;
        child.style.setProperty('--settings-base-font-size', getComputedStyle(child).fontSize);
        child.setAttribute('data-settings-font-captured', '');
      });
    });
  };

  const fontObserver = new MutationObserver((records) => {
    if (!root.classList.contains('preference-font-scale-active')) return;
    const added = records.flatMap((record) => Array.from(record.addedNodes))
      .filter((node) => node instanceof Element && !node.hasAttribute('data-settings-font-captured'));
    if (added.length === 0) return;
    requestAnimationFrame(() => {
      const shouldScale = root.classList.contains('preference-font-scale-active');
      root.classList.remove('preference-font-scale-active');
      captureBaseFontSizes(added);
      if (shouldScale) root.classList.add('preference-font-scale-active');
    });
  });

  const setFontSize = (size) => {
    root.dataset.fontSize = size;
    if (size === 'standard') {
      root.classList.remove('preference-font-scale-active');
      lastAppliedFontSize = size;
      return;
    }
    if (!fontScale[size]) return;
    if (lastAppliedFontSize === null || lastAppliedFontSize === 'standard') {
      root.classList.remove('preference-font-scale-active');
      captureBaseFontSizes([root]);
    }
    root.style.setProperty('--settings-font-scale', String(fontScale[size]));
    root.classList.add('preference-font-scale-active');
    lastAppliedFontSize = size;
  };

  const applyPreferences = (next) => {
    preferences = { ...defaults, ...next };
    root.dataset.themeChoice = preferences.theme;
    root.dataset.theme = preferences.theme === 'auto'
      ? (themeQuery?.matches ? 'dark' : 'light')
      : preferences.theme;
    root.dataset.highContrast = String(preferences.high_contrast);
    root.dataset.reduceMotion = String(preferences.reduce_motion);
    root.dataset.underlineLinks = String(preferences.underline_links);
    root.dataset.lineSpacing = preferences.line_spacing;
    setFontSize(preferences.font_size);
    syncControls();
  };

  const syncControls = () => {
    if (!settingsPage) return;
    settingsPage.querySelectorAll('[data-preference]').forEach((control) => {
      const { name } = control;
      if (!Object.prototype.hasOwnProperty.call(preferences, name)) return;
      if (control.type === 'radio') control.checked = control.value === preferences[name];
      else if (control.type === 'checkbox') control.checked = Boolean(preferences[name]);
      else control.value = String(preferences[name]);
    });
    const preview = settingsPage.querySelector('.settings-preview');
    if (preview) {
      const labels = { small: 'Pequeno', standard: 'Padrão', large: 'Grande', 'extra-large': 'Extragrande' };
      preview.textContent = `Prévia ${labels[preferences.font_size]}: textos e controles ajustam a escala sem remover o zoom do navegador.`;
    }
  };

  const status = (message, isError = false) => {
    const output = settingsPage?.querySelector('[data-save-status]');
    if (!output) return;
    output.textContent = message;
    output.dataset.error = String(isError);
  };

  const readControls = () => {
    const next = { ...preferences };
    settingsPage.querySelectorAll('[data-preference]').forEach((control) => {
      if (control.type === 'radio') {
        if (control.checked) next[control.name] = control.value;
      } else if (control.type === 'checkbox') {
        next[control.name] = control.checked;
      } else {
        next[control.name] = control.value;
      }
    });
    return next;
  };

  const savePreferences = (snapshot) => {
    saveQueue = saveQueue.catch(() => {}).then(async () => {
      const response = await fetch('api/configuracoes.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({ preferences: snapshot })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Não foi possível salvar as preferências.');
      if (JSON.stringify(readControls()) === JSON.stringify(snapshot)) {
        applyPreferences(result.preferences);
        status('Preferências salvas.');
      }
    }).catch((error) => {
      status(error instanceof Error ? error.message : 'Não foi possível salvar as preferências.', true);
    });
    return saveQueue;
  };

  const scheduleSave = () => {
    applyPreferences(readControls());
    status('Salvando…');
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
      void savePreferences(readControls());
    }, 200);
  };

  const refreshSystemTheme = () => {
    if (preferences.theme === 'auto') {
      root.dataset.theme = themeQuery.matches ? 'dark' : 'light';
    }
  };

  const initializeSystemTheme = () => {
    if (!window.matchMedia) return;
    themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    themeListener = refreshSystemTheme;
    if (themeQuery.addEventListener) themeQuery.addEventListener('change', themeListener);
    else themeQuery.addListener(themeListener);
  };

  const loadPreferences = async () => {
    try {
      const response = await fetch('api/configuracoes.php', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      });
      const result = await response.json();
      if (!response.ok || !result.preferences) throw new Error(result.error || 'Não foi possível carregar as preferências.');
      applyPreferences(result.preferences);
      if (settingsPage) status('');
    } catch (error) {
      if (settingsPage) status(error instanceof Error ? error.message : 'Não foi possível carregar as preferências.', true);
    }
  };

  initializeSystemTheme();
  fontObserver.observe(document.body, { childList: true, subtree: true });

  if (settingsPage) {
    settingsPage.addEventListener('change', (event) => {
      if (event.target instanceof Element && event.target.matches('[data-preference]')) scheduleSave();
    });

    settingsPage.querySelector('[data-reset-preferences]')?.addEventListener('click', () => {
      applyPreferences(defaults);
      status('Restaurando aparência padrão…');
      void savePreferences({ ...defaults });
    });

    settingsPage.querySelector('[data-settings-select]')?.addEventListener('change', (event) => {
      const section = event.target.value;
      if (section) window.location.hash = section;
    });
  }

  void loadPreferences();
})();
