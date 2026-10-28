/* Общий доступный select/listbox: работает с существующими onchange и динамическими формами. */
(function () {
    var instances = new WeakMap(), states = new Set(), openState = null, serial = 0;
    var valueProperty = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    var indexProperty = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');

    function close() {
        if (!openState) return;
        var state = openState; openState = null;
        state.button.setAttribute('aria-expanded', 'false');
        state.button.removeAttribute('aria-activedescendant');
        if (state.menu) state.menu.remove();
        state.menu = null;
    }
    function position(state) {
        if (!state.menu) return;
        var rect = state.button.getBoundingClientRect();
        var viewport = window.visualViewport;
        var height = viewport ? viewport.height : innerHeight;
        var width = viewport ? viewport.width : innerWidth;
        if (rect.bottom < 0 || rect.top > height || !state.button.getClientRects().length) { close(); return; }
        var above = rect.top - 12, below = height - rect.bottom - 12;
        var upward = below < 160 && above > below;
        var size = Math.min(Math.max(rect.width, 150), width - 16);
        state.menu.style.width = size + 'px';
        state.menu.style.maxHeight = Math.min(350, Math.max(70, upward ? above : below)) + 'px';
        state.menu.style.left = Math.min(Math.max(8, rect.left), width - size - 8) + 'px';
        state.menu.style.top = (upward ? Math.max(8, rect.top - state.menu.offsetHeight - 6) : rect.bottom + 6) + 'px';
    }
    function active(state, index) {
        state.active = index;
        if (!state.menu) return;
        state.menu.querySelectorAll('[role="option"]').forEach(function (option) {
            var current = Number(option.dataset.index) === index;
            option.classList.toggle('is-active', current);
            if (current) {
                state.button.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({block: 'nearest'});
            }
        });
    }
    function available(state) {
        return Array.from(state.select.options).map(function (option, index) { return index; }).filter(function (index) {
            var option = state.select.options[index];
            return !option.disabled && !option.hidden && !(option.parentElement.tagName === 'OPTGROUP' && option.parentElement.disabled);
        });
    }
    function choose(state, index) {
        if (available(state).indexOf(index) < 0) return;
        state.select.selectedIndex = index;
        close();
        state.button.focus({preventScroll: true});
        state.select.dispatchEvent(new Event('input', {bubbles: true}));
        state.select.dispatchEvent(new Event('change', {bubbles: true}));
    }
    function menuOptions(state) {
        if (!state.menu) return;
        state.menu.replaceChildren();
        var group = null;
        Array.from(state.select.options).forEach(function (option, index) {
            if (option.hidden) return;
            var parent = option.parentElement;
            if (parent.tagName === 'OPTGROUP' && parent !== group) {
                var title = document.createElement('div'); title.className = 'site-select-group'; title.textContent = parent.label;
                state.menu.appendChild(title); group = parent;
            }
            var item = document.createElement('div');
            item.className = 'site-select-option'; item.id = state.id + '-option-' + index;
            item.setAttribute('role', 'option'); item.dataset.index = index;
            item.setAttribute('aria-selected', String(option.selected));
            item.setAttribute('aria-disabled', String(option.disabled || (parent.tagName === 'OPTGROUP' && parent.disabled)));
            item.textContent = option.label;
            item.addEventListener('pointerdown', function (event) { event.preventDefault(); });
            item.addEventListener('click', function () { choose(state, index); });
            state.menu.appendChild(item);
        });
    }
    function sync(state) {
        var select = state.select, option = select.options[select.selectedIndex];
        state.label.textContent = option ? option.label : 'Выберите';
        state.button.disabled = select.disabled;
        state.button.setAttribute('aria-required', String(select.required));
        state.wrapper.hidden = select.hidden || select.style.display === 'none';
        var label = select.getAttribute('aria-label') || (select.labels && select.labels[0] ? select.labels[0].textContent.trim() : '') || select.name || select.id || 'Выберите значение';
        state.button.setAttribute('aria-label', label);
        if (select.getAttribute('aria-labelledby')) state.button.setAttribute('aria-labelledby', select.getAttribute('aria-labelledby'));
        if (state.menu) {
            if (select.disabled || state.wrapper.hidden) { close(); return; }
            menuOptions(state); position(state); active(state, select.selectedIndex);
        }
    }
    function open(state) {
        if (state.select.disabled) return;
        if (openState === state) { close(); return; }
        close(); openState = state;
        var menu = document.createElement('div');
        menu.className = 'site-select-menu'; menu.id = state.id + '-menu'; menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', state.button.getAttribute('aria-label'));
        state.menu = menu;
        if (typeof menu.showPopover === 'function') menu.setAttribute('popover', 'manual');
        document.body.appendChild(menu);
        menuOptions(state);
        if (typeof menu.showPopover === 'function') menu.showPopover();
        state.button.setAttribute('aria-expanded', 'true');
        position(state);
        var selected = state.select.selectedIndex;
        active(state, available(state).includes(selected) ? selected : available(state)[0]);
    }
    function enhance(select) {
        if (instances.has(select) || select.multiple || select.size > 1 || select.closest('.skiptranslate,#google_translate_element') || select.classList.contains('goog-te-combo')) return;
        var wrapper = document.createElement('span'); wrapper.className = 'site-select'; wrapper.dataset.selectClass = select.className;
        ['width', 'maxWidth', 'minWidth', 'margin', 'marginTop', 'marginBottom', 'flex', 'flexBasis'].forEach(function (property) {
            if (select.style[property]) wrapper.style[property] = select.style[property];
        });
        select.parentNode.insertBefore(wrapper, select); wrapper.appendChild(select);
        select.classList.add('site-native-select'); select.tabIndex = -1; select.setAttribute('aria-hidden', 'true');
        var button = document.createElement('button'); button.type = 'button'; button.className = 'site-select-trigger';
        button.setAttribute('role', 'combobox'); button.setAttribute('aria-haspopup', 'listbox'); button.setAttribute('aria-expanded', 'false');
        var id = 'site-select-' + (++serial); button.id = id;
        button.setAttribute('aria-controls', id + '-menu');
        var label = document.createElement('span'); label.className = 'site-select-label'; button.appendChild(label);
        button.insertAdjacentHTML('beforeend', '<svg class="site-select-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');
        wrapper.appendChild(button);
        var state = {select: select, wrapper: wrapper, button: button, label: label, menu: null, id: id, active: -1, query: '', timer: null};
        instances.set(select, state); states.add(state);
        button.addEventListener('click', function () { open(state); });
        button.addEventListener('keydown', function (event) {
            var keys = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' ', 'Escape'];
            if (event.key === 'Tab') { close(); return; }
            if (event.key === 'Escape') { if (state.menu) { event.preventDefault(); event.stopPropagation(); close(); } return; }
            if (keys.indexOf(event.key) >= 0) {
                event.preventDefault();
                if (event.key === 'Enter' || event.key === ' ') { if (state.menu) choose(state, state.active); else open(state); return; }
                if (!state.menu) open(state);
                var options = available(state), index = options.indexOf(state.active);
                if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = options.length - 1;
                else index = Math.max(0, Math.min(options.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1)));
                active(state, options[index]);
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                state.query += event.key.toLocaleLowerCase(); clearTimeout(state.timer);
                state.timer = setTimeout(function () { state.query = ''; }, 600);
                if (!state.menu) open(state);
                var match = available(state).find(function (index) { return select.options[index].label.toLocaleLowerCase().startsWith(state.query); });
                if (match !== undefined) active(state, match);
            }
        });
        select.addEventListener('change', function () { sync(state); });
        select.addEventListener('focus', function () { button.focus(); });
        select.addEventListener('invalid', function (event) { event.preventDefault(); button.focus(); open(state); });
        if (select.form) select.form.addEventListener('reset', function () { setTimeout(function () { sync(state); }, 0); });
        [['value', valueProperty], ['selectedIndex', indexProperty]].forEach(function (entry) {
            Object.defineProperty(select, entry[0], {
                configurable: true,
                get: function () { return entry[1].get.call(this); },
                set: function (value) { entry[1].set.call(this, value); sync(state); }
            });
        });
        state.observer = new MutationObserver(function () { sync(state); });
        state.observer.observe(select, {childList: true, subtree: true, attributes: true, characterData: true});
        sync(state);
    }
    function scan(root) {
        if (root.nodeType !== 1 && root.nodeType !== 9) return;
        if (root.matches && root.matches('select')) enhance(root);
        root.querySelectorAll('select').forEach(enhance);
    }
    scan(document);
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) { mutation.addedNodes.forEach(scan); });
        states.forEach(function (state) {
            if (state.select.isConnected) return;
            if (openState === state) close();
            state.observer.disconnect(); clearTimeout(state.timer); states.delete(state); instances.delete(state.select);
            if (state.wrapper.isConnected) state.wrapper.remove();
        });
    }).observe(document.body, {childList: true, subtree: true});
    document.addEventListener('click', function (event) {
        if (openState && !openState.wrapper.contains(event.target) && !openState.menu.contains(event.target)) close();
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (openState) position(openState); });
    document.addEventListener('scroll', function () { if (openState) position(openState); }, true);
    if (window.visualViewport) window.visualViewport.addEventListener('resize', function () { if (openState) position(openState); });
})();
