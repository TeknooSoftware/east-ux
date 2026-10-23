import { Controller } from '@hotwired/stimulus';

/*
 * Theme controller, set on <html> by `east_ux_theme_attributes()`: switches the style (legacy, modern) and the mode
 * (light, dark, auto) of the System.css x Bootstrap theme without reloading the page, with the rules of East UX's
 * `ThemeService`:
 * - the class `system-modern` on <html> for the modern style,
 * - `data-bs-theme="dark"` for the dark mode, and for the auto mode when the browser prefers a dark color scheme,
 *   `data-bs-theme="light"` for the light modern theme, no attribute for the light legacy theme.
 *
 * The choice is kept in the localStorage with the `browser` storage, and sent as JSON to the `url` value when it is
 * set (the endpoint of East UX with the `session` storage, or an endpoint of the application). The event
 * `theme:changed` is dispatched on <html> after each change, with the style and the mode in its detail.
 *
 * The `option` targets are the buttons of the switchers, with the `style` or `mode` param of their `select` action:
 * they get the class `active` and `aria-pressed="true"` when their value is the current one. The `menuOption` targets
 * are the entries of a menu (`role="menuitemradio"`, added to the menu bar by `ThemeMenuGenerator`): they get
 * `aria-checked="true"`, drawn as a check mark by `east-ux.css`.
 */
const STYLES = ['legacy', 'modern'];
const MODES = ['light', 'dark', 'auto'];

export default class extends Controller {
    static targets = ['option', 'menuOption'];

    static values = {
        style: { type: String, default: 'legacy' },
        mode: { type: String, default: 'auto' },
        storage: { type: String, default: 'session' },
        storageKey: { type: String, default: 'east-ux-theme' },
        url: String,
        modernClass: { type: String, default: 'system-modern' },
        switchingClass: { type: String, default: 'east-ux-theme-switching' },
    };

    connect() {
        this.media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        this.boundMediaChanged = this.mediaChanged.bind(this);
        this.media?.addEventListener('change', this.boundMediaChanged);

        if (this.isBrowserStorage()) {
            this.readStorage();
            this.boundStorageChanged = this.storageChanged.bind(this);
            window.addEventListener('storage', this.boundStorageChanged);
        }

        this.apply(false);
    }

    disconnect() {
        this.media?.removeEventListener('change', this.boundMediaChanged);

        if (this.boundStorageChanged) {
            window.removeEventListener('storage', this.boundStorageChanged);
            this.boundStorageChanged = null;
        }
    }

    // Options rendered after the connection (a dropdown or a Live Component re-rendered) are updated too
    optionTargetConnected(option) {
        this.updateOption(option);
    }

    menuOptionTargetConnected(option) {
        this.updateMenuOption(option);
    }

    select(event) {
        const { style, mode } = event.params;

        this.change(
            STYLES.includes(style) ? style : this.styleValue,
            MODES.includes(mode) ? mode : this.modeValue,
        );
    }

    toggleStyle() {
        this.change('modern' === this.styleValue ? 'legacy' : 'modern', this.modeValue);
    }

    // From the auto mode, switches to the opposite of the current appearance
    toggleMode() {
        this.change(this.styleValue, this.isDark() ? 'light' : 'dark');
    }

    change(style, mode) {
        if (style === this.styleValue && mode === this.modeValue) {
            return;
        }

        this.styleValue = style;
        this.modeValue = mode;

        this.apply(true);
        this.persist();
        this.dispatch('changed', { detail: { style, mode } });
    }

    isBrowserStorage() {
        return 'browser' === this.storageValue;
    }

    isDark() {
        return 'dark' === this.modeValue || ('auto' === this.modeValue && !!this.media?.matches);
    }

    apply(switching) {
        const root = this.element;

        // No color fade while the theme changes (rule of `east-ux.css`)
        if (switching) {
            root.classList.add(this.switchingClassValue);
        }

        root.classList.toggle(this.modernClassValue, 'modern' === this.styleValue);
        if (this.isDark()) {
            root.setAttribute('data-bs-theme', 'dark');
        } else if ('modern' === this.styleValue) {
            root.setAttribute('data-bs-theme', 'light');
        } else {
            root.removeAttribute('data-bs-theme');
        }

        this.optionTargets.forEach((option) => this.updateOption(option));
        this.menuOptionTargets.forEach((option) => this.updateMenuOption(option));

        if (switching) {
            // Applies the new styles without transition before removing the class
            void root.offsetWidth;
            requestAnimationFrame(() => root.classList.remove(this.switchingClassValue));
        }
    }

    // An option is current when the value of its `style` or `mode` param is the current one
    isCurrent(option) {
        const style = option.getAttribute(`data-${this.identifier}-style-param`);
        const mode = option.getAttribute(`data-${this.identifier}-mode-param`);

        return (null !== style && style === this.styleValue) || (null !== mode && mode === this.modeValue);
    }

    updateOption(option) {
        const active = this.isCurrent(option);

        option.classList.toggle('active', active);
        option.setAttribute('aria-pressed', String(active));
    }

    updateMenuOption(option) {
        option.setAttribute('aria-checked', String(this.isCurrent(option)));
    }

    persist() {
        if (this.isBrowserStorage()) {
            try {
                localStorage.setItem(`${this.storageKeyValue}:style`, this.styleValue);
                localStorage.setItem(`${this.storageKeyValue}:mode`, this.modeValue);
            } catch (error) {
                console.error('Failed to keep the theme: ', error);
            }
        }

        if (this.hasUrlValue && this.urlValue) {
            fetch(this.urlValue, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ style: this.styleValue, mode: this.modeValue }),
            }).then((response) => {
                if (!response.ok) {
                    console.error('Failed to save the theme: HTTP ' + response.status);
                }
            }).catch((error) => {
                console.error('Failed to save the theme: ', error);
            });
        }
    }

    readStorage() {
        try {
            const style = localStorage.getItem(`${this.storageKeyValue}:style`);
            const mode = localStorage.getItem(`${this.storageKeyValue}:mode`);

            if (STYLES.includes(style)) {
                this.styleValue = style;
            }

            if (MODES.includes(mode)) {
                this.modeValue = mode;
            }
        } catch (error) {
            // localStorage unavailable (private browsing, disabled storage): the rendered theme is kept
        }
    }

    // Another tab of the application changed the theme
    storageChanged(event) {
        if (event.key === `${this.storageKeyValue}:style` || event.key === `${this.storageKeyValue}:mode`) {
            this.readStorage();
            this.apply(true);
        }
    }

    mediaChanged() {
        if ('auto' === this.modeValue) {
            this.apply(true);
        }
    }
}
