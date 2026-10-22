/* Homepage-only small-screen shortcut. Never covers the actual search form
 * or the remainder of the page; no booking depends on this enhancement.
 * With scripts blocked, the ordinary hero link stays functional. */
(() => {
    const form = document.querySelector('[data-home-search-form]');
    const shortcut = document.querySelector('[data-resavar-mobile-search-link]');
    if (!form || !shortcut) return;

    const mobile = window.matchMedia('(max-width: 460px)');
    let pending = false;
    const update = () => {
        pending = false;
        const formRect = form.getBoundingClientRect();
        const beforeSearch = formRect.top > window.innerHeight - 75;
        shortcut.hidden = !mobile.matches || !beforeSearch;
    };
    const schedule = () => {
        if (pending) return;
        pending = true;
        window.requestAnimationFrame(update);
    };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule, { passive: true });
    mobile.addEventListener?.('change', schedule);
    shortcut.addEventListener('click', () => {
        shortcut.hidden = true;
        window.setTimeout(() => {
            form.querySelector('[name="destination"]')?.focus({ preventScroll: true });
        }, 100);
    });
    update();
})();
