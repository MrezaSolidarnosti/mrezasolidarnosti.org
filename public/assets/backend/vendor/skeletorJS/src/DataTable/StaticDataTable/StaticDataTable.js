import EventEmitter from "../../EventEmitter/EventEmitter.js";
import {template} from "./template.js";
import {dataTableSelectors} from "../dataTableSelectors.js";
import Pagination from "../Pagination/Pagination.js";
import {events} from "../events.js";
import SelectSearch from "../../SelectSearch/SelectSearch.js";

export default class StaticDataTable {


    #fragment = document.createDocumentFragment();
    #container;
    #tableContainer;
    #tableWrapper;
    #table;
    #tableBody;
    #tableHead;
    #perPageSelect;
    #headers = [];
    #data = [];
    #target;
    eventEmitter = new EventEmitter();
    #pagination;
    #maxResults;
    #searchInput;
    #maxPage;
    #perPage = 10;
    #offset = 0;
    #activePage = 1;
    #sort = {orderBy: null, direction: 'DESC'};
    #sortButtons = [];
    #filtersContainer;
    #filterSelects = [];
    #filterSelectElements = [];
    #activeFilters = [];


    constructor({headers, data, target}) {
        this.#headers = headers;
        this.#data = data;
        this.#target = target;
        this.#maxResults = data.length;
        this.#maxPage = Math.ceil(this.#maxResults / this.#perPage);
    }

