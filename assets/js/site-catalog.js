/* Единые разделы и поведение каталога на публичных страницах. */
(function () {
    var drawer = document.getElementById('catalogDrawer');
    var overlay = document.getElementById('catalogOverlay');
    if (!drawer || !overlay) return;
    var icons = {
        home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        restaurants: '<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/>',
        menu: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        recipes: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8M8 11h6"/>',
        chefs: '<circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>',
        reviews: '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        about: '<circle cx="12" cy="12" r="10"/><path d="M12 11v5"/><circle cx="12" cy="7" r="1" fill="currentColor" stroke="none"/>',
        login: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
        cart: '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        calendar: '<path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>',
        cheap: '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01M10 13l2 2 4-4"/>'
    };
    function svg(content) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' + content + '</svg>'; }
    function link(label, href, icon, secondary) {
        return '<a class="catalog-link' + (secondary ? ' secondary' : '') + '" href="' + href + '">' + svg(icons[icon]) + label + '</a>';
    }
    drawer.innerHTML = '<h3 id="catalogTitle">' + svg('<path d="M4 6h16M4 12h16M4 18h16"/>') + 'Каталог сайта</h3>' +
        link('Главная', 'index.html', 'home') +
        link('Рестораны/бары/кафе', 'restaurants.html', 'restaurants') +
        link('Меню', 'menu.html', 'menu') + link('Рецепты', 'recipes.html', 'recipes') +
        link('Повара', 'chefs.html', 'chefs') + link('Отзывы', 'reviews.html', 'reviews') + link('О нас', 'about.html', 'about') +
        '<div class="catalog-divider thick"></div>' +
        '<a class="catalog-link" href="#" data-catalog-auth>' + svg(icons.login) + 'Войти / Регистрация</a>' +
        link('Корзина', 'cart.html', 'cart') + '<div class="catalog-divider thick"></div>' +
        '<h3 class="catalog-subheader">' + svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>') + 'Совместные проекты</h3>' +
        link('Еду ем', '#', 'calendar', true) + link('Дёшево! Честно!', 'cheap-honest.html', 'cheap', true);
    drawer.setAttribute('role', 'navigation');
    drawer.setAttribute('aria-labelledby', 'catalogTitle');
    var opener = null;
    function setOpen(open) {
        drawer.classList.toggle('active', open);
        overlay.classList.toggle('active', open);
        document.documentElement.classList.toggle('catalog-open', open);
        drawer.setAttribute('aria-hidden', String(!open));
        if (open) drawer.removeAttribute('inert');
        else drawer.setAttribute('inert', '');
        document.querySelectorAll('.catalog-btn').forEach(function (button) {
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-controls', 'catalogDrawer');
        });
        if (open) { opener = document.activeElement; drawer.querySelector('a').focus({preventScroll:true}); }
        else if (opener && drawer.contains(document.activeElement)) opener.focus({preventScroll:true});
    }
    window.toggleCatalog = function () { setOpen(!drawer.classList.contains('active')); };
    window.updateCatalogVisibility = function () {
        var user = null;
        try { user = JSON.parse(localStorage.getItem('user') || 'null'); } catch (e) {}
        drawer.querySelector('[data-catalog-auth]').style.display = user && user.name ? 'none' : '';
    };
    drawer.addEventListener('click', function (event) {
        var anchor = event.target.closest('a.catalog-link');
        if (!anchor) return;
        setOpen(false);
        if (anchor.getAttribute('href') === '#') event.preventDefault();
        if (anchor.hasAttribute('data-catalog-auth')) window.openAuth();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && drawer.classList.contains('active')) setOpen(false);
        if (event.key !== 'Tab' || !drawer.classList.contains('active')) return;
        var elements = Array.from(drawer.querySelectorAll('a,button,[tabindex="0"]')).filter(function (el) { return el.offsetParent !== null; });
        var first = elements[0], last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    window.addEventListener('storage', function (event) { if (event.key === 'user') window.updateCatalogVisibility(); });
    setOpen(false);
    window.updateCatalogVisibility();
    document.addEventListener('DOMContentLoaded', window.updateCatalogVisibility);
})();
