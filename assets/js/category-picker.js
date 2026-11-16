(function () {
    'use strict';
    var categories = [];
    var byId = new Map();

    function children(parentId) {
        return categories.filter(function (item) {
            return Number(item.parent_id || 0) === Number(parentId || 0);
        });
    }

    function option(select, items, placeholder, selected) {
        select.innerHTML = '';
        var first = document.createElement('option');
        first.value = '';
        first.textContent = placeholder;
        select.appendChild(first);
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            select.appendChild(opt);
        });
        if (selected && items.some(function (item) { return Number(item.id) === Number(selected); })) {
            select.value = String(selected);
        }
    }

    function pathFor(id) {
        var path = [], item = byId.get(Number(id));
        while (item) {
            path.unshift(item);
            item = item.parent_id ? byId.get(Number(item.parent_id)) : null;
        }
        return path;
    }

    function bind(root) {
        var search = root.querySelector('[data-category-search]');
        var results = root.querySelector('[data-category-results]');
        var main = root.querySelector('[data-category-main]');
        var sub = root.querySelector('[data-category-sub]');
        var subsub = root.querySelector('[data-category-subsub]');
        var title = root.dataset.titleId && document.getElementById(root.dataset.titleId);
        var description = root.dataset.descriptionId && document.getElementById(root.dataset.descriptionId);
        var manual = false, timer;

        function setPath(path, userChoice) {
            if (!path || !path.length) return;
            main.value = String(path[0].id);
            option(sub, children(path[0].id), 'Выберите подкатегорию *', path[1] && path[1].id);
            option(subsub, path[1] ? children(path[1].id) : [], 'Подкатегория 2 (необязательно)', path[2] && path[2].id);
            root.dataset.categoryId = String((path[2] || path[1]).id);
            if (userChoice) {
                manual = true;
                search.value = path.map(function (x) { return x.name; }).join(' › ');
                results.hidden = true;
            }
        }

        function renderResults(query) {
            results.innerHTML = '';
            var q = query.trim().toLocaleLowerCase('ru');
            if (!q) { results.hidden = true; return; }
            var matches = categories.filter(function (item) {
                var path = pathFor(item.id);
                return path.length >= 2 && path.map(function (x) { return x.name; }).join(' ').toLocaleLowerCase('ru').includes(q);
            }).slice(0, 20);
            matches.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'category-search-result';
                button.textContent = pathFor(item.id).map(function (x) { return x.name; }).join(' › ');
                button.addEventListener('click', function () { setPath(pathFor(item.id), true); });
                results.appendChild(button);
            });
            results.hidden = !matches.length;
        }

        main.addEventListener('change', function () {
            manual = true;
            option(sub, children(main.value), 'Выберите подкатегорию *');
            option(subsub, [], 'Подкатегория 2 (необязательно)');
            root.dataset.categoryId = '';
            search.value = byId.get(Number(main.value))?.name || '';
            results.hidden = true;
        });
        sub.addEventListener('change', function () {
            manual = true;
            option(subsub, children(sub.value), 'Подкатегория 2 (необязательно)');
            root.dataset.categoryId = sub.value || '';
            results.hidden = true;
        });
        subsub.addEventListener('change', function () {
            manual = true;
            root.dataset.categoryId = subsub.value || sub.value || '';
            results.hidden = true;
        });
        search.addEventListener('input', function () { renderResults(search.value); });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) results.hidden = true;
        });
        function suggest() {
            if (manual || !title || !title.value.trim()) return;
            var text = (title.value + ' ' + (description ? description.value : '')).toLocaleLowerCase('ru');
            var terms = text.match(/[а-яёa-z]{3,}/gi) || [];
            var best = null, score = 0;
            categories.filter(function (c) { return children(c.id).length > 0 && c.parent_id; }).forEach(function (item) {
                var path = pathFor(item.id);
                if (path.length < 2) return;
                var label = path.map(function (x) { return x.name; }).join(' ').toLocaleLowerCase('ru');
                var points = terms.reduce(function (n, term) { return n + (label.includes(term) ? Math.min(term.length, 8) : 0); }, 0);
                if (points > score) { score = points; best = item; }
            });
            if (best && score >= 3) {
                setPath(pathFor(best.id), false);
                search.value = pathFor(best.id).map(function (x) { return x.name; }).join(' › ');
            }
        }
        [title, description].forEach(function (field) {
            if (field) field.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(suggest, 350);
            });
        });
        root.getCategoryId = function () { return root.dataset.categoryId || ''; };
        root.getPath = function () {
            return {
                main_category_id: main.value || '',
                subcategory_id: sub.value || '',
                subsubcategory_id: subsub.value || ''
            };
        };
        root.setCategoryId = function (id) {
            var path = pathFor(id);
            if (path.length >= 2) {
                setPath(path, false);
                search.value = path.map(function (x) { return x.name; }).join(' › ');
                manual = false;
            } else {
                main.value = '';
                option(sub, [], 'Выберите подкатегорию *');
                option(subsub, [], 'Подкатегория 2 (необязательно)');
                root.dataset.categoryId = '';
            }
        };
        root.resetCategory = function () {
            manual = false;
            search.value = '';
            root.dataset.categoryId = '';
            main.value = '';
            option(sub, [], 'Выберите подкатегорию *');
            option(subsub, [], 'Подкатегория 2 (необязательно)');
        };
        option(main, children(null), 'Выберите категорию *');
        option(sub, [], 'Выберите подкатегорию *');
        option(subsub, [], 'Подкатегория 2 (необязательно)');
    }

    fetch('/api/categories.php').then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) return;
        categories = data.categories;
        categories.forEach(function (item) { byId.set(Number(item.id), item); });
        document.querySelectorAll('[data-category-picker]').forEach(bind);
    }).catch(function (error) { console.error('Category picker:', error); });
})();
