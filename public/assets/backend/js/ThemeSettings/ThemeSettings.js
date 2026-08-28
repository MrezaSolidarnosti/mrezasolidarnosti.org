import Navigation from "./Navigation.js";
import Social from "./Social.js";
import Loader from "../../../../vendor/skeletorjs/src/Loader/Loader.js";


export default class ThemeSettings {

    #items;
    #contentElement;
    #activeItem;
    #loader = new Loader();
    constructor() {
        this.#setProperties();
    }


    #setProperties() {
        this.#items = document.querySelectorAll('#themeSettingsNavigation li');
        this.#contentElement = document.getElementById('themeSettingsContent');
    }

    init() {
        this.#addListeners();
        if(this.#items.length > 0) {
            void this.#handleItemClick({target: this.#items[0]})
        }
    }


    #addListeners() {
        this.#addItemClickListener();
    }

    #addItemClickListener() {
        this.#items.forEach(item => {
            item.addEventListener('click', this.#handleItemClick);
        });
    }

    #handleItemClick = async (e) => {
        if(e.target.dataset.endpoint) {
            this.removeActiveMenuItem();
            if(this.#activeItem) {
                this.#activeItem.destroy();
            }
            e.target.classList.add('active');
            this.clearContent();
            this.#loader.start(this.#contentElement);
            this.#activeItem = this.#getItem(e.target.dataset.type);
            if(this.#activeItem) {
                const content = await this.#getContent(e.target.dataset.endpoint);
                this.populateContent(content);
                this.#activeItem.init();
            }
            this.#loader.stop();
        }
    }

    removeActiveMenuItem() {
        this.#items.forEach(item => {
            item.classList.remove('active');
        });
    }

    #getItem(type) {
        switch(type) {
            case 'navigation':
                return new Navigation();
            case 'social':
                return new Social();
            default:
                return null;
        }
    }

    clearContent() {
        this.#contentElement.innerHTML = '';
    }

    populateContent(content) {
        this.#contentElement.innerHTML = content;
    }

    async #getContent(endpoint) {
        const res = await fetch(endpoint);
        return await res.text();
    }


    destroy() {
        this.#items.forEach(item => {
            item.removeEventListener('click', this.#handleItemClick);
        });
        this.#handleItemClick = null;
        this.#activeItem.destroy();
        this.#activeItem = null;
        this.#loader.destroy();
        this.#loader = null;
    }
}