/**
 * Cross-tab sync for time blocks.
 *
 * The dashboard, history page, and any other view that lists time blocks
 * read from localStorage key 'chrono.timeBlocks.v1' and re-render on the
 * 'chrono:blocks:changed' window event. That event is dispatched only by
 * the writing tab. Without this bridge, a category toggle in tab A
 * (dashboard) wouldn't reflect in tab B (history page) until the user
 * manually refreshed B.
 *
 * The browser's 'storage' event fires in every tab EXCEPT the one that
 * wrote — perfect signal for "another tab updated our cache". When that
 * key changes, we re-broadcast 'chrono:blocks:changed' so any listener
 * in this tab catches up.
 *
 * Lives in a JS module (rather than inline in the Blade layout) so
 * auto-formatters and merge tooling can't strip it.
 */
(() => {
    const KEY = 'chrono.timeBlocks.v1';

    window.addEventListener('storage', (e) => {
        if (e.key !== KEY) return;
        // Re-broadcast so the page-specific render() / updateAll()
        // listeners pick up the cross-tab change without a refresh.
        window.dispatchEvent(new CustomEvent('chrono:blocks:changed'));
    });
})();
