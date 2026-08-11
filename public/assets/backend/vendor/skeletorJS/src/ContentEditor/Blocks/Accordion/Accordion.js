import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";

export default class Accordion extends Block {
    static label = 'Accordion';
    static keywords = ['accordion', 'toggle', 'collapse', 'details', 'faq', 'expand'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80 300-260l58-58 122 122 122-122 58 58L480-80ZM358-598l-58-58 180-180 180 180-58 58-122-122-122 122Z"/></svg>`;
    static isText = false;
    static name = 'core/accordion';
    static category = 'design';
    static description = 'Add collapsible sections of content.';
    static TOGGLE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-345 240-585l43-43 197 197 197-197 43 43-240 240Z"/></svg>`;
    static DELETE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>`;
    static ADD_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/></svg>`;

    static DEFAULT_SETTINGS = Object.freeze({
        allowMultiple: true,
        firstItemOpen: true,
    });
    static advancedSidebarOpen = false;

    element;
    itemsContainer;
    addButton;
    settings;
    #sidebarModule = null;
    #allowMultipleInput = null;
    #firstItemOpenInput = null;

    render() {
        this.settings = Accordion.resolveSettings(this.data && this.data.settings);
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.accordionBlock);

        this.itemsContainer = document.createElement('div');
        this.itemsContainer.classList.add(contentEditorSelectors.classes.accordionItems);
        this.#resolveItems().forEach((item) => this.itemsContainer.appendChild(this.#buildItem(item)));
        this.element.appendChild(this.itemsContainer);

        this.addButton = document.createElement('div');
        this.addButton.classList.add(contentEditorSelectors.classes.accordionAddItem);
        this.addButton.innerHTML = `${Accordion.ADD_ICON}<span>Add item</span>`;
        this.element.appendChild(this.addButton);

        this.#applyOpenState();
        this.#addListeners();
        return this.element;
    }

    // Unknown keys are ignored and missing ones fall back, so a payload written before these
    // settings existed still opens with the behaviour it had.
    static resolveSettings(settings) {
        if (!settings || typeof settings !== 'object') {
            return {...Accordion.DEFAULT_SETTINGS};
        }
        return {
            allowMultiple: settings.allowMultiple !== false,
            firstItemOpen: settings.firstItemOpen !== false,
        };
    }

