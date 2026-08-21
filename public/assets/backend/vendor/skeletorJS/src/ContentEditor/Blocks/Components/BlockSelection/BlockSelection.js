import {events} from "../../events.js";
import {events as selectionEvents} from "./events.js";
import {contentEditorSelectors} from "../../../contentEditorSelectors.js";


export default class BlockSelection {

    // What a selection can do, for the shortcuts panel. Declared here because this is what
    // implements them (with the block manager's keydown handling) — the panel only lists
    // them, since several involve the mouse and none is a bindable Shortcut.
    static SHORTCUTS = [
        {keys: ['Shift', 'Click'], description: 'Select a range of blocks'},
        {keys: ['Ctrl', 'Click'], description: 'Add or remove a single block from the selection'},
        {keys: ['Ctrl', 'A'], description: 'Select all blocks (press again after selecting a block’s text)'},
        {keys: ['Ctrl', 'C'], description: 'Copy the selected blocks'},
        {keys: ['Ctrl', 'X'], description: 'Cut the selected blocks'},
        {keys: ['Ctrl', 'V'], description: 'Paste blocks after the selection'},
        {keys: ['Ctrl', 'D'], description: 'Duplicate the selected blocks'},
        {keys: ['Delete'], description: 'Delete the selected blocks (Backspace does the same)'},
        {keys: ['Esc'], description: 'Clear the selection'},
    ];

    #setupComplete = false;
    eventEmitter;
    blocks;
    #ids = [];

    constructor({eventEmitter, blocks}) {
        this.eventEmitter = eventEmitter;
        this.blocks = blocks;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#listenToEvents();
        // Capturing, so a handler that stops propagation on the way up can't strand a
        // selection that is no longer true.
        document.addEventListener('mousedown', this.#handleDocumentMouseDown, true);
        this.#setupComplete = true;
    }

    // Clicking anywhere outside the canvas ends the selection: the user has moved on, and
    // leaving blocks highlighted would claim a selection that no longer drives anything.
    // Clicks inside are left alone — landing on a block fires blockFocused, which clears it,
    // and a modified click is the gesture that builds the selection in the first place.
    #handleDocumentMouseDown = (e) => {
        if (!this.#ids.length) {
            return;
        }
        const content = this.blocks ? this.blocks.contentContainer : null;
        if (content && content.contains(e.target)) {
            return;
        }
        this.clear();
    }

