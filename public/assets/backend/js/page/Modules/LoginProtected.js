import BaseModule from "../../../../../vendor/skeletorjs/src/ContentEditor/BaseModule.js";
export default class LoginProtected extends BaseModule {

    #setupComplete = false;
    checkbox;

    constructor({eventEmitter, config}) {
        super({eventEmitter, config});
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.checkbox = document.getElementById('loginProtected');
        if (!this.checkbox) {
            return;
        }
        if (this.isReadOnly()) {
            this.checkbox.disabled = true;
        }
        this.#setupComplete = true;
    }

    getSelected() {
        return this.checkbox ? this.checkbox.checked : false;
    }

    setSelected(value) {
        if (this.checkbox) {
            this.checkbox.checked = Boolean(value);
        }
    }

    destroy() {
        super.destroy();
        this.checkbox = null;
        this.#setupComplete = false;
    }
}
