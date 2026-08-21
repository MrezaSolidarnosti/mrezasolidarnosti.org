import {ajaxInputSearchSelectors} from "../AjaxInputSearch/ajaxInputSearchSelectors.js";
import {events} from "./events.js";
import {crudPageSelectors} from "../Page/crudPageSelectors.js";

export default class Search {

    #input;
    #inputNameNew;
    #endpoint;
    #viewColumnName;
    #idColumnName;
    #offset = 0;
    #limit = 10;
    #container;
    #searchInput;
    #resultContainer;
    #searchFilters;
    #eventEmitter;
    #isFetching = false;
    #noMoreResults = false;
    #delaySearchInputInMs = 300;
    #timeout = null;
    #results = [];
    #searchPlaceholder;
    #validateBeforeEntitySelectCallback = () => {return true;}
    #afterEntitySelectCallback = () => {return null;}

    constructor(
        input,
        inputNameNew,
        endpoint,
        viewColumnName,
        idColumnName,
        searchFilters,
        eventEmitter,
        afterEntitySelectCallback = null,
        validateBeforeEntitySelectCallback = null,
        searchPlaceholder = null
    ) {
        this.#input = input;
        this.#inputNameNew = inputNameNew;
        this.#endpoint = endpoint;
        this.#viewColumnName = viewColumnName;
        this.#idColumnName = idColumnName;
        this.#searchFilters = searchFilters;
        this.#eventEmitter = eventEmitter;
        this.#searchPlaceholder = searchPlaceholder;
        if(afterEntitySelectCallback) {
            this.#afterEntitySelectCallback = afterEntitySelectCallback;
        }
        if(validateBeforeEntitySelectCallback) {
            this.#validateBeforeEntitySelectCallback = validateBeforeEntitySelectCallback;
        }
    }

    generateSearch() {
        const parent = this.#input.parentElement;
        if(parent) {
            this.#assembleSearchContainer();
            parent.appendChild(this.#container);
            void this.#initBehavior();
        }
    }

    #assembleSearchContainer() {
        this.#container = this.#generateContainer();
        this.#searchInput = this.#generateSearchInput();
        this.#resultContainer = this.#generateResultContainer();

        this.#container.appendChild(this.#searchInput);
        this.#container.appendChild(this.#resultContainer);
    }

