(function () {
    'use strict';
    function closeMenu() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.querySelector('[data-menu-overlay]');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.classList.remove('menu-open');
    }
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-menu-toggle]');
        var overlayClick = event.target.closest('[data-menu-overlay]');
        if (toggle) {
            event.preventDefault();
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.querySelector('[data-menu-overlay]');
            if (!sidebar) return;
            var opening = !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', opening);
            if (overlay) overlay.classList.toggle('active', opening);
            document.body.classList.toggle('menu-open', opening);
            return;
        }
        if (overlayClick) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });
})();
