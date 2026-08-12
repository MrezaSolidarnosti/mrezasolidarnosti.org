export default class Response {
    constructor(response) {
        this.response = response;
    }

    getErrors() {
        return this.response.errors.length ? this.response.errors : null;
    }

    getGeneralErrors() {
        return this.response.generalErrors.length ? this.response.generalErrors : null;
    }

    getErrorMessages() {
        let messages = [];
        Object.keys(this.getErrors()).forEach((key) => {
            if(this.getErrors()[key].message) {
                messages.push(this.getErrors()[key].message);
            }
        });
        return messages;
    }

    getGeneralErrorMessages() {
        if(!this.getGeneralErrors()) {
            return [];
        }
        let messages = [];
        Object.keys(this.getGeneralErrors()).forEach((key) => {
            if(this.getGeneralErrors()[key].message) {
                messages.push(this.getGeneralErrors()[key].message);
            }
        });
        return messages;
    }

    getCSRFTokenInput() {
        return this.response.token;
    }

    getMessage() {
        return this.response.message ?? null;
    }

    getStatus() {
        return this.response.status ?? false;
    }

    getData() {
        return this.response.data ?? null;
    }

}