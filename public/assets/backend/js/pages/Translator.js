import CrudPage from "../../../../vendor/skeletorjs/src/Page/CrudPage.js";
import {events} from "../../../../vendor/skeletorjs/src/DataTable/events.js";
import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";
import {dataTableSelectors} from "../../../../vendor/skeletorjs/src/DataTable/dataTableSelectors.js";

export default class Translator extends CrudPage {

    languageOptions = {};
    editableColumns = {
        originalString: {type: 'text'},
        translatedString: {type: 'text'},
        language: {type: 'select'},
    }
    listeners = [];
    saveTranslationUrl = '/translator/save/'

    afterDataTableActionsSet() {
        this.removeDataTableAction('edit');
    }

    preload() {
        const languageSelect = document.querySelector('select[name="language"]');
        if(languageSelect) {
            const options = languageSelect.querySelectorAll('option');
            options.forEach(option => {
                this.languageOptions[option.value] = option.textContent;
            });
        }
    }

    tdStyler = (td, columnName, columnValue, entity) => {
        if(this.editableColumns[columnName]) {
            const td = document.createElement('td');
            td.classList.add('inPlaceEditable');
            switch(this.editableColumns[columnName].type) {
                case 'select':
                    td.appendChild(this.generateLanguageSelect(columnName, this.getLanguageId(columnValue)));
                    break;
                case 'text':
                    const input = document.createElement('input');
                    input.classList.add('inPlaceEditableInput');
                    input.dataset.columnName = columnName;
                    input.type = this.editableColumns[columnName].type;
                    input.value = columnValue;
                    input.spellcheck = false;
                    td.appendChild(input);
                    break;
            }
            return td;
        }
        return td
    }

    generateLanguageSelect(columnName, columnValue) {
        const select = document.createElement('select');
        select.name = columnName;
        select.classList.add('inPlaceEditableSelect');
        select.dataset.columnName = columnName;
        for(const [value, text] of Object.entries(this.languageOptions)) {
            if(value === '-1') continue;
            const option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            if(value === columnValue) {
                option.selected = true;
            }
            select.appendChild(option);
        }
        return select;
    }

    finalize() {
        this.dataTable.eventEmitter.on(events.tablePopulated, () => {
            this.setListeners();
        });
        this.dataTable.eventEmitter.on(events.beforeTablePopulated, () => {
           this.destroyListeners();
        });
    }

    setListeners() {
        const inputs = this.dataTable.getTable().querySelectorAll('.inPlaceEditableInput');
        const selects = this.dataTable.getTable().querySelectorAll('.inPlaceEditableSelect');
        inputs.forEach((input) => {
           input.addEventListener('blur', this.saveTranslation);
           this.listeners.push({element: input, event: 'blur', handler: this.saveTranslation});
           input.addEventListener('focus', this.onInputFocus);
           this.listeners.push({element: input, event: 'focus', handler: this.onInputFocus});
        });
        selects.forEach((select) => {
            select.addEventListener('change', this.saveTranslation);
            this.listeners.push({element: select, event: 'change', handler: this.saveTranslation});
            select.addEventListener('focus', this.onInputFocus);
            this.listeners.push({element: select, event: 'focus', handler: this.onInputFocus});
        });
    }

    getLanguageId(languageName) {
        for(const [id, name] of Object.entries(this.languageOptions)) {
            if(name.toLowerCase() === languageName.toLowerCase()) {
                return id;
            }
        }
    }


    saveTranslation = async (e) => {
        const target = e.target;
        let value = target.value;
        if(value === target.currentValidValue) {
            return;
        }
        const columnName = target.dataset.columnName;
        if(columnName === 'language') {
            value = parseInt(value, 10);
        }
        const row = target.closest('tr');
        const entityId = row.dataset.id;

        const data = {
            id: parseInt(entityId, 10),
            column: columnName,
            value: value
        };
        const csrfData = this.getCSRFTokenData();
        data[csrfData.name] = csrfData.value;

        const response = await fetch(this.saveTranslationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        if(result.token) {
            this.replaceCSRFInput(result.token);
        }
        let inputSuccessClass = '';
        if(result.status) {
            inputSuccessClass = 'flashInput';
        } else {
            inputSuccessClass = 'flashInputError';
            Message.spawn({
                message: result.message || 'An error occurred while saving the translation.',
                type: Message.TYPES.ERROR,
                view: {
                    type: Message.VIEW_TYPES.NOTIFICATION,
                    container: this.getMessageContainer(),
                    prepend: false,
                },
            });
            target.value = target.currentValidValue;
        }
        target.classList.add(inputSuccessClass);
        target.addEventListener('animationend', () => {
            target.classList.remove(inputSuccessClass);
        }, { once: true });
    }


    onInputFocus = (e) => {
        e.target.currentValidValue = e.target.value;
    }

    destroyListeners() {
        this.listeners.forEach(({element, event, handler}) => {
           element.removeEventListener(event, handler);
        });
    }

}