import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Translator from "../../Translator/Translator.js";
import Overlay from "../Overlay/Overlay.js";
import Dismissible from "../Dismissible/Dismissible.js";
import LocalStorage from "../../LocalStorage/LocalStorage.js";

/**
 * Per-person editor preferences — how the canvas looks to *you*, not what the post contains.
 *
 * Nothing here is content: none of it reaches getDataForSave(), none of it is per-post. It is
 * stored in localStorage under a key the project chooses, so several editors can share one set
 * of preferences or keep their own:
 *
 *   config.contentEditor.userSettings = {key: 'myApp.contentEditor.settings'}
 *
 * Same key on the posts editor and the pages editor and a writer sets their font size once.
 * Different keys and each page remembers separately.
 *
 * A setting is a registry entry, so a project adds its own the way it adds anything else:
 *
 *   UserSettings.register({
 *       key: 'canvasTheme',                       // also the localStorage property
 *       label: 'Canvas theme',
 *       description: 'Optional line under the label.',
 *       default: 'dark',
 *       options: [
 *           {value: 'dark',  label: 'Dark'},
 *           {value: 'light', label: 'Light'},
 *       ],
 *       apply: (value, {editor}) => {
 *           editor.contentContainer.dataset.theme = value;
 *       },
 *   });
 *   UserSettings.unRegister('contentWidth');      // or drop one of the built-ins
 *
 * `apply` is the whole contract. It runs once on init with the stored value (or the default)
 * and again on every change, so a setting never needs to know whether it is being restored or
 * chosen. Settings appear in registration order; the two built-ins register at the bottom of
 * this file, so project code always lands after them.
 */
export default class UserSettings {

    // Shared across editor instances, like every other registry in the library.
    static SETTINGS = new Map();

    static DEFAULT_STORAGE_KEY = 'contentEditorUserSettings';

    static register(definition) {
        if (!definition || !definition.key) {
            throw new Error('A user setting needs a `key` — it is the property it is stored under.');
        }
        if (!Array.isArray(definition.options) || !definition.options.length) {
            throw new Error(`User setting "${definition.key}" needs at least one option.`);
        }
        // Keyed, so registering the same key twice replaces rather than duplicates — a project
        // can redefine a built-in (different labels, a third size) instead of removing it.
        UserSettings.SETTINGS.set(definition.key, {...definition});
    }

    static unRegister(key) {
        UserSettings.SETTINGS.delete(key);
    }

    #setupComplete = false;
    #dismissible = null;
    #optionListeners = [];
    #values = {};

    editor;
    eventEmitter;
    button;
    modal;
    closeButton;
    list;

    constructor({eventEmitter, editor}) {
        this.eventEmitter = eventEmitter;
        this.editor = editor;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#setElements();
        // The button is part of the app's HTML shell. No button, no settings — but the editor
        // itself carries on, exactly like a module whose DOM section is missing.
        if (!this.button || !this.modal || !this.closeButton || !this.list) {
            return;
        }
        this.#values = this.#read();
        this.#render();
        this.#applyAll();
        this.#addListeners();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.isOpen(),
            close: () => this.close(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.button = document.getElementById(contentEditorSelectors.ids.userSettingsButton);
        this.modal = document.getElementById(contentEditorSelectors.ids.userSettingsModal);
        this.closeButton = document.getElementById(contentEditorSelectors.ids.closeUserSettings);
        this.list = document.getElementById(contentEditorSelectors.ids.userSettingsList);
    }

    // Deliberately not read-only aware: these are view preferences, not edits. Someone reviewing
    // a locked post has as much reason to want larger text as the person who wrote it.
    #addListeners() {
        this.button.addEventListener('click', this.toggle);
        this.closeButton.addEventListener('click', this.close);
    }


    /* ------------------------------- Storage ------------------------------- */

