(function () {
    'use strict';

    function isInteractiveTarget(target) {
        return !!(target && target.closest('a,button,input,select,textarea,[data-detail-ignore],.edit-pencil-icon'));
    }

    function detailUrl(card) {
        return 'favorite-detail.html?type=' + encodeURIComponent(card.dataset.detailType) +
            '&id=' + encodeURIComponent(card.dataset.detailId);
    }

    function openCard(card) {
        var type = card.dataset.detailType;
        var id = card.dataset.detailId;
        if (!type || !id) return;
        if (type === 'place' && card._detailItem) {
            try { sessionStorage.setItem('icook-place-detail-' + id, JSON.stringify(card._detailItem)); } catch (error) {}
        }
        window.location.assign(detailUrl(card));
    }

    function prepare(root) {
        if (root.matches && root.matches('.content-detail-card[data-detail-type][data-detail-id]')) {
            root.setAttribute('role', 'link');
            root.setAttribute('tabindex', '0');
            root.style.cursor = 'pointer';
        }
        if (root.querySelectorAll) {
            root.querySelectorAll('.content-detail-card[data-detail-type][data-detail-id]').forEach(function (card) {
                card.setAttribute('role', 'link');
                card.setAttribute('tabindex', '0');
                card.style.cursor = 'pointer';
            });
        }
    }

    document.addEventListener('click', function (event) {
        var card = event.target.closest('.content-detail-card[data-detail-type][data-detail-id]');
        if (!card || isInteractiveTarget(event.target)) return;
        openCard(card);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        var card = event.target.closest('.content-detail-card[data-detail-type][data-detail-id]');
        if (!card || event.target !== card || isInteractiveTarget(event.target)) return;
        event.preventDefault();
        openCard(card);
    });

    prepare(document);
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) prepare(node);
            });
        });
    }).observe(document.documentElement, {childList: true, subtree: true});
})();
