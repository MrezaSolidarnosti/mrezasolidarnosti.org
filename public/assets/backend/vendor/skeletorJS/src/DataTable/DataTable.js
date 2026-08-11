import {dataTableSelectors} from "./dataTableSelectors.js";
import Loader from "../Loader/Loader.js";
import {config as dataTableConfig} from "./config.js"
import LocalStorage from "../LocalStorage/LocalStorage.js";
import UserViewOptions from "./UserViewOptions/UserViewOptions.js";
import Row from "./Row/Row.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import Pagination from "./Pagination/Pagination.js";
import {rangeFilterSelectors} from "./RangeFilter/rangeFilterSelectors.js";
import {RangeFilter} from "./RangeFilter/RangeFilter.js";
import {events as rangeFilterEvents} from "./RangeFilter/events.js";
import ProgressBar from "../ProgressBar/ProgressBar.js";
import Translator from "../Translator/Translator.js";
import Export from "./Export/Export.js";

export default class DataTable {

    static STORAGE_KEYS = {
        PER_PAGE: 'perPage'
    }

    static MODES = {
        MOBILE: 'mobile',
        DESKTOP: 'desktop'
    }

    static CHECKBOX_SHIFT_DIRECTIONS = {
        UP: 'up',
        DOWN: 'down'
    }

    static RANGE_FILTERS_KEY = 'rangeFilters';

    #baseAction;
    #exportEndpoint;
    #tableDataEndpoint;
    #actions;
    #actionFilter;
    #bulkActions;
    #config;
    #tdStyler;
    #trStyler;
    #countTdFilter;
    #afterRowRender;

    #isShiftActive = false;
    #selectedIds = new Set();
    #userViewOptions = null;
    eventEmitter = new EventEmitter();
    #lastCheckedCheckbox = null;

    #customFilterData = {};
    #sort = null;
    #searchTimeout = null;
    #goToPageTimeout = null;
    #maxPage = null;
    #currentMode = null;
    #rows = [];
    #rangeFilters = [];
    #baseFilterNames = [];

    #tableContainer;
    #table;
    #tableBody;
    #tableOverlay;
    #tableWrapper;
    #tableTop;
    #tableBottom;
    #perPageSelect;
    #bulkActionsContainer;
    #bulkActionSelect;
    #bulkActionApplyButton;
    #tableSearchInput;
    #tableSearchType;
    #resultCountElement;
    #noResultsMessageElement;
    #selectAllCheckboxes;
    #selectAllCheckboxesBottom;
    #sortButtons;
    #filterSelects;
    #goToPageInputs;
    #tableHeaders;
    #export;
    #searchableColumnsList;
    #searchableColumnsTrigger;


    #tableOverlayProgress = new ProgressBar();
    #tableLoader = new Loader();
    #pagination = new Pagination(this.eventEmitter);

    constructor({baseAction, tableDataEndpoint, exportEndpoint, actions, actionFilter, bulkActions, tdStyler, trStyler, countTdFilter, afterRowRender, config}) {
        try {
            this.#baseAction = baseAction;
            this.#tableDataEndpoint = tableDataEndpoint;
            this.#exportEndpoint = exportEndpoint;
            this.#actions = actions;
            this.#bulkActions = bulkActions;
            this.#config = config;
            this.#actionFilter = actionFilter;
            this.#tdStyler = tdStyler;
            this.#trStyler = trStyler;
            this.#countTdFilter = countTdFilter;
            this.#afterRowRender = afterRowRender;
            this.#parseConfig();
            this.#setElements();
            this.#setCurrentMode();
            this.#setExporter();
        } catch (e) {
            console.error(e);
        }
    }

    #setElements() {
        this.#tableContainer = document.getElementById(dataTableSelectors.ids.tableContainer);
        this.#table = document.getElementById(dataTableSelectors.ids.table);
        this.#tableBody = document.getElementById(dataTableSelectors.ids.tableBody);
        this.#tableOverlay = document.getElementById(dataTableSelectors.ids.tableOverlay);
        this.#tableTop = document.getElementById(dataTableSelectors.ids.tableTop);
        this.#tableBottom = document.getElementById(dataTableSelectors.ids.tableBottom);
        this.#perPageSelect = document.getElementById(dataTableSelectors.ids.perPageSelect);
        this.#bulkActionsContainer = document.getElementById(dataTableSelectors.ids.bulkActionsContainer);
        this.#bulkActionSelect = document.getElementById(dataTableSelectors.ids.bulkActionSelect);
        this.#bulkActionApplyButton = document.getElementById(dataTableSelectors.ids.bulkActionApplyButton);
        this.#tableSearchInput = document.getElementById(dataTableSelectors.ids.tableSearchInput);
        this.#tableSearchType = document.getElementById(dataTableSelectors.ids.tableSearchType);
        this.#resultCountElement = document.getElementById(dataTableSelectors.ids.resultCountElement);
        this.#noResultsMessageElement = document.getElementById(dataTableSelectors.ids.noResultsMessageElement);
        this.#selectAllCheckboxes = document.getElementById(dataTableSelectors.ids.selectAllCheckboxes);
        this.#selectAllCheckboxesBottom = document.getElementById(dataTableSelectors.ids.selectAllCheckboxesBottom);
        this.#sortButtons = this.#table.querySelectorAll(`.${dataTableSelectors.classes.sortButtons}`);
        this.#filterSelects = document.querySelectorAll(`.${dataTableSelectors.classes.tableFilter}`);
        this.#goToPageInputs = document.querySelectorAll(`.${dataTableSelectors.classes.goToPageInput}`);
        this.#tableHeaders = this.#table.querySelectorAll(`.${dataTableSelectors.classes.dataColumn}`);
        this.#tableWrapper = document.getElementById(dataTableSelectors.ids.tableWrapper);
        this.#searchableColumnsList = document.getElementById(dataTableSelectors.ids.searchableColumnsList);
        this.#searchableColumnsTrigger = document.getElementById(dataTableSelectors.ids.searchableColumnsTrigger);

