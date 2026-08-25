import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class Spacer extends Block {
    static label = 'Spacer';
    static keywords = ['spacer', 'space', 'gap', 'margin', 'blank'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80 340-220l57-57 43 43v-492l-43 43-57-57 140-140 140 140-57 57-43-43v492l43-43 57 57L480-80Z"/></svg>`;
    static isText = false;
    static name = 'core/spacer';
    static category = 'design';
    static description = 'Add white space between blocks and control its height.';
    static DEFAULT_HEIGHT = 100;
    static MIN_HEIGHT = 24;
    static MAX_HEIGHT = 300;
    static advancedSidebarOpen = false;
    element;
    handle;
    height;
    rangeContainer;
    rangeInput;
    numberInput;
    #isResizing = false;
    #startY = 0;
    #startHeight = 0;

    render() {
        // Coerced on the way in, not trusted: a payload from an import or an older schema can
        // carry "120px" or a numeric string, and #setHeight's Math.min/max would turn that into
        // NaN — which getData() then saves as null. parseFloat also lets a stored 0 through to
        // #setHeight, which clamps it to MIN_HEIGHT rather than silently reverting to the default.
        const storedHeight = parseFloat(this.data?.height);
        this.height = Number.isFinite(storedHeight) ? storedHeight : Spacer.DEFAULT_HEIGHT;
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.spacerBlock);

        this.handle = document.createElement('div');
        this.handle.classList.add(contentEditorSelectors.classes.spacerBlockHandle);
        this.element.appendChild(this.handle);

        this.#setHeight(this.height);
        this.#addListeners();
        return this.element;
    }

    #addListeners() {
        this.element.addEventListener('keydown', this.#handleKeydown);
        this.handle.addEventListener('pointerdown', this.#handlePointerDown);
        this.handle.addEventListener('pointermove', this.#handlePointerMove);
        this.handle.addEventListener('pointerup', this.#handlePointerUp);
    }

    #handlePointerDown = (e) => {
        if(!this.element.classList.contains(contentEditorSelectors.classes.focused)) {
            this.focus();
        }
        e.preventDefault();
        this.#isResizing = true;
        this.#startY = e.clientY;
        this.#startHeight = this.height;
        this.handle.setPointerCapture(e.pointerId);
    }

    #handlePointerMove = (e) => {
        if (!this.#isResizing) {
            return;
        }
        const delta = e.clientY - this.#startY;
        this.#setHeight(this.#startHeight + delta);
    }

    #setHeight(height) {
        this.height = Math.round(Math.min(Spacer.MAX_HEIGHT, Math.max(Spacer.MIN_HEIGHT, height)));
        this.element.style.height = `${this.height}px`;
        if (this.rangeInput) {
            this.rangeInput.value = this.height;
            this.#updateRangeFill();
        }
        if (this.numberInput) {
            this.numberInput.value = this.height;
        }
    }

    #handlePointerUp = (e) => {
        if (!this.#isResizing) {
            return;
        }
        this.#isResizing = false;
        this.handle.releasePointerCapture(e.pointerId);
    }

    #handleKeydown = (e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            this.destroy();
        }
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.rangeInput) {
            this.rangeContainer = document.createElement('div');
            this.rangeContainer.classList.add(contentEditorSelectors.classes.inputContainer);
            this.rangeContainer.classList.add(contentEditorSelectors.classes.spacerContainerSidebar);
            const label = document.createElement('label');
            label.textContent = Translator.translate('Height');
            this.rangeContainer.appendChild(label);

            const controls = document.createElement('div');
            controls.classList.add(
                contentEditorSelectors.classes.spacerHeightControls,
                contentEditorSelectors.classes.rangeControls
            );

            this.rangeInput = document.createElement('input');
            this.rangeInput.type = 'range';
            this.rangeInput.min = Spacer.MIN_HEIGHT;
            this.rangeInput.max = Spacer.MAX_HEIGHT;
            this.rangeInput.value = this.height;
            this.rangeInput.addEventListener('input', this.#handleRangeInput);

            this.numberInput = document.createElement('input');
            this.numberInput.type = 'number';
            this.numberInput.min = Spacer.MIN_HEIGHT;
            this.numberInput.max = Spacer.MAX_HEIGHT;
            this.numberInput.value = this.height;
            this.numberInput.addEventListener('input', this.#handleNumberInput);

            controls.append(this.rangeInput, this.numberInput);
            this.rangeContainer.appendChild(controls);
            this.sidebarContainer.prepend(this.rangeContainer);
            this.#updateRangeFill();
        }
        return this.sidebarContainer;
    }

    #updateRangeFill() {
        if (!this.rangeInput) {
            return;
        }
        const percent = ((this.height - Spacer.MIN_HEIGHT) / (Spacer.MAX_HEIGHT - Spacer.MIN_HEIGHT)) * 100;
        this.rangeInput.style.setProperty('--range-fill', `${percent}%`);
    }

    #handleRangeInput = () => {
        this.#setHeight(parseInt(this.rangeInput.value, 10));
    }

    #handleNumberInput = () => {
        const value = parseInt(this.numberInput.value, 10);
        if (Number.isNaN(value)) {
            this.numberInput.value = this.height;
            return;
        }
        this.#setHeight(value);
    }

    destroySidebar() {
        this.rangeInput.removeEventListener('input', this.#handleRangeInput);
        this.numberInput.removeEventListener('input', this.#handleNumberInput);
        this.rangeContainer.remove();
        this.rangeInput = null;
        this.numberInput = null;
        this.rangeContainer = null;
        super.destroySidebar();
    }

    getData() {
        return {height: this.height};
    }

    destroy() {
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.handle.removeEventListener('pointerdown', this.#handlePointerDown);
        this.handle.removeEventListener('pointermove', this.#handlePointerMove);
        this.handle.removeEventListener('pointerup', this.#handlePointerUp);
        this.element.remove();
        super.destroy();
    }
}
