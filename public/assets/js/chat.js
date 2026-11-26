(() => {
  const root = document.getElementById('chat');
  if (!root) return;
  const projectId = root.dataset.projectId;
  const list = document.getElementById('chat-list');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const API = 'chat_api.php';
  let lastId = 0;
  let timer = null;

  const nearBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 80;

  function render(m) {
    if (list.querySelector(`[data-id="${m.id}"]`)) return;
    const row = document.createElement('div');
    row.className = 'chat-msg' + (m.mine ? ' mine' : '');
    row.dataset.id = m.id;

    const av = document.createElement('span');
    av.className = 'chat-av';
    av.innerHTML = m.avatar; // HTML généré et échappé par avatar() côté serveur

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';

    const meta = document.createElement('div');
    meta.className = 'chat-meta';
    meta.textContent = `${m.name} · ${m.time}`;

    const body = document.createElement('div');
    body.className = 'chat-body';
    body.textContent = m.body;

    bubble.append(meta, body);

    if (m.can_delete) {
      const del = document.createElement('button');
      del.type = 'button';
      del.className = 'chat-del';
      del.title = 'Supprimer';
      del.textContent = '✕';
      del.addEventListener('click', async () => {
        if (!confirm('Supprimer ce message ?')) return;
        const fd = new FormData(form);
        fd.set('action', 'delete');
        fd.set('message_id', m.id);
        fd.delete('body');
        try {
          const resp = await fetch(API, { method: 'POST', body: fd });
          if (resp.ok) {
            row.remove();
          } else {
            const res = await resp.json().catch(() => ({}));
            alert(res.error || 'Suppression impossible.');
          }
        } catch (err) {
          alert('Suppression impossible.');
        }
        poll();
      });
      bubble.append(del);
    }

    row.append(av, bubble);
    list.append(row);
    lastId = Math.max(lastId, m.id);
  }

  function addAll(messages) {
    const stick = lastId === 0 || nearBottom();
    messages.forEach(render);
    if (stick) list.scrollTop = list.scrollHeight;
  }

  function syncDeleted(ids, upTo) {
    const keep = new Set(ids);
    const min = ids.length ? Math.min(...ids) : 0;
    list.querySelectorAll('.chat-msg').forEach((el) => {
      const id = Number(el.dataset.id);
      if (id <= upTo && id >= min && !keep.has(id)) el.remove();
    });
  }

  async function poll() {
    clearTimeout(timer);
    if (!document.hidden) {
      const upTo = lastId;
      try {
        const r = await fetch(`${API}?project_id=${projectId}&after=${lastId}`, { cache: 'no-store' });
        const data = await r.json();
        if (data.messages) addAll(data.messages);
        if (Array.isArray(data.ids)) syncDeleted(data.ids, upTo);
      } catch (e) { /* on réessaiera */ }
    }
    timer = setTimeout(poll, 3000);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    const fd = new FormData(form);
    fd.set('action', 'send');
    input.disabled = true;
    try {
      const res = await fetch(API, { method: 'POST', body: fd }).then((r) => r.json());
      if (res.message) {
        addAll([res.message]);
        list.scrollTop = list.scrollHeight;
        input.value = '';
      } else {
        alert(res.error || 'Envoi impossible.');
      }
    } catch (err) {
      alert('Envoi impossible.');
    } finally {
      input.disabled = false;
      input.focus();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  poll();
})();