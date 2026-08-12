import Child from "./Child.js";
import {events} from "./events.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {elementRelationSelectors} from "./elementRelationSelectors.js";

export default class Parent {

    #removeButton;
    #createChildButton;
    #childrenContainer;
    #children = [];
    #baseName;
    #nextAvailableId = 0;
    #inputs = {};
    #inputEventListeners = [];
    #eventEmitter = new EventEmitter();
    #config;
    #id;
    #parentEventEmitter;
    #container;
    constructor(config, id, parentEventEmitter, container = null) {
        this.#config = config;
        this.#id = id;
        this.#parentEventEmitter = parentEventEmitter;
        this.#container = container;
        this.#setProperties();
        this.#addListeners();
        if(container) {
            this.#initExisting();
        }
        this.#parentEventEmitter.emit(events.parentCreated, {id: this.#id, inputs: this.#getInputs()});
    }

    #getInputs() {
        const inputs = [];
        Object.keys(this.#inputs).forEach((name) => {
            inputs.push({name: name, input: this.#inputs[name]});
        });
        return inputs;
    }

    #setProperties() {
        if (this.#container === null) {
            const HTMLString = this.#config.parent;
            const parser = new DOMParser();
            const HTML = parser.parseFromString(HTMLString, 'text/html');
            this.#container = HTML.body.firstChild;
        }
        this.#removeButton = this.#container.querySelector('.removeParent');
        this.#createChildButton = this.#container.querySelector('.createChild');
        this.#childrenContainer = this.#container.querySelector('.childrenContainer');
        this.#baseName = this.#container.getAttribute(elementRelationSelectors.attributes.dataBase);
        const childrenWithDataName = Array.from(this.#container.querySelectorAll(`[data-name]`)).filter(element => {
            let isInsideChildrenContainer = false;
            let currentElement = element.parentElement;
            while (currentElement !== null) {
                if (currentElement.classList.contains(elementRelationSelectors.classes.childrenContainer)) {
                    isInsideChildrenContainer = true;
                    break;
                }
                currentElement = currentElement.parentElement;
            }
            return !isInsideChildrenContainer;
        });
        childrenWithDataName.forEach((input) => {
            const name = input.getAttribute(elementRelationSelectors.attributes.dataName);
            if (name) {
                this.#setInput(name, input);
            }
        });
    }

    #setInput(name, input) {
        input.name = `${this.#baseName}[${this.#id}][${name}]`;
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
        this.#createChildButton.addEventListener('click', this.#createChild);
        this.#eventEmitter.on(events.childDestroyed, (data) => {
            this.#parentEventEmitter.emit(events.childDestroyed, data);
            delete this.#children[data.id];
        });
        this.#eventEmitter.on(events.childCreated, (data) => {
            this.#parentEventEmitter.emit(events.childCreated, data);
        });
    }

    #initExisting() {
        this.#container.querySelectorAll(`.${elementRelationSelectors.classes.child}`).forEach((child) => {
            const newChild = new Child(this.#config, this.#id, this.#baseName, this.#nextAvailableId++, this.#eventEmitter, child);
            this.#children.push(newChild);
        });
    }

    #createChild = () => {
        const child = new Child(this.#config, this.#id, this.#baseName, this.#nextAvailableId++, this.#eventEmitter);
        this.#children.push(child);
        this.#childrenContainer.appendChild(child.getView());
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
        this.#children.forEach((child) => {
            child.destroy();
        });
        this.#removeButton.removeEventListener('click', this.destroy);
        this.#createChildButton.removeEventListener('click', this.#createChild);
        this.#removeButton = null;
        this.#createChildButton = null;
        this.#childrenContainer = null;
        this.#config = null;
        this.#container.remove();
        this.#container = null;
        this.#children = null;
        this.#baseName = null;
        this.#nextAvailableId = 0;
        this.#parentEventEmitter.emit(events.parentDestroyed, {id: this.#id, inputs: this.#getInputs()});
        this.#inputs = null;
        this.#id = null;
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        this.#parentEventEmitter = null;
    }

    getView() {
        return this.#container;
    }
}