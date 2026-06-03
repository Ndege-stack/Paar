/**
 * =====================================================================
 * PAAR — landing.js
 * ---------------------------------------------------------------------
 * Vanilla micro-interactions for the marketing landing page:
 *   1. Sticky-nav backdrop blur after scroll
 *   2. IntersectionObserver scroll reveals (data-reveal)
 *   3. Count-up stats (data-countup)
 *   4. Mouse-tracked spotlight in hero
 *   5. Mobile burger toggle
 *
 * Respects prefers-reduced-motion.
 * =====================================================================
 */

(function () {
    'use strict';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* -------------------------------------------------------------- *
     * 1. Sticky-nav backdrop blur                                    *
     * -------------------------------------------------------------- */
    const nav = document.getElementById('land-nav');
    if (nav) {
        const onScroll = () => {
            nav.classList.toggle('is-stuck', window.scrollY > 12);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* -------------------------------------------------------------- *
     * 2. Reveal on scroll                                            *
     * -------------------------------------------------------------- */
    const revealEls = document.querySelectorAll('[data-reveal]');
    revealEls.forEach((el) => {
        const delay = el.getAttribute('data-reveal-delay');
        if (delay) el.style.setProperty('--reveal-delay', delay + 'ms');
    });

    if (reduceMotion) {
        revealEls.forEach((el) => el.classList.add('is-visible'));
    } else if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        revealEls.forEach((el) => io.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('is-visible'));
    }

    /* -------------------------------------------------------------- *
     * 3. Count-up stats                                              *
     * -------------------------------------------------------------- */
    const formatNumber = (n) => n.toLocaleString('en-US');

    const countUp = (el) => {
        const end    = parseInt(el.getAttribute('data-countup-end') || '0', 10);
        const suffix = el.getAttribute('data-countup-suffix') || '';
        const dur    = 1600;
        const start  = performance.now();

        if (reduceMotion) {
            el.textContent = formatNumber(end) + suffix;
            return;
        }

        const tick = (now) => {
            const t = Math.min((now - start) / dur, 1);
            // ease-out cubic
            const eased = 1 - Math.pow(1 - t, 3);
            const value = Math.round(end * eased);
            el.textContent = formatNumber(value) + suffix;
            if (t < 1) requestAnimationFrame(tick);
            else el.textContent = formatNumber(end) + suffix;
        };
        requestAnimationFrame(tick);
    };

    const countEls = document.querySelectorAll('[data-countup]');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const cIO = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    countUp(entry.target);
                    cIO.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        countEls.forEach((el) => cIO.observe(el));
    } else {
        countEls.forEach((el) => countUp(el));
    }

    /* -------------------------------------------------------------- *
     * 4. Hero mouse spotlight                                        *
     * -------------------------------------------------------------- */
    const heroBg = document.querySelector('.land-hero');
    const spot   = document.querySelector('.land-hero__spotlight');
    if (heroBg && spot && !reduceMotion && window.matchMedia('(hover: hover)').matches) {
        let raf = 0;
        let mx = 50, my = 30;
        heroBg.addEventListener('pointermove', (e) => {
            const rect = heroBg.getBoundingClientRect();
            mx = ((e.clientX - rect.left) / rect.width) * 100;
            my = ((e.clientY - rect.top)  / rect.height) * 100;
            if (raf) return;
            raf = requestAnimationFrame(() => {
                spot.style.setProperty('--mx', mx + '%');
                spot.style.setProperty('--my', my + '%');
                raf = 0;
            });
        });
    }

    /* -------------------------------------------------------------- *
     * 5. Mobile burger toggle                                        *
     * -------------------------------------------------------------- */
    const burger = document.getElementById('land-nav-burger');
    if (burger && nav) {
        burger.addEventListener('click', () => {
            const open = burger.getAttribute('aria-expanded') === 'true';
            burger.setAttribute('aria-expanded', String(!open));
            nav.classList.toggle('is-open', !open);
        });
    }
})();
