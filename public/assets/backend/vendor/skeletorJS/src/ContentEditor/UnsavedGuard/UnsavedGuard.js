import {events} from "../events.js";

/**
 * Warns before leaving with unsaved work — closing the tab, reloading, or navigating away.
 *
 * "Unsaved" is decided by diffing the editor's own save payload against a baseline, not by a
 * change flag. That is deliberate: a change flag hung off the blocks' contentChanged signal
 * would miss the modules (categories, authors, tags, status, SEO …), which live outside the
 * block content and never emit it. getDataForSave() already aggregates blocks + every enabled
 * module, so diffing it covers all of them — and any module added later — for free.
 *
 * The baseline is captured from getDataForSave() *after load*, not from initialContent. The
 * two are different shapes (minted ids, additionalData:{}, default-filled fields, key order),
 * so they don't stringify-match even untouched — and initialContent can't advance on save.
 * Baselining from the editor's own serializer compares like with like: only real edits differ.
 */
export default class UnsavedGuard {

    #setupComplete = false;
    #eventEmitter;
    #getData;
    #baseline = null;   // null until the first capture — dirty is false while unbaselined
    #enabled;

    constructor({eventEmitter, getData, enabled = true}) {
        this.#eventEmitter = eventEmitter;
        this.#getData = getData;
        this.#enabled = enabled;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        // Baseline once the initial content has rendered (the zero-edit state), and again
        // after every save — a save makes the current state the new clean state.
        this.#eventEmitter.on(events.contentEditorFinalize, this.#capture);
        this.#eventEmitter.on(events.afterSave, this.#capture);
        window.addEventListener('beforeunload', this.#handleBeforeUnload);
        this.#setupComplete = true;
    }

    // Turn the prompt off or on. Off can be the starting state (config.unsavedGuard: false) or
    // a momentary silence around an intentional navigation (a save that redirects). Only the
    // prompt is gated — baselining continues regardless, so flipping it back on mid-session
    // reflects the true dirty state rather than a stale one.
    setEnabled(enabled) {
        this.#enabled = enabled;
    }

    isEnabled() {
        return this.#enabled;
    }

    /**
     * Treat the current state as saved. The guard already re-baselines on `afterSave`, so this
     * is for work that lands outside that event — an app-owned save endpoint, a draft restored
     * programmatically, or content swapped in after a load. Without it the editor stays dirty
     * against a baseline that no longer reflects what the backend holds.
     */
    markClean() {
        this.#capture();
    }

    isDirty() {
        if (this.#baseline === null) {
            return false;   // nothing to compare against yet
        }
        return this.#serialize() !== this.#baseline;
    }

    #capture = () => {
        const serialized = this.#serialize();
        if (serialized !== null) {
            this.#baseline = serialized;
        }
    };

    #serialize() {
        try {
            return JSON.stringify(this.#getData());
        } catch (e) {
            // Never let the guard throw — a serialization hiccup shouldn't trap the user or
            // fire a spurious prompt. Report no change.
            return null;
        }
    }

    #handleBeforeUnload = (e) => {
        if (!this.#enabled || !this.isDirty()) {
            return;   // let the unload proceed silently
        }
        // preventDefault + returnValue is what triggers the browser's own (unstylable,
        // fixed-wording) "Leave site?" dialog. The text can't be customised.
        e.preventDefault();
        e.returnValue = '';
    };

    destroy() {
        window.removeEventListener('beforeunload', this.#handleBeforeUnload);
        this.#eventEmitter = null;
        this.#getData = null;
        this.#baseline = null;
        this.#setupComplete = false;
    }
}
