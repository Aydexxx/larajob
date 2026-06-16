/**
 * LaraJob motion layer.
 *
 * Subtle, dependency-free interaction polish built on IntersectionObserver and
 * a couple of passive scroll listeners. Everything degrades gracefully:
 *   - with JavaScript disabled the page renders fully visible (CSS gates the
 *     hidden reveal state behind a `.js` class added below);
 *   - users with `prefers-reduced-motion: reduce` get the final state instantly,
 *     with no transitions, counting, or movement.
 *
 * All durations sit in the 150–300ms range for interactions and ~500ms for
 * one-shot scroll reveals, using transform/opacity only to avoid reflow.
 */

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const supportsObserver = 'IntersectionObserver' in window;

/**
 * Reveal-on-scroll. Elements tagged `[data-reveal]` fade and slide in once as
 * they enter the viewport; an inline `--reveal-delay` custom property staggers
 * groups such as card grids. Each element is revealed a single time.
 */
function initScrollReveal() {
    const targets = document.querySelectorAll('[data-reveal]');
    if (!targets.length) return;

    if (prefersReducedMotion() || !supportsObserver) {
        targets.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.15 }
    );

    targets.forEach((el) => observer.observe(el));
}

/**
 * Count-up. Numbers tagged `[data-count-to]` animate from zero to their target
 * the first time they scroll into view, then stop. The server-rendered value
 * remains as the fallback when motion is reduced or scripts do not run.
 */
function initCounters() {
    const counters = document.querySelectorAll('[data-count-to]');
    if (!counters.length) return;

    const render = (el, value) => {
        el.textContent = Number(value).toLocaleString('en-US');
    };

    if (prefersReducedMotion() || !supportsObserver) {
        return; // Leave the server-rendered number untouched.
    }

    // Reset to zero up-front so the count-up starts cleanly, with no flash of
    // the final figure before the element scrolls into view.
    counters.forEach((el) => render(el, 0));

    const animate = (el) => {
        const target = Number(el.dataset.countTo) || 0;
        const duration = 1200;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            render(el, Math.round(target * eased));
            if (progress < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                animate(entry.target);
                obs.unobserve(entry.target);
            });
        },
        { threshold: 0.4 }
    );

    counters.forEach((el) => observer.observe(el));
}

/**
 * Sticky navbar elevation. Toggles `is-scrolled` on `[data-navbar]` so CSS can
 * deepen the shadow and solidify the background once the page leaves the top.
 */
function initNavbarScroll() {
    const nav = document.querySelector('[data-navbar]');
    if (!nav) return;

    let ticking = false;
    const update = () => {
        nav.classList.toggle('is-scrolled', window.scrollY > 8);
        ticking = false;
    };

    update();
    window.addEventListener(
        'scroll',
        () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(update);
        },
        { passive: true }
    );
}

/**
 * Navigation progress bar. Forms tagged `[data-loading-form]` reveal a slim top
 * bar while the page navigation they trigger is in flight — feedback for search
 * and filter submits that reload the page.
 */
function initNavigationProgress() {
    const bar = document.getElementById('nav-progress');
    if (!bar) return;

    const start = () => bar.classList.add('is-active');

    document
        .querySelectorAll('[data-loading-form]')
        .forEach((form) => form.addEventListener('submit', start));

    // Clear the bar if the page is restored from the back/forward cache.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) bar.classList.remove('is-active');
    });
}

function init() {
    initScrollReveal();
    initCounters();
    initNavbarScroll();
    initNavigationProgress();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
