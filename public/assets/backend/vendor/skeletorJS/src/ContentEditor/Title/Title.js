import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";
import Translator from "../../Translator/Translator.js";

export default class Title extends BaseModule {

    #setupComplete = false;
    titleInput = null;


    init() {
        if (this.#setupComplete) {
            return;
        }
        this.titleInput = document.getElementById(contentEditorSelectors.ids.title);
        if (!this.titleInput) {
            return;
        }
        this.#addEventListeners();
        this.#refreshPlaceholder();
        this.#setupComplete = true;
    }

    #addEventListeners() {
        if(this.isReadOnly()) {
            this.titleInput.removeAttribute('contentEditable');
            return;
        }
        this.titleInput.addEventListener('input', this.#handleInput);
        this.titleInput.addEventListener('keydown', this.#handleKeyDown);
        this.titleInput.addEventListener('paste', this.#handlePaste);
        this.titleInput.addEventListener('focus', this.#handleFocus);
        this.titleInput.addEventListener('mousedown', this.#handleMouseDown);
        this.titleInput.addEventListener('blur', this.#handleBlur);
    }

    #handleMouseDown = (e) => {
        if (this.#getPlaceholder()) {
            e.preventDefault();
            this.titleInput.focus();
            this.#caretToStart();
        }
    }

    #handleInput = () => {
        this.#refreshPlaceholder();
        this.eventEmitter.emit(events.titleInput, {titleObj: this});
    }

    #handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            // The title is single-line, so Enter never inserts a newline — instead it moves on
            // to the body, the same as pressing it at the end of a paragraph.
            e.preventDefault();
            this.eventEmitter.emit(events.titleEnter, {titleObj: this});
        }
    }

    #handlePaste = (e) => {
        e.preventDefault();
        const clipboard = e.clipboardData || window.clipboardData;
        const text = clipboard.getData('text/plain').replace(/[\r\n]+/g, ' ');
        document.execCommand('insertText', false, text);
        this.#refreshPlaceholder();
        this.eventEmitter.emit(events.titlePaste, {titleObj: this});
    }

    #handleFocus = () => {
        if (this.#getPlaceholder()) {
            this.#caretToStart();
        }
        this.eventEmitter.emit(events.titleFocus, {titleObj: this});
    }

    #handleBlur = () => {
        this.eventEmitter.emit(events.titleBlur, {titleObj: this});
    }

    #refreshPlaceholder() {
        const placeholder = this.#getPlaceholder();
        const empty = this.#realText() === '';
        if (empty && !placeholder) {
            this.#addPlaceholder();
        } else if (!empty && placeholder) {
            placeholder.remove();
        }
    }

    #getPlaceholder() {
        return document.getElementById(contentEditorSelectors.ids.titlePlaceholder);
    }

    #realText() {
        const placeholder = this.#getPlaceholder();
        let text = '';
        this.titleInput.childNodes.forEach((node) => {
            if (node !== placeholder) {
                text += node.textContent;
            }
        });
        return text.trim();
    }

    #addPlaceholder() {
        const wasFocused = document.activeElement === this.titleInput;
        this.titleInput.innerHTML = '';
        const placeholder = document.createElement('span');
        placeholder.id = contentEditorSelectors.ids.titlePlaceholder;
        placeholder.setAttribute('contenteditable', 'false');
        placeholder.style.pointerEvents = 'none';
        placeholder.textContent = Translator.translate(this.titleInput.getAttribute(contentEditorSelectors.attributes.dataPlaceholder) || '');
        this.titleInput.appendChild(placeholder);
        if (wasFocused) {
            this.#caretToStart();
        }
    }

    #caretToStart() {
        const range = document.createRange();
        range.setStart(this.titleInput, 0);
        range.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    getValue() {
        return this.titleInput ? this.#realText() : '';
    }

    setValue(value) {
        if (this.titleInput) {
            this.titleInput.textContent = value || '';
            this.#refreshPlaceholder();
        }
    }

    focus() {
        this.titleInput.focus();
    }

    destroy() {
        super.destroy();
        if (this.titleInput) {
            this.titleInput.removeEventListener('input', this.#handleInput);
            this.titleInput.removeEventListener('keydown', this.#handleKeyDown);
            this.titleInput.removeEventListener('paste', this.#handlePaste);
            this.titleInput.removeEventListener('focus', this.#handleFocus);
            this.titleInput.removeEventListener('mousedown', this.#handleMouseDown);
            this.titleInput.removeEventListener('blur', this.#handleBlur);
        }
        this.#handleInput = null;
        this.#handleKeyDown = null;
        this.#handlePaste = null;
        this.titleInput = null;
        this.#setupComplete = false;
    }
}
