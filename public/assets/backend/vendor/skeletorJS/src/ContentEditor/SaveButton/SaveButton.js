import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";


export default class SaveButton extends BaseModule {

    #setupComplete = false;
    button;
    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();
        if(!this.button) {
            return;
        }
        this.#addListeners();

        this.#setupComplete = true;
    }


    #setElements() {
        this.button = document.getElementById(contentEditorSelectors.ids.saveButton);
    }

    #addListeners() {
        if(this.isReadOnly()) {
            this.button.classList.add(contentEditorSelectors.classes.disabled);
            return;
        }
        this.button.addEventListener('click', this.#handleSaveButtonClick);
    }

    #handleSaveButtonClick = () => {
        this.eventEmitter.emit(events.saveInitiated);
    }

    destroy() {
        super.destroy();
        if(this.button) {
            this.button.removeEventListener('click', this.#handleSaveButtonClick);
        }
    }
}