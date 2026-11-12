/* Coquille unifiée : menu mobile + thème clair/sombre. */
(function () {
    'use strict';

    var body = document.body;
    var sidebar = document.getElementById('gosSidebar');
    var overlay = document.getElementById('gosOverlay');
    var menuBtn = document.getElementById('gosMenuBtn');

    function closeMenu() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
    }

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
            if (overlay) overlay.classList.toggle('is-open', sidebar.classList.contains('is-open'));
        });
    }
    if (overlay) overlay.addEventListener('click', closeMenu);

    var themeBtn = document.getElementById('gosThemeBtn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            if (body) body.setAttribute('data-theme', next);
            var base = (body && body.dataset.base) ? body.dataset.base : '';
            var path = base === '' ? '/' : base + '/';
            document.cookie = 'gos_theme=' + next + ';path=' + path + ';maxage=31536000;samesite=lax';
        });
    }

    // Fermer le menu après un clic sur un lien (mobile).
    if (sidebar) {
        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a.gos-nav-link, a.gos-switch')) closeMenu();
        });
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) closeMenu();
    });

    /* ---------------------------------------------------------------------
       Visionneuse d'images (bons société, chèques et effets)
       --------------------------------------------------------------------- */

    function closeImageModal(modal) {
        modal.hidden = true;
        document.body.classList.remove('gos-no-scroll');
        var image = modal.querySelector('img');
        if (image) image.removeAttribute('src');
    }

    function ensureImageModal() {
        var modal = document.getElementById('gosImageModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'gosImageModal';
        modal.className = 'gos-image-modal';
        modal.hidden = true;
        modal.innerHTML =
            '<div class="gos-image-backdrop" data-gos-image-close></div>' +
            '<div class="gos-image-box" role="dialog" aria-modal="true">' +
            '  <div class="gos-image-head">' +
            '    <span class="gos-image-title"></span>' +
            '    <button type="button" class="gos-image-close" data-gos-image-close aria-label="Fermer">&times;</button>' +
            '  </div>' +
            '  <div class="gos-image-body"><img alt="Aperçu"></div>' +
            '</div>';
        document.body.appendChild(modal);

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-gos-image-close]')) closeImageModal(modal);
        });

        return modal;
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-gos-image]');
        if (!link) return;
        event.preventDefault();

        var modal = ensureImageModal();
        modal.querySelector('.gos-image-title').textContent = link.getAttribute('data-title') || '';
        modal.querySelector('img').src = link.getAttribute('href');
        modal.hidden = false;
        document.body.classList.add('gos-no-scroll');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var modal = document.getElementById('gosImageModal');
        if (modal && !modal.hidden) closeImageModal(modal);
    });
})();

