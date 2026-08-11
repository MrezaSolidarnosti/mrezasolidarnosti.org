import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";
import Loader from "../../Loader/Loader.js";


export default class SaveButton extends BaseModule {

    #setupComplete = false;
    button;
    loader = new Loader({
        size: '20px',
        thickness: '2px',
        trackColor: '#575757',
        innerTrackColor: '#a587fa'
    });
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

    saving() {
        this.loader.start(this.button, ['svg', 'span']);
        this.button.classList.add(contentEditorSelectors.classes.saving);
    }

    notSaving() {
        this.loader.stop(this.button, ['svg', 'span']);
        this.button.classList.remove(contentEditorSelectors.classes.saving);
    }

    destroy() {
        super.destroy();
        if(this.button) {
            this.button.removeEventListener('click', this.#handleSaveButtonClick);
        }
        this.loader.destroy();
    }
}