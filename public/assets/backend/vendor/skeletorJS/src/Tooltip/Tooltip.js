import {tooltipSelectors} from "./tooltipSelectors.js";

export default class Tooltip {

    #container;
    #triggerElement;
    #message;
    constructor(container) {
        this.#container = container;
    }

    init() {
        try {
            this.#setProperties();
            this.#addListeners();
        } catch (error) {
            console.error(error);
        }

    }

    #setProperties() {
        this.#triggerElement = this.#container.querySelector(`.${tooltipSelectors.classes.trigger}`);
        this.#message = this.#container.querySelector(`.${tooltipSelectors.classes.content}`);
        if(!this.#triggerElement || !this.#message) {
            throw new Error('Tooltip trigger or message not found');
        }
    }

    #addListeners() {
        //@todo check if works on mobile
        this.#triggerElement.addEventListener('mouseenter', this.#showTooltip);
        this.#triggerElement.addEventListener('mouseleave', this.#hideTooltip);
    }

    #showTooltip = () => {
        this.#message.classList.add(tooltipSelectors.classes.active);
    }

    #hideTooltip = () => {
        this.#message.classList.remove(tooltipSelectors.classes.active);
    }


    destroy() {
        this.#triggerElement.removeEventListener('mouseenter', this.#showTooltip);
        this.#triggerElement.removeEventListener('mouseleave', this.#hideTooltip);
        this.#showTooltip = null;
        this.#hideTooltip = null;
        this.#triggerElement = null;
        this.#message = null;
    }
}