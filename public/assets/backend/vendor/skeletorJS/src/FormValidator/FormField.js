import ElementNoLongerExistsError from "./ElementNoLongerExistsError.js";

export default class FormField {
    #formField;
    #helper;
    #type;
    #required;
    #requiredText;
    #validationStrategy;
    #validateStrategyMessage;
    #selectInputEmptyValue;
    #maxLength;
    #minLength;
    #exactLen;
    #maxLenMessage;
    #minLenMessage;
    #exactLenMessage;
    #maxNum;
    #minNum;
    #maxNumMessage;
    #minNumMessage;
    #matchInputId;
    #matchInputMessage;
    #beforeDateTime;
    #beforeDateTimeMessage;
    #afterDateTime;
    #afterDateTimeMessage;
    #beforeOtherDateTime;
    #beforeOtherDateTimeMessage;
    #afterOtherDateTime;
    #afterOtherDateTimeMessage;
    #beforeDate;
    #beforeDateMessage;
    #afterDate;
    #afterDateMessage;
    #beforeOtherDate;
    #beforeOtherDateMessage;
    #afterOtherDate;
    #afterOtherDateMessage;
    #beforeTime;
    #beforeTimeMessage;
    #afterTime;
    #afterTimeMessage;
    #beforeOtherTime;
    #beforeOtherTimeMessage;
    #afterOtherTime;
    #afterOtherTimeMessage;
    #applyOnlyWhenPopulated;

    constructor(formField, helper) {
        this.#formField = formField;
        this.#helper = helper;
        this.#type = this.#formField.type;
        this.#required = this.#setRequired(this.#formField.getAttribute('data-required'));
        this.#requiredText = this.#required ? this.#formField.getAttribute('data-required-text') : null;
        this.#validationStrategy = this.#formField.getAttribute('data-validation-strategy') ?? null;
        this.#validateStrategyMessage = this.#formField.getAttribute('data-validation-strategy-message') ?? null;
        this.#selectInputEmptyValue = this.#formField.getAttribute('data-select-empty-value') ?? null;
        this.#maxLength = parseInt(this.#formField.getAttribute('data-max-len'), 10) ?? null;
        this.#minLength = parseInt(this.#formField.getAttribute('data-min-len'), 10) ?? null;
        this.#exactLen = parseInt(this.#formField.getAttribute('data-exact-len'), 10) ?? null;
        this.#maxLenMessage = this.#formField.getAttribute('data-max-len-message') ?? null;
        this.#minLenMessage = this.#formField.getAttribute('data-min-len-message') ?? null;
        this.#exactLenMessage = this.#formField.getAttribute('data-exact-len-message') ?? null;
        this.#maxNum = parseInt(this.#formField.getAttribute('data-max-num'), 10) ?? null;
        this.#minNum = parseInt(this.#formField.getAttribute('data-min-num'), 10) ?? null;
        this.#maxNumMessage = this.#formField.getAttribute('data-max-num-message') ?? null;
        this.#minNumMessage = this.#formField.getAttribute('data-min-num-message') ?? null;
        this.#matchInputId = this.#formField.getAttribute('data-match-input-id') ?? null;
        this.#matchInputMessage = this.#formField.getAttribute('data-match-input-message') ?? null;
        this.#beforeDateTime = this.#formField.getAttribute('data-before-date-time') ?? null;
        this.#beforeDateTimeMessage = this.#formField.getAttribute('data-before-date-time-message') ?? null;
        this.#afterDateTime = this.#formField.getAttribute('data-after-date-time') ?? null;
        this.#afterDateTimeMessage = this.#formField.getAttribute('data-after-date-time-message') ?? null;
        this.#beforeOtherDateTime = this.#formField.getAttribute('data-before-other-date-time') ?? null;
        this.#beforeOtherDateTimeMessage = this.#formField.getAttribute('data-before-other-date-time-message') ?? null;
        this.#afterOtherDateTime = this.#formField.getAttribute('data-after-other-date-time') ?? null;
        this.#afterOtherDateTimeMessage = this.#formField.getAttribute('data-after-other-date-time-message') ?? null;
        this.#beforeDate = this.#formField.getAttribute('data-before-date') ?? null;
        this.#beforeDateMessage = this.#formField.getAttribute('data-before-date-message') ?? null;
        this.#afterDate = this.#formField.getAttribute('data-after-date') ?? null;
        this.#afterDateMessage = this.#formField.getAttribute('data-after-date-message') ?? null;
        this.#beforeOtherDate = this.#formField.getAttribute('data-before-other-date') ?? null;
        this.#beforeOtherDateMessage = this.#formField.getAttribute('data-before-other-date-message') ?? null;
        this.#afterOtherDate = this.#formField.getAttribute('data-after-other-date') ?? null;
        this.#afterOtherDateMessage = this.#formField.getAttribute('data-after-other-date-message') ?? null;
        this.#beforeTime = this.#formField.getAttribute('data-before-time') ?? null;
        this.#beforeTimeMessage = this.#formField.getAttribute('data-before-time-message') ?? null;
        this.#afterTime = this.#formField.getAttribute('data-after-time') ?? null;
        this.#afterTimeMessage = this.#formField.getAttribute('data-after-time-message') ?? null;
        this.#beforeOtherTime = this.#formField.getAttribute('data-before-other-time') ?? null;
        this.#beforeOtherTimeMessage = this.#formField.getAttribute('data-before-other-time-message') ?? null;
        this.#afterOtherTime = this.#formField.getAttribute('data-after-other-time') ?? null;
        this.#afterOtherTimeMessage = this.#formField.getAttribute('data-after-other-time-message') ?? null;
        this.#applyOnlyWhenPopulated = this.#formField.getAttribute('data-apply-only-when-populated') ?? null;
    }

