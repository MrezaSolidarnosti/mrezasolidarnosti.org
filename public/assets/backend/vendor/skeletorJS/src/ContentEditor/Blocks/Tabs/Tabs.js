import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Tabs extends Block {
    static label = 'Tabs';
    static keywords = ['tabs', 'tab', 'sections', 'switcher'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-280H200v280Zm0-360h560v-200H200v200Zm280-80h240v-80H480v80Zm-280 80v-200 200Z"/></svg>`;
    static isText = false;
    static name = 'core/tabs';
    static category = 'design';
    static description = 'Organise content into switchable tabs.';
    static DELETE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>`;
    static ADD_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"></path></svg>`;

    element;
    nav;
    panels;
    addButton;
    #nextKey = 0;   // per-tab local key linking a nav tab to its panel; survives deletes

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.tabsBlock);

        this.nav = document.createElement('div');
        this.nav.classList.add(contentEditorSelectors.classes.tabsNav);

        this.panels = document.createElement('div');
        this.panels.classList.add(contentEditorSelectors.classes.tabsPanels);

        const items = this.#resolveItems();
        const activeIndex = Math.max(0, items.findIndex((item) => item.active));
        items.forEach((item, index) => this.#appendTab(item, index === activeIndex));

        this.addButton = document.createElement('div');
        this.addButton.classList.add(contentEditorSelectors.classes.tabsAddTab);
        this.addButton.innerHTML = Tabs.ADD_ICON;
        this.addButton.title = Translator.translate('Add tab');
        this.nav.appendChild(this.addButton);   // the + always sits at the end of the tab row

        this.element.append(this.nav, this.panels);
        this.#addListeners();
        return this.element;
    }

    #resolveItems() {
        if (this.data && Array.isArray(this.data.items) && this.data.items.length) {
            return this.data.items;
        }
        // A tabs block with a single tab reads oddly, so seed two.
        return [{label: '', content: '', active: true}, {label: '', content: '', active: false}];
    }

    // A tab is two linked elements in two regions: the nav button and its panel, joined by a
    // data-tab-key rather than by index, so deleting a middle tab can't desync them.
    #appendTab(item, active) {
        const key = String(this.#nextKey++);
        this.nav.insertBefore(this.#buildTab(item, key, active), this.addButton || null);
        this.panels.appendChild(this.#buildPanel(item, key, active));
    }

    #buildTab(item, key, active) {
        const tab = document.createElement('div');
        tab.classList.add(contentEditorSelectors.classes.tabsTab);
        tab.setAttribute(contentEditorSelectors.attributes.tabKey, key);
        tab.classList.toggle(contentEditorSelectors.classes.active, active);

        // Plain-text title — deliberately NOT `.editable`, so the format toolbar stays out of
        // the tab label and only decorates the panel body (same choice as the accordion).
        const label = document.createElement('div');
        label.classList.add(contentEditorSelectors.classes.tabsLabel);
        label.contentEditable = 'true';
        label.spellcheck = false;
        label.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Tab'));
        if (item.label) {
            label.innerHTML = item.label;
        }

        const remove = document.createElement('div');
        remove.classList.add(contentEditorSelectors.classes.tabsTabDelete);
        remove.innerHTML = Tabs.DELETE_ICON;
        remove.title = Translator.translate('Remove tab');

        tab.append(label, remove);
        return tab;
    }

    #buildPanel(item, key, active) {
        const panel = document.createElement('div');
        panel.classList.add(contentEditorSelectors.classes.tabsPanel);
        panel.setAttribute(contentEditorSelectors.attributes.tabKey, key);
        panel.classList.toggle(contentEditorSelectors.classes.active, active);

        // Rich body: `editable` + living inside #content is all the format toolbar needs.
        const body = document.createElement('div');
        body.classList.add(contentEditorSelectors.classes.tabsPanelBody);
        body.classList.add(contentEditorSelectors.classes.editableBlock);
        body.contentEditable = 'true';
        body.spellcheck = false;
        body.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Tab content'));
        if (item.content) {
            body.innerHTML = item.content;
        }

        panel.appendChild(body);
        return panel;
    }

    #addListeners() {
        this.element.addEventListener('click', this.#handleClick);
        this.element.addEventListener('keydown', this.#handleKeydown);
        this.element.addEventListener('input', this.#handleInput);
    }

    // Emptying a contentEditable leaves a stray <br>, which blocks the :empty placeholder and
    // would save as markup — clear it back to truly empty.
    #handleInput = (e) => {
        const editable = e.target.closest(
            `.${contentEditorSelectors.classes.tabsLabel}, .${contentEditorSelectors.classes.tabsPanelBody}`
        );
        if (!editable) {
            return;
        }
        const html = editable.innerHTML;
        if (html === '<br>' || html === '<p><br></p>' || html === '<div><br></div>') {
            editable.innerHTML = '';
        }
    };

    #handleClick = (e) => {
        if (e.target.closest(`.${contentEditorSelectors.classes.tabsTabDelete}`)) {
            this.#deleteTab(e.target.closest(`.${contentEditorSelectors.classes.tabsTab}`));
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.tabsAddTab}`)) {
            this.#addTab();
            return;
        }
        const tab = e.target.closest(`.${contentEditorSelectors.classes.tabsTab}`);
        if (tab) {
            this.#activate(tab.getAttribute(contentEditorSelectors.attributes.tabKey));
        }
    };

    #activate(key) {
        const {active} = contentEditorSelectors.classes;
        const attr = contentEditorSelectors.attributes.tabKey;
        this.#tabs().forEach((tab) => tab.classList.toggle(active, tab.getAttribute(attr) === key));
        [...this.panels.children].forEach((panel) => panel.classList.toggle(active, panel.getAttribute(attr) === key));
    }

    #addTab() {
        const key = String(this.#nextKey);   // #appendTab consumes and increments this
        this.#appendTab({label: '', content: '', active: false}, false);
        this.#activate(key);
        this.#focusEditable(this.nav.querySelector(
            `.${contentEditorSelectors.classes.tabsTab}[${contentEditorSelectors.attributes.tabKey}='${key}'] `
            + `.${contentEditorSelectors.classes.tabsLabel}`
        ));
    }

    #deleteTab(tab) {
        if (this.#tabs().length <= 1) {
            this.destroy();
            return;
        }
        const key = tab.getAttribute(contentEditorSelectors.attributes.tabKey);
        const wasActive = tab.classList.contains(contentEditorSelectors.classes.active);
        this.#panelForKey(key)?.remove();
        const fallback = tab.previousElementSibling || tab.nextElementSibling;   // sibling to activate
        tab.remove();
        // A deleted active tab hands off to a neighbour so a panel is always showing.
        if (wasActive && fallback && fallback.classList.contains(contentEditorSelectors.classes.tabsTab)) {
            this.#activate(fallback.getAttribute(contentEditorSelectors.attributes.tabKey));
        }
    }

    // Enter in a label jumps to that tab's body; Enter/Delete in a body stays local (so the
    // editor's global new-paragraph / delete-block handlers don't fire mid-edit).
    #handleKeydown = (e) => {
        const label = e.target.closest(`.${contentEditorSelectors.classes.tabsLabel}`);
        if (label) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const key = label.closest(`.${contentEditorSelectors.classes.tabsTab}`)
                    .getAttribute(contentEditorSelectors.attributes.tabKey);
                this.#activate(key);
                this.#focusEditable(this.#panelForKey(key)?.querySelector(`.${contentEditorSelectors.classes.tabsPanelBody}`));
            }
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.tabsPanelBody}`)
            && (e.key === 'Enter' || e.key === 'Delete')) {
            if (e.key === 'Enter') {
                document.execCommand('defaultParagraphSeparator', false, 'p');
            }
            e.stopPropagation();
        }
    };

    #tabs() {
        return [...this.nav.querySelectorAll(`.${contentEditorSelectors.classes.tabsTab}`)];
    }

    #panelForKey(key) {
        return this.panels.querySelector(
            `.${contentEditorSelectors.classes.tabsPanel}[${contentEditorSelectors.attributes.tabKey}='${key}']`
        );
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
        this.#focusEditable(this.nav.querySelector(`.${contentEditorSelectors.classes.tabsLabel}`));
    }

    // Tab order is the nav order; each label is paired to its panel by key.
    getData() {
        const items = this.#tabs().map((tab) => {
            const key = tab.getAttribute(contentEditorSelectors.attributes.tabKey);
            const body = this.#panelForKey(key)?.querySelector(`.${contentEditorSelectors.classes.tabsPanelBody}`);
            return {
                label: tab.querySelector(`.${contentEditorSelectors.classes.tabsLabel}`).innerHTML,
                content: body ? body.innerHTML : '',
                active: tab.classList.contains(contentEditorSelectors.classes.active),
            };
        });
        return {items};
    }

    destroy() {
        this.element.removeEventListener('click', this.#handleClick);
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.element.removeEventListener('input', this.#handleInput);
        this.element.remove();
        super.destroy();
    }
}
