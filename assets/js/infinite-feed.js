/* Общая автоподгрузка: один запрос за раз, отмена старого поиска, без дублей. */
(function () {
    window.InfiniteFeed = function (options) {
        var grid = options.grid;
        var sentinel = options.sentinel;
        var page = 0, pages = Infinity, loading = false, failed = false;
        var controller = null, generation = 0, seen = new Set();
        var observer = null;

        function message(text, retry) {
            sentinel.replaceChildren();
            sentinel.textContent = text;
            if (retry) {
                var button = document.createElement('button');
                button.type = 'button'; button.className = 'btn feed-retry'; button.textContent = 'Повторить';
                button.addEventListener('click', function () { failed = false; load(); });
                sentinel.appendChild(button);
            }
        }
        async function load() {
            if (loading || page >= pages) return;
            loading = true; failed = false;
            if (observer) observer.unobserve(sentinel);
            var currentGeneration = generation;
            var request = new AbortController(); controller = request;
            grid.setAttribute('aria-busy', 'true');
            message(options.loadingText);
            var params = new URLSearchParams(options.params ? options.params() : {});
            params.set('page', page + 1); params.set('limit', 10);
            try {
                var response = await fetch(options.url + '?' + params, {signal: request.signal});
                var data = await response.json();
                if (currentGeneration !== generation) return;
                if (!response.ok || !data.success || !Array.isArray(data[options.key])) throw new Error('feed');
                var rows = data[options.key];
                var fresh = rows.filter(function (row) {
                    var key = String(row.id);
                    if (seen.has(key)) return false;
                    seen.add(key); return true;
                });
                options.render(fresh);
                page += 1; pages = Math.max(0, Number(data.pages) || 0);
                if (!rows.length) pages = page;
                if (options.onTotal) options.onTotal(Number(data.total) || 0);
                message(!seen.size ? options.emptyText() : page >= pages ? options.endText : '');
            } catch (error) {
                if (error.name === 'AbortError' || currentGeneration !== generation) return;
                failed = true;
                message('Не удалось загрузить карточки. Проверьте соединение и повторите.', true);
            } finally {
                if (currentGeneration === generation) {
                    loading = false; grid.setAttribute('aria-busy', 'false');
                    if (observer && !failed && page < pages) observer.observe(sentinel);
                }
            }
        }
        function reset() {
            generation += 1;
            if (controller) controller.abort();
            if (observer) observer.unobserve(sentinel);
            loading = false; failed = false; page = 0; pages = Infinity; seen.clear();
            grid.replaceChildren();
            if (options.onReset) options.onReset();
            return load();
        }
        if ('IntersectionObserver' in window) {
            observer = new IntersectionObserver(function (entries) {
                if (entries.some(function (entry) { return entry.isIntersecting; }) && !failed) load();
            }, {rootMargin: '0px 0px 100px 0px'});
        } else {
            window.addEventListener('scroll', function () {
                if (!failed && sentinel.getBoundingClientRect().top < window.innerHeight + 100) load();
            }, {passive: true});
        }
        return {reset: reset, load: load};
    };
})();
