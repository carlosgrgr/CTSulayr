jQuery(window).load(function ($) {
    function initializeSplides() {
        document.querySelectorAll('.splide-element').forEach((element, index) => {
            if (!element.classList.contains('is-initialized')) {

                const testimonial = element.closest('.c-testamonials');

                let autoplay = 'no';
                let autoplayDelay = 3000;

                if (testimonial) {
                    autoplay = testimonial.getAttribute('data-autoplay') || 'no';
                    autoplayDelay = parseInt(testimonial.getAttribute('data-autoplay-delay')) || 3000;
                }

                let splide = new Splide(element, {
                    type: "loop",
                    focus: "center",
                    autoWidth: true,
                    gap: "40px",
                    arrows: false,
                    breakpoints: {
                        1199: { gap: "24px" },
                        767: { gap: "16px" },
                        480: { gap: "10px" },
                    },
                }).mount();

                if (splide) {
                    element.closest('.c-testamonials')?.querySelector('.splide_left .prev')?.addEventListener('click', () => {
                        splide.go('-1');
                    });

                    element.closest('.c-testamonials')?.querySelector('.splide_right .next')?.addEventListener('click', () => {
                        splide.go('+1');
                    });

                    if (autoplay === 'yes') {
                        if (element.dataset.intervalId) {
                            clearInterval(element.dataset.intervalId);
                        }

                        let interval = setInterval(() => {
                            if (document.contains(element)) {
                                splide.go('+1');
                            } else {
                                clearInterval(interval);
                            }
                        }, autoplayDelay);

                        element.dataset.intervalId = interval;
                    }
                }
            }
        });
    }

    var observeWpVisualEditor = (function () {
        var MutationObserver = window.MutationObserver || window.WebKitMutationObserver,
            eventListenerSupported = window.addEventListener;

        return function (obj, callback) {
            if (MutationObserver) {
                var obs = new MutationObserver(function (mutations, observer) {
                    if (mutations[0].addedNodes.length || mutations[0].removedNodes.length) {
                        let position_testimonials = mutations[0].target.className.indexOf('wp-block-ferrovial-testimonials');
                        if (position_testimonials !== -1) {
                            callback();
                        }
                    }
                });

                obs.observe(obj, { childList: true, subtree: true });
            } else if (eventListenerSupported) {
                obj.addEventListener('DOMNodeInserted', callback, false);
                obj.addEventListener('DOMNodeRemoved', callback, false);
            }
        };
    })();

    let VisualEditor = jQuery('.edit-post-visual-editor');
    if (typeof VisualEditor[0] !== 'undefined') {
        observeWpVisualEditor(VisualEditor[0], function () {
            initializeSplides();
        });
    }

    initializeSplides();
});
