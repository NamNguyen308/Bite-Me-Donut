/**
 * home.js — Homepage interactions
 * Scroll reveal + smooth anchor scroll
 */

(function () {
    'use strict';

    /* ----------------------------------------------------------
       1. SCROLL REVEAL
       Adds `.reveal` to key sections and triggers `.is-visible`
       when they enter the viewport via IntersectionObserver.
    ---------------------------------------------------------- */
    const REVEAL_SELECTORS = [
        '.trust-bar__item',
        '.category-card',
        '.product-card',
        '.step',
        '.testimonial-card',
        '.promo-banner__image-wrap',
        '.promo-banner__content',
        '.hero__stats',
    ];

    function initReveal() {
        if (!('IntersectionObserver' in window)) return;

        const elements = document.querySelectorAll(REVEAL_SELECTORS.join(', '));

        elements.forEach(function (el, index) {
            el.classList.add('reveal');

            // Stagger siblings inside the same parent
            const siblings = Array.from(el.parentElement.children);
            const siblingIndex = siblings.indexOf(el);
            if (siblingIndex > 0 && siblingIndex <= 4) {
                el.classList.add('reveal--delay-' + siblingIndex);
            }
        });

        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        document.querySelectorAll('.reveal').forEach(function (el) {
            observer.observe(el);
        });
    }

    /* ----------------------------------------------------------
       2. SMOOTH ANCHOR SCROLL
       Handles "#how-it-works" and other in-page links,
       offsetting for the sticky navbar height.
    ---------------------------------------------------------- */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                var targetId = this.getAttribute('href').slice(1);
                if (!targetId) return;

                var target = document.getElementById(targetId);
                if (!target) return;

                e.preventDefault();

                var navbarHeight = parseInt(
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--navbar-height') || '68',
                    10
                );

                var targetY =
                    target.getBoundingClientRect().top +
                    window.pageYOffset -
                    navbarHeight -
                    16;

                window.scrollTo({ top: targetY, behavior: 'smooth' });
            });
        });
    }

    /* ----------------------------------------------------------
       3. INIT
    ---------------------------------------------------------- */
    function init() {
        initReveal();
        initSmoothScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();