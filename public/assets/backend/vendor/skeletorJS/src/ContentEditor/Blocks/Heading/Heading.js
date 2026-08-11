import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";

export default class Heading extends Block {
    static label = 'Heading 1';
    static keywords = ['heading', 'title', 'h1'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-280v-400h80v160h160v-160h80v400h-80v-160H280v160h-80Zm480 0v-320h-80v-80h160v400h-80Z"/></svg>`;
    static name = 'core/heading';
    static category = 'text';
    static description = 'Introduce a new section with a top-level heading.';
    static isText = true;
    static tags = ['h1'];
    element;

    render() {
        this.element = document.createElement('h1');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        this.element.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, slashCommandPlaceholder(this.eventEmitter));
        this.addListeners();
        return this.element;
    }

    addListeners() {
        this.element.addEventListener('input', this.#handleOnInput);
        this.element.addEventListener('keydown', this.#handleKeydown);
    }

    #handleOnInput = () => {
        if(this.element.innerHTML === '<br>') {
            this.element.innerHTML = '';
        }
        if(this.element.textContent === '/') {
            this.renderBLockMenu(this.element);
        }
    }

    #handleKeydown = (e) => {
        if(e.key === 'Backspace' && this.element.textContent.trim() === '') {
            e.preventDefault();
            this.destroy();
        }
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
        if (window.getSelection && document.createRange) {
            const range = document.createRange();
            range.selectNodeContents(this.element);
            range.collapse(false); // false collapses the range to the end of the node

            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    getData() {
        return {html: this.element.innerHTML};
    }


    destroy() {
        this.element.removeEventListener('input', this.#handleOnInput);
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.element.remove();
        super.destroy();
    }
}