import {dataTableSelectors} from '../dataTableSelectors.js';
import {userViewOptionsSelectors} from "./userViewOptionsSelectors.js";
import ColumnOption from "./ColumnOption.js";
import TableOptions from "./TableOptions.js";

export default class UserViewOptions {

    #baseAction;
    #toggleButton;
    #container;
    #anchors;
    #columnData;
    #columnOptionsContainer;
    #tableOptionsContainer;
    #columnOptions = [];
    #tableOptions = null;
    #tableElement;
    constructor({baseAction, columnData, tableElement}) {
        try {
            this.#baseAction = baseAction;
            this.#columnData = columnData;
            this.#tableElement = tableElement;
            this.#setProperties();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#toggleButton = document.getElementById(userViewOptionsSelectors.ids.userViewOptionsToggle);
        this.#container = document.getElementById(userViewOptionsSelectors.ids.userViewOptionsContainer);
        this.#anchors = document.querySelectorAll(`.${userViewOptionsSelectors.classes.optionsAnchor}`);
        this.#columnOptionsContainer = document.getElementById(userViewOptionsSelectors.ids.columnOptionsContainer);
        this.#tableOptionsContainer = document.getElementById(userViewOptionsSelectors.ids.tableOptionsContainer);
        if(!this.#toggleButton) {
            throw new Error(`${userViewOptionsSelectors.ids.userViewOptionsToggle} not found`);
        }
        if(!this.#container) {
            throw new Error(`${userViewOptionsSelectors.ids.userViewOptionsContainer} not found`);
        }
        if(!this.#anchors || this.#anchors.length === 0) {
            throw new Error(`${userViewOptionsSelectors.classes.optionsAnchor} not found`);
        }
        if(!this.#columnOptionsContainer) {
            throw new Error(`${userViewOptionsSelectors.ids.columnOptionsContainer} not found`);
        }
        if(!this.#tableOptionsContainer) {
            throw new Error(`${userViewOptionsSelectors.ids.tableOptionsContainer} not found`);
        }
        this.#toggleButton.classList.remove(dataTableSelectors.classes.hide);
    }

    init() {
        this.#setColumnOptions();
        this.#setTableOptions();
        this.#addListeners();
    }

    #setColumnOptions() {
        this.#columnData.forEach((column) => {
            const columnOption = new ColumnOption({
                tableElement: this.#tableElement,
                columnData: column,
                baseAction: this.#baseAction
            });
            this.#columnOptions.push(columnOption);
            this.#columnOptionsContainer.querySelector(`.${userViewOptionsSelectors.classes.userViewOptions}`)
                .appendChild(columnOption.getView());
        });
    }

    #setTableOptions() {
        this.#tableOptions = new TableOptions({
            tableElement: this.#tableElement,
            baseAction: this.#baseAction
        });
        this.#tableOptionsContainer.querySelector(`.${userViewOptionsSelectors.classes.userViewOptions}`)
            .append(...this.#tableOptions.getOptionContainers());
    }

    #addListeners() {
        this.#addToggleListener();
        this.#addWindowListener();
        this.#addAnchorListeners();
    }

    #addToggleListener() {
        this.#toggleButton.addEventListener('click', this.#toggle);
    }

    #addWindowListener() {
        window.addEventListener('mousedown', this.#windowMousedownCallback);
    }

    #windowMousedownCallback = (e) => {
        if(!this.#container.contains(e.target) && !this.#toggleButton.contains(e.target)) {
            this.#container.classList.add(dataTableSelectors.classes.hide);
        }
    }

    #addAnchorListeners() {
        this.#anchors.forEach((anchor) => {
            anchor.addEventListener('click', this.#anchorClickCallback);
        });
    }

    #toggle = () => {
        this.#container.classList.toggle(dataTableSelectors.classes.hide);
    }

    #anchorClickCallback = (e) => {
        const anchor = e.target;
        const optionsContainer = anchor.nextElementSibling;
        const icon = anchor.querySelector('svg');
        if(optionsContainer && icon) {
            anchor.classList.toggle(userViewOptionsSelectors.classes.active);
            optionsContainer.classList.toggle(dataTableSelectors.classes.hide);
        }
    }

    hide() {
        this.#container.classList.add(dataTableSelectors.classes.hide);
    }

    show() {
        this.#container.classList.remove(dataTableSelectors.classes.hide);
    }

    destroy() {
        this.#toggleButton.removeEventListener('click', this.#toggle);
        window.removeEventListener('mousedown', this.#windowMousedownCallback);
        this.#toggleButton = null;
        this.#container = null;
        this.#anchors.forEach((anchor) => {
            anchor.removeEventListener('click', this.#anchorClickCallback);
        });
        this.#anchors = null;
        this.#columnData = null;
        this.#columnOptions.forEach((columnOption) => {
            columnOption.destroy();
        });
        this.#columnOptions = null;
        this.#columnOptionsContainer = null;
        this.#tableOptionsContainer = null;
        this.#tableOptions.destroy();
        this.#tableOptions = null;
        this.#tableElement = null;
        this.#baseAction = null;
    }
}