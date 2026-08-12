import {events} from "./events.js";
import {elementRelationSelectors} from "./elementRelationSelectors.js";

export default class Child {

    #removeButton;
    #container;
    #inputs = {};
    #inputEventListeners = [];
    #config;
    #parentId;
    #parentBaseName;
    #id;
    #parentEventEmitter;
    #baseName;
    constructor(config, parentId, parentBaseName, id, parentEventEmitter, container = null) {
        this.#config = config;
        this.#container = container;
        this.#parentId = parentId;
        this.#parentBaseName = parentBaseName;
        this.#id = id;
        this.#parentEventEmitter = parentEventEmitter;
        this.#setProperties();
        this.#addListeners();
        this.#parentEventEmitter.emit(events.childCreated, {id: this.#id, inputs: this.#getInputs()});
    }

    #getInputs() {
        const inputs = [];
        Object.keys(this.#inputs).forEach((name) => {
            inputs.push({name: name, input: this.#inputs[name]});
        });
        return inputs;
    }

    #setProperties() {
        if(this.#container === null) {
            const HTMLString = this.#config.child;
            const parser = new DOMParser();
            const HTML = parser.parseFromString(HTMLString, 'text/html');
            this.#container = HTML.body.firstChild;
        }
        this.#removeButton = this.#container.querySelector('.removeChild');
        this.#baseName = this.#container.getAttribute(elementRelationSelectors.attributes.dataBase);
        this.#container.querySelectorAll(`[data-name]`).forEach((input) => {
            const name = input.getAttribute(elementRelationSelectors.attributes.dataName);
            if(name) {
                this.#setInput(name, input);
            }
        });
    }

    #setInput(name, input) {
        input.name = `${this.#parentBaseName}[${this.#parentId}][${this.#baseName}][${this.#id}][${name}]`;
        const callbackConfigData = this.#getCallbackDataFromConfig(name);
        if(callbackConfigData) {
            if (callbackConfigData.callbackData
                && callbackConfigData.callbackData.event
                && callbackConfigData.callbackData.callback) {
                const callbackFunction = callbackConfigData.callbackData.callback;
                const eventListenerFunction = (event) => {
                    callbackFunction(event, this.#inputs);
                };
                input.addEventListener(callbackConfigData.callbackData.event, eventListenerFunction);
                this.#inputEventListeners.push({name, eventListenerFunction});
            }
        }
        this.#inputs[name] = input;
    }

    #getCallbackDataFromConfig(name) {
        return this.#config.callbacks.find((callback) => callback.target === name);
    }

    #addListeners() {
        this.#removeButton.addEventListener('click', this.destroy);
    }

    getView() {
        return this.#container;
    }

    destroy = () => {
        this.#inputEventListeners.forEach((inputEventListener) => {
            const callbackDataFromConfig = this.#getCallbackDataFromConfig(inputEventListener.name);
            if(callbackDataFromConfig) {
                if (callbackDataFromConfig.callbackData
                    && callbackDataFromConfig.callbackData.event
                    && callbackDataFromConfig.callbackData.callback) {
                    const event = callbackDataFromConfig.callbackData.event;
                    this.#inputs[inputEventListener.name].removeEventListener(event, inputEventListener.eventListenerFunction);
                }
            }
        });
        this.#inputEventListeners = null;
        this.#removeButton.removeEventListener('click', this.destroy);
        this.#config = null;
        this.#parentId = null;
        this.#parentBaseName = null;
        this.#removeButton = null;
        this.#container.remove();
        this.#container = null;
        this.#parentEventEmitter.emit(events.childDestroyed, {id: this.#id, inputs: this.#getInputs()});
        this.#inputs = null;
        this.#id = null;
        this.#parentEventEmitter = null;
    }
}