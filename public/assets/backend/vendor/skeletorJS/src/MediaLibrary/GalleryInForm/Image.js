import {galleryInFormSelectors} from "./galleryInFormSelectors.js";
import {events} from "./events.js";
import MediaLibrary from "../MediaLibrary.js";

export default class Image {

    #container;
    #inputName;
    #removeImageButton;
    #input;
    #eventEmitter;
    #readOnly;
    constructor({container, inputName, eventEmitter, readOnly}) {
        this.#container = container;
        this.#inputName = inputName;
        this.#eventEmitter = eventEmitter;
        this.#readOnly = readOnly;
    }


    init() {
        try {
            this.#setProperties();
            this.#setAttributes();
            if(!this.#readOnly) {
                this.#addListeners();
            }
        } catch(e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#removeImageButton = this.#container.querySelector(`.${galleryInFormSelectors.classes.removeImageButton}`);
        this.#input = this.#container.querySelector('input');
    }

    #setAttributes() {
        this.#input.name = this.#inputName;
    }

    #addListeners() {
        this.#removeImageButton.addEventListener('click', this.#handleRemoveImage);
    }

    #handleRemoveImage = (e) => {
        e.stopPropagation();
        this.#eventEmitter.emit(events.imageReadyForDelete, {id: this.getId()});
    }

    getView() {
        return this.#container;
    }

    getId() {
        return this.#input.value;
    }

    getSrc() {
        return this.#container.querySelector('img').src;
    }

    static generateHTML({id = null, src = null}) {
        const container = document.createElement('div');
        container.classList.add(galleryInFormSelectors.classes.galleryImage);
        container.innerHTML = `
                <input type="hidden" value="${id ?? ''}">
                <div class="removeImage">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>
                </div>`;
        if(src) {
            const img = document.createElement('img');
            img.src = `${MediaLibrary.imagePath}${src}`;
            container.appendChild(img);
        }
        return container;
    }

    destroy() {
        this.#removeImageButton.removeEventListener('click', this.#handleRemoveImage);
        this.#handleRemoveImage = null;
        this.#inputName = null;
        this.#removeImageButton = null;
        this.#input = null;
        this.#eventEmitter = null;
        this.#container.remove();
        this.#container = null;
    }
}