        if (!this.#tableContainer) {
            throw new Error(`${dataTableSelectors.ids.tableContainer} is not found on the page.`);
        }
        if (!this.#table) {
            throw new Error(`${dataTableSelectors.ids.table} is not found on the page.`);
        }
        if (!this.#tableBody) {
            throw new Error(`${dataTableSelectors.ids.tableBody} is not found on the page.`);
        }
        if (!this.#tableOverlay) {
            throw new Error(`${dataTableSelectors.ids.tableOverlay} is not found on the page.`);
        }
        if (!this.#tableTop) {
            throw new Error(`${dataTableSelectors.ids.tableTop} is not found on the page.`);
        }
        if (!this.#tableBottom) {
            throw new Error(`${dataTableSelectors.ids.tableBottom} is not found on the page.`);
        }
        if (!this.#perPageSelect) {
            throw new Error(`${dataTableSelectors.ids.perPageSelect} is not found on the page.`);
        }
        if (!this.#bulkActionsContainer) {
            throw new Error(`${dataTableSelectors.ids.bulkActionsContainer} is not found on the page.`);
        }
        if (!this.#bulkActionSelect) {
            throw new Error(`${dataTableSelectors.ids.bulkActionSelect} is not found on the page.`);
        }
        if (!this.#bulkActionApplyButton) {
            throw new Error(`${dataTableSelectors.ids.bulkActionApplyButton} is not found on the page.`);
        }
        if (!this.#tableSearchInput) {
            throw new Error(`${dataTableSelectors.ids.tableSearchInput} is not found on the page.`);
        }
        if (!this.#tableSearchType) {
            throw new Error(`${dataTableSelectors.ids.tableSearchType} is not found on the page.`);
        }
        if (!this.#resultCountElement) {
            throw new Error(`${dataTableSelectors.ids.resultCountElement} is not found on the page.`);
        }
        if (!this.#noResultsMessageElement) {
            throw new Error(`${dataTableSelectors.ids.noResultsMessageElement} is not found on the page.`);
        }
        if (!this.#selectAllCheckboxes) {
            throw new Error(`${dataTableSelectors.ids.selectAllCheckboxes} is not found on the page.`);
        }
        if (!this.#selectAllCheckboxesBottom) {
            throw new Error(`${dataTableSelectors.ids.selectAllCheckboxesBottom} is not found on the page.`);
        }
        if (!this.#sortButtons.length) {
            throw new Error(`${dataTableSelectors.classes.sortButtons} not found on the page.`);
        }
        if (!this.#goToPageInputs.length) {
            throw new Error(`${dataTableSelectors.classes.goToPageInput} not found on the page.`);
        }
        if (!this.#tableHeaders.length) {
            throw new Error(`${dataTableSelectors.classes.dataColumn} not found on the page.`);
        }
        if(!this.#tableWrapper) {
            throw new Error(`${dataTableSelectors.ids.tableWrapper} not found on the page.`);
        }
        if(!this.#searchableColumnsList) {
            throw new Error(`${dataTableSelectors.ids.searchableColumnsList} not found on the page.`);
        }
        if(!this.#searchableColumnsTrigger) {
            throw new Error(`${dataTableSelectors.ids.searchableColumnsTrigger} not found on the page.`);
        }
    }

    async init() {
        this.startProgressInOverlay();
        this.#addRangeFilters();
        this.#addListeners();
        this.#initBulkActions();
        this.#initCheckboxes();
        this.#setPerPageFromStorage();
        this.#setFiltersFromParams();
        this.#setDefaultSort();
        this.#initUserViewOptions();
        this.#listenToEvents();
        const data = await this.populateTable(this.#pagination.getActivePageFromURL());
        this.#pagination.generatePagination(this.maxPage, parseInt(data.entities.page));
        this.stopProgressInOverlay();
        this.hideTableOverlay();
        this.eventEmitter.emit(events.tableFirstTimePopulated);
    }

    #setCurrentMode() {
        if (window.innerWidth <= this.getConfigValue('mobileBreakpoint')) {
            this.#currentMode = DataTable.MODES.MOBILE;
        } else {
            this.#currentMode = DataTable.MODES.DESKTOP;
        }
    }

    #setExporter() {
        this.#export = new Export({
            columnData: this.#getColumnDataForUserViewOptions(),
            eventEmitter: this.eventEmitter
        });
        this.#export.init();
    }

    async populateTable(page = 1) {
        this.eventEmitter.emit(events.beforeTablePopulated);
        const requestData = this.#getRequestData(page);
        this.#tableLoader.start(this.#tableBody);
        this.makeTableBodyTransparent();
        const req = await fetch(this.#tableDataEndpoint, {method:'POST', body: requestData});
        const res = await req.json();
        const entitiesList = res.entities.data;
        const hasCountColumns = res.entities.countColumnData && Object.keys(res.entities.countColumnData).length > 0;
        this.#rows.forEach((row) => {
            row.destroy();
        });
        this.#rows = [];
        this.#selectedIds.clear();
        this.#selectAllCheckboxes.checked = false;
        this.#selectAllCheckboxesBottom.checked = false;
        this.removeNoResultsMessageContent();
        this.#removeCountColumns();
        this.#tableLoader.stop();
        if (entitiesList.length > 0) {
            this.#printEntities(entitiesList);
            if (hasCountColumns) {
                this.#printCountColumns(res.entities.countColumnData);
            }
        } else {
            this.printNoResultsMessage(res.message);
        }
        this.normalizeTableBody();
        this.maxPage = res.entities.maxPage;
        this.#setResultCount(res.entities);
        if (res.entities.page !== page) {
            let params = new URLSearchParams(window.location.search);
            params.set('page', res.entities.page);
            let newUrl = window.location.origin
                + window.location.pathname
                + '?' + params.toString();
            window.history.replaceState({path: newUrl}, '', newUrl);
        }
        this.eventEmitter.emit(events.tablePopulated);
        return res;
    }

    async reloadTable(data = {}) {
        let page = 1;
        if (data.keepPaginationPage) {
            page = this.#pagination.getActivePageFromURL()
        } else {
           this.#pushStateWithNoPage();
        }
        let populateData = await this.populateTable(page);
        this.#pagination.generatePagination(this.maxPage, parseInt(populateData.entities.page));
    }

    #pushStateWithNoPage() {
        let params = new URLSearchParams(window.location.search);
        if (params.get('page')) {
            params.delete('page');
            let newUrl = window.location.origin
                + window.location.pathname
                + '?' + params.toString();
            window.history.pushState({path: newUrl}, '', newUrl);
        }
    }

    #getRequestData(page = 1) {
        const formData = new FormData();
        if (this.#sort) {
            formData.append('orderBy', this.#sort.orderBy);
            formData.append('order', this.#sort.order);
        }
        const searchData = this.#getSearchData();
        if (searchData) {
            formData.append('search', searchData);
        }
        const filters = this.#getFilterData();
        if (!filters.page || parseInt(filters.page) < 1) {
            formData.append('filter[page]', page.toString());
        } else {
            page = parseInt(filters.page);
        }
        Object.keys(filters).forEach((filterKey) => {
            if (filterKey === DataTable.RANGE_FILTERS_KEY) {
                formData.append('filter[' + filterKey + ']', JSON.stringify(filters[filterKey]));
            } else {
                formData.append('filter[' + filterKey + ']', filters[filterKey]);
            }
        });
        const perPage = this.#perPageSelect.value;
        formData.append('limit', perPage);
        formData.append('offset', ((page - 1) * perPage).toString());

        return formData;
    }

    #getSearchData() {
        if (this.#tableSearchInput.value === '') {
            return null;
        }
        switch (this.#tableSearchType.value) {
            case 'startsWith':
                return this.#tableSearchInput.value + '%';
            case 'endsWith':
                return '%' + this.#tableSearchInput.value;
            case 'contains':
            default:
                return '%' + this.#tableSearchInput.value + '%';
        }
    }

    #getFilterData() {
        let filters = {};
        let params = new URLSearchParams(window.location.search);
        params.forEach((value, key) => {
            if (this.#baseFilterNames.includes(key)) {
                filters[key] = value;
            }
        });
        return Object.assign({}, filters, this.#customFilterData);
    }


    #addListeners() {
        if (this.getConfigValue('shiftCheckboxModifier')) {
            this.#addShiftKeyListener();
        }
        this.#addPerPageChangeListener();
        this.#addSearchListener();
        this.#addSearchTypeListener();
        this.#addSortListener();
        this.#addFilterListener();
        this.#addPopStateListener();
        this.#addGoToPageListeners();
        // this.#addResizeListener();
        this.#addSearchableColumnsTriggerListener();
        this.#addRangeFilterListenerForWindow();
    }

    #addShiftKeyListener() {
        window.addEventListener('keydown', this.#shiftKeyDownCallback);
        window.addEventListener('keyup', this.#shiftKeyUpCallback);
    }

    #addRangeFilters() {
        const rangeFilterButtons = this.#table.querySelectorAll(`.${rangeFilterSelectors.classes.rangeFilterButton}`);
        rangeFilterButtons.forEach((filter) => {
            const rangeFilter = new RangeFilter(filter, this.eventEmitter);
            rangeFilter.init();
            this.#rangeFilters.push(rangeFilter);
        });
    }

    #addPerPageChangeListener() {
        this.#perPageSelect.addEventListener('change', this.#perPageChangeCallback);
    }

    #perPageChangeCallback = () => {
        LocalStorage.set(`${this.#baseAction}-${DataTable.STORAGE_KEYS.PER_PAGE}`, parseInt(this.#perPageSelect.value));
        this.eventEmitter.emit(events.requestReloadTable);
    }

    #addSearchListener() {
        this.#tableSearchInput.addEventListener('input', this.#searchCallback);
    }

    #searchCallback = () => {
        clearTimeout(this.#searchTimeout);
        if (this.#tableSearchInput.value.length === 0) {
            this.eventEmitter.emit(events.requestReloadTable);
            return;
        }
        if (this.#tableSearchInput.value.length >= this.getConfigValue('minimumCharactersForSearch')) {
            this.#searchTimeout = setTimeout(() => {
                this.eventEmitter.emit(events.requestReloadTable);
            }, this.getConfigValue('delayOnInputSearchInMs'));
        }
    }

    #addSearchTypeListener() {
        this.#tableSearchType.addEventListener('change', this.#searchTypeCallback);
    }

    #searchTypeCallback = () => {
        if (this.#tableSearchInput.value.length > 0) {
            this.eventEmitter.emit(events.requestReloadTable);
        }
    }

    #addSortListener() {
        this.#sortButtons.forEach((button) => {
            button.addEventListener('click', this.#sortCallback);
        });
    }

    #sortCallback = (e) => {
        const sortButton = e.target;
        this.#applySortForButton(sortButton);
    }

    applySort(sort) {
        if(!sort || !sort.orderBy || !sort.order) {
            return;
        }
        const button = this.#getSortButtonByColumnName(sort.orderBy);
        if(button) {
            this.#applySortForButton(button);
        }
    }

    #getSortButtonByColumnName(columnName) {
        const header = this.#table.querySelector(`.${dataTableSelectors.classes.dataColumn}[${dataTableSelectors.attributes.columnName}="${columnName}"]`);
        if(header) {
            return header.querySelector('.sort');
        }
    }

    #applySortForButton(sortButton) {
        this.#sortButtons.forEach((button) => {
            if (button !== sortButton) {
                button.classList.remove(dataTableSelectors.classes.active);
                button.removeAttribute(dataTableSelectors.attributes.sortDesc);
                button.removeAttribute(dataTableSelectors.attributes.sortAsc);
            } else {
                if (button.hasAttribute(dataTableSelectors.attributes.sortDesc)) {
                    button.removeAttribute(dataTableSelectors.attributes.sortDesc);
                    button.setAttribute(dataTableSelectors.attributes.sortAsc, '');
                } else {
                    button.removeAttribute(dataTableSelectors.attributes.sortAsc);
                    button.setAttribute(dataTableSelectors.attributes.sortDesc, '');
                }
                button.classList.add(dataTableSelectors.classes.active);
                this.#sort = {
                    order: button.hasAttribute(dataTableSelectors.attributes.sortAsc) ? 'ASC' : 'DESC',
                    orderBy: button.parentElement.getAttribute(dataTableSelectors.attributes.columnName)

                }
            }
        });
        this.eventEmitter.emit(events.requestPopulateTable);
    }

    setFilter(filter) {
        if (filter && filter.name && filter.value) {
            let select = Array.from(this.#filterSelects).find((select) => select.name === filter.name);
            if (select) {
                select.value = filter.value;
                this.#applyFilterForSelect(select);
            }
            select = null;
        }
    }

    #addFilterListener() {
        if (this.#filterSelects.length) {
            this.#filterSelects.forEach((select) => {
                this.#baseFilterNames.push(select.name);
                select.addEventListener('change', this.#filterCallback);
            });
        }
    }

    #filterCallback = (e) => {
        const select = e.target;
        this.#applyFilterForSelect(select);
    }

    #applyFilterForSelect(select) {
        let params = new URLSearchParams(window.location.search);
        if (select.value === '-1') {
            params.delete(select.name);
        } else {
            params.set(select.name, select.value);
        }
        if (params.get('page')) {
            params.delete('page');
        }
        let newUrl = window.location.origin
            + window.location.pathname
            + '?' + params.toString();
        window.history.pushState({path: newUrl}, '', newUrl);
        this.eventEmitter.emit(events.requestReloadTable);
    }

    #getFilterSelectByName(name) {
        return Array.from(this.#filterSelects).find((select) => select.name === name);
    }

    #addPopStateListener() {
        window.addEventListener('popstate', this.#popStateCallback);
    }

    #popStateCallback = () => {
        this.#setFiltersFromParams();
        this.eventEmitter.emit(events.requestReloadTable, {keepPaginationPage: true});
    }

    #addGoToPageListeners() {
        this.#goToPageInputs.forEach((input) => {
            input.addEventListener('input', this.#goToPageCallback);
        });
    }

    #addSearchableColumnsTriggerListener() {
        if(this.#searchableColumnsTrigger) {
            this.#searchableColumnsTrigger.addEventListener('mouseenter', this.#searchableColumnsTriggerCallbackMouseEnter);
            this.#searchableColumnsTrigger.addEventListener('mouseleave', this.#searchableColumnsTriggerCallbackMouseLeave);
        }
    }

    #searchableColumnsTriggerCallbackMouseEnter = () => {
        this.#searchableColumnsList.classList.add(dataTableSelectors.classes.active);
    }

    #searchableColumnsTriggerCallbackMouseLeave = () => {
        this.#searchableColumnsList.classList.remove(dataTableSelectors.classes.active);
    }



    #addResizeListener() {
        window.addEventListener('resize', this.#resizeCallback);
    }

    #resizeCallback = () => {
        if (window.innerWidth <= this.getConfigValue('mobileBreakpoint') && this.#currentMode !== DataTable.MODES.MOBILE) {
            this.#currentMode = DataTable.MODES.MOBILE;
            this.eventEmitter.emit(events.requestReloadTable, {keepPaginationPage: true});
        }
        if (window.innerWidth > this.getConfigValue('mobileBreakpoint') && this.#currentMode !== DataTable.MODES.DESKTOP) {
            this.#currentMode = DataTable.MODES.DESKTOP;
            this.eventEmitter.emit(events.requestReloadTable, {keepPaginationPage: true});
        }
    }

    #goToPageCallback = (e) => {
        const input = e.target;
        clearTimeout(this.#goToPageTimeout);
        this.#goToPageTimeout = setTimeout(() => {
            const page = input.value;
            this.#goToPageInputs.forEach((input) => {
                input.value = page;
            });
            if (input.value === '') {
                return;
            }
            let setPage = this.#pagination.goToPage(e.target.value);
            this.#goToPageInputs.forEach((input) => {
                input.value = setPage;
            });
        }, 300);
    }

    #addRangeFilterListenerForWindow() {
        window.addEventListener('mousedown', this.#rangeFilterWindowCallback);
    }

    #rangeFilterWindowCallback = (e) => {
        this.#rangeFilters.forEach((filter) => {
            if (filter.isOpen() && e.target !== filter.getButton() && !filter.getButton().contains(e.target)) {
                filter.close();
            }
        });
    }


    #shiftKeyDownCallback = (e) => {
        if (e.key === 'Shift') {
            this.#isShiftActive = true;
        }
    }

    #shiftKeyUpCallback = (e) => {
        if (e.key === 'Shift') {
            this.#isShiftActive = false;
        }
    }

    #initBulkActions() {
        if (this.getConfigValue('enableCheckboxes')) {
            if (this.#bulkActions.size) {
                this.#bulkActions.forEach((action) => {
                    this.#generateBulkActionOption(action);
                });
                this.#addApplyBulkActionListener();
            }
        } else {
            this.#bulkActionsContainer.remove();
        }
    }

    #initCheckboxes() {
        if (this.getConfigValue('enableCheckboxes')) {
            this.#addSelectAllCheckboxesListener();
        } else {
            this.#selectAllCheckboxes.parentElement.remove();
            this.#selectAllCheckboxesBottom.parentElement.remove();
        }
    }

    #setPerPageFromStorage() {
        const perPage = LocalStorage.get(`${this.#baseAction}-${DataTable.STORAGE_KEYS.PER_PAGE}`);
        if (perPage) {
            this.#perPageSelect.value = perPage;
        }
    }

    #setFiltersFromParams() {
        const params = new URLSearchParams(window.location.search);
        this.#filterSelects.forEach((select) => {
            let match = false;
            params.forEach((value, key) => {
                if (key === select.name) {
                    select.value = value;
                    match = true;
                }
            });
            if (!match) {
                select.value = '-1';
            }
        });
    }

    #setDefaultSort() {
        const defaultSort = this.getConfigValue('defaultSort');
        for (const sort of defaultSort) {
            if (this.#setSortFromObject(sort)) {
                return;
            }
        }
    }

    #setSortFromObject(sort) {
        if (sort) {
            const sortButton = this.#table.querySelector(`.${dataTableSelectors.classes.dataColumn}[${dataTableSelectors.attributes.columnName}="${sort.orderBy}"]`);
            if (sortButton) {
                sortButton.classList.add(dataTableSelectors.classes.active);
                switch (sort.order) {
                    case 'ASC':
                        sortButton.setAttribute(dataTableSelectors.attributes.sortAsc, '');
                        break;
                    case 'DESC':
                        sortButton.setAttribute(dataTableSelectors.attributes.sortDesc, '');
                        break;
                }
                this.#sort = sort;
                return true;
            }
        }
        return false;
    }

    #initUserViewOptions() {
        if (this.getConfigValue('userViewOptions')) {
            this.#userViewOptions = new UserViewOptions({
                baseAction: this.#baseAction,
                columnData: this.#getColumnDataForUserViewOptions(),
                tableElement: this.#table,
            });
            this.#userViewOptions.init();
        }
    }

    #addSelectAllCheckboxesListener() {
        this.#selectAllCheckboxes.addEventListener('change', this.#selectAllCheckboxesCallback);
        this.#selectAllCheckboxesBottom.addEventListener('change', this.#selectAllCheckboxesCallback);
    }

    #selectAllCheckboxesCallback = (e) => {
        const checkboxes = this.#tableBody.querySelectorAll(`.${dataTableSelectors.classes.entityCheckbox}`);
        const checked = e.target.checked;
        this.#selectAllCheckboxes.checked = checked;
        this.#selectAllCheckboxesBottom.checked = checked;
        checkboxes.forEach((checkbox) => {
            checkbox.checked = checked;
            let tr = checkbox.parentElement.parentElement;
            if (checked) {
                this.#selectedIds.add(checkbox.getAttribute(dataTableSelectors.attributes.checkboxEntityId.toString()));
                tr.classList.add(dataTableSelectors.classes.active);
            } else {
                tr.classList.remove(dataTableSelectors.classes.active);
            }
            tr = null;
        });
        if (!checked) {
            this.#selectedIds.clear();
        }
    }

    #generateBulkActionOption(action) {
        const option = document.createElement('option');
        option.value = action.getName();
        option.textContent = action.getContent();
        this.#bulkActionSelect.appendChild(option);
    }

    #addApplyBulkActionListener() {
        this.#bulkActionApplyButton.addEventListener('click', this.#applyBulkActionCallback);
    }

    #applyBulkActionCallback = async () => {
        let action = this.#bulkActions.get(this.#bulkActionSelect.value);
        if (action) {
            if(action.getPromptMessage()) {
                if(!confirm(action.getPromptMessage())) {
                    return;
                }
            }
            let loader = new Loader({size: '24px', thickness: '3px'});
            if (action.getUseLoader()) {
                loader.start(this.#bulkActionsContainer, ['button'])
            }
            const callback = action.getCallback();
            if (callback.constructor.name !== 'AsyncFunction') {
                callback([...this.#selectedIds]);
            } else {
                await callback([...this.#selectedIds]);
            }
            if (action.getUseLoader() && loader) {
                loader.stop(this.#bulkActionsContainer, ['button']);
            }
            loader.destroy();
            loader = null;
        }
        action = null;
    }

    #parseConfig() {
        Object.keys(dataTableConfig).forEach((key) => {
            if (this.#config && typeof this.#config !== 'undefined' && typeof this.#config[key] !== 'undefined') {
                this.#config[key] = this.#config[key];
            } else {
                this.#config[key] = dataTableConfig[key];
            }
        });
    }

    #getColumnDataForUserViewOptions() {
        const data = [];
        this.#tableHeaders.forEach((column) => {
            const columnName = column.getAttribute(dataTableSelectors.attributes.columnName);
            const columnIndex = column.getAttribute(dataTableSelectors.attributes.columnIndex);
            if (columnName && columnIndex) {
                data.push({
                    name: columnName,
                    index: columnIndex
                });
            }
        });
        return data;
    }

    #setResultCount(data) {
        if (data.filteredCount && data.totalCount) {
            this.#resultCountElement.innerText = `${Translator.translate('Filtered')} ${data.filteredCount} of ${data.totalCount}`;
            return;
        }
        if (data.totalCount) {
            this.#resultCountElement.innerText = `Total results: ${data.totalCount}`;
        }
    }

    #printEntities(entitiesList) {
        entitiesList.forEach((entity) => {
            const row = new Row({
                entity: entity,
                tableHeaders: this.#tableHeaders,
                table: this.#table,
                actions: this.#actions,
                actionFilter: this.#actionFilter,
                mode: this.#currentMode,
                useCheckbox: this.getConfigValue('enableCheckboxes'),
                tdStyler: this.#tdStyler,
                trStyler: this.#trStyler,
                eventEmitter: this.eventEmitter,
                showAdditionalContentOnLoad: this.getConfigValue('showAdditionalContentOnLoad'),
            });
            this.#rows.push(row);
            this.#tableBody.appendChild(row.getView());
            this.#afterRowRender(row.getView(), entity);
            if (row.getAdditionalDataRow()) {
                this.#tableBody.appendChild(row.getAdditionalDataRow());
            }
        });
    }

    #printCountColumns(countColumnData) {
        if (countColumnData) {
            this.#printTotalCountColumn(countColumnData.total);
            this.#printPageCountColumn(countColumnData.page);
        }
    }

    #printTotalCountColumn(countColumnData) {
        const row = document.createElement('tr');
        row.classList.add(dataTableSelectors.classes.pageCountRow);
        const label = document.createElement('td');
        label.textContent = Translator.translate('Total');
        label.classList.add(dataTableSelectors.classes.countDataTitle);
        row.appendChild(label);
        this.#tableHeaders.forEach((header, index) => {
            // If checkboxes are enabled, the first column is the label for total count. First column can't have count data.
            if (index === 0 && !this.getConfigValue('enableCheckboxes')) {
                return;
            }
            const columnName = header.getAttribute(dataTableSelectors.attributes.columnName);
            let td = document.createElement('td');
            td.classList.add(dataTableSelectors.classes.countDataElement);
            if (countColumnData[columnName]) {
                td.textContent = countColumnData[columnName];
                td = this.#countTdFilter(td, columnName, countColumnData[columnName], 'total');
            }
            row.appendChild(td);
        });
        this.#tableBody.appendChild(row);
    }

    #printPageCountColumn(countColumnData) {
        const row = document.createElement('tr');
        row.classList.add(dataTableSelectors.classes.pageCountRow);
        const label = document.createElement('td');
        label.textContent = Translator.translate('Page');
        label.classList.add(dataTableSelectors.classes.countDataTitle);
        row.appendChild(label);
        this.#tableHeaders.forEach((header, index) => {
            // If checkboxes are enabled, the first column is the label for total count. First column can't have count data.
            if (index === 0 && !this.getConfigValue('enableCheckboxes')) {
                return;
            }
            const columnName = header.getAttribute(dataTableSelectors.attributes.columnName);
            let td = document.createElement('td');
            td.classList.add(dataTableSelectors.classes.countDataElement);
            if (countColumnData[columnName]) {
                td.textContent = countColumnData[columnName];
                td = this.#countTdFilter(td, columnName, countColumnData[columnName], 'page');
            }
            row.appendChild(td);
        });
        this.#tableBody.appendChild(row);
    }

    #removeCountColumns() {
        const countRows = this.#tableBody.querySelectorAll(`.${dataTableSelectors.classes.pageCountRow}`);
        if (countRows.length === 0) {
            return;
        }
        countRows.forEach((row) => {
            row.remove();
        });
    }

    #listenToEvents() {
        if (this.getConfigValue('enableCheckboxes')) {
            this.#listenToCheckboxChange();
        }
        this.#listenToRangeFilterApply();
        this.#listenToRangeFilterIsEmpty();
        this.#listenToTableEvents();
        this.#listenToExport();
    }

    #listenToCheckboxChange() {
        this.eventEmitter.on(events.checkboxChange, (data) => {
            if (data.entityId) {
                let tr = this.#tableBody.querySelector(`[${dataTableSelectors.attributes.entityId}="${data.entityId}"]`);
                data.checked ? this.#selectedIds.add(data.entityId.toString()) : this.#selectedIds.delete(data.entityId.toString());
                data.checked ? tr.classList.add(dataTableSelectors.classes.active) :
                    tr.classList.remove(dataTableSelectors.classes.active);
                tr = null;
            }
            if (this.#isShiftActive &&
                data.checked &&
                data.checkbox &&
                this.#lastCheckedCheckbox &&
                this.#lastCheckedCheckbox !== data.checkbox) {
                if (this.#isNodeBefore(data.checkbox, this.#lastCheckedCheckbox)) {
                    this.#handleShiftSelectCheckboxes(DataTable.CHECKBOX_SHIFT_DIRECTIONS.DOWN, data.checkbox);
                } else {
                    this.#handleShiftSelectCheckboxes(DataTable.CHECKBOX_SHIFT_DIRECTIONS.UP, data.checkbox);
                }
            }
            if (data.checked) {
                this.#lastCheckedCheckbox = data.checkbox;
            } else {
                this.#lastCheckedCheckbox = null;
            }
        });
    }

    #isNodeBefore(node1, node2) {
        return (node1.compareDocumentPosition(node2) & (Node.DOCUMENT_POSITION_PRECEDING | Node.DOCUMENT_POSITION_CONTAINS)) !== 0;
    }

    #handleShiftSelectCheckboxes(direction, currentCheckbox) {
        let nextTarget = null;
        let endTarget = null;
        switch (direction) {
            case DataTable.CHECKBOX_SHIFT_DIRECTIONS.UP:
                nextTarget = this.#getNextCheckbox(currentCheckbox);
                endTarget = this.#lastCheckedCheckbox;
                break;
            case DataTable.CHECKBOX_SHIFT_DIRECTIONS.DOWN:
                nextTarget = this.#getNextCheckbox(this.#lastCheckedCheckbox);
                endTarget = currentCheckbox;
                break;
        }
        if (nextTarget && endTarget) {
            while (nextTarget && nextTarget !== endTarget) {
                nextTarget.checked = true;
                const entityId = nextTarget.getAttribute(dataTableSelectors.attributes.entityId);
                const tr = this.#tableBody.querySelector(`[${dataTableSelectors.attributes.entityId}="${entityId}"]`);
                tr.classList.add(dataTableSelectors.classes.active);
                this.#selectedIds.add(nextTarget.getAttribute(dataTableSelectors.attributes.entityId));
                nextTarget = this.#getNextCheckbox(nextTarget);
            }
        }
    }

    #getNextCheckbox(target) {
        let nextRow = target.parentElement.parentElement.nextElementSibling;
        if (nextRow && nextRow.classList.contains(dataTableSelectors.classes.additionalDataRow)) {
            nextRow = nextRow.nextElementSibling;
        }
        return nextRow.querySelector(`.${dataTableSelectors.classes.entityCheckbox}`);
    }

    #listenToRangeFilterApply() {
        this.eventEmitter.on(rangeFilterEvents.rangeFilterApply, (data) => {
            this.setCustomRangeFilterData(data.columnName, JSON.stringify({
                from: data.from,
                to: data.to
            }));
            this.eventEmitter.emit(events.requestReloadTable);
        });
    }

    #listenToRangeFilterIsEmpty() {
        this.eventEmitter.on(rangeFilterEvents.rangeFilterIsEmpty, (data) => {
            this.deleteCustomRangeFilter(data.columnName);
            this.eventEmitter.emit(events.requestReloadTable);
        });
    }

    setCustomRangeFilterData(key, value) {
        if (!this.#customFilterData[DataTable.RANGE_FILTERS_KEY]) {
            this.#customFilterData[DataTable.RANGE_FILTERS_KEY] = {};
        }
        this.#customFilterData[DataTable.RANGE_FILTERS_KEY][key] = value;
    }

    deleteCustomRangeFilter(key) {
        if (this.#customFilterData[DataTable.RANGE_FILTERS_KEY] && this.#customFilterData[DataTable.RANGE_FILTERS_KEY][key]) {
            delete this.#customFilterData[DataTable.RANGE_FILTERS_KEY][key];
        }
    }

    #listenToTableEvents() {
        this.#listenToPopulateRequest();
        this.#listenToReloadRequest();
    }

    #listenToExport() {
        this.eventEmitter.on(events.exportData, async (data) => {
            const form = this.#export.getForm();
            form.method = 'POST';
            form.action = this.#exportEndpoint;
            if(form) {
                let requestData = this.#getRequestData();
                requestData.append('columns', data.columns);
                requestData.append('exportType', data.exportType);
                for (const [key, value] of requestData.entries()) {
                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.classList.add('exportData');
                    input.name = key;
                    input.value = value.toString();
                    form.appendChild(input);
                }
                form.submit();
                const inputs = document.querySelectorAll('.exportData');
                if(inputs) {
                    inputs.forEach((input) => {
                        input.remove();
                    });
                }
            }
            this.#export.closeModal();
        });
    }

    #listenToPopulateRequest() {
        this.eventEmitter.on(events.requestPopulateTable, async (data) => {
            let page = this.#pagination.getActivePageFromURL();
            if (data && data.page) {
                page = data.page;
            }
            await this.populateTable(page);
        });
    }

    #listenToReloadRequest() {
        this.eventEmitter.on(events.requestReloadTable, async (data) => {
            await this.reloadTable(data);
        });
    }

    printNoResultsMessage(message) {
        this.#tableWrapper.classList.add(dataTableSelectors.classes.noResults);
        this.#noResultsMessageElement.textContent = message;
    }

    removeNoResultsMessageContent() {
        this.#tableWrapper.classList.remove(dataTableSelectors.classes.noResults);
        this.#noResultsMessageElement.textContent = '';
    }

    makeTableBodyTransparent() {
        this.#tableBody.style.opacity = '0.5';
        this.#tableBody.style.pointerEvents = 'none';
    }

    normalizeTableBody() {
        this.#tableBody.style.removeProperty('opacity');
        this.#tableBody.style.removeProperty('pointer-events');
    }

    getConfigValue(key) {
        return this.#config[key];
    }

    startProgressInOverlay() {
        this.#tableOverlayProgress.start(this.#tableOverlay);
    }

    stopProgressInOverlay() {
        this.#tableOverlayProgress.stop(this.#tableOverlay);
    }

    hideTableOverlay() {
        this.#tableOverlay.classList.add(dataTableSelectors.classes.hide);
    }

    showTableOverlay() {
        this.#tableOverlay.classList.remove(dataTableSelectors.classes.hide);
    }

    setCustomFilterData(data) {
        Object.entries(data).forEach(([key, value]) => {
            this.#customFilterData[key] = value;
        });
    }

    setCustomFilterDataRaw(key, data) {
        this.#customFilterData[key] = data;
    }

    deleteCustomFilterData(key) {
        delete this.#customFilterData[key];
    }

    getCustomFilterData() {
        return this.#customFilterData;
    }

    getCustomFilterDataValue(key) {
        return this.#customFilterData[key];
    }

    getTable() {
        return this.#table;
    }

    destroy() {
        this.#rows.forEach((row) => {
            row.destroy();
        });
        this.#rows = null;
        this.#actions.forEach((action) => {
            action.destroy();
        });
        this.#actions = null;
        this.#bulkActions.forEach((action) => {
            action.destroy();
        });
        this.#bulkActions = null;
        this.#sortButtons.forEach((button) => {
            button.removeEventListener('click', this.#sortCallback);
        });
        this.#filterSelects.forEach((select) => {
            select.removeEventListener('change', this.#filterCallback);
        });
        window.removeEventListener('mousedown', this.#rangeFilterWindowCallback);
        this.#rangeFilterWindowCallback = null;
        this.#rangeFilters.forEach((filter) => {
            filter.destroy();
        });
        this.#rangeFilters = null;
        this.#sort = null;
        this.#filterSelects = null;
        this.#sortButtons = null;
        this.#bulkActionApplyButton.removeEventListener('click', this.#applyBulkActionCallback);
        this.#applyBulkActionCallback = null;
        this.#bulkActionApplyButton = null;
        this.#config = null;
        this.#tableContainer = null;
        this.#table = null;
        this.#tableBody = null;
        this.#tableOverlay = null;
        this.#tableTop = null;
        this.#tableBottom = null;
        this.#perPageSelect.removeEventListener('change', this.#perPageChangeCallback);
        this.#perPageSelect = null;
        this.#bulkActionsContainer = null;
        this.#bulkActionSelect = null;
        this.#tableSearchInput.removeEventListener('input', this.#searchCallback);
        clearTimeout(this.#searchTimeout);
        this.#searchTimeout = null;
        this.#tableSearchInput = null;
        this.#tableSearchType.removeEventListener('change', this.#searchTypeCallback);
        this.#tableSearchType = null;
        this.#resultCountElement = null;
        this.#noResultsMessageElement = null;
        this.#tableOverlayProgress.destroy();
        this.#tableOverlayProgress = null;
        window.removeEventListener('keydown', this.#shiftKeyDownCallback);
        window.removeEventListener('keyup', this.#shiftKeyUpCallback);
        this.#isShiftActive = null;
        this.#selectedIds = null;
        this.#selectAllCheckboxes.removeEventListener('change', this.#selectAllCheckboxesCallback);
        this.#selectAllCheckboxesBottom.removeEventListener('change', this.#selectAllCheckboxesCallback);
        this.#selectAllCheckboxes = null;
        this.#selectAllCheckboxesBottom = null;
        window.removeEventListener('popstate', this.#popStateCallback);
        this.#popStateCallback = null;
        this.#goToPageInputs.forEach((input) => {
            input.removeEventListener('input', this.#goToPageCallback);
        });
        this.#goToPageInputs = null;
        this.#baseFilterNames = null;
        this.#userViewOptions.destroy();
        this.#userViewOptions = null;
        this.#customFilterData = null;
        this.#baseAction = null;
        this.#perPageChangeCallback = null;
        this.#searchCallback = null;
        this.#searchTypeCallback = null;
        this.#sortCallback = null;
        this.#filterCallback = null;
        this.#popStateCallback = null;
        this.#goToPageCallback = null;
        this.#shiftKeyDownCallback = null;
        this.#shiftKeyUpCallback = null;
        this.#applyBulkActionCallback = null;
        this.#selectAllCheckboxesCallback = null;
        this.#tableLoader.destroy();
        this.#tableLoader = null;
        // window.removeEventListener('resize', this.#resizeCallback);
        this.#resizeCallback = null;
        this.#maxPage = null;
        this.#currentMode = null;
        this.#tableHeaders = null;
        this.#tdStyler = null;
        this.#trStyler = null;
        this.#actionFilter = null;
        this.eventEmitter.destroy();
        this.eventEmitter = null;
        this.#afterRowRender = null;
        this.#pagination.destroy();
        this.#pagination = null;
        this.#countTdFilter = null;
        this.#lastCheckedCheckbox = null;
        this.#rangeFilterWindowCallback = null;
        if(this.#export) {
            this.#export.destroy();
        }
        this.#export = null;
        if(this.#searchableColumnsTrigger) {
            this.#searchableColumnsTrigger.removeEventListener('mouseenter', this.#searchableColumnsTriggerCallbackMouseEnter);
            this.#searchableColumnsTrigger.removeEventListener('mouseleave', this.#searchableColumnsTriggerCallbackMouseLeave);
        }
        this.#searchableColumnsList = null;
        this.#searchableColumnsTrigger = null;
        this.#searchableColumnsTriggerCallbackMouseEnter = null;
        this.#searchableColumnsTriggerCallbackMouseLeave = null;
    }
}