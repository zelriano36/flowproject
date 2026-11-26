(() => {
  const board = document.querySelector('.board');
  const side = document.getElementById('side');
  const head = document.querySelector('.project-head > div');
  if (!board || !side || !head) return;

  const url = board.dataset.url;
  const regions = [
    { el: head, sel: '.project-head > div', applied: null },
    { el: board, sel: '.board', applied: null },
    { el: side, sel: '#side', applied: null },
  ];
  const TEXT_TYPES = ['checkbox', 'radio', 'button', 'submit', 'file'];
  let timer = null;

  function busy() {
    const a = document.activeElement;
    if (a && regions.some((r) => r.el.contains(a))) {
      if (a.tagName === 'TEXTAREA' || a.tagName === 'SELECT') return true;
      if (a.tagName === 'INPUT' && !TEXT_TYPES.includes(a.type)) return true;
    }
    if (document.querySelector('.dragging, .is-dragging, .drag-ghost, .drag-clone')) return true;
    for (const r of regions) {
      for (const f of r.el.querySelectorAll('input[type=text],input[type=email],input[type=url],textarea')) {
        if (f.value !== f.defaultValue) return true;
      }
    }
    return false;
  }

  function detailsState(root) {
    const counters = {};
    const map = new Map();
    root.querySelectorAll('details').forEach((d) => {
      const art = d.closest('article.task');
      const base = (art ? art.id : '') + '|' + d.className;
      counters[base] = (counters[base] || 0) + 1;
      map.set(base + '#' + counters[base], d.open);
    });
    return map;
  }

  function restoreDetails(root, map) {
    const counters = {};
    root.querySelectorAll('details').forEach((d) => {
      const art = d.closest('article.task');
      const base = (art ? art.id : '') + '|' + d.className;
      counters[base] = (counters[base] || 0) + 1;
      const key = base + '#' + counters[base];
      if (map.has(key)) d.open = map.get(key);
    });
  }

  async function poll() {
    clearTimeout(timer);
    if (!document.hidden) {
      try {
        const resp = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
        if (resp.status === 404 || resp.status === 403 || (resp.redirected && !resp.url.includes('project.php'))) {
          location.href = 'index.php';
          return;
        }
        const doc = new DOMParser().parseFromString(await resp.text(), 'text/html');
        const fresh = regions.map((r) => {
          const n = doc.querySelector(r.sel);
          return n ? n.innerHTML : null;
        });
        if (regions[0].applied === null) {
          regions.forEach((r, i) => { r.applied = fresh[i]; });
        } else if (!busy()) {
          regions.forEach((r, i) => {
            if (fresh[i] !== null && fresh[i] !== r.applied) {
              const state = detailsState(r.el);
              r.el.innerHTML = fresh[i];
              restoreDetails(r.el, state);
              r.applied = fresh[i];
            }
          });
        }
      } catch (e) { /* on réessaiera */ }
    }
    timer = setTimeout(poll, 4000);
  }

  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  poll();
})();