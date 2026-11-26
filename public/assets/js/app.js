(function () {
    // Confirmation avant les actions sensibles (data-confirm sur le <form>)
    document.addEventListener('submit', function (e) {
        var msg = e.target.dataset ? e.target.dataset.confirm : null;
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    // Tiroir des membres (mobile)
    var side = document.getElementById('side');
    var overlay = document.getElementById('overlay');
    function toggleSide(open) {
        if (!side) return;
        side.classList.toggle('open', open);
        overlay.classList.toggle('open', open);
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-toggle-side]')) toggleSide(!side.classList.contains('open'));
        else if (e.target === overlay) toggleSide(false);
    });

    var board = document.querySelector('.board');
    if (!board) return;

    var url = board.dataset.url;
    var csrf = board.dataset.csrf;
    var MOVE = 6;
    var LONG = 450;
    var MB = 1024 * 1024;
    var MAX_IMG_MB = 15;      // doit correspondre à TaskImage::MAX_BYTES
    var MAX_TOTAL_MB = 40;    // doit rester inférieur à post_max_size de php.ini
    var drag = null;
    var pending = null;
    var timer = null;
    var touchStart = null;

    function movableCard(target) {
        if (target.closest('button, a, input, select, textarea, summary, label, .more, .addcard, .subtasks, .task-images')) return null;
        var card = target.closest('.task');
        return card && card.dataset.canmove === '1' ? card : null;
    }
    function columnAt(x, y) {
        var el = document.elementFromPoint(x, y);
        return el ? el.closest('.column') : null;
    }
    function highlight(col) {
        board.querySelectorAll('.column').forEach(function (c) {
            c.classList.toggle('over', c === col);
        });
    }
    function updateCounts() {
        board.querySelectorAll('.column').forEach(function (c) {
            c.querySelector('.count').textContent = c.querySelectorAll('.task').length;
        });
    }
    function place(x, y) {
        drag.ghost.style.left = (x - drag.dx) + 'px';
        drag.ghost.style.top = (y - drag.dy) + 'px';
        highlight(columnAt(x, y));
        var r = board.getBoundingClientRect();
        if (x < r.left + 40) board.scrollLeft -= 12;
        else if (x > r.right - 40) board.scrollLeft += 12;
    }
    function start(card, x, y) {
        var rect = card.getBoundingClientRect();
        var ghost = card.cloneNode(true);
        ghost.classList.add('ghost');
        ghost.style.width = rect.width + 'px';
        document.body.appendChild(ghost);
        document.body.classList.add('is-dragging');
        drag = { card: card, ghost: ghost, from: card.closest('.column'),
                 dx: x - rect.left, dy: y - rect.top };
        card.classList.add('dragging');
        place(x, y);
    }
    function finish(x, y, cancel) {
        if (!drag) return;
        var d = drag;
        drag = null;
        d.ghost.remove();
        d.card.classList.remove('dragging');
        document.body.classList.remove('is-dragging');
        highlight(null);

        var col = cancel ? null : columnAt(x, y);
        if (!col || col === d.from) return;

        col.querySelector('.cards').appendChild(d.card);
        updateCounts();

        var data = new FormData();
        data.append('action', 'change_status');
        data.append('task_id', d.card.dataset.id);
        data.append('status', col.dataset.status);
        data.append('csrf', csrf);
        data.append('ajax', '1');
        fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (j) { if (!j.ok) throw 0; })
            .catch(function () { window.location.reload(); });
    }

    /* ----- Sous-tâches ----- */
    function updateSub(block) {
        var total = block.querySelectorAll('.subtask-check').length;
        var done = block.querySelectorAll('.subtask-check:checked').length;
        block.querySelector('.sub-count').textContent = done + '/' + total;
        var bar = block.querySelector('.sub-progress');
        bar.max = Math.max(1, total);
        bar.value = done;
    }
    board.addEventListener('change', function (e) {
        var box = e.target.closest('.subtask-check');
        if (!box) return;
        var wanted = box.checked;
        var data = new FormData();
        data.append('action', 'toggle_subtask');
        data.append('subtask_id', box.dataset.id);
        data.append('done', wanted ? '1' : '0');
        data.append('csrf', csrf);
        data.append('ajax', '1');
        fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (text) {
                var j;
                try { j = JSON.parse(text); }
                catch (err) { throw new Error('Réponse non JSON : ' + text.slice(0, 300)); }
                if (!j.ok) throw new Error('Refusé par le serveur');
                box.closest('li').classList.toggle('done', wanted);
                updateSub(box.closest('.subtasks'));
            })
            .catch(function (err) {
                console.error('Sous-tâche : échec', err);
                box.checked = !wanted;
            });
    });

    /* ----- Images : envoi automatique après le choix des fichiers ----- */
    board.addEventListener('change', function (e) {
        var input = e.target.closest('.img-input');
        if (!input || !input.files.length) return;
        var total = 0;
        for (var i = 0; i < input.files.length; i++) {
            if (input.files[i].size > MAX_IMG_MB * MB) {
                window.alert('« ' + input.files[i].name + ' » dépasse ' + MAX_IMG_MB + ' Mo.');
                input.value = '';
                return;
            }
            total += input.files[i].size;
        }
        if (total > MAX_TOTAL_MB * MB) {
            window.alert('Envoi trop volumineux (' + MAX_TOTAL_MB + ' Mo maximum au total).');
            input.value = '';
            return;
        }
        input.form.submit();
    });

    /* ----- Images : affichage en grand ----- */
    board.addEventListener('click', function (e) {
        var link = e.target.closest('.img-thumb');
        if (!link || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        var box = document.createElement('div');
        box.className = 'lightbox';
        var img = document.createElement('img');
        img.src = link.href;
        img.alt = link.title || '';
        box.appendChild(img);
        function close() {
            box.remove();
            document.removeEventListener('keydown', onKey);
        }
        function onKey(ev) { if (ev.key === 'Escape') close(); }
        box.addEventListener('click', close);
        document.addEventListener('keydown', onKey);
        document.body.appendChild(box);
    });

    /* ----- Souris ----- */
    board.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return;
        var card = movableCard(e.target);
        if (!card) return;
        e.preventDefault();
        pending = { card: card, x: e.clientX, y: e.clientY };
    });
    document.addEventListener('mousemove', function (e) {
        if (drag) { place(e.clientX, e.clientY); return; }
        if (pending && (Math.abs(e.clientX - pending.x) > MOVE || Math.abs(e.clientY - pending.y) > MOVE)) {
            start(pending.card, e.clientX, e.clientY);
            pending = null;
        }
    });
    document.addEventListener('mouseup', function (e) {
        pending = null;
        if (drag) finish(e.clientX, e.clientY, false);
    });

    /* ----- Tactile : appui long ----- */
    function clearTimer() {
        if (timer) { clearTimeout(timer); timer = null; }
    }
    board.addEventListener('touchstart', function (e) {
        var t = e.touches[0];
        var card = movableCard(e.target);
        if (!card || e.touches.length > 1) return;
        touchStart = { x: t.clientX, y: t.clientY };
        clearTimer();
        timer = setTimeout(function () {
            timer = null;
            start(card, touchStart.x, touchStart.y);
            if (navigator.vibrate) navigator.vibrate(15);
        }, LONG);
    }, { passive: true });

    board.addEventListener('touchmove', function (e) {
        var t = e.touches[0];
        if (drag) {
            e.preventDefault();
            place(t.clientX, t.clientY);
            return;
        }
        if (timer && touchStart &&
            (Math.abs(t.clientX - touchStart.x) > MOVE || Math.abs(t.clientY - touchStart.y) > MOVE)) {
            clearTimer();
        }
    }, { passive: false });

    board.addEventListener('touchend', function (e) {
        clearTimer();
        if (drag) {
            var t = e.changedTouches[0];
            finish(t.clientX, t.clientY, false);
        }
    });
    board.addEventListener('touchcancel', function () {
        clearTimer();
        if (drag) finish(0, 0, true);
    });

    board.addEventListener('contextmenu', function (e) {
        if (timer || drag) e.preventDefault();
    });
})();