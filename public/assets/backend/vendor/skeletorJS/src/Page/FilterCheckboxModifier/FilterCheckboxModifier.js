import {dataTableSelectors} from "../../DataTable/dataTableSelectors.js";
import {crudPageSelectors} from "../crudPageSelectors.js";
import EventEmitter from "../../EventEmitter/EventEmitter.js";
import {events} from "./events.js";

export default class FilterCheckboxModifier {
    #label;
    #filterName;
    #modifierName;

    #filterContainer;
    #container;
    #labelElement;
    #checkbox;

    eventEmitter = new EventEmitter();

    constructor({
        label,
        filterName,
        modifierName
    }) {
        this.#label = label;
        this.#filterName = filterName;
        this.#modifierName = modifierName;
        this.#setElements();
        this.#addListeners();
    }

    #setElements() {
        this.#filterContainer = document.querySelector(
            `.${dataTableSelectors.classes.tableFilterContainer}[${dataTableSelectors.attributes.filterContainerName}="${this.#filterName}"]`
        );
        this.#generateView();
    }

    #generateView() {
        this.#container = this.#generateContainer();
        this.#labelElement = this.#generateLabelElement();
        this.#checkbox = this.#generateCheckbox();
        this.#container.appendChild(this.#labelElement);
        this.#container.appendChild(this.#checkbox);
    }

    #generateContainer() {
        const container = document.createElement('div');
        container.classList.add(dataTableSelectors.classes.tableFilterModifierContainer);
        return container;
    }

    #generateLabelElement() {
        const labelElement = document.createElement('label');
        labelElement.textContent = this.#label;
        labelElement.setAttribute('for', `${this.#modifierName}Modifier`);
        return labelElement;
    }

    #generateCheckbox() {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = this.#modifierName;
        checkbox.id = `${this.#modifierName}Modifier`;
        checkbox.classList.add(crudPageSelectors.classes.input);
        return checkbox;
    }

    #addListeners() {
        this.#addCheckboxListener();
    }

    #addCheckboxListener() {
        this.#checkbox.addEventListener('change', this.#checkboxChangeCallback);
    }

    #checkboxChangeCallback = () => {
        this.eventEmitter.emit(events.modifierChanged, {
            modifierName: this.#modifierName,
            isChecked: this.#checkbox.checked
        });
    }

    init() {
        this.#filterContainer.appendChild(this.#container);
    }

    destroy() {
        this.#checkbox.removeEventListener('change', this.#checkboxChangeCallback);
        this.#container.remove();
        this.#container = null;
        this.#labelElement = null;
        this.#checkbox = null;
        this.eventEmitter.destroy();
        this.eventEmitter = null;
    }
}