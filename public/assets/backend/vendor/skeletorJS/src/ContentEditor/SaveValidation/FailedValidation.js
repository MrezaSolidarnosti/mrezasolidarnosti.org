export default class FailedValidation {

    #messages = [];
    constructor(messages = []) {
        this.#messages = messages;
    }

    addMessage(message) {
        this.#messages.push(message);
    }

    clearMessages() {
        this.#messages = [];
    }

    getMessages() {
        return this.#messages;
    }


}