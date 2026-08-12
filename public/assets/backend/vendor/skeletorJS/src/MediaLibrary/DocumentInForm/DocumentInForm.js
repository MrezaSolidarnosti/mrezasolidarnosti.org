import {documentInFormSelectors} from "./documentInFormSelectors.js";
import {events} from "../events.js";
import Document from "../Cell/Document.js";
import {mediaLibrarySelectors} from "../mediaLibrarySelectors.js";
import {crudPageSelectors} from "../../Page/crudPageSelectors.js";

export default class DocumentInForm {
    #container;
    #inputName;
    #input;
    #previewContainer;
    #removeDocumentButton;
    #readOnly;
    constructor(container) {
        this.#container = container;
        this.#previewContainer = this.#container.querySelector(`.${documentInFormSelectors.classes.documentPreview}`);
        this.#inputName = container.getAttribute(documentInFormSelectors.attributes.documentInputName);
        this.#input = this.#container.querySelector(`input`);
        this.#input.name = this.#inputName;
        this.#removeDocumentButton = this.#container.querySelector(`.${documentInFormSelectors.classes.removeDocumentButton}`);
        this.#readOnly = container.getAttribute(documentInFormSelectors.attributes.readOnly);
    }
    #validateProperties() {
        if(!this.#container) {
            throw new Error('Container not found for DocumentInForm');
        }
        if(!this.#inputName) {
            throw new Error('Input name not found for DocumentInForm');
        }
        if(!this.#input) {
            throw new Error('Input not found for DocumentInForm');
        }
        if(!this.#previewContainer) {
            throw new Error('Preview container not found for DocumentInForm');
        }
        if(!this.#removeDocumentButton) {
            throw new Error('Remove button not found for DocumentInForm');
        }
    }
    init() {
        try {
            this.#validateProperties();
            if(!this.#readOnly) {
                this.#addListeners();
                this.#listenForMediaReadyForInsert();
            }
        } catch (e) {
            console.error(e);
        }
    }
    #addListeners() {
        this.#container.addEventListener('click', this.#handleDocumentSelect);
        this.#removeDocumentButton.addEventListener('click', this.#handleRemoveDocument);
    }
    #listenForMediaReadyForInsert() {
        window.mediaLibrary.eventEmitter.on(events.mediaReadyForInsert, this.#handleMediaReadyForInsert);
    }
    #handleMediaReadyForInsert = (data) => {
        if(data.initiator === this.#container) {
            this.#input.value = data.mediaData[0].id;
            this.#input.dispatchEvent(new Event('change'));
            let previewElement = this.#container.querySelector(`.${documentInFormSelectors.classes.documentPreviewElement}`);
            if (previewElement) {
                previewElement.remove();
            }
            previewElement = document.createElement('div');
            previewElement.classList.add(documentInFormSelectors.classes.documentPreviewElement);
            const icon = Document.generateIcon(data.mediaData[0].mimeType);
            previewElement.appendChild(icon);
            const text = document.createElement('span');
            text.textContent = data.mediaData[0].filename;
            previewElement.appendChild(text);
            previewElement.title = data.mediaData[0].filename;
            this.#previewContainer.appendChild(previewElement);
        }
    }
    #handleDocumentSelect = () => {
        window.mediaLibrary.open(this.#container);
    }
    #handleRemoveDocument = (e) => {
        e.stopPropagation();
        this.#input.value = '';
        this.#input.dispatchEvent(new Event('change'));
        const previewInput = this.#container.querySelector(`.${documentInFormSelectors.classes.documentPreviewElement}`);
        if(previewInput) {
            previewInput.remove();
        }
    }

    static generateHtml({inputName, label, chooseDocumentText}) {
        const container = document.createElement('div');
        container.classList.add(documentInFormSelectors.classes.initiator, documentInFormSelectors.classes.documentSelect);
        container.setAttribute(documentInFormSelectors.attributes.documentInputName, inputName);
        container.setAttribute(`${mediaLibrarySelectors.attributes.insertable}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.multiple}`, 'false');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowImages}`, 'false');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowDocuments}`, 'true');

        const labelElement = document.createElement('label');
        labelElement.textContent = label;


        container.appendChild(labelElement);

        const previewContainer = document.createElement('div');
        previewContainer.classList.add(documentInFormSelectors.classes.documentPreview);
        const chooseDocument = document.createElement('span');
        chooseDocument.textContent = chooseDocumentText;
        chooseDocument.classList.add(documentInFormSelectors.classes.chooseDocumentText);
        chooseDocument.insertAdjacentHTML('beforeend', `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                                        <path d="M64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V160H256c-17.7 0-32-14.3-32-32V0H64zM256 0V128H384L256 0zM112 256H272c8.8 0 16 7.2 16 16s-7.2 16-16 16H112c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H272c8.8 0 16 7.2 16 16s-7.2 16-16 16H112c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64H272c8.8 0 16 7.2 16 16s-7.2 16-16 16H112c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/>
                                    </svg>`);
        previewContainer.appendChild(chooseDocument);

        const input = document.createElement('input');
        input.classList.add(crudPageSelectors.classes.input);
        previewContainer.appendChild(input);
        previewContainer.insertAdjacentHTML('beforeend', `
                                 <div class="removeDocument">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>
                                </div>   `);
        container.appendChild(previewContainer);
        return container;
    }

    destroy() {
        this.#container.removeEventListener('click', this.#handleDocumentSelect);
        this.#removeDocumentButton.removeEventListener('click', this.#handleRemoveDocument);
        window.mediaLibrary.eventEmitter.remove(events.mediaReadyForInsert, this.#handleMediaReadyForInsert);
        this.#container = null;
        this.#inputName = null;
        this.#input = null;
        this.#previewContainer = null;
        this.#removeDocumentButton = null;
        this.#handleMediaReadyForInsert = null;
        this.#handleDocumentSelect = null;
        this.#handleRemoveDocument = null;
    }
}