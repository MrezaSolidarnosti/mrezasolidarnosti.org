import {mediaLibrarySelectors} from "./mediaLibrarySelectors.js";
import {events} from "./events.js";

export default class Initiator {

    #initiatorElement;
    #insertable
    #multiple
    #allowDocuments
    #allowImages
    constructor({initiatorElement}) {
        this.#initiatorElement = initiatorElement;
    }


    init() {
        this.#setAttributes();
        this.#addEventListeners();
    }

    #setAttributes() {
        this.#insertable = this.#initiatorElement.getAttribute(mediaLibrarySelectors.attributes.insertable) === 'true';
        this.#multiple = this.#initiatorElement.getAttribute(mediaLibrarySelectors.attributes.multiple) === 'true';
        this.#allowDocuments = this.#initiatorElement.getAttribute(mediaLibrarySelectors.attributes.allowDocuments) === 'true';
        this.#allowImages = this.#initiatorElement.getAttribute(mediaLibrarySelectors.attributes.allowImages) === 'true';
    }

    getAttributes() {
        return {
            insertable: this.#insertable,
            multiple: this.#multiple,
            allowDocuments: this.#allowDocuments,
            allowImages: this.#allowImages
        }
    }

    #addEventListeners() {
        this.#initiatorElement.addEventListener('click', this.#handleClick);
    }

    #handleClick = () => {
        window.mediaLibrary.open(this.#initiatorElement);
    }


    getInitiatorElement() {
        return this.#initiatorElement;
    }

    destroy() {
        this.#initiatorElement.removeEventListener('click', this.#handleClick);
        this.#handleClick = null;
        this.#initiatorElement = null;
        this.#insertable = null;
        this.#multiple = null;
        this.#allowDocuments = null;
        this.#allowImages = null;
    }
}