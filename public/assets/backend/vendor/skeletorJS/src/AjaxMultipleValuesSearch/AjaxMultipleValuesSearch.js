import Search from "./Search.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import Value from "./Value.js";
import {ajaxMultipleValuesSearchSelectors} from "./ajaxMultipleValuesSearchSelectors.js";

export default class AjaxMultipleValuesSearch {


    #config;

    #container;

    #input;

    #searchValuesInput;

    #inputName;

    #inputNameNew = null;

    #endpoint;

    #viewColumnName;

    #valuesContainer;

    #idColumnName;

    #searchFilters = {};

    #afterEntitySelectCallback = null;

    #validateBeforeEntitySelectCallback = null;

    #search = null;

    #eventEmitter = new EventEmitter();

    #values = new Map();

    #nextAvailableNewValueId = 0;

    #newValues = new Map();

    #searchPlaceholder;

    #readOnly;

    constructor(config) {
        this.#config = config;
    }

    init() {
        try {
            this.#parseConfig();
            if(!this.#readOnly) {
                this.#addListeners();
                this.#listenToEvents();
            }
            this.#initExisting();
        } catch(e) {
            console.error(e);
        }
    }

    #parseConfig() {
        if(!this.#config.input) {
            throw new Error('Input element is required in the constructor.')
        }
        if(!this.#config.container) {
            throw new Error('Container element is required in the constructor.')
        }
        if(!this.#config.searchValuesInput) {
            throw new Error('searchValuesInput is required in the constructor.')
        }
        if(!this.#config.endpoint) {
            throw new Error('endpoint is required in the constructor.')
        }
        if(!this.#config.viewColumnName) {
            throw new Error('viewColumnName is required in the constructor.')
        }
        if(!this.#config.idColumnName) {
            throw new Error('idColumnName is required in the constructor.')
        }
        if(!this.#config.valuesContainer) {
            throw new Error('valuesContainer is required in the constructor.')
        }
        if(!this.#config.inputName) {
            throw new Error('inputName is required in the constructor.')
        }
        this.#setProperties();
    }

    #setProperties() {
        this.#input = this.#config.input;
        this.#container = this.#config.container;
        this.#searchValuesInput = this.#config.searchValuesInput;
        this.#endpoint = this.#config.endpoint;
        this.#viewColumnName = this.#config.viewColumnName;
        this.#inputName = this.#config.inputName;
        this.#idColumnName = this.#config.idColumnName;
        this.#valuesContainer = this.#config.valuesContainer;
        this.#searchPlaceholder = this.#config.searchPlaceholder ?? null;
        if(this.#config.inputNameNew) {
            this.#inputNameNew = this.#config.inputNameNew;
        }
        if(this.#config.searchFilters) {
            this.#searchFilters = this.#config.searchFilters;
        }
        if(this.#config.afterEntitySelectCallback) {
            this.#afterEntitySelectCallback = this.#config.afterEntitySelectCallback;
        }
        if(this.#config.validateBeforeEntitySelectCallback) {
            this.#validateBeforeEntitySelectCallback = this.#config.validateBeforeEntitySelectCallback;
        }
        this.#readOnly = this.#container.getAttribute(ajaxMultipleValuesSearchSelectors.attributes.readOnly);
    }

    #addListeners() {
        this.#addTriggerSearchListener();
        this.#addSearchValuesInputListener();
    }


    #addTriggerSearchListener() {
        this.#input.addEventListener('click', this.#inputClickCallback);
    }

    #inputClickCallback = () => {
        if(this.#search) {
            this.#search.destroy();
        }
        this.#search = new Search(
            this.#input,
            this.#inputNameNew,
            this.#endpoint,
            this.#viewColumnName,
            this.#idColumnName,
            this.#searchFilters,
            this.#eventEmitter,
            this.#afterEntitySelectCallback,
            this.#validateBeforeEntitySelectCallback,
            this.#searchPlaceholder
        );
        this.#search.generateSearch();
        this.#search = null;
    }

    #addSearchValuesInputListener() {
        this.#searchValuesInput.addEventListener('input', this.#searchValuesInputCallback);
    }

    #searchValuesInputCallback = () => {
        this.#values.forEach((value) => {
            value.filter(this.#searchValuesInput.value);
        });
    }

    #clearValueSearchFilter() {
        this.#searchValuesInput.value = '';
        this.#values.forEach((value) => {
            value.show();
        });
    }

    #listenToEvents() {
        this.#eventEmitter.on(events.resultClicked, ({entity, viewText}) => {
            this.#addValueToContainer(entity.id, viewText);
        });

        this.#eventEmitter.on(events.valueRemoved, ({id, newValue}) => {
            if(!newValue) {
                if (this.#values.has(id)) {
                    this.#values.get(id).destroy();
                    this.#values.delete(id);
                }
            } else {
                if (this.#newValues.has(id)) {
                    this.#newValues.get(id).destroy();
                    this.#newValues.delete(id);
                }
            }
        });

        this.#eventEmitter.on(events.newValueAdded, ({value}) => {
            this.#addNewValueToContainer(value);
        });
    }

    #addValueToContainer(id, viewText) {
        this.#clearValueSearchFilter();
        if(this.#values.has(id)) {
            return;
        }
        const value = new Value(id, viewText, this.#inputName, this.#eventEmitter);
        this.#values.set(id, value);
        this.#valuesContainer.appendChild(value.getView());

    }

    #addNewValueToContainer(viewText) {
        const id = this.#nextAvailableNewValueId++;
        const value = new Value(
            id,
            viewText,
            this.#inputNameNew,
            this.#eventEmitter,
            null,
            true
        );
        this.#newValues.set(id, value);
        this.#valuesContainer.appendChild(value.getView());
    }

    #initExisting() {
        const values = this.#valuesContainer.querySelectorAll(`.${ajaxMultipleValuesSearchSelectors.classes.value}`);
        values.forEach((value) => {
            this.initIndividual(value);
        });
    }

    initIndividual(element) {
        const id = parseInt(element.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.input}`).value);
        const viewText = element.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.text}`).innerText;
        this.#values.set(id, new Value(id, viewText, this.#inputName, this.#eventEmitter, element));
    }

    getInput() {
        return this.#input;
    }

    static generateValue(name, id, view) {
        const element = document.createElement('div');
        element.classList.add('value');
        element.innerHTML = `<span class="valueText">${view}</span>
            <input type="hidden" name="${name}" value="${id}" class="multipleValuesSearchInput">
                <div class="removeValue">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"></path></svg>
                </div>`;
        return element;
    }

    addElementValue(element) {
        this.#valuesContainer.appendChild(element);
        this.initIndividual(element);
    }
    destroy() {
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        this.#input.removeEventListener('click', this.#inputClickCallback);
        this.#input = null;
        this.#inputClickCallback = null;
        this.#inputNameNew = null;
        this.#searchValuesInput.removeEventListener('input', this.#searchValuesInputCallback);
        this.#searchValuesInput = null;
        this.#searchValuesInputCallback = null;
        this.#endpoint = null;
        this.#viewColumnName = null;
        this.#idColumnName = null;
        this.#searchFilters = null;
        this.#afterEntitySelectCallback = null;
        this.#validateBeforeEntitySelectCallback = null;
        this.#config = null;
        if(this.#search) {
            this.#search.destroy();
        }
        this.#search = null;
        this.#valuesContainer = null;
        this.#inputName = null;
        this.#values.forEach(value => {
            value.destroy();
        });
        this.#values.clear();
        this.#values = null;
        this.#nextAvailableNewValueId = null;
        this.#newValues.forEach(value => {
            value.destroy();
        });
        this.#newValues.clear();
        this.#newValues = null;
        this.#searchPlaceholder = null;
    }
}