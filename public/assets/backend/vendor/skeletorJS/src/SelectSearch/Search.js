import {selectSearchSelectors} from "./selectSearchSelectors.js";

export default class Search {

    #container;
    #select;
    #searchResultContainer;
    #searchInput;
    #resultContainer;
    #results = [];
    #searchPlaceholder;
    #currentResultIndex = -1;
    constructor({container, select, searchPlaceholder}) {
        this.#container = container;
        this.#select = select;
        this.#searchPlaceholder = searchPlaceholder;
    }

    generateSearch() {
        this.#assembleSearchContainer();
        this.#container.appendChild(this.#searchResultContainer);
        void this.#initBehavior();
    }

    #assembleSearchContainer() {
        this.#searchResultContainer = this.#generateSearchResultContainer();
        this.#searchInput = this.#generateSearchInput();
        this.#resultContainer = this.#generateResultContainer();
        this.#results = this.#generateResults();
        this.#resultContainer.append(...this.#results);

        this.#searchResultContainer.appendChild(this.#searchInput);
        this.#searchResultContainer.appendChild(this.#resultContainer);
    }

    #generateSearchResultContainer() {
        const container = document.createElement('div');
        container.classList.add(selectSearchSelectors.classes.selectSearchResultContainer);
        return container;
    }

    #generateSearchInput() {
        const input = document.createElement('input');
        input.classList.add(selectSearchSelectors.classes.input);
        input.placeholder = this.#searchPlaceholder ?? 'Search...';
        return input;
    }

    #generateResultContainer() {
        const container = document.createElement('div');
        container.classList.add(selectSearchSelectors.classes.resultContainer);
        return container;
    }

    #generateResults() {
        const results = [];
        Array.from(this.#select.querySelectorAll('option')).map(option => {
            const result = document.createElement('div');
            result.textContent = option.textContent;
            result.dataset.value = option.value;
            //@todo check if works on mobile
            if(option.disabled) {
                result.classList.add(selectSearchSelectors.classes.resultDisabled);
            } else {
                result.addEventListener('mousedown', this.#handleResultClick);
            }
            results.push(result);
        });
        return results;
    }

    #handleResultClick = (e) => {
        this.#setSelectValue(e.target.dataset.value);
    }

    #setSelectValue(value) {
        this.#select.value = value;
        this.#select.dispatchEvent(new Event('change'));
    }
    #initBehavior() {
        this.#searchInput.focus();
        this.#addListeners();
    }

    #addListeners() {
        this.#addSearchListener();
        this.#addBlurListener();
    }

    #addSearchListener() {
        this.#searchInput.addEventListener('input', this.#searchCallback);
        this.#searchInput.addEventListener('keydown', this.#keyDownCallback);
    }

    #searchCallback = (e) => {
        this.#results.forEach((result, index) => {
            if(result.textContent.toLowerCase().includes(this.#searchInput.value.toLowerCase())) {
                result.style.display = 'block';
            } else {
                if(result.classList.contains(selectSearchSelectors.classes.resultActive)) {
                    result.classList.remove(selectSearchSelectors.classes.resultActive);
                }
                if(this.#currentResultIndex === index) {
                    this.#currentResultIndex = -1;
                }
                result.style.display = 'none';
            }
        });
        if(this.#currentResultIndex !== -1) {
            this.#scrollToResult(this.#results[this.#currentResultIndex]);
        }
    }

    #keyDownCallback = (e) => {
        let targetIndex;
        switch(e.key) {
            case 'Escape':
                this.destroy();
                return;
            case 'Enter':
                if(this.#currentResultIndex !== -1) {
                    this.#setSelectValue(this.#results[this.#currentResultIndex].dataset.value);
                    this.destroy();
                }
                return;
            case 'ArrowDown':
                targetIndex = this.#getNextResultIndex();
                break;
            case 'ArrowUp':
                targetIndex = this.#getPreviousResultIndex();
                break;
            default:
                targetIndex = -1;
        }
        if(targetIndex === -1) {
            return;
        }
        this.#removeCurrentActiveResult()
        this.#setActiveResult(targetIndex);
    }

    #getNextResultIndex() {
        for(let i = this.#currentResultIndex + 1; i < this.#results.length; i++) {
            if (this.#results[i].style.display !== 'none' && !this.#results[i].classList.contains(selectSearchSelectors.classes.resultDisabled)) {
                return i;
            }
        }
        return -1;
    }

    #getPreviousResultIndex() {
        for(let i = this.#currentResultIndex - 1; i >= 0; i--) {
            if(this.#results[i].style.display !== 'none' && !this.#results[i].classList.contains(selectSearchSelectors.classes.resultDisabled)) {
                return i;
            }
        }
        return -1;
    }

    #scrollToResult(result) {
        this.#resultContainer.scrollTop = result.offsetTop - this.#resultContainer.offsetTop - this.#resultContainer.clientHeight / 2;
    }


    #removeCurrentActiveResult() {
        if(this.#currentResultIndex !== -1) {
            this.#results[this.#currentResultIndex].classList.remove(selectSearchSelectors.classes.resultActive);
        }
    }

    #setActiveResult(index) {
        this.#currentResultIndex = index;
        this.#results[this.#currentResultIndex].classList.add(selectSearchSelectors.classes.resultActive);
        this.#scrollToResult(this.#results[this.#currentResultIndex]);
    }

    #addBlurListener() {
        this.#searchInput.addEventListener('blur', this.#blurCallback);
    }

    #blurCallback = () => {
        this.destroy();
    }

    destroy() {
        this.#searchInput.removeEventListener('input', this.#searchCallback);
        this.#searchInput.removeEventListener('blur', this.#blurCallback);
        this.#searchInput.removeEventListener('keydown', this.#keyDownCallback);
        this.#results.forEach(result => {
            result.removeEventListener('mousedown', this.#handleResultClick);
        });
        this.#results = null;
        this.#searchResultContainer.remove();
        this.#container = null;
        this.#select = null;
        this.#searchResultContainer = null;
        this.#searchInput = null;
        this.#resultContainer = null;
        this.#searchPlaceholder = null;
        this.#searchCallback = null;
        this.#blurCallback = null;
        this.#handleResultClick = null;
        this.#keyDownCallback = null;
        this.#currentResultIndex = null;
    }
}