import BaseModule from "../../../vendor/skeletorJS/src/ContentEditor/BaseModule.js";
export default class Excerpt extends BaseModule {

    #setupComplete = false;
    text;

    constructor({eventEmitter}) {
        super({eventEmitter});
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.text = document.getElementById('excerpt');
        if (!this.text) {
            return;
        }
        if (this.isReadOnly()) {
            this.text.disabled = true;
        }
        this.#setupComplete = true;
    }

    getData() {
        return this.text.value;
    }

    setData(value) {
        if(this.text) {
            this.text.value = value;
        }
    }


    destroy() {
        super.destroy();
        this.text = null;
        this.#setupComplete = false;
    }
}
