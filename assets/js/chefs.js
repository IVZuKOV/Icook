(function () {
    var grid = document.getElementById('chefsGrid');
    var search = document.getElementById('chefSearch');
    var category = document.getElementById('chefCategory');
    var columns = document.getElementById('chefColumns');
    var summary = document.getElementById('chefsSummary');
    var timer;
    function element(tag, className, text) {
        var node = document.createElement(tag); node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function card(chef) {
        var article = element('article', 'chef-card');
        var initials = (chef.name || 'Повар').trim().split(/\s+/).slice(0,2).map(function (word) { return Array.from(word)[0] || ''; }).join('').toUpperCase();
        var avatar = element('div', 'chef-avatar', initials); avatar.setAttribute('aria-hidden', 'true'); article.appendChild(avatar);
        article.appendChild(element('h2', 'chef-name', chef.name || 'Повар'));
        var location = [chef.city, chef.country].filter(Boolean).join(', ');
        if (location) article.appendChild(element('p', 'chef-location', location));
        article.appendChild(element('p', 'chef-description', chef.description || 'Знакомьтесь с поваром и его домашней кухней.'));
        article.appendChild(element('span', 'chef-count', 'Блюд в меню: ' + Number(chef.dish_count || 0)));
        var link = element('a', 'btn', 'Меню повара →'); link.href = 'menu.html?seller_id=' + encodeURIComponent(chef.id);
        link.setAttribute('aria-label', 'Меню повара ' + (chef.name || '')); article.appendChild(link);
        return article;
    }
    var feed = new InfiniteFeed({
        grid: grid, sentinel: document.getElementById('chefsSentinel'),
        url: '/api/chefs.php', key: 'chefs', loadingText: 'Загружаем поваров…',
        endText: 'Вы посмотрели всех поваров',
        params: function () { return {search: search.value.trim(), category_id: category.value}; },
        emptyText: function () { return search.value || category.value !== '0' ? 'Повара не найдены. Попробуйте изменить поиск или категорию.' : 'Здесь скоро появятся наши повара.'; },
        render: function (chefs) { chefs.forEach(function (chef) { grid.appendChild(card(chef)); }); },
        onTotal: function (total) { summary.textContent = 'Найдено поваров: ' + total; },
        onReset: function () { summary.textContent = ''; }
    });
    search.addEventListener('input', function () {
        document.getElementById('chefSearchClear').hidden = !search.value;
        clearTimeout(timer); timer = setTimeout(function () { feed.reset(); }, 300);
    });
    document.getElementById('chefSearchClear').addEventListener('click', function () {
        clearTimeout(timer); search.value = ''; this.hidden = true; feed.reset(); search.focus();
    });
    category.addEventListener('change', function () { clearTimeout(timer); feed.reset(); });
    columns.addEventListener('change', function () { grid.className = 'chefs-grid cols-' + this.value; });
    fetch('/api/categories.php').then(function (response) {
        if (!response.ok) throw new Error('categories'); return response.json();
    }).then(function (data) {
        if (!data.success) return;
        data.categories.forEach(function (item) { var option = document.createElement('option'); option.value = item.id; option.textContent = item.name; category.appendChild(option); });
    }).catch(function () { category.disabled = true; });
    feed.reset();
})();
