import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";
import BlockMenu from "../Components/BlockMenu/BlockMenu.js";

export default class Paragraph extends Block {
    static label = 'Paragraph';
    static keywords = ['p', 'text', 'body', 'paragraph'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M360-160v-240q-83 0-141.5-58.5T160-600q0-83 58.5-141.5T360-800h360v80h-80v560h-80v-560H440v560h-80Z"/></svg>`;
    static isText = true;
    static name = 'core/paragraph'
    static category = 'text';
    static description = 'Start with the basic building block of all narrative.';
    static tags = ['p'];
    element;
    render() {
        this.element = document.createElement('p');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        this.element.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, slashCommandPlaceholder(this.eventEmitter));
        this.#addListeners();
        return this.element;
    }

    #addListeners() {
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
        if(BlockMenu.isOpen() && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            return;
        }
        if(e.key === 'Backspace' && this.element.textContent.trim() === '') {
            e.preventDefault();
            this.destroy();
        }
    }

    getContainer() {
        return this.element;
    }

    focus() {
        if(!this.element.isConnected) return;
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