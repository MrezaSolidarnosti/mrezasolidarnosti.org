import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Author from "./Author.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";

//@todo multiple authors or 1 author?
export default class Authors extends BaseModule {

    #setupComplete = false;
    authors = [];

    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();
        if(!this.container || !this.searchInput) {
            return;
        }
        this.#setAuthors();
        this.#addListeners();

        this.#setupComplete = true;
    }

    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.authorsContainer);
        this.searchInput = document.getElementById(contentEditorSelectors.ids.searchAuthorInput);
    }

    #setAuthors() {
        const authorContainers = this.container.querySelectorAll('label');
        authorContainers.forEach((authorContainer) => {
           const author = new Author({
               container: authorContainer,
               eventEmitter: this.eventEmitter,
               readOnly: this.isReadOnly()
           });
           author.init();
           this.authors.push(author);
           this.eventEmitter.emit(events.authorRegistered, {author});
        });
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
            this.filterAuthors(value);
        } else {
            this.resetAuthorsVisibility();
        }
    }

    filterAuthors(val) {
        this.authors.forEach((author) => {
           if(author.nameIncludes(val)) {
               author.show();
           } else {
               author.hide();
           }
        });
    }

    resetAuthorsVisibility() {
        this.authors.forEach((author) => {
           author.show();
        });
    }


    getSelectedAuthorIds() {
        const ids = [];
        this.authors.forEach((author) => {
            if(author.isSelected()) {
                ids.push(author.getId());
            }
        });
        return ids;
    }

    setSelectedAuthorIds(ids) {
        this.authors.forEach((author) => {
            if(ids.includes(author.getId())) {
                author.check();
            }
        });
    }

    destroy() {
        super.destroy();
        if(this.searchInput) {
            this.searchInput.removeEventListener('input', this.#handleSearchInput);
        }
        this.authors.forEach((author) => {
           author.destroy();
        });
        this.authors = null;
    }
}