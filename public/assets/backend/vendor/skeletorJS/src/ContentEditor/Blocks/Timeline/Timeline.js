import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Timeline extends Block {
    static label = 'Timeline';
    static keywords = ['timeline', 'time', 'events', 'history', 'chronology', 'schedule'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M640-120q-33 0-56.5-23.5T560-200v-160q0-33 23.5-56.5T640-440h160q33 0 56.5 23.5T880-360v160q0 33-23.5 56.5T800-120H640Zm0-80h160v-160H640v160ZM80-240v-80h360v80H80Zm560-280q-33 0-56.5-23.5T560-600v-160q0-33 23.5-56.5T640-840h160q33 0 56.5 23.5T880-760v160q0 33-23.5 56.5T800-520H640Zm0-80h160v-160H640v160ZM80-640v-80h360v80H80Zm640 360Zm0-400Z"/></svg>`;
    static isText = false;
    static name = 'core/timeline';
    static category = 'design';
    static description = 'Show a sequence of events along a timeline.';
    static DELETE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>`;
    static ADD_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/></svg>`;

    element;
    itemsContainer;
    addButton;

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.timelineBlock);

        this.itemsContainer = document.createElement('div');
        this.itemsContainer.classList.add(contentEditorSelectors.classes.timelineItems);
        this.#resolveItems().forEach((item) => this.itemsContainer.appendChild(this.#buildItem(item)));
        this.element.appendChild(this.itemsContainer);

        this.addButton = document.createElement('div');
        this.addButton.classList.add(contentEditorSelectors.classes.timelineAddItem);
        this.addButton.innerHTML = Timeline.ADD_ICON;
        this.addButton.title = Translator.translate('Add Event');
        this.element.appendChild(this.addButton);

        this.#addListeners();
        return this.element;
    }

    #resolveItems() {
        if (this.data && Array.isArray(this.data.items) && this.data.items.length) {
            return this.data.items;
        }
        return [{time: '', content: ''}];
    }

    #buildItem(data) {
        const item = document.createElement('div');
        item.classList.add(contentEditorSelectors.classes.timelineItem);

        // Plain-text time: deliberately NOT `.editable`, so the format toolbar only decorates
        // the description.
        const time = document.createElement('div');
        time.classList.add(contentEditorSelectors.classes.timelineItemTime);
        time.contentEditable = 'true';
        time.spellcheck = false;
        time.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Time'));
        if (data.time) {
            time.innerHTML = data.time;
        }

        // The dot + connecting line are drawn from this element in CSS.
        const marker = document.createElement('div');
        marker.classList.add(contentEditorSelectors.classes.timelineItemMarker);

        // Rich description: `editable` + inside #content is all the format toolbar needs to
        // enable bold / italic / underline / link here.
        const content = document.createElement('div');
        content.classList.add(contentEditorSelectors.classes.timelineItemContent);
        content.classList.add(contentEditorSelectors.classes.editableBlock);
        content.contentEditable = 'true';
        content.spellcheck = false;
        content.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Description'));
        if (data.content) {
            content.innerHTML = data.content;
        }

        const remove = document.createElement('div');
        remove.classList.add(contentEditorSelectors.classes.timelineItemDelete);
        remove.innerHTML = Timeline.DELETE_ICON;
        remove.title = Translator.translate('Remove');

        item.append(time, marker, content, remove);
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
            `.${contentEditorSelectors.classes.timelineItemTime}, .${contentEditorSelectors.classes.timelineItemContent}`
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
        if (e.target.closest(`.${contentEditorSelectors.classes.timelineItemDelete}`)) {
            this.#deleteItem(e.target.closest(`.${contentEditorSelectors.classes.timelineItem}`));
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.timelineAddItem}`)) {
            this.#addItem();
        }
    }

    #addItem() {
        const item = this.#buildItem({time: '', content: ''});
        this.itemsContainer.appendChild(item);
        this.#focusEditable(item.querySelector(`.${contentEditorSelectors.classes.timelineItemTime}`));
    }

    #deleteItem(item) {
        if (this.#items().length <= 1) {
            this.destroy();
        }
        item.remove();
    }

    // Delegated so any number of items is covered by a single listener. stopPropagation
    // keeps the editor's global Enter/Delete handlers (new paragraph after the block /
    // delete the block) from firing while a time or description is being edited.
    #handleKeydown = (e) => {
        const time = e.target.closest(`.${contentEditorSelectors.classes.timelineItemTime}`);
        if (time) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const item = time.closest(`.${contentEditorSelectors.classes.timelineItem}`);
                this.#focusEditable(item.querySelector(`.${contentEditorSelectors.classes.timelineItemContent}`));
            }
            if (e.key === 'Delete') {
                e.stopPropagation();
            }
            return;
        }
        if (e.target.closest(`.${contentEditorSelectors.classes.timelineItemContent}`)
            && (e.key === 'Enter' || e.key === 'Delete')) {
            if (e.key === 'Enter') {
                // Browsers default to wrapping new lines in <div>; make them <p> instead.
                document.execCommand('defaultParagraphSeparator', false, 'p');
            }
            e.stopPropagation();
        }
    }

    #items() {
        return [...this.itemsContainer.querySelectorAll(`.${contentEditorSelectors.classes.timelineItem}`)];
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
        this.#focusEditable(this.itemsContainer.querySelector(`.${contentEditorSelectors.classes.timelineItemTime}`));
    }

    getData() {
        const items = this.#items().map((item) => ({
            time: item.querySelector(`.${contentEditorSelectors.classes.timelineItemTime}`).innerHTML,
            content: item.querySelector(`.${contentEditorSelectors.classes.timelineItemContent}`).innerHTML
        }));
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
