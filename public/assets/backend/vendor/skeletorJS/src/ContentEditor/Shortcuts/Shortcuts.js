import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Overlay from "../Overlay/Overlay.js";
import Dismissible from "../Dismissible/Dismissible.js";
import Platform from "../../Platform/Platform.js";
import Translator from "../../Translator/Translator.js";
export default class Shortcuts {

    #setupComplete = false;
    #dismissible = null;
    #generated = [];   // rows added by the last populate — removed before the next one
    shortcuts = [];
    sequences = [];   // typed triggers ([[…]], @…) — listed, but not Shortcut key-combos
    selectionShortcuts = [];   // block-selection combos, handled by the block manager
    shortcutsButton;
    shortcutsContainer;
    closeShortcutsButton;
    registerShortcut(shortcut) {
        this.shortcuts.push(shortcut);
    }

    // A typed sequence rather than a modifier+key combo (e.g. an entity trigger). Shown in its
    // own section of the panel; it drives no keybinding here — the owner handles behaviour.
    // `sequence` may be a string, or a `() => string` for a value that can change at runtime
    // (the command menu's trigger): the panel re-reads it each time it opens.
    registerSequence(sequence, description) {
        this.sequences.push({sequence, description});
    }

    // A block-selection combo. Like a sequence it is listed only — the block manager owns
    // the behaviour in its own keydown/mousedown handling, and several of these involve the
    // mouse, so there is no Shortcut to bind. `keys` is the chips to show, in order:
    // registerSelectionShortcut(['Shift', 'Click'], 'Select a range of blocks').
    registerSelectionShortcut(keys, description) {
        this.selectionShortcuts.push({keys: Array.isArray(keys) ? keys : [keys], description});
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#populateShortcutsContainer();
        this.#addListeners();
        this.shortcuts.forEach((shortcut) => {
            shortcut.addConstraint(() => {
               return !Overlay.isShown();
            });
            shortcut.init();
        });
        this.#dismissible = Dismissible.register({
            isOpen: () => this.shortcutsContainer.classList.contains(contentEditorSelectors.classes.active),
            close: () => this.#toggleShortcuts(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.shortcutsButton = document.getElementById(contentEditorSelectors.ids.shortcutsButton);
        this.shortcutsContainer = document.getElementById(contentEditorSelectors.ids.shortcutsContainer);
        this.closeShortcutsButton = document.getElementById(contentEditorSelectors.ids.closeShortcuts);
    }

