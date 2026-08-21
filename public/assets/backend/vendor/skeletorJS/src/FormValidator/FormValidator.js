import FormField from "./FormField.js";
import FormValidatorHelper from "./FormValidatorHelper.js";
import {formFieldSelectors} from "./formFieldSelectors.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import ElementNoLongerExistsError from "./ElementNoLongerExistsError.js";

export default class FormValidator {

    #form;
    #formFieldClassNames;
    #inputs;
    #formScrollableContainer;
    #helper;
    #formFields;
    #setupDone = false;
    eventEmitter = new EventEmitter();

    static FORM_VALIDATOR_IGNORE_CLASS_NAME = 'formValidatorIgnore';

    constructor({form, formFieldClassNames, formScrollableContainer = null}) {
        this.#form = form
        if(this.#form) {
            this.#formFieldClassNames = formFieldClassNames
            this.#inputs = this.#form.querySelectorAll(`.${this.#formFieldClassNames}:not(.${FormValidator.FORM_VALIDATOR_IGNORE_CLASS_NAME})`);
            this.#formScrollableContainer = formScrollableContainer ?? window;
            this.#helper = new FormValidatorHelper();
            this.#formFields = [];
        }
    }

    init() {
        if(this.#setupDone) {
            return;
        }
        this.#registerFormFields();
        this.#addSubmitListener();
        this.#setupDone = true;
    }

    #addSubmitListener() {
        if(this.#form) {
            this.#form.addEventListener('submit', this.#submitCallback);
        }
    }


    #submitCallback = (e) => {
        if (!this.validate()) {
            e.stopImmediatePropagation();
            e.preventDefault();
            this.scrollToFirstInvalidFormField();
            this.eventEmitter.emit(events.invalidFormSubmitted);
        }
    };


    #registerFormFields() {
        if(this.#form) {
            this.#inputs.forEach((field) => {
                let formField = new FormField(field, this.#helper);
                this.#formFields.push(formField);
            });
        }
    }

    validate() {
        let valid = true;
        this.#formFields.forEach((field) => {
            try {
                if (!field.validate()) {
                    valid = false;
                }
            } catch (e) {
                if(e instanceof ElementNoLongerExistsError) {
                    this.#formFields = this.#formFields.filter((formField) => {
                        field.destroy();
                        return formField !== field;
                    });
                }
            }
        })
        return valid;
    }

    scrollToFirstInvalidFormField() {
        let firstInvalidFormField = this.#form.querySelector(`.${formFieldSelectors.classes.invalidFormField}`);
        if(firstInvalidFormField) {
            this.#helper.scrollTo(firstInvalidFormField,this.#formScrollableContainer);
        }
    }

    destroy() {
        if(this.#form) {
            this.#form.removeEventListener('submit', this.#submitCallback);
        }
        this.#submitCallback = null;
        this.#form = null;
        this.#formFieldClassNames = null;
        this.#inputs = null;
        this.#formScrollableContainer = null;
        this.#helper = null;
        if (this.#formFields) {
            this.#formFields.forEach((field) => {
                field.destroy();
            });
            this.#formFields = null;
        }
        this.#setupDone = null;
        this.eventEmitter.destroy();
        this.eventEmitter = null;
    }

}