    #listenToEvents() {
        // A block taking focus means a plain click — that ends the selection. Modified clicks
        // never get here: they preventDefault(), so focus never moves.
        this.eventEmitter.on(events.blockFocused, () => {
            this.clear();
        });
        // Members can be destroyed by any path (delete, a container cascade, an undo). Drop
        // just that id — leaving it would let copy/delete reach a block that no longer exists.
        this.eventEmitter.on(events.blockDeleted, (block) => {
            if (this.#ids.includes(block.id)) {
                this.#apply(this.#ids.filter((id) => id !== block.id));
            }
        });
    }

    // Ctrl/Cmd+click: pick out individual blocks.
    // With a block still active it is the first thing selected, so the block the user was on
    // plus the one they just clicked both end up in the list. Ctrl+clicking the active block
    // itself does nothing — there is no second block, so there is nothing to select.
    toggle(id) {
        if (!this.#isSelectable(id)) {
            return;   // hidden/system blocks (footnotes, unknown) aren't part of a selection
        }
        const active = this.blocks.activeBlock;
        if (active) {
            if (active.id === id) {
                return;
            }
            this.#apply([active.id, id]);
            return;
        }
        this.#apply(this.#ids.includes(id)
            ? this.#ids.filter((selected) => selected !== id)
            : [...this.#ids, id]);
    }

    // Shift+click: select everything between the anchor and the clicked block. The anchor is
    // the active block, or the last block selected if the selection is already running.
    // Restricted to siblings of one container — a range that spans containers has no meaning.
    extendTo(targetId) {
        const anchorId = this.blocks.activeBlock
            ? this.blocks.activeBlock.id
            : this.#ids[this.#ids.length - 1];
        if (!anchorId) {
            return;
        }
        const anchorElement = this.blocks.blockElementFromId(anchorId);
        const targetElement = this.blocks.blockElementFromId(targetId);
        if (!anchorElement || !targetElement
            || anchorElement.parentElement !== targetElement.parentElement) {
            return;
        }
        const siblings = [...anchorElement.parentElement.querySelectorAll(
            `:scope > [${contentEditorSelectors.attributes.blockId}]`
        )];
        const from = siblings.indexOf(anchorElement);
        const to = siblings.indexOf(targetElement);
        if (from === -1 || to === -1) {
            return;
        }
        this.#apply(siblings
            .slice(Math.min(from, to), Math.max(from, to) + 1)
            .map((element) => element.getAttribute(contentEditorSelectors.attributes.blockId)));
    }

    // Every top-level block. Hidden ones (footnotes) are left out: they can't be copied,
    // cut or deleted, so highlighting them would promise something that doesn't work.
    selectAll() {
        const container = this.blocks.contentContainer;
        if (!container) {
            return;
        }
        const ids = [...container.querySelectorAll(
            `:scope > [${contentEditorSelectors.attributes.blockId}]`
        )]
            .map((element) => element.getAttribute(contentEditorSelectors.attributes.blockId))
            .filter((id) => {
                const block = this.blocks.blocks.get(id);
                return block && !block.constructor.hidden;
            });
        if (ids.length) {
            this.#apply(ids);
        }
    }

    // Select an explicit set — used after duplicating, so the new copies come out selected.
    select(ids) {
        if (ids && ids.length) {
            this.#apply(ids);
        }
    }

    clear() {
        if (!this.#ids.length) {
            return;   // nothing to undo, and no event worth firing
        }
        this.#unpaint();
        this.#ids = [];
        this.eventEmitter.emit(selectionEvents.selectionChanged, []);
    }

    // The single chokepoint that keeps hidden/system blocks out of any selection — a shift+click
    // range can span one (a footnotes block between two paragraphs), and the active anchor could
    // itself be hidden, so filtering here covers every entry path at once.
    #isSelectable(id) {
        const block = this.blocks.blocks.get(id);
        return !!block && !block.constructor.hidden;
    }

    #apply(ids) {
        // A selection only exists to drive copy/cut/delete/duplicate, and all of those are
        // refused in read-only — so don't let one form at all. Guarding here covers every
        // entry point at once: shift+click, ctrl+click, select-all and arrow navigation.
        if (this.blocks.isReadOnly()) {
            return;
        }
        this.#unpaint();
        this.#ids = this.#inDomOrder(ids.filter((id) => this.#isSelectable(id)));
        this.#paint();
        if (this.#ids.length) {
            // The selection takes over from single-block focus — see the class comment.
            this.blocks.deactivateActiveBlock();
        }
        this.eventEmitter.emit(selectionEvents.selectionChanged, this.getBlocks());
    }

    // compareDocumentPosition rather than an index within one parent, so blocks picked out of
    // different containers still come back in document order.
    #inDomOrder(ids) {
        return [...new Set(ids)].sort((a, b) => {
            const first = this.blocks.blockElementFromId(a);
            const second = this.blocks.blockElementFromId(b);
            if (!first || !second) {
                return 0;
            }
            return (first.compareDocumentPosition(second) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1;
        });
    }

    #paint() {
        this.#eachElement((element) => {
            element.classList.add(contentEditorSelectors.classes.blockSelected);
        });
    }

    #unpaint() {
        this.#eachElement((element) => {
            element.classList.remove(contentEditorSelectors.classes.blockSelected);
        });
    }

    #eachElement(callback) {
        this.#ids.forEach((id) => {
            const element = this.blocks.blockElementFromId(id);
            if (element) {
                callback(element);
            }
        });
    }

    getIds() {
        return [...this.#ids];
    }

    // Resolved fresh each call, in DOM order, skipping anything already gone.
    getBlocks() {
        return this.#ids.map((id) => this.blocks.blocks.get(id)).filter(Boolean);
    }

    size() {
        return this.#ids.length;
    }

    has(id) {
        return this.#ids.includes(id);
    }

    destroy() {
        // Before the refs it depends on are dropped.
        document.removeEventListener('mousedown', this.#handleDocumentMouseDown, true);
        this.#unpaint();
        this.#ids = [];
        this.eventEmitter = null;
        this.blocks = null;
        this.#setupComplete = false;
    }
}
