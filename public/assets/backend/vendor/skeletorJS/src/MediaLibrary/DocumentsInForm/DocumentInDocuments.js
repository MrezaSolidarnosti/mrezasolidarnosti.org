import {events} from "./events.js";
import {documentsInFormSelectors} from "./documentsInFormSelectors.js";
import Document from "../Cell/Document.js";

export default class DocumentInDocuments {
    #container;
    #inputName;
    #removeDocumentButton;
    #input;
    #eventEmitter;
    constructor({container, inputName, eventEmitter}) {
        this.#container = container;
        this.#inputName = inputName;
        this.#eventEmitter = eventEmitter;
    }


    init() {
        try {
            this.#setProperties();
            this.#setAttributes();
            this.#addListeners();
        } catch(e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#removeDocumentButton = this.#container.querySelector(`.${documentsInFormSelectors.classes.removeDocumentButton}`);
        this.#input = this.#container.querySelector('input');
    }

    #setAttributes() {
        this.#input.name = this.#inputName;
    }

    #addListeners() {
        this.#removeDocumentButton.addEventListener('click', this.#handleRemoveDocument);
    }

    #handleRemoveDocument = (e) => {
        e.stopPropagation();
        this.#eventEmitter.emit(events.documentReadyForDelete, {id: this.getId()});
    }


    getView() {
        return this.#container;
    }

    getId() {
        return this.#input.value;
    }

    static generateHTML({id = null, src = null, mimeType = null}) {
        const container = document.createElement('div');
        container.classList.add(documentsInFormSelectors.classes.document);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.value = id ?? '';
        container.appendChild(input);
        const documentPreview = document.createElement('div');
        documentPreview.classList.add(documentsInFormSelectors.classes.documentPreview);
        documentPreview.title = src ?? '';
        const documentIcon = document.createElement('div');
        documentIcon.appendChild(Document.generateIcon(mimeType));
        documentPreview.appendChild(documentIcon);
        const documentName = document.createElement('span');
        documentName.textContent = src ?? '';
        documentPreview.appendChild(documentName);
        container.appendChild(documentPreview);
        container.insertAdjacentHTML('beforeend', `<div class="removeDocument">
                                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>
                                    </div>`);
        return container;
    }

    destroy() {
        this.#removeDocumentButton.removeEventListener('click', this.#handleRemoveDocument);
        this.#handleRemoveDocument = null;
        this.#inputName = null;
        this.#removeDocumentButton = null;
        this.#input = null;
        this.#eventEmitter = null;
        this.#container.remove();
        this.#container = null;
    }
}