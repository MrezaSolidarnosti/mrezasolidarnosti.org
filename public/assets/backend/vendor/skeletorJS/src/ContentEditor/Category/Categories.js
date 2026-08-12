import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Category from "./Category.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";

export default class Categories extends BaseModule {
    #setupComplete = false;
    container = null;
    searchInput = null;
    categories = [];

    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();
        if(!this.container || !this.searchInput) {
            return;
        }
        this.#setCategories();
        this.#addListeners();

        this.#setupComplete = true;
    }


    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.categoriesContainer);
        this.searchInput = document.getElementById(contentEditorSelectors.ids.searchCategoriesInput);
    }

    #setCategories() {
        const categories = this.container.querySelectorAll(`.${contentEditorSelectors.classes.categoryContainer}`);
        if(categories) {
            categories.forEach((category) => {
                const cat = new Category({
                    container:category,
                    eventEmitter: this.eventEmitter,
                    readOnly: this.isReadOnly()
                });
                cat.init();
                this.categories.push(cat);
                this.eventEmitter.emit(events.categoryRegistered, {category: cat});
            });
        }
    }


    #addListeners() {
        if(this.isReadOnly()) {
            this.searchInput.disabled = true;
            return;
        }
        this.searchInput.addEventListener('input', this.#handleSearchInput);
    }

    #handleSearchInput = () => {
        const value = this.searchInput.value.trim();
        if(value) {
            this.filterCategories(value);
        } else {
            this.resetCategories();
        }
    }


    filterCategories(val) {
        this.categories.forEach((category) => {
            category.handleVisibilityBasedOnName(val);
        });
    }

    resetCategories() {
        this.categories.forEach((category) => {
            category.resetVisibility();
        });
    }


    getCategoryParent(category) {
        let parent = null;
        this.categories.forEach((topLevelCat) => {
            if(!parent) {
                const parentCat = topLevelCat.getParentForCategoryInTree(category);
                if(parentCat) {
                    parent = parentCat;
                }
            }
        });
        return parent;
    }

    isCategorySelected() {
        return !!document.querySelector(`#${contentEditorSelectors.ids.categoriesContainer} input:checked`);
    }

    getSelectedCategoryIds() {
        const selected = document.querySelectorAll(
            `#${contentEditorSelectors.ids.categoriesContainer} input:checked`
        );
        const ids = [];
        selected.forEach((selectedElement) => {
            ids.push(parseInt(selectedElement.value, 10));
        });
        return ids;
    }

    setSelectedCategoryIds(ids) {
        if(!Array.isArray(ids)) {
            return;
        }
        this.categories.forEach((category) => {
            category.setSelectedCategoryIds(ids);
        });
    }

    destroy() {
        super.destroy();
        if(this.categories) {
            this.categories.forEach((category) => {
               category.destroy();
            });
            this.categories = null;
        }
        if(this.searchInput) {
            this.searchInput.removeEventListener('input', this.#handleSearchInput);
        }
    }
}