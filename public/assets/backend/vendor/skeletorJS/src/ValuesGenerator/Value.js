import {valuesGeneratorSelector} from "./valuesGeneratorSelectors.js";
import {events} from "./events.js";

export default class Value {

    #id;
    #inputName;
    #eventEmitter;
    #value;
    #container;
    #input;
    #removeButton;
    #name;
    constructor({id, inputName, eventEmitter, value, container}) {
        this.#id = id;
        this.#inputName = inputName;
        this.#eventEmitter = eventEmitter;
        this.#value = value ?? null;
        this.#container = container ?? null;
        if(!this.#container) {
           this.#generateView();
        }
        this.#setProperties();
        this.#addListeners();
    }

    #generateView() {
        this.#container = document.createElement('div');
        this.#container.classList.add(valuesGeneratorSelector.classes.value);
        this.#container.innerHTML = `
            <input type="hidden" value='${this.#value}' />
            <span>${this.#value}</span>
            <div class="removeValue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"></path></svg></div>
        `;
    }

    #setProperties() {
        this.#input = this.#container.querySelector('input');
        this.#input.name = this.#inputName;
        this.#removeButton = this.#container.querySelector(`.${valuesGeneratorSelector.classes.removeValueButton}`);
        this.#name = this.#container.querySelector('span').textContent;
    }

    #addListeners() {
        this.#removeButton.addEventListener('click', this.#removeButtonCallback);
    }

    #removeButtonCallback = () => {
        this.#eventEmitter.emit(events.removeValue, {id: this.#id});
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

    getName() {
        return this.#name;
    }

    show() {
        this.#container.classList.remove(valuesGeneratorSelector.classes.hidden);
    }

    hide() {
        this.#container.classList.add(valuesGeneratorSelector.classes.hidden);
    }

    destroy() {
        this.#removeButton.removeEventListener('click', this.#removeButtonCallback);
        this.#removeButton = null;
        this.#removeButtonCallback = null;
        this.#input = null;
        this.#inputName = null;
        this.#eventEmitter = null;
        this.#value = null;
        this.#id = null;
        this.#name = null;
        this.#container.remove();
        this.#container = null;
    }
}