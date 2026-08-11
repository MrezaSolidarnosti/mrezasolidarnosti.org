import {userViewOptionsSelectors} from "./userViewOptionsSelectors.js";
import LocalStorage from "../../LocalStorage/LocalStorage.js";
import {dataTableSelectors} from "../dataTableSelectors.js";
import Translator from "../../Translator/Translator.js";

export default class ColumnOption {
    #baseAction;
    #columnData;
    #container;
    #inputCheckbox;
    #label;
    #inputWidth;
    #originalColumnWidth;
    #storageKeys = {};
    #tableElement;
    #tableColumnElement;
    #template = `
                <div class="${userViewOptionsSelectors.classes.userViewOption}">
                    <div>
                        <input class="input" type="checkbox">
                        <label></label>
                    </div>
                    <div>
                        <input class="input" type="number" placeholder="${Translator.translate('Column Width')}">
                    </div>
                </div>`;
    constructor({columnData, baseAction, tableElement}) {
        this.#baseAction = baseAction;
        this.#columnData = columnData;
        this.#tableElement = tableElement;
        this.#storageKeys = {
            hiddenColumn: `${this.#baseAction}-hidden-column-${this.#columnData.name}`,
            columnWidth: `${this.#baseAction}-column-width-${this.#columnData.name}`
        }
        this.#setProperties();
        this.#addListeners();
        this.#applyStyleSettings();
        this.#applyStoredSettings();
    }

    #setProperties() {
        const parser = new DOMParser();
        const doc = parser.parseFromString(this.#template, 'text/html');
        this.#container = doc.body.firstChild;
        this.#inputCheckbox = this.#container.querySelector('input[type="checkbox"]');
        this.#label = this.#container.querySelector('label');
        this.#label.textContent = this.#columnData.name.charAt(0).toUpperCase() + this.#columnData.name.slice(1);
        this.#inputWidth = this.#container.querySelector('input[type="number"]');
        this.#tableColumnElement = this.#tableElement.querySelector(`[${dataTableSelectors.attributes.columnName}="${this.#columnData.name}"]`);
    }

    #addListeners() {
        this.#inputCheckbox.addEventListener('input', this.#toggleColumnVisibilityCallback);
        this.#inputWidth.addEventListener('input', this.#changeColumnWidthCallback);
    }

    #applyStoredSettings() {
        if(LocalStorage.get(this.#storageKeys.hiddenColumn)) {
            this.#hideTableColumn();
            this.#inputCheckbox.checked = false;
        } else {
            this.#inputCheckbox.checked = true;
        }
        let storedColumnWidth = LocalStorage.get(this.#storageKeys.columnWidth);
        if(storedColumnWidth) {
            this.#inputWidth.value = storedColumnWidth;
            this.#tableColumnElement.style.width = `${storedColumnWidth}px`;
        }
    }

    #toggleColumnVisibilityCallback = () => {
        if(this.#inputCheckbox.checked) {
            this.#showTableColumn();
            return;
        }
        this.#hideTableColumn();
    }

    #showTableColumn() {
        this.#tableColumnElement.classList.remove(dataTableSelectors.classes.hide);
        this.#removeStyleSheet();
        LocalStorage.remove(this.#storageKeys.hiddenColumn);
    }

    #hideTableColumn() {
        this.#tableColumnElement.classList.add(dataTableSelectors.classes.hide);
        this.#addStyleSheet();
        LocalStorage.set(this.#storageKeys.hiddenColumn, 'true');
    }

    #changeColumnWidthCallback = () => {
        if(this.#inputWidth.value.trim().length === 0) {
            LocalStorage.remove(this.#storageKeys.columnWidth);
            if(this.#originalColumnWidth === null) {
                this.#tableColumnElement.style.removeProperty('width');
            } else {
                this.#tableColumnElement.style.width = this.#originalColumnWidth;
            }
            return;
        }
        if(!isNaN(this.#inputWidth.value)) {
            this.#tableColumnElement.style.width = `${this.#inputWidth.value}px`;
            LocalStorage.set(this.#storageKeys.columnWidth, this.#inputWidth.value);
        }
    }

    #addStyleSheet() {
        let styleSheet = document.createElement('style');
        styleSheet.setAttribute('data-title', this.#columnData.name);
        styleSheet.textContent = `#${dataTableSelectors.ids.tableBody} tr:not(.${dataTableSelectors.classes.additionalDataRow}) td:nth-of-type(${this.#columnData.index}){display:none!important;}`;
        document.head.appendChild(styleSheet);
    }

    #removeStyleSheet() {
        let styleSheet = document.querySelector(`style[data-title="${this.#columnData.name}"]`);
        if(styleSheet) {
            styleSheet.remove();
        }
    }

    #applyStyleSettings() {
        this.#originalColumnWidth = this.#tableColumnElement.style.width !== '' ? this.#tableColumnElement.style.width : null;
    }

    getView() {
        return this.#container;
    }

    destroy() {
        this.#inputCheckbox.removeEventListener('input', this.#toggleColumnVisibilityCallback);
        this.#inputWidth.removeEventListener('input', this.#changeColumnWidthCallback);
        this.#columnData = null;
        this.#container = null;
        this.#inputCheckbox = null;
        this.#label = null;
        this.#inputWidth = null;
        this.#template = null;
        this.#baseAction = null;
        this.#toggleColumnVisibilityCallback = null;
        this.#changeColumnWidthCallback = null;
        this.#storageKeys = null;
        this.#tableElement = null;
        this.#originalColumnWidth = null;
        this.#tableColumnElement = null;
    }
}