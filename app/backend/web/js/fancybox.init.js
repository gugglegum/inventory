document.addEventListener('DOMContentLoaded', function() {
    if (!window.Fancybox) {
        return;
    }

    window.Fancybox.bind('[data-fancybox]', {
        mainClass: 'stockhub-fancybox',
        Carousel: {
            infinite: false
        }
    });
});
