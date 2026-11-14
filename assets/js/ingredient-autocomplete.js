/* Ingredient suggestions for both recipe modals. Typing never writes to the dictionary. */
(function () {
    var input = null, menu = null, items = [], active = -1, controller = null;
    var timer = null, generation = 0, sequence = 0, selecting = false;
    function field(target) {
        return target instanceof HTMLInputElement && target.matches('.ing-name') && target.closest('#addRecipeModal,#editRecipeModal');
    }
    function close() {
        generation++;
        clearTimeout(timer);
        if (controller) controller.abort();
        controller = null;
        if (input) { input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }
        if (menu) menu.remove();
        menu = null; items = []; active = -1;
    }
    function position() {
        if (!menu || !input) return;
        var rect = input.getBoundingClientRect();
        var viewport = window.visualViewport;
        var top = viewport ? viewport.offsetTop : 0;
        var left = viewport ? viewport.offsetLeft : 0;
        var height = viewport ? viewport.height : innerHeight;
        var width = viewport ? viewport.width : innerWidth;
        if (rect.bottom < top || rect.top > top + height || !input.getClientRects().length) { close(); return; }
        var above = rect.top - top - 12, below = top + height - rect.bottom - 12;
        var upwards = below < 130 && above > below;
        var size = Math.min(rect.width, width - 16);
        menu.style.width = size + 'px';
        menu.style.maxHeight = Math.min(280, Math.max(60, upwards ? above : below)) + 'px';
        menu.style.left = Math.min(Math.max(left + 8, rect.left), left + width - size - 8) + 'px';
        menu.style.top = (upwards ? Math.max(top + 8, rect.top - menu.offsetHeight - 6) : rect.bottom + 6) + 'px';
    }
    function ensureMenu() {
        if (menu) return;
        menu = document.createElement('div');
        menu.className = 'site-select-menu ingredient-suggestions';
        menu.id = input.dataset.ingredientSuggestions;
        menu.setAttribute('role', 'listbox'); menu.setAttribute('aria-label', 'Ингредиенты');
        if (typeof menu.showPopover === 'function') menu.setAttribute('popover', 'manual');
        document.body.appendChild(menu);
        if (typeof menu.showPopover === 'function') menu.showPopover();
        input.setAttribute('aria-expanded', 'true');
    }
    function note(text) {
        ensureMenu(); menu.replaceChildren();
        var message = document.createElement('div'); message.className = 'ingredient-suggestion-note';
        message.setAttribute('role', 'status'); message.textContent = text; menu.appendChild(message);
        position();
    }
    function highlight(index) {
        active = index;
        if (!menu) return;
        menu.querySelectorAll('[role=option]').forEach(function (option, i) {
            option.classList.toggle('is-active', i === active);
            option.setAttribute('aria-selected', String(i === active));
            if (i === active) {
                input.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({block: 'nearest'});
            }
        });
    }
    function choose(index) {
        if (!items[index] || !input) return;
        var target = input;
        target.value = items[index].name;
        close();
        selecting = true;
        target.dispatchEvent(new Event('input', {bubbles: true}));
        target.dispatchEvent(new Event('change', {bubbles: true}));
        selecting = false;
        target.focus({preventScroll: true});
    }
    async function lookup() {
        if (!input || document.activeElement !== input) return;
        if (controller) controller.abort();
        var target = input, query = target.value.trim(), current = ++generation;
        items = []; active = -1; target.removeAttribute('aria-activedescendant');
        if (query.length > 100) { close(); return; }
        var request = new AbortController(); controller = request;
        note('Ищем ингредиенты…');
        try {
            var response = await fetch('/api/ingredients.php?q=' + encodeURIComponent(query), {signal: request.signal});
            var data = await response.json();
            if (current !== generation || input !== target || document.activeElement !== target) return;
            if (!response.ok || !data.success) throw new Error('suggestions');
            items = data.ingredients || [];
            if (!items.length) {
                note(query ? 'Новое название добавится в справочник после сохранения рецепта.' : 'Введите название — новые ингредиенты сохраняются вместе с рецептом.');
                return;
            }
            ensureMenu(); menu.replaceChildren();
            items.forEach(function (ingredient, index) {
                var option = document.createElement('div');
                option.className = 'site-select-option ingredient-option'; option.id = menu.id + '-' + index;
                option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
                var name = document.createElement('span'); name.className = 'ingredient-option-name'; name.textContent = ingredient.name;
                option.appendChild(name);
                if (ingredient.recipe_count > 0) {
                    var count = document.createElement('span'); count.className = 'ingredient-option-count';
                    count.textContent = 'В рецептах: ' + ingredient.recipe_count; option.appendChild(count);
                }
                option.addEventListener('pointerdown', function (event) { event.preventDefault(); });
                option.addEventListener('click', function () { choose(index); });
                menu.appendChild(option);
            });
            position();
        } catch (error) {
            if (error.name === 'AbortError' || current !== generation) return;
            note('Подсказки временно недоступны. Название можно ввести самостоятельно.');
        }
    }
    function initialize(target) {
        if (input !== target) close();
        input = target;
        if (!input.dataset.ingredientSuggestions) input.dataset.ingredientSuggestions = 'ingredient-suggestions-' + (++sequence);
        input.setAttribute('autocomplete', 'off'); input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-controls', input.dataset.ingredientSuggestions);
        input.maxLength = 100;
    }
    document.addEventListener('focusin', function (event) {
        if (!field(event.target)) return;
        initialize(event.target);
        if (!selecting) lookup();
    });
    document.addEventListener('input', function (event) {
        if (selecting || !field(event.target)) return;
        initialize(event.target);
        close(); clearTimeout(timer);
        timer = setTimeout(lookup, 220);
    });
    document.addEventListener('keydown', function (event) {
        if (event.target !== input || !field(input)) return;
        if (event.key === 'Escape' && menu) { event.preventDefault(); event.stopPropagation(); close(); return; }
        if (event.key === 'Tab') { close(); return; }
        if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && items.length) {
            event.preventDefault(); event.stopPropagation();
            highlight(event.key === 'ArrowDown' ? (active + 1) % items.length : (active <= 0 ? items.length - 1 : active - 1));
        } else if (event.key === 'Enter' && menu && active >= 0) {
            event.preventDefault(); event.stopPropagation(); choose(active);
        }
    }, true);
    document.addEventListener('focusout', function (event) { if (event.target === input) close(); });
    document.addEventListener('pointerdown', function (event) {
        if (menu && event.target !== input && !menu.contains(event.target)) close();
    });
    document.addEventListener('scroll', position, true);
    window.addEventListener('resize', position);
    if (window.visualViewport) {
        visualViewport.addEventListener('resize', position);
        visualViewport.addEventListener('scroll', position);
    }
    new MutationObserver(function () {
        if (!menu || !input) return;
        var modal = input.closest('#addRecipeModal,#editRecipeModal');
        if (!input.isConnected || !modal || !modal.classList.contains('active')) close();
    }).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['class']});
})();