    init() {
        this.#setElements().then(() => {
            this.#addListeners();
            this.#listenToEvents();
            this.#render();
            this.#createPagination();
        });

    }

    async #setElements() {
        const templateElement = document.createElement('template');
        templateElement.innerHTML = template.trim();
        this.#fragment.appendChild(templateElement.content);
        this.#container = this.#fragment.querySelector(`#${dataTableSelectors.ids.mainTableContainer}`);
        this.#tableContainer = this.#fragment.querySelector(`#${dataTableSelectors.ids.tableContainer}`);
        this.#tableWrapper = this.#fragment.querySelector(`#${dataTableSelectors.ids.tableWrapper}`);
        this.#table = this.#fragment.querySelector(`#${dataTableSelectors.ids.table}`);
        this.#tableBody = this.#fragment.querySelector(`#${dataTableSelectors.ids.tableBody}`);
        this.#tableHead = this.#fragment.querySelector('thead');
        this.#searchInput = this.#fragment.querySelector(`#${dataTableSelectors.ids.tableSearchInput}`);
        this.#perPageSelect = this.#fragment.querySelector(`#${dataTableSelectors.ids.perPageSelect}`);
        this.#filtersContainer = this.#fragment.querySelector(`#${dataTableSelectors.ids.tableFilters}`);
        this.#createTableFilters();
        this.#createTableHeader();
        this.#renderRows();
    }

    #createTableFilters() {
        this.#headers.forEach((header, index) => {
            if(header.filterable) {
                const selectSearch = SelectSearch.generateHTML(
                    header.label,
                    this.#getFilterOptionsForColumn(index),
                    header.label,
                    null,
                    {'-1': '---'}
                );
                const filter = new SelectSearch(selectSearch)
                this.#filterSelects.push(filter);
                const selectElement = selectSearch.querySelector('select');
                this.#filterSelectElements.push(selectElement);
                this.#filtersContainer.appendChild(selectSearch);
                filter.init();
            }
        });
    }

    #getFilterOptionsForColumn(index) {
        const data = {};
        this.#data.forEach((row) => {
            row.forEach((cell, cellIndex) => {
                if(cellIndex === index) {
                    if(!data.cell) {
                        data[cell] = cell;
                    }
                }
            });
        });
        return data;
    }

    #createTableHeader() {
        const headerRow = document.createElement('tr');
        this.#headers.forEach(header => {
            const th = document.createElement('th');
            th.classList.add(dataTableSelectors.classes.dataColumn);
            th.textContent = header.label;
            if(header.sortable) {
                const sortButton = this.#generateSortButton(header.label);
                this.#sortButtons.push(sortButton);
                th.appendChild(sortButton);
            }
            headerRow.appendChild(th);
        });
        this.#tableHead.appendChild(headerRow);
    }

    #generateSortButton(columnName) {
        const sortButton = document.createElement('div');
        sortButton.setAttribute(dataTableSelectors.attributes.columnName, columnName);
        sortButton.classList.add(dataTableSelectors.classes.sortButtons);
        sortButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M151.6 469.6C145.5 476.2 137 480 128 480s-17.5-3.8-23.6-10.4l-88-96c-11.9-13-11.1-33.3 2-45.2s33.3-11.1 45.2 2L96 365.7V64c0-17.7 14.3-32 32-32s32 14.3 32 32V365.7l32.4-35.4c11.9-13 32.2-13.9 45.2-2s13.9 32.2 2 45.2l-88 96zM320 480c-17.7 0-32-14.3-32-32s14.3-32 32-32h32c17.7 0 32 14.3 32 32s-14.3 32-32 32H320zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32h96c17.7 0 32 14.3 32 32s-14.3 32-32 32H320zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32H480c17.7 0 32 14.3 32 32s-14.3 32-32 32H320zm0-128c-17.7 0-32-14.3-32-32s14.3-32 32-32H544c17.7 0 32 14.3 32 32s-14.3 32-32 32H320z"></path></svg>`;
        sortButton.addEventListener('click', this.#sortButtonCallback);
        return sortButton;
    }

    #sortButtonCallback = (e) => {
        const columnName = e.target.getAttribute(dataTableSelectors.attributes.columnName);
        this.#sortButtons.forEach(button => {
           if(button !== e.target) {
               button.classList.remove(dataTableSelectors.classes.active);
               button.removeAttribute(dataTableSelectors.attributes.sortAsc);
               button.removeAttribute(dataTableSelectors.attributes.sortDesc);
               return;
           }
            if(button.hasAttribute(dataTableSelectors.attributes.sortDesc)) {
                button.removeAttribute(dataTableSelectors.attributes.sortDesc);
                button.setAttribute(dataTableSelectors.attributes.sortAsc, '');
            } else {
                button.removeAttribute(dataTableSelectors.attributes.sortAsc);
                button.setAttribute(dataTableSelectors.attributes.sortDesc, '');
            }
            button.classList.add(dataTableSelectors.classes.active);
            this.#sort.column = columnName;
            this.#sort.orderBy = button.hasAttribute(dataTableSelectors.attributes.sortAsc) ? 'ASC' : 'DESC';
        });
        this.#renderRows();
    }

    #createPagination() {
        this.#pagination = new Pagination(this.eventEmitter, false);
        this.#pagination.generatePagination(this.#maxPage);
    }

    #listenToEvents() {
        this.eventEmitter.on(events.requestPopulateTable, (targetPage) => {
            this.#activePage = targetPage;
            this.#renderRows();
        });
    }

    #renderRows() {
        this.#tableBody.innerHTML = '';
        const data = this.#getData();
        if(this.#pagination) {
            this.#pagination.generatePagination(this.#maxPage, this.#activePage)
        }
        data.forEach(row => {
            const tr = document.createElement('tr');
            row.forEach(cell => {
                const td = document.createElement('td');
                td.textContent = cell;
                tr.appendChild(td);
            });
            this.#tableBody.appendChild(tr);
        });
    }

    #getData() {
        this.#offset = (this.#activePage - 1) * this.#perPage;
        let data = this.#data;
        this.#maxPage = Math.ceil(this.#maxResults / this.#perPage);
        if(this.#searchInput.value.trim() !== '') {
            data = this.#getDataBySearch(data);
            this.#maxPage = Math.ceil(data.length / this.#perPage);
        } else {
            this.#maxPage = Math.ceil(this.#maxResults / this.#perPage);
        }
        if(this.#sort.orderBy) {
            data = this.#getDataBySort(data);
        }
        if(this.#activeFilters.length) {
            data = this.#getDataByFilters(data);
            this.#maxPage = Math.ceil(data.length / this.#perPage);
        }
        return data.slice(this.#offset, this.#perPage * this.#activePage);
    }

    #getDataByFilters(data) {
        return data.filter(row => {
            return this.#activeFilters.every(filter => {
                const index = this.#headers.findIndex(header => header.label === filter.column);
                return row[index] === filter.value;
            });
        });
    }

    #getDataBySort(data) {
        return data.sort((a, b) => {
            const index = this.#headers.findIndex(header => header.label === this.#sort.column);
            if(this.#sort.orderBy === 'ASC') {
                return a[index] > b[index] ? 1 : -1;
            } else {
                return a[index] < b[index] ? 1 : -1;
            }
        });
    }

    #getDataBySearch(data) {
        return data.filter(row => {
            return row.some(cell => {
                return cell.toString().toLowerCase().includes(this.#searchInput.value.trim().toLowerCase());
            });
        });
    }

    #addListeners() {
        this.#searchInput.addEventListener('input', this.#searchInputCallback);
        this.#perPageSelect.addEventListener('change', this.#perPageSelectCallback);
        this.#filterSelectElements.forEach((select) => {
            select.addEventListener('change', this.#filterSelectCallback);
        });
    }

    #searchInputCallback = () => {
        this.#activePage = 1;
        this.#offset = 0;
        this.#renderRows();
    }

    #perPageSelectCallback = () => {
        this.#perPage = parseInt(this.#perPageSelect.value);
        this.#activePage = 1;
        this.#offset = 0;
        this.#maxPage = Math.ceil(this.#maxResults / this.#perPage);
        this.#renderRows();
    }

    #filterSelectCallback = () => {
        this.#activeFilters = [];
        this.#filterSelectElements.forEach((select) => {
            if(select.value !== '-1') {
                this.#activeFilters.push({column: select.name, value: select.value});
            }
        });
        this.#renderRows();
    }

    #render() {
        this.#target.appendChild(this.#container);
    }

    destroy() {
        this.eventEmitter.destroy();
        this.#pagination.destroy();
        this.eventEmitter = null;
        this.#headers = null;
        this.#data = null;
        this.#target = null;
        this.#container = null;
        this.#tableContainer = null;
        this.#tableWrapper = null;
        this.#table = null;
        this.#tableBody = null;
        this.#tableHead = null;
        this.#pagination = null;
        this.#maxPage = null;
        this.#maxResults = null;
        this.#searchInput.removeEventListener('input', this.#searchInputCallback);
        this.#searchInput = null;
        this.#searchInputCallback = null;
        this.#offset = null;
        this.#perPage = null;
        this.#activePage = null;
        this.#perPageSelect.removeEventListener('change', this.#perPageSelectCallback);
        this.#perPageSelect = null;
        this.#perPageSelectCallback = null;
        this.#sort = null;
        this.#sortButtons.forEach(button => button.removeEventListener('click', this.#sortButtonCallback));
        this.#sortButtons = null;
        this.#sortButtonCallback = null;
        this.#filtersContainer = null;
        this.#filterSelects.forEach(filter => filter.destroy());
        this.#filterSelects = null;
        this.#filterSelectElements.forEach(select => select.removeEventListener('change', this.#filterSelectCallback));
        this.#filterSelectElements = null;
        this.#filterSelectCallback = null;
    }
}