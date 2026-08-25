import EventEmitter from "../../EventEmitter/EventEmitter.js";
import {documentsInFormSelectors} from "./documentsInFormSelectors.js";
import {events} from "./events.js";
import {events as mediaLibraryEvents} from "../events.js";
import DocumentInDocuments from "./DocumentInDocuments.js";
import Message from "../../Message/Message.js";
import Translator from "../../Translator/Translator.js";

export default class DocumentsInForm {
    #container;
    #inputName;
    #documentsContainer;
    #documents = new Map();
    #eventEmitter = new EventEmitter();
    #readOnly;

    constructor(container) {
        this.#container = container;
        this.#inputName = container.getAttribute(documentsInFormSelectors.attributes.documentsInputName);
        this.#documentsContainer = container.querySelector(`.${documentsInFormSelectors.classes.documentsContainer}`);
        this.#readOnly = container.getAttribute(documentsInFormSelectors.attributes.readOnly);
    }

    init() {
        this.#initExistingDocuments();
        this.#addListeners();
        this.#listenToDocumentReadyForDelete();
        this.#listenForMediaReadyForInsert();
    }

    #initExistingDocuments() {
        const documentContainers = this.#container.querySelectorAll(`.${documentsInFormSelectors.classes.document}`);
        documentContainers.forEach(documentContainer => {
            const document = new DocumentInDocuments({
                container: documentContainer,
                inputName: this.#inputName,
                eventEmitter: this.#eventEmitter
            });
            document.init();
            this.#documents.set(document.getId(), document);
        });
    }

    #addListeners() {
        this.#container.addEventListener('click', this.#handleDocumentsSelect);
    }

    #handleDocumentsSelect = () => {
        window.mediaLibrary.open(this.#container);
    }

    #listenToDocumentReadyForDelete() {
        this.#eventEmitter.on(events.documentReadyForDelete, this.#handleDocumentReadyForDelete);
    }

    #handleDocumentReadyForDelete = (data) => {
        const document = this.#documents.get(data.id.toString());
        document.destroy();
        this.#documents.delete(data.id);
    }

    #listenForMediaReadyForInsert() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
    }

    #handleMediaReadyForInsert = (data) => {
        if (data.initiator === this.#container) {
            Message.removeMessages(this.#container);
            data.mediaData.forEach(mediaData => {
                if (this.#documents.has(mediaData.id.toString())) {
                    Message.spawn({
                        message: `${Translator.translate('Document')} ${mediaData.filename} ${Translator.translate('already exists in documents')}`,
                        type: Message.TYPES.WARNING,
                        view: {
                            container: this.#container,
                        },
                    });
                    return;
                }
                const document = new DocumentInDocuments({
                    container: DocumentInDocuments.generateHTML({
                        id: mediaData.id,
                        src: mediaData.filename,
                        mimeType: mediaData.mimeType
                    }),
                    inputName: this.#inputName,
                    eventEmitter: this.#eventEmitter
                });
                document.init();
                this.#documents.set(document.getId().toString(), document);
                this.#documentsContainer.appendChild(document.getView());
            });
        }
    }



    destroy() {
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        window.mediaLibrary.eventEmitter.remove(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
        this.#inputName = null;
        this.#documents.forEach(document => document.destroy());
        this.#documents.clear();
        this.#documents = null;
        this.#container.removeEventListener('click', this.#handleDocumentsSelect);
        this.#container = null;
        this.#documentsContainer = null;
        this.#handleDocumentsSelect = null;
        this.#handleDocumentReadyForDelete = null;
        this.#handleMediaReadyForInsert = null;
    }
}