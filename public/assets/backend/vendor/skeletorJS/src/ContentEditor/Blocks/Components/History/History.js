import {events} from "../../events.js";
import {events as contentEditorEvents} from "../../../events.js";
import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import Dismissible from "../../../Dismissible/Dismissible.js";
import Translator from "../../../../Translator/Translator.js";

export default class History {
    #setupComplete = false;
    eventEmitter;
    blocks;
    #stack = [];
    #index = -1;
    undoButton;
    redoButton;
    historyButton;
    #panel = null;
    #list = null;
    #panelOpen = false;
    #dismissible = null;
    #highlightedRow = 0;   // keyboard cursor (row position, newest = 0), only while open

    static MAX_ENTRIES = 100;

    static ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m520-384 56-56-96-96v-184h-80v216l120 120ZM368-249q16-48 56.5-79.5T518-360h152q24-34 37-74.5t13-85.5q0-117-81.5-198.5T440-800q-117 0-198.5 81.5T160-520q0 98 58.5 172.5T368-249ZM520-40q-58 0-102-36.5T363-168q-122-26-202.5-124T80-520q0-150 105-255t255-105q150 0 255 105t105 255q0 43-9.5 83.5T763-360q66 0 111.5 47T920-200q0 66-47 113T760-40H520Zm-80-485Zm200 325Zm-120 80h240q33 0 56.5-23.5T840-200q0-33-23.5-56.5T760-280H520q-33 0-56.5 23.5T440-200q0 33 23.5 56.5T520-120Zm-28.5-51.5Q480-183 480-200t11.5-28.5Q503-240 520-240t28.5 11.5Q560-217 560-200t-11.5 28.5Q537-160 520-160t-28.5-11.5Zm120 0Q600-183 600-200t11.5-28.5Q623-240 640-240t28.5 11.5Q680-217 680-200t-11.5 28.5Q657-160 640-160t-28.5-11.5Zm120 0Q720-183 720-200t11.5-28.5Q743-240 760-240t28.5 11.5Q800-217 800-200t-11.5 28.5Q777-160 760-160t-28.5-11.5Z"/></svg>`;

    constructor({eventEmitter, blocks}) {
        this.eventEmitter = eventEmitter;
        this.blocks = blocks;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#buildHistoryButton();
        this.#buildPanel();
        this.#addListeners();
        this.#listenToEvents();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.#panelOpen,
            close: () => this.#closePanel(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.undoButton = document.getElementById(contentEditorSelectors.ids.undoButton);
        this.redoButton = document.getElementById(contentEditorSelectors.ids.redoButton);
    }

    // Sits right after the redo arrow, in the same group.
    #buildHistoryButton() {
        this.historyButton = document.createElement('div');
        this.historyButton.id = contentEditorSelectors.ids.historyButton;
        this.historyButton.title = Translator.translate('History');
        this.historyButton.innerHTML = History.ICON;
        this.redoButton.after(this.historyButton);
    }

    #buildPanel() {
        this.#panel = document.createElement('div');
        this.#panel.id = contentEditorSelectors.ids.historyPanel;
        this.#panel.classList.add(contentEditorSelectors.classes.hidden);
        this.#list = document.createElement('div');
        this.#list.id = contentEditorSelectors.ids.historyList;
        this.#list.addEventListener('click', this.#handleItemClick);
        this.#panel.appendChild(this.#list);
        document.body.appendChild(this.#panel);
    }

    #addListeners() {
        this.undoButton.addEventListener('click', this.undo);
        this.redoButton.addEventListener('click', this.redo);
        this.historyButton.addEventListener('click', this.#togglePanel);
    }

    #listenToEvents() {
        this.eventEmitter.on(contentEditorEvents.contentEditorFinalize, () => this.#captureBaseline());
        this.eventEmitter.on(events.contentChanged, () => this.#capture());
    }

    #snapshot() {
        return JSON.stringify(this.blocks.getBlockData());
    }

    #captureBaseline() {
        this.#stack = [this.#snapshot()];
        this.#index = 0;
        this.#updateButtons();
    }

    #capture() {
        const snapshot = this.#snapshot();
        if(snapshot === this.#stack[this.#index]) {
            return;
        }
        // Drop any redo branch, then push the new state.
        this.#stack.length = this.#index + 1;
        this.#stack.push(snapshot);
        if(this.#stack.length > History.MAX_ENTRIES) {
            this.#stack.shift();
        } else {
            this.#index++;
        }
        this.#updateButtons();
    }

    undo = () => {
        // Flush a not-yet-debounced edit first, so an immediate undo has the latest state
        // to step back from.
        this.#capture();
        if(this.#index <= 0) {
            return;
        }
        this.#index--;
        this.#restore();
        this.#updateButtons();
    }

    redo = () => {
        if(this.#index >= this.#stack.length - 1) {
            return;
        }
        this.#index++;
        this.#restore();
        this.#updateButtons();
    }

    // Jump directly to any point in the stack — the panel's click target.
    jumpTo(index) {
        // Flush a pending edit first (as undo does), so it isn't lost when we restore. It only
        // ever appends, so an index that referred to an existing entry is still valid after.
        this.#capture();
        if(index < 0 || index >= this.#stack.length || index === this.#index) {
            return;
        }
        this.#index = index;
        // Move the cursor onto the state we jumped to, so the panel re-render highlights (and
        // scrolls to) the row you clicked rather than snapping back to the previous one. Rows
        // are newest-first, so the row position is measured from the top of the (post-capture)
        // stack. Keyboard Enter already has these in sync, so this is a no-op there.
        this.#highlightedRow = (this.#stack.length - 1) - this.#index;
        this.#restore();
        this.#updateButtons();
    }

    #restore() {
        this.blocks.restoreContent(JSON.parse(this.#stack[this.#index]));
    }

    // Undo is available once we've moved past the baseline; redo once we're behind the top of
    // the stack. Kept in sync everywhere the stack or index moves — so this is also where the
    // open panel is refreshed.
    #updateButtons() {
        this.undoButton.classList.toggle(contentEditorSelectors.classes.disabled, this.#index <= 0);
        this.redoButton.classList.toggle(contentEditorSelectors.classes.disabled, this.#index >= this.#stack.length - 1);
        if(this.#panelOpen) {
            this.#renderPanel();
        }
    }

    #togglePanel = () => {
        this.#panelOpen ? this.#closePanel() : this.#openPanel();
    }

    #openPanel() {
        this.#capture();   // make sure a pending edit is represented before we list the states
        this.#panelOpen = true;
        // Start the cursor on the state you're currently at — its row, counting from the top
        // (newest first). Arrows move from there.
        this.#highlightedRow = (this.#stack.length - 1) - this.#index;
        this.#renderPanel();
        this.#position();
        this.#panel.classList.remove(contentEditorSelectors.classes.hidden);
        this.historyButton.classList.add(contentEditorSelectors.classes.active);
        document.addEventListener('mousedown', this.#handleOutside, true);
        document.addEventListener('keydown', this.#handlePanelKeydown, true);
    }

    #closePanel() {
        this.#panelOpen = false;
        this.#panel.classList.add(contentEditorSelectors.classes.hidden);
        this.historyButton.classList.remove(contentEditorSelectors.classes.active);
        document.removeEventListener('mousedown', this.#handleOutside, true);
        document.removeEventListener('keydown', this.#handlePanelKeydown, true);
    }

    // Up/Down move the cursor (wrapping, like the other popups); Enter jumps to it. Capturing
    // + stopPropagation so the arrows drive the list, not the editor's block navigation behind
    // it. Escape is left alone — the Dismissible registry closes the panel.
    #handlePanelKeydown = (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            e.stopPropagation();
            this.#moveHighlight(e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            const row = this.#list.children[this.#highlightedRow];
            if (row) {
                this.jumpTo(Number(row.getAttribute('data-index')));
            }
            this.#closePanel();
        }
    }

    #position() {
        const rect = this.historyButton.getBoundingClientRect();
        this.#panel.style.top = `${rect.bottom + 6}px`;
        this.#panel.style.left = `${rect.left}px`;
    }

    // Move the keyboard cursor without rebuilding the list — only the two affected rows change.
    // (#renderPanel is the full rebuild, used when the stack itself changes.)
    #moveHighlight(delta) {
        const rows = this.#list.children;
        if(!rows.length) {
            return;
        }
        rows[this.#highlightedRow]?.classList.remove(contentEditorSelectors.classes.historyItemHighlighted);
        this.#highlightedRow = (this.#highlightedRow + delta + rows.length) % rows.length;
        const next = rows[this.#highlightedRow];
        next.classList.add(contentEditorSelectors.classes.historyItemHighlighted);
        next.scrollIntoView({block: 'nearest'});
    }

    // Newest state on top. Each row is a stack entry; the current one is marked and inert.
    #renderPanel() {
        // Keep the cursor in range if the stack shrank while open.
        this.#highlightedRow = Math.max(0, Math.min(this.#highlightedRow, this.#stack.length - 1));
        this.#list.innerHTML = '';
        let row = 0;
        for(let i = this.#stack.length - 1; i >= 0; i--, row++) {
            this.#list.appendChild(this.#renderItem(i, row));
        }
        const highlighted = this.#list.children[this.#highlightedRow];
        if(highlighted) {
            highlighted.scrollIntoView({block: 'nearest'});
        }
    }

    #renderItem(index, rowPosition) {
        const item = document.createElement('div');
        item.classList.add(contentEditorSelectors.classes.historyItem);
        if(index === this.#index) {
            item.classList.add(contentEditorSelectors.classes.historyItemActive);
        }
        if(rowPosition === this.#highlightedRow) {
            item.classList.add(contentEditorSelectors.classes.historyItemHighlighted);
        }
        item.setAttribute('data-index', String(index));

        const label = document.createElement('span');
        label.classList.add(contentEditorSelectors.classes.historyItemLabel);
        label.textContent = index === 0 ? 'Initial' : `Change ${index}`;

        const meta = document.createElement('span');
        meta.classList.add(contentEditorSelectors.classes.historyItemMeta);
        const count = this.#blockCount(index);
        const blocks = `${count} block${count === 1 ? '' : 's'}`;
        meta.textContent = index === this.#index ? `${blocks} · current` : blocks;

        item.append(label, meta);
        return item;
    }

    #blockCount(index) {
        try {
            const data = JSON.parse(this.#stack[index]);
            return Array.isArray(data) ? data.length : 0;
        } catch (e) {
            return 0;
        }
    }

    #handleItemClick = (e) => {
        const item = e.target.closest(`.${contentEditorSelectors.classes.historyItem}`);
        if(!item) {
            return;
        }
        this.jumpTo(Number(item.getAttribute('data-index')));
    }

    #handleOutside = (e) => {
        if(!this.#panel.contains(e.target) && e.target !== this.historyButton
            && !this.historyButton.contains(e.target)) {
            this.#closePanel();
        }
    }

    destroy() {
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.#stack = [];
        this.#index = -1;
        this.eventEmitter = null;
        this.blocks = null;
        this.undoButton.removeEventListener('click', this.undo);
        this.redoButton.removeEventListener('click', this.redo);
        this.historyButton.removeEventListener('click', this.#togglePanel);
        this.#list.removeEventListener('click', this.#handleItemClick);
        document.removeEventListener('mousedown', this.#handleOutside, true);
        document.removeEventListener('keydown', this.#handlePanelKeydown, true);
        this.historyButton.remove();
        this.#panel.remove();
        this.#panel = null;
        this.#list = null;
    }
}
