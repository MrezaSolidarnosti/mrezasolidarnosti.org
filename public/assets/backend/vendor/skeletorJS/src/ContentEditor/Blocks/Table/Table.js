import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";
import {parseCsv} from "./parseCsv.js";
import Translator from "../../../Translator/Translator.js";

export default class Table extends Block {
    static label = 'Table';
    static keywords = ['table', 'data', 'grid', 'list', 'matrix', 'spreadsheet'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm240-240H200v160h240v-160Zm80 0v160h240v-160H520Zm-80-80v-160H200v160h240Zm80 0h240v-160H520v160ZM200-680h560v-80H200v80Z"/></svg>`;
    static isText = false;
    static name = 'core/table';
    static category = 'data visualization';
    static description = 'Insert a table for your data.';
    static NUMBER_OF_COLUMNS_WHEN_EMPTY = 2;
    static NUMBER_OF_ROWS_WHEN_EMPTY = 1;
    static COLOR_SETTINGS = [
        {key: 'headerBackground', label: 'Header Background'},
        {key: 'headerColor', label: 'Header Color'},
        {key: 'oddRowBackground', label: 'Odd Row Background'},
        {key: 'oddRowColor', label: 'Odd Row Color'},
        {key: 'evenRowBackground', label: 'Even Row Background'},
        {key: 'evenRowColor', label: 'Even Row Color'},
    ];
    static OPTION_SETTINGS = [
        {key: 'enableSearch', label: 'Enable Search'},
        {key: 'enableSort', label: 'Enable Sort'},
    ];
    static STYLE_VARIABLES = {
        headerBackground: '--table-header-bg',
        headerColor: '--table-header-color',
        oddRowBackground: '--table-odd-bg',
        oddRowColor: '--table-odd-color',
        evenRowBackground: '--table-even-bg',
        evenRowColor: '--table-even-color',
    };
    static advancedSidebarOpen = false;
    table;
    thead;
    theadRow;
    tbody;
    activeHeader = null;
    activeRow = null;
    rowOptionsContainer;
    columnOptionsContainer;
    insertBeforeColumnButton;
    insertAfterColumnButton;
    deleteColumnButton;
    insertAboveRowButton;
    insertBelowRowButton;
    deleteRowButton;
    optionButtonsShown = false;
    settings;
    styleSidebarModule;
    optionsSidebarModule;
    importSidebarModule;
    csvInput;
    csvImportButton;
    csvError;
    sidebarInputs = [];
    filtersCheckbox;
    filterOptionsContainer;
    filterOptionListeners = [];


    render() {
        this.settings = this.#resolveSettings();
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.tableBlock);
        this.#generateBase();
        if(!this.data.headers) {
            this.#generateEmpty();
        } else {
            this.#generateFromData();
        }
        this.#applyStyles();
        this.#addListeners();
        return this.element;
    }


    #resolveSettings() {
        const saved = (this.data && this.data.settings) ? this.data.settings : {};

