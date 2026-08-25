import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";

export default class Category {

    container;
    eventEmitter;
    id;
    #setupComplete = false;
    labelElement = null;
    input = null;
    level = 1;
    name = null;
    categories = [];
    selected = false;
    readOnly;

    constructor({container, eventEmitter, readOnly}) {
        this.container = container;
        this.eventEmitter = eventEmitter;
        this.readOnly = readOnly;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#setProperties();
        this.#addListeners();
        this.#setSubCategories();
        this.#setupComplete = true;

    }

    #setElements() {
        this.labelElement = this.container.querySelector(`.${contentEditorSelectors.classes.categoryCheckboxLabel}`);
        if(!this.labelElement) {
            throw new Error(`.${contentEditorSelectors.classes.categoryCheckboxLabel} not found in category`);
        }
        this.input = this.container.querySelector('input');
        if(!this.input) {
            throw new Error(`input not found in category`);
        }
    }


    #setProperties() {
        this.level = parseInt(this.container.getAttribute(contentEditorSelectors.attributes.categoryLevel), 10);
        this.name = this.container.getAttribute(contentEditorSelectors.attributes.categoryName);
        if(this.input.checked) {
            this.selected = true;
        }
        this.id = this.input.value;
    }

    #addListeners() {
        if(this.readOnly) {
            this.input.disabled = true;
            return;
        }
        this.labelElement.addEventListener('click', this.#handleLabelClick);
        this.input.addEventListener('change', this.#handleCheckboxChange);
    }

    #handleLabelClick = () => {
        this.input.checked = !this.input.checked;
        this.selected = this.input.checked;
        this.input.dispatchEvent(
            new Event('change', { bubbles: false })
        );
    }

    #handleCheckboxChange = () => {
        this.eventEmitter.emit(events.categoryCheckboxChanged, {checked: this.input.checked, category: this});
    }


    #setSubCategories()  {
        const subCategories = this.container.querySelectorAll(`div[${contentEditorSelectors.attributes.categoryLevel}="${this.level + 1}"]`);
        if(subCategories) {
            subCategories.forEach((subCategory) => {
               const subCat = new Category({
                   container: subCategory,
                   eventEmitter: this.eventEmitter,
                   readOnly: this.readOnly
               });
               subCat.init();
               this.categories.push(subCat);
                this.eventEmitter.emit(events.categoryRegistered, {category: subCat});
            });
        }
    }


    handleVisibilityBasedOnName(term) {
        const search = (term || '').trim().toLowerCase();
        return this.#filterNode(this.container, search);
    }

    #filterNode(node, term) {
        const name = (node.getAttribute(contentEditorSelectors.attributes.categoryName) || '').toLowerCase();
        const selfMatch = term === '' || name.includes(term);

        let descendantMatch = false;
        for (const child of node.children) {
            if (!child.hasAttribute(contentEditorSelectors.attributes.categoryName)) {
                continue;
            }
            const childVisible = this.#filterNode(child, term);
            descendantMatch = descendantMatch || childVisible;
        }

        const visible = selfMatch || descendantMatch;
        if(visible) {
            node.classList.remove(contentEditorSelectors.classes.hidden);
        } else {
            node.classList.add(contentEditorSelectors.classes.hidden);
        }
        return visible;
    }

    resetVisibility() {
        this.container.classList.remove(contentEditorSelectors.classes.hidden);
        this.categories.forEach((category) => {
            category.resetVisibility();
        });
    }

    getParentForCategoryInTree(category) {
        let foundCatParent = false;
        this.categories.forEach((cat) => {
            if(!foundCatParent) {
                if (cat === category) {
                    foundCatParent = this;
                } else {
                    foundCatParent = cat.getParentForCategoryInTree(category);
                }
            }
        });
        return foundCatParent;
    }

    setSelectedCategoryIds(ids) {
        if(ids.includes(this.getId())) {
            this.select();
        }
        this.categories.forEach((category) => {
            category.setSelectedCategoryIds(ids);
        });
    }

    select() {
        this.#handleLabelClick();
    }

    isSelected() {
        return this.selected;
    }

    getId() {
        return parseInt(this.id, 10);
    }

    destroy() {
        if(this.categories) {
            this.categories.forEach((category) => {
                category.destroy();
            });
            this.categories = null;
        }
        this.labelElement.removeEventListener('click', this.#handleLabelClick);
        this.#handleLabelClick = null;
        this.eventEmitter = null;
    }
}