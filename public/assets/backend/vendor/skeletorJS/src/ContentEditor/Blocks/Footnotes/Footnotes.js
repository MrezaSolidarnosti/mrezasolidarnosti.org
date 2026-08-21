import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Footnotes extends Block {
    static label = 'Footnotes';
    static keywords = ['footnotes', 'notes', 'references', 'citations'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-240h560v-400H200v400Zm0 80v80h560v-80H200Zm0 0v80-80Z"/></svg>`;
    static isText = false;
    static name = 'core/footnotes';
    static category = 'text';
    static description = 'A list of footnotes, kept in sync with the markers in your text.';
    static hidden = true; // auto-created/removed by the footnotes controller; not user-insertable

    element;
    list;

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.footnotesBlock);

        this.list = document.createElement('ol');
        this.list.classList.add(contentEditorSelectors.classes.footnotesList);

        const items = (this.data && Array.isArray(this.data.items)) ? this.data.items : [];
        items.forEach((item, index) => this.list.appendChild(this.#buildItem(item.id, item.html, index + 1)));

        this.element.appendChild(this.list);
        return this.element;
    }

    // Each item: a clickable back-link (the number, filled by the controller) + the
    // editable note text. The controller renumbers/reorders on load.
    #buildItem(id, html, number) {
        const li = document.createElement('li');
        li.classList.add(contentEditorSelectors.classes.footnotesItem);
        li.setAttribute(contentEditorSelectors.attributes.footnoteId, id);

        const backlink = document.createElement('a');
        backlink.classList.add(contentEditorSelectors.classes.footnotesBacklink);
        backlink.contentEditable = 'false';
        backlink.setAttribute(contentEditorSelectors.attributes.footnoteId, id);
        backlink.textContent = `${number}`;

        const content = document.createElement('div');
        content.classList.add(contentEditorSelectors.classes.footnotesContent);
        content.contentEditable = 'true';
        content.spellcheck = false;
        content.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Write the footnote...'));
        if (html) {
            content.innerHTML = html;
        }

        li.append(backlink, content);
        return li;
    }

    getListElement() {
        return this.list;
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    getData() {
        const items = [...this.list.children].map((li) => {
            const content = li.querySelector(`.${contentEditorSelectors.classes.footnotesContent}`);
            return {
                id: li.getAttribute(contentEditorSelectors.attributes.footnoteId),
                html: content ? content.innerHTML : '',
            };
        });
        return {items};
    }

    destroy() {
        this.element.remove();
        super.destroy();
    }
}