    #storageKey() {
        return this.editor?.config?.userSettings?.key || UserSettings.DEFAULT_STORAGE_KEY;
    }

    // Everything lives under one key as a single object, so a project switching keys moves the
    // whole set at once and there is no orphaned per-setting entry to clean up.
    #read() {
        const stored = LocalStorage.get(this.#storageKey(), true);
        return stored && typeof stored === 'object' ? stored : {};
    }

    #write() {
        LocalStorage.set(this.#storageKey(), this.#values, true);
    }

    /**
     * The stored value if it is still one of the setting's options, otherwise the default.
     *
     * Re-validating on every read matters because the registry can change under stored data:
     * a project that renames 'large' to 'xl' would otherwise leave people on a value no button
     * represents, with nothing selected and no way back.
     */
    getValue(key) {
        const setting = UserSettings.SETTINGS.get(key);
        if (!setting) {
            return null;
        }
        const stored = this.#values[key];
        const known = setting.options.some((option) => option.value === stored);
        return known ? stored : (setting.default ?? setting.options[0].value);
    }

    setValue(key, value) {
        const setting = UserSettings.SETTINGS.get(key);
        if (!setting || !setting.options.some((option) => option.value === value)) {
            return;
        }
        this.#values[key] = value;
        this.#write();
        this.#apply(setting, value);
        this.#syncActive(key, value);
    }


    /* -------------------------------- Apply -------------------------------- */

    #apply(setting, value) {
        if (typeof setting.apply !== 'function') {
            return;
        }
        // One bad setting shouldn't stop the rest from applying — the same isolation content
        // transforms get, for the same reason: these can come from project code.
        try {
            setting.apply(value, {editor: this.editor, settings: this});
        } catch (error) {
            console.error(`ContentEditor: user setting "${setting.key}" threw while applying.`, error);
        }
    }

    #applyAll() {
        UserSettings.SETTINGS.forEach((setting) => this.#apply(setting, this.getValue(setting.key)));
    }


    /* -------------------------------- Render ------------------------------- */

    #render() {
        this.#clearOptionListeners();
        this.list.innerHTML = '';
        UserSettings.SETTINGS.forEach((setting) => this.list.appendChild(this.#renderSetting(setting)));
    }

    #renderSetting(setting) {
        const row = document.createElement('div');
        row.classList.add(contentEditorSelectors.classes.userSetting);
        row.setAttribute(contentEditorSelectors.attributes.userSettingKey, setting.key);

        const label = document.createElement('label');
        label.classList.add(contentEditorSelectors.classes.userSettingLabel);
        label.textContent = Translator.translate(setting.label || setting.key);
        row.appendChild(label);

        if (setting.description) {
            const description = document.createElement('span');
            description.classList.add(contentEditorSelectors.classes.userSettingDescription);
            description.textContent = Translator.translate(setting.description);
            row.appendChild(description);
        }

        const options = document.createElement('div');
        options.classList.add(contentEditorSelectors.classes.userSettingOptions);
        const current = this.getValue(setting.key);
        setting.options.forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add(contentEditorSelectors.classes.userSettingOption);
            button.setAttribute(contentEditorSelectors.attributes.userSettingValue, option.value);
            button.textContent = Translator.translate(option.label || option.value);
            button.classList.toggle(contentEditorSelectors.classes.userSettingOptionActive, option.value === current);
            this.#trackOption(button, () => this.setValue(setting.key, option.value));
            options.appendChild(button);
        });
        row.appendChild(options);
        return row;
    }

    // Guarded on `list` as well as on the row: setValue is public, so a project can set a
    // preference from code in a shell that has no settings markup at all.
    #syncActive(key, value) {
        const row = this.list?.querySelector(`[${contentEditorSelectors.attributes.userSettingKey}="${key}"]`);
        if (!row) {
            return;
        }
        row.querySelectorAll(`.${contentEditorSelectors.classes.userSettingOption}`).forEach((button) => {
            const active = button.getAttribute(contentEditorSelectors.attributes.userSettingValue) === value;
            button.classList.toggle(contentEditorSelectors.classes.userSettingOptionActive, active);
        });
    }

    #trackOption(element, handler) {
        element.addEventListener('click', handler);
        this.#optionListeners.push({element, handler});
    }

    #clearOptionListeners() {
        this.#optionListeners.forEach(({element, handler}) => element.removeEventListener('click', handler));
        this.#optionListeners = [];
    }


    /* -------------------------------- Popup -------------------------------- */

    toggle = () => {
        if (this.isOpen()) {
            this.close();
            return;
        }
        this.open();
    }

    open = () => {
        this.modal.classList.add(contentEditorSelectors.classes.active);
        this.button.classList.add(contentEditorSelectors.classes.active);
        Overlay.showOverlay();
    }

    close = () => {
        this.modal.classList.remove(contentEditorSelectors.classes.active);
        this.button.classList.remove(contentEditorSelectors.classes.active);
        Overlay.hideOverlay();
    }

    isOpen() {
        return !!this.modal && this.modal.classList.contains(contentEditorSelectors.classes.active);
    }

    destroy() {
        if (this.button) {
            this.button.removeEventListener('click', this.toggle);
        }
        if (this.closeButton) {
            this.closeButton.removeEventListener('click', this.close);
        }
        this.#clearOptionListeners();
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.editor = null;
        this.eventEmitter = null;
        this.#setupComplete = false;
    }
}


/* ------------------------------ Built-in settings ----------------------------- */
// Registered here rather than inside the class so project code — which always runs later — can
// replace or unRegister them. Both write to #content, the element config.width already sizes.

const CONTENT_FONT_SIZES = {medium: '1.2rem', large: '1.4rem'};
const CONTENT_WIDTHS = {medium: '70%', large: '80%'};

UserSettings.register({
    key: 'contentFontSize',
    label: 'Content font size',
    description: 'How large the text you are writing appears.',
    default: 'base',
    options: [
        {value: 'base', label: 'Base'},
        {value: 'medium', label: 'Medium'},
        {value: 'large', label: 'Large'},
    ],
    apply: (value, {editor}) => {
        const content = editor?.contentContainer;
        if (!content) {
            return;
        }
        const size = CONTENT_FONT_SIZES[value];
        // 'base' clears the inline size rather than writing one, so the stylesheet's own value
        // is what shows — whatever a project has changed it to.
        size ? content.style.fontSize = size : content.style.removeProperty('font-size');
    },
});

UserSettings.register({
    key: 'contentWidth',
    label: 'Content width',
    description: 'How wide the writing area is.',
    default: 'base',
    options: [
        {value: 'base', label: 'Base'},
        {value: 'medium', label: 'Medium'},
        {value: 'large', label: 'Large'},
    ],
    apply: (value, {editor}) => {
        const content = editor?.contentContainer;
        if (!content) {
            return;
        }
        const width = CONTENT_WIDTHS[value];
        if (width) {
            content.style.width = width;
            return;
        }
        // 'base' means "back to the project's default", which is not always the stylesheet's:
        // #applyConfigToElements writes config.width to this same inline style at init. Clearing
        // it outright would quietly discard the project's chosen width the first time anyone
        // picked Base, so it is restored rather than removed.
        const configured = editor?.config?.width;
        configured ? content.style.width = configured : content.style.removeProperty('width');
    },
});