    /**
     * Reconciles the per-item `open` flags with the two block-level settings, which can disagree
     * — a saved accordion may have three items open under a setting that now allows one.
     *
     * `allowMultiple: false` keeps the first open item and closes the rest, rather than closing
     * everything: the author opened those deliberately, so the first one is the least surprising
     * survivor. `firstItemOpen` then only applies when nothing is open at all, so it seeds the
     * initial state without fighting a deliberate "all closed".
     */
    #applyOpenState() {
        const active = contentEditorSelectors.classes.active;
        const items = this.#items();
        if (!items.length) {
            return;
        }
        if (!this.settings.allowMultiple) {
            let seen = false;
            items.forEach((item) => {
                const open = item.classList.contains(active);
                if (open && !seen) {
                    seen = true;
                    return;
                }
                item.classList.remove(active);
            });
        }
        const anyOpen = items.some((item) => item.classList.contains(active));
        if (!anyOpen && this.settings.firstItemOpen) {
            items[0].classList.add(active);
        }
    }

    #resolveItems() {
        if (this.data && Array.isArray(this.data.items) && this.data.items.length) {
            return this.data.items;
        }
        return [{summary: '', content: '', open: true}];
    }

    #buildItem(data) {
        const item = document.createElement('div');
        item.classList.add(contentEditorSelectors.classes.accordionItem);
        if (data.open !== false) {
            item.classList.add(contentEditorSelectors.classes.active);
        }

        const header = document.createElement('div');
        header.classList.add(contentEditorSelectors.classes.accordionHeader);

        // Plain-text title: deliberately NOT `.editable`, so the format toolbar stays out
        // of the title and only decorates the body.
        const summary = document.createElement('div');
        summary.classList.add(contentEditorSelectors.classes.accordionSummary);
        summary.contentEditable = 'true';
        summary.spellcheck = false;
        summary.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Accordion title'));
        if (data.summary) {
            summary.innerHTML = data.summary;
        }

        const toggle = document.createElement('div');
        toggle.classList.add(contentEditorSelectors.classes.accordionToggle);
        toggle.innerHTML = Accordion.TOGGLE_ICON;
        toggle.title = Translator.translate('Toggle');

        const remove = document.createElement('div');
        remove.classList.add(contentEditorSelectors.classes.accordionItemDelete);
        remove.innerHTML = Accordion.DELETE_ICON;
        remove.title = Translator.translate('Remove');

        header.append(summary, toggle, remove);

        // Rich body: `editable` + living inside #content is all the format toolbar needs
        // to enable bold / italic / underline / link here.
        const body = document.createElement('div');
        body.classList.add(contentEditorSelectors.classes.accordionBody);
        body.classList.add(contentEditorSelectors.classes.editableBlock);
        body.contentEditable = 'true';
        body.spellcheck = false;
        body.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Accordion content'));
        if (data.content) {
            body.innerHTML = data.content;
        }

        item.append(header, body);
        return item;
    }

    #addListeners() {
        this.element.addEventListener('click', this.#handleClick);
        this.element.addEventListener('keydown', this.#handleKeydown);
        this.element.addEventListener('input', this.#handleInput);
    }

    // Emptying a contentEditable leaves a stray <br> (or <p><br></p>), which blocks the
    // :empty placeholder and would save as markup — clear it back to truly empty.
    #handleInput = (e) => {
        const editable = e.target.closest(
            `.${contentEditorSelectors.classes.accordionSummary}, .${contentEditorSelectors.classes.accordionBody}`
        );
        if (!editable) {
            return;
        }
        const html = editable.innerHTML;
        if (html === '<br>' || html === '<p><br></p>' || html === '<div><br></div>') {
            editable.innerHTML = '';
        }
    }

    #handleClick = (e) => {
        if (e.target.closest(`.${contentEditorSelectors.classes.accordionToggle}`)) {
            const item = e.target.closest(`.${contentEditorSelectors.classes.accordionItem}`);
            const active = contentEditorSelectors.classes.active;
            const opening = !item.classList.contains(active);
            // Closing is always allowed; only opening has to close the siblings, and only when
            // the block is set to one-at-a-time.
            if (opening && !this.settings.allowMultiple) {
                this.#items().forEach((other) => other.classList.remove(active));
            }
            item.classList.toggle(active, opening);
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.accordionItemDelete}`)) {
            this.#deleteItem(e.target.closest(`.${contentEditorSelectors.classes.accordionItem}`));
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.accordionAddItem}`)) {
            this.#addItem();
        }
    }

    #addItem() {
        const item = this.#buildItem({summary: '', content: '', open: true});
        this.itemsContainer.appendChild(item);
        this.#focusEditable(item.querySelector(`.${contentEditorSelectors.classes.accordionSummary}`));
    }

    #deleteItem(item) {
        if (this.#items().length <= 1) {
            this.destroy();
            return;
        }
        item.remove();
    }

    // Delegated so any number of items is covered by a single listener. stopPropagation
    // keeps the editor's global Enter/Delete handlers (new paragraph after the block /
    // delete the block) from firing while a title or body is being edited.
    #handleKeydown = (e) => {
        const summary = e.target.closest(`.${contentEditorSelectors.classes.accordionSummary}`);
        if (summary) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const item = summary.closest(`.${contentEditorSelectors.classes.accordionItem}`);
                item.classList.add(contentEditorSelectors.classes.active);
                this.#focusEditable(item.querySelector(`.${contentEditorSelectors.classes.accordionBody}`));
            }
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.accordionBody}`)
            && (e.key === 'Enter' || e.key === 'Delete')) {
            if (e.key === 'Enter') {
                // Browsers default to wrapping new lines in <div>; make them <p> instead.
                document.execCommand('defaultParagraphSeparator', false, 'p');
            }
            e.stopPropagation();
        }
    }

    #items() {
        return [...this.itemsContainer.querySelectorAll(`.${contentEditorSelectors.classes.accordionItem}`)];
    }

    #focusEditable(editable) {
        if (!editable || !editable.isConnected) {
            return;
        }
        editable.focus();
        const range = document.createRange();
        range.selectNodeContents(editable);
        range.collapse(false);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.#focusEditable(this.itemsContainer.querySelector(`.${contentEditorSelectors.classes.accordionSummary}`));
    }

    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.#sidebarModule) {
            this.#sidebarModule = SidebarSection.generate(
                Translator.translate('Accordion Settings'),
                contentEditorSelectors.ids.accordionSidebar,
                this.eventEmitter,
                true
            );
            const content = this.#sidebarModule.container
                .querySelector(`#${contentEditorSelectors.ids.accordionSidebar}`);

            this.#allowMultipleInput = this.#buildToggle(
                'allowMultiple', 'Allow multiple sections open at once', content
            );
            this.#firstItemOpenInput = this.#buildToggle(
                'firstItemOpen', 'Open the first section by default', content
            );
            this.sidebarContainer.prepend(this.#sidebarModule.container);
        }
        return this.sidebarContainer;
    }

    #buildToggle(key, labelText, content) {
        const wrapper = document.createElement('div');
        wrapper.classList.add(contentEditorSelectors.classes.inputContainer);

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.classList.add(contentEditorSelectors.classes.input);
        input.checked = this.settings[key] !== false;
        input.addEventListener('change', this.#handleSettingChange);
        input.setAttribute(contentEditorSelectors.attributes.accordionSettings, key);

        const label = document.createElement('label');
        label.append(input, document.createTextNode(' ' + Translator.translate(labelText)));
        wrapper.appendChild(label);
        content.appendChild(wrapper);
        return input;
    }

    #handleSettingChange = (e) => {
        const key = e.target.getAttribute(contentEditorSelectors.attributes.accordionSettings);
        this.settings[key] = e.target.checked;
        // Applied straight away so the editor shows what the setting means: switching multiple
        // off with three sections open should close two of them there and then, not on reload.
        this.#applyOpenState();
        this.#commit();
    }

    /**
     * The open/closed classes the settings drive are watched by the observer (childList and
     * attributes are both in scope for a class change), but flipping a setting that changes
     * nothing on screen — turning `allowMultiple` back on — moves no DOM at all. Mirroring the
     * settings onto an attribute makes every change visible to history and the unsaved guard.
     */
    #commit() {
        this.element.setAttribute(
            contentEditorSelectors.attributes.accordionSettings,
            JSON.stringify(this.settings)
        );
    }

    destroySidebar() {
        this.#allowMultipleInput?.removeEventListener('change', this.#handleSettingChange);
        this.#firstItemOpenInput?.removeEventListener('change', this.#handleSettingChange);
        this.#allowMultipleInput = null;
        this.#firstItemOpenInput = null;
        this.#sidebarModule = null;
        super.destroySidebar();
    }

    getData() {
        const items = this.#items().map((item) => ({
            summary: item.querySelector(`.${contentEditorSelectors.classes.accordionSummary}`).innerHTML,
            content: item.querySelector(`.${contentEditorSelectors.classes.accordionBody}`).innerHTML,
            open: item.classList.contains(contentEditorSelectors.classes.active)
        }));
        return {items, settings: {...this.settings}};
    }

    destroy() {
        this.#allowMultipleInput?.removeEventListener('change', this.#handleSettingChange);
        this.#firstItemOpenInput?.removeEventListener('change', this.#handleSettingChange);
        this.element.removeEventListener('click', this.#handleClick);
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.element.removeEventListener('input', this.#handleInput);
        this.element.remove();
        super.destroy();
    }
}
