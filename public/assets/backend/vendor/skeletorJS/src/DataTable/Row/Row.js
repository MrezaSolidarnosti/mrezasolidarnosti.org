import DataTable from "../DataTable.js";
import {dataTableSelectors} from "../dataTableSelectors.js";
import Loader from "../../Loader/Loader.js";
import {events} from "../events.js";
import Translator from "../../Translator/Translator.js";
import DataTableAction from "../DataTableAction/DataTableAction.js";
import DynamicDataTableAction from "../DynamicDataTableAction/DynamicDataTableAction.js";
import DataTableGroupAction from "../DataTableGroupAction/DataTableGroupAction.js";
import Modal from "../../Modal/Modal.js";
import Prompt from "../../Prompt/Prompt.js";

export default class Row {

    #entity;
    #tableHeaders;
    #actions;
    #dynamicActions;
    #actionFilter;
    #tdStyler;
    #trStyler;
    #mode;
    #useCheckbox;
    #rowElement;
    #checkboxElement;
    #entityId;
    #actionElementsData = [];
    #editColumns = [];
    #eventEmitter;
    #additionalDataRow = null;
    #showAdditionalContentOnLoad;
    #dynamicActionsData = new Map();
    #groupActions = [];

    constructor({
        entity,
        tableHeaders,
        actions,
        dynamicActions,
        actionFilter,
        mode,
        useCheckbox,
        tdStyler,
        trStyler,
        eventEmitter,
        showAdditionalContentOnLoad
                }) {
        this.#entity = entity;
        this.#tableHeaders = tableHeaders;
        this.#actions = actions;
        this.#dynamicActions = dynamicActions;
        this.#actionFilter = actionFilter;
        this.#mode = mode;
        this.#useCheckbox = useCheckbox;
        this.#tdStyler = tdStyler;
        this.#trStyler = trStyler;
        this.#eventEmitter = eventEmitter;
        this.#entityId = entity.id;
        this.#showAdditionalContentOnLoad = showAdditionalContentOnLoad;
        this.#setProperties();
    }

