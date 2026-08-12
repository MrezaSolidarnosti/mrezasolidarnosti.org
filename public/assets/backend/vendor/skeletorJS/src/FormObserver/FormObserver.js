export default class FormObserver {
    #form;
    #inputsData = [];
    #numberOfChangedElements = 0;
    #observing = false;
    #observer;
    #observeElementDeletion;

    static FORM_OBSERVER_IGNORE_CLASS_NAME = 'formObserverIgnore';
    constructor(form) {
        this.#form = form;
        this.#observeElementDeletion = this.#observeElementDeletionCallback.bind(this);
        this.#observer = new MutationObserver((mutationsList, observer) => {
            this.#observeElementDeletion(mutationsList, observer);
        });
        this.#observer.observe(this.#form, { childList: true, subtree: true});
    }

    #observeElementDeletionCallback(mutationsList) {
        mutationsList.forEach(mutation => {
            if (mutation.removedNodes && mutation.removedNodes.length > 0) {
                this.#inputsData.forEach((inputData) => {
                    if (Array.from(mutation.removedNodes).some(removedNode => removedNode.contains(inputData.input))) {
                        inputData.input.removeEventListener(inputData.event, inputData.callback);
                        this.#inputsData = this.#inputsData.filter((inputDataEntry) => {
                            return inputDataEntry.input !== inputData.input;
                        });
                    }
                });
            }
        });
    }


    observe() {
        if(!this.#form || this.#observing) {
            return;
        }
        this.#handleInputs();
        this.#handleSelects();
        this.#handleTextarea();
        this.#observing = true;
    }

    #handleInputs() {
        this.#form.querySelectorAll('input').forEach((input) => {
            this.#observer.observe(input.parentElement, { childList: true, subtree: true});
            if(input.classList.contains(FormObserver.FORM_OBSERVER_IGNORE_CLASS_NAME)) {
                return;
            }
            switch(input.type) {
                case 'hidden':
                    break;
                case 'checkbox':
                    this.#observeCheckbox(input);
                    break;
                case 'radio':
                    this.#observeRadio(input);
                    break;
                default:
                    this.#observeDefault(input)
                    break;
            }
        });
    }

    #handleSelects() {
        this.#form.querySelectorAll('select').forEach((select) => {
            this.#observeSelect(select);
        });
    }

    #handleTextarea() {
        this.#form.querySelectorAll('textarea').forEach((textarea) => {
            this.#observeDefault(textarea);
        });
    }

    #observeCheckbox(input) {
        let originalValue = input.checked;
        const callback = this.#checkboxChangeCallback.bind(this, originalValue, input);
        const event = 'change';
        this.#inputsData.push({
            input,
            originalValue,
            event,
            callback
        });
        input.addEventListener(event, callback);
    }

    #checkboxChangeCallback = (originalValue, input) => {
        if(originalValue === input.checked) {
            this.#numberOfChangedElements--;
        } else {
            this.#numberOfChangedElements++;
        }
    }

    #observeSelect(select) {
        //@todo add multiple select support
        if(select.multiple) {
            return;
        }
        let originalValue = select.value;
        const callback = this.#selectChangeCallback.bind(this, originalValue, select);
        const event = 'change';
        this.#inputsData.push({
            input: select,
            originalValue,
            event,
            callback
        });
        select.addEventListener(event, callback);
    }

    #selectChangeCallback = (originalValue, select) => {
        if(select.value === originalValue) {
            this.#numberOfChangedElements--;
        } else {
            this.#numberOfChangedElements++;
        }
    }

    #observeDefault(input) {
        let originalValue = input.value;
        const callback = this.#defaultChangeCallback.bind(this, originalValue, input);
        const event = 'change';
        this.#inputsData.push({
            input,
            originalValue,
            event,
            callback
        });
        input.addEventListener(event, callback);
    }

    #defaultChangeCallback = (originalValue, input) => {
        if(originalValue === input.value) {
            this.#numberOfChangedElements--;
        } else {
            this.#numberOfChangedElements++;
        }
    }

    #observeRadio(radio) {
        let radioName = radio.getAttribute('name');
        let checkedRadio = this.#form.querySelector(`input[name=${radioName}]:checked`);
        let originalValue = false;
        if(checkedRadio) {
            originalValue = checkedRadio.value;
        }
        const callback = this.#radioChangeCallback.bind(this, originalValue, radio);
        const event = 'change';
        this.#inputsData.push({
            input: radio,
            originalValue,
            event,
            callback
        });
        radio.addEventListener('change', callback);
    }

    #radioChangeCallback = (originalValue, radio) => {
        if(originalValue === radio.value) {
            this.#numberOfChangedElements--;
        } else {
            this.#numberOfChangedElements++;
        }
    }

    isModified() {
        return this.#numberOfChangedElements > 0;
    }

    reset() {
        this.#inputsData.forEach((inputData) => {
            if(inputData.input.type === 'checkbox') {
                inputData.input.checked = inputData.originalValue;
                return;
            }
            inputData.input.value = inputData.originalValue;
        });
        this.#numberOfChangedElements = 0;
    }

    getModifiedElements() {
        return this.#inputsData.filter((inputData) => {
            if(inputData.input.type === 'checkbox') {
                return inputData.originalValue !== inputData.input.checked;
            }
            return inputData.originalValue !== inputData.input.value;
        });
    }

    getUnmodifiedElements() {
        return this.#inputsData.filter((inputData) => {
            if(inputData.input.type === 'checkbox') {
                return inputData.originalValue === inputData.input.checked;
            }
            return inputData.originalValue === inputData.input.value;
        });
    }

    getModifiedElementsCount() {
        return this.getModifiedElements().length;
    }

    getUnmodifiedElementsCount() {
        return this.getUnmodifiedElements().length;
    }

    getModifiedElementsPercentage() {
        return this.getModifiedElementsCount() / this.#inputsData.length * 100;
    }

    getUnmodifiedElementsPercentage() {
        return this.getUnmodifiedElementsCount() / this.#inputsData.length * 100;
    }

    resetNumberOfChangedElements() {
        this.#numberOfChangedElements = 0;
    }

    destroy() {
        if(this.#inputsData) {
            this.#inputsData.forEach((inputData) => {
                inputData.input.removeEventListener(inputData.event, inputData.callback);
            });
        }
        this.#inputsData = null;
        this.#form = null;
        this.#numberOfChangedElements = null;
        this.#observer.disconnect();
        this.#observer = null;
        this.#observeElementDeletion = null;
    }
}