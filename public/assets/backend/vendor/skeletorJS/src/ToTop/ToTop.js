import {toTopSelectors} from "./toTopSelectors.js";

export default class ToTop {

    #container;
    #toTopButton;
    #paramToCheck;

    constructor(container) {
        this.#container = container ?? window;
    }
    init() {
        try {
            this.#setProperties();
            this.#addListeners();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#paramToCheck = this.#container === window ? 'scrollY' : 'scrollTop';
        this.#toTopButton = document.getElementById(toTopSelectors.ids.toTopButton);
        if(!this.#toTopButton) {
            throw new Error(`${toTopSelectors.ids.toTopButton} not found`);
        }
    }

    #addListeners() {
        this.#toTopButton.addEventListener('click', this.#scrollToTop);
        this.#container.addEventListener('scroll', this.#showHideToTop);
    }

    #showHideToTop = () => {
        if(this.#container[`${this.#paramToCheck}`] >= 100) {
            if(!this.#toTopButton.classList.contains(toTopSelectors.classes.show)) {
                this.#toTopButton.classList.add(toTopSelectors.classes.show);
            }
        } else {
            if(this.#toTopButton.classList.contains(toTopSelectors.classes.show)) {
                this.#toTopButton.classList.remove(toTopSelectors.classes.show);
            }
        }
    }

    #scrollToTop = () => {
        if(this.#container) {
            this.#container.scroll({top: 0, left: 0, behavior: 'smooth'});
        } else {
            window.scrollTo({top: 0, left: 0, behavior: 'smooth'});
        }
    }


    destroy() {
        this.#toTopButton.removeEventListener('click', this.#scrollToTop);
        this.#scrollToTop = null;
        this.#toTopButton = null;
        this.#container.removeEventListener('scroll', this.#showHideToTop);
        this.#showHideToTop = null;
        this.#container = null;
        this.#paramToCheck = null;
    }

}