    #setProperties() {
        switch(this.#mode) {
            case DataTable.MODES.DESKTOP:
                this.#buildForDesktop();
                break;
            case DataTable.MODES.MOBILE:
                // this.#buildForMobile();
                this.#buildForDesktop();
                break;
            default:
                throw new Error('Invalid mode provided to Row');
        }
    }

    #buildForDesktop() {
        this.#rowElement = document.createElement('tr');
        this.#rowElement.setAttribute(dataTableSelectors.attributes.entityId, this.#entityId);
        if(this.#useCheckbox) {
            const td = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.setAttribute(dataTableSelectors.attributes.entityId, this.#entityId);
            checkbox.classList.add(dataTableSelectors.classes.entityCheckbox);
            checkbox.classList.add(dataTableSelectors.classes.input);
            this.#checkboxElement = checkbox;
            this.#checkboxElement.addEventListener('change', this.#checkboxCallback);
            td.appendChild(checkbox);
            this.#rowElement.appendChild(td);
        }
        this.#tableHeaders.forEach((header) => {
            const columnName = header.getAttribute(dataTableSelectors.attributes.columnName);
            const tdValue = this.#entity.columns[columnName];
            let td = document.createElement('td');
            td.setAttribute(dataTableSelectors.attributes.columnName, columnName);
            if(tdValue || tdValue === '' || tdValue === false || tdValue === 0) {
                if(typeof tdValue.value !== 'undefined') {
                    td.innerHTML = tdValue.value;
                    if(tdValue.editColumn) {
                        td.classList.add(dataTableSelectors.classes.editColumn);
                    }
                    td = this.#tdStyler(td, columnName, tdValue, this.#entity);
                    this.#editColumns.push({td:td});
                } else {
                    td.innerHTML = tdValue;
                    td = this.#tdStyler(td, columnName, tdValue, this.#entity);
                }
                this.#rowElement.appendChild(td);
            }
            if(tdValue === null) {
                td.textContent = '';
                td = this.#tdStyler(td, columnName, tdValue, this.#entity);
                this.#rowElement.appendChild(td);
            }
        });
        this.#rowElement.appendChild(this.#getActionsTD());
        this.#rowElement = this.#trStyler(this.#rowElement, this.#entity);
        if(this.#entity?.additionalContent) {
            this.#buildAdditionalDataRow();
        }
    }

    #buildAdditionalDataRow() {
        const tr = document.createElement('tr');
        tr.classList.add(dataTableSelectors.classes.additionalDataRow);
        if(this.#useCheckbox) {
            const td = document.createElement('td');
            tr.appendChild(td);
        }
        const td = document.createElement('td');
        td.innerHTML = this.#entity.additionalContent;
        td.setAttribute('colspan', this.#tableHeaders.length);
        tr.appendChild(td);
        if(this.#showAdditionalContentOnLoad) {
            tr.classList.add(dataTableSelectors.classes.show);
        }
        this.#additionalDataRow = tr;
    }

    #buildForMobile() {

    }

    #getActionsTD() {
        const td = document.createElement('td');
        td.classList.add(dataTableSelectors.classes.actionContainer);
        const wrapper = document.createElement('div');
        wrapper.classList.add(dataTableSelectors.classes.actionsWrapper);
        this.#actions.forEach((action) => {
            let actionElement = null;
            switch(true) {
                case action instanceof DataTableAction:
                    actionElement = this.#addAction(action);
                    break;
                case action instanceof DynamicDataTableAction:
                    actionElement = this.#addDynamicAction(action);
                    break;
                case action instanceof DataTableGroupAction:
                    actionElement = this.#addGroupAction(action);
                    break;
            }
            if(actionElement) {
                wrapper.appendChild(actionElement);
            }
        });
        if(this.#entity?.additionalContent) {
            this.#addShowAdditionalDataActionToWrapper(wrapper);
        }
        td.appendChild(wrapper);
        return td;
    }

    #addShowAdditionalDataActionToWrapper(wrapper) {
        const button = document.createElement('div');
        button.title = Translator.translate('Additional Data');
        button.classList.add(dataTableSelectors.classes.entityActionButton, dataTableSelectors.classes.additionalDataActionButton);
        button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>`;
        button.style.order = '-1';
        if(this.#showAdditionalContentOnLoad) {
            button.classList.add(dataTableSelectors.classes.active);
        }
        const callback = () => {
            const additionalDataRow = this.#rowElement.nextElementSibling;
            if(additionalDataRow.classList.contains('additionalDataRow')) {
               additionalDataRow.classList.toggle('show');
            }
            button.classList.toggle(dataTableSelectors.classes.active);
        }
        button.addEventListener('click', callback);
        wrapper.appendChild(button);
        this.#actionElementsData.push({
            button,
            callback: callback
        })
    }

    #addAction(action) {
        action = this.#actionFilter(action, this.#entity);
        if(!action) {
            return false;
        }
        const button = document.createElement('div');
        button.title = action.getLabel();
        button.classList.add(dataTableSelectors.classes.entityActionButton);
        button.innerHTML = action.getContent();
        button.style.order = action.getOrder();
        if(action.getAsText()) {
            button.classList.add(dataTableSelectors.classes.textActionButton);
            if(action.getTextType()) {
                button.classList.add(action.getTextType());
            }
        }
        if(action.getClassName() !== '') {
            button.classList.add(action.getClassName());
        }
        const callback = async () => {
            let loader = new Loader({size:'20px', thickness:'3px'});
            if(action.getPromptMessage()) {
                const confirm = new Prompt({
                    message: action.getPromptMessage(),
                    choices: [
                        {value: 1, text: Translator.translate('Confirm')},
                        {value: 0, text: Translator.translate('Cancel')}
                    ]
                });
                const response = await confirm.prompt();
                if(parseInt(response) === 0 || response === null) {
                    return;
                }
            }
            if(action.getLockDuringCallback()) {
                if(this.#rowElement) {
                    this.#rowElement.classList.add(dataTableSelectors.classes.locked);
                }
            }
            if(action.getUseLoader()) {
                loader.start(button, ['svg'])
            }
            await action.getCallback()(this.#entity);

            if(action.getUseLoader()) {
                loader.stop(button, ['svg']);
            }
            if(action.getFlashOnSuccess()) {
                if(this.#rowElement) {
                    this.#rowElement.classList.add(dataTableSelectors.classes.flash);
                    this.#rowElement.addEventListener('animationend', () => {
                        this.#rowElement.classList.remove(dataTableSelectors.classes.flash);
                    }, { once: true });
                }
            }
            if(action.getLockDuringCallback()) {
                if(this.#rowElement) {
                    this.#rowElement.classList.remove(dataTableSelectors.classes.locked);
                }
            }
            loader.destroy();
            loader = null;
        }
        if(action.getName() === 'edit') {
            this.#editColumns.forEach((editColumn) => {
                editColumn.callback = callback;
                editColumn.td.addEventListener('click', callback);
            });
        }
        button.addEventListener('click', callback);
        this.#actionElementsData.push({
            button,
            callback: callback
        })
        return button;
    }

    #addDynamicAction(action) {
        action = this.#actionFilter(action, this.#entity);
        if(!action) {
            return false;
        }
        const button = document.createElement('div');
        button.classList.add(dataTableSelectors.classes.entityActionButton);
        if(action.getClassName() !== '' && typeof action.getClassName() !== 'undefined') {
            button.classList.add(action.getClassName());
        }
        const initialState = action.getInitialStateCallback()(this.#entity);
        if(initialState) {
            let state = action.getState(initialState);
            if (state) {
                return this.#setStateForDynamicCallback(action, state, button);
            }
        }
        return false;
    }

    #setStateForDynamicCallback(action, state, button) {
        action = this.#actionFilter(action, this.#entity);
        if(!action) {
            return false;
        }
        button.title = state.label;
        button.innerHTML = state.content;
        button.style.order = action.getOrder();
        if(state.className && state.className !== '') {
            button.classList.add(state.className);
        }
        if(state.asText) {
            button.classList.add(dataTableSelectors.classes.textActionButton);
            if(state.textType) {
                button.classList.add(state.textType);
            }
        }
        const callback = async () => {
            let loader = new Loader({size:'20px', thickness:'3px'});
            if(state.promptMessage) {
                if(!confirm(state.promptMessage)) {
                    return;
                }
            }
            button.removeEventListener('click', callback);
            if(state.lockRowDuringCallback) {
                if(this.#rowElement) {
                    this.#rowElement.classList.add(dataTableSelectors.classes.locked);
                }
            }
            if(action.getUseLoader()) {
                loader.start(button, ['svg'])
            }
            const nextSateName = await state.setNextState(this.#entity);
            if(nextSateName) {
                let nextState = action.getState(nextSateName);
                if(nextState) {
                    button = this.#setStateForDynamicCallback(action, nextState, button);
                }
            }
            if(action.getUseLoader()) {
                loader.stop(button, ['svg']);
            }
            if(state.flashOnSuccess) {
                if(this.#rowElement) {
                    this.#rowElement.classList.add(dataTableSelectors.classes.flash);
                    this.#rowElement.addEventListener('animationend', () => {
                        this.#rowElement.classList.remove(dataTableSelectors.classes.flash);
                    }, { once: true });
                }
            }
            if(state.lockRowDuringCallback) {
                if(this.#rowElement) {
                    this.#rowElement.classList.remove(dataTableSelectors.classes.locked);
                }
            }
            loader.destroy();
            loader = null;
        }
        button.addEventListener('click', callback);
        this.#dynamicActionsData.set(action.getName(), {button: button, callback: callback});
        return button;
    }

    #addGroupAction(action) {
        const actions = action.getActions();
        if(action && actions.length > 0) {
            const button = document.createElement('div');
            button.classList.add(dataTableSelectors.classes.entityActionButton);
            button.innerHTML = action.getContent();
            button.title = action.getLabel();
            button.style.order = action.getOrder();
            if(action.getClassName() !== '') {
                button.classList.add(action.getClassName());
            }
            const actionElements = [];
            const callback = async () => {
                let modal;
                const modalContent = document.createElement('div');
                modalContent.classList.add(dataTableSelectors.classes.groupActionWrapper);
                actions.forEach((action) => {
                    const actionElement = document.createElement('div');
                    actionElement.innerText = action.getContent();
                    actionElement.title = action.getLabel();
                    actionElement.classList.add(dataTableSelectors.classes.groupActionElement);
                    if(action.getClassName() !== '') {
                        actionElement.classList.add(action.getClassName());
                    }
                    const actionCallback = async () => {
                        let loader = new Loader({size:'20px', thickness:'3px'});
                        if(action.getPromptMessage()) {
                            if(!confirm(action.getPromptMessage())) {
                                return;
                            }
                        }
                        if(modal) {
                            modal.hide();
                        }
                        if(action.getLockDuringCallback()) {
                            if(this.#rowElement) {
                                this.#rowElement.classList.add(dataTableSelectors.classes.locked);
                            }
                        }
                        if(action.getUseLoader()) {
                            loader.start(button, ['svg'])
                        }
                        await action.getCallback()(this.#entity);

                        if(action.getUseLoader()) {
                            loader.stop(button, ['svg']);
                        }
                        if(action.getFlashOnSuccess()) {
                            if(this.#rowElement) {
                                this.#rowElement.classList.add(dataTableSelectors.classes.flash);
                                this.#rowElement.addEventListener('animationend', () => {
                                    this.#rowElement.classList.remove(dataTableSelectors.classes.flash);
                                }, { once: true });
                            }
                        }
                        if(action.getLockDuringCallback()) {
                            if(this.#rowElement) {
                                this.#rowElement.classList.remove(dataTableSelectors.classes.locked);
                            }
                        }
                        loader.destroy();
                        loader = null;
                    }
                    actionElement.addEventListener('click', actionCallback);
                    actionElements.push({element: actionElement, callback: actionCallback});
                    modalContent.appendChild(actionElement);
                })
                modal = new Modal(
                    {
                        width:'max-content',
                        height:'max-content',
                        destroyOnClose: true,
                        beforeHideCallback: () => {
                            actionElements.forEach((actionElement) => {
                                actionElement.element.removeEventListener('click', actionElement.callback);
                                actionElement.callback = null;
                            });
                            modal = null;
                        }
                });
                document.body.appendChild(modal.getView());
                modal.populateWithElement(modalContent);
                modal.show();
            }
            button.addEventListener('click', callback);
            this.#groupActions.push({button, callback});
            return button;
        }
        return null;
    }

    #checkboxCallback = () => {
        this.#eventEmitter.emit(events.checkboxChange, {entityId: this.#entityId, checked: this.#checkboxElement.checked, checkbox: this.#checkboxElement});
    }

    getView() {
        return this.#rowElement;
    }

    getAdditionalDataRow() {
        return this.#additionalDataRow;
    }

    destroy() {
        this.#actionElementsData.forEach((actionData) => {
            actionData.button.removeEventListener('click', actionData.callback);
            actionData.callback = null;
        });
        this.#actionElementsData = [];
        this.#editColumns.forEach((editColumn) => {
            editColumn.td.removeEventListener('click', editColumn.callback);
            editColumn.callback = null;
        });
        this.#editColumns = [];
        this.#dynamicActionsData.forEach((actionData) => {
            actionData.button.removeEventListener('click', actionData.callback);
            actionData.callback = null;
        });
        this.#dynamicActionsData.clear();
        this.#dynamicActionsData = null;
        this.#groupActions.forEach((groupAction) => {
            groupAction.button.removeEventListener('click', groupAction.callback);
            groupAction.callback = null;
        });
        this.#groupActions = null;
        this.#entity = null;
        this.#tableHeaders = null;
        this.#actions = null;
        this.#mode = null;
        this.#useCheckbox = null;
        if(this.#useCheckbox && this.#checkboxElement) {
            this.#checkboxElement.removeEventListener('change', this.#checkboxCallback);
        }
        this.#checkboxElement = null;
        this.#entityId = null;
        this.#actionFilter = null;
        this.#tdStyler = null;
        this.#eventEmitter = null;
        this.#rowElement.remove();
        this.#rowElement = null;
        if(this.#additionalDataRow) {
            this.#additionalDataRow.remove();
        }
        this.#additionalDataRow = null;
        this.#showAdditionalContentOnLoad = null;
    }
}