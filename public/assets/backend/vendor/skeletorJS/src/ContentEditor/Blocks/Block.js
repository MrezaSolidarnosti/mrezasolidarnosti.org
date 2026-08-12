import {events} from "./events.js";
import {events as contentEditorEvents} from "../events.js";
import SidebarSection from "../Sidebar/SidebarSection/SidebarSection.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {appliesToBlock} from "./blockScope.js";
import Translator from "../../Translator/Translator.js";

export default class Block {
    static label = 'Block';
    static keywords = [];
    static icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M224.3-2.5c19.8-11.4 44.2-11.4 64 0L464.2 99c19.8 11.4 32 32.6 32 55.4l0 203c0 22.9-12.2 44-32 55.4L288.3 514.5c-19.8 11.4-44.2 11.4-64 0L48.5 413c-19.8-11.4-32-32.6-32-55.4l0-203c0-22.9 12.2-44 32-55.4L224.3-2.5zm207.8 360l0-166.1-143.8 83 0 166.1 143.8-83z"/></svg>';
    static isText = false;
    // Whether the Advanced section starts expanded. A block with a settings section of its
    // own sets this false, so its own controls are what you see first.
    static advancedSidebarOpen = true;
    static name = 'core/block'
    static category = 'text';
    static description = 'A content block.';
    // `hidden` = a system/managed block (footnotes, unknown): kept out of the inserter, slash
    // menu, overview and multi-selection, and not duplicated by hand. By default it also can't
    // be deleted — that's the footnotes contract. A hidden block opts *back* into deletion with
    // `static deletable = true` (the unknown-block placeholder does, so it can be removed).
    static hidden = false;
    static deletable = false;
    // Marks the unknown-block placeholder. Registration APIs skip it by default: it's inert
    // preserved content, so most actions/controls are meaningless on it.
    static isUnknown = false;
    static canBeDeleted() {
        return !this.hidden || this.deletable;
    }

    /**
     * Extra sidebar controls, added by the project rather than by a block.
     *
     * Keyed by the `additionalData` key they write to, so registering the same key twice
     * replaces rather than duplicates. Whatever a control writes lands in `sidebarData`, which
     * *is* `additionalData` — so the value round-trips through save/load with no extra work.
     *
     *   Block.registerSidebarControl({
     *       key: 'showOnMobile',        // → additionalData.showOnMobile
     *       label: 'Show on mobile',
     *       type: 'checkbox',           // checkbox | text | textarea | select
     *       default: true,
     *       blocks: ['core/image'],     // omit for every block
     *       section: 'Visibility',      // omit to sit inside Advanced
     *   });
     *
     * `render(block, {listen, value})` may be given instead of `type` for anything the built-in
     * types can't express; it returns an element and owns its own wiring (write to
     * `block.sidebarData`). `value` is the stored value or the declared `default`. Bind events
     * with the supplied `listen(target, event, handler)` so they're torn down with the sidebar
     * like the built-in ones. An optional `destroy(block, element)` covers anything that isn't
     * a listener.
     */
    static SIDEBAR_CONTROLS = new Map();

    static registerSidebarControl(definition) {
        if (!definition || !definition.key) {
            throw new Error('A sidebar control needs a `key` — it is the additionalData key it writes to.');
        }
        Block.SIDEBAR_CONTROLS.set(definition.key, {type: 'text', ...definition});
    }

