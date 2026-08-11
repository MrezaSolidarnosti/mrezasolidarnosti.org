import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Columns extends Block {
    static label = 'Columns';
    static keywords = ['columns', 'column', 'layout', 'grid', 'row'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120q-33 0-56.5-23.5T520-200v-560q0-33 23.5-56.5T600-840h160q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H600Zm0-640v560h160v-560H600ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h160q33 0 56.5 23.5T440-760v560q0 33-23.5 56.5T360-120H200Zm0-640v560h160v-560H200Zm560 0H600h160Zm-400 0H200h160Z"/></svg>`;
    static isText = false;
    static name = 'core/columns';
    static category = 'layout';
    static description = 'Display content side by side in multiple columns.';
    static advancedSidebarOpen = false;

    static LAYOUTS = [
        {key: '50-50',       label: '50 / 50',           columns: [1, 1]},
        {key: '33-66',       label: '33 / 66',           columns: [1, 2]},
        {key: '66-33',       label: '66 / 33',           columns: [2, 1]},
        {key: '33-33-33',    label: '33 / 33 / 33',      columns: [1, 1, 1]},
        {key: '25-50-25',    label: '25 / 50 / 25',      columns: [1, 2, 1]},
        {key: '25-25-25-25', label: '25 / 25 / 25 / 25', columns: [1, 1, 1, 1]},
    ];

    element;
    columns = [];
    layout = null;
    layoutContainer;
    layoutSelect;

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.columnsBlock);
        this.element.addEventListener('click', this.#handlePickerClick);
        const initial = this.#resolveInitialLayout();
        if (initial) {
            this.#buildColumns(initial);
        } else {
            this.#renderPicker();
        }
        return this.element;
    }

    #resolveInitialLayout() {
        return (this.data && this.data.layout) ? this.#layoutByKey(this.data.layout) : null;
    }

    #layoutByKey(key) {
        return Columns.LAYOUTS.find((l) => l.key === key) || null;
    }

    #renderPicker() {
        const picker = document.createElement('div');
        picker.classList.add(contentEditorSelectors.classes.columnsBlockPicker);
        Columns.LAYOUTS.forEach((layout) => {
            const option = document.createElement('div');
            option.classList.add(contentEditorSelectors.classes.columnsBlockPickerOption);
            option.setAttribute(contentEditorSelectors.attributes.columnsLayout, layout.key);

            const preview = document.createElement('div');
            preview.classList.add(contentEditorSelectors.classes.columnsBlockPickerPreview);
            preview.style.gridTemplateColumns = this.#gridTemplate(layout);
            layout.columns.forEach(() => preview.appendChild(document.createElement('span')));

            const label = document.createElement('span');
            label.classList.add(contentEditorSelectors.classes.columnsBlockPickerLabel);
            label.textContent = layout.label;

            option.append(preview, label);
            picker.appendChild(option);
        });
        this.element.appendChild(picker);
    }

    #handlePickerClick = (e) => {
        const option = e.target.closest(`[${contentEditorSelectors.attributes.columnsLayout}]`);
        if (!option) {
            return;
        }
        const layout = this.#layoutByKey(option.getAttribute(contentEditorSelectors.attributes.columnsLayout));
        if (layout) {
            this.#setLayout(layout);
            this.element.focus();
        }
    }

    #buildColumns(layout) {
        this.layout = layout;
        this.element.innerHTML = '';   // clears the picker
        this.columns = [];
        layout.columns.forEach(() => {
            const column = this.#createColumn();
            this.element.appendChild(column);
            this.columns.push(column);
        });
        this.#applyGridTemplate();
    }

    #createColumn() {
        const column = document.createElement('div');
        column.classList.add(contentEditorSelectors.classes.columnsBlockColumn);
        column.setAttribute(contentEditorSelectors.attributes.blockContainer, 'true');
        return column;
    }

    #gridTemplate(layout) {
        return layout.columns.map((fr) => `${fr}fr`).join(' ');
    }

    #applyGridTemplate() {
        this.element.style.gridTemplateColumns = this.#gridTemplate(this.layout);
    }

    #setLayout(layout) {
        if (!this.layout) {
            this.#buildColumns(layout);
        } else {
            this.#changeLayout(layout);
        }
        if (this.layoutSelect) {
            this.layoutSelect.value = layout.key;
        }
    }

    #changeLayout(layout) {
        if (layout.key === this.layout.key) {
            return;
        }
        const newCount = layout.columns.length;
        const oldCount = this.columns.length;
        if (newCount > oldCount) {
            for (let i = oldCount; i < newCount; i++) {
                const column = this.#createColumn();
                this.element.appendChild(column);
                this.columns.push(column);
            }
        } else if (newCount < oldCount) {
            const lastKept = this.columns[newCount - 1];
            for (let i = newCount; i < oldCount; i++) {
                const removed = this.columns[i];
                while (removed.firstChild) {
                    lastKept.appendChild(removed.firstChild);
                }
                removed.remove();
            }
            this.columns = this.columns.slice(0, newCount);
        }
        this.layout = layout;
        this.#applyGridTemplate();
    }

    getChildContainers() {
        return this.columns;
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.layoutSelect) {
            this.layoutContainer = document.createElement('div');
            this.layoutContainer.classList.add(contentEditorSelectors.classes.inputContainer);
            this.layoutContainer.classList.add(contentEditorSelectors.classes.columnsSelectContainer);
            const label = document.createElement('label');
            label.textContent = Translator.translate('Layout');
            this.layoutSelect = document.createElement('select');
            this.layoutSelect.classList.add(contentEditorSelectors.classes.input);
            Columns.LAYOUTS.forEach((layout) => {
                const option = document.createElement('option');
                option.value = layout.key;
                option.textContent = Translator.translate(layout.label);
                this.layoutSelect.appendChild(option);
            });
            if (this.layout) {
                this.layoutSelect.value = this.layout.key;
            }
            this.layoutSelect.addEventListener('change', this.#handleLayoutChange);
            this.layoutContainer.append(label, this.layoutSelect);
            this.sidebarContainer.prepend(this.layoutContainer);
        }
        return this.sidebarContainer;
    }

    #handleLayoutChange = () => {
        const layout = this.#layoutByKey(this.layoutSelect.value);
        if (layout) {
            this.#setLayout(layout);
        }
    }

    destroySidebar() {
        this.layoutSelect.removeEventListener('change', this.#handleLayoutChange);
        this.layoutContainer.remove();
        this.layoutSelect = null;
        this.layoutContainer = null;
        super.destroySidebar();
    }

    getData() {
        return {layout: this.layout ? this.layout.key : null};
    }

    destroy() {
        this.element.removeEventListener('click', this.#handlePickerClick);
        this.element.remove();
        super.destroy();
    }
}
