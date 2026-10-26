(() => {
  const apiUrl = 'api/feed.php';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const currentUserId = Number(document.body.dataset.userId || 0);

  const setStatus = (node, message, isError = false) => {
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('feed-state-error', isError);
  };

  const readJsonResponse = async response => {
    try {
      return await response.json();
    } catch {
      throw new Error('O serviço retornou uma resposta inválida. Tente novamente.');
    }
  };

  const request = async (url, options) => {
    try {
      return await fetch(url, options);
    } catch {
      throw new Error('Não foi possível conectar ao Feed agora. Verifique sua conexão e tente novamente.');
    }
  };

  const send = async (fields) => {
    const formData = fields instanceof FormData ? fields : new FormData();
    if (!(fields instanceof FormData)) {
      Object.entries({ csrf, ...fields }).forEach(([key, value]) => formData.append(key, value));
    } else if (!formData.has('csrf')) {
      formData.append('csrf', csrf);
    }
    const response = await request(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const data = await readJsonResponse(response);
    if (!response.ok) throw new Error(data.error || 'Não foi possível concluir a ação.');
    return data;
  };

  const makeComment = (comment, ownerId, canModerate) => {
    const item = document.createElement('li');
    item.className = 'feed-comment';
    const head = document.createElement('div');
    head.className = 'feed-comment-head';
    const author = document.createElement('span');
    author.className = 'feed-comment-author';
    author.textContent = comment.autor || 'Autor não informado';
    const time = document.createElement('time');
    time.className = 'feed-comment-time';
    time.dateTime = comment.criado_em || '';
    time.textContent = comment.criado_em || '';
    head.append(author, time);
    const text = document.createElement('p');
    text.className = 'feed-comment-text';
    text.textContent = comment.conteudo || '';
    item.append(head, text);

    if (Number(comment.usuario_id) === currentUserId || ownerId === currentUserId || canModerate) {
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'feed-action';
      remove.dataset.feedAction = 'delete-comment';
      remove.dataset.commentId = String(comment.id);
      remove.textContent = 'Remover comentário';
      item.append(remove);
    }
    return item;
  };

  const makeCommentReport = commentId => {
    const details = document.createElement('details');
    details.className = 'feed-report';
    const summary = document.createElement('summary');
    summary.textContent = 'Denunciar comentário';
    const form = document.createElement('form');
    form.action = apiUrl;
    form.method = 'post';
    form.dataset.reportForm = '';
    const fields = {
      csrf,
      action: 'report',
      content_type: 'comment',
      id: String(commentId)
    };
    Object.entries(fields).forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.append(input);
    });
    const reasonLabel = document.createElement('label');
    reasonLabel.textContent = 'Motivo';
    const reason = document.createElement('select');
    reason.name = 'reason';
    reason.required = true;
    [['spam', 'Spam'], ['assédio', 'Assédio'], ['conteúdo inadequado', 'Conteúdo inadequado'], ['outro', 'Outro']].forEach(([value, label]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      reason.append(option);
    });
    reasonLabel.append(reason);
    const detailsLabel = document.createElement('label');
    detailsLabel.textContent = 'Detalhes (opcional)';
    const detailsInput = document.createElement('textarea');
    detailsInput.name = 'details';
    detailsInput.maxLength = 1000;
    detailsLabel.append(detailsInput);
    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'btn btn-outline';
    button.textContent = 'Enviar denúncia';
    const status = document.createElement('span');
    status.className = 'feed-form-status';
    status.setAttribute('role', 'status');
    form.append(reasonLabel, detailsLabel, button, status);
    form.addEventListener('submit', async event => {
      event.preventDefault();
      button.disabled = true;
      setStatus(status, 'Enviando denúncia...');
      try {
        const result = await send(Object.fromEntries(new FormData(form).entries()));
        setStatus(status, result.created === false
          ? 'Você já enviou uma denúncia pendente para este conteúdo.'
          : 'Denúncia enviada para análise.');
        form.reset();
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível enviar a denúncia.', true);
        button.disabled = false;
      }
    });
    details.append(summary, form);
    return details;
  };

  const loadComments = async (card) => {
    const panel = card.querySelector('.feed-comments');
    const list = panel?.querySelector('.feed-comment-list');
    const status = panel?.querySelector('.feed-comment-status');
    if (!panel || !list) return;
    panel.hidden = !panel.hidden;
    const toggle = card.querySelector('[data-feed-action="comments"]');
    toggle?.setAttribute('aria-expanded', String(!panel.hidden));
    if (panel.hidden || panel.dataset.loaded === 'true') return;
    setStatus(status, 'Carregando comentários...');
    try {
      const id = card.dataset.publicationId;
      const response = await request(`${apiUrl}?action=comments&id=${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await readJsonResponse(response);
      if (!response.ok) throw new Error(data.error || 'Não foi possível carregar os comentários.');
      const canModerate = card.dataset.canModerate === '1';
      (data.comments || []).forEach(comment => {
        const item = makeComment(comment, Number(card.dataset.ownerId), canModerate);
        if (Number(card.dataset.roomId) > 0) item.append(makeCommentReport(Number(comment.id)));
        list.append(item);
      });
      panel.dataset.loaded = 'true';
      setStatus(status, data.comments?.length ? '' : 'Ainda não há comentários.');
    } catch (error) {
      setStatus(status, error.message || 'Falha ao carregar comentários.', true);
    }
  };

  document.querySelectorAll('[data-feed-kind]').forEach(radio => {
    radio.addEventListener('change', () => {
      const lesson = radio.value === 'free_lesson';
      const material = radio.value === 'material';
      const form = radio.closest('[data-publication-form]');
      const mediaInput = form?.querySelector('[data-media-upload]');
      const mediaHelp = form?.querySelector('[data-media-help]');
      if (mediaInput) {
        mediaInput.accept = lesson ? '.mp4,.webm,video/mp4,video/webm' : '.pdf,.epub,.docx,.pptx,.xlsx,.odt,.odp,.ods,.txt,.rtf,.csv,.jpg,.jpeg,.png,.gif,.webp,.mp4,.webm';
      }
      if (mediaHelp) {
        mediaHelp.textContent = lesson
          ? 'Envie um vídeo MP4/WEBM de até 20 MB ou informe um link externo.'
          : 'Anexe documentos, imagens ou vídeos MP4/WEBM de até 20 MB.';
      }
      document.querySelectorAll('[data-lesson-field]').forEach(field => { field.hidden = !lesson; });
      document.querySelectorAll('[data-material-field]').forEach(field => { field.hidden = !material; });
      const visibilityField = document.querySelector('[data-visibility-field]');
      if (visibilityField) visibilityField.hidden = lesson;
      const roomSelect = document.querySelector('[data-room-select]');
      if (roomSelect) {
        roomSelect.disabled = lesson;
        if (lesson) roomSelect.value = '';
      }
      if (lesson) {
        const visibilitySelect = document.querySelector('[name="visibility"]');
        if (visibilitySelect) visibilitySelect.value = 'publica';
      }
    });
  });
  document.querySelector('[data-feed-kind]:checked')?.dispatchEvent(new Event('change'));
  document.querySelectorAll('[data-publication-form]').forEach(form => {
    const roomSelect = form.querySelector('[data-room-select]');
    const visibilitySelect = form.querySelector('[name="visibility"]');
    const editor = form.querySelector('[data-rich-editor]');
    const richSource = form.querySelector('[data-rich-source]');
    let savedRange = null;
    const syncRichText = () => {
      if (editor && richSource) richSource.value = editor.innerHTML;
    };
    const rememberSelection = () => {
      const selection = window.getSelection();
      if (editor && selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
        savedRange = selection.getRangeAt(0).cloneRange();
      }
    };
    const restoreSelection = () => {
      editor?.focus();
      if (savedRange) {
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(savedRange);
      }
    };
    editor?.addEventListener('input', syncRichText);
    editor?.addEventListener('keyup', rememberSelection);
    editor?.addEventListener('mouseup', rememberSelection);
    form.querySelectorAll('[data-rich-command]').forEach(button => {
      button.addEventListener('mousedown', event => event.preventDefault());
      button.addEventListener('click', () => {
        restoreSelection();
        document.execCommand(button.dataset.richCommand, false, null);
        syncRichText();
        rememberSelection();
      });
    });
    form.querySelector('[data-rich-block]')?.addEventListener('change', event => {
      restoreSelection();
      document.execCommand('formatBlock', false, event.currentTarget.value);
      syncRichText();
      rememberSelection();
    });
    form.querySelector('[data-rich-link]')?.addEventListener('mousedown', event => event.preventDefault());
    form.querySelector('[data-rich-link]')?.addEventListener('click', () => {
      const url = window.prompt('Informe um link HTTPS:');
      if (!url || !/^https:\/\/[^\s]+$/i.test(url)) return;
      restoreSelection();
      document.execCommand('createLink', false, url);
      syncRichText();
      rememberSelection();
    });
    const syncRoomVisibility = () => {
      if (roomSelect?.value && visibilitySelect) visibilitySelect.value = 'sala';
      else if (visibilitySelect?.value === 'sala') visibilitySelect.value = 'publica';
    };
    roomSelect?.addEventListener('change', syncRoomVisibility);
  });

  document.querySelectorAll('[data-publication-form]').forEach(form => {
    form.querySelector('[data-save-draft]')?.addEventListener('click', async event => {
      const button = event.currentTarget;
      const status = form.querySelector('[data-form-status]');
      button.disabled = true;
      setStatus(status, 'Salvando rascunho...');
      try {
        syncRichText();
        const fields = new FormData(form);
        fields.set('action', 'save_draft');
        const data = await send(fields);
        window.location.assign(`feed.php?draft=${encodeURIComponent(data.id)}`);
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível salvar o rascunho.', true);
        button.disabled = false;
      }
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      const status = form.querySelector('[data-form-status]');
      const button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      setStatus(status, 'Salvando publicação...');
      try {
        syncRichText();
        const fields = new FormData(form);
        const data = await send(fields);
        setStatus(status, 'Publicação salva. Atualizando o Feed...');
        window.location.assign('feed.php');
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível publicar.', true);
      } finally {
        if (button) button.disabled = false;
      }
    });
  });

  document.querySelector('[data-feed-search]')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('button[type="submit"]');
    const status = document.querySelector('[data-feed-status]');
    if (button) { button.disabled = true; button.textContent = 'Buscando...'; }
    setStatus(status, 'Buscando publicações...');
  });

  document.querySelectorAll('[data-report-form]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]');
      const status = form.querySelector('[data-report-status]');
      if (button) button.disabled = true;
      setStatus(status, 'Enviando denúncia...');
      try {
        const fields = Object.fromEntries(new FormData(form).entries());
        const result = await send(fields);
        setStatus(status, result.created === false
          ? 'Você já enviou uma denúncia pendente para este conteúdo.'
          : 'Denúncia enviada para análise.');
        form.reset();
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível enviar a denúncia.', true);
        if (button) button.disabled = false;
      }
    });
  });

  document.addEventListener('click', async event => {
    const button = event.target.closest('[data-feed-action]');
    if (!button) return;
    const card = button.closest('[data-publication-id]');
    const status = card?.querySelector('[data-card-status]');
    if (!card) return;
    const action = button.dataset.feedAction;

    if (action === 'comments') {
      await loadComments(card);
      return;
    }

    if (action === 'delete') {
      if (!window.confirm('Arquivar esta publicação? Ela deixará de aparecer no Feed.')) return;
      button.disabled = true;
      try {
        await send({ action: 'delete', id: card.dataset.publicationId });
        card.remove();
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível arquivar.', true);
        button.disabled = false;
      }
      return;
    }

    if (action === 'delete-comment') {
      button.disabled = true;
      try {
        await send({ action: 'delete_comment', id: button.dataset.commentId });
        const item = button.closest('.feed-comment');
        item?.remove();
        const count = card.querySelector('[data-comment-count]');
        if (count) count.textContent = String(Math.max(0, Number(count.textContent) - 1));
      } catch (error) {
        setStatus(status, error.message || 'Não foi possível remover o comentário.', true);
        button.disabled = false;
      }
      return;
    }

    if (action !== 'like' && action !== 'save') return;
    button.disabled = true;
    try {
      const data = await send({ action, id: card.dataset.publicationId });
      button.setAttribute('aria-pressed', String(data.active));
      const count = button.querySelector('[data-count]');
      if (count) count.textContent = String(data.count);
      const label = action === 'like' ? (data.active ? 'Curtido' : 'Curtir') : (data.active ? 'Salvo' : 'Salvar');
      button.querySelector('[data-action-label]').textContent = label;
    } catch (error) {
      setStatus(status, error.message || 'Não foi possível atualizar a interação.', true);
    } finally {
      button.disabled = false;
    }
  });

  document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-comment-form]');
    if (!form) return;
    event.preventDefault();
    const card = form.closest('[data-publication-id]');
    const status = form.querySelector('[data-form-status]');
    const button = form.querySelector('button[type="submit"]');
    if (!card) return;
    if (button) button.disabled = true;
    setStatus(status, 'Enviando comentário...');
    try {
      const fields = Object.fromEntries(new FormData(form).entries());
      const result = await send(fields);
      const list = card.querySelector('.feed-comment-list');
      list?.append(makeComment(result.comment, Number(card.dataset.ownerId)));
      const count = card.querySelector('[data-comment-count]');
      if (count) count.textContent = String(Number(count.textContent) + 1);
      form.reset();
      setStatus(status, 'Comentário publicado.');
    } catch (error) {
      setStatus(status, error.message || 'Não foi possível comentar.', true);
    } finally {
      if (button) button.disabled = false;
    }
  });
})();