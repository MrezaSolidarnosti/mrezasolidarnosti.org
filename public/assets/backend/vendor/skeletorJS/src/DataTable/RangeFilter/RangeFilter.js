import {rangeFilterSelectors} from "./rangeFilterSelectors.js";
import {events} from "./events.js";

export class RangeFilter {

    #button;
    #container;
    #closeButton;
    #applyButton;
    #clearButton;
    #isOpen = false;
    #fromInput;
    #toInput;
    #type;
    #lastFromValue;
    #lastToValue;
    eventEmitter;
    constructor(button, eventEmitter) {
        this.#button = button;
        this.eventEmitter = eventEmitter;
        this.#setProperties();
    }

    #setProperties() {
        this.#type = this.#button.getAttribute(rangeFilterSelectors.attributes.rangeFilterType);
        this.#container = this.#button.querySelector(`.${rangeFilterSelectors.classes.rangeFilterContainer}`);
        if(!this.#container) {
            throw new Error(`${rangeFilterSelectors.classes.rangeFilterContainer} not found`);
        }
        this.#closeButton = this.#container.querySelector(`.${rangeFilterSelectors.classes.closeButton}`);
        if(!this.#closeButton) {
            throw new Error(`${rangeFilterSelectors.classes.closeButton} not found`);
        }
        this.#applyButton = this.#container.querySelector(`.${rangeFilterSelectors.classes.applyButton}`);
        if(!this.#applyButton) {
            throw new Error(`${rangeFilterSelectors.classes.applyButton} not found`);
        }
        this.#fromInput = this.#container.querySelector(`.${rangeFilterSelectors.classes.fromInput}`);
        if(!this.#fromInput) {
            throw new Error(`${rangeFilterSelectors.classes.fromInput} not found`);
        }
        this.#toInput = this.#container.querySelector(`.${rangeFilterSelectors.classes.toInput}`);
        if(!this.#toInput) {
            throw new Error(`${rangeFilterSelectors.classes.toInput} not found`);
        }
        this.#clearButton = this.#container.querySelector(`.${rangeFilterSelectors.classes.clearButton}`);
        if(!this.#clearButton) {
            throw new Error(`${rangeFilterSelectors.classes.clearButton} not found`);
        }
    }


    init() {
        this.#addListeners();
    }


    #addListeners() {
        this.#addButtonListener();
        this.#addCloseButtonListener();
        this.#addApplyListener();
        this.#addClearButtonListener();
    }

    #addButtonListener() {
        this.#button.addEventListener('click', this.#buttonCallback);
    }

    #buttonCallback = (e) => {
        if(e.target === this.#button) {
            this.#container.classList.toggle(rangeFilterSelectors.classes.show);
            this.#isOpen = !this.#isOpen;
        }
    }

    #addCloseButtonListener() {
        this.#closeButton.addEventListener('click', this.#closeButtonCallback);
    }

    #closeButtonCallback = (e) => {
        if(e.target === this.#closeButton) {
            this.close();
        }
    }

    #addApplyListener() {
        this.#applyButton.addEventListener('click', this.#applyButtonCallback);
    }

    #applyButtonCallback = () => {
        this.#container.classList.remove(rangeFilterSelectors.classes.show);
        if(this.#fromInput.value === '' && this.#toInput.value === '') {
            this.#lastFromValue = '';
            this.#lastToValue = '';
            this.eventEmitter.emit(events.rangeFilterIsEmpty, {
                columnName: this.#button.getAttribute(rangeFilterSelectors.attributes.columnName)
            });
            this.#button.classList.remove(rangeFilterSelectors.classes.active);
        } else {
            this.#lastFromValue = this.#fromInput.value;
            this.#lastToValue = this.#toInput.value;
            this.eventEmitter.emit(events.rangeFilterApply, {
                from: this.#fromInput.value,
                to: this.#toInput.value,
                columnName: this.#button.getAttribute(rangeFilterSelectors.attributes.columnName)
            });
            this.#button.classList.add(rangeFilterSelectors.classes.active);
        }
        this.close();
    }

    #addClearButtonListener() {
        this.#clearButton.addEventListener('click', this.#clearButtonCallback);
    }

    #clearButtonCallback = () => {
        this.#fromInput.value = '';
        this.#toInput.value = '';
        this.#lastFromValue = '';
        this.#lastToValue = '';
        this.#button.classList.remove(rangeFilterSelectors.classes.active);
        this.eventEmitter.emit(events.rangeFilterIsEmpty, {
            columnName: this.#button.getAttribute(rangeFilterSelectors.attributes.columnName)
        });
        this.close();
    }

    getButton() {
        return this.#button;
    }

    isOpen() {
        return this.#isOpen;
    }

    open() {
        this.#container.classList.add(rangeFilterSelectors.classes.show);
        this.#isOpen = true;
    }

    close() {
        this.#fromInput.value = this.#lastFromValue ?? '';
        this.#toInput.value = this.#lastToValue ?? '';
        this.#container.classList.remove(rangeFilterSelectors.classes.show);
        this.#isOpen = false;
    }

    destroy() {
        this.eventEmitter = null;
        this.#button.removeEventListener('click', this.#buttonCallback);
        this.#buttonCallback = null;
        this.#button = null;
        this.#container = null;
        this.#closeButton.removeEventListener('click', this.#closeButtonCallback)
        this.#closeButtonCallback = null;
        this.#closeButton = null;
        this.#applyButton.removeEventListener('click', this.#applyButtonCallback);
        this.#applyButtonCallback = null;
        this.#applyButton = null;
        this.#clearButton.removeEventListener('click', this.#clearButtonCallback);
        this.#clearButtonCallback = null;
        this.#clearButton = null;
        this.#fromInput = null;
        this.#toInput = null;
        this.#type = null;
        this.#lastFromValue = null;
        this.#lastToValue = null;
    }
}