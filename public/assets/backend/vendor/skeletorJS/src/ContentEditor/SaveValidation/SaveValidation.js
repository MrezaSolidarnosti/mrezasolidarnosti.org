import FailedValidation from "./FailedValidation.js";

export default class SaveValidation {

    validations = new Map();
    failedValidations = [];

    registerValidation({name, callback}) {
        if(this.validations.has(name)) {
            throw new Error(`Save validation ${name} is already registered.`);
        }
        this.validations.set(name, callback);
    }

    validate() {
        const failed = [];
        this.validations.forEach((validation) => {
            const valid = validation();
            if(valid instanceof FailedValidation) {
                failed.push(valid);
            }
        });
        return {
            ok: failed.length === 0,
            failed
        }
    }

    destroy() {
        this.validations.clear();
    }
}