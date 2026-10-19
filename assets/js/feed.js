(() => {
  const apiUrl = 'api/feed.php';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const currentUserId = Number(document.body.dataset.userId || 0);

  const setStatus = (node, message, isError = false) => {
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('feed-state-error', isError);
  };

  const send = async (fields) => {
    const formData = new FormData();
    Object.entries({ csrf, ...fields }).forEach(([key, value]) => formData.append(key, value));
    const response = await fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Não foi possível concluir a ação.');
    return data;
  };

  const makeComment = (comment, ownerId) => {
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

    if (Number(comment.usuario_id) === currentUserId || ownerId === currentUserId) {
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
      const response = await fetch(`${apiUrl}?action=comments&id=${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Não foi possível carregar os comentários.');
      (data.comments || []).forEach(comment => list.append(makeComment(comment, Number(card.dataset.ownerId))));
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
      document.querySelectorAll('[data-lesson-field]').forEach(field => { field.hidden = !lesson; });
      document.querySelectorAll('[data-material-field]').forEach(field => { field.hidden = !material; });
      const visibilityField = document.querySelector('[data-visibility-field]');
      if (visibilityField) visibilityField.hidden = lesson;
    });
  });
  document.querySelector('[data-feed-kind]:checked')?.dispatchEvent(new Event('change'));

  document.querySelectorAll('[data-publication-form]').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const status = form.querySelector('[data-form-status]');
      const button = form.querySelector('button[type="submit"]');
      if (button) button.disabled = true;
      setStatus(status, 'Salvando publicação...');
      try {
        const fields = Object.fromEntries(new FormData(form).entries());
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