    // Rebuild the listing from scratch — called on init and again each time the panel opens, so
    // live values (the command menu's trigger) reflect any runtime change.
    #refresh() {
        this.#generated.forEach((element) => element.remove());
        this.#generated = [];
        this.#populateShortcutsContainer();
    }

    #populateShortcutsContainer() {
        const add = (element) => {
            this.shortcutsContainer.appendChild(element);
            this.#generated.push(element);
        };
        if(this.shortcuts.length === 0 && this.sequences.length === 0
            && this.selectionShortcuts.length === 0) {
            add(this.#generateNoShortcutsNotice());
            return;
        }
        this.shortcuts.forEach((shortcut) => {
           add(this.#generateShortcutDescriptionElement(shortcut));
        });

        if(this.sequences.length > 0) {
            add(this.#generateSectionTitle(Translator.translate('Text triggers')));
            this.sequences.forEach((sequence) => {
                add(this.#generateSequenceElement(sequence));
            });
        }

        if(this.selectionShortcuts.length > 0) {
            add(this.#generateSectionTitle(Translator.translate('Selection')));
            this.selectionShortcuts.forEach((selectionShortcut) => {
                add(this.#generateSelectionShortcutElement(selectionShortcut));
            });
        }
    }

    // Same row shape as a shortcut, but with any number of chips: a selection combo can be
    // one key (Esc), a modifier plus a key (Ctrl + D), or a modifier plus the mouse
    // (Shift + Click). The chips are joined by the same '+' separator.
    #generateSelectionShortcutElement({keys, description}) {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.shortcutContainer);
        keys.forEach((key, index) => {
            if(index > 0) {
                const plus = document.createElement('span');
                plus.classList.add(contentEditorSelectors.classes.shortcutPlus);
                plus.textContent = '+';
                container.appendChild(plus);
            }
            const keyElement = document.createElement('span');
            // The leading chip is the modifier, so it picks up the modifier styling.
            keyElement.classList.add(index === 0 && keys.length > 1
                ? contentEditorSelectors.classes.shortcutModifier
                : contentEditorSelectors.classes.shortcutKey);
            // Modifier names become their symbol on a Mac (⇧, ⌘, …); plain keys and "Click"
            // pass through unchanged.
            keyElement.textContent = Platform.modifierSymbol(key);
            container.appendChild(keyElement);
        });
        const descriptionElement = document.createElement('p');
        descriptionElement.classList.add(contentEditorSelectors.classes.shortcutDescription);
        descriptionElement.textContent = Translator.translate(description || '');
        container.appendChild(descriptionElement);
        return container;
    }

    #generateSectionTitle(text) {
        const title = document.createElement('h2');
        title.classList.add(contentEditorSelectors.classes.shortcutsSectionTitle);
        title.textContent = text;
        return title;
    }

    // Same row shape as a shortcut, minus the modifier/plus: the sequence sits in the key
    // chip, followed by its description.
    #generateSequenceElement({sequence, description}) {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.shortcutContainer);
        const key = document.createElement('span');
        key.classList.add(contentEditorSelectors.classes.shortcutKey);
        // Resolve a live sequence (a function) so a changed trigger shows on the next open.
        key.textContent = typeof sequence === 'function' ? sequence() : sequence;
        const descriptionElement = document.createElement('p');
        descriptionElement.classList.add(contentEditorSelectors.classes.shortcutDescription);
        descriptionElement.textContent = Translator.translate(description || '');
        container.append(key, descriptionElement);
        return container;
    }

    #generateShortcutDescriptionElement(shortcut) {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.shortcutContainer);
        const modifier = document.createElement('span');
        modifier.classList.add(contentEditorSelectors.classes.shortcutModifier);
        // On a Mac the app's "Ctrl" binding is really Cmd, so show ⌘ (and ⌥/⇧ for Alt/Shift).
        modifier.textContent = Platform.modifierSymbol(shortcut.getModifier());
        const plus = document.createElement('span');
        plus.classList.add(contentEditorSelectors.classes.shortcutPlus);
        plus.textContent = '+';
        const key = document.createElement('span');
        key.classList.add(contentEditorSelectors.classes.shortcutKey);
        key.textContent = shortcut.getKey();
        const description = document.createElement('p');
        description.classList.add(contentEditorSelectors.classes.shortcutDescription);
        description.textContent = Translator.translate(shortcut.getDescription());

        container.append(modifier);
        container.append(plus);
        container.append(key);
        container.append(description);
        return container;
    }

    #generateNoShortcutsNotice() {
        const container = document.createElement('span');
        container.textContent = Translator.translate('No shortcuts registered.');
        return container;
    }

    #addListeners() {
        this.shortcutsButton.addEventListener('click', this.#toggleShortcuts);
        this.closeShortcutsButton.addEventListener('click', this.#toggleShortcuts);
    }

    #toggleShortcuts = () => {
        const active = this.shortcutsContainer.classList.toggle(contentEditorSelectors.classes.active);
        this.shortcutsButton.classList.toggle(contentEditorSelectors.classes.active);
        if (active) {
            this.#refresh();   // re-read live values (the command trigger) on each open
        }
        active ? Overlay.showOverlay() :  Overlay.hideOverlay();
    }

    destroy() {
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.shortcuts.forEach((shortcut) => {
           shortcut.destroy();
        });
        this.shortcuts = [];
        this.sequences = [];
        this.selectionShortcuts = [];
        this.shortcutsButton.removeEventListener('click', this.#toggleShortcuts);
        this.closeShortcutsButton.removeEventListener('click', this.#toggleShortcuts);
        this.shortcutsButton = null;
        this.shortcutsContainer = null;
    }
}