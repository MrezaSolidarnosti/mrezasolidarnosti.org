import {events} from "./events.js";
import {navigationSelectors} from "./navigationSelectors.js";
import SubItem from "./SubItem.js";

export default class Item {
    #itemElement;
    #itemAnchor;
    #arrow;
    #eventEmitter;
    #href;
    #customBehavior;
    #tooltip;
    #top;
    #left;
    #subItems = [];
    constructor(itemElement, eventEmitter) {
        this.#itemElement = itemElement;
        this.#eventEmitter = eventEmitter;
        this.init();
    }

    init() {
        this.#setProperties();
    }

    #setProperties() {
        this.#itemAnchor = this.#itemElement.querySelector(`.${navigationSelectors.classes.itemAnchor}`);
        this.#arrow = this.#itemElement.querySelector(`.${navigationSelectors.classes.arrow}`);
        this.#setSubItems();
        this.#setHref();
        this.#setCustomBehavior();
        this.#setTooltip();
        this.#addListeners();
    }

    #setSubItems() {
        const subItems = this.#itemElement.querySelectorAll(`.${navigationSelectors.classes.subItem}`);
        if(subItems && subItems.length > 0) {
            subItems.forEach((subItem) => {
               this.#subItems.push(new SubItem(subItem, this.#eventEmitter));
            });
        }
    }

    #setHref() {
        this.#href = this.#itemElement.getAttribute(navigationSelectors.attributes.href);
    }

    #setCustomBehavior() {
        this.#customBehavior = this.#itemElement.getAttribute(navigationSelectors.attributes.customBehavior);
    }

    #setPosition() {
        this.#top = this.#itemElement.getBoundingClientRect().top;
        this.#left = this.#itemElement.getBoundingClientRect().left;
    }

    #getPosition() {
        return {
            top: this.#itemElement.getBoundingClientRect().top,
            left: this.#itemElement.getBoundingClientRect().left
        }
    }
    #setTooltip() {
        this.#tooltip = this.#itemElement.querySelector(`.${navigationSelectors.classes.tooltip}`);
    }

    #addListeners() {
        this.#addClickListener();
        this.#addTooltipListener();
    }

    #addClickListener() {
        if(this.#customBehavior) {
            return;
        }
        if(this.#itemAnchor) {
            this.#itemAnchor.addEventListener('click', this.#itemClickCallback);
            this.#itemElement.addEventListener('mouseup', this.#mouseUpCallback)
        }
    }

    #itemClickCallback = (e) => {
        if (this.#href) {
            window.location.href = this.#href;
            return;
        }
        this.#itemElement.classList.toggle('active');
        if(this.#arrow) {
            this.#arrow.classList.toggle('active');
        }
        this.#eventEmitter.emit(events.ITEM_CLICKED);
    }

    #mouseUpCallback = (e) => {
        if(this.#href && e.which === 2) {
            window.open(this.#href, '_blank');
        }
    }

    #addTooltipListener() {
        if(this.#tooltip) {
            this.#itemAnchor.addEventListener('mouseenter', this.#itemMouseEnterCallback);
            this.#itemAnchor.addEventListener('mouseleave', this.#itemMouseLeaveCallback);
        }
    }

    #itemMouseEnterCallback = () => {
        const position = this.#getPosition();
        this.#tooltip.style.top = `${position.top + 3}px`;
        this.#tooltip.style.left = `${position.left + 44}px`;
        this.#tooltip.classList.add('active');
    }


    #itemMouseLeaveCallback = () => {
        this.#tooltip.classList.remove('active');
    }

    show() {
        this.#itemElement.classList.remove(navigationSelectors.classes.hidden);
    }

    hide() {
        this.#itemElement.classList.add(navigationSelectors.classes.hidden);
    }

    filter(searchValue) {
        if(searchValue === '' && this.#subItems.length === 0) {
            this.show();
            return true;
        }
        if(this.#subItems.length === 0) {
            if(this.#itemElement.innerText.toLowerCase().includes(searchValue.toLowerCase())) {
                this.show();
                return true;
            }
            this.hide();
            return false;
        }
        let numberOfShownSubItems = 0;
        this.#subItems.forEach((subItem) => {
            numberOfShownSubItems += subItem.filter(searchValue) ? 1 : 0;
        });
        if(numberOfShownSubItems === 0) {
            this.hide();
            return false;
        } else {
            this.show();
            return true;
        }
    }

    open() {
        this.#itemElement.classList.add('active');
        if(this.#arrow) {
            this.#arrow.classList.add('active');
        }
    }

    close() {
        this.#itemElement.classList.remove('active');
        if(this.#arrow) {
            this.#arrow.classList.remove('active');
        }
    }

    hasId(id) {
        return this.#itemElement.id === id;
    }

    destroy() {
        this.#itemAnchor.removeEventListener('click', this.#itemClickCallback);
        this.#itemElement.removeEventListener('mouseup', this.#mouseUpCallback);
        this.#itemAnchor.removeEventListener('mouseenter', this.#itemMouseEnterCallback);
        this.#itemAnchor.removeEventListener('mouseleave', this.#itemMouseLeaveCallback);
        this.#itemElement = null;
        this.#itemAnchor = null;
        this.#arrow = null;
        this.#eventEmitter = null;
        this.#href = null;
        this.#customBehavior = null;
        this.#tooltip = null;
        this.#top = null;
        this.#left = null;
        this.#subItems.forEach((subItem) => {
            subItem.destroy();
        });
        this.#subItems = null;
    }
};