    static unRegisterSidebarControl(key) {
        Block.SIDEBAR_CONTROLS.delete(key);
    }
    eventEmitter;
    // The editor's config (`config.contentEditor`), whole rather than a slice — a block reads
    // the key it owns. Empty object for a block built outside a full editor.
    config;
    data;
    id;      // stable identity: persisted with the block and reused on load
    sidebarData = {};
    classNamesContainer;
    classNamesInput;
    htmlIdContainer;
    htmlIdInput;
    inlineCssContainer;
    inlineCssInput;
    sidebarContainer;
    advancedSidebarModule;
    registeredSidebarListeners = [];   // {element, event, handler} from registered controls
    registeredSidebarSections = [];    // SidebarSection modules created for custom sections
    registeredSidebarTeardowns = [];   // destroy() callbacks from custom-rendered controls
    /**
     * A subclass that declares its own constructor must forward the whole bag —
     * `constructor(options) { super(options); }` — or better, not declare one at all. A
     * constructor that only passes its arguments through is what JavaScript does by default,
     * and one that destructures a fixed list silently drops anything added here later.
     */
    constructor({data, id, eventEmitter, config = {}}) {
        this.data = data;
        this.id = id;
        this.eventEmitter = eventEmitter;
        this.config = config;
    }
    render() {
        throw new Error(`A ContentEditor Block must implement the render() method.`);
    }

    getContainer() {
        throw new Error(`A ContentEditor Block must implement the getContainer() method.`);
    }

    focus() {
        throw new Error(`A ContentEditor Block must implement the focus() method.`);
    }

    getData() {
        throw new Error(`A ContentEditor Block must implement the getData() method.`);
    }

    setContent(html) {
        if (this.element) {
            this.element.innerHTML = html;
        }
    }


    renderBLockMenu(textElement) {
        this.eventEmitter.emit(contentEditorEvents.renderBlockMenu, {block: this, textElement});
    }

