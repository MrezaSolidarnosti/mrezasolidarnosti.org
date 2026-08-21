import {events} from "./events.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";


export default class Author {

    container;
    eventEmitter;
    input;
    nameElement;
    id;
    name;
    readOnly;
    #setupComplete = false;
    constructor({container, eventEmitter, readOnly}) {
        this.container = container;
        this.eventEmitter = eventEmitter;
        this.readOnly = readOnly;
    }


    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#setProperties();
        if(!this.input) {
            return;
        }
        this.#addListeners();
        this.#setupComplete = true;
    }

    #setElements() {
        this.input = this.container.querySelector('input');
        this.nameElement = this.container.querySelector('span');
    }

    hide() {
        this.container.classList.add(contentEditorSelectors.classes.hidden);
    }

    show() {
        this.container.classList.remove(contentEditorSelectors.classes.hidden);
    }

    isSelected() {
        return this.input.checked;
    }

    nameIncludes(val) {
        return this.name.toLowerCase().trim().includes(val.toLowerCase().trim());
    }

    #setProperties() {
        this.id = this.input.value;
        this.name = this.nameElement.textContent;
    }

    #addListeners() {
        if(this.readOnly) {
            this.input.disabled = true;
            return;
        }
        this.input.addEventListener('change', this.#handleCheckboxChange);
    }

    check() {
        this.input.checked = true;
        this.eventEmitter.emit(events.authorCheckboxChanged, {checked: this.input.checked, author: this});
    }

    getId() {
        return parseInt(this.id, 10);
    }

    #handleCheckboxChange = () => {
        this.eventEmitter.emit(events.authorCheckboxChanged, {checked: this.input.checked, author: this});
    }

    destroy() {
        if(this.input) {
            this.input.removeEventListener('change', this.#handleCheckboxChange);
        }
    }
}