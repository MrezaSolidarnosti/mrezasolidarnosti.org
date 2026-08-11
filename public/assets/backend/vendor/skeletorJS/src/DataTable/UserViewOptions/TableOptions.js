import {userViewOptionsSelectors} from "./userViewOptionsSelectors.js";
import LocalStorage from "../../LocalStorage/LocalStorage.js";
import Translator from "../../Translator/Translator.js";

export default class TableOptions {

    #baseAction;
    #container;
    #inputCheckbox;
    #containerWidth;
    #inputWidth;
    #label;
    #input;
    #tableElement;
    #storageKeys = {};
    #template = `
                <div class="${userViewOptionsSelectors.classes.userViewOption}">
                    <div>
                        <label>${Translator.translate('Table Font Size')}</label>
                        <input class="input" type="number">
                    </div>
                </div>`;
    #templateWidth= `
                <div class="${userViewOptionsSelectors.classes.userViewOption}">
                    <div>
                        <label>${Translator.translate('Table Width')}</label>
                        <input class="input" type="number">
                    </div>
                </div>`;
    constructor({baseAction, tableElement}) {
        this.#baseAction = baseAction;
        this.#tableElement = tableElement;
        this.#storageKeys = {
            tableFontSize: `${this.#baseAction}-table-font-size`,
            tableWidth: `${this.#baseAction}-table-width`,
        }
        this.#setProperties();
        this.#applyStoredSettings();
        this.#addListeners();
    }

    #setProperties() {
        const parser = new DOMParser();
        const doc = parser.parseFromString(this.#template, 'text/html');
        this.#container = doc.body.firstChild;
        this.#input = this.#container.querySelector('input[type="number"]');

        const docTwo = parser.parseFromString(this.#templateWidth, 'text/html');
        this.#containerWidth = docTwo.body.firstChild;
        this.#inputWidth = this.#containerWidth.querySelector('input[type="number"]');
    }

    #applyStoredSettings() {
        const fontSize = LocalStorage.get(this.#storageKeys.tableFontSize);
        if(fontSize) {
            this.#input.value = fontSize;
            this.#changeTableFontSize(fontSize);
        }
        const width = LocalStorage.get(this.#storageKeys.tableWidth);
        if(width) {
            this.#inputWidth.value = width;
            this.#changeTableWidth(width);
        }
    }

    #addListeners() {
        this.#input.addEventListener('input', this.#changeTableFontSizeCallback);
        this.#inputWidth.addEventListener('input', this.#changeTableWidthCallback);
    }

    #changeTableFontSizeCallback = () => {
        this.#changeTableFontSize(this.#input.value);
    }

    #changeTableWidthCallback = () => {
        this.#changeTableWidth(this.#inputWidth.value);
    }

    #changeTableFontSize(fontSize) {
        if(this.#input.value.trim() === '') {
            LocalStorage.remove(this.#storageKeys.tableFontSize);
            this.#tableElement.style.removeProperty('font-size');
            return;
        }
        if(!isNaN(fontSize)) {
            this.#tableElement.style.fontSize = `${fontSize}px`;
            LocalStorage.set(this.#storageKeys.tableFontSize, fontSize);
        }
    }

    #changeTableWidth(width) {
        if(this.#inputWidth.value.trim() === '') {
            LocalStorage.remove(this.#storageKeys.tableWidth);
            this.#tableElement.style.removeProperty('width');
            return;
        }
        if(!isNaN(width)) {
            this.#tableElement.style.width = `${width}px`;
            LocalStorage.set(this.#storageKeys.tableWidth, width);
        }
    }

    getOptionContainers() {
        return [this.#container, this.#containerWidth];
    }

    destroy() {
        this.#baseAction = null;
        this.#container = null;
        this.#inputCheckbox = null;
        this.#label = null;
        this.#input.removeEventListener('input', this.#changeTableFontSizeCallback);
        this.#changeTableFontSizeCallback = null;
        this.#input = null;
        this.#tableElement = null;
        this.#storageKeys = null;
        this.#template = null;
        this.#templateWidth = null;
        this.#containerWidth = null;
        this.#inputWidth.removeEventListener('input', this.#changeTableWidthCallback);
        this.#inputWidth = null;
        this.#changeTableWidthCallback = null;
    }
}