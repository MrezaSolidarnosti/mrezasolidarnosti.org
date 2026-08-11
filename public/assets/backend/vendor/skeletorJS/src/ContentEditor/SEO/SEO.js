import BaseModule from "../BaseModule.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events as mediaLibraryEvents} from "../../MediaLibrary/events.js";
import Overlay from "../Overlay/Overlay.js";
import Dismissible from "../Dismissible/Dismissible.js";

export default class SEO extends BaseModule {
    #setupComplete = false;
    #dismissible = null;
    seoTitleInput = null;
    seoDescriptionInput = null;
    closeButton;
    seoButton;
    modal;
    imageId;
    imageSrc;
    imageContainer;
    seoImageContentContainer;
    imagePreview;

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#addListeners();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.modal.classList.contains(contentEditorSelectors.classes.active),
            close: () => this.#closePopup(),
        });
        this.#listenToEvents();
        this.#setupComplete = true;
    }

    #setElements() {
        this.seoTitleInput = document.getElementById(contentEditorSelectors.ids.seoTitleInput);
        this.#handleCounter(this.seoTitleInput);
        this.seoDescriptionInput = document.getElementById(contentEditorSelectors.ids.seoDescriptionInput);
        this.#handleCounter(this.seoDescriptionInput);
        this.closeButton = document.getElementById(contentEditorSelectors.ids.closeSeo);
        this.seoButton = document.getElementById(contentEditorSelectors.ids.seoButton);
        this.modal = document.getElementById(contentEditorSelectors.ids.seoModal);
        this.imageContainer = document.getElementById(contentEditorSelectors.ids.seoImageContainer);
        this.seoImageContentContainer = document.getElementById(contentEditorSelectors.ids.seoImageContentContainer);
        this.imagePreview = this.seoImageContentContainer.querySelector('img');
        if(this.isReadOnly()) {
            this.seoTitleInput.readOnly = true;
            this.seoDescriptionInput.readOnly = true;
            this.imageContainer.style.pointerEvents = 'none';
        }
    }

    #addListeners() {
        if(!this.isReadOnly()) {
            this.seoTitleInput.addEventListener('input', this.#handleCounter);
            this.seoDescriptionInput.addEventListener('input', this.#handleCounter);
        }
        this.seoButton.addEventListener('click', this.#togglePopup);
        this.closeButton.addEventListener('click', this.#closePopup);
    }

    #handleCounter = (e) => {
        let target = null;
        if(e instanceof Event) {
            target = e.target;
        }
        if(e instanceof HTMLElement) {
            target = e;
        }
        if(!target) {
            return;
        }
        const counter = target.parentElement.querySelector(`.${contentEditorSelectors.classes.seoCounterElement}`);
        counter.classList.remove('red', 'orange', 'yellow', 'green');
        const currentCharacters = counter.querySelector(`.${contentEditorSelectors.classes.seoCurrentCharacters}`);
        currentCharacters.textContent = target.value.length;
        counter.classList.add(this.#getColorForCount(
            target.value.length,
            counter.getAttribute(contentEditorSelectors.attributes.seoRed),
            counter.getAttribute(contentEditorSelectors.attributes.seoOrange),
            counter.getAttribute(contentEditorSelectors.attributes.seoYellow),
            counter.getAttribute(contentEditorSelectors.attributes.seoGreen),
        ));
    }

    #getColorForCount(count, red, orange, yellow, green) {
        const ranges = [
            { range: red, color: 'red'},
            { range: orange, color: 'orange' },
            { range: yellow, color: 'yellow' },
            { range: green, color: 'green' },
        ];

        const match = ranges.find(item => {
            const [min, max] = item.range.split("-").map(Number);
            return count >= min && count <= max;
        });

        return match?.color ?? 'red';
    }


    #togglePopup = () => {
        if(this.modal.classList.contains(contentEditorSelectors.classes.active)) {
            this.#closePopup();
            return;
        }
        this.#openPopup();
    }

    #closePopup = () => {
        this.modal.classList.remove(contentEditorSelectors.classes.active);
        this.seoButton.classList.remove(contentEditorSelectors.classes.active);
        Overlay.hideOverlay();
    }

    #openPopup = () => {
        this.modal.classList.add(contentEditorSelectors.classes.active);
        this.seoButton.classList.add(contentEditorSelectors.classes.active);
        Overlay.showOverlay();
    }

    #listenToEvents() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, (data) => {
            if(data.initiator === this.imageContainer) {
                if(data?.mediaData[0]?.filename) {
                    this.setImage(parseInt(data?.mediaData[0].id, 10), data?.mediaData[0].filename);
                }
            }
        });
    }

    getData() {
        return {
            title: this.getTitle(),
            description: this.getDescription(),
            image: this.getImage()
        }
    }

    getTitle() {
        return this.seoTitleInput.value;
    }

    getDescription() {
        return this.seoDescriptionInput.value;
    }

    getImage() {
        return {id: this.imageId ?? null, src: this.imageSrc ?? null};
    }

    setData({title, description, image}) {
        this.setTitle(title);
        this.setDescription(description);
        this.setImage(image.id, image.src);
    }

    setTitle(value) {
        this.seoTitleInput.value = value;
        this.#handleCounter(this.seoTitleInput);
    }

    setDescription(value) {
        this.seoDescriptionInput.value = value;
        this.#handleCounter(this.seoDescriptionInput);
    }

    setImage(id, src) {
        this.imageId = parseInt(id, 10);
        this.imageSrc = src;
        this.imagePreview.src = (this.config.imagePath ?? '') + this.imageSrc;
        this.imagePreview.classList.add(contentEditorSelectors.classes.active);
    }

    destroy() {
        this.seoTitleInput.removeEventListener('input', this.#handleCounter);
        this.seoDescriptionInput.removeEventListener('input', this.#handleCounter);
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.seoButton.removeEventListener('click', this.#togglePopup);
        this.closeButton.removeEventListener('click', this.#closePopup);
    }
}
