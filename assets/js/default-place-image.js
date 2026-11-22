(function (global) {
    'use strict';

    var images = [
        '/assets/images/default-places/place-1.jpg',
        '/assets/images/default-places/place-2.jpg'
    ];

    global.IcookDefaultPlaceImage = {
        random: function () {
            return images[Math.floor(Math.random() * images.length)];
        }
    };
})(window);
