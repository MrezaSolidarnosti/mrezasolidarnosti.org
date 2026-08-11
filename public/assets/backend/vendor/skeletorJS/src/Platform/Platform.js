/**
 * Small platform helper — OS detection and keyboard-modifier labels, so UI can show `⌘` on a
 * Mac where it would show `Ctrl` elsewhere. No dependencies; safe to import anywhere.
 *
 * Detection is memoised on first use and reads the most reliable source available
 * (`navigator.userAgentData.platform`, then `navigator.platform`, then the UA string). Every
 * label method also takes an optional `mac` override — handy for tests, SSR, or honouring a
 * user preference — defaulting to the detected value.
 */
export default class Platform {

    static #isMac = null;

    static isMac() {
        if (Platform.#isMac === null) {
            Platform.#isMac = /mac|iphone|ipad|ipod/i.test(Platform.#source());
        }
        return Platform.#isMac;
    }

    static isWindows() {
        return /win/i.test(Platform.#source());
    }

    /**
     * Whether the *primary* pointer is a finger — i.e. a phone or tablet rather than a desktop.
     *
     * Not memoised: this can genuinely change (a tablet docked to a mouse), so it's read live.
     * `(pointer: coarse)` asks about the primary input, so a touchscreen laptop driven by a
     * trackpad correctly reports `false` — use it instead of feature-detecting a touch API,
     * which desktops also satisfy.
     */
    static isTouch() {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return false;
        }
        return window.matchMedia('(pointer: coarse)').matches;
    }

    static isLinux() {
        const source = Platform.#source();
        return /linux|x11/i.test(source) && !/android/i.test(source);
    }

    // The modifier the app binds as "Ctrl" (it matches Ctrl-or-Cmd) — shown as `⌘` on a Mac.
    static primaryModifier(mac = Platform.isMac()) {
        return mac ? '⌘' : 'Ctrl';
    }

    // A modifier name (`Ctrl`, `Alt`, `Shift`, `Meta`, …) as its platform label. On a Mac that
    // is the symbol; elsewhere the name is returned unchanged. Anything unrecognised (a plain
    // key like `K`, or `Click`) passes straight through, so it is safe to map every chip.
    static modifierSymbol(name, mac = Platform.isMac()) {
        if (!mac) {
            return name;
        }
        return Platform.#MAC_SYMBOLS[name] || name;
    }

    static #MAC_SYMBOLS = Object.freeze({
        Ctrl: '⌘', Cmd: '⌘', Command: '⌘', Meta: '⌘',
        Alt: '⌥', Option: '⌥',
        Shift: '⇧',
    });

    static #source() {
        if (typeof navigator === 'undefined' || !navigator) {
            return '';
        }
        const uaData = navigator.userAgentData;
        return (uaData && uaData.platform) || navigator.platform || navigator.userAgent || '';
    }
}
