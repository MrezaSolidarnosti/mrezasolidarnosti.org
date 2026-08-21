import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";
import Translator from "../../../Translator/Translator.js";

export default class Divider extends Block {
    static label = 'Divider';
    static keywords = ['divider', 'hr', 'separator', 'line', 'rule'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M160-440v-80h640v80H160Z"/></svg>`;
    static isText = false;
    static name = 'core/divider';
    static category = 'design';
    static description = 'Create a break between ideas with a horizontal line.';
    static tags = ['hr'];

    static DEFAULT_HEIGHT = 1;
    static MIN_HEIGHT = 1;
    static MAX_HEIGHT = 20;
    // No default colour on purpose: with none set the rule keeps whatever the stylesheet gives
    // it, so a divider nobody has touched still follows the theme instead of being pinned to a
    // value chosen here — and a theme change reaches every untouched divider.
    static DEFAULT_COLOR = '';
    static advancedSidebarOpen = false;

    element;
    rule;
    height;
    color;
    #sidebarModule = null;
    #colorInput = null;
    #heightInput = null;
    #heightRange = null;
    #clearColorButton = null;

    render() {
        this.height = Divider.resolveHeight(this.data?.height);
        this.color = typeof this.data?.color === 'string' ? this.data.color : Divider.DEFAULT_COLOR;

        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add(contentEditorSelectors.classes.dividerBlock);

        this.rule = document.createElement('hr');
        this.rule.classList.add(contentEditorSelectors.classes.dividerRule);
        this.element.appendChild(this.rule);

        this.#applyStyle();
        this.#addListeners();
        return this.element;
    }

    // Coerced rather than trusted, for the same reason the spacer coerces its height: a payload
    // from an import or an older schema can carry "2px", and Math.min/max would turn that into
    // NaN, which getData() would then save as null.
    static resolveHeight(value) {
        const parsed = parseFloat(value);
        if (!Number.isFinite(parsed)) {
            return Divider.DEFAULT_HEIGHT;
        }
        return Math.round(Math.min(Divider.MAX_HEIGHT, Math.max(Divider.MIN_HEIGHT, parsed)));
    }

    /**
     * Written as custom properties rather than as `height` / `background` directly, so the
     * stylesheet keeps ownership of how a divider is drawn — it can add margins, a dotted
     * variant or a responsive rule without this block fighting it with inline values.
     *
     * An unset color removes the property entirely, so the CSS default wins rather than being
     * overridden by an empty string.
     */
    #applyStyle() {
        this.element.style.setProperty(contentEditorSelectors.variables.dividerHeight, `${this.height}px`);
        if (this.color) {
            this.element.style.setProperty(contentEditorSelectors.variables.dividerColor, this.color);
            return;
        }
        this.element.style.removeProperty(contentEditorSelectors.variables.dividerColor);
    }

    #addListeners() {
        this.element.addEventListener('keydown', this.#handleKeydown);
    }

