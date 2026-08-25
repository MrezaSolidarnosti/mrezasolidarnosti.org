import {paginationAssets} from "./paginationAssets.js";
import {paginationSelectors} from "./paginationSelectors.js";
import {events} from "../events.js";
import Translator from "../../Translator/Translator.js";

export default class Pagination {


    #paginationContainerTop;
    #paginationContainerBottom;
    #hasTopPagination = false;
    #hasBottomPagination = false;
    #buttonsTop = [];
    #buttonsBottom = [];
    #maxPage;
    #manipulateURL;
        constructor(eventEmitter, manipulateURL = true) {
        this.eventEmitter = eventEmitter;
        this.#manipulateURL = manipulateURL;
        this.#setProperties();
    }

    #setProperties() {
        this.#paginationContainerTop = document.getElementById(paginationSelectors.ids.tablePaginationContainerTop);
        this.#paginationContainerBottom = document.getElementById(paginationSelectors.ids.tablePaginationContainerBottom);
        if(this.#paginationContainerTop) {
            this.#hasTopPagination = true;
        }
        if(this.#paginationContainerBottom) {
            this.#hasBottomPagination = true;
        }
    }

    generatePagination(maxPage, activePage = 1) {
        this.#maxPage = maxPage;
        this.#setButtons(activePage);
        this.#populatePaginationContainers();
    }

    #setButtons(activePage) {
        if(this.#hasTopPagination) {
            this.#destroyTopButtons();
            if (this.#maxPage > 1) {
                this.#buttonsTop.push({button:this.generatePaginationButton(1, Translator.translate('First'))});
                this.#buttonsTop.push({
                    button:
                    this.generatePaginationButton(
                        activePage - 1,
                        paginationAssets.previousIcon,
                        null,
                        paginationSelectors.classes.previousPaginationButton
                    )});
            }
            this.#buttonsTop.push({button:this.generatePaginationButton(activePage, activePage, activePage)});
            if (this.#maxPage > 1) {
                let next = activePage + 1;
                if (activePage >= this.#maxPage) {
                    next = this.#maxPage;
                }
                this.#buttonsTop.push({
                    button:
                    this.generatePaginationButton(
                        next,
                        paginationAssets.nextIcon,
                        null,
                        paginationSelectors.classes.nextPaginationButton
                    )});
                this.#buttonsTop.push({button:this.generatePaginationButton(this.#maxPage, this.#maxPage.toString())});
            }
        }
        if(this.#hasBottomPagination) {
            this.#destroyBottomButtons();
            if (this.#maxPage > 1) {
                this.#buttonsBottom.push({button:this.generatePaginationButton(1, Translator.translate('First'))});
                this.#buttonsBottom.push({
                    button:
                    this.generatePaginationButton(
                        activePage - 1,
                        paginationAssets.previousIcon,
                        null,
                        paginationSelectors.classes.previousPaginationButton
                    )});
            }
            this.#buttonsBottom.push({button:this.generatePaginationButton(activePage, activePage, activePage)});
            if (this.#maxPage > 1) {
                let next = activePage + 1;
                if (activePage >= this.#maxPage) {
                    next = this.#maxPage;
                }
                this.#buttonsBottom.push({
                    button:
                    this.generatePaginationButton(
                        next,
                        paginationAssets.nextIcon,
                        null,
                        paginationSelectors.classes.nextPaginationButton
                    )});
                this.#buttonsBottom.push({button:this.generatePaginationButton(this.#maxPage, this.#maxPage.toString())});
            }

        }
    }

    #populatePaginationContainers() {
        if(this.#hasTopPagination) {
            this.#buttonsTop.forEach((btn) => {
                const callback = () => {
                    const targetPage = parseInt(btn.button.getAttribute(paginationSelectors.attributes.page));
                    if(this.#manipulateURL) {
                        if (targetPage === this.getActivePageFromURL() || targetPage === 0) {
                            return;
                        }
                    }
                    this.#animateClick(btn.button);
                    this.goToPage(targetPage);
                }
                btn.callback = callback;
                btn.button.addEventListener('click', callback);
                this.#paginationContainerTop.append(btn.button);
            });
        }
        if(this.#hasBottomPagination) {
            this.#buttonsBottom.forEach((btn) => {
                const callback = () => {
                    const targetPage = parseInt(btn.button.getAttribute(paginationSelectors.attributes.page));
                    if(this.#manipulateURL) {
                        if (targetPage === this.getActivePageFromURL() || targetPage === 0) {
                            return;
                        }
                    }
                    this.#animateClick(btn.button);
                    this.goToPage(targetPage);
                }
                btn.callback = callback;
                btn.button.addEventListener('click', callback);
                this.#paginationContainerBottom.append(btn.button);
            });
        }
    }

    #destroyTopButtons() {
        this.#buttonsTop.forEach((btn) => {
            btn.button.removeEventListener('click', btn.callback);
            btn.callback = null;
            btn.button.remove();
        });
        this.#buttonsTop = [];
    }

    #destroyBottomButtons() {
        this.#buttonsBottom.forEach((btn) => {
            btn.button.removeEventListener('click', btn.callback);
            btn.callback = null;
            btn.button.remove();
        });
        this.#buttonsBottom = [];
    }

    #animateClick(button) {
        button.animate({transform:'matrix(0.95, 0, 0, 0.95, 0, 0)'}, {duration:100, iterations:1})
    }

    #getStateButtons() {
        const buttons = {
            active: [],
            previous: [],
            next: []
        };
        if(this.#hasTopPagination) {
            this.#buttonsTop.filter((btn) => {
                if(btn.button.classList.contains(paginationSelectors.classes.activePaginationButton)) {
                    buttons.active.push(btn.button);
                }
                if(btn.button.classList.contains(paginationSelectors.classes.previousPaginationButton)) {
                    buttons.previous.push(btn.button);
                }
                if(btn.button.classList.contains(paginationSelectors.classes.nextPaginationButton)) {
                    buttons.next.push(btn.button);
                }
            });
        }
        if(this.#hasBottomPagination) {
            this.#buttonsBottom.filter((btn) => {
                if(btn.button.classList.contains(paginationSelectors.classes.activePaginationButton)) {
                    buttons.active.push(btn.button);
                }
                if(btn.button.classList.contains(paginationSelectors.classes.previousPaginationButton)) {
                    buttons.previous.push(btn.button);
                }
                if(btn.button.classList.contains(paginationSelectors.classes.nextPaginationButton)) {
                    buttons.next.push(btn.button);
                }
            });
        }
        return buttons;
    }

    goToPage(targetPage) {
        const stateButtons = this.#getStateButtons();
        const activePage = parseInt(stateButtons.active[0]?.getAttribute(paginationSelectors.attributes.page));
        if(activePage === targetPage || targetPage === 0) {
            return targetPage;
        }
        if(targetPage > this.#maxPage) {
            targetPage = this.#maxPage;
        }
        if(targetPage < 1) {
            targetPage = 1;
        }
        stateButtons.active.forEach((btn) => {
           btn.textContent = targetPage;
           btn.setAttribute(paginationSelectors.attributes.page, targetPage.toString());
        });

        stateButtons.previous.forEach((btn) => {
            let previous = targetPage - 1;
            if(targetPage === 1) {
                previous = 1;
            }
            btn.setAttribute(paginationSelectors.attributes.page, previous.toString());
        });

        stateButtons.next.forEach((btn) => {
            let next = targetPage + 1;
            if(targetPage === this.#maxPage) {
                next = this.#maxPage;
            }
            btn.setAttribute(paginationSelectors.attributes.page, next.toString());
        });
        if(this.#manipulateURL) {
            let params = new URLSearchParams(window.location.search);
            params.set('page', targetPage.toString());
            let newUrl = window.location.origin
                + window.location.pathname
                + '?' + params.toString();
            window.history.pushState({path: newUrl}, '', newUrl);
        }
        this.eventEmitter.emit(events.requestPopulateTable, targetPage);
        return targetPage;
    }


    generatePaginationButton(page, text, activePage = null, className = null) {
        const button = document.createElement('button');
        button.innerHTML = text;
        if(activePage && parseInt(page) === parseInt(activePage)) {
            button.classList.add(paginationSelectors.classes.activePaginationButton);
        }
        if(className) {
            button.classList.add(className);
        }
        button.setAttribute(paginationSelectors.attributes.page, page);
        button.classList.add(paginationSelectors.classes.paginationButton);
        return button;
    }

    getActivePageFromURL() {
        let params = new URLSearchParams(window.location.search);
        let page = params.get('page') ?? 1;
        if(parseInt(page) < 1) {
            page = 1;
        }
        return parseInt(page);
    }

    destroy() {
        this.eventEmitter = null;
        this.#paginationContainerTop = null;
        this.#paginationContainerBottom = null;
        this.#hasTopPagination = false;
        this.#hasBottomPagination = false;
        this.#destroyTopButtons();
        this.#destroyBottomButtons();
        this.#buttonsTop = [];
        this.#buttonsBottom = [];
        this.#maxPage = null;
        this.#manipulateURL = null;
    }
}