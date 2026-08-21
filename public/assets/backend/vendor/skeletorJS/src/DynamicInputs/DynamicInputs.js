import {dynamicInputsSelectors} from "./dynamicInputsSelectors.js";
import Input from "./Input.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";

export default class DynamicInputs {

    #container;
    #baseInputName;
    #inputsContainer;
    #addButton;
    #config;
    #inputData = [];
    #eventEmitter = new EventEmitter();
    #readOnly;
    #nextAvailableId = 0;
    constructor({container, baseInputName, config}) {
        this.#container = container;
        this.#baseInputName = baseInputName;
        this.#config = config;
    }

    init() {
        try {
            this.#setProperties();
            this.#addListeners();
            this.#initExisting();
            this.#listenToEvents();
        } catch(e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#inputsContainer = this.#container.querySelector(`.${dynamicInputsSelectors.classes.inputsContainer}`);
        this.#addButton = this.#container.querySelector(`.${dynamicInputsSelectors.classes.addButton}`);
        this.#readOnly = this.#container.getAttribute(dynamicInputsSelectors.attributes.readOnly) === 'true';
        if(!this.#inputsContainer) {
            throw new Error(`${dynamicInputsSelectors.classes.inputsContainer} not found in ${this.#container}`);
        }
        if(!this.#addButton && !this.#readOnly) {
            throw new Error(`${dynamicInputsSelectors.classes.addButton} not found in ${this.#container}`);
        }
        if(!this.#config) {
            throw new Error('config is required for the DynamicInputs component');
        }
    }

    #addListeners() {
        if(!this.#readOnly) {
            this.#addButton.addEventListener('click', this.#handleAddButtonClick);
        }
    }

    #handleAddButtonClick = () => {
        this.addInput();
    }

    addInput(data = null) {
        const wrapper = document.createElement('div');
        wrapper.classList.add(dynamicInputsSelectors.classes.inputsWrapper);
        const inputData = {
            inputs: [],
            wrapper: wrapper,
            removeButton: null,
            callback: null,
        };
        this.#config.inputs.forEach(inputConfig => {
            const inputInstance = new Input(inputConfig, `${this.#baseInputName}[${this.#nextAvailableId}]`);
            const input = inputInstance.getView();
            if(data) {
                const inputElement = input.querySelector(`.${dynamicInputsSelectors.classes.input}`);
                const inputName = inputElement.name;
                if (data[inputName] || data[inputName] === 0) {
                    inputElement.value = data[inputName];
                }
            }
            wrapper.appendChild(input);
            inputData.inputs.push(input);
        });
        inputData.removeButton = document.createElement('div');
        inputData.removeButton.classList.add(dynamicInputsSelectors.classes.removeButton);
        inputData.removeButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg>`;
        const callback = () => {
            this.#eventEmitter.emit(events.inputReadyForDelete, inputData);
            inputData.removeButton.removeEventListener('click', callback);
            wrapper.remove();
        }
        inputData.removeButton.addEventListener('click', callback);
        inputData.callback = callback;

        wrapper.appendChild(inputData.removeButton);
        this.#inputsContainer.appendChild(wrapper);
        this.#inputData.push(inputData);
        this.#nextAvailableId++;
    }

    #initExisting() {
        const existingWrappers = this.#inputsContainer.querySelectorAll(`.${dynamicInputsSelectors.classes.inputsWrapper}`);
        existingWrappers.forEach(wrapper => {
            const inputs = wrapper.querySelectorAll(`.${dynamicInputsSelectors.classes.input}`);
            const removeButton = wrapper.querySelector(`.${dynamicInputsSelectors.classes.removeButton}`);

            let callback = null;
            if(!this.#readOnly) {
                callback = () => {
                    removeButton.removeEventListener('click', callback);
                    wrapper.remove();
                }
                removeButton.addEventListener('click', callback);
            }
            this.#inputData.push({
                wrapper: wrapper,
                inputs: inputs,
                removeButton: removeButton,
                callback: callback,
            });
            this.#nextAvailableId++;
        });
    }

    #listenToEvents() {
        this.#eventEmitter.on(events.inputReadyForDelete, (inputData) => {
            this.#inputData = this.#inputData.filter(data => data.wrapper !== inputData.wrapper);
        });
    }

    removeAllInputs() {
        this.#inputData.forEach(data => {
            if(!this.#readOnly) {
                data.removeButton.removeEventListener('click', data.callback);
            }
            data.wrapper.remove();
        });
        this.#inputData = [];
    }

    getInputs() {
        return this.#inputData.map(data => data.inputs);
    }

    getAddButton() {
        return this.#addButton;
    }

    destroy() {
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        if(!this.#readOnly) {
            this.#addButton.removeEventListener('click', this.#handleAddButtonClick);
        }
        this.#inputData.forEach(data => {
            if(!this.#readOnly) {
                data.removeButton.removeEventListener('click', data.callback);
                data.wrapper.remove();
            }
        });
        this.#inputData = null;
        this.#container = null;
        this.#inputsContainer = null;
        this.#addButton = null;
        this.#config = null;
        this.#handleAddButtonClick = null;
    }
}