import {modalSelectors} from "./modalSelectors.js";

export default class Modal {

    #container;
    #closeButton;
    #content;
    #width;
    #height;
    #removeContentOnClose;
    #destroyOnClose;
    #lockScroll;
    beforeHideCallback;
    afterHideCallback;

    constructor({
                width = 'max-content',
                height = 'max-content',
                removeContentOnClose = true,
                destroyOnClose = false,
                lockScroll = true,
                beforeHideCallback = () => {},
                afterHideCallback =() => {}} = {}
        ) {
        this.#width = width;
        this.#height = height;
        this.#removeContentOnClose = removeContentOnClose;
        this.#destroyOnClose = destroyOnClose;
        this.#lockScroll = lockScroll;
        this.beforeHideCallback = beforeHideCallback;
        this.afterHideCallback = afterHideCallback;
    }

    getView() {
        if(!this.#container) {
            this.#generateView();
        }
        return this.#container;
    }

    getContentElement() {
        return this.#content;
    }

    #generateView() {
        this.#container = document.createElement('dialog');
        this.#container.style.width = this.#width;
        this.#container.style.height = this.#height;
        this.#closeButton = document.createElement('div');
        this.#closeButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"></path>
            </svg>`;
        this.#closeButton.classList.add(modalSelectors.classes.closeModal);
        this.#container.appendChild(this.#closeButton);
        this.#content = document.createElement('div');
        this.#container.appendChild(this.#content);
        this.#addListeners();
    }

    #addListeners() {
        this.#container.addEventListener('cancel', this.hide);
        this.#closeButton.addEventListener('click', this.hide);
        this.#container.appendChild(this.#closeButton);
    }

    show(content = null) {
        if(content) {
            this.#content.innerHTML = content;
        }
        if(this.#lockScroll) {
            document.body.style.overflow = 'hidden';
        }
        this.#container.showModal();
    }

    populate(content) {
        this.#content.innerHTML = content;
    }


    populateWithElement(element) {
        this.#content.innerHTML = '';
        this.#content.appendChild(element);
    }

    hide = () => {
        this.beforeHideCallback();
        if(this.#removeContentOnClose) {
            this.#content.innerHTML = '';
        }
        this.#container.close();
        this.afterHideCallback();
        if(this.#lockScroll) {
            document.body.style.removeProperty('overflow');
        }
        if(this.#destroyOnClose) {
            this.destroy();
        }
    }

    destroy() {
        this.#width = null;
        this.#height = null;
        this.#removeContentOnClose = null;
        this.#closeButton.removeEventListener('click', this.hide);
        this.#container.removeEventListener('cancel', this.hide);
        this.hide = null;
        this.beforeHideCallback = null;
        this.afterHideCallback = null;
        this.#container.remove();
        this.#container = null;
        this.#destroyOnClose = null;
    }
}