import {imageInFormSelectors} from "./imageInFormSelectors.js";
import {mediaLibrarySelectors} from "../mediaLibrarySelectors.js";
import {events} from "../events.js";
import MediaLibrary from "../MediaLibrary.js";

export default class ImageInForm {


    #container;
    #inputName;
    #input;
    #previewContainer;
    #removeImageButton;
    #readOnly;
    constructor(container) {
        this.#container = container;
        this.#previewContainer = this.#container.querySelector(`.${imageInFormSelectors.classes.imagePreview}`);
        this.#inputName = container.getAttribute(imageInFormSelectors.attributes.imageInputName);
        this.#input = this.#container.querySelector(`input`);
        this.#input.name = this.#inputName;
        this.#removeImageButton = this.#container.querySelector(`.${imageInFormSelectors.classes.removeImageButton}`);
        this.#readOnly = this.#container.getAttribute(imageInFormSelectors.attributes.readOnly);
    }

    #validateProperties() {
        if(!this.#container) {
            throw new Error('Container not found for ImageInForm');
        }
        if(!this.#inputName) {
            throw new Error('Input name not found for ImageInForm');
        }
        if(!this.#input) {
            throw new Error('Input not found for ImageInForm');
        }
        if(!this.#previewContainer) {
            throw new Error('Preview container not found for ImageInForm');
        }
        if(!this.#removeImageButton) {
            throw new Error('Remove button not found for ImageInForm');
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
        this.#container.addEventListener('click', this.#handleImageSelect);
        this.#removeImageButton.addEventListener('click', this.#handleRemoveImage);
    }

    #listenForMediaReadyForInsert() {
        window.mediaLibrary.eventEmitter.on(events.mediaReadyForInsert, this.#handleMediaReadyForInsert);
    }

    #handleMediaReadyForInsert = (data) => {
        if(data.initiator === this.#container) {
            this.#input.value = data.mediaData[0].id;
            this.#input.dispatchEvent(new Event('change'));
            const existingImage = this.#previewContainer.querySelector('img');
            if(existingImage) {
                existingImage.remove();
            }
            this.#previewContainer.appendChild(ImageInForm.getImagePreview(`${MediaLibrary.imagePath}${data.mediaData[0].filename}`));
        }
    }

    static getImagePreview(src) {
        const image = document.createElement('img');
        image.src = src;
        return image;
    }

    #handleRemoveImage = (e) => {
        e.stopPropagation();
        this.#input.value = '';
        this.#input.dispatchEvent(new Event('change'));
        this.#previewContainer.querySelector('img').remove();
    }

    #handleImageSelect = () => {
       window.mediaLibrary.open(this.#container);
    }

    static generateHTML({inputName, label, chooseImageText, imageId, src}) {
        const container = document.createElement('div');
        container.classList.add(mediaLibrarySelectors.classes.initiator, imageInFormSelectors.classes.imageSelect);

        container.setAttribute(`${imageInFormSelectors.attributes.imageInputName}`, inputName);
        container.setAttribute(`${mediaLibrarySelectors.attributes.insertable}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.multiple}`, 'false');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowImages}`, 'true');
        container.setAttribute(`${mediaLibrarySelectors.attributes.allowDocuments}`, 'false');

        const labelElement = document.createElement('label');
        labelElement.textContent = label;

        const imagePreview = document.createElement('div');
        imagePreview.classList.add(imageInFormSelectors.classes.imagePreview);

        const input = document.createElement('input');
        input.name = inputName;
        input.type = 'text';

        if(imageId && src) {
            imagePreview.appendChild(ImageInForm.getImagePreview(`${MediaLibrary.imagePath}${src}`));
            input.value = imageId;
        }
        const chooseImageTextElement = document.createElement('span');
        chooseImageTextElement.classList.add(imageInFormSelectors.classes.chooseImageText);
        chooseImageTextElement.textContent = chooseImageText;
        chooseImageTextElement.insertAdjacentHTML('beforeend', `
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                    <path d="M149.1 64.8L138.7 96H64C28.7 96 0 124.7 0 160V416c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V160c0-35.3-28.7-64-64-64H373.3L362.9 64.8C356.4 45.2 338.1 32 317.4 32H194.6c-20.7 0-39 13.2-45.5 32.8zM256 192a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/>
                                </svg>`);

        imagePreview.appendChild(chooseImageTextElement);
        imagePreview.insertAdjacentHTML('beforeend', `
                                <div class="removeImage">
                                     <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c9.4-9.4 24.6-9.4 33.9 0l47 47 47-47c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-47 47 47 47c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-47-47-47 47c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l47-47-47-47c-9.4-9.4-9.4-24.6 0-33.9z"/></svg>
                                </div> `);

        imagePreview.appendChild(input);

        container.appendChild(labelElement);
        container.appendChild(imagePreview);
        return container;
    }

    getContainer() {
        return this.#container;
    }

    getImageId() {
        return this.#input.value;
    }

    getImageSrc() {
        return this.#previewContainer.querySelector('img').src;
    }

    destroy() {
        this.#container.removeEventListener('click', this.#handleImageSelect);
        this.#handleImageSelect = null;
        window.mediaLibrary.eventEmitter.remove(events.mediaReadyForInsert, this.#handleMediaReadyForInsert);
        this.#handleMediaReadyForInsert = null;
        this.#container = null;
        this.#inputName = null;
        this.#input = null;
        this.#previewContainer = null;
        this.#removeImageButton.removeEventListener('click', this.#handleRemoveImage);
        this.#handleRemoveImage = null;
        this.#removeImageButton = null;
    }
}