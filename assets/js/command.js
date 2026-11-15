/* =========================================================================
   Palette de commandes — Ctrl/Cmd + K
   Recherche instantanée (produits, clients, bénéficiaires, fournisseurs,
   chèques, ventes) sans quitter la page. Aucun framework, ~6 Ko.
   ========================================================================= */
(function () {
    'use strict';

    var root = document.getElementById('gosCmd');
    if (!root) return;

    var input = document.getElementById('gosCmdInput');
    var body = document.getElementById('gosCmdBody');
    var trigger = document.getElementById('gosSearchBtn');
    var base = (document.body.getAttribute('data-base') || '').replace(/\/$/, '');
    var endpoint = base + '/api/global_search.php';

    var labels = {
        hint: root.getAttribute('data-hint') || 'Tapez au moins 2 caractères.',
        empty: root.getAttribute('data-empty') || 'Aucun résultat.',
        loading: root.getAttribute('data-loading') || 'Recherche…'
    };

    var icons = {
        box: '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        wallet: '<path d="M20 7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h15V7"/><path d="M16 13h3"/>',
        receipt: '<path d="M4 2h16v20l-4-2-4 2-4-2-4 2z"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>'
    };

    var items = [];
    var selected = -1;
    var timer = null;
    var controller = null;
    var cache = new Map();

    function icon(name) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + (icons[name] || icons.search) + '</svg>';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function highlight(text, query) {
        var safe = escapeHtml(text);
        if (!query) return safe;
        var escapedQuery = escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return safe.replace(new RegExp('(' + escapedQuery + ')', 'gi'), '<mark>$1</mark>');
    }

    function showSkeleton() {
        body.innerHTML = '<div class="gos-cmd-skel"></div><div class="gos-cmd-skel"></div><div class="gos-cmd-skel"></div>';
    }

    function showHint(message) {
        body.innerHTML = '<div class="gos-cmd-empty">' + escapeHtml(message) + '</div>';
        items = [];
        selected = -1;
    }

    function render(groups, query) {
        items = [];
        if (!groups || groups.length === 0) {
            showHint(labels.empty.replace(':q', query));
            return;
        }

        var html = '';
        groups.forEach(function (group) {
            html += '<div class="gos-cmd-group">' + escapeHtml(group.label) + '</div>';
            (group.items || []).forEach(function (item) {
                var index = items.length;
                items.push(item);
                html += '<a class="gos-cmd-item" role="option" data-index="' + index + '" href="' + escapeHtml(item.url) + '">'
                    + '<span class="gos-cmd-ico">' + icon(item.icon) + '</span>'
                    + '<span class="gos-cmd-text"><strong>' + highlight(item.title, query) + '</strong>'
                    + (item.subtitle ? '<small>' + escapeHtml(item.subtitle) + '</small>' : '')
                    + '</span>'
                    + (item.meta ? '<span class="gos-cmd-meta">' + escapeHtml(item.meta) + '</span>' : '')
                    + '</a>';
            });
        });

        body.innerHTML = html;
        selected = -1;
    }

    function paintSelection() {
        var nodes = body.querySelectorAll('.gos-cmd-item');
        nodes.forEach(function (node, i) {
            node.classList.toggle('is-selected', i === selected);
        });
        if (selected >= 0 && nodes[selected]) {
            nodes[selected].scrollIntoView({ block: 'nearest' });
        }
    }

    function search(query) {
        if (cache.has(query)) {
            render(cache.get(query), query);
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();
        showSkeleton();

        fetch(endpoint + '?q=' + encodeURIComponent(query), {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (response) { return response.ok ? response.json() : { ok: false }; })
            .then(function (data) {
                if (!data || !data.ok) {
                    showHint(labels.empty.replace(':q', query));
                    return;
                }
                if (cache.size > 40) cache.clear();
                cache.set(query, data.groups || []);
                render(data.groups || [], query);
            })
            .catch(function (error) {
                if (error && error.name === 'AbortError') return;
                showHint(labels.empty.replace(':q', query));
            });
    }

    function open() {
        root.hidden = false;
        document.body.classList.add('gos-no-scroll');
        input.value = '';
        showHint(labels.hint);
        window.setTimeout(function () { input.focus(); }, 20);
    }

    function close() {
        root.hidden = true;
        document.body.classList.remove('gos-no-scroll');
        if (controller) controller.abort();
    }

    function openWith(query) {
        open();
        input.value = query;
        search(query);
    }

    input.addEventListener('input', function () {
        var query = input.value.trim();
        window.clearTimeout(timer);
        if (query.length < 2) {
            showHint(labels.hint);
            return;
        }
        timer = window.setTimeout(function () { search(query); }, 140);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (items.length === 0) return;
            selected = event.key === 'ArrowDown'
                ? (selected + 1) % items.length
                : (selected - 1 + items.length) % items.length;
            paintSelection();
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            var target = selected >= 0 ? items[selected] : items[0];
            if (target && target.url) window.location.href = target.url;
        }
    });

    body.addEventListener('mousemove', function (event) {
        var node = event.target.closest('.gos-cmd-item');
        if (!node) return;
        var index = Number(node.getAttribute('data-index'));
        if (index !== selected) {
            selected = index;
            paintSelection();
        }
    });

    root.addEventListener('click', function (event) {
        if (event.target.closest('[data-cmd-close]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            root.hidden ? open() : close();
            return;
        }
        if (event.key === 'Escape' && !root.hidden) {
            close();
        }
    });

    if (trigger) {
        trigger.addEventListener('click', function () { open(); });
    }
})();
