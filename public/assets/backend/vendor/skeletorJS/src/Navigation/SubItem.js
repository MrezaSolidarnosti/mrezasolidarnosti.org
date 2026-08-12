import {navigationSelectors} from "./navigationSelectors.js";

export default class SubItem {

    #item;
    #href;
    constructor(item) {
        this.#item = item
        this.#href = this.#item.getAttribute(navigationSelectors.attributes.href);
        if(this.#href) {
            this.#addHrefListener();
        }
    }

    #addHrefListener() {
        this.#item.addEventListener('click', this.#itemClickCallback);
        this.#item.addEventListener('mouseup', this.#mouseUpCallback)
    }

    #itemClickCallback = () => {
        window.location.href = this.#href;
    }

    #mouseUpCallback = (e) => {
        if(this.#href && e.which === 2) {
            window.open(this.#href, '_blank');
        }
    }

    filter(searchValue) {
        if(searchValue === '') {
            this.#item.classList.remove(navigationSelectors.classes.hidden);
            return true;
        }
        if(this.#item.textContent.toLowerCase().includes(searchValue.toLowerCase())) {
            this.#item.classList.remove(navigationSelectors.classes.hidden);
            return true;
        }
        this.#item.classList.add(navigationSelectors.classes.hidden);
        return false;
    }

    destroy() {
        this.#item.removeEventListener('click', this.#itemClickCallback);
        this.#item.removeEventListener('mouseup', this.#mouseUpCallback);
        this.#item = null;
        this.#href = null;
    }
}