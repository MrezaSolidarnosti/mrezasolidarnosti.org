export default class Shortcut {

    static MODIFIERS = {
        ALT: 'Alt',
        SHIFT: 'Shift',
        CTRL: 'Ctrl',
    }

    #container;
    #key;
    #description;
    #callback;
    #modifier;
    #event;
    #preventDefault;
    constraints = [];

    constructor({container, modifier = Shortcut.MODIFIERS.ALT, key, description, callback, constraints = [], event = 'keyup', preventDefault = false}) {
        this.#container = container;
        this.#modifier = modifier;
        this.#key = key;
        this.#description = description;
        this.#callback = callback;
        this.constraints = constraints;
        this.#event = event;
        this.#preventDefault = preventDefault;
    }

    init() {
        this.#addListener();
    }

    #addListener() {
        document.addEventListener(this.#event, this.#handleKey);
    }

    #handleKey = (e) => {
        const withinContainer = this.#container.classList.contains('active') || this.#container.contains(document.activeElement);
        if(!withinContainer || !this.#keyMatches(e)) {
            return;
        }
        if(!this.#modifierMatches(e) || !this.#passesConstraints()) {
            return;
        }
        if(this.#preventDefault) {
            e.preventDefault();
        }
        this.#callback();
    }

    // Matches on e.key, plus e.code for single letters. On macOS the Option (Alt) key turns
    // e.key into an alternate character (Option+F -> "ƒ"), so e.key would never equal "F" —
    // but e.code stays "KeyF" regardless of modifier or layout.
    #keyMatches(e) {
        const key = this.#key.toUpperCase();
        if(e.key.toUpperCase() === key) {
            return true;
        }
        return /^[A-Z]$/.test(key) && e.code === `Key${key}`;
    }

    #modifierMatches(e) {
        switch(this.#modifier) {
            case Shortcut.MODIFIERS.ALT:
                return e.altKey;
            case Shortcut.MODIFIERS.SHIFT:
                return e.shiftKey;
            case Shortcut.MODIFIERS.CTRL:
                return (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey;
            default:
                return false;
        }
    }

    #passesConstraints() {
        let passed = true;
        this.constraints.forEach((constraint) => {
           if(!passed) {
               return;
           }
           passed = constraint();
        });
        return passed;
    }

    getCallback() {
        return this.#callback;
    }

    getContainer() {
        return this.#container;
    }

    getKey() {
        return this.#key;
    }

    getDescription() {
        return this.#description;
    }

    getModifier() {
        return this.#modifier;
    }

    addConstraint(constraint) {
        this.constraints.push(constraint);
    }

    getConstraints() {
        return this.constraints;
    }

    destroy() {
        document.removeEventListener(this.#event, this.#handleKey);
        this.#callback = null;
        this.constraints = [];
    }
}
