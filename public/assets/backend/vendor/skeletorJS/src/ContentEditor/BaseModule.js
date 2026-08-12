export default class BaseModule {

    eventEmitter;
    // The editor's config (`config.contentEditor`), whole rather than a slice — a module reads
    // the key it owns. Empty object for a module built outside a full editor.
    config;
    readOnly = false;

    /**
     * A subclass that declares its own constructor must forward the whole bag —
     * `constructor(options) { super(options); }` — or better, not declare one at all. A
     * constructor that only passes its arguments through is what JavaScript does by default,
     * and one that destructures a fixed list silently drops anything added here later.
     */
    constructor({eventEmitter, config = {}}) {
        this.eventEmitter = eventEmitter;
        this.config = config;
    }

    setReadOnly(value) {
        this.readOnly = value;
    }

    isReadOnly() {
        return this.readOnly;
    }

    destroy() {
        this.eventEmitter = null;
        this.config = null;
    }
}