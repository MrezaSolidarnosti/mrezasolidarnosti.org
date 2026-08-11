import BaseModule from "../BaseModule.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";

export default class Slug extends BaseModule {

    #setupComplete = false;
    input;
    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        if(!this.input) {
            return;
        }
        this.#setupComplete = true;
    }

    #setElements() {
        this.input = document.getElementById(contentEditorSelectors.ids.slugInput);
        if(this.isReadOnly()) {
            this.input.readOnly = true;
        }
    }

    getData() {
        return this.input.value;
    }

    setValue(value) {
        this.input.value = value;
    }
}