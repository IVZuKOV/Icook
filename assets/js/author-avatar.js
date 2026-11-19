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

    function parseCrop(value) {
        if (typeof value === 'string') {
            try { value = JSON.parse(value); } catch (error) { value = null; }
        }
        var zoom = Math.max(1, Math.min(3, Number(value && value.zoom) || 1));
        return { zoom: zoom, x: Math.max(-1, Math.min(1, Number(value && value.x) || 0)), y: Math.max(-1, Math.min(1, Number(value && value.y) || 0)) };
    }

    function applyCrop(image) {
        var settings = parseCrop(image.getAttribute('data-crop'));
        var ratioX = Math.max(1, image.naturalWidth / image.naturalHeight);
        var ratioY = Math.max(1, image.naturalHeight / image.naturalWidth);
        var width = ratioX * settings.zoom;
        var height = ratioY * settings.zoom;
        var xShift = settings.x * Math.max(0, width - 1) / (2 * width) * 100;
        var yShift = settings.y * Math.max(0, height - 1) / (2 * height) * 100;
        image.style.width = (width * 100) + '%';
        image.style.height = (height * 100) + '%';
        image.style.maxWidth = 'none';
        image.style.maxHeight = 'none';
        image.style.top = '50%';
        image.style.left = '50%';
        image.style.transform = 'translate(calc(-50% + ' + (xShift * 1) + '%), calc(-50% + ' + (yShift * 1) + '%))';
    }

    function create(name, path, version, crop, className) {
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
            var settings = parseCrop(crop);
            image.dataset.crop = JSON.stringify(settings);
            image.setAttribute('onload', 'window.IcookAuthorAvatar.applyCrop(this)');
            var safeVersion = /^[a-f0-9]{1,64}$/i.test(String(version || '')) ? String(version) : '';
            image.src = '/' + safePath + (safeVersion ? '?v=' + encodeURIComponent(safeVersion) : '');
            avatar.appendChild(image);
        }
        return avatar;
    }

    global.IcookAuthorAvatar = {
        create: create,
        applyCrop: applyCrop,
        html: function (name, path, version, crop, className) {
            return create(name, path, version, crop, className).outerHTML;
        },
        escape: escapeHtml
    };
})(window);
