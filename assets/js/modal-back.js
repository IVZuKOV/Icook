(function () {
    'use strict';

    var modalSelector = '[class*="modal"].active, [id*="Modal"].active, [id*="modal"].active, [role="dialog"][open]';
    var managed = [];
    var sequence = 0;
    var currentToken = history.state && history.state.__siteModalBackToken || null;
    var manualBack = null;
    var closingFromPop = false;
    var gesture = null;

    function isVisibleModal(element) {
        if (!element || !element.isConnected) return false;
        if (location.pathname.endsWith('/restaurants.html') &&
            (element.id === 'authModal' || element.id === 'venuePhotoModal')) return false;
        if (location.pathname.endsWith('/profile.html') &&
            ['addDishModal', 'editDishModal', 'addRecipeModal', 'editRecipeModal'].indexOf(element.id) !== -1) return false;
        var style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden' &&
            (element.classList.contains('active') || element.hasAttribute('open'));
    }

    function visibleModals() {
        return Array.prototype.slice.call(document.querySelectorAll(modalSelector))
            .filter(isVisibleModal)
            .sort(function (a, b) {
                var za = parseInt(getComputedStyle(a).zIndex, 10) || 0;
                var zb = parseInt(getComputedStyle(b).zIndex, 10) || 0;
                return za - zb;
            });
    }

    function closeDirectly(element) {
        if (!element) return;
        element.classList.remove('active', 'is-open', 'open');
        element.removeAttribute('open');
        element.setAttribute('aria-hidden', 'true');
    }

    function sync() {
        var open = visibleModals();
        open.forEach(function (element) {
            if (managed.some(function (entry) { return entry.element === element; })) return;
            var token = 'modal-back-' + (++sequence);
            var previousToken = currentToken;
            try {
                var state = history.state && typeof history.state === 'object' ? history.state : {};
                history.pushState(Object.assign({}, state, { __siteModalBackToken: token }), '', location.href);
                currentToken = token;
                managed.push({ element: element, token: token, previousToken: previousToken });
            } catch (error) {
                // The touch gesture still closes the modal when the browser disallows history state.
            }
        });

        managed.slice().forEach(function (entry) {
            if (isVisibleModal(entry.element)) return;
            managed.splice(managed.indexOf(entry), 1);
            if (!closingFromPop && history.state &&
                history.state.__siteModalBackToken === entry.token) {
                manualBack = { targetToken: entry.previousToken };
                try { history.back(); } catch (error) { manualBack = null; }
            }
        });
    }

    function closeTopFromBack() {
        var top = visibleModals().pop();
        if (!top) return;
        closingFromPop = true;
        closeDirectly(top);
        var index = managed.map(function (entry) { return entry.element; }).lastIndexOf(top);
        if (index !== -1) managed.splice(index, 1);
        closingFromPop = false;
    }

    function closeTopFromGesture() {
        var top = visibleModals().pop();
        if (!top) return;
        closeDirectly(top);
        var entry = managed.slice().reverse().find(function (item) { return item.element === top; });
        if (!entry) return;
        managed.splice(managed.indexOf(entry), 1);
        if (history.state && history.state.__siteModalBackToken === entry.token) {
            manualBack = { targetToken: entry.previousToken };
            try { history.back(); } catch (error) { manualBack = null; }
        }
    }

    window.addEventListener('popstate', function (event) {
        var nextToken = event.state && event.state.__siteModalBackToken || null;
        if (manualBack && nextToken === manualBack.targetToken) {
            manualBack = null;
            currentToken = nextToken;
            return;
        }
        var poppedToken = currentToken;
        currentToken = nextToken;
        var entry = managed.slice().reverse().find(function (item) {
            return item.token === poppedToken && isVisibleModal(item.element);
        });
        if (entry) {
            closingFromPop = true;
            closeDirectly(entry.element);
            managed.splice(managed.indexOf(entry), 1);
            closingFromPop = false;
            return;
        }
        closeTopFromBack();
    });

    var observer = new MutationObserver(sync);
    observer.observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'open', 'style'] });
    sync();

    function ignoredGestureTarget(target) {
        return !!(target && target.closest &&
            target.closest('input, textarea, select, [contenteditable="true"], .avatar-crop-frame, [data-modal-swipe-ignore], [data-no-modal-swipe-close], .carousel-track'));
    }

    document.addEventListener('touchstart', function (event) {
        var modal = visibleModals().pop();
        if (!modal || !event.touches || event.touches.length !== 1 ||
            !modal.contains(event.target) || ignoredGestureTarget(event.target)) {
            gesture = null;
            return;
        }
        var touch = event.touches[0];
        gesture = { x: touch.clientX, y: touch.clientY, modal: modal };
    }, { passive: true });

    document.addEventListener('touchmove', function (event) {
        if (!gesture || !event.touches || event.touches.length !== 1) return;
        var touch = event.touches[0];
        var dx = touch.clientX - gesture.x;
        var dy = touch.clientY - gesture.y;
        if (Math.abs(dx) > 10 && Math.abs(dx) > Math.abs(dy) * 1.2) {
            gesture.horizontal = true;
            if (event.cancelable) event.preventDefault();
        }
    }, { passive: false });

    document.addEventListener('touchend', function (event) {
        if (!gesture) return;
        var touch = event.changedTouches && event.changedTouches[0];
        var dx = touch ? touch.clientX - gesture.x : 0;
        var dy = touch ? touch.clientY - gesture.y : 0;
        if (gesture.horizontal && Math.abs(dx) >= 64 && Math.abs(dx) > Math.abs(dy) * 1.2) {
            closeTopFromGesture();
        }
        gesture = null;
    }, { passive: true });

    document.addEventListener('touchcancel', function () { gesture = null; }, { passive: true });
})();