    renderSidebarContent() {
        if(!this.sidebarContainer) {
            this.sidebarContainer = document.createElement('div');
            this.advancedSidebarModule = SidebarSection.generate(
                Translator.translate('Advanced'),
                contentEditorSelectors.ids.advancedBlockSidebar, this.eventEmitter,
                this.constructor.advancedSidebarOpen
            );
            this.advancedSidebarModule.init();
            this.sidebarContainer.appendChild(this.advancedSidebarModule.container);
            const advancedBlockSidebar = this.advancedSidebarModule.container.querySelector(`#${contentEditorSelectors.ids.advancedBlockSidebar}`);
            advancedBlockSidebar.appendChild(this.#generateAdvancedSidebarClassNames());
            advancedBlockSidebar.appendChild(this.#generateAdvancedSidebarHtmlId());
            advancedBlockSidebar.appendChild(this.#generateAdvancedSidebarInlineCss());
            this.#renderRegisteredControls(advancedBlockSidebar);
        }

        return this.sidebarContainer;
    }

    // Project-registered controls that apply to this block. Sectionless ones go into Advanced
    // (after the built-ins); the rest are grouped into their own collapsible sections, placed
    // above Advanced so it stays last.
    #renderRegisteredControls(advancedBlockSidebar) {
        const applicable = [...Block.SIDEBAR_CONTROLS.values()].filter((control) =>
            appliesToBlock(control, this.constructor)
        );
        if (!applicable.length) {
            return;
        }
        const sections = new Map();   // section name → [controls]; insertion order is render order
        applicable.forEach((control) => {
            if (!control.section) {
                advancedBlockSidebar.appendChild(this.#renderSidebarControl(control));
                return;
            }
            if (!sections.has(control.section)) {
                sections.set(control.section, []);
            }
            sections.get(control.section).push(control);
        });
        sections.forEach((controls, name) => {
            // generate() already inits, and exposes the content wrapper — no id lookup needed.
            // The id still has to be unique per section, so it's derived from the label.
            const module = SidebarSection.generate(
                name,
                `blockSidebarSection-${this.#sectionId(name)}`,
                this.eventEmitter,
                false
            );
            const content = module.contentElement.firstElementChild || module.contentElement;
            controls.forEach((control) => content.appendChild(this.#renderSidebarControl(control)));
            this.sidebarContainer.insertBefore(module.container, this.advancedSidebarModule.container);
            this.registeredSidebarSections.push(module);
        });
    }

    #sectionId(name) {
        return name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    }

    // One control. `render(block, {listen})` is the escape hatch — it owns its own markup and
    // wiring; everything else is built from `type` and bound to sidebarData[key].
    #renderSidebarControl(control) {
        if (typeof control.render === 'function') {
            // `listen` registers into the same list the built-in types use, so a custom
            // control's listeners are removed on destroySidebar like everything else — the
            // consumer doesn't have to track them. `destroy` covers anything that isn't a
            // listener (a timer, an observer).
            const element = control.render(this, {
                listen: (target, event, handler) => {
                    target.addEventListener(event, handler);
                    this.registeredSidebarListeners.push({element: target, event, handler});
                },
                // The stored value, or the declared default — resolved the same way the
                // built-in types do it, so `default` is declared once and works everywhere
                // (it also seeds additionalData before the control is ever touched).
                value: this.sidebarData[control.key] ?? control.default,
            });
            if (typeof control.destroy === 'function') {
                this.registeredSidebarTeardowns.push(() => control.destroy(this, element));
            }
            return element;
        }
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.inputContainer);
        // `??` not `||`, so a stored `false` or '' wins over the default rather than being
        // treated as "unset".
        const current = this.sidebarData[control.key] ?? control.default;

        if (control.type === 'checkbox') {
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.classList.add(contentEditorSelectors.classes.input);
            input.checked = Boolean(current);
            const handler = () => {
                this.sidebarData[control.key] = input.checked;
            };
            input.addEventListener('change', handler);
            this.registeredSidebarListeners.push({element: input, event: 'change', handler});
            label.append(input, document.createTextNode(control.label || control.key));
            container.appendChild(label);
            return container;
        }

        const label = document.createElement('label');
        label.textContent = control.label || control.key;
        container.appendChild(label);

        let input;
        if (control.type === 'select') {
            input = document.createElement('select');
            (control.options || []).forEach((option) => {
                const optionElement = document.createElement('option');
                optionElement.value = option.value;
                optionElement.textContent = option.label ?? option.value;
                input.appendChild(optionElement);
            });
        } else {
            input = document.createElement(control.type === 'textarea' ? 'textarea' : 'input');
        }
        input.classList.add(contentEditorSelectors.classes.input);
        if (current !== undefined && current !== null) {
            input.value = current;
        }
        const event = control.type === 'select' ? 'change' : 'input';
        const handler = () => {
            this.sidebarData[control.key] = input.value;
        };
        input.addEventListener(event, handler);
        this.registeredSidebarListeners.push({element: input, event, handler});
        container.appendChild(input);
        return container;
    }

    #generateAdvancedSidebarHtmlId() {
        this.htmlIdContainer = document.createElement('div');
        this.htmlIdContainer.classList.add(contentEditorSelectors.classes.inputContainer);
        const label = document.createElement('label');
        label.textContent = Translator.translate('HTML ID');
        this.htmlIdContainer.appendChild(label);
        this.htmlIdInput = document.createElement('input');
        this.htmlIdInput.classList.add(contentEditorSelectors.classes.input);
        if(this.sidebarData.htmlId) {
            this.htmlIdInput.value = this.sidebarData.htmlId;
        }
        this.htmlIdInput.addEventListener('input', this.#handleHtmlIdOnInput);
        this.htmlIdContainer.appendChild(this.htmlIdInput);
        return this.htmlIdContainer;
    }

    #generateAdvancedSidebarClassNames() {
        this.classNamesContainer = document.createElement('div');
        this.classNamesContainer.classList.add(contentEditorSelectors.classes.inputContainer);
        const label = document.createElement('label');
        label.textContent = Translator.translate('CSS Classes');
        this.classNamesContainer.appendChild(label);
        this.classNamesInput = document.createElement('input');
        this.classNamesInput.classList.add(contentEditorSelectors.classes.input);
        if(this.sidebarData.classNames) {
            this.classNamesInput.value = this.sidebarData.classNames;
        }
        this.classNamesInput.addEventListener('input', this.#handleClassNamesOnInput);
        this.classNamesContainer.appendChild(this.classNamesInput);
        return this.classNamesContainer;
    }

    #generateAdvancedSidebarInlineCss() {
        this.inlineCssContainer = document.createElement('div');
        this.inlineCssContainer.classList.add(contentEditorSelectors.classes.inputContainer);
        const label = document.createElement('label');
        label.textContent = Translator.translate('Inline CSS');
        this.inlineCssContainer.appendChild(label);
        this.inlineCssInput = document.createElement('textarea');
        this.inlineCssInput.classList.add(contentEditorSelectors.classes.input);
        if(this.sidebarData.inlineCss) {
            this.inlineCssInput.value = this.sidebarData.inlineCss;
        }
        this.inlineCssInput.addEventListener('input', this.#handleInlineCssOnInput);
        this.inlineCssContainer.appendChild(this.inlineCssInput);
        return this.inlineCssContainer;
    }

    #handleClassNamesOnInput = () => {
        this.sidebarData.classNames = this.classNamesInput.value;
    }

    #handleHtmlIdOnInput = () => {
        this.sidebarData.htmlId = this.htmlIdInput.value;
    }

