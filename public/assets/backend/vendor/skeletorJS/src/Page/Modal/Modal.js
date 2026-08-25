import {modalSelectors} from "./modalSelectors.js";
import {crudPageSelectors} from "../crudPageSelectors.js";
import Loader from "../../Loader/Loader.js";
import {config as modalConfig} from "./config.js";
import {events} from "./events.js";
export default class Modal {

    #config;
    #modalOverlay;
    #modal;
    #modalContent;
    #messageContainer;
    #closeModalButton;
    #initialized;
    #loader = new Loader();
    #submitButtonLoader = new Loader({size: '30px', thickness: '4px'});

    eventEmitter;
    interruptModalClose = () => {return true};
    constructor(config, eventEmitter) {
        this.#config = config;
        this.eventEmitter = eventEmitter;
        this.#cleanUpConfig();
        this.#parseConfig();
    }


    init() {
        if(this.#initialized) {
            return;
        }
        try {
            this.#setProperties();
            this.#addEventListeners();
            this.#initialized = true;
        } catch (e) {
            console.error(e);
        }
    }

    getConfigValue(key) {
        return this.#config[key];
    }

    #setProperties() {
        this.#modalOverlay = document.getElementById(modalSelectors.ids.modalOverlay);
        this.#modal = document.getElementById(modalSelectors.ids.modal);
        this.#modalContent = document.getElementById(modalSelectors.ids.modalContent);
        this.#messageContainer = document.getElementById(modalSelectors.ids.messageContainer);
        this.#closeModalButton = document.getElementById(modalSelectors.ids.closeModalButton);
        if(!this.#modalOverlay) {
            throw new Error(`${modalSelectors.ids.modalOverlay} is not found on the page.`);
        }
        if(!this.#modal) {
            throw new Error(`${modalSelectors.ids.modal} is not found on the page.`);
        }
        if(!this.#modalContent) {
            throw new Error(`${modalSelectors.ids.modalContent} is not found on the page.`);
        }
        if(!this.#messageContainer) {
            throw new Error(`${modalSelectors.ids.messageContainer} is not found on the page.`);
        }
        if(!this.#closeModalButton) {
            throw new Error(`${modalSelectors.ids.closeModalButton} is not found on the page.`);
        }
    }

    #addEventListeners() {
        this.#closeModalButton.addEventListener('click', this.#closeModalClickHandler);
        if(this.#config.closeOnEscape) {
            document.addEventListener('keydown', this.#closeModalOnEscapeHandler);
        }
        if(this.#config.closeOnClickOutsideOfModal) {
            this.#modalOverlay.addEventListener('mousedown', this.#closeModalOnOutsideClickHandler);
        }
    }

    #closeModalOnEscapeHandler = (e) => {
        if(this.isModalOpen() && e.code === 'Escape') {
            this.closeModal();
        }
    }

    #closeModalOnOutsideClickHandler = (e) => {
        if(e.target === this.#modalOverlay) {
            this.closeModal();
        }
    }

    #closeModalClickHandler = () => {
        this.closeModal();
    }

    #cleanUpConfig() {
        Object.keys(this.#config).forEach((key) => {
            if(typeof modalConfig[key] === 'undefined') {
                console.warn(`${key} is not supported as an option for modal config and will be ignored.`);
                delete this.#config[key];
            }
        });
    }

    #parseConfig() {
        Object.keys(modalConfig).forEach((key) => {
            if (this.#config && typeof this.#config !== 'undefined' && typeof this.#config[key] !== 'undefined') {
                this.#config[key] = this.#config[key];
            } else {
                this.#config[key] = modalConfig[key];
            }
        });
    }

    openModal(width = this.#config.width, height = this.#config.height) {
        this.emptyMessageContainer();
        this.#modalOverlay.classList.remove(crudPageSelectors.classes.hidden);
        this.#modal.style.width = width;
        this.#modal.style.height = height;
        document.body.classList.add(crudPageSelectors.classes.freeze);
    }

    closeModal() {
        if(window.mediaLibrary && window.mediaLibrary.isOpen()) {
            window.mediaLibrary.close();
            return;
        }
        if(!this.interruptModalClose()) {
            return;
        }
        this.eventEmitter.emit(events.modalBeforeClose);
        const form = this.getForm();
        if(form) {
            form.removeEventListener('keydown', this.#formKeydownCallback);
        }
        this.#modalOverlay.classList.add(crudPageSelectors.classes.hidden);
        this.#modal.style.width = '0';
        this.#modal.style.height = '0';
        document.body.classList.remove(crudPageSelectors.classes.freeze);
        this.#modalContent.innerHTML = '';
        this.emptyMessageContainer();
        this.eventEmitter.emit(events.modalClosed);
    }

    startLoader() {
        this.#loader.start(this.#modalContent);
    }

    stopLoader() {
        this.#loader.stop();
    }

    isModalOpen() {
        return !this.#modalOverlay.classList.contains(crudPageSelectors.classes.hidden);
    }

    getMessageContainer() {
        return this.#messageContainer;
    }

    emptyMessageContainer() {
        this.#messageContainer.innerHTML = '';
    }

    populateModalContent(content) {
        this.#modalContent.innerHTML = content;
        const form = this.getForm();
        if(form) {
            form.addEventListener('keydown', this.#formKeydownCallback);
        }
    }

    populateModalContentStrict(content) {
        if(this.isModalOpen()) {
            this.populateModalContent(content);
        }
    }

    #formKeydownCallback = (e) => {
        if(e.keyCode === 13 && e.target.classList.contains(crudPageSelectors.classes.preventEnterSubmit)) {
            e.preventDefault();
        }
    }

    getForm() {
        return this.#modalContent.querySelector('form') ?? null;
    }

    getModalElement() {
        return this.#modal;
    }

    getSubmitButton() {
        return this.#modalContent.querySelector('input[type="submit"]') ?? null;
    }

    updateCSRFToken(tokenInputString) {
        let form = this.getForm();
        if(form) {
            let csrfInput= form.querySelector(`input[name^="${modalSelectors.attributes.csrfSuffix}"]`);
            if(csrfInput) {
                csrfInput.remove();
                form.insertAdjacentHTML('beforeend', tokenInputString);
            }
        }
    }

    startLoaderInSubmitButton() {
        this.#submitButtonLoader.start(this.#modalContent.querySelector(`#${modalSelectors.ids.submitButtonContainer}`),
            ['input[type="submit"]']);
    }

    stopLoaderInSubmitButton() {
        this.#submitButtonLoader.stop(this.#modalContent.querySelector(`#${modalSelectors.ids.submitButtonContainer}`),
            ['input[type="submit"]']);
    }

    scrollToTop() {
        this.getModalElement().scroll({top:0, behavior:'smooth'});
    }

    destroy() {
        const form = this.getForm();
        if(form) {
            form.removeEventListener('keydown', this.#formKeydownCallback);
        }
        this.#formKeydownCallback = null;
        this.#modal = null;
        this.#modalContent = null;
        this.#messageContainer = null;
        this.#closeModalButton.removeEventListener('click', this.#closeModalClickHandler);
        document.removeEventListener('keydown', this.#closeModalOnEscapeHandler);
        this.#closeModalButton = null;
        if(this.#config.closeOnClickOutsideOfModal) {
            this.#modalOverlay.removeEventListener('mousedown', this.#closeModalOnOutsideClickHandler);
        }
        this.#config = null;
        this.#initialized = false;
        this.#loader.destroy();
        this.#loader = null;
        this.#modalOverlay.remove();
        this.#modalOverlay = null;
        this.eventEmitter = null;
    }


}