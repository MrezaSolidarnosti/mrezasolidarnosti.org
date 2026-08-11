import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Html extends Block {
    static label = 'Custom HTML';
    static keywords = ['html', 'code', 'embed', 'custom', 'raw', 'iframe'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240 80-480l240-240 57 57-184 184 183 183-56 56Zm320 0-57-57 184-184-183-183 56-56 240 240-240 240Z"/></svg>`;
    static isText = false;
    static name = 'core/html';
    static category = 'text';
    static description = 'Add custom HTML code and edit it directly.';
    element;

    render() {
        this.element = document.createElement('textarea');
        this.element.spellcheck = false;
        this.element.rows = 6;
        this.element.placeholder = Translator.translate('Enter custom HTML');
        this.#addListeners();
        return this.element;
    }

    #addListeners() {
        this.element.addEventListener('keydown', this.#handleKeydown);
    }

    #handleKeydown = (e) => {
        if (e.key === 'Backspace' && this.element.value.trim() === '') {
            e.preventDefault();
            this.destroy();
        }
    }

    getContainer() {
        return this.element;
    }

    getValue() {
        return this.element.value;
    }

    focus() {
        this.element.focus();
        const end = this.element.value.length;
        this.element.setSelectionRange(end, end);
    }

    getData() {
        return {value: this.element.value};
    }


    destroy() {
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.element.remove();
        super.destroy();
    }
}
