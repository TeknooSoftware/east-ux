/*
 * Wait cursor of the windows opened by `window-dynamic-loader` and `form-window-dynamic-loader`, from the click until
 * the window is shown: <html> carries `data-east-ux-waiting`, the number of the windows being opened, and the rule of
 * `east-ux.css` shows the `wait` cursor on the whole page. A module imported by the two loaders: its waits are shared
 * by all their controllers, also by a controller disconnected during its wait (a row re-rendered by Live).
 */
const WAITING_ATTRIBUTE = 'data-east-ux-waiting';
// A wait without end signal (a Live render that never answers, a window that does not dispatch `window:opened`, a
// followed link that does not leave the page) ends after this delay
const WAITING_TIMEOUT_MS = 10000;

// The ends of the waits in progress
const waits = new Set();

// Begins a wait; the returned function ends it, once: call it, or add it as a listener of `window:opened` on the window
export function beginWait() {
    const root = document.documentElement;
    const end = () => {
        if (!waits.delete(end)) {
            return;
        }

        clearTimeout(timeout);
        if (0 < waits.size) {
            root.setAttribute(WAITING_ATTRIBUTE, String(waits.size));

            return;
        }

        root.removeAttribute(WAITING_ATTRIBUTE);
    };
    const timeout = setTimeout(end, WAITING_TIMEOUT_MS);

    waits.add(end);
    root.setAttribute(WAITING_ATTRIBUTE, String(waits.size));

    return end;
}

// Ends all the waits in progress: called by the loaders on `pagehide`, so a page left during an opening (a followed
// link) goes into the back-forward cache without the wait cursor and without its timers
export function endWaits() {
    waits.forEach((end) => end());
}
