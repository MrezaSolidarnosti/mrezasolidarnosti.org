import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";
import ChartRenderer from "../../../Chart/Chart.js";
import Translator from "../../../Translator/Translator.js";

export default class Chart extends Block {
    static label = 'Chart';
    static keywords = ['chart', 'graph', 'bar', 'line', 'pie', 'donut', 'area', 'data', 'visualization', 'plot'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M441-82Q287-97 184-211T81-480q0-155 103-269t257-129v120q-104 14-172 93t-68 185q0 106 68 185t172 93v120Zm80 0v-120q94-12 159-78t79-160h120q-14 143-114.5 243.5T521-82Zm238-438q-14-94-79-160t-159-78v-120q143 14 243.5 114.5T879-520H759Z"/></svg>`;
    static isText = false;
    static name = 'core/chart';
    static category = 'data visualization';
    static description = 'Insert a chart to represent your data.';
    static advancedSidebarOpen = false;

    // Each picker/sidebar type maps to the Chart component's `type` + `stacked`.
    // `multi` seeds/ensures a second series; `categorical` (pie/donut) uses one series.
    static TYPES = [
        {key: 'area',       label: 'Area',        componentType: 'area',  stacked: false, multi: false, categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-160v-520l160 120 200-280 200 160h160v520H120Zm200-120 160-220 280 218v-318H652L496-725 298-447l-98-73v144l120 96Z"/></svg>`},
        {key: 'multiline',  label: 'Multi-line',  componentType: 'line',  stacked: false, multi: true,  categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-120 232-360l-112 80v-98l120-86 245 238 167-134h188v80H680L480-120Zm0-360L305-655 120-520v-99l193-141 175 175 352-255v99L480-480Z"/></svg>`},
        {key: 'line',       label: 'Line',        componentType: 'line',  stacked: false, multi: false, categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m140-220-60-60 300-300 160 160 284-320 56 56-340 384-160-160-240 240Z"/></svg>`},
        {key: 'groupedbar', label: 'Grouped bar', componentType: 'bar',   stacked: false, multi: true,  categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M160-160v-480h160v480H160Zm200 0v-280h160v280H360Zm280 0v-640h160v640H640Z"/></svg>`},
        {key: 'stackedbar', label: 'Stacked bar', componentType: 'bar',   stacked: true,  multi: true,  categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M160-160v-120h160v120H160Zm0-160v-160h160v160H160Zm0-200v-280h160v280H160Zm240 360v-280h160v280H400Zm0-320v-160h160v160H400Zm0-200v-120h160v120H400Zm240 520v-80h160v80H640Zm0-120v-160h160v160H640Zm0-200v-320h160v320H640Z"/></svg>`},
        {key: 'bar',        label: 'Bar',         componentType: 'bar',   stacked: false, multi: false, categorical: false, icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M640-160v-280h160v280H640Zm-240 0v-640h160v640H400Zm-240 0v-440h160v440H160Z"/></svg>`},
        {key: 'donut',      label: 'Donut',       componentType: 'donut', stacked: false, multi: false, categorical: true,  icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M441-82Q287-97 184-211T81-480q0-155 103-269t257-129v120q-104 14-172 93t-68 185q0 106 68 185t172 93v120Zm80 0v-120q94-12 159-78t79-160h120q-14 143-114.5 243.5T521-82Zm238-438q-14-94-79-160t-159-78v-120q143 14 243.5 114.5T879-520H759Z"/></svg>`},
        {key: 'pie',        label: 'Pie',         componentType: 'pie',   stacked: false, multi: false, categorical: true,  icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M520-520h278q-15-110-91.5-186.5T520-798v278Zm-80 358v-636q-121 15-200.5 105.5T160-480q0 122 79.5 212.5T440-162Zm80 0q110-14 187-77t91-187H520v278Zm-40-318Zm0 400q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 155.5 31.5t127 86q54.5 54.5 86 127T880-480q0 82-31.5 155T763-197.5q-54 54.5-127 86T480-80Z"/></svg>`},
    ];

    static PREVIEW_HEIGHT = 300;

    element;
    chartType = null;
    labels = [];
    series = [];
    chart = null;              // ChartRenderer instance
    chartContainer = null;

    chartSidebarModule = null;
    typeGrid = null;
    dataEditorContainer = null;
    #gridListeners = [];       // type-grid buttons (live for the section's lifetime)
    #dataListeners = [];       // data-editor inputs (rebuilt on every #renderDataEditor)

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.chartBlock);
        this.element.addEventListener('click', this.#handlePickerClick);
        this.#loadInitialData();
        if (this.chartType) {
            this.#buildEditorSurface();
        } else {
            this.#renderPicker();
        }
        return this.element;
    }

    #loadInitialData() {
        const data = this.data || {};
        if (data.chartType && this.#typeByKey(data.chartType)) {
            this.chartType = data.chartType;
            this.labels = Array.isArray(data.labels) ? data.labels.map((l) => `${l}`) : [];
            this.series = Array.isArray(data.series)
                ? data.series.map((s) => ({name: s.name || '', values: (s.values || []).map((v) => this.#num(v))}))
                : [];
            if (!this.labels.length || !this.series.length) {
                this.#seedData(this.chartType);
            }
        }
    }


    #renderPicker() {
        const picker = document.createElement('div');
        picker.classList.add(contentEditorSelectors.classes.chartBlockPicker);
        Chart.TYPES.forEach((type) => {
            const option = document.createElement('div');
            option.classList.add(contentEditorSelectors.classes.chartBlockPickerOption);
            option.setAttribute(contentEditorSelectors.attributes.chartBlockType, type.key);

            const icon = document.createElement('span');
            icon.classList.add(contentEditorSelectors.classes.chartBlockPickerIcon);
            icon.innerHTML = type.icon;

            const label = document.createElement('span');
            label.classList.add(contentEditorSelectors.classes.chartBlockPickerLabel);
            label.textContent = type.label;

            option.append(icon, label);
            picker.appendChild(option);
        });
        this.element.appendChild(picker);
    }

    #handlePickerClick = (e) => {
        const option = e.target.closest(`[${contentEditorSelectors.attributes.chartBlockType}]`);
        if (!option) {
            return;
        }
        this.#setType(option.getAttribute(contentEditorSelectors.attributes.chartBlockType));
    };


    #buildEditorSurface() {
        this.chartContainer = document.createElement('div');
        this.chartContainer.classList.add(contentEditorSelectors.classes.chartPreview);
        this.element.appendChild(this.chartContainer);
        this.#buildChart();
    }

    #buildChart() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        const type = this.#typeByKey(this.chartType);
        this.chart = new ChartRenderer({
            target: this.chartContainer,
            type: type.componentType,
            data: this.#chartData(),
            options: {
                stacked: type.stacked,
                animate: false,      // instant while editing
                legendToggle: false, // don't let a preview-only toggle diverge from the data
                height: Chart.PREVIEW_HEIGHT,
            },
        }).init();
    }

    #updateChart() {
        if (this.chart) {
            this.chart.update(this.#chartData());
        }
    }

    #chartData() {
        return {
            labels: this.labels.slice(),
            series: this.series.map((s) => ({name: s.name, values: s.values.slice()})),
        };
    }


    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.chartSidebarModule) {
            this.chartSidebarModule = SidebarSection.generate(
                Translator.translate('Chart'),
                contentEditorSelectors.ids.chartSidebar,
                this.eventEmitter,
                true
            );
            const content = this.chartSidebarModule.container.querySelector(`#${contentEditorSelectors.ids.chartSidebar}`);
            content.appendChild(this.#buildTypeGrid());
            this.dataEditorContainer = document.createElement('div');
            this.dataEditorContainer.classList.add(contentEditorSelectors.classes.chartDataEditor);
            content.appendChild(this.dataEditorContainer);
            this.#renderDataEditor();
            this.sidebarContainer.prepend(this.chartSidebarModule.container);
        }
        return this.sidebarContainer;
    }

    #buildTypeGrid() {
        this.typeGrid = document.createElement('div');
        this.typeGrid.classList.add(contentEditorSelectors.classes.chartTypeGrid);
        Chart.TYPES.forEach((type) => {
            const option = document.createElement('div');
            option.classList.add(contentEditorSelectors.classes.chartTypeOption);
            option.setAttribute(contentEditorSelectors.attributes.chartBlockType, type.key);
            option.title = Translator.translate(type.label);
            option.innerHTML = type.icon;
            this.#track(this.#gridListeners, option, 'click', () => this.#setType(type.key));
            this.typeGrid.appendChild(option);
        });
        this.#updateTypeGridActive();
        return this.typeGrid;
    }

    #updateTypeGridActive() {
        if (!this.typeGrid) {
            return;
        }
        this.typeGrid.querySelectorAll(`.${contentEditorSelectors.classes.chartTypeOption}`).forEach((option) => {
            const active = option.getAttribute(contentEditorSelectors.attributes.chartBlockType) === this.chartType;
            option.classList.toggle(contentEditorSelectors.classes.chartTypeOptionActive, active);
        });
    }

    #setType(key) {
        const type = this.#typeByKey(key);
        if (!type) {
            return;
        }
        const firstChoice = !this.chartType;
        if (!firstChoice && key === this.chartType) {
            return;
        }

        if (firstChoice) {
            this.chartType = key;
            this.#seedData(key);
            this.element.innerHTML = '';
            this.element.removeEventListener('click', this.#handlePickerClick);
            this.#buildEditorSurface();
            this.element.focus();
        } else {
            this.chartType = key;
            this.#reconcileSeriesForType(type);
            this.#buildChart();
        }

        this.#updateTypeGridActive();
        if (this.dataEditorContainer) {
            this.#renderDataEditor();
        }
    }

    #renderDataEditor() {
        this.#clear(this.#dataListeners);
        this.dataEditorContainer.innerHTML = '';
        this.#updateSidebarWidth();
        if (!this.chartType) {
            return;
        }
        const type = this.#typeByKey(this.chartType);
        const seriesCount = type.categorical ? 1 : this.series.length;

        const grid = document.createElement('div');
        grid.classList.add(contentEditorSelectors.classes.chartDataGrid);
        grid.style.gridTemplateColumns = `1fr ${'minmax(56px, 1fr) '.repeat(seriesCount)}28px`;

        const labelHead = document.createElement('div');
        labelHead.classList.add(contentEditorSelectors.classes.chartDataHead);
        labelHead.textContent = Translator.translate('Label');
        grid.appendChild(labelHead);

        for (let s = 0; s < seriesCount; s++) {
            grid.appendChild(this.#buildSeriesHead(s, type));
        }
        grid.appendChild(document.createElement('div')); // corner above the remove column

        this.labels.forEach((label, i) => {
            const labelInput = this.#input(label, 'text');
            this.#track(this.#dataListeners, labelInput, 'input', () => {
                this.labels[i] = labelInput.value;
                this.#updateChart();
            });
            grid.appendChild(labelInput);

            for (let s = 0; s < seriesCount; s++) {
                const value = this.series[s] ? (this.series[s].values[i] ?? 0) : 0;
                const valueInput = this.#input(value, 'number');
                this.#track(this.#dataListeners, valueInput, 'input', () => {
                    if (!this.series[s]) return;
                    this.series[s].values[i] = this.#num(valueInput.value);
                    this.#updateChart();
                });
                grid.appendChild(valueInput);
            }

            const remove = this.#miniButton();
            if (this.labels.length <= 1) {
                remove.classList.add(contentEditorSelectors.classes.chartDataRemoveDisabled);
            }
            this.#track(this.#dataListeners, remove, 'click', () => this.#removeCategory(i));
            grid.appendChild(remove);
        });

