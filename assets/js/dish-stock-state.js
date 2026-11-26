(function () {
    'use strict';

    function updateCard(card) {
        if (!card || card.dataset.stockCount !== '0' || card.dataset.stockStateApplied === '1') return;
        card.dataset.stockStateApplied = '1';
        card.classList.add('dish-sold-out');
        card.setAttribute('aria-label', 'Блюдо закончилось на сегодня');
        card.querySelectorAll('.btn, .qty-btn, button[onclick*="addToCart"]').forEach(function (button) {
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
        });
        var cartButton = card.querySelector('[id^="cartBtn-"]');
        if (cartButton) {
            cartButton.textContent = 'Закончилось на сегодня';
            cartButton.removeAttribute('onclick');
        }
        var qtyControl = card.querySelector('.qty-control');
        if (qtyControl) {
            qtyControl.replaceChildren();
            qtyControl.textContent = 'Закончилось на сегодня';
            qtyControl.classList.add('stock-empty-label');
        }
    }

    function scan(root) {
        if (root.nodeType === 1 && root.matches('[data-stock-count]')) updateCard(root);
        if (root.querySelectorAll) root.querySelectorAll('[data-stock-count]').forEach(updateCard);
    }

    scan(document);
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(scan);
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
