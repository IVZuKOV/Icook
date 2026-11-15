(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function initialFor(name) {
        var first = Array.from(String(name || '').trim())[0] || '?';
        return first.toLocaleUpperCase('ru-RU');
    }

    function safeAvatarPath(path) {
        var value = String(path || '').replace(/^\/+/, '');
        return /^uploads\/avatars\/avatar_[0-9]+_[a-f0-9]{12}\.jpg$/i.test(value) ? value : '';
    }

    function create(name, path, version, className) {
        var avatar = document.createElement('span');
        avatar.className = 'author-avatar' + (className ? ' ' + className : '');
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = initialFor(name);

        var safePath = safeAvatarPath(path);
        if (safePath) {
            var image = document.createElement('img');
            image.alt = '';
            image.loading = 'lazy';
            image.draggable = false;
            image.addEventListener('error', function () { image.remove(); });
            var safeVersion = /^[a-f0-9]{1,64}$/i.test(String(version || '')) ? String(version) : '';
            image.src = '/' + safePath + (safeVersion ? '?v=' + encodeURIComponent(safeVersion) : '');
            avatar.appendChild(image);
        }
        return avatar;
    }

    global.IcookAuthorAvatar = {
        create: create,
        html: function (name, path, version, className) {
            return create(name, path, version, className).outerHTML;
        },
        escape: escapeHtml
    };
})(window);