    getFormField() {
        return this.#formField;
    }


    #setRequired(data) {
        return data === 'true';
    }

    validate() {
        if(document.body.contains(this.#formField) === false){
            throw new ElementNoLongerExistsError('Form field no longer exists');
        }
        if(!this.#formField) {
            return true;
        }
        if (this.#formField.classList.contains('hidden')) {
            return true;
        }
        let pattern;
        switch (this.#validationStrategy) {
            case 'onlyLetters':
                pattern = /^[\p{L}]*$/u;
                break;
            case 'uppercase':
                pattern = /^[\p{Lu}]*$/u;
                break;
            case 'lowercase':
                pattern = /^[\p{Ll}]*$/u;
                break;
            case 'email':
                pattern = /\S+@\S+\.\S+/;
                break;
            default:
                pattern = this.#validationStrategy; // custom pattern
                break;
        }
        if (this.#type !== 'select-one' && this.#type !== 'checkbox') { // non-select inputs
            let formFieldValue = this.#formField.value.trim();
            if (formFieldValue.length === 0) { // if empty
                if (this.#required) { // if required
                    this.#helper.invalidateField(this.#formField, this.#requiredText);
                    return false;
                }
                if(this.#applyOnlyWhenPopulated) {
                    return true;
                }
            }
            if (this.#maxLength !== null && this.#maxLenMessage !== null && formFieldValue.length > this.#maxLength) { // max length string
                this.#helper.invalidateField(this.#formField, this.#maxLenMessage);
                return false;
            }
            if (this.#minLength !== null && this.#minLenMessage !== null && formFieldValue.length < this.#minLength) { // min length string
                this.#helper.invalidateField(this.#formField, this.#minLenMessage);
                return false;
            }
            if (this.#exactLen !== null && this.#exactLenMessage !== null && formFieldValue.length !== this.#exactLen) { // exact len string
                this.#helper.invalidateField(this.#formField, this.#exactLenMessage);
                return false;
            }
            if (this.#maxNum !== null && this.#maxNumMessage !== null && parseInt(formFieldValue, 10) > this.#maxNum) { // max num
                this.#helper.invalidateField(this.#formField, this.#maxNumMessage);
                return false;
            }
            if (this.#minNum !== null && this.#minNumMessage !== null && parseInt(formFieldValue, 10) < this.#minNum) { // min num
                this.#helper.invalidateField(this.#formField, this.#minNumMessage);
                return false;
            }
            if(this.#type === 'datetime-local') {
                if (this.#beforeDateTime !== null && this.#beforeDateTimeMessage !== null) {
                    if (this.#formField.value >= this.#beforeDateTime) {
                        this.#helper.invalidateField(this.#formField, this.#beforeDateTimeMessage);
                        return false;
                    }
                }
                if (this.#afterDateTime !== null && this.#afterDateTimeMessage !== null) {
                    if (this.#formField.value <= this.#afterDateTime) {
                        this.#helper.invalidateField(this.#formField, this.#afterDateTimeMessage);
                        return false;
                    }
                }

                if (this.#beforeOtherDateTime !== null && this.#beforeOtherDateTimeMessage !== null) {
                    let otherDateTime = document.getElementById(this.#beforeOtherDateTime);
                    if (otherDateTime && this.#formField.value >= otherDateTime.value) {
                        this.#helper.invalidateField(this.#formField, this.#beforeOtherDateTimeMessage);
                        return false;
                    }
                }

                if (this.#afterOtherDateTime !== null && this.#afterOtherDateTimeMessage !== null) {
                    let otherDateTime = document.getElementById(this.#afterOtherDateTime);
                    if (otherDateTime && this.#formField.value <= otherDateTime.value) {
                        this.#helper.invalidateField(this.#formField, this.#afterOtherDateTimeMessage);
                        return false;
                    }
                }
            }
            if(this.#type === 'date') {
                if (this.#beforeDate !== null && this.#beforeDateMessage !== null) {
                    if (this.#formField.value >= this.#beforeDate) {
                        this.#helper.invalidateField(this.#formField, this.#beforeDateMessage);
                        return false;
                    }
                }

                if (this.#afterDate !== null && this.#afterDateMessage !== null) {
                    if (this.#formField.value <= this.#afterDate) {
                        this.#helper.invalidateField(this.#formField, this.#afterDateMessage);
                        return false;
                    }
                }

                if (this.#beforeOtherDate !== null && this.#beforeOtherDateMessage !== null) {
                    let otherDate = document.getElementById(this.#beforeOtherDate);
                    if (otherDate && this.#formField.value >= otherDate.value) {
                        this.#helper.invalidateField(this.#formField, this.#beforeOtherDateMessage);
                        return false;
                    }
                }

                if (this.#afterOtherDate !== null && this.#afterOtherDateMessage !== null) {
                    let otherDate = document.getElementById(this.#afterOtherDate);
                    if (otherDate && this.#formField.value <= otherDate.value) {
                        this.#helper.invalidateField(this.#formField, this.#afterOtherDateMessage);
                        return false;
                    }
                }
            }
            if(this.#type === 'time') {
                if (this.#beforeTime !== null && this.#beforeTimeMessage !== null) {
                    if (this.#formField.value >= this.#beforeTime) {
                        this.#helper.invalidateField(this.#formField, this.#beforeTimeMessage);
                        return false;
                    }
                }

                if (this.#afterTime !== null && this.#afterTimeMessage !== null) {
                    if (this.#formField.value <= this.#afterTime) {
                        this.#helper.invalidateField(this.#formField, this.#afterTimeMessage);
                        return false;
                    }
                }

                if (this.#beforeOtherTime !== null && this.#beforeOtherTimeMessage !== null) {
                    let otherTime = document.getElementById(this.#beforeOtherTime);
                    if (otherTime && this.#formField.value >= otherTime.value) {
                        this.#helper.invalidateField(this.#formField, this.#beforeOtherTimeMessage);
                        return false;
                    }
                }

                if (this.#afterOtherTime !== null && this.#afterOtherTimeMessage !== null) {
                    let otherTime = document.getElementById(this.#afterOtherTime);
                    if (otherTime && this.#formField.value <= otherTime.value) {
                        this.#helper.invalidateField(this.#formField, this.#afterOtherTimeMessage);
                        return false;
                    }
                }
            }


            if (pattern !== null && this.#validateStrategyMessage !== null) { // validation strategy pattern
                if (!this.#validatePattern(pattern)) {
                    this.#helper.invalidateField(this.#formField, this.#validateStrategyMessage);
                    return false;
                }
            }
        } else if(this.#type === 'checkbox') {
            if(!this.#formField.checked && this.#required) {
                this.#helper.invalidateField(this.#formField, this.#requiredText);
                return false;
            }
        } else { // select inputs
            if(this.#formField.multiple) {
                if (this.#required) {
                    if (this.#formField.selectedOptions.length === 0) { // if no option selected
                        this.#helper.invalidateField(this.#formField, this.#requiredText);
                        return false;
                    }
                }
            } else {
                if (this.#required) {
                    if (this.#formField.value === this.#selectInputEmptyValue) { // if the default empty value is the value
                        this.#helper.invalidateField(this.#formField, this.#requiredText);
                        return false;
                    }
                }
            }
        }
        if(this.#matchInputId !== null && this.#matchInputMessage !== null) {
            let matchInput = document.getElementById(this.#matchInputId);
            if(matchInput && matchInput.value !== this.#formField.value) {
                this.#helper.invalidateField(this.#formField, this.#matchInputMessage);
                return false;
            }
        }
        this.#helper.makeFieldValid(this.#formField);
        return true;
    }


    #validatePattern(pattern) {
        if (typeof pattern !== 'object') {
            pattern = new RegExp(pattern);
        }
        return pattern.test(this.#formField.value);
    }

    destroy() {
        this.#formField = null;
        this.#helper = null;
        this.#type = null;
        this.#required = null;
        this.#requiredText = null;
        this.#validationStrategy = null;
        this.#validateStrategyMessage = null;
        this.#selectInputEmptyValue = null;
        this.#maxLength = null;
        this.#minLength = null;
        this.#exactLen = null;
        this.#maxLenMessage = null;
        this.#minLenMessage = null;
        this.#exactLenMessage = null;
        this.#maxNum = null;
        this.#minNum = null;
        this.#maxNumMessage = null;
        this.#minNumMessage = null;
        this.#matchInputId = null;
        this.#matchInputMessage = null;
        this.#beforeDateTime = null;
        this.#beforeDateTimeMessage = null;
        this.#afterDateTime = null;
        this.#afterDateTimeMessage = null;
        this.#beforeOtherDateTime = null;
        this.#beforeOtherDateTimeMessage = null;
        this.#afterOtherDateTime = null;
        this.#afterOtherDateTimeMessage = null;
        this.#beforeDate = null;
        this.#beforeDateMessage = null;
        this.#afterDate = null;
        this.#afterDateMessage = null;
        this.#beforeOtherDate = null;
        this.#beforeOtherDateMessage = null;
        this.#afterOtherDate = null;
        this.#afterOtherDateMessage = null;
        this.#beforeTime = null;
        this.#beforeTimeMessage = null;
        this.#afterTime = null;
        this.#afterTimeMessage = null;
        this.#beforeOtherTime = null;
        this.#beforeOtherTimeMessage = null;
        this.#afterOtherTime = null;
        this.#afterOtherTimeMessage = null;
    }

}
