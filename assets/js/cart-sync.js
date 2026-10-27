/* cart-sync.js — единая синхронизация счётчиков корзины на карточках блюд.
   Правило: если в корзине >= 1 шт. блюда, у его карточки вместо кнопки
   «В корзину» всегда показывается счётчик «− N +» — при загрузке страницы,
   при переходе с другой страницы и при асинхронном рендере карточек.
   Подключается на страницах с карточками: index, menu, favorites, about. */
(function () {
    var dataCache = {}; // данные последних кнопок «В корзину» (для восстановления кнопки)

    function readCart() {
        try {
            var c = JSON.parse(localStorage.getItem('cart') || '[]');
            return Array.isArray(c) ? c : [];
        } catch (e) { return []; }
    }

    function findItem(id, list) {
        id = String(id);
        for (var i = 0; i < list.length; i++) {
            if (String(list[i].id) === id) return list[i];
        }
        return null;
    }

    // Имена функций страницы: menu использует свои menuChgQty/addToCartMenuBtn
    function qtyFn() {
        if (typeof window.chgQtyMain === 'function') return 'chgQtyMain';
        if (typeof window.menuChgQty === 'function') return 'menuChgQty';
        return null;
    }
    function addFn() {
        if (typeof window.addToCartBtn === 'function') return 'addToCartBtn';
        if (typeof window.addToCartMenuBtn === 'function') return 'addToCartMenuBtn';
        return null;
    }

    function makeQty(item, fn) {
        var div = document.createElement('div');
        div.className = 'qty-control';
        div.id = 'qty-' + item.id;
        div.innerHTML =
            '<button class="qty-btn" onclick="' + fn + '(' + item.id + ',-1)">&minus;</button>' +
            '<span class="qty-val">' + item.qty + '</span>' +
            '<button class="qty-btn" onclick="' + fn + '(' + item.id + ',1)">+</button>';
        return div;
    }

    function makeBtn(item, fn) {
        var d = dataCache[String(item.id)] || {};
        var b = document.createElement('button');
        b.className = 'btn';
        b.id = 'cartBtn-' + item.id;
        b.dataset.id = item.id;
        b.dataset.name = item.name || d.name || '';
        b.dataset.chef = item.chef || d.chef || '';
        b.dataset.price = (item.price != null && item.price !== '') ? item.price : (d.price != null ? d.price : '');
        b.dataset.img = item.img || d.img || '';
        b.textContent = 'В корзину';
        b.setAttribute('onclick', fn + '(this)');
        return b;
    }

    function rememberData(b) {
        if (!b.dataset || !b.dataset.id) return;
        dataCache[String(b.dataset.id)] = {
            name: b.dataset.name || '',
            chef: b.dataset.chef || '',
            price: b.dataset.price,
            img: b.dataset.img || ''
        };
    }

    function sync() {
        var qFn = qtyFn();
        if (!qFn) return; // на странице нет счётчиков корзины — не трогаем
        var list = readCart();

        // 1) кнопка «В корзину» → счётчик, если блюдо уже в корзине
        var btns = document.querySelectorAll('button[id^="cartBtn-"]');
        for (var i = 0; i < btns.length; i++) {
            var b = btns[i];
            rememberData(b);
            var id = b.dataset.id;
            if (!id || document.getElementById('qty-' + id)) continue;
            var item = findItem(id, list);
            if (item && item.qty > 0 && b.parentNode) {
                b.parentNode.replaceChild(makeQty(item, qFn), b);
            }
        }

        // 2) счётчик → кнопка, если блюда больше нет в корзине; иначе освежить число
        var ctrls = document.querySelectorAll('div.qty-control[id^="qty-"]');
        for (var j = 0; j < ctrls.length; j++) {
            var div = ctrls[j];
            var cid = div.id.replace('qty-', '');
            var it = findItem(cid, list);
            if (!it || !(it.qty > 0)) {
                var aFn = addFn();
                if (aFn && div.parentNode) div.parentNode.replaceChild(makeBtn({ id: cid }, aFn), div);
            } else {
                var val = div.querySelector('.qty-val');
                if (val && val.textContent !== String(it.qty)) val.textContent = it.qty;
            }
        }
    }

    var timer;
    function schedule() { clearTimeout(timer); timer = setTimeout(sync, 30); }

    function start() {
        sync();
        // карточки грузятся асинхронно — подхватываем их появления в DOM
        if (window.MutationObserver) {
            new MutationObserver(schedule).observe(document.documentElement, { childList: true, subtree: true });
        }
        // изменения корзины из другой вкладки
        window.addEventListener('storage', function (e) {
            if (e.key !== 'cart') return;
            try { cart = readCart(); } catch (err) {}
            sync();
            if (typeof window.updateCartBadge === 'function') window.updateCartBadge();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
