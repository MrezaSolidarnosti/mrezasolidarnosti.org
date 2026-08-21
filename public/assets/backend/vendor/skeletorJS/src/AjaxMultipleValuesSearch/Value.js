import {ajaxMultipleValuesSearchSelectors} from "./ajaxMultipleValuesSearchSelectors.js";
import {events} from "./events.js";

export default class Value {

    #id;
    #name;
    #container;
    #input;
    #inputName;
    #text;
    #removeValueButton;
    #eventEmitter;
    #newValue;
    constructor(id, name, inputName, eventEmitter, container = null, newValue = false) {
        this.#id = id;
        this.#name = name;
        this.#inputName = inputName;
        this.#eventEmitter = eventEmitter;
        this.#container = container;
        this.#newValue = newValue;
        if(!this.#container) {
            this.#generateView();
        }
        this.#setProperties();
        this.#addListeners();
    }

    #generateView() {
        this.#container = document.createElement('div');
        this.#container.classList.add(ajaxMultipleValuesSearchSelectors.classes.value);
        let value = this.#id;
        if(this.#newValue) {
            value = this.#name;
        }
        this.#container.innerHTML = `
            <span class="${ajaxMultipleValuesSearchSelectors.classes.text}">${this.#name}</span>
            <input type="hidden" name="${this.#inputName}" value="${value}" class="${ajaxMultipleValuesSearchSelectors.classes.input}">
            <div class="${ajaxMultipleValuesSearchSelectors.classes.removeValue}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"></path></svg>
            </div>
        `;
    }

    #setProperties() {
        this.#text = this.#container.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.text}`);
        this.#input = this.#container.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.input}`);
        this.#removeValueButton = this.#container.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.removeValue}`);
    }

    #addListeners() {
        this.#removeValueButton.addEventListener('click', this.#onRemoveValueButtonClick);
    }

    #onRemoveValueButtonClick = () => {
        this.#eventEmitter.emit(events.valueRemoved, {id: this.#id, newValue: this.#newValue});
    }

    getView() {
        return this.#container;
    }

    filter(value) {
        if(this.#name.toLowerCase().includes(value.toLowerCase())) {
            this.show();
        } else {
            this.hide();
        }
    }

    show() {
        this.#container.classList.remove(ajaxMultipleValuesSearchSelectors.classes.hidden);
    }

    hide() {
        this.#container.classList.add(ajaxMultipleValuesSearchSelectors.classes.hidden);
    }

    destroy() {
        this.#id = null;
        this.#name = null;
        this.#input = null;
        this.#inputName = null;
        this.#text = null;
        this.#removeValueButton.removeEventListener('click', this.#onRemoveValueButtonClick);
        this.#onRemoveValueButtonClick = null;
        this.#removeValueButton = null;
        this.#eventEmitter = null;
        this.#newValue = null;
        this.#container.remove();
        this.#container = null;
    }
}