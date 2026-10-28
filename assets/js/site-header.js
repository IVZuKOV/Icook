/* Один обработчик прокрутки для всех верхних хедеров. */
(function () {
    var header = document.querySelector('header.site-header');
    if (!header || header.dataset.scrollHeader === 'ready') return;
    header.dataset.scrollHeader = 'ready';
    header.classList.remove('hidden', 'is-scroll-hidden');
    function scrollPosition() {
        var max = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
        return Math.max(0, Math.min(window.scrollY, max));
    }
    var lastY = scrollPosition();
    var queued = false;
    function update() {
        queued = false;
        var y = scrollPosition();
        if (y <= 60 || y < lastY) header.classList.remove('is-scroll-hidden');
        else if (y > lastY) header.classList.add('is-scroll-hidden');
        lastY = y;
    }
    window.addEventListener('scroll', function () {
        if (queued) return;
        queued = true;
        requestAnimationFrame(update);
    }, {passive: true});
    header.addEventListener('focusin', function () { header.classList.remove('is-scroll-hidden'); });
})();