    #handleKeydown = (e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            this.destroy();
        }
    }

    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.#sidebarModule) {
            this.#sidebarModule = SidebarSection.generate(
                Translator.translate('Visual Settings'),
                contentEditorSelectors.ids.dividerSidebar,
                this.eventEmitter,
                true
            );
            const content = this.#sidebarModule.container
                .querySelector(`#${contentEditorSelectors.ids.dividerSidebar}`);

            content.appendChild(this.#buildColorControl());
            content.appendChild(this.#buildHeightControl());
            this.sidebarContainer.prepend(this.#sidebarModule.container);
        }
        return this.sidebarContainer;
    }

    #buildColorControl() {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.inputContainer);

        const label = document.createElement('label');
        label.textContent = Translator.translate('Color');

        this.#colorInput = document.createElement('input');
        this.#colorInput.type = 'color';
        this.#colorInput.classList.add(contentEditorSelectors.classes.input);
        // A color input has no empty state, so an untouched divider shows the theme's own
        // colour as the swatch while `this.color` stays empty. Picking one is what commits it.
        this.#colorInput.value = this.color || this.#themeColor();
        this.#colorInput.addEventListener('input', this.#handleColorInput);

        // The way back to "no colour set" — without it the first click on the swatch is
        // irreversible and the divider is detached from the theme for good.
        this.#clearColorButton = document.createElement('div');
        this.#clearColorButton.textContent = Translator.translate('Use theme colour');
        this.#clearColorButton.title = Translator.translate('Use theme colour');
        this.#clearColorButton.addEventListener('click', this.#handleClearColor);

        container.append(label, this.#colorInput, this.#clearColorButton);
        return container;
    }

    // The rule's computed colour as a hex value, which is the only form a colour input accepts.
    #themeColor() {
        const computed = window.getComputedStyle(this.rule).getPropertyValue('background-color');
        const match = String(computed).match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) {
            return '#000000';
        }
        return '#' + [match[1], match[2], match[3]]
            .map((part) => Number(part).toString(16).padStart(2, '0')).join('');
    }

    // A slider paired with a number input, the same control the spacer uses — dragging is the
    // natural way to pick a thickness, and the number is there for an exact value. Only the
    // sidebar gains anything; the block itself stays a plain rule with no handle in the content.
    #buildHeightControl() {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.inputContainer);

        const label = document.createElement('label');
        label.textContent = Translator.translate('Height (px)');

        const controls = document.createElement('div');
        controls.classList.add(contentEditorSelectors.classes.rangeControls);

        this.#heightRange = document.createElement('input');
        this.#heightRange.type = 'range';
        this.#heightRange.min = String(Divider.MIN_HEIGHT);
        this.#heightRange.max = String(Divider.MAX_HEIGHT);
        this.#heightRange.value = String(this.height);
        this.#heightRange.addEventListener('input', this.#handleRangeInput);

        this.#heightInput = document.createElement('input');
        this.#heightInput.type = 'number';
        this.#heightInput.classList.add(contentEditorSelectors.classes.input);
        this.#heightInput.min = String(Divider.MIN_HEIGHT);
        this.#heightInput.max = String(Divider.MAX_HEIGHT);
        this.#heightInput.value = String(this.height);
        this.#heightInput.addEventListener('input', this.#handleNumberInput);

        controls.append(this.#heightRange, this.#heightInput);
        container.append(label, controls);
        this.#updateRangeFill();
        return container;
    }

    // The two inputs are handled separately so neither writes back to the one being used: setting
    // .value on a number input mid-typing moves the caret, and clamping "1" on the way to "12"
    // would fight the person entering it.
    #handleRangeInput = () => {
        this.#setHeight(this.#heightRange.value);
        if (this.#heightInput) {
            this.#heightInput.value = String(this.height);
        }
    }

    #handleNumberInput = () => {
        this.#setHeight(this.#heightInput.value);
        if (this.#heightRange) {
            this.#heightRange.value = String(this.height);
        }
    }

    #setHeight(value) {
        this.height = Divider.resolveHeight(value);
        this.#updateRangeFill();
        this.#applyStyle();
        this.#commit();
    }

    // The filled part of the track is a gradient stop driven by this custom property, so the
    // slider shows how far along the range the current value sits.
    #updateRangeFill() {
        if (!this.#heightRange) {
            return;
        }
        const span = Divider.MAX_HEIGHT - Divider.MIN_HEIGHT;
        const percent = span ? ((this.height - Divider.MIN_HEIGHT) / span) * 100 : 0;
        this.#heightRange.style.setProperty('--range-fill', `${percent}%`);
    }

    #handleColorInput = () => {
        this.color = this.#colorInput.value;
        this.#applyStyle();
        this.#commit();
    }

    #handleClearColor = () => {
        this.color = '';
        this.#applyStyle();
        this.#colorInput.value = this.#themeColor();
        this.#commit();
    }

    /**
     * Settings live on the instance, but the editor decides what changed by watching the DOM,
     * and the observer's attributeFilter covers href/target/rel/src/alt only — so a style change
     * on its own is invisible to it. Writing them to a watched attribute is what makes an edit
     * here land in history and count towards the unsaved-changes guard.
     */
    #commit() {
        this.element.setAttribute(
            contentEditorSelectors.attributes.dividerSettings,
            JSON.stringify({color: this.color, height: this.height})
        );
    }

    destroySidebar() {
        this.#colorInput?.removeEventListener('input', this.#handleColorInput);
        this.#clearColorButton?.removeEventListener('click', this.#handleClearColor);
        this.#heightInput?.removeEventListener('input', this.#handleNumberInput);
        this.#heightRange?.removeEventListener('input', this.#handleRangeInput);
        this.#colorInput = null;
        this.#clearColorButton = null;
        this.#heightInput = null;
        this.#heightRange = null;
        this.#sidebarModule = null;
        super.destroySidebar();
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    getData() {
        return {color: this.color, height: this.height};
    }

    destroy() {
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.#colorInput?.removeEventListener('input', this.#handleColorInput);
        this.#clearColorButton?.removeEventListener('click', this.#handleClearColor);
        this.#heightInput?.removeEventListener('input', this.#handleNumberInput);
        this.#heightRange?.removeEventListener('input', this.#handleRangeInput);
        this.element.remove();
        super.destroy();
    }
}