    #generateContainer() {
        const container = document.createElement('div');
        container.classList.add(ajaxInputSearchSelectors.classes.searchContainer);
        return container;
    }

    #generateSearchInput() {
        const input = document.createElement('input');
        input.classList.add(ajaxInputSearchSelectors.classes.searchInput);
        input.classList.add(crudPageSelectors.classes.preventEnterSubmit);
        input.placeholder = this.#searchPlaceholder ?? 'Search';
        return input;
    }

    #generateResultContainer()
    {
        const container = document.createElement('div');
        container.classList.add(ajaxInputSearchSelectors.classes.resultContainer);
        return container;
    }

    async #initBehavior() {
        this.#searchInput.focus();
        this.#addListeners();
        await this.#loadResults();
    }

    #addListeners() {
        this.#addSearchListener();
        this.#addBlurListener();
        this.#addScrollListener();
        if(this.#inputNameNew) {
            this.#addListenerForNewValues();
        }
    }

    #addSearchListener() {
        this.#searchInput.addEventListener('input', this.#searchCallback);
    }

    #searchCallback = async () => {
        clearTimeout(this.#timeout);
        if(this.#isFetching) {
            return;
        }
        this.#timeout = setTimeout(async () => {
            await this.#loadResults();
        }, this.#delaySearchInputInMs);
    }

    #addScrollListener() {
        this.#resultContainer.addEventListener('scroll', this.#scrollCallback);
    }

    #scrollCallback = async () => {
        if(this.#isFetching || this.#noMoreResults) {
            return;
        }
        if (this.#resultContainer.offsetHeight + this.#resultContainer.scrollTop >= this.#resultContainer.scrollHeight) {
            this.#offset += this.#limit;
            this.#isFetching = true;
            await this.#loadResults(false);
            this.#isFetching = false;
        }
    };


    #addListenerForNewValues() {
        this.#searchInput.addEventListener('keyup', this.#addNewValue);
    }

    #addNewValue = (e) => {
        if(e.key === 'Enter') {
            const value = this.#searchInput.value.trim();
            if(value) {
                this.#eventEmitter.emit(events.newValueAdded, {value: value});
                this.destroy();
            }
        }
    }

    async #loadResults(cleanOldResults = true) {
        if(cleanOldResults && this.#resultContainer) {
            this.#resultContainer.innerHTML = '';
            this.#noMoreResults = false;
            this.#offset = 0;
            this.#results = [];
            this.#clearResultListeners();
        }
        this.#isFetching = true;
        const responseData = await this.#getResults();
        const fragment = document.createDocumentFragment();
        if(responseData && responseData.entities && responseData.entities.data && responseData.entities.data.length > 0) {
            responseData.entities.data.forEach((entry) => {
                const resultData = this.#generateResult(entry);
                if(resultData) {
                    this.#results.push(resultData);
                    fragment.appendChild(resultData.element);
                }
            });
        } else {
            this.#noMoreResults = true;
            this.#offset = 0;
        }
        this.#populateResults(fragment);
        this.#isFetching = false;
    }

    #populateResults(element) {
        if(this.#resultContainer) {
            this.#resultContainer.appendChild(element);
        }
    }

    async #getResults() {
        const req = await fetch(this.#endpoint, {
            method: 'POST',
            body: this.#getParamsForSearch()
        })
        return await req.json();
    }

    #getParamsForSearch() {
        const formData = new FormData();
        formData.append('limit', this.#limit.toString());
        formData.append('offset', this.#offset.toString());
        formData.append('search', `%${this.#searchInput.value.trim()}%`);
        Object.keys(this.#searchFilters).forEach((key) => {
            formData.append(`filter[${key}]`, JSON.stringify(this.#searchFilters[key]));
        });

        return formData;
    }

    #generateResult(entity) {
        if(entity.columns && entity.columns[this.#viewColumnName] && entity.columns[this.#idColumnName]) {
            let viewText = '';
            if(typeof entity.columns[this.#viewColumnName] === 'object' && entity.columns[this.#viewColumnName].value) {
                viewText = entity.columns[this.#viewColumnName].value;
            } else {
                viewText = entity.columns[this.#viewColumnName];
            }
            const result = document.createElement('div');
            result.textContent = viewText;
            result.classList.add(ajaxInputSearchSelectors.classes.result);
            const callback = this.#resultClickCallback.bind(this, entity, viewText);
            //@todo check if works on mobile
            result.addEventListener('mousedown', callback);
            return {element: result, callback: callback};
        }
        return null;
    }



    #resultClickCallback = (entity, viewText) => {
        if(!this.#validateBeforeEntitySelectCallback(entity)) {
            return;
        }
        this.#eventEmitter.emit(events.resultClicked, {entity: entity, viewText: viewText});
        this.#afterEntitySelectCallback(entity);
    }

    #addBlurListener() {
        this.#searchInput.addEventListener('blur', this.destroy);
    }

    #clearResultListeners() {
        this.#results.forEach((resultData) => {
            resultData.element.removeEventListener('mousedown', resultData.callback);
        });
    }

    destroy = () => {
        this.#searchInput.removeEventListener('input', this.#searchCallback);
        this.#searchInput.removeEventListener('blur', this.destroy);
        this.#searchInput.removeEventListener('keyup', this.#addNewValue);
        this.#resultContainer.removeEventListener('scroll', this.#scrollCallback);
        this.#clearResultListeners();
        this.#resultContainer = null;
        this.#searchInput = null;
        this.#container.remove();
        this.#container = null;
        this.#input = null;
        this.#endpoint = null;
        this.#viewColumnName = null;
        this.#idColumnName = null;
        this.#searchFilters = null;
        this.#results = null;
        this.#timeout = null;
        this.#afterEntitySelectCallback = null;
        this.#validateBeforeEntitySelectCallback = null;
        this.#addNewValue = null;
        this.#eventEmitter = null;
        this.#inputNameNew = null;
        this.#searchPlaceholder = null;
    }
}