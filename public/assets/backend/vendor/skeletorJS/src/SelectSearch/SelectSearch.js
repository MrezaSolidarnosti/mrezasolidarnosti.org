import {selectSearchSelectors} from "./selectSearchSelectors.js";
import Search from "./Search.js";

export default class SelectSearch {
    #container;
    #select;
    #overlay;
    #search = null;
    #searchPlaceholder;
    #readOnly;
    constructor(container, searchPlaceholder) {
        try {
            this.#container = container;
            this.#searchPlaceholder = searchPlaceholder;
            this.#setProperties();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#select = this.#container.querySelector('select');
        this.#overlay = this.#container.querySelector(`.${selectSearchSelectors.classes.selectSearchOverlay}`);
        if(!this.#select) {
            throw new Error('Select element not found in container');
        }
        if(!this.#overlay) {
            throw new Error('Overlay element not found in container');
        }
        this.#readOnly = this.#container.getAttribute(selectSearchSelectors.attributes.readOnly);
    }

    init() {
        if(!this.#readOnly) {
            this.#addListeners();
        }
    }

    #addListeners() {
        //@todo check if works on mobile
        this.#overlay.addEventListener('click', this.#handleClick);
    }

    #handleClick = () => {
        if(this.#search) {
            this.#search.destroy();
        }
        this.#search = new Search({
            select: this.#select,
            container: this.#container,
            searchPlaceholder: this.#searchPlaceholder ?? null
        });
        this.#search.generateSearch();
        this.#search = null;
    }

    static generateHTML(name, options, label = null, className = null, defaultValue = null) {
        if(!name) {
            throw new Error('Name not provided');
        }
        if(!options) {
            throw new Error('Options not provided');
        }
        const container = document.createElement('div');
        container.classList.add(selectSearchSelectors.classes.selectSearchContainer);
        const select = document.createElement('select');
        select.name = name;
        select.classList.add(selectSearchSelectors.classes.input);
        if(defaultValue) {
            const option = document.createElement('option');
            option.value = Object.keys(defaultValue)[0];
            option.textContent = Object.values(defaultValue)[0].toString();
            select.appendChild(option);
        }
        Object.entries(options).map(([value, text]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            select.appendChild(option);
        });
        if(label) {
            const labelElement = document.createElement('label');
            labelElement.textContent = label;
            container.appendChild(labelElement);
        }
        container.appendChild(select);
        if(className) {
            container.classList.add(className);
        }
        const overlay = document.createElement('div');
        overlay.classList.add(selectSearchSelectors.classes.selectSearchOverlay);
        container.appendChild(overlay);
        return container;
    }

    destroy() {
        this.#overlay.removeEventListener('click', this.#handleClick);
        this.#container = null;
        this.#select = null;
        this.#overlay = null;
        this.#handleClick = null;
        if(this.#search) {
            this.#search.destroy();
        }
        this.#search = null;
        this.#searchPlaceholder = null;
    }
}