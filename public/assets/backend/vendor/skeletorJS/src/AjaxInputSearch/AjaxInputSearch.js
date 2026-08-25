import Search from "./Search.js";
import {ajaxInputSearchSelectors} from "./ajaxInputSearchSelectors.js";

export default class AjaxInputSearch {

    #input;

    #container;

    #targetInput;

    #endpoint;

    #viewColumnName;

    #idColumnName;

    #searchFilters = {};

    #afterEntitySelectCallback = null;

    #validateBeforeEntitySelectCallback = null;

    #config;

    #search = null;

    #searchPlaceholder;

    #removeValueButton;

    #readOnly;

    #method;

    constructor(config) {
        this.#config = config;
    }

    init() {
        try {
            this.#parseConfig();
            this.#addListeners();
        } catch(e) {
            console.error(e);
        }
    }

    setConfigProperty(property, value) {
        this.#config[property] = value;
        this.#setProperties();
    }

    #parseConfig() {
        if(!this.#config.container) {
            throw new Error('Container element is required in the constructor.')
        }
        if(!this.#config.input) {
            throw new Error('Input element is required in the constructor.')
        }
        if(!this.#config.targetInput) {
            throw new Error('targetInput is required in the constructor.')
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
        this.#setProperties();
    }

    #setProperties() {
        this.#container = this.#config.container;
        this.#removeValueButton = this.#config.container.querySelector(`.${ajaxInputSearchSelectors.classes.removeValueButton}`);
        if(!this.#removeValueButton) {
            throw new Error('Remove value button is required in the container.')
        }
        this.#input = this.#config.input;
        this.#targetInput = this.#config.targetInput;
        this.#endpoint = this.#config.endpoint;
        this.#viewColumnName = this.#config.viewColumnName;
        this.#idColumnName = this.#config.idColumnName;
        if(this.#config.searchFilters) {
            this.#searchFilters = this.#config.searchFilters;
        }
        this.#searchPlaceholder = this.#config.searchPlaceholder ?? null;
        if(this.#config.afterEntitySelectCallback) {
            this.#afterEntitySelectCallback = this.#config.afterEntitySelectCallback;
        }
        if(this.#config.validateBeforeEntitySelectCallback) {
            this.#validateBeforeEntitySelectCallback = this.#config.validateBeforeEntitySelectCallback;
        }
        this.#readOnly = this.#container.getAttribute(ajaxInputSearchSelectors.attributes.readOnly);
        this.#method = this.#config.method ?? 'POST';
    }

    #addListeners() {
        if(!this.#readOnly) {
            this.#addTriggerSearchListener();
            this.#addRemoveValueButtonListener();
        }
    }

    #addTriggerSearchListener() {
        //@todo check if works on mobile
        this.#input.addEventListener('click', this.#inputClickCallback);
    }

    #inputClickCallback = () => {
        if(this.#search) {
            this.#search.destroy();
        }
        this.#search = new Search(
            this.#input,
            this.#targetInput,
            this.#endpoint,
            this.#viewColumnName,
            this.#idColumnName,
            this.#searchFilters,
            this.#afterEntitySelectCallback,
            this.#validateBeforeEntitySelectCallback,
            this.#searchPlaceholder ?? null,
            this.#method
        );
        this.#search.generateSearch();
        this.#search = null;
    }

    #addRemoveValueButtonListener() {
        this.#removeValueButton.addEventListener('click', this.#removeValueButtonClickCallback);
    }

    #removeValueButtonClickCallback = () => {
        this.#targetInput.value = '';
        this.#targetInput.dispatchEvent(new Event('change'));
        this.#input.value = '';

    }

    getInput() {
        return this.#input;
    }

    destroy() {
        this.#input.removeEventListener('click', this.#inputClickCallback);
        this.#removeValueButton.removeEventListener('click', this.#removeValueButtonClickCallback);
        this.#container = null;
        this.#inputClickCallback = null;
        this.#removeValueButtonClickCallback = null;
        this.#input = null;
        this.#targetInput = null;
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
        this.#searchPlaceholder = null;
    }

}