        this.dataEditorContainer.appendChild(grid);

        const footer = document.createElement('div');
        footer.classList.add(contentEditorSelectors.classes.chartDataFooter);
        const addCategory = this.#addButton('+ Category');
        this.#track(this.#dataListeners, addCategory, 'click', () => this.#addCategory());
        footer.appendChild(addCategory);
        if (!type.categorical) {
            const addSeries = this.#addButton('+ Series');
            this.#track(this.#dataListeners, addSeries, 'click', () => this.#addSeries());
            footer.appendChild(addSeries);
        }
        this.dataEditorContainer.appendChild(footer);
    }

    #buildSeriesHead(index, type) {
        const cell = document.createElement('div');
        cell.classList.add(contentEditorSelectors.classes.chartSeriesHead);
        if (type.categorical) {
            cell.classList.add(contentEditorSelectors.classes.chartDataHead);
            cell.textContent = Translator.translate('Value');
            return cell;
        }
        const nameInput = this.#input(this.series[index] ? this.series[index].name : '', 'text');
        nameInput.placeholder = `Series`;
        this.#track(this.#dataListeners, nameInput, 'input', () => {
            if (!this.series[index]) return;
            this.series[index].name = nameInput.value;
            this.#updateChart();
        });
        cell.appendChild(nameInput);
        if (this.series.length > 1) {
            const remove = this.#miniButton();
            this.#track(this.#dataListeners, remove, 'click', () => this.#removeSeries(index));
            cell.appendChild(remove);
        }
        return cell;
    }


    #addCategory() {
        this.labels.push('');
        this.series.forEach((s) => s.values.push(0));
        this.#renderDataEditor();
        this.#updateChart();
    }

    #removeCategory(index) {
        if (this.labels.length <= 1) {
            return;
        }
        this.labels.splice(index, 1);
        this.series.forEach((s) => s.values.splice(index, 1));
        this.#renderDataEditor();
        this.#updateChart();
    }

    #addSeries() {
        this.series.push({name: '', values: this.labels.map(() => 0)});
        this.#renderDataEditor();
        this.#updateChart();
    }

    #removeSeries(index) {
        if (this.series.length <= 1) {
            return;
        }
        this.series.splice(index, 1);
        this.#renderDataEditor();
        this.#updateChart();
    }

    #reconcileSeriesForType(type) {
        if (type.categorical && this.series.length > 1) {
            this.series = this.series.slice(0, 1);
        } else if (type.multi && this.series.length < 2) {
            this.series.push({name: '', values: this.labels.map(() => 0)});
        }
    }

    #seedData(key) {
        const type = this.#typeByKey(key);
        this.labels = ['A', 'B', 'C'];
        if (type.categorical) {
            this.series = [{name: '', values: [40, 35, 25]}];
        } else if (type.multi) {
            this.series = [
                {name: 'Series 1', values: [12, 20, 15]},
                {name: 'Series 2', values: [8, 14, 9]},
            ];
        } else {
            this.series = [{name: 'Series 1', values: [12, 20, 15]}];
        }
    }


    #typeByKey(key) {
        return Chart.TYPES.find((t) => t.key === key) || null;
    }

    #updateSidebarWidth() {
        if (!this.sidebarContainer) {
            return;
        }
        const type = this.#typeByKey(this.chartType);
        const count = type && !type.categorical ? this.series.length : 1;
        if (count > 1) {
            this.sidebarContainer.setAttribute(contentEditorSelectors.attributes.chartSeriesCount, `${Math.min(6, count)}`);
        } else {
            this.sidebarContainer.removeAttribute(contentEditorSelectors.attributes.chartSeriesCount);
        }
    }

    #input(value, type) {
        const input = document.createElement('input');
        input.type = type;
        input.classList.add(contentEditorSelectors.classes.input);
        input.classList.add(contentEditorSelectors.classes.chartDataInput);
        input.value = value;
        return input;
    }

    #miniButton() {
        const el = document.createElement('div');
        el.classList.add(contentEditorSelectors.classes.chartDataRemove);
        el.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>';
        return el;
    }

    #addButton(text) {
        const el = document.createElement('div');
        el.classList.add(contentEditorSelectors.classes.chartDataAdd);
        el.textContent = text;
        return el;
    }

    #num(value) {
        const n = parseFloat(value);
        return isFinite(n) ? n : 0;
    }

    #track(list, element, event, handler) {
        element.addEventListener(event, handler);
        list.push({element, event, handler});
    }

    #clear(list) {
        list.forEach(({element, event, handler}) => element.removeEventListener(event, handler));
        list.length = 0;
    }


    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    getData() {
        return {
            chartType: this.chartType,
            labels: this.labels.slice(),
            series: this.series.map((s) => ({name: s.name, values: s.values.slice()})),
        };
    }

    destroySidebar() {
        this.#clear(this.#dataListeners);
        this.#clear(this.#gridListeners);
        if (this.chartSidebarModule) {
            this.chartSidebarModule.destroy();
            this.chartSidebarModule = null;
        }
        this.typeGrid = null;
        this.dataEditorContainer = null;
        super.destroySidebar();
    }

    destroy() {
        this.element.removeEventListener('click', this.#handlePickerClick);
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        this.element.remove();
        super.destroy();
    }
}
