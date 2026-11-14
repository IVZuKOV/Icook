(function () {
    'use strict';

    const storageKey = 'favoriteContent';
    const allowedTypes = new Set(['dish', 'place', 'recipe']);

    function readLegacyDishIds() {
        try {
            const ids = JSON.parse(localStorage.getItem('favorites') || '[]');
            return Array.isArray(ids) ? ids.map(Number).filter(Number.isFinite) : [];
        } catch (error) {
            console.error('Unable to read existing dish favorites', error);
            return [];
        }
    }

    function readStoredEntries() {
        try {
            const entries = JSON.parse(localStorage.getItem(storageKey) || '[]');
            if (!Array.isArray(entries)) throw new TypeError('Favorite content must be an array');
            return entries.filter(function (entry) {
                return entry && allowedTypes.has(entry.type) && String(entry.id || '') !== '';
            }).map(function (entry) {
                return {
                    type: entry.type,
                    id: String(entry.id),
                    addedAt: Number(entry.addedAt) || 0,
                    item: entry.item && typeof entry.item === 'object' ? entry.item : null
                };
            });
        } catch (error) {
            console.error('Unable to read saved favorites', error);
            return [];
        }
    }

    function getEntries() {
        const entries = readStoredEntries();
        const knownDishIds = new Set(entries.filter(function (entry) {
            return entry.type === 'dish';
        }).map(function (entry) { return entry.id; }));

        readLegacyDishIds().forEach(function (id, index) {
            const key = String(id);
            if (!knownDishIds.has(key)) {
                entries.push({ type: 'dish', id: key, addedAt: index + 1, item: null });
                knownDishIds.add(key);
            }
        });

        return entries;
    }

    function sanitizeItem(type, item) {
        if (!item || typeof item !== 'object') return null;
        const fields = type === 'place'
            ? ['name', 'type', 'address', 'image', 'website', 'email', 'phone', 'distance', 'venueId']
            : type === 'recipe'
                ? ['title', 'image', 'author_name', 'description']
                : ['title', 'image', 'seller_name', 'description', 'price', 'weight', 'category_name'];
        const result = {};
        fields.forEach(function (field) {
            const value = item[field];
            if (value === null || ['string', 'number'].includes(typeof value)) result[field] = value;
        });
        return result;
    }

    function writeEntries(entries) {
        localStorage.setItem(storageKey, JSON.stringify(entries));
    }

    function syncLegacyDishIds(entries) {
        localStorage.setItem('favorites', JSON.stringify(entries
            .filter(function (entry) { return entry.type === 'dish'; })
            .map(function (entry) { return Number(entry.id); })
            .filter(Number.isFinite)));
    }

    function isFavorite(type, id) {
        const key = String(id);
        return getEntries().some(function (entry) {
            return entry.type === type && entry.id === key;
        });
    }

    function toggleFavorite(type, id, item) {
        if (!allowedTypes.has(type) || id === null || id === undefined || String(id) === '') {
            throw new TypeError('Invalid favorite type or ID');
        }

        const key = String(id);
        const entries = getEntries();
        const existingIndex = entries.findIndex(function (entry) {
            return entry.type === type && entry.id === key;
        });
        const added = existingIndex < 0;
        if (added) {
            const latestTimestamp = entries.reduce(function (latest, entry) {
                return Math.max(latest, entry.addedAt);
            }, 0);
            entries.push({
                type: type,
                id: key,
                addedAt: Math.max(Date.now(), latestTimestamp + 1),
                item: sanitizeItem(type, item)
            });
        } else {
            entries.splice(existingIndex, 1);
        }

        writeEntries(entries);
        syncLegacyDishIds(entries);
        document.querySelectorAll('.content-fav-btn[data-favorite-type="' + type + '"]').forEach(function (button) {
            if (button.dataset.favoriteId === key) {
                button.classList.toggle('active', added);
                button.setAttribute('aria-label', added ? 'Удалить из избранного' : 'Добавить в избранное');
            }
        });
        if (type === 'dish') {
            document.querySelectorAll('.fav-btn[data-id="' + key + '"]').forEach(function (button) {
                button.classList.toggle('active', added);
            });
        }
        if (typeof window.updateFavBadge === 'function') window.updateFavBadge();
        if (typeof window.showToast === 'function') {
            window.showToast(added ? 'Добавлено в избранное' : 'Удалено из избранного');
        }
        document.dispatchEvent(new CustomEvent('favoritecontentchange', {
            detail: { type: type, id: key, added: added }
        }));
        return added;
    }

    window.getFavoriteContent = getEntries;
    window.isContentFavorite = isFavorite;
    window.toggleContentFavorite = toggleFavorite;

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.content-fav-btn');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        let item = null;
        if (button.dataset.favoriteItem) {
            try {
                item = JSON.parse(button.dataset.favoriteItem);
            } catch (error) {
                console.error('Unable to read favorite item details', error);
            }
        }
        toggleFavorite(button.dataset.favoriteType, button.dataset.favoriteId, item);
    }, true);
})();
