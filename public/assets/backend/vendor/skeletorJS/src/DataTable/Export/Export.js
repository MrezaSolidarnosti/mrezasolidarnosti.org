import {dataTableSelectors} from "../dataTableSelectors.js";
import Modal from "../../Modal/Modal.js";
import Translator from "../../Translator/Translator.js";
import {crudPageSelectors} from "../../Page/crudPageSelectors.js";
import {exportSelectors} from "./exportSelectors.js";
import {events} from "../events.js";

export default class Export {

    static EXPORT_TYPES = {
        CSV: 'csv',
        JSON: 'json',
    }

    #columnData;
    #exportToggleButton;
    #exportButton;
    #eventEmitter;
    #modal;
    #form;

    constructor({columnData, eventEmitter}) {
        this.#columnData = columnData;
        this.#eventEmitter = eventEmitter;
        this.#setElements();
    }

    #setElements() {
        this.#exportToggleButton = document.getElementById(dataTableSelectors.ids.exportTable);
    }

    init() {
        if(!this.#exportToggleButton) {
            return;
        }
        this.#initModal();
        this.#exportToggleButton.addEventListener('click', this.#exportTableCallback);
    }

    closeModal() {
        if(this.#modal) {
            this.#modal.hide();
        }
    }

    #initModal() {
        this.#modal = new Modal({
            width: '400px',
            removeContentOnClose: false,
        });
        document.body.appendChild(this.#modal.getView());
        this.#form = this.#getModalContent();
        this.#modal.populateWithElement(this.#form);
    }

    #getModalContent() {
        const content = document.createElement('form');
        const title = document.createElement('span');
        title.textContent = Translator.translate('Columns to Export');
        content.appendChild(title);
        content.appendChild(this.#getColumnsToSelect());
        content.appendChild(this.#getExportTypeSelect());
        this.#exportButton = this.#generateExportButton();
        content.appendChild(this.#exportButton);
        return content;
    }

    #generateExportButton() {
        const button = document.createElement('button');
        button.textContent = Translator.translate('Export');
        button.classList.add(crudPageSelectors.classes.button);
        button.addEventListener('click', this.#exportData);
        return button;
    }

    #exportData =  async (e) => {
        e.preventDefault();
        const columns = this.#getSelectedColumns();
        const exportType = this.#getSelectedExportType();
        this.#eventEmitter.emit(events.exportData, {columns, exportType});
    }

    #getSelectedColumns() {
        const checkboxes = document.querySelectorAll(`.${exportSelectors.classes.checkboxContainer} input:checked`);
        return Array.from(checkboxes).map((checkbox) => checkbox.value);
    }

    #getSelectedExportType() {
        return document.querySelector(`.${exportSelectors.classes.selectContainer} select`).value;
    }

    #getColumnsToSelect() {
        const container = document.createElement('div');
        container.classList.add(exportSelectors.classes.columnsContainer);
        this.#columnData.forEach((column) => {
            container.appendChild(this.#getColumnCheckbox(column));
        });
        return container;
    }

    #getColumnCheckbox(column) {
        const container = document.createElement('div');
        container.classList.add(exportSelectors.classes.checkboxContainer);
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.classList.add(crudPageSelectors.classes.input);
        checkbox.value = column.name;
        checkbox.checked = true;
        container.appendChild(checkbox);
        const label = document.createElement('span');
        label.textContent = column.name.charAt(0).toUpperCase() + column.name.slice(1);
        container.appendChild(label);
        return container;
    }

    #getExportTypeSelect() {
        const container = document.createElement('div');
        const label = document.createElement('label');
        label.textContent = Translator.translate('Export As');
        const select = document.createElement('select');
        Object.keys(Export.EXPORT_TYPES).forEach((type) => {
            const option = document.createElement('option');
            option.value = Export.EXPORT_TYPES[type];
            option.textContent = type.toUpperCase();
            select.appendChild(option);
        });
        container.classList.add(crudPageSelectors.classes.inputContainer);
        container.classList.add(exportSelectors.classes.selectContainer);
        select.classList.add(crudPageSelectors.classes.input);
        container.appendChild(label);
        container.appendChild(select);
        return container;
    }

    #exportTableCallback = () => {
        this.#modal.show();
    }

    getForm() {
        return this.#form;
    }

    destroy() {
        this.#columnData = null;
        this.#eventEmitter = null;
        if(this.#exportToggleButton) {
            this.#exportToggleButton.removeEventListener('click', this.#exportTableCallback);
        }
        this.#exportTableCallback = null;
        this.#modal.destroy();
        this.#modal = null;
        if(this.#exportButton) {
            this.#exportButton.removeEventListener('click', this.#exportData);
        }
        this.#exportButton = null;
        this.#exportData = null;
        this.#form = null;
    }
}