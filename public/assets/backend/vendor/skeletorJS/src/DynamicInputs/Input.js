import {dynamicInputsSelectors} from "./dynamicInputsSelectors.js";

export default class Input {

    #config;
    #baseInputName;
    constructor(config, baseInputName) {
        this.#config = config;
        this.#baseInputName = baseInputName;
    }

    getView() {
        const wrapper = document.createElement('div');
        wrapper.classList.add(dynamicInputsSelectors.classes.inputContainer);
        const label = document.createElement('label');
        label.textContent = this.#config.label;
        wrapper.appendChild(label);
        let input;
        if (this.#config.type === 'textarea') {
            input = document.createElement('textarea');
        } else {
            input = document.createElement('input');
            input.type = this.#config.type;
        }
        input.spellcheck = false;
        input.classList.add(dynamicInputsSelectors.classes.input);
        input.name = `${this.#baseInputName}[${this.#config.name}]`;
        wrapper.appendChild(input);
        return wrapper;
    }
}