    #handleInlineCssOnInput = () => {
        this.sidebarData.inlineCss = this.inlineCssInput.value;
    }

    destroySidebar() {
        // Registered controls first — they're torn down with the container below.
        this.registeredSidebarListeners.forEach(({element, event, handler}) => {
            element.removeEventListener(event, handler);
        });
        this.registeredSidebarListeners = [];
        this.registeredSidebarSections.forEach((section) => section.destroy());
        this.registeredSidebarSections = [];
        this.registeredSidebarTeardowns.forEach((teardown) => teardown());
        this.registeredSidebarTeardowns = [];
        this.classNamesInput.removeEventListener('input', this.#handleClassNamesOnInput);
        this.htmlIdInput.removeEventListener('input', this.#handleHtmlIdOnInput);
        this.inlineCssInput.removeEventListener('input', this.#handleInlineCssOnInput);
        this.classNamesInput = null;
        this.classNamesContainer = null;
        this.htmlIdContainer = null;
        this.htmlIdInput = null;
        this.inlineCssContainer = null;
        this.inlineCssInput = null;
        this.advancedSidebarModule.destroy();
        this.sidebarContainer.remove();
        this.sidebarContainer = null;
    }

    getBlockData() {
        const data = this.getData();
        data.type = this.constructor.name;
        data.id = this.id;
        // Text alignment lives as data-align on the block's own root, set by the format
        // toolbar. Persisted here (not in getData) so it round-trips for any block whose root
        // carries it — today only text blocks, but the plumbing is type-agnostic.
        const align = this.getContainer().getAttribute(contentEditorSelectors.attributes.blockAlign);
        if (align) {
            data.align = align;
        }
        data.additionalData = this.#additionalDataWithDefaults();
        return data;
    }

    // A registered control's `default` is what it should be worth *before anyone touches it* —
    // so it belongs in the payload from the start, not only once the user flips the control.
    // Filling in here (rather than on the change event) also means it doesn't depend on the
    // block ever having been focused: a block whose sidebar was never opened still saves the
    // default. Only missing keys are filled — a stored value, including `false` or '', wins.
    #additionalDataWithDefaults() {
        const data = {...this.sidebarData};
        Block.SIDEBAR_CONTROLS.forEach((control) => {
            if (control.default === undefined) {
                return;
            }
            if (!appliesToBlock(control, this.constructor)) {
                return;   // not registered for this block type
            }
            if (data[control.key] === undefined) {
                data[control.key] = control.default;
            }
        });
        return data;
    }


    // Subclasses must detach their element *before* calling this. Announcing the deletion moves
    // focus to a neighbour, and the side toggle positions itself off that neighbour's
    // getBoundingClientRect(). With the element still in the document the neighbour is measured
    // where it sits *under* the block being deleted, and the removal that follows shifts it up
    // with nothing left to re-measure — the toggle is stranded by the deleted block's own height.
    destroy() {
        this.eventEmitter.emit(events.blockDeleted, this);
        this.eventEmitter = null;
        this.config = null;
    }
}