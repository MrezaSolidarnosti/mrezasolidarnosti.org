import {galleryInFormSelectors} from "./galleryInFormSelectors.js";
import Image from "./Image.js";
import EventEmitter from "../../EventEmitter/EventEmitter.js";
import {events as mediaLibraryEvents} from "../events.js";
import {events} from "./events.js";
import Message from "../../Message/Message.js";
import {mediaLibrarySelectors} from "../mediaLibrarySelectors.js";
import Translator from "../../Translator/Translator.js";

export default class GalleryInForm {
    #container;
    #inputName;
    #imagesContainer;
    #images = new Map();
    #eventEmitter = new EventEmitter();
    #readOnly;

    constructor(container) {
        this.#container = container;
        this.#inputName = container.getAttribute(galleryInFormSelectors.attributes.galleryInputName);
        this.#imagesContainer = container.querySelector(`.${galleryInFormSelectors.classes.galleryImagesContainer}`);
        this.#readOnly = container.hasAttribute(galleryInFormSelectors.attributes.readOnly);
    }

    init() {
        this.#initExistingImages();
        if(!this.#readOnly) {
            this.#addListeners();
            this.#listenToImageReadyForDelete();
            this.#listenForMediaReadyForInsert();
        }
    }

    #initExistingImages() {
        const imageContainers = this.#container.querySelectorAll(`.${galleryInFormSelectors.classes.galleryImage}`);
        imageContainers.forEach(imageContainer => {
            const image = new Image({
                container: imageContainer,
                inputName: this.#inputName,
                eventEmitter: this.#eventEmitter,
                readOnly: this.#readOnly
            });
            image.init();
            this.#images.set(image.getId(), image);
        });
    }

    #addListeners() {
        this.#container.addEventListener('click', this.#handleGallerySelect);
    }

    #handleGallerySelect = () => {
        window.mediaLibrary.open(this.#container);
    }

    #listenToImageReadyForDelete() {
        this.#eventEmitter.on(events.imageReadyForDelete, this.#handleImageReadyForDelete);
    }

    #handleImageReadyForDelete = (data) => {
        const image = this.#images.get(data.id.toString());
        image.destroy();
        this.#images.delete(data.id);
    }

    #listenForMediaReadyForInsert() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
    }

    #handleMediaReadyForInsert = (data) => {
        if(data.initiator === this.#container) {
            Message.removeMessages(this.#container);
            data.mediaData.forEach(mediaData => {
                if(this.#images.has(mediaData.id.toString())) {
                    Message.spawn({
                        message: `${Translator.translate('Image')} ${mediaData.filename} ${Translator.translate('already exists in gallery')}`,
                        type: Message.TYPES.WARNING,
                        view: {
                            container: this.#container,
                        }
                    });
                    return;
                }
                const image = new Image({
                    container: Image.generateHTML({id: mediaData.id, src: mediaData.filename}),
                    inputName: this.#inputName,
                    eventEmitter: this.#eventEmitter
                });
                image.init();
                this.#images.set(mediaData.id.toString(), image);
                this.#imagesContainer.appendChild(image.getView());
            });
        }
    }


    static generateHTML({inputName, data}) {
        const container = document.createElement('div');
        container.classList.add(mediaLibrarySelectors.classes.initiator, galleryInFormSelectors.classes.gallerySelect);

        container.setAttribute(`${galleryInFormSelectors.attributes.galleryInputName}`, inputName);
        container.setAttribute(`${mediaLibrarySelectors.attributes.insertable}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.multiple}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowImages}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowDocuments}`, 'false');

        const imagesContainer = document.createElement('div');
        imagesContainer.classList.add(galleryInFormSelectors.classes.galleryImagesContainer);
        imagesContainer.innerHTML = `<div class="${galleryInFormSelectors.classes.chooseText}">                       
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M0 96C0 60.7 28.7 32 64 32H448c35.3 0 64 28.7 64 64V416c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V96zM323.8 202.5c-4.5-6.6-11.9-10.5-19.8-10.5s-15.4 3.9-19.8 10.5l-87 127.6L170.7 297c-4.6-5.7-11.5-9-18.7-9s-14.2 3.3-18.7 9l-64 80c-5.8 7.2-6.9 17.1-2.9 25.4s12.4 13.6 21.6 13.6h96 32H424c8.9 0 17.1-4.9 21.2-12.8s3.6-17.4-1.4-24.7l-120-176zM112 192a48 48 0 1 0 0-96 48 48 0 1 0 0 96z"></path></svg>
                                </div>`;

        if(data) {
            data.data.forEach((imageData) => {
                const image = Image.generateHTML({id: imageData.imageId, src: imageData.filename});
                imagesContainer.appendChild(image);
            });
        }
        container.appendChild(imagesContainer);
        return container;
    }

    getImages() {
        return this.#images;
    }
    destroy() {
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        window.mediaLibrary.eventEmitter.remove(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
        this.#inputName = null;
        this.#images.forEach(image => image.destroy());
        this.#images.clear();
        this.#images = null;
        this.#container.removeEventListener('click', this.#handleGallerySelect);
        this.#container = null;
        this.#imagesContainer = null;
        this.#handleGallerySelect = null;
        this.#handleImageReadyForDelete = null;
        this.#handleMediaReadyForInsert = null;
    }
}