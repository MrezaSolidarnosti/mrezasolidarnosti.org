import {crudPageSelectors} from "./crudPageSelectors.js";
import Modal from "./Modal/Modal.js";
import FormValidator from "../FormValidator/FormValidator.js";
import FormObserver from "../FormObserver/FormObserver.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./Modal/events.js";
import Message from "../Message/Message.js";
import Response from "../Response/Response.js";
import DataTableAction from "../DataTable/DataTableAction/DataTableAction.js";
import DataTable from "../DataTable/DataTable.js";
import DataTableBulkAction from "../DataTable/DataTableBulkAction/DataTableBulkAction.js";
import {events as dataTableEvents} from "../DataTable/events.js";
import SelectSearch from "../SelectSearch/SelectSearch.js";
import {selectSearchSelectors} from "../SelectSearch/selectSearchSelectors.js";
import TabbedContent from "../TabbedContent/TabbedContent.js";
import {events as formValidatorEvents} from "../FormValidator/events.js";
import {formFieldSelectors} from "../FormValidator/formFieldSelectors.js";
import {imageInFormSelectors} from "../MediaLibrary/ImageInForm/imageInFormSelectors.js";
import ImageInForm from "../MediaLibrary/ImageInForm/ImageInForm.js";
import {galleryInFormSelectors} from "../MediaLibrary/GalleryInForm/galleryInFormSelectors.js";
import GalleryInForm from "../MediaLibrary/GalleryInForm/GalleryInForm.js";
import DocumentInForm from "../MediaLibrary/DocumentInForm/DocumentInForm.js";
import {documentInFormSelectors} from "../MediaLibrary/DocumentInForm/documentInFormSelectors.js";
import {multipleSelectSelectors} from "../MultipleSelect/multipleSelectSelectors.js";
import MultipleSelect from "../MultipleSelect/MultipleSelect.js";
import AjaxInputSearch from "../AjaxInputSearch/AjaxInputSearch.js";
import {documentsInFormSelectors} from "../MediaLibrary/DocumentsInForm/documentsInFormSelectors.js";
import DocumentsInForm from "../MediaLibrary/DocumentsInForm/DocumentsInForm.js";
import {tooltipSelectors} from "../Tooltip/tooltipSelectors.js";
import Tooltip from "../Tooltip/Tooltip.js";
import {dynamicInputsSelectors} from "../DynamicInputs/dynamicInputsSelectors.js";
import DynamicInputs from "../DynamicInputs/DynamicInputs.js";
import {textEditorSelectors} from "../TextEditor/textEditorSelectors.js";
import TextEditor from "../TextEditor/TextEditor.js";
import AjaxMultipleValuesSearch from "../AjaxMultipleValuesSearch/AjaxMultipleValuesSearch.js";
import {ajaxMultipleValuesSearchSelectors} from "../AjaxMultipleValuesSearch/ajaxMultipleValuesSearchSelectors.js";
import {valuesGeneratorSelector} from "../ValuesGenerator/valuesGeneratorSelectors.js";
import ValuesGenerator from "../ValuesGenerator/ValuesGenerator.js";
import {dataTableSelectors} from "../DataTable/dataTableSelectors.js";
import Translator from "../Translator/Translator.js";
import DynamicDataTableAction from "../DataTable/DynamicDataTableAction/DynamicDataTableAction.js";
import DataTableGroupAction from "../DataTable/DataTableGroupAction/DataTableGroupAction.js";
import FilterCheckboxModifier from "./FilterCheckboxModifier/FilterCheckboxModifier.js";
import {events as filterCheckboxModifierEvents} from "./FilterCheckboxModifier/events.js";
import HTMLModal from "../Modal/Modal.js"
import Shortcut from "../Shortcuts/Shortcut.js";


export default class CrudPage {

    static BADGE_TYPES = {
        GREEN: 'green',
        RED: 'red',
        BLUE: 'blue',
        YELLOW: 'yellow',
        PURPLE: 'purple',
        ORANGE: 'orange',
        GRAY: 'gray'
    }

    baseAction;
    tableDataEndpoint;
    formEndpoint;
    getEntityDataByIdEndpoint;
    deleteEntityEndpoint;
    bulkDeleteEntityEndpoint;
    exportEndpoint;


    eventEmitter = new EventEmitter();
    modal;
    modalConfig = {}
    dataTable;
    dataTableConfig = {};
    submitCallback = null;

    #messageContainer;
    #messagesContainerFixed;
    #createNewButton;
    #formValidator;
    #formObserver;
    #isFetchingForm = false;
    #isSubmittingModalForm = false;
    #dataTableActions = new Map();
    #dataTableBulkActions = new Map();
    #selectSearches = [];
    #selectSearchesInForm = [];
    #tabbedContent = null;
    #imagesInForm = [];
    #galleriesInForm = [];
    #documentInputsInForm = [];
    #multipleSelects = [];
    #ajaxInputSearches = [];
    #documentsInForm = [];
    #tooltips = [];
    #dynamicInputsInForm = [];
    #textEditors = [];
    #ajaxMultipleValuesSearches = [];
    #valueGeneratorsInForm = [];
    #checkboxModifiers = [];
    #blockActions = [];
    constructor() {
        this.baseAction = '/' + window.location.pathname.split('/')[1] ?? '';
    }

