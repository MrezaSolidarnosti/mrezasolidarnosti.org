import {valuesGeneratorSelector} from "./valuesGeneratorSelectors.js";
import Value from "./Value.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";

export default class ValuesGenerator {


    #container;
    #inputForNewValues;
    #valuesContainer;
    #inputNameValues;
    #inputNameNewValues;
    #searchValuesInput;
    #values = new Map();
    #nextAvailableValueId = 0;
    #eventEmitter = new EventEmitter();
    #readOnly;

    constructor(container) {
        this.#container = container;
    }

    init() {
        try {
            this.#setProperties();
            if(!this.#readOnly) {
                this.#addListeners();
                this.#listenToEvents();
            }
            this.#initExisting();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#inputForNewValues = this.#container.querySelector(`.${valuesGeneratorSelector.classes.inputForNewValues}`);
        this.#valuesContainer = this.#container.querySelector(`.${valuesGeneratorSelector.classes.valuesContainer}`);
        this.#searchValuesInput = this.#container.querySelector(`.${valuesGeneratorSelector.classes.searchValuesInput}`);
        if (!this.#inputForNewValues) {
            throw new Error(`${valuesGeneratorSelector.classes.inputForNewValues} not found`);
        }
        if (!this.#valuesContainer) {
            throw new Error(`${valuesGeneratorSelector.classes.valuesContainer} not found`);
        }
        this.#inputNameValues = this.#container.getAttribute(valuesGeneratorSelector.attributes.valuesInputName);
        this.#inputNameNewValues = this.#container.getAttribute(valuesGeneratorSelector.attributes.newValuesInputName);
        if (!this.#inputNameValues) {
            throw new Error(`${valuesGeneratorSelector.attributes.valuesInputName} not found`);
        }
        if (!this.#inputNameNewValues) {
            throw new Error(`${valuesGeneratorSelector.attributes.newValuesInputName} not found`);
        }
        if(!this.#searchValuesInput) {
            throw new Error(`${valuesGeneratorSelector.classes.searchValuesInput} not found`);
        }
        this.#readOnly = this.#container.getAttribute(valuesGeneratorSelector.attributes.readOnly);
    }

    #addListeners() {
        this.#inputForNewValues.addEventListener('keyup', this.#newValueCallback);
        this.#searchValuesInput.addEventListener('input', this.#searchValuesCallback);
    }

    #newValueCallback = (e) => {
        if (e.key === 'Enter') {
            this.#addNewValue();
        }
    }

    #searchValuesCallback = (e) => {
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

    #addNewValue() {
        if(this.#inputForNewValues.value.trim() === '') {
            return;
        }
        if(this.#checkIfValueExistsByName()) {
            return;
        }
        this.#clearValueSearchFilter();
        const id = this.#nextAvailableValueId++;
        const value = new Value({
            id,
            inputName: this.#inputNameNewValues,
            eventEmitter: this.#eventEmitter,
            value: this.#inputForNewValues.value.trim()
        });
        this.#values.set(id, value);
        this.#valuesContainer.appendChild(value.getView());
        this.#inputForNewValues.value = '';
    }

    #checkIfValueExistsByName() {
        const valueFromInput = this.#inputForNewValues.value.trim();
        let exists= false;
        for (const [key, value] of this.#values) {
            if (value.getName() === valueFromInput) {
                exists = true;
                break;
            }
        }
        return exists;
    }

    #listenToEvents() {
        this.#eventEmitter.on(events.removeValue, ({id}) => {
            this.#removeValue(id);
        })
    }

    #removeValue(id) {
        const value = this.#values.get(id);
        if (value) {
            value.destroy();
            this.#values.delete(id);
        }
    }

    #initExisting() {
        this.#valuesContainer.querySelectorAll(`.${valuesGeneratorSelector.classes.value}`).forEach((valueContainer) => {
            const id = this.#nextAvailableValueId++;
            const value = new Value({
                container: valueContainer,
                eventEmitter: this.#eventEmitter,
                inputName: this.#inputNameValues,
                id
            });
            this.#values.set(id, value);
        });
    }


    destroy() {
        this.#container = null;
        this.#inputForNewValues.removeEventListener('keyup', this.#newValueCallback);
        this.#newValueCallback = null;
        this.#searchValuesInput.removeEventListener('input', this.#searchValuesCallback);
        this.#searchValuesCallback = null;
        this.#searchValuesInput = null;
        this.#inputForNewValues = null;
        this.#valuesContainer = null;
        this.#inputNameValues = null;
        this.#inputNameNewValues = null;
        this.#values.forEach((value) => {
            value.destroy();
        });
        this.#values.clear();
        this.#values = null;
        this.#nextAvailableValueId = null;
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
    }
}