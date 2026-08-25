export default class Identifier {

    #id = 0;
    constructor() {}

    generateId() {
        return this.#id++;
    }

    current() {
        return this.#id;
    }

    next() {
        return this.#id + 1;
    }

    previous() {
        return this.#id - 1;
    }
}