    init() {
        try {
            this.preload();
            this.setEndpoints();
            this.#setElements();
            this.#setModal();
            this.#addListeners();
            this.#setBaseDataTableActions();
            this.#setBaseBulkDataTableActions();
            this.#setDataTable();
            this.#listenToDataTableEvents();
            this.initDataTable();
            this.finalize();
            this.#openFormIfIDInGET();
        } catch (e) {
            console.error(e);
        }
    }

    setEndpoints() {
        this.tableDataEndpoint = `${this.baseAction}/tableHandler/`;
        this.formEndpoint = `${this.baseAction}/form/`;
        this.getEntityDataByIdEndpoint = `${this.baseAction}/getEntityData/`;
        this.deleteEntityEndpoint = `${this.baseAction}/delete/`;
        this.bulkDeleteEntityEndpoint = `${this.baseAction}/deleteBulk/`;
        this.exportEndpoint = `${this.baseAction}/export/`;
    }

    #setElements() {
        this.#messageContainer = document.getElementById(crudPageSelectors.ids.messageContainer);
        this.#messagesContainerFixed = document.getElementById(crudPageSelectors.ids.messageContainerFixed);
        this.#createNewButton = document.getElementById(crudPageSelectors.ids.createNewButton);
        if(!this.#messageContainer) {
           throw new Error(`${ crudPageSelectors.ids.messageContainer } is not found on the page.`);
        }
    }

    #setModal() {
        this.modal = new Modal(this.modalConfig, this.eventEmitter);
        if(this.modal.getConfigValue('warnUserIfFormEditedOnModalClose')) {
            this.modal.interruptModalClose = () => {
                if(this.#isFetchingForm) {
                    return false;
                }
                if (this.#formObserver && this.#formObserver.isModified()) {
                    return confirm(Translator.translate('You have unsaved changes. Are you sure you want to close the modal?'));
                }
                return true;
            }
        }
        this.modal.init();
    }

    #addListeners() {
        if(this.#createNewButton) {
            this.#createNewButton.addEventListener('click', this.createNewCallback);
        }
        this.#listenToModalEvents();
        this.#addSearchSelectListeners();
    }

    #setBaseDataTableActions() {
        this.setDataTableAction({
            name: 'delete',
            label: Translator.translate('Delete'),
            order: 999,
            className: 'deleteEntity',
            content: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M135.2 17.7L128 32H32C14.3 32 0 46.3 0 64S14.3 96 32 96H416c17.7 0 32-14.3 32-32s-14.3-32-32-32H320l-7.2-14.3C307.4 6.8 296.3 0 284.2 0H163.8c-12.1 0-23.2 6.8-28.6 17.7zM416 128H32L53.2 467c1.6 25.3 22.6 45 47.9 45H346.9c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg>',
            promptMessage: Translator.translate('Are you sure you want to perform this action?'),
            useLoader: true,
            lockRowDuringCallback: true,
            callback: async (entity) => {
                const req = await fetch(`${this.deleteEntityEndpoint}${entity.id}/`, {method: 'POST'});
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                const success = this.handleResponseFromForm(new Response(res));
                if(success) {
                    this.reloadTable(true);
                }
            }
        });
        this.setDataTableAction({
            name: 'edit',
            label: Translator.translate('Edit'),
            order: 0,
            className: 'editEntity',
            content: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M441 58.9L453.1 71c9.4 9.4 9.4 24.6 0 33.9L424 134.1 377.9 88 407 58.9c9.4-9.4 24.6-9.4 33.9 0zM209.8 256.2L344 121.9 390.1 168 255.8 302.2c-2.9 2.9-6.5 5-10.4 6.1l-58.5 16.7 16.7-58.5c1.1-3.9 3.2-7.5 6.1-10.4zM373.1 25L175.8 222.2c-8.7 8.7-15 19.4-18.3 31.1l-28.6 100c-2.4 8.4-.1 17.4 6.1 23.6s15.2 8.5 23.6 6.1l100-28.6c11.8-3.4 22.5-9.7 31.1-18.3L487 138.9c28.1-28.1 28.1-73.7 0-101.8L474.9 25C446.8-3.1 401.2-3.1 373.1 25zM88 64C39.4 64 0 103.4 0 152V424c0 48.6 39.4 88 88 88H360c48.6 0 88-39.4 88-88V312c0-13.3-10.7-24-24-24s-24 10.7-24 24V424c0 22.1-17.9 40-40 40H88c-22.1 0-40-17.9-40-40V152c0-22.1 17.9-40 40-40H200c13.3 0 24-10.7 24-24s-10.7-24-24-24H88z"/></svg>',
            callback: async (entity) => {
                if(this.#isFetchingForm) {
                    return;
                }
                this.#isFetchingForm = true;
                this.modal.openModal();
                this.modal.startLoader();
                const req = await fetch(`${this.formEndpoint}${entity.id}/`);
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    this.modal.closeModal();
                    this.#isFetchingForm = false;
                    return;
                }
                const res = await req.text();
                this.modal.stopLoader();
                this.modal.populateModalContentStrict(res);
                if(this.modal.isModalOpen()) {
                    this.#onFormReadyInternal(entity);
                    this.onFormReady({action: 'edit', entity: entity});
                }
                this.#isFetchingForm = false;
            }
        });
    }

    #setBaseBulkDataTableActions() {
        this.setDataTableBulkAction({
            name: 'delete',
            content: Translator.translate('Delete'),
            useLoader: true,
            promptMessage: Translator.translate('Are you sure?'),
            callback: async (ids) => {
                if(ids.length === 0) {
                    return;
                }
                const req = await fetch(this.bulkDeleteEntityEndpoint, {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                });
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                const success = this.handleResponseFromForm(new Response(res));
                if(success) {
                    this.reloadTable();
                }
            }
        });
    }

    #setDataTable() {
        this.dataTable = new DataTable({
            baseAction: this.baseAction,
            tableDataEndpoint: this.tableDataEndpoint,
            exportEndpoint: this.exportEndpoint,
            actions: this.getDataTableActions(),
            actionFilter: this.actionFilter,
            bulkActions: this.getDataTableBulkActions(),
            tdStyler: this.tdStyler,
            trStyler: this.trStyler,
            countTdFilter: this.countTdFilter,
            afterRowRender: this.afterRowRender,
            config: this.dataTableConfig,
        });
        this.onTableInitialized();
    }

    #listenToDataTableEvents() {
        this.dataTable.eventEmitter.on(dataTableEvents.tablePopulated, () => {
            this.onTablePopulated();
        });
        this.dataTable.eventEmitter.on(dataTableEvents.beforeTablePopulated, () => {
            this.onBeforeTablePopulated();
        });
        this.dataTable.eventEmitter.on(dataTableEvents.tableFirstTimePopulated, () => {
            this.onTableFirstTimePopulated();
        })
    }

    #openFormIfIDInGET() {
        const id = new URLSearchParams(window.location.search).get('id');
        if(id) {
            this.#isFetchingForm = true;
            this.modal.openModal();
            this.modal.startLoader();
            fetch(`${this.formEndpoint}${id}/`)
            .then(res => res.text())
            .then(res => {
                this.modal.stopLoader();
                this.modal.populateModalContentStrict(res);
                if(this.modal.isModalOpen()) {
                    this.#onFormReadyInternal({id: id});
                    this.onFormReady({action: 'edit', entity: {id: id}});
                }
                this.#isFetchingForm = false;
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    initDataTable() {
        void this.dataTable.init();
    }

    #listenToModalEvents() {
        this.eventEmitter.on(events.modalBeforeClose, () => {
           this.#onModalBeforeCloseInternal();
           this.onModalBeforeClose();
        });
        this.eventEmitter.on(events.modalClosed, () => {
            this.onModalClosed();
        });
    }

    #onModalBeforeCloseInternal() {
        if (this.#formObserver) {
            this.#formObserver.destroy();
        }
        if (this.#formValidator) {
            this.#formValidator.destroy();
        }
        if (this.modal.getForm()) {
            this.modal.getForm().removeEventListener('submit', this.submitCallback);
        }
        this.submitCallback = null;
        this.#selectSearchesInForm.forEach((search) => {
            search.destroy();
        });
        this.#selectSearchesInForm = [];
        if(this.#tabbedContent) {
            this.#tabbedContent.destroy();
            this.#tabbedContent = null;
        }
        if(this.#imagesInForm) {
            this.#imagesInForm.forEach((imageInForm) => {
                imageInForm.destroy();
            });
        }
        this.#imagesInForm = [];
        if(this.#galleriesInForm) {
            this.#galleriesInForm.forEach((galleryInForm) => {
                galleryInForm.destroy();
            });
        }
        this.#galleriesInForm = [];
        if(this.#documentInputsInForm) {
            this.#documentInputsInForm.forEach((documentInput) => {
                documentInput.destroy();
            });
        }
        this.#documentInputsInForm = [];
        if(this.#multipleSelects) {
            this.#multipleSelects.forEach((multipleSelect) => {
                multipleSelect.destroy();
            });
        }
        this.#multipleSelects = [];
        if(this.#ajaxInputSearches) {
            this.#ajaxInputSearches.forEach((ajaxInputSearch) => {
                ajaxInputSearch.destroy();
            });
        }
        this.#ajaxInputSearches = [];
        if(this.#documentsInForm) {
            this.#documentsInForm.forEach((documentInForm) => {
                documentInForm.destroy();
            });
        }
        this.#documentsInForm = [];
        if(this.#tooltips) {
            this.#tooltips.forEach((tooltip) => {
                tooltip.destroy();
            });
        }
        this.#tooltips = [];
        if(this.#dynamicInputsInForm) {
            this.#dynamicInputsInForm.forEach((dynamicInputs) => {
                dynamicInputs.destroy();
            });
        }
        this.#dynamicInputsInForm = [];
        if(this.#textEditors) {
            this.#textEditors.forEach((textEditor) => {
                textEditor.destroy();
            });
        }
        this.#textEditors = [];
        if(this.#ajaxMultipleValuesSearches) {
            this.#ajaxMultipleValuesSearches.forEach((ajaxMultipleValuesSearch) => {
                ajaxMultipleValuesSearch.destroy();
            });
        }
        this.#ajaxMultipleValuesSearches = [];
        if(this.#valueGeneratorsInForm) {
            this.#valueGeneratorsInForm.forEach((valueGenerator) => {
                valueGenerator.destroy();
            });
        }
        this.#valueGeneratorsInForm = [];
    }

    #addSearchSelectListeners() {
        const selects = document.querySelectorAll(`.${selectSearchSelectors.classes.selectSearchContainer}`);
        selects.forEach((container) => {
            const search = new SelectSearch(container, Translator.translate('Search'));
            search.init();
            this.#selectSearches.push(search);
        });
    }

    createNewCallback = async (e) => {
        if(this.#isFetchingForm) {
            return;
        }
        this.#isFetchingForm = true;
        this.modal.openModal();
        this.modal.startLoader();
        const req = await fetch(this.formEndpoint);
        if(req.redirected && req.url.includes('loginForm')) {
            Message.spawn({
                message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                type: Message.TYPES.ERROR,
                view: {
                    container: this.getMessagesContainerFixed(),
                    type: Message.VIEW_TYPES.NOTIFICATION,
                }
            });
            this.modal.closeModal();
            this.#isFetchingForm = false;
            return;
        }
        const res = await req.text();
        this.modal.stopLoader();
        this.modal.populateModalContentStrict(res);
        if(this.modal.isModalOpen()) {
            this.#onFormReadyInternal();
            this.onFormReady({action: 'create'});
        }
        this.#isFetchingForm = false;
    }

    handleResponseFromForm(response) {
        this.modal.emptyMessageContainer();
        this.emptyMessageContainer();
        if (response.getErrors()) {
            if (response.getCSRFTokenInput()) {
                this.modal.updateCSRFToken(response.getCSRFTokenInput());
            }
            response.getErrors().forEach((error) => {
                Message.spawn({
                    message: error.message,
                    type: Message.TYPES.ERROR,
                    view: {
                        container: this.modal.getMessageContainer(),
                    },
                });
            })
            this.modal.scrollToTop();
            return false;
        }
        if(this.#formObserver) {
            this.#formObserver.resetNumberOfChangedElements();
        }
        if(this.modal.isModalOpen()) {
            this.modal.closeModal();
        }
        if(response.getGeneralErrors()) {
            response.getGeneralErrors().forEach((error) => {
                Message.spawn({
                    message: error.message,
                    type: Message.TYPES.ERROR,
                    view: {
                        container: this.getMessageContainer(),
                    },
                });
            });
            this.modal.scrollToTop();
            return false;
        }
        if(response.getMessage()) {
            Message.spawn({
                message: response.getMessage(),
                type: response.getStatus() ? Message.TYPES.SUCCESS : Message.TYPES.ERROR,
                view: {
                    container: this.getMessageContainer(),
                },
            });
        }
        return true;
    }


    #onFormReadyInternal(entity = null) {
        this.#setFormValidator();
        if(this.modal.getConfigValue('warnUserIfFormEditedOnModalClose')) {
            this.#setFormObserver();
        }
        if(this.modal.getForm()) {
            this.submitCallback = async (e) => {
                void this.submitFormCallback(e, entity)
            }
            this.modal.getForm().addEventListener('submit', this.submitCallback);
        }
        this.#addSearchSelectListenersInForm();
        this.#addTabbedContent();
        this.#handleFormValidatorWithTabbedContent();
        this.#handleImagesInForm();
        this.#handleGalleriesInForm();
        this.#handleDocumentInputsInForm();
        this.#handleMultipleSelectsInForm();
        this.#handleAjaxInputSearches();
        this.#handleDocumentsInForm();
        this.#handleTooltipsInForm();
        this.#handleDynamicInputsInForm();
        this.#handleTextEditorsInForm();
        this.#handleAjaxMultipleValuesSearchesInForm();
        this.#handleValueGeneratorsInForm();
    }

    #addSearchSelectListenersInForm() {
        const selects = this.modal.getModalElement().querySelectorAll(`.${selectSearchSelectors.classes.selectSearchContainer}`);
        selects.forEach((container) => {
            const search = new SelectSearch(container, Translator.translate('Search'));
            search.init();
            this.#selectSearchesInForm.push(search);
        });
    }

    #addTabbedContent() {
        const container = this.modal.getForm();
        if(container) {
            this.#tabbedContent = new TabbedContent(container);
            this.#tabbedContent.init();
        }
    }

    #handleFormValidatorWithTabbedContent() {
        if(this.#tabbedContent && this.#formValidator) {
            this.#formValidator.eventEmitter.on(formValidatorEvents.invalidFormSubmitted, () => {
                const firstInvalidTabData = this.#tabbedContent.getTabbedContentWithClassNameInside(formFieldSelectors.classes.invalidFormField);
                if(firstInvalidTabData) {
                    this.#tabbedContent.showTabContent(firstInvalidTabData.index);
                }
            });
        }
    }

    #handleImagesInForm() {
        const imagesInForm = this.modal.getModalElement().querySelectorAll(`.${imageInFormSelectors.classes.imageSelect}`);
        if(imagesInForm.length > 0) {
            imagesInForm.forEach((imageInForm) => {
                const imageInFormInstance = new ImageInForm(imageInForm);
                imageInFormInstance.init();
                this.#imagesInForm.push(imageInFormInstance);
            });
        }
    }

    #handleGalleriesInForm() {
        const galleriesInForm = this.modal.getModalElement().querySelectorAll(`.${galleryInFormSelectors.classes.gallerySelect}`);
        if(galleriesInForm.length > 0) {
            galleriesInForm.forEach((galleryInForm) => {
                const galleryInFormInstance = new GalleryInForm(galleryInForm);
                galleryInFormInstance.init();
                this.#galleriesInForm.push(galleryInFormInstance);
            });
        }
    }

    #handleDocumentInputsInForm() {
        const documentInputsInForm = this.modal.getModalElement().querySelectorAll(`.${documentInFormSelectors.classes.documentSelect}`);
        if(documentInputsInForm.length > 0) {
            documentInputsInForm.forEach((documentInput) => {
                const documentInputInstance = new DocumentInForm(documentInput);
                documentInputInstance.init();
                this.#documentInputsInForm.push(documentInputInstance);
            });
        }
    }

    #handleMultipleSelectsInForm() {
        const multipleSelects = this.modal.getModalElement().querySelectorAll(`.${multipleSelectSelectors.classes.container}`);
        if(multipleSelects.length > 0) {
            multipleSelects.forEach((multipleSelect) => {
                const multipleSelectInstance = new MultipleSelect(multipleSelect);
                multipleSelectInstance.init();
                this.#multipleSelects.push(multipleSelectInstance);
            });
        }
    }

    #handleAjaxInputSearches() {
        const ajaxInputSearches = this.modal.getModalElement().querySelectorAll(`.${crudPageSelectors.classes.ajaxInputSearch}`);
        if (ajaxInputSearches.length > 0) {
            ajaxInputSearches.forEach((ajaxInputSearch) => {
                const ajaxInputSearchInstance = new AjaxInputSearch({
                    container: ajaxInputSearch,
                    input: ajaxInputSearch.querySelector(`.${crudPageSelectors.classes.ajaxInputSearchViewInput}`),
                    targetInput: ajaxInputSearch.querySelector(`.${crudPageSelectors.classes.ajaxInputSearchTargetInput}`),
                    endpoint: ajaxInputSearch.getAttribute(crudPageSelectors.attributes.ajaxInputSearchEndpoint),
                    viewColumnName: ajaxInputSearch.getAttribute(crudPageSelectors.attributes.ajaxInputSearchViewColumnName),
                    idColumnName: ajaxInputSearch.getAttribute(crudPageSelectors.attributes.ajaxInputSearchIdColumnName),
                    searchFilters: JSON.parse(ajaxInputSearch.getAttribute(crudPageSelectors.attributes.ajaxInputSearchSearchFilters) ?? null),
                    searchPlaceholder: Translator.translate('Search')
                });
                ajaxInputSearchInstance.init();
                this.#ajaxInputSearches.push(ajaxInputSearchInstance);
            });
        }
    }

    #handleDocumentsInForm() {
        const documentsInForm = this.modal.getModalElement().querySelectorAll(`.${documentsInFormSelectors.classes.documentsSelect}`);
        if (documentsInForm.length > 0) {
            documentsInForm.forEach((documentInForm) => {
                const documentsInFormInstance = new DocumentsInForm(documentInForm);
                documentsInFormInstance.init();
                this.#documentsInForm.push(documentsInFormInstance);
            });
        }
    }

    #handleTooltipsInForm() {
        const tooltipContainers = this.modal.getModalElement().
        querySelectorAll(`.${crudPageSelectors.classes.inputContainer}:has(.${tooltipSelectors.classes.trigger})`);
        if (tooltipContainers.length > 0) {
            tooltipContainers.forEach((container) => {
                const tooltip = new Tooltip(container);
                tooltip.init();
                this.#tooltips.push(tooltip);
            });
        }
    }

    #handleDynamicInputsInForm() {
        const dynamicInputsContainers = this.modal.getModalElement().querySelectorAll(`.${dynamicInputsSelectors.classes.container}`);
        if (dynamicInputsContainers.length > 0) {
            dynamicInputsContainers.forEach((container) => {
                const dynamicInputs = new DynamicInputs({
                    container: container,
                    baseInputName: container.getAttribute(dynamicInputsSelectors.attributes.dynamicInputsBaseInputName),
                    config: JSON.parse(container.getAttribute(dynamicInputsSelectors.attributes.dynamicInputsConfig) ?? null)
                });
                dynamicInputs.init();
                this.#dynamicInputsInForm.push(dynamicInputs);
            });
        }
    }

    #handleTextEditorsInForm() {
        const textEditorContainers = this.modal.getModalElement().querySelectorAll(`.${textEditorSelectors.classes.container}`);
        if (textEditorContainers.length > 0) {
            textEditorContainers.forEach((container) => {
                const input = container.parentElement.querySelector(`.${crudPageSelectors.classes.textEditorInput}`);
                const textEditor = new TextEditor(container, input, input.value ?? null);
                textEditor.init();
                this.#textEditors.push(textEditor);
            });
        }
    }


    #handleAjaxMultipleValuesSearchesInForm() {
        const ajaxMultipleValuesSearchContainers = this.modal.getModalElement().querySelectorAll(`.${ajaxMultipleValuesSearchSelectors.classes.container}`);
        if(ajaxMultipleValuesSearchContainers.length > 0) {
            ajaxMultipleValuesSearchContainers.forEach((container) => {
                const ajaxMultipleValuesSearch = new AjaxMultipleValuesSearch({
                    container: container,
                    input: container.querySelector(`.${crudPageSelectors.classes.ajaxInputSearchViewInput}`),
                    searchValuesInput: container.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.searchValuesInput}`),
                    endpoint: container.getAttribute(crudPageSelectors.attributes.ajaxInputSearchEndpoint),
                    viewColumnName: container.getAttribute(crudPageSelectors.attributes.ajaxInputSearchViewColumnName),
                    idColumnName: container.getAttribute(crudPageSelectors.attributes.ajaxInputSearchIdColumnName),
                    inputName: container.getAttribute(crudPageSelectors.attributes.ajaxInputSearchInputName),
                    inputNameNew: container.getAttribute(crudPageSelectors.attributes.ajaxMultipleValuesSearchNewInputName) ?? null,
                    valuesContainer: container.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.valuesContainer}`),
                    searchFilters: JSON.parse(container.getAttribute(crudPageSelectors.attributes.ajaxInputSearchSearchFilters) ?? null),
                    searchPlaceholder: Translator.translate('Search')
                });
                ajaxMultipleValuesSearch.init();
                this.#ajaxMultipleValuesSearches.push(ajaxMultipleValuesSearch);
            });
        }
    }

    #handleValueGeneratorsInForm() {
        const valueGenerators = this.modal.getModalElement().querySelectorAll(`.${valuesGeneratorSelector.classes.container}`);
        if(valueGenerators.length > 0) {
            valueGenerators.forEach((valueGenerator) => {
                const valueGeneratorInstance = new ValuesGenerator(valueGenerator);
                valueGeneratorInstance.init();
                this.#valueGeneratorsInForm.push(valueGeneratorInstance);
            });
        }
    }

    #setFormValidator() {
        const form = this.modal.getForm();
        if(form) {
            this.#formValidator = new FormValidator({
                form: form,
                formFieldClassNames: crudPageSelectors.classes.input,
                formScrollableContainer: this.modal.getModalElement()
            })
            this.#formValidator.init();
        }
    }

    #setFormObserver() {
        const form = this.modal.getForm();
        if(form) {
            this.#formObserver = new FormObserver(form);
            this.#formObserver.observe();
        }
    }

    submitFormCallback = async (e, entity) => {
        e.preventDefault();
        if(this.#isSubmittingModalForm) {
            return;
        }
        this.onFormSubmitStart();
        this.#isSubmittingModalForm = true;
        this.modal.startLoaderInSubmitButton();
        const form = e.target;
        const formData = new FormData(form);
        let action = `${this.baseAction}/create/`;
        if(entity) {
            action = `${this.baseAction}/update/${entity.id}/`;
        }
        const dataAction = form.getAttribute('data-action');
        const req = await fetch(action, {method: 'POST', body: formData});
        if(req.redirected && req.url.includes('loginForm')) {
            Message.spawn({
                message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                type: Message.TYPES.ERROR,
                view: {
                    container: this.getMessagesContainerFixed(),
                    type: Message.VIEW_TYPES.NOTIFICATION,
                }
            });
            this.modal.closeModal();
            this.#isFetchingForm = false;
            return;
        }
        const res = await req.json();
        this.modal.stopLoaderInSubmitButton();
        const responseObject = new Response(res);
        if(responseObject.response.status) {
            this.onFormSubmitSuccess(responseObject);
        } else {
            this.onFormSubmitFail(responseObject);
        }
        this.onFormSubmitEnd(responseObject);
        const success = this.handleResponseFromForm(responseObject);
        this.#isSubmittingModalForm = false;
        if(success) {
            let keepCurrentPage = false;
            if(dataAction === 'update') {
                keepCurrentPage = true;
            }
            this.reloadTable(keepCurrentPage);
        }
    }

    onFormSubmitStart() {

    }

    onFormSubmitSuccess(response) {

    }

    onFormSubmitFail(response) {

    }

    onFormSubmitEnd(response) {

    }

    getMessageContainer() {
        return this.#messageContainer;
    }

    getMessagesContainerFixed() {
        return this.#messagesContainerFixed;
    }

    emptyMessageContainer() {
        this.#messageContainer.innerHTML = '';
    }

    emptyMessagesContainerFixed() {
        this.#messagesContainerFixed.innerHTML = '';
    }

    setDataTableAction(config) {
        this.#dataTableActions.set(config.name, new DataTableAction(config));
    }

    setDynamicDataTableAction(config) {
        this.#dataTableActions.set(config.name, new DynamicDataTableAction(config));
    }

    setDataTableGroupAction(config) {
        this.#dataTableActions.set(config.name, new DataTableGroupAction(config));
    }

    getDataTableAction(actionName) {
        return this.#dataTableActions.get(actionName);
    }

    getDataTableActions() {
        return this.#dataTableActions;
    }


    removeDataTableAction(actionName) {
        this.#dataTableActions.delete(actionName);
    }

    setDataTableBulkAction(config) {
        this.#dataTableBulkActions.set(config.name, new DataTableBulkAction(config));
    }

    getDataTableBulkAction(actionName) {
        return this.#dataTableBulkActions.get(actionName);
    }

    getDataTableBulkActions() {
        return this.#dataTableBulkActions;
    }

    removeDataTableBulkAction(actionName) {
        this.#dataTableBulkActions.delete(actionName);
    }


    addFilterCheckboxModifier({
          label,
          filterName,
          modifierName,
          reloadTable = true,
          keepPagination = true
      }) {
        const modifier = new FilterCheckboxModifier({
            label: label,
            filterName: filterName,
            modifierName: modifierName
        });
        modifier.eventEmitter.on(filterCheckboxModifierEvents.modifierChanged, ({modifierName, isChecked}) => {
            if(this.dataTable) {
                const filterData = {};
                filterData[modifierName] = isChecked;
                this.dataTable.setCustomFilterData(filterData);
                if(reloadTable) {
                    this.reloadTable(keepPagination);
                }
            }
        });
        modifier.init();
        this.#checkboxModifiers.push(modifier);
    }

    getRowById(id) {
        if(this.dataTable) {
            return this.dataTable.getTable().querySelector(`tr[${dataTableSelectors.attributes.entityId}="${id}"]`);
        }
    }

    getRowTDByColumnName(row, columnName) {
        return row.querySelector(`td[${dataTableSelectors.attributes.columnName}="${columnName}"]`);
    }

    preload() {}

    finalize() {}

    onModalBeforeClose() {}

    onModalClosed() {}

    onFormReady(data) {}

    actionFilter = (action, entity) => {return action;}

    tdStyler = (td, columnName, columnValue, entity) => {return td}

    makeTDValueToBadge = (td, value, type = CrudPage.BADGE_TYPES.GREEN) => {
        td.innerHTML = `<span class="${type}">${value}</span>`;
    }

    trStyler = (tr, entity) => {return tr}

    countTdFilter = (td, columnName, columnValue, type) => {return td}
    afterRowRender = (entity, row) => {}

    reloadTable(keepCurrentPage = false) {
        const data = {};
        if(keepCurrentPage) {
            data.keepPaginationPage = true;
        }
        this.dataTable.eventEmitter.emit(dataTableEvents.requestReloadTable, data);
    }

    populateTable() {
        this.dataTable.eventEmitter.emit(dataTableEvents.requestPopulateTable);
    }

    applySort(sort) {
        if(this.dataTable) {
            this.dataTable.applySort(sort);
        }
    }

    setFilter(filter) {
        if(this.dataTable) {
            this.dataTable.setFilter(filter);
        }
    }


    onTablePopulated() {}

    onBeforeTablePopulated() {}

    onTableFirstTimePopulated() {}

    onTableInitialized() {}

    setBlockAction({name, label, icon, callback, setDefaultData, order, blocks = []}) {
        this.#blockActions.push({name, label, icon, callback, setDefaultData, order, blocks});
    }


    handleResponse(response, messageViewType = Message.VIEW_TYPES.NOTIFICATION) {
        let container;
        switch(messageViewType) {
            case Message.VIEW_TYPES.NOTIFICATION:
                container = this.getMessagesContainerFixed();
                break;
            case Message.VIEW_TYPES.STATIC:
                container = this.getMessageContainer();
                break;
        }
        if (response.getStatus()) {
            Message.spawn({
                message: response.getMessage(),
                type: Message.TYPES.SUCCESS,
                view: {
                    container: container,
                    type: messageViewType,
                }
            });
        } else {
            if (response.getErrors()) {
                response.getErrors().forEach((error) => {
                    Message.spawn({
                        message: error.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: container,
                            type: messageViewType,
                        }
                    });
                });
            }
            if (response.getGeneralErrorMessages()) {
                response.getGeneralErrorMessages().forEach((error) => {
                    Message.spawn({
                        message: error,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: container,
                            type: messageViewType,
                        }
                    });
                });
            }
        }
    }


    spawnMessageNotification(message, type = Message.TYPES.SUCCESS) {
        Message.spawn({
            message: message,
            type: type,
            view: {
                container: this.getMessagesContainerFixed(),
                type: Message.VIEW_TYPES.NOTIFICATION,
            }
        });
    }

    spawnMessageStatic(message, type = Message.TYPES.SUCCESS) {
        Message.spawn({
            message: message,
            type: type,
            view: {
                container: this.getMessageContainer(),
                type: Message.VIEW_TYPES.STATIC,
            }
        });
    }

    getAjaxInputSearches() {
        return this.#ajaxInputSearches;
    }

    destroy() {
        this.baseAction = null;
        this.tableDataEndpoint = null;
        this.formEndpoint = null;
        this.getEntityDataByIdEndpoint = null;
        this.modal.destroy();
        this.modal = null;
        this.modalConfig = null;
        if(this.#createNewButton) {
            this.#createNewButton.removeEventListener('click', this.createNewCallback);
        }
        this.submitCallback = null;
        this.#createNewButton = null;
        this.#messageContainer = null;
        this.#messagesContainerFixed = null;
        if(this.#formValidator) {
            this.#formValidator.destroy();
        }
        this.#formValidator = null;
        if(this.#formObserver) {
            this.#formObserver.destroy();
        }
        this.#formObserver = null;
        this.#isFetchingForm = null;
        this.#isSubmittingModalForm = null;
        this.#dataTableActions.forEach((action) => {
            action.destroy();
        });
        this.#dataTableActions = null;
        this.#dataTableBulkActions.forEach((action) => {
            action.destroy();
        });
        this.#dataTableBulkActions = null;
        this.#selectSearches.forEach((search) => {
            search.destroy();
        });
        this.#selectSearches = null;
        this.#selectSearchesInForm.forEach((search) => {
            search.destroy();
        });
        this.#selectSearchesInForm = null;
        if(this.#tabbedContent) {
            this.#tabbedContent.destroy();
        }
        this.#tabbedContent = null;
        if(this.#imagesInForm) {
            this.#imagesInForm.forEach((imageInForm) => {
                imageInForm.destroy();
            });
        }
        this.#imagesInForm = null;
        if(this.#galleriesInForm) {
            this.#galleriesInForm.forEach((galleryInForm) => {
                galleryInForm.destroy();
            });
        }
        this.#galleriesInForm = null;
        if(this.#documentInputsInForm) {
            this.#documentInputsInForm.forEach((documentInput) => {
                documentInput.destroy();
            });
        }
        this.#documentInputsInForm = null;
        if(this.#multipleSelects) {
            this.#multipleSelects.forEach((multipleSelect) => {
                multipleSelect.destroy();
            });
        }
        this.#multipleSelects = null;
        if(this.#ajaxInputSearches) {
            this.#ajaxInputSearches.forEach((ajaxInputSearch) => {
                ajaxInputSearch.destroy();
            });
        }
        this.#ajaxInputSearches = null;
        if(this.#documentsInForm) {
            this.#documentsInForm.forEach((documentInForm) => {
                documentInForm.destroy();
            });
        }
        this.#documentsInForm = null;
        if(this.#tooltips) {
            this.#tooltips.forEach((tooltip) => {
                tooltip.destroy();
            });
        }
        this.#tooltips = null;
        if(this.#dynamicInputsInForm) {
            this.#dynamicInputsInForm.forEach((dynamicInputs) => {
                dynamicInputs.destroy();
            });
        }
        this.#dynamicInputsInForm = null;
        if(this.#textEditors) {
            this.#textEditors.forEach((textEditor) => {
                textEditor.destroy();
            });
        }
        this.#textEditors = null;
        if(this.#ajaxMultipleValuesSearches) {
            this.#ajaxMultipleValuesSearches.forEach((ajaxMultipleValuesSearch) => {
                ajaxMultipleValuesSearch.destroy();
            });
        }
        this.#ajaxMultipleValuesSearches = null;
        if(this.#valueGeneratorsInForm) {
            this.#valueGeneratorsInForm.forEach((valueGenerator) => {
                valueGenerator.destroy();
            });
        }
        this.#valueGeneratorsInForm = null;
        this.#checkboxModifiers.forEach((modifier) => {
            modifier.destroy();
        });
        this.#checkboxModifiers = null;
        this.#blockActions = null;
        this.eventEmitter.destroy();
        this.eventEmitter = null;
        this.dataTable.destroy();
        this.dataTable = null;
        this.dataTableConfig = null;
        this.createNewCallback = null;
        this.submitFormCallback = null;
        this.preload = null;
        this.finalize = null;
        this.onModalBeforeClose = null;
        this.onModalClosed = null;
        this.onFormReady = null;
        this.actionFilter = null;
        this.tdStyler = null;
        this.trStyler = null;
        this.afterRowRender = null;
        this.countTdFilter = null;
        this.onTablePopulated = null;
        this.onBeforeTablePopulated = null;
    }
}