        return {
            headerBackground: saved.headerBackground || '',
            headerColor: saved.headerColor || '',
            oddRowBackground: saved.oddRowBackground || '',
            oddRowColor: saved.oddRowColor || '',
            evenRowBackground: saved.evenRowBackground || '',
            evenRowColor: saved.evenRowColor || '',
            enableSearch: Boolean(saved.enableSearch),
            enableSort: Boolean(saved.enableSort),
            enableFilters: Boolean(saved.enableFilters),
            filterColumns: Array.isArray(saved.filterColumns) ? [...saved.filterColumns] : [],
        };
    }


    // Live editor preview: the colors are pushed onto the block element as CSS custom
    // properties which the stylesheet reads (with fallbacks), so newly added rows pick
    // up the odd/even styling automatically. The values themselves are saved via getData.
    #applyStyles() {
        Object.entries(Table.STYLE_VARIABLES).forEach(([key, variable]) => {
            const value = this.settings[key];

            if (value) {
                this.element.style.setProperty(variable, value);
            } else {
                this.element.style.removeProperty(variable);
            }
        });
    }


    #generateBase() {
        this.table = document.createElement('table');
        this.table.spellcheck = false;
        this.table.contentEditable = true;
        this.thead = document.createElement('thead');
        this.theadRow = document.createElement('tr');
        this.thead.appendChild(this.theadRow)
        this.table.appendChild(this.thead);
        this.tbody = document.createElement('tbody');
        this.table.appendChild(this.tbody);
        this.#generateRowOptions();
        this.#generateColumnOptions();
        this.element.appendChild(this.table);
    }


    #generateRowOptions() {
        this.rowOptionsContainer = document.createElement('div');
        this.rowOptionsContainer.classList.add(contentEditorSelectors.classes.tableRowOptions);

        this.insertAboveRowButton = document.createElement('div');
        this.insertAboveRowButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-160h560v-240H200v240Zm640 80H120v-720h160v80h-80v240h560v-240h-80v-80h160v720ZM480-480Zm0 80v-80 80Zm0 0Zm-40-240v-80h-80v-80h80v-80h80v80h80v80h-80v80h-80Z"/></svg>`;
        this.insertAboveRowButton.title = Translator.translate('Insert Row Above');

        this.insertBelowRowButton = document.createElement('div');
        this.insertBelowRowButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-560h560v-240H200v240Zm-80 400v-720h720v720H680v-80h80v-240H200v240h80v80H120Zm360-320Zm0-80v80-80Zm0 0ZM440-80v-80h-80v-80h80v-80h80v80h80v80h-80v80h-80Z"/></svg>`;
        this.insertBelowRowButton.title = Translator.translate('Insert Row Below');

        this.deleteRowButton = document.createElement('div');
        this.deleteRowButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>`;
        this.deleteRowButton.title = Translator.translate('Delete Row');

        this.rowOptionsContainer.appendChild(this.insertAboveRowButton);
        this.rowOptionsContainer.appendChild(this.insertBelowRowButton);
        this.rowOptionsContainer.appendChild(this.deleteRowButton);
        this.element.appendChild(this.rowOptionsContainer);
    }

    #generateColumnOptions() {
        this.columnOptionsContainer = document.createElement('div');
        this.columnOptionsContainer.classList.add(contentEditorSelectors.classes.tableColumnOptions);

        this.insertBeforeColumnButton = document.createElement('div');
        this.insertBeforeColumnButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M800-200v-560H560v560h240Zm-640 80v-160h80v80h240v-560H240v80h-80v-160h720v720H160Zm320-360Zm80 0h-80 80Zm0 0ZM160-360v-80H80v-80h80v-80h80v80h80v80h-80v80h-80Z"/></svg>`;
        this.insertBeforeColumnButton.title = Translator.translate('Insert Column Left');

        this.insertAfterColumnButton = document.createElement('div');
        this.insertAfterColumnButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M160-760v560h240v-560H160ZM80-120v-720h720v160h-80v-80H480v560h240v-80h80v160H80Zm400-360Zm-80 0h80-80Zm0 0Zm320 120v-80h-80v-80h80v-80h80v80h80v80h-80v80h-80Z"/></svg>`;
        this.insertAfterColumnButton.title = Translator.translate('Insert Column Right');

        this.deleteColumnButton = document.createElement('div');
        this.deleteColumnButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M55.1 73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L147.2 256 9.9 393.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192.5 301.3 329.9 438.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.8 256 375.1 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192.5 210.7 55.1 73.4z"></path></svg>`;
        this.deleteColumnButton.title = Translator.translate('Delete Column');

        this.columnOptionsContainer.appendChild(this.insertBeforeColumnButton);
        this.columnOptionsContainer.appendChild(this.insertAfterColumnButton);
        this.columnOptionsContainer.appendChild(this.deleteColumnButton);
        this.element.appendChild(this.columnOptionsContainer);
    }



    #generateEmpty() {
        for (let rowIndex = 0; rowIndex < Table.NUMBER_OF_ROWS_WHEN_EMPTY; rowIndex++) {
            const row = document.createElement('tr');

            for (let columnIndex = 0; columnIndex < Table.NUMBER_OF_COLUMNS_WHEN_EMPTY; columnIndex++) {
                this.theadRow.appendChild(document.createElement('th'));
                row.appendChild(document.createElement('td'));
            }

            this.tbody.appendChild(row);
        }
    }

    #generateFromData() {
        const fragmentHeaders = document.createDocumentFragment();
        this.data.headers.forEach((header) => {
            const th = document.createElement('th');
            th.textContent = header;
            fragmentHeaders.appendChild(th);
        });
        this.theadRow.appendChild(fragmentHeaders);


        const fragmentRows = document.createDocumentFragment();
        this.data.rows.forEach((row) => {
            const rowElement = document.createElement('tr');
            row.forEach((td) => {
               const tdElement = document.createElement('td');
               tdElement.textContent = td;
               rowElement.appendChild(tdElement);
            });
            fragmentRows.appendChild(rowElement);
        });
        this.tbody.appendChild(fragmentRows);
    }


    getContainer() {
        return this.element;
    }


    getData() {
        const headers = [];
        this.theadRow.querySelectorAll('th').forEach((th) => {
            headers.push(th.textContent);
        });
        const rows = [];
        this.tbody.querySelectorAll('tr').forEach((tr) => {
           const row = [];
           tr.querySelectorAll('td').forEach((td) => {
              row.push(td.textContent);
           });
           rows.push(row);
        });

        return {headers, rows, settings: {...this.settings, filterColumns: [...this.settings.filterColumns]}};
    }


    #addListeners() {
        this.table.addEventListener('keydown', this.#handleKeyDown);
        this.table.addEventListener('mouseenter', this.#handleMouseEnter, true);
        this.insertBeforeColumnButton.addEventListener('click', this.#handleInsertColumnBefore);
        this.insertAfterColumnButton.addEventListener('click', this.#handleInsertColumnAfter);
        this.deleteColumnButton.addEventListener('click', this.#handleDeleteColumn);
        this.insertAboveRowButton.addEventListener('click', this.#handleInsertRowAbove);
        this.insertBelowRowButton.addEventListener('click', this.#handleInsertRowBelow);
        this.deleteRowButton.addEventListener('click', this.#handleDeleteRow);
    }


    #handleInsertColumnBefore = () => {
        if (!this.activeHeader) {
            return;
        }

        this.#insertColumn(this.activeHeader.cellIndex);
    }


    #handleInsertColumnAfter = () => {
        if (!this.activeHeader) {
            return;
        }

        this.#insertColumn(this.activeHeader.cellIndex + 1);
    }


    #insertColumn(columnIndex) {
        this.theadRow.insertBefore(document.createElement('th'), this.theadRow.children[columnIndex] || null);

        this.tbody.querySelectorAll('tr').forEach((row) => {
            row.insertBefore(document.createElement('td'), row.children[columnIndex] || null);
        });

        // Keep the stored filter indices aligned with the shifted columns.
        this.settings.filterColumns = this.settings.filterColumns.map((index) => index >= columnIndex ? index + 1 : index);
        this.#refreshFilterOptions();
        this.#positionColumnOptions();
    }


    #handleDeleteColumn = () => {
        if (!this.activeHeader) {
            return;
        }

        if (this.theadRow.children.length === 1) {
            return;
        }

        const columnIndex = this.activeHeader.cellIndex;

        this.theadRow.children[columnIndex]?.remove();

        this.tbody.querySelectorAll('tr').forEach((row) => {
            row.children[columnIndex]?.remove();
        });

        // Drop the removed column's filter and shift the ones after it down.
        this.settings.filterColumns = this.settings.filterColumns
            .filter((index) => index !== columnIndex)
            .map((index) => index > columnIndex ? index - 1 : index);
        this.#refreshFilterOptions();

        const nextIndex = Math.min(columnIndex, this.theadRow.children.length - 1);
        this.activeHeader = this.theadRow.children[nextIndex];
        this.#positionColumnOptions();
    }


    #handleInsertRowAbove = () => {
        if (!this.activeRow) {
            return;
        }

        this.#insertRow('above');
    }


    #handleInsertRowBelow = () => {
        if (!this.activeRow) {
            return;
        }

        this.#insertRow('below');
    }


    #insertRow(position) {
        const row = document.createElement('tr');

        for (let columnIndex = 0; columnIndex < this.theadRow.children.length; columnIndex++) {
            row.appendChild(document.createElement('td'));
        }

        this.tbody.insertBefore(row, position === 'above' ? this.activeRow : this.activeRow.nextElementSibling);
        this.#positionRowOptions();
    }


    #handleDeleteRow = () => {
        if (!this.activeRow) {
            return;
        }

        if (this.tbody.children.length === 1) {
            return;
        }

        const nextRow = this.activeRow.nextElementSibling || this.activeRow.previousElementSibling;

        this.activeRow.remove();

        this.activeRow = nextRow;
        this.#positionRowOptions();
    }


    #handleKeyDown = (e) => {
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            this.#handleVerticalArrow(e);
            return;
        }
        if (e.key !== 'Tab' && e.key !== 'Enter') {
            return;
        }
        const cell = this.#getFocusedCell();

        if (!cell) {
            return;
        }

        e.preventDefault();

        if (e.key === 'Tab') {
            this.#moveToNextCell(cell, e.shiftKey);
        }

        if (e.key === 'Enter') {
            const moved = this.#moveToNextRow(cell);

            if (moved) {
                e.stopPropagation();
                return;
            }

            if (cell.parentElement.parentElement === this.tbody) {
                if (cell.textContent.trim() !== '') {
                    e.stopPropagation();
                    this.activeRow = cell.parentElement;
                    this.#insertRow('below');
                    this.#focusCell(this.activeRow.nextElementSibling.children[cell.cellIndex]);
                } else {
                    if (this.tbody.children.length === 1) {
                        return;
                    }
                    const lastRow = this.tbody.querySelector('tr:last-of-type');
                    if(lastRow && this.isRowEmpty(lastRow)) {
                        lastRow.remove();
                        const newLastRow = this.tbody.querySelector('tr:last-of-type');
                        if(newLastRow) {
                            this.activeRow = newLastRow;
                            this.#positionRowOptions();
                        }
                    }
                }
            }
        }
    }

    isRowEmpty(row) {
        let empty = true;
        row.querySelectorAll('td').forEach((td) => {
            if(td.textContent.trim() !== '') {
                empty = false;
            }
        });
        return empty;
    }

    #handleMouseEnter = (e) => {
        const cell = e.target.closest('td, th');

        if (!cell || !this.table.contains(cell)) {
            return;
        }

        const columnIndex = cell.cellIndex;
        const row = cell.parentElement;
        const headerCell = this.theadRow.children[columnIndex];

        if (!headerCell) {
            return;
        }

        this.activeHeader = headerCell;

        this.#positionColumnOptions();

        if(!this.optionButtonsShown) {
            this.columnOptionsContainer.classList.add(contentEditorSelectors.classes.active);
            this.optionButtonsShown = true;
        }

        if (row.parentElement === this.tbody) {
            this.activeRow = row;
            this.#positionRowOptions();
            this.rowOptionsContainer.classList.add(contentEditorSelectors.classes.active);
        } else {
            this.activeRow = null;
            this.rowOptionsContainer.classList.remove(contentEditorSelectors.classes.active);
        }
    };


    #positionColumnOptions() {
        if (!this.activeHeader) {
            return;
        }

        const headerRect = this.activeHeader.getBoundingClientRect();
        const containerRect = this.element.getBoundingClientRect();

        this.columnOptionsContainer.style.left = `${headerRect.left - containerRect.left + 10}px`;
        this.columnOptionsContainer.style.top = `${headerRect.top - containerRect.top - 30}px`;
        this.columnOptionsContainer.style.width = `${headerRect.width}px`;
        this.#enableGlide(this.columnOptionsContainer);
    }


    #positionRowOptions() {
        if (!this.activeRow) {
            return;
        }

        const rowRect = this.activeRow.getBoundingClientRect();
        const containerRect = this.element.getBoundingClientRect();

        this.rowOptionsContainer.style.top = `${rowRect.top - containerRect.top}px`;
        this.rowOptionsContainer.style.left = `${rowRect.left - containerRect.left - 90}px`;
        this.rowOptionsContainer.style.height = `${rowRect.height}px`;
        this.#enableGlide(this.rowOptionsContainer);
    }

    // The toolbars are positioned with inline left/top. Transitioning those makes them glide
    // between columns/rows, but the very first placement would then slide in from 0,0. So the
    // gliding class is added one frame after the first placement — the initial jump lands
    // instantly, every move after it animates.
    #enableGlide(container) {
        if (container.classList.contains(contentEditorSelectors.classes.tableOptionsMovable)) {
            return;
        }
        requestAnimationFrame(() => {
            container.classList.add(contentEditorSelectors.classes.tableOptionsMovable);
        });
    }


    // Up/Down move between cells in the same column (header row included). At the top edge
    // going up, or the bottom edge going down, the key is left alone so it bubbles to the
    // editor's block navigation — which then moves to the block above/below the table.
    #handleVerticalArrow(e) {
        const cell = this.#getFocusedCell();
        if (!cell) {
            return;
        }
        const columnIndex = cell.cellIndex;
        const rows = [this.theadRow, ...this.tbody.children];
        const rowIndex = rows.indexOf(cell.parentElement);
        if (rowIndex === -1) {
            return;
        }
        const targetRow = e.key === 'ArrowDown' ? rows[rowIndex + 1] : rows[rowIndex - 1];
        if (!targetRow) {
            return;   // table edge — let the event bubble so block navigation takes over
        }
        const targetCell = targetRow.children[columnIndex];
        if (!targetCell) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();   // handled inside the table — don't also jump to another block
        this.#focusCell(targetCell);
    }

    #getFocusedCell() {
        const selection = window.getSelection();

        if (!selection.rangeCount) {
            return null;
        }

        let node = selection.anchorNode;

        if (node.nodeType === Node.TEXT_NODE) {
            node = node.parentElement;
        }

        return node.closest('td, th');
    }


    #moveToNextCell(cell, backwards = false) {
        const cells = [...this.table.querySelectorAll('th, td')];
        const index = cells.indexOf(cell);

        if (index === -1) {
            return;
        }

        let nextIndex = backwards ? index - 1 : index + 1;

        if (nextIndex < 0 || nextIndex >= cells.length) {
            return;
        }
        this.#focusCell(cells[nextIndex]);
    }


    #moveToNextRow(cell) {
        const row = cell.parentElement;
        const section = row.parentElement;
        const columnIndex = cell.cellIndex;

        const rows = [...section.querySelectorAll('tr')];
        const rowIndex = rows.indexOf(row);

        if (rows[rowIndex + 1]) {
            this.#focusCell(rows[rowIndex + 1].children[columnIndex]);
            return true;
        }

        if (section === this.thead) {
            const firstBodyRow = this.tbody.querySelector('tr');

            if (firstBodyRow && firstBodyRow.children[columnIndex]) {
                this.#focusCell(firstBodyRow.children[columnIndex]);
                return true;
            }
        }

        return false;
    }


    #focusCell(cell) {
        const range = document.createRange();

        range.selectNodeContents(cell);
        range.collapse(true);

        const selection = window.getSelection();

        selection.removeAllRanges();
        selection.addRange(range);

        cell.focus();
    }


    renderSidebarContent() {
        super.renderSidebarContent();

        if (!this.styleSidebarModule) {
            this.#buildImportSidebar();
            this.#buildStyleSidebar();
            this.#buildOptionsSidebar();

        }

        return this.sidebarContainer;
    }

    #buildImportSidebar() {
        this.importSidebarModule = SidebarSection.generate(
            Translator.translate('Import CSV'),
            contentEditorSelectors.ids.tableImportSidebar,
            this.eventEmitter,
            false
        );
        const content = this.importSidebarModule.container.querySelector(`#${contentEditorSelectors.ids.tableImportSidebar}`);

        this.csvInput = document.createElement('textarea');
        this.csvInput.id = contentEditorSelectors.ids.tableCsvInput;
        this.csvInput.spellcheck = false;
        this.csvInput.classList.add(contentEditorSelectors.classes.input);
        this.csvInput.rows = 5;
        this.csvInput.placeholder = Translator.translate('Paste CSV here — first row becomes the headers.');

        this.csvError = document.createElement('span');
        this.csvError.id = contentEditorSelectors.ids.tableCsvError;
        this.csvError.classList.add(contentEditorSelectors.classes.hidden);

        this.csvImportButton = document.createElement('div');
        this.csvImportButton.title = Translator.translate('Import');
        this.csvImportButton.id = contentEditorSelectors.ids.tableCsvImport;
        this.csvImportButton.textContent = Translator.translate('Import');
        this.csvImportButton.addEventListener('click', this.#handleCsvImport);

        content.append(this.csvInput, this.csvError, this.csvImportButton);
        this.sidebarContainer.prepend(this.importSidebarModule.container);
    }

    #handleCsvImport = () => {
        const grid = parseCsv(this.csvInput.value);
        if (!grid.length) {
            this.#showCsvError('Nothing to import — paste some CSV first.');
            return;
        }
        // Ragged rows are padded to the widest so every row has the same number of cells.
        const width = grid.reduce((max, row) => Math.max(max, row.length), 0);
        const padded = grid.map((row) => {
            const cells = [...row];
            while (cells.length < width) {
                cells.push('');
            }
            return cells;
        });
        this.#applyGrid(padded);
        this.csvInput.value = '';
        this.#hideCsvError();
    };

    // Replace the table's contents with a parsed grid: the first row is the header, the rest
    // are body rows. Rebuilding the DOM inside #content is picked up by the editor's mutation
    // observer, so this flows into contentChanged (history + the unsaved guard) on its own.
    #applyGrid(grid) {
        const [headerRow, ...bodyRows] = grid;
        // A header-only import would render a table with nowhere to type — seed one empty row
        // (matching the header's column count) so it's immediately editable.
        if (bodyRows.length === 0) {
            bodyRows.push(new Array(headerRow.length).fill(''));
        }
        this.theadRow.replaceChildren(...headerRow.map((text) => {
            const th = document.createElement('th');
            th.textContent = text;
            return th;
        }));
        this.tbody.replaceChildren(...bodyRows.map((cells) => {
            const tr = document.createElement('tr');
            cells.forEach((text) => {
                const td = document.createElement('td');
                td.textContent = text;
                tr.appendChild(td);
            });
            return tr;
        }));
        // A rebuilt grid invalidates the previous active header/row (their elements are gone).
        this.activeHeader = null;
        this.activeRow = null;
    }

    #showCsvError(message) {
        this.csvError.textContent = Translator.translate(message);
        this.csvError.classList.remove(contentEditorSelectors.classes.hidden);
    }

    #hideCsvError() {
        this.csvError.textContent = '';
        this.csvError.classList.add(contentEditorSelectors.classes.hidden);
    }

    #buildStyleSidebar() {
        this.styleSidebarModule = SidebarSection.generate(
            Translator.translate('Table Styles'),
            contentEditorSelectors.ids.tableStyleSidebar,
            this.eventEmitter,
            false
        );
        const content = this.styleSidebarModule.container.querySelector(`#${contentEditorSelectors.ids.tableStyleSidebar}`);
        Table.COLOR_SETTINGS.forEach((setting) => {
            content.appendChild(this.#createColorControl(setting));
        });
        this.sidebarContainer.prepend(this.styleSidebarModule.container);
    }

    #buildOptionsSidebar() {
        this.optionsSidebarModule = SidebarSection.generate(
            Translator.translate('Table Options'),
            contentEditorSelectors.ids.tableOptionsSidebar,
            this.eventEmitter,
            true
        );
        const content = this.optionsSidebarModule.container.querySelector(`#${contentEditorSelectors.ids.tableOptionsSidebar}`);
        Table.OPTION_SETTINGS.forEach((setting) => {
            content.appendChild(this.#createCheckboxControl(setting));
        });
        content.appendChild(this.#createFiltersControl());
        this.sidebarContainer.prepend(this.optionsSidebarModule.container);
    }

    #createColorControl(setting) {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.inputContainer);

        const label = document.createElement('label');
        label.textContent = Translator.translate(setting.label);

        const input = document.createElement('input');
        input.type = 'color';
        input.classList.add(contentEditorSelectors.classes.input);
        if (this.settings[setting.key]) {
            input.value = this.settings[setting.key];
        }

        const handler = () => {
            this.settings[setting.key] = input.value;
            this.#applyStyles();
        };
        input.addEventListener('input', handler);
        this.sidebarInputs.push({input, event: 'input', handler});

        container.append(label, input);
        return container;
    }

    #createCheckboxControl(setting) {
        const container = document.createElement('label');

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = this.settings[setting.key];
        input.classList.add(contentEditorSelectors.classes.input);

        const fragment = document.createDocumentFragment();
        fragment.textContent = Translator.translate(setting.label);

        const handler = () => {
            this.settings[setting.key] = input.checked;
        };
        input.addEventListener('change', handler);
        this.sidebarInputs.push({input, event: 'change', handler});

        container.append(input, fragment);
        return container;
    }

    // "Enable Filters" plus a dynamically generated checkbox per header. The header
    // list is (re)built from the live thead whenever filters are toggled on or the
    // columns change, so the options always mirror the current header values.
    #createFiltersControl() {
        const wrapper = document.createElement('div');

        const container = document.createElement('label');
        this.filtersCheckbox = document.createElement('input');
        this.filtersCheckbox.type = 'checkbox';
        this.filtersCheckbox.checked = this.settings.enableFilters;
        this.filtersCheckbox.classList.add(contentEditorSelectors.classes.input);

        const text = document.createDocumentFragment();
        text.textContent = Translator.translate('Enable Filters');

        const handler = () => {
            this.settings.enableFilters = this.filtersCheckbox.checked;
            if (!this.settings.enableFilters) {
                this.settings.filterColumns = [];
            }
            this.#renderFilterOptions();
        };
        this.filtersCheckbox.addEventListener('change', handler);
        this.sidebarInputs.push({input: this.filtersCheckbox, event: 'change', handler});

        container.append(this.filtersCheckbox, text);

        this.filterOptionsContainer = document.createElement('div');
        this.filterOptionsContainer.classList.add(contentEditorSelectors.classes.tableFilterOptions);

        wrapper.append(container, this.filterOptionsContainer);
        this.#renderFilterOptions();
        return wrapper;
    }

    #renderFilterOptions() {
        if (!this.filterOptionsContainer) {
            return;
        }
        this.#clearFilterOptionListeners();
        this.filterOptionsContainer.innerHTML = '';

        if (!this.settings.enableFilters) {
            return;
        }

        [...this.theadRow.children].forEach((th, index) => {
            const container = document.createElement('label');
            container.classList.add(contentEditorSelectors.classes.tableFilterOption);

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = this.settings.filterColumns.includes(index);
            input.classList.add(contentEditorSelectors.classes.input);

            const text = document.createDocumentFragment();
            const headerText = th.textContent.trim();
            text.textContent = headerText || `Column ${index + 1}`;

            const handler = () => {
                this.#toggleFilterColumn(index, input.checked);
            };
            input.addEventListener('change', handler);
            this.filterOptionListeners.push({input, handler});

            container.append(input, text);
            this.filterOptionsContainer.appendChild(container);
        });
    }

    #refreshFilterOptions() {
        if (this.filterOptionsContainer) {
            this.#renderFilterOptions();
        }
    }

    #toggleFilterColumn(index, enabled) {
        const columns = new Set(this.settings.filterColumns);

        if (enabled) {
            columns.add(index);
        } else {
            columns.delete(index);
        }

        this.settings.filterColumns = [...columns].sort((a, b) => a - b);
    }

    #clearFilterOptionListeners() {
        this.filterOptionListeners.forEach(({input, handler}) => {
            input.removeEventListener('change', handler);
        });
        this.filterOptionListeners = [];
    }

    destroySidebar() {
        this.#clearFilterOptionListeners();
        this.sidebarInputs.forEach(({input, event, handler}) => {
            input.removeEventListener(event, handler);
        });
        this.sidebarInputs = [];
        this.filtersCheckbox = null;
        this.filterOptionsContainer = null;
        this.csvImportButton.removeEventListener('click', this.#handleCsvImport);
        this.csvInput = null;
        this.csvImportButton = null;
        this.csvError = null;
        this.styleSidebarModule.destroy();
        this.optionsSidebarModule.destroy();
        this.importSidebarModule.destroy();
        this.styleSidebarModule = null;
        this.optionsSidebarModule = null;
        this.importSidebarModule = null;
        super.destroySidebar();
    }


    focus() {
        const firstCell = this.getFirstCell();
        if(firstCell) {
            // A <th> isn't focusable on its own — the caret in a cell is a selection Range,
            // not element focus. #focusCell places that range (same as Tab/arrow navigation).
            this.#focusCell(firstCell);
            return;
        }
        this.element.focus();
    }

    getFirstCell() {
        return this.thead.querySelector('th');
    }


    destroy() {
        this.element.remove();
        this.table.removeEventListener('keydown', this.#handleKeyDown);
        this.table.removeEventListener('mouseenter', this.#handleMouseEnter, true);
        this.insertBeforeColumnButton.removeEventListener('click', this.#handleInsertColumnBefore);
        this.insertAfterColumnButton.removeEventListener('click', this.#handleInsertColumnAfter);
        this.deleteColumnButton.removeEventListener('click', this.#handleDeleteColumn);
        this.insertAboveRowButton.removeEventListener('click', this.#handleInsertRowAbove);
        this.insertBelowRowButton.removeEventListener('click', this.#handleInsertRowBelow);
        this.deleteRowButton.removeEventListener('click', this.#handleDeleteRow);
        super.destroy();
    }
}