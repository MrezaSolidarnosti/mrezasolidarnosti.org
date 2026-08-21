import {multipleSelectSelectors} from "./multipleSelectSelectors.js";

export default class MultipleSelect {

    #container;
    #optionsToSelect;
    #optionsToSelectContainer;
    #removeFromSelectedButtons;
    #selectedOptions;
    #select;
    #selectOptions;
    #numberOfSelectedOptions = 0;
    #numberOfOptions = 0;
    #readOnly;

    constructor(container) {
        this.#container = container;
    }

    init() {
        try {
            this.#setProperties();
            if(!this.#readOnly) {
                this.#addListeners();
            }
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#optionsToSelect = this.#container.querySelectorAll(`.${multipleSelectSelectors.classes.optionToSelectElement}`);
        this.#selectedOptions = this.#container.querySelectorAll(`.${multipleSelectSelectors.classes.selectedOptionElement}`);
        this.#removeFromSelectedButtons = this.#container.querySelectorAll(`.${multipleSelectSelectors.classes.removeButton}`);
        this.#select = this.#container.querySelector('select');
        this.#selectOptions = this.#select.querySelectorAll('option');
        this.#optionsToSelectContainer = this.#container.querySelector(`.${multipleSelectSelectors.classes.optionsToSelectContainer}`);
        this.#numberOfOptions = this.#optionsToSelect.length;
        this.#selectOptions.forEach((option) => {
            if(option.selected) {
                this.#numberOfSelectedOptions++;
            }
        });
        if(!this.#optionsToSelect) {
            throw new Error(`${multipleSelectSelectors.classes.optionsToSelectContainer} not found in container`);
        }
        if(!this.#selectedOptions) {
            throw new Error(`${multipleSelectSelectors.classes.selectedOptionsContainer} not found in container`);
        }
        if(!this.#removeFromSelectedButtons) {
            throw new Error(`${multipleSelectSelectors.classes.removeButton} not found in container`);
        }
        if(!this.#select) {
            throw new Error('Select not found in container');
        }
        if(!this.#selectOptions) {
            throw new Error('Select options not found in container');
        }
        if(!this.#optionsToSelectContainer) {
            throw new Error(`${multipleSelectSelectors.classes.optionsToSelectContainer} not found in container`);
        }
        this.#readOnly = this.#container.getAttribute(multipleSelectSelectors.attributes.readOnly);
    }

    #addListeners() {
        this.#optionsToSelect.forEach((option) => {
            option.addEventListener('click', this.#handleOptionSelect)
        });
        this.#removeFromSelectedButtons.forEach((button) => {
            button.addEventListener('click', this.#handleRemoveFromSelected);
        })
    }

    #handleOptionSelect = (e) => {
        let option = e.target;
        option.classList.add(multipleSelectSelectors.classes.selected);
        const value = option.getAttribute(multipleSelectSelectors.attributes.value);
        this.#selectedOptions.forEach((selectedOption) => {
            if(selectedOption.getAttribute(multipleSelectSelectors.attributes.value) === value) {
                selectedOption.classList.add(multipleSelectSelectors.classes.selected);
            }
        });
        this.#addSelectValue(value);
        this.#select.dispatchEvent(new Event('change'));
    }

    #handleRemoveFromSelected = (e) => {
        let button = e.target;
        button.parentElement.classList.remove(multipleSelectSelectors.classes.selected);
        const value = button.getAttribute(multipleSelectSelectors.attributes.value);
        this.#optionsToSelect.forEach((selectedOption) => {
            if(selectedOption.getAttribute(multipleSelectSelectors.attributes.value) === value) {
                selectedOption.classList.remove(multipleSelectSelectors.classes.selected);
            }
        });
        this.#removeSelectValue(value);
        this.#select.dispatchEvent(new Event('change'));
    }

    #addSelectValue(value) {
        this.#selectOptions.forEach((option) => {
            if (option.value === value) {
                option.selected = true;
                this.#numberOfSelectedOptions++;
            }
        });
        if(this.#areAllSelected()) {
            this.#optionsToSelectContainer.classList.add(multipleSelectSelectors.classes.empty);
        }
    }

    #removeSelectValue(value) {
        this.#selectOptions.forEach((option) => {
            if (option.value === value) {
                option.selected = false;
                this.#numberOfSelectedOptions--;
            }
        });
        if(!this.#areAllSelected()) {
            this.#optionsToSelectContainer.classList.remove(multipleSelectSelectors.classes.empty);
        }
    }

    #areAllSelected() {
        return this.#numberOfSelectedOptions === this.#numberOfOptions;
    }


    destroy() {
        this.#optionsToSelect.forEach((option) => {
            option.removeEventListener('click', this.#handleOptionSelect)
        });
        this.#removeFromSelectedButtons.forEach((button) => {
            button.removeEventListener('click', this.#handleRemoveFromSelected);
        });
        this.#handleOptionSelect = null;
        this.#handleRemoveFromSelected = null;
        this.#container = null;
        this.#optionsToSelect = null;
        this.#removeFromSelectedButtons = null;
        this.#selectedOptions = null;
        this.#select = null;
        this.#selectOptions = null;
    }
}