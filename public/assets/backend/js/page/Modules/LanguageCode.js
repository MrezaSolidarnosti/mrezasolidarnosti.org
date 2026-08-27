import BaseModule from "../../../../../vendor/skeletorjs/src/ContentEditor/BaseModule.js";

export default class LanguageCode extends BaseModule {

    #setupComplete = false;
    #language = null;
    constructor({eventEmitter}) {
        super({eventEmitter});
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#setupComplete = true;
    }

    getData() {
        return this.#language;
    }

    setData(value) {
        this.#language = value;
    }


    destroy() {
        super.destroy();
        this.#setupComplete = false;
    }
}