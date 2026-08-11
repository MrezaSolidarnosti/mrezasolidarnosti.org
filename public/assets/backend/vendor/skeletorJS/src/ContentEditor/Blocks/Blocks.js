import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BlockMenu from "./Components/BlockMenu/BlockMenu.js";
import {events as blockMenuEvents} from "./Components/BlockMenu/events.js";
import {events as contentEditorEvents} from "./../events.js";
import {events as sidebarEvents} from "./../Sidebar/events.js";
import BlockSideToggle from "./Components/BlockSideToggle/BlockSideToggle.js";
import {events as blockSideToggleEvents} from "./Components/BlockSideToggle/events.js";
import FormatToolbar from "./Components/FormatToolbar/FormatToolbar.js";
import {parsePaste, parsePlainText} from "./Components/Paste/parsePaste.js";
import Paragraph from "./Paragraph/Paragraph.js";
import Unknown from "./Unknown/Unknown.js";
import Overview from "./Components/Overview/Overview.js";
import History from "./Components/History/History.js";
import BlockInserter from "./Components/BlockInserter/BlockInserter.js";
import BlockGapInserter from "./Components/BlockGapInserter/BlockGapInserter.js";
import Footnotes from "./Components/Footnotes/Footnotes.js";
import {remintFootnoteIds} from "./Components/Footnotes/remintFootnoteIds.js";
import {withBlockDefaults} from "./blockDefaults.js";
import EntityTriggers from "./Components/EntityTriggers/EntityTriggers.js";
import Message from "../../Message/Message.js";
import BlockSelection from "./Components/BlockSelection/BlockSelection.js";
import Translator from "../../Translator/Translator.js";

// A private clipboard format for copied blocks. The clipboard carries several formats at
// once, so this rides alongside the text/plain written with it: this editor reads the block
// payload, everything else (other apps, other tabs) just sees the text.
const BLOCK_CLIPBOARD_MIME = 'application/x-skeletor-blocks';

export default class Blocks {

    static PASTE_HANDLERS = new Map();

    static registerPasteHandler(definition) {
        if (!definition || !definition.key || typeof definition.handle !== 'function') {
            throw new Error('A paste handler needs a `key` and a `handle` function.');
        }
        Blocks.PASTE_HANDLERS.set(definition.key, definition);
    }

    static unRegisterPasteHandler(key) {
        Blocks.PASTE_HANDLERS.delete(key);
    }

    #setupComplete = false;
    config;
    blockModules = new Map();
    blocks = new Map();
    #nextAvailableBlockId = 0;
    #nextCopiedFootnoteId = 0;
    contentContainer;
    eventEmitter;
    activeBlock = null;
    beforeActiveBlock = null;
    afterActiveBlock = null;
    blockMenu;
    blockSideToggle;
    #blockQueuedForInsertion = false;
    formatToolbar;
    #tagToBlockName = new Map();
    overviewHandler;
    historyHandler;
    blockInserter;
    blockGapInserter;
    footnotesHandler;
    entityTriggersHandler;
    blockSelection;
    #pastePlainText = false;   // armed by Ctrl/Cmd+Shift+V keydown, consumed by the paste event
    readOnly;
    #contentObserver = null;
    #contentChangedTimeout = null;

    static BLOCK_SPACES = {
        CORE: 'core/',
        APP: 'app/'
    }

    static CONTENT_CHANGE_DEBOUNCE = 300;

    constructor({config, eventEmitter}) {
        this.config = config;
        this.eventEmitter = eventEmitter;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();
        this.#addListeners();
        this.#listenToEvents();
        this.#setModules();

        this.#setupComplete = true;
    }

    #setElements() {
        this.contentContainer = document.getElementById(contentEditorSelectors.ids.contentContainer);
        // Focusable so that a block selection has somewhere to park focus: nothing is
        // editable there, but keydown and copy still reach the listeners bound below.
        this.contentContainer.tabIndex = -1;
    }

    #setModules() {
        this.blockSideToggle = new BlockSideToggle({eventEmitter: this.eventEmitter, readOnly: this.readOnly});
        this.blockSideToggle.init();
        this.formatToolbar = new FormatToolbar({eventEmitter: this.eventEmitter, readOnly: this.readOnly});
        this.formatToolbar.init();
        this.historyHandler = new History({eventEmitter: this.eventEmitter, blocks: this});
        this.historyHandler.init();
        this.footnotesHandler = new Footnotes({eventEmitter: this.eventEmitter, blocks: this});
        this.footnotesHandler.init();
        this.entityTriggersHandler = new EntityTriggers({eventEmitter: this.eventEmitter});
        this.entityTriggersHandler.init();
        this.blockSelection = new BlockSelection({eventEmitter: this.eventEmitter, blocks: this});
        this.blockSelection.init();
    }

    insertFootnote() {
        if(this.isReadOnly()) return;
        this.footnotesHandler.insert();
    }

    // Whether a block type is in this instance's config.blocks — so a feature can gate itself
    // on a block being available (e.g. the footnote command only shows when core/footnotes is).
    isBlockRegistered(name) {
        return this.blockModules.has(name);
    }

    undo() {
        this.historyHandler.undo();
    }

    redo() {
        this.historyHandler.redo();
    }

    #addListeners() {
        this.contentContainer.addEventListener('keydown', this.#handleOnKeydown);
        this.contentContainer.addEventListener('focusin', this.#handleFocusIn);
        this.contentContainer.addEventListener('paste', this.#handlePaste);
        this.contentContainer.addEventListener('copy', this.#handleCopy);
        this.contentContainer.addEventListener('cut', this.#handleCut);
        this.contentContainer.addEventListener('mousedown', this.#handleMouseDown);
        this.contentContainer.addEventListener('click', this.#handleClick);
    }

    // Ctrl+C with a range selected is an ordinary text copy and the browser owns it. With
    // nothing selected there is no text to copy, so the intent is the focused block itself.
    #handleCopy = (e) => {
        const blocks = this.#blocksForClipboard();
        if (!blocks || !this.#writeBlocksToClipboard(e, blocks)) {
            return;
        }
        this.#notifyClipboard(blocks, 'Copied');
    }

    // Cut only ever applies to a block *selection*. Falling back to the focused block the way
    // copy does would mean an idle caret plus Ctrl+X silently deletes a block — destructive,
    // where a mistaken copy is merely surprising.
    #handleCut = (e) => {
        if (this.isReadOnly() || !this.blockSelection.size()) {
            return;
        }
        const blocks = this.#blocksForClipboard();
        if (!blocks || !this.#writeBlocksToClipboard(e, blocks)) {
            return;
        }
        this.#notifyClipboard(blocks, 'Cut');
        this.#deleteSelection();   // only once the payload is safely on the clipboard
    }

    // What a copy/cut should carry, or null to let the browser handle the event itself.
    #blocksForClipboard() {
        // A field owns its own copy, and window.getSelection() can't see inside one — without
        // this, selecting text in a block's input would look like "nothing selected".
        const focused = document.activeElement;
        if (focused && (focused.tagName === 'INPUT' || focused.tagName === 'TEXTAREA')) {
            return null;
        }
        const selection = window.getSelection();
        if (selection && !selection.isCollapsed) {
            return null;
        }
        // A selection carries every block in it; otherwise it's the focused block. Hidden
        // blocks (footnotes) are auto-managed and must stay unique, so they never travel.
        const blocks = (this.blockSelection.size()
            ? this.blockSelection.getBlocks()
            : [this.activeBlock]).filter((block) => block && !block.constructor.hidden);
        return blocks.length ? blocks : null;
    }

    #writeBlocksToClipboard(e, blocks) {
        const clipboard = e.clipboardData || window.clipboardData;
        if (!clipboard) {
            return false;
        }
        e.preventDefault();
        clipboard.setData(BLOCK_CLIPBOARD_MIME, JSON.stringify({
            version: 1,
            blocks: blocks.map((block) => this.#stripIds(structuredClone(this.#serializeBlock(block)))),
        }));
        // Whatever can't read the format above still gets something meaningful.
        clipboard.setData('text/plain', blocks
            .map((block) => block.getContainer().textContent.trim())
            .filter(Boolean)
            .join('\n\n'));
        return true;
    }

    // Shift+click selects the range, Ctrl/Cmd+click picks out individual blocks. Handled on
    // mousedown, not click: focus would otherwise move to the clicked block first, making it
    // the active block and destroying the very anchor the selection is measured from.
    // preventDefault() stops that, so blockFocused never fires and the selection survives.
    #handleMouseDown = (e) => {
        if (this.isReadOnly() || (!e.shiftKey && !e.ctrlKey && !e.metaKey)) {
            return;
        }
        const element = e.target.closest
            ? e.target.closest(`[${contentEditorSelectors.attributes.blockId}]`)
            : null;
        if (!element || !this.contentContainer.contains(element)) {
            return;
        }
        e.preventDefault();
        const id = element.getAttribute(contentEditorSelectors.attributes.blockId);
        if (e.shiftKey) {
            this.blockSelection.extendTo(id);
        } else {
            this.blockSelection.toggle(id);
        }
        // preventDefault() above stops the browser moving focus, which is the point — but it
        // also stops focus *entering* the editor. The keydown and copy listeners live on
        // #content and only fire while focus is inside it, so a selection made while focus
        // was elsewhere (having clicked outside and come back) would be inert: no Delete, no
        // Ctrl+C. Park focus here ourselves. Only when a selection actually resulted, so
        // ctrl+clicking the active block still does nothing at all.
        if (this.blockSelection.size() && !this.contentContainer.contains(document.activeElement)) {
            this.contentContainer.focus();
        }
    }

    // Selecting blocks takes over from single-block focus entirely, so the active block is
    // stood down: its highlight goes, its sidebar is cleared, and focus is parked on the
    // canvas itself. Parking matters — focus has to stay somewhere inside #content, or the
    // keydown and copy listeners bound to it would stop receiving anything at all.
    deactivateActiveBlock() {
        if (!this.activeBlock) {
            return;
        }
        const element = this.activeBlock.getContainer();
        element.classList.remove(contentEditorSelectors.classes.focused);
        if (document.activeElement && element.contains(document.activeElement)) {
            // Drop any text selection inside the block — e.g. the select-all-text that Ctrl+A
            // made just before widening to blocks — so it doesn't stay highlighted under the
            // block selection. Then park focus on the canvas so keydown/copy still land.
            const textSelection = window.getSelection();
            if (textSelection) {
                textSelection.removeAllRanges();
            }
            this.contentContainer.focus();
        }
        this.activeBlock = null;
        this.beforeActiveBlock = null;
        this.afterActiveBlock = null;
        this.eventEmitter.emit(events.blockBlurred);
    }

    // Ctrl+A escalates rather than hijacking. The decision hangs on one thing: is there
    // editable text under the caret right now? A text block, an accordion body or a table
    // cell all say yes; an image/gallery/divider — focused as a whole, no caret — says no.
    // With editable text, the first press is the browser's own "select all of it" and only a
    // second press (or an empty region, nothing to step through) widens to every block. With
    // no editable text there is nothing to step through, so it widens at once.
    #shouldSelectAllBlocks() {
        if (!this.activeBlock) {
            return true;   // a selection is running, or focus is parked on the canvas
        }
        const editable = this.#editableUnderCaret();
        if (!editable) {
            return true;   // a non-text block is focused as a whole — nothing to select within
        }
        const editableText = editable.textContent.trim();
        if (editableText === '') {
            return true;   // empty editable — nothing to step through
        }
        const selection = window.getSelection();
        if (selection.isCollapsed) {
            return false;  // just a caret — let the browser select this text first
        }
        return selection.toString().trim() === editableText;
    }

    // The editable region the caret sits in (text block, table cell, accordion body …), or
    // null when focus is on a non-editable block. Every editable area sets contentEditable,
    // so that is the one signal that covers them all.
    #editableUnderCaret() {
        const selection = window.getSelection();
        if (!selection || !selection.anchorNode) {
            return null;
        }
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE
            ? selection.anchorNode.parentElement
            : selection.anchorNode;
        const editable = node ? node.closest('[contenteditable="true"]') : null;
        return editable && this.contentContainer.contains(editable) ? editable : null;
    }

    // Duplicates the selection, or the focused block when there is no selection. The copies
    // land after the last original and end up selected, so they can be moved or deleted
    // straight away — the same courtesy as duplicating one block and landing on it.
    #duplicateSelection() {
        if (this.isReadOnly()) {
            return;
        }
        const selected = this.blockSelection.getBlocks().filter((block) => !block.constructor.hidden);
        if (!selected.length) {
            if (this.activeBlock && !this.activeBlock.constructor.hidden) {
                this.#duplicateBlock(this.activeBlock.id);
            }
            return;
        }
        let reference = selected[selected.length - 1].getContainer();
        // Copies go after the last selected block — but when the selection spans containers there
        // is no sensible home inside any of them: inserting after the last block would bury the
        // whole group in whichever column that one happened to live in, including the copies of
        // blocks that were never in a column. Hoist to the top level in that case, so the group
        // lands after the container block rather than inside it. A selection wholly within one
        // column still duplicates into that column, which is what it looks like it should do.
        const containers = new Set(selected.map((block) => this.#containerOf(block.getContainer())));
        if (containers.size > 1) {
            reference = this.#topLevelAncestor(reference);
        }
        const created = [];
        selected.forEach((block) => {
            const data = this.#stripIds(structuredClone(this.#serializeBlock(block)));
            const rendered = this.renderBlock(data.type, data, reference, 'after', false);
            if (rendered) {
                created.push(rendered.id);
                reference = rendered.getContainer();
            }
        });
        if (created.length) {
            this.blockSelection.select(created);
        }
    }

    // Arrow-key navigation between blocks. Moves the selection to the previous/next sibling,
    // starting from the edge of the current selection or a focused non-text block. Returns
    // false — leaving the arrow key to the browser — when a text block has the caret (its own
    // line-by-line movement) or there's no adjacent block to move to.
    #navigateBlocks(delta) {
        // The block to move from: whatever's focused, or — if a selection took focus — its
        // edge (up from the first, down from the last).
        let fromElement = null;
        if (this.activeBlock) {
            fromElement = this.activeBlock.getContainer();
        } else if (this.blockSelection.size()) {
            const ids = this.blockSelection.getIds();
            fromElement = this.blockElementFromId(delta > 0 ? ids[ids.length - 1] : ids[0]);
        }
        if (!fromElement) {
            return false;   // nothing to navigate from
        }
        let element = fromElement;
        while (element) {
            element = delta > 0 ? this.nextBlockElement(element) : this.previousBlockElement(element);
            if (!element) {
                return false;   // reached the first/last block in this container
            }
            const id = element.getAttribute(contentEditorSelectors.attributes.blockId);
            const block = id ? this.blocks.get(id) : null;
            // Skip hidden/system blocks (footnotes). Focus the target rather than selecting it:
            // focus() moves the caret into it, fires blockFocused (which also clears any
            // selection), and makes it the active block — ready to edit.
            if (block && !block.constructor.hidden) {
                block.focus();
                element.scrollIntoView({block: 'nearest'});
                return true;
            }
        }
        return false;
    }

    // Focus the first top-level, non-hidden block — e.g. after Enter in the title. Hidden/system
    // blocks (footnotes) are skipped. Returns whether it found one to focus.
    /**
     * Focus one block by its id.
     *
     * Focusing goes through the block's own `focus()`, so the focusin handler picks it up and the
     * active block, side toggle and sidebar all follow exactly as they would for a click — there
     * is nothing extra to keep in sync here.
     *
     * It scrolls too, without doing anything about it: every block's focus() bottoms out in an
     * element focus() — a table cell included, via #focusCell — and the platform scrolls a
     * focused element into view by default.
     *
     * Returns `false` when no block holds that id: one deleted since the caller last looked, or
     * an id from a stale payload. Callers that jump to a block (a validation message pointing at
     * the offending one, a deep link, the overview) need to be able to tell rather than fail
     * silently.
     *
     * @param {string} blockId
     * @returns {boolean} whether a block with that id was found.
     */
    focusBlock(blockId) {
        // Coerced for the same reason renderBlock mints string ids: the id round-trips through a
        // DOM attribute, so a caller holding a numeric 1 would never match the entry stored as "1".
        const block = this.blocks.get(String(blockId));
        if (!block) {
            return false;
        }
        block.focus();
        return true;
    }

    focusFirstBlock() {
        const blockElements = this.contentContainer.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`);
        for (const element of blockElements) {
            const block = this.blockFromBlockElement(element);
            if (block && !block.constructor.hidden) {
                block.focus();
                element.scrollIntoView({block: 'nearest'});
                return true;
            }
        }
        return false;
    }

    // The selected blocks go in one managed operation. Done naively, the per-block delete
    // handler would refocus a neighbour after every removal (and call renderInitial() the
    // moment the canvas empties). One synchronous batch also collapses into a single undo
    // entry, because contentChanged is debounced.
    #deleteSelection() {
        if (this.isReadOnly()) {
            return;
        }
        const selected = this.blockSelection.getBlocks().filter((block) => !block.constructor.hidden);
        if (!selected.length) {
            return;
        }
        // Clear first: every destroy() emits blockDeleted, which the selection prunes on.
        this.blockSelection.clear();
        this.#blockQueuedForInsertion = true;
        [...selected].reverse().forEach((block) => block.destroy());
        this.#blockQueuedForInsertion = false;
        if (this.getBlockCount() === 0) {
            this.renderInitial();
        }
    }

    // Copying a block changes nothing on screen, so the toast is the only sign it worked.
    #notifyClipboard(blocks, verb) {
        const container = document.getElementById(contentEditorSelectors.ids.messagesContainer);
        if (!container) {
            return;
        }
        // Whole sentences rather than a verb glued onto a suffix: word order and the verb's
        // form both move between languages, so each message has to reach the catalogue intact.
        const templates = verb === 'Cut'
            ? {one: 'Cut "%s" to clipboard', many: 'Cut %n blocks to clipboard'}
            : {one: 'Copied "%s" to clipboard', many: 'Copied %n blocks to clipboard'};
        Message.spawn({
            message: blocks.length === 1
                ? Translator.translate(templates.one)
                    .replace('%s', Translator.translate(blocks[0].constructor.label))
                : Translator.translate(templates.many).replace('%n', blocks.length),
            type: Message.TYPES.SUCCESS,
            view: {
                type: Message.VIEW_TYPES.NOTIFICATION,
                container,
                prepend: false,
            },
            ephemeralTimeout: 2000,
        });
    }

    #handleClick = (e) => {
        if (e.target === this.contentContainer) {
            this.#handleRootClick(e);
            return;
        }
        // Column: only adds a paragraph when the column is completely empty.
        if (e.target.hasAttribute(contentEditorSelectors.attributes.blockContainer)
            && !e.target.querySelector(`:scope > [${contentEditorSelectors.attributes.blockId}]`)) {
            this.renderBlock(Paragraph.name, {}, null, 'end', true, e.target);
        }
    }

    // Clicking the empty area *below the last block* drops a trailing paragraph to type
    // in — unless the last block is already an empty paragraph, then just focus that one.
    // Clicks in the gaps between/above blocks do nothing.
    #handleRootClick(e) {
        const blockElements = this.contentContainer.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`);
        const lastElement = blockElements[blockElements.length - 1];
        if (lastElement && e.clientY <= lastElement.getBoundingClientRect().bottom) {
            return;   // clicked between/above blocks, not below the last one
        }
        const lastBlock = lastElement ? this.blockFromBlockElement(lastElement) : null;
        if (lastBlock && lastBlock.constructor === Paragraph && lastBlock.getData().html === '') {
            lastBlock.focus();
            return;
        }
        this.renderBlock(Paragraph.name, {}, null, 'end', true, this.contentContainer);
    }

    #handlePaste = (e) => {
        if (this.isReadOnly()) {
            e.preventDefault();   // nothing may enter the canvas, including a native text paste
            return;
        }
        const clipboard = e.clipboardData || window.clipboardData;
        if (!clipboard) {
            return;
        }
        // Ctrl/Cmd+Shift+V (flagged on keydown, consumed here): paste the clipboard's plain
        // text only — strip all formatting and ignore a copied-block payload.
        const plainOnly = this.#pastePlainText;
        this.#pastePlainText = false;

        // Blocks copied from this editor come back as whole blocks, whatever is focused — so
        // this runs before the text-block guard below. Skipped for a plain-text paste.
        if (!plainOnly) {
            const blockPayload = clipboard.getData(BLOCK_CLIPBOARD_MIME);
            if (blockPayload) {
                e.preventDefault();
                this.#pasteBlocks(blockPayload);
                return;
            }
            if (this.#runPasteHandlers(e, clipboard)) {
                return;
            }
        }
        if (!this.activeBlock || !this.activeBlock.constructor.isText) {
            return;
        }
        e.preventDefault();
        // Plain-text paste forces the text/plain path; otherwise prefer text/html.
        const html = plainOnly ? '' : clipboard.getData('text/html');
        const pieces = html ? parsePaste(html) : parsePlainText(clipboard.getData('text/plain'));
        if (pieces.length === 0) {
            return;
        }
        this.#insertPastedPieces(pieces);
    }


    // Give each registered handler a chance to claim the paste. The first one to return truthy
    // wins and the default parsing is skipped. A handler that throws is reported and treated as
    // "not mine", so one bad handler can't break pasting altogether.
    #runPasteHandlers(e, clipboard) {
        if (!Blocks.PASTE_HANDLERS.size) {
            return false;
        }
        const context = {
            text: clipboard.getData('text/plain'),
            html: clipboard.getData('text/html'),
            block: this.activeBlock,
            blocks: this,
        };
        for (const definition of Blocks.PASTE_HANDLERS.values()) {
            let handled = false;
            try {
                handled = definition.handle(context);
            } catch (error) {
                console.error(`ContentEditor: paste handler "${definition.key}" threw.`, error);
            }
            if (handled) {
                e.preventDefault();
                return true;
            }
        }
        return false;
    }

    // Blocks pasted from the clipboard. The payload is user-controlled — it's only clipboard
    // text, and anyone can craft it — so every entry is checked against the registered block
    // types before it's rendered. Ids are re-minted (#stripIds): reusing them would give two
    // blocks the same identity, which breaks reconcile, undo/redo and revision diffing.
    #pasteBlocks(raw) {
        let payload = null;
        try {
            payload = JSON.parse(raw);
        } catch (e) {
            return;
        }
        // Where the paste lands: after the focused block, or — when a selection has taken
        // over and there is no focused block — after the last block in it. Without this it
        // would fall through to the end of the canvas, nowhere near what the user is looking at.
        const selected = this.blockSelection.getBlocks();
        const anchorBlock = this.activeBlock || (selected.length ? selected[selected.length - 1] : null);
        const nested = anchorBlock
            && this.#containerOf(anchorBlock.getContainer()) !== this.contentContainer;
        const entries = (Array.isArray(payload?.blocks) ? payload.blocks : []).filter((entry) => {
            if (!entry || typeof entry.type !== 'string' || !this.blockModules.has(entry.type)) {
                return false;
            }
            const module = this.blockModules.get(entry.type);
            if (module.hidden) {
                return false;   // auto-managed blocks are never pasted in by hand
            }
            // Columns can't nest, so a container block dropped inside a column is skipped.
            return !(nested && typeof module.prototype.getChildContainers === 'function');
        });
        if (!entries.length) {
            return;
        }
        // An empty text block is a placeholder, not content — the paste takes its place
        // instead of leaving a blank line above. The isText check matters: a divider or an
        // image has no text either, and those must not be replaced.
        const emptyAnchor = this.activeBlock
            && this.activeBlock.constructor.isText
            && this.#isActiveBlockEmpty()
                ? this.activeBlock
                : null;
        let lastBlock = null;

        if (emptyAnchor) {
            // Render each block before the placeholder (which keeps their order), then drop
            // it. Flagged as a managed insertion so the removal doesn't refocus or re-render.
            const anchorElement = emptyAnchor.getContainer();
            this.#blockQueuedForInsertion = true;
            entries.forEach((entry) => {
                const data = this.#stripIds(entry);
                lastBlock = this.renderBlock(data.type, data, anchorElement, 'before') || lastBlock;
            });
            emptyAnchor.destroy();
            this.#blockQueuedForInsertion = false;
        } else {
            // After the anchor (focused block, or the last one selected); only with neither
            // does it go to the end of the canvas. Each block is the reference for the next,
            // so a multi-block payload keeps its order.
            let reference = anchorBlock ? anchorBlock.getContainer() : null;
            entries.forEach((entry) => {
                const data = this.#stripIds(entry);
                const rendered = reference
                    ? this.renderBlock(data.type, data, reference, 'after')
                    : this.renderBlock(data.type, data, null, 'end');
                if (rendered) {
                    lastBlock = rendered;
                    reference = rendered.getContainer();
                }
            });
        }

        if (lastBlock) {
            lastBlock.focus();
        }
    }

    #insertPastedPieces(pieces) {
        if (pieces.length === 1 && pieces[0].tag === 'p') {
            document.execCommand('insertHTML', false, pieces[0].html);
            this.#exitTrailingInline();
            return;
        }
        const emptyAnchor = this.#isActiveBlockEmpty() ? this.activeBlock : null;
        let lastBlock = null;
        if (emptyAnchor) {
            const anchorElement = emptyAnchor.getContainer();
            this.#blockQueuedForInsertion = true;
            pieces.forEach((piece) => {
                lastBlock = this.#renderPiece(piece, anchorElement, 'before');
            });
            emptyAnchor.destroy();
            this.#blockQueuedForInsertion = false;
        } else {
            let referenceElement = this.activeBlock.getContainer();
            pieces.forEach((piece) => {
                lastBlock = this.#renderPiece(piece, referenceElement, 'after');
                referenceElement = lastBlock.getContainer();
            });
        }
        if (lastBlock) {
            lastBlock.focus();
        }
    }

    #renderPiece(piece, referenceElement, position) {
        const blockName = this.#tagToBlockName.get(piece.tag) || Paragraph.name;
        return this.renderBlock(blockName, {html: piece.html}, referenceElement, position);
    }

    #isActiveBlockEmpty() {
        return this.activeBlock.getContainer().textContent.trim() === '';
    }

    // Pasted HTML that ends in a superscript/subscript leaves the caret inside the
    // trailing tag, so typing would continue as sup/sub. Drop the caret just past it.
    #exitTrailingInline() {
        const selection = window.getSelection();
        if (!selection.rangeCount) {
            return;
        }
        const node = selection.anchorNode;
        const element = node && node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        const inline = element ? element.closest('sup, sub') : null;
        if (!inline) {
            return;
        }
        const range = document.createRange();
        range.setStartAfter(inline);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    #handleOnKeydown = (e) => {
        // Ctrl/Cmd+Shift+V — arm plain-text paste. The clipboard can only be read inside the
        // paste event (which fires next and carries no modifier state), so this just records
        // intent; #handlePaste reads and clears it. Don't preventDefault — the paste must fire.
        // A macrotask disarm covers the case where no paste follows (e.g. a non-editable block
        // held focus), so a later plain Ctrl+V isn't wrongly treated as plain-text.
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'v' || e.key === 'V')) {
            this.#pastePlainText = true;
            setTimeout(() => { this.#pastePlainText = false; }, 0);
        }
        if (e.key === 'Escape' && this.blockSelection.size()) {
            // This fires on #content, so it runs before the document-level Dismissible closer.
            // stopPropagation makes that precedence explicit: with a selection up, Escape only
            // clears it — it never also closes a panel in the same keystroke.
            e.stopPropagation();
            this.blockSelection.clear();
            return;
        }
        // Backspace is what most people reach for, so it removes a selection too. Only when
        // there *is* one — otherwise it stays the ordinary within-block Backspace.
        if ((e.key === 'Delete' || e.key === 'Backspace') && this.blockSelection.size()) {
            e.preventDefault();
            this.#deleteSelection();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'a' || e.key === 'A') && this.#shouldSelectAllBlocks()) {
            e.preventDefault();
            this.blockSelection.selectAll();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'd' || e.key === 'D')) {
            e.preventDefault();   // the browser would open its bookmark dialog
            this.#duplicateSelection();
            return;
        }
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            // While the slash menu is open the arrows belong to it (it moves the highlighted
            // item on its own listener). Swallow them here so they don't also move the caret or
            // jump to another block underneath.
            if (BlockMenu.isOpen()) {
                e.preventDefault();
                return;
            }
            // Up/Down move focus to the previous/next block. Only falls through to the browser
            // (native caret movement) when there's nothing to navigate to — the first/last block.
            if (this.#navigateBlocks(e.key === 'ArrowDown' ? 1 : -1)) {
                e.preventDefault();
                return;
            }
        }
        if(e.key === 'Delete') {
            if (!this.isReadOnly() && this.activeBlock && this.activeBlock.constructor.canBeDeleted()) {
                this.activeBlock.destroy();
            }
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if(!this.blockMenu) {
                if (this.activeBlock && this.#shouldExitColumn()) {
                    this.#exitColumnWithNewParagraph();
                    return;
                }
                if (this.#shouldOpenGapAbove()) {
                    // No focus() and no caret restore: inserting a preceding sibling does
                    // not disturb a selection inside this block, so the caret is already
                    // where the user left it. focus() would drag it to the end.
                    this.renderBlock(Paragraph.name, {}, this.activeBlock.getContainer(), 'before', false);
                    // Staying put means blockFocused never fires, so what it would have done
                    // has to be done by hand: the neighbours it recomputes (the block above
                    // is new) and the side toggle it repositions (the gap pushed this block
                    // down, leaving the toggle floating beside where it used to be).
                    this.#refreshActiveNeighbours();
                    this.eventEmitter.emit(blockSideToggleEvents.recalculateTogglePosition);
                    return;
                }
                let renderAfter = null;
                if(this.activeBlock) {
                    renderAfter = this.activeBlock.getContainer();
                }
                this.renderBlock(Paragraph.name, {}, renderAfter, 'after');
            }
        }
    }

    // Enter on the last, empty text block of a column exits the columns: delete that
    // paragraph and drop a new one right after the whole Columns block.
    /**
     * Enter at the very start of a text block that already has content: open an empty
     * paragraph above it rather than below, and stay put.
     *
     * Otherwise the first block of a document is a dead end — the only way to get anything
     * above it is to add a block below and drag it up. Lists never reach here: they stop
     * Enter from propagating so the browser can make a new <li>.
     */
    #shouldOpenGapAbove() {
        if (!this.activeBlock || this.activeBlock.constructor.isText !== true) {
            return false;
        }
        const element = this.activeBlock.getContainer();
        if (element.textContent.trim() === '') {
            return false;   // empty block — Enter keeps its usual "add one below" meaning
        }
        return this.#caretAtStartOf(element);
    }

    // Measured as "everything before the caret is empty" rather than offset === 0, which
    // only holds for a bare text node — a block starting with <strong> puts the caret at
    // offset 0 of a nested node, and one starting with <sup> can leave it after an element.
    #caretAtStartOf(element) {
        const selection = window.getSelection();
        if (!selection.rangeCount || !selection.isCollapsed) {
            return false;
        }
        const range = selection.getRangeAt(0);
        if (!element.contains(range.startContainer)) {
            return false;
        }
        const preceding = range.cloneRange();
        preceding.selectNodeContents(element);
        preceding.setEnd(range.startContainer, range.startOffset);
        return preceding.toString() === '';
    }

    #shouldExitColumn() {
        if (this.activeBlock.constructor.isText !== true || !this.#isActiveBlockEmpty()) {
            return false;
        }
        const activeElement = this.activeBlock.getContainer();
        const column = this.#containerOf(activeElement);
        if (column === this.contentContainer) {
            return false;
        }
        const blockElements = column.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`);
        return blockElements[blockElements.length - 1] === activeElement;
    }

    #exitColumnWithNewParagraph() {
        const column = this.#containerOf(this.activeBlock.getContainer());
        const columnsElement = column.closest(`[${contentEditorSelectors.attributes.blockId}]`);
        this.#blockQueuedForInsertion = true;
        this.activeBlock.destroy();
        this.#blockQueuedForInsertion = false;
        this.renderBlock(Paragraph.name, {}, columnsElement, 'after');
    }

    #handleFocusIn = (e) => {
        const blockElement = e.target.closest(`[${contentEditorSelectors.attributes.blockId}]`);
        if (!blockElement) {
            return;
        }
        const block = this.blocks.get(blockElement.getAttribute(contentEditorSelectors.attributes.blockId));
        if (!block) {
            // The element is no longer a tracked block — it can still be in the DOM while
            // being torn down (a block that removes its element after super.destroy()), and
            // removals move focus. Emitting here would hand every blockFocused listener an
            // undefined block.
            return;
        }
        this.eventEmitter.emit(events.blockFocused, block);
    }

    async loadBlockModules() {
        for (const block of this.config.blocks) {
            const blockScope = this.#getBlockScope(block);
            let blockModule = null;
            switch(blockScope) {
                case Blocks.BLOCK_SPACES.CORE:
                    blockModule = await this.#loadCoreBlockModule(block);
                    break;
                case Blocks.BLOCK_SPACES.APP:
                    blockModule = await this.#loadAppBlockModule(block);
                    break;
            }
            if(blockModule) {
                if(this.blockModules.has(block)) {
                    throw new Error(`ContentEditor block ${block} already registered.`);
                }
                this.blockModules.set(block, blockModule);
            }
        }
        this.#buildPasteTagMap();
        this.eventEmitter.emit(events.blocksReady);
        this.#initOverview();
    }

    // Maps each loaded block's declared `static tags` to its config name, so a
    // pasted HTML tag (p, h1, ul, ...) can be resolved to the block that owns it.
    #buildPasteTagMap() {
        this.blockModules.forEach((blockModule, blockName) => {
            (blockModule.tags || []).forEach((tag) => {
                if (!this.#tagToBlockName.has(tag)) {
                    this.#tagToBlockName.set(tag, blockName);
                }
            });
        });
    }


    async #loadCoreBlockModule(block) {
        const blockNameCapitalized = Blocks.getBlockNameCapitalized(block);
        try {
            const module = await import(`./${blockNameCapitalized}/${blockNameCapitalized}.js`);
            return module.default;
        } catch(e) {
            console.error(e);
            return null;
        }
    }

    async #loadAppBlockModule(block) {
        if(!this.config.appBlocksPath) {
            throw new Error('appBlocksPath is required in the config when using app scope blocks');
        }
        const blockNameCapitalized = Blocks.getBlockNameCapitalized(block);
        try {
            const module = await import (`${this.config.appBlocksPath}${blockNameCapitalized}/${blockNameCapitalized}.js`);
            return module.default;
        } catch(e) {
            console.error(e);
            return null;
        }
    }

    static getBlockNameCapitalized(block) {
        return block.split('/').pop().charAt(0).toUpperCase() + block.split('/').pop().slice(1);
    }

    // pointer-events alone only stops the mouse. A contentEditable element is still reachable
    // with Tab — and once focused, typing edits it — so read-only has to actually turn editing
    // off. Clearing contentEditable also drops the block out of the tab order, which is what
    // stops Tab walking the document and popping the side toggle open on each block.
    #makeUneditable(blockView) {
        blockView.style.pointerEvents = 'none';
        blockView.contentEditable = 'false';
        // Nested editables set their own contentEditable (a table, an accordion body), so
        // they don't inherit the false above and have to be switched off individually.
        blockView.querySelectorAll('[contenteditable="true"]').forEach((element) => {
            element.contentEditable = 'false';
        });
        blockView.querySelectorAll('svg').forEach((element) => {
           element.style.pointerEvents = 'none';
        });
        // Form controls inside a block (a select, a button) stay keyboard-reachable too.
        blockView.querySelectorAll('input, select, textarea, button').forEach((control) => {
            control.disabled = true;
        });
    }

    // A block's identity is stable: it's saved with the block and reused on load, so it
    // survives a round trip (and undo/redo). That lets revisions match blocks exactly — and
    // detect moves — instead of guessing by content similarity.
    #generateBlockId() {
        return `b-${Date.now().toString(36)}-${(this.#nextAvailableBlockId++).toString(36)}-${Math.random().toString(36).slice(2, 6)}`;
    }

    // A duplicate is a new block — it must not inherit the original's identity (nor its
    // nested children's).
    #stripIds(data) {
        this.#stripBlockIds(data);
        // A footnote marker's id identifies the note that belongs to it, so a copy that keeps it
        // points at the original's note — and the controller then builds a second, empty note
        // under the same id and numbers the wrong one. Reminted across the whole payload in one
        // pass with a shared mapping, so a marker appearing twice in one copy still resolves to
        // a single new note.
        remintFootnoteIds(data, () => this.#generateFootnoteId());
        return data;
    }

    #stripBlockIds(data) {
        delete data.id;
        if (Array.isArray(data.columns)) {
            data.columns.forEach((column) => column.forEach((child) => this.#stripBlockIds(child)));
        }
    }

    // The footnotes controller's own ids count from zero each session, so a copy minted here
    // gets a random segment as well — otherwise the two generators could collide.
    #generateFootnoteId() {
        return `fn-${Date.now().toString(36)}-${(this.#nextCopiedFootnoteId++).toString(36)}`
            + `-${Math.random().toString(36).slice(2, 6)}`;
    }

    // Human labels for the registered types, for anything that names a block to a user
    // rather than identifying it. Only types in config.blocks are here, so a caller has to
    // cope with a miss — saved content can hold a type the config has since dropped.
    getBlockLabels() {
        const labels = new Map();
        this.blockModules.forEach((blockModule, name) => labels.set(name, Translator.translate(blockModule.label)));
        return labels;
    }

    #getBlockModule(block) {
        return this.blockModules.get(block);
    }

    #getBlockScope(block) {
        if(block.includes(Blocks.BLOCK_SPACES.CORE)) {
            return Blocks.BLOCK_SPACES.CORE;
        }
        if(block.includes(Blocks.BLOCK_SPACES.APP)) {
            return Blocks.BLOCK_SPACES.APP;
        }
        throw new Error('Block scope not found');
    }

    // Only evict if this exact instance is still the one mapped. Ids are reused across a
    // replace (a History restore rebuilds a block under its persisted id), so a stale
    // instance destroyed after its replacement was mapped must not evict the replacement.
    #removeBlockFromMap(block) {
        if (this.blocks.get(block.id) === block) {
            this.blocks.delete(block.id);
        }
    }

    getBlockCount() {
        return this.blocks.size;
    }

    #initOverview() {
        this.overviewHandler = new Overview({eventEmitter: this.eventEmitter, blockModules: this.blockModules, readOnly: this.readOnly});
        this.overviewHandler.init();
        this.blockInserter = new BlockInserter({eventEmitter: this.eventEmitter, blocks: this, blockModules: this.blockModules, readOnly: this.readOnly});
        this.blockInserter.init();
        this.blockGapInserter = new BlockGapInserter({eventEmitter: this.eventEmitter, blocks: this, readOnly: this.readOnly});
        this.blockGapInserter.init();
    }

    renderBlock(block, data, referenceBlock = null, position = 'end', focus = true, container = this.contentContainer) {
        if(this.isReadOnly() && this.#isEmptyObject(data)) {
            return;
        }
        // After the read-only check above, which reads the payload's emptiness — merging first
        // would make an empty one look populated and change what read-only renders.
        data = withBlockDefaults(this.config.blockDefaults, block, data);
        let blockModule = this.#getBlockModule(block);
        if (!blockModule) {
            // Unregistered type (a stripped-down config, a failed import, a newer schema).
            // Dropping it would delete the content on the next save, so preserve it as an
            // inert Unknown block that re-emits the original entry verbatim.
            console.warn(`ContentEditor: "${block}" is not registered in config.blocks — preserving it as an unknown block.`);
            blockModule = Unknown;
            // Ensure the original type travels with the data so Unknown can show and re-save it.
            data = {...(data || {}), type: (data && data.type) ? data.type : block};
        }
        // Reuse the saved id when loading (or restoring); mint one for a genuinely new block.
        // Always a string: the id round-trips through a DOM attribute, so a numeric one would
        // come back as "1" and never match the map entry set under 1.
        const id = (data && data.id) ? String(data.id) : this.#generateBlockId();
        const blockObj = new blockModule({
            data,
            id,
            eventEmitter: this.eventEmitter,
            config: this.config
        });

        if(blockObj && data.additionalData) {
            blockObj.sidebarData = data.additionalData;
        }
        const blockView = blockObj.render();
        blockView.setAttribute(contentEditorSelectors.attributes.blockId, id);
        blockView.setAttribute(contentEditorSelectors.attributes.blockName, blockObj.constructor.name);
        if (data && data.align) {
            blockView.setAttribute(contentEditorSelectors.attributes.blockAlign, data.align);
        }
        if(this.isReadOnly()) {
            this.#makeUneditable(blockView);
        }

        if (data && data.html) {
            blockObj.setContent(data.html);
        }

        this.blocks.set(id, blockObj);

        if (position === 'prepend') {
            container.prepend(blockView);
        } else if (position === 'before' && referenceBlock) {
            referenceBlock.insertAdjacentElement('beforebegin', blockView);

        } else if (position === 'after' && referenceBlock) {
            referenceBlock.insertAdjacentElement('afterend', blockView);
        } else {
            container.appendChild(blockView);
        }

        // Animate only deliberate standalone inserts (Enter, duplicate, side-toggle insert):
        // focus:true, and not a managed operation. #blockQueuedForInsertion marks the managed
        // ones — a block-menu transform or replacing the initial empty paragraph both swap a
        // block in place, where a slide-in reads as wrong. Initial load, paste-restore, undo
        // and column children render with focus:false and are excluded anyway.
        if (focus && !this.isReadOnly() && !this.#blockQueuedForInsertion) {
            this.#animateInsert(blockView);
        }

        if (data && Array.isArray(data.columns) && typeof blockObj.getChildContainers === 'function') {
            const childContainers = blockObj.getChildContainers();
            data.columns.forEach((columnBlocks, index) => {
                const columnElement = childContainers[index];
                if (!columnElement) {
                    return;
                }
                columnBlocks.forEach((childData) => {
                    this.renderBlock(childData.type, childData, null, 'end', false, columnElement);
                });
            });
        }

        this.eventEmitter.emit(events.blockInserted, blockObj);
        if(focus) {
            blockObj.focus();
        }
        return blockObj;
    }

    // The block slides in via translateY, so its box moves every frame. Rather than position
    // the side toggle once (which then jumps when the block settles), re-anchor it each frame
    // for the animation's life so the two glide in together. Stops on animationend, if the
    // block is removed mid-slide, or after a safety cap — never a runaway rAF loop.
    #animateInsert(blockView) {
        blockView.classList.add(contentEditorSelectors.classes.blockInserting);
        let tracking = true;
        const stop = () => { tracking = false; };
        const track = () => {
            if (!tracking || !blockView.isConnected) {
                return;
            }
            this.eventEmitter.emit(blockSideToggleEvents.recalculateTogglePosition);
            requestAnimationFrame(track);
        };
        requestAnimationFrame(track);
        blockView.addEventListener('animationend', () => {
            stop();
            blockView.classList.remove(contentEditorSelectors.classes.blockInserting);
            this.eventEmitter.emit(blockSideToggleEvents.recalculateTogglePosition);   // final settle
        }, {once: true});
        setTimeout(stop, 400);   // safety: never track past the animation
    }


    #listenToEvents() {
        this.#listenToBlockFocused();
        this.#listenToBlockDeleted();
        this.#listenToRenderBlockMenu();
        this.#listenToBlockMenuRemoved();
        this.#listenToBlockMenuItemSelected();
        this.#listenToInsertBeforeAndAfter();
        this.#listenToSetActiveBlock();
        this.#listenToContentEditorFinalized();
        this.#listenToDeleteBLock();
        this.#listenToDuplicateBlock();
    }


    #listenToBlockFocused() {
        this.eventEmitter.on(events.blockFocused, (block) => {
            if(this.activeBlock) {
                this.activeBlock.getContainer().classList.remove(contentEditorSelectors.classes.focused);
            }
            this.activeBlock = block;
            this.activeBlock.getContainer().classList.add(contentEditorSelectors.classes.focused);
            this.#refreshActiveNeighbours();
            this.#handleBlockSidebar();
        });
    }

    #refreshActiveNeighbours() {
        const activeBlockElement = this.activeBlock ? this.blockElementFromId(this.activeBlock.id) : null;
        if(!activeBlockElement) {
            this.beforeActiveBlock = null;
            this.afterActiveBlock = null;
            return;
        }
        const previousBlockElement = this.previousBlockElement(activeBlockElement);
        this.beforeActiveBlock = previousBlockElement ? this.blockFromBlockElement(previousBlockElement) : null;
        const nextBlockElement = this.nextBlockElement(activeBlockElement);
        this.afterActiveBlock = nextBlockElement ? this.blockFromBlockElement(nextBlockElement) : null;
    }

    #listenToBlockDeleted() {
        this.eventEmitter.on(events.blockDeleted, (block) => {
            if(typeof block.getChildContainers === 'function') {
                this.#destroyDescendants(block);
            }
            if(this.activeBlock === block) {
                this.activeBlock = null;
            }
            if(!this.beforeActiveBlock?.getContainer().isConnected) {
                this.beforeActiveBlock = null;
            }
            if(!this.afterActiveBlock?.getContainer().isConnected) {
                this.afterActiveBlock = null;
            }
            if (!this.#blockQueuedForInsertion) {
                if (this.afterActiveBlock) {
                    this.afterActiveBlock.focus();
                } else if (this.beforeActiveBlock) {
                    this.beforeActiveBlock.focus();
                }
            }
            this.#removeBlockFromMap(block);
            if(this.getBlockCount() === 0 && !this.#blockQueuedForInsertion) {
                this.renderInitial();
            }
        });
    }

    #listenToRenderBlockMenu() {
        this.eventEmitter.on(contentEditorEvents.renderBlockMenu, ({block, textElement}) => {
            if (this.blockMenu) {
                this.blockMenu.destroy();
            }
            const nested = this.#containerOf(block.getContainer()) !== this.contentContainer;
            this.blockMenu = new BlockMenu({
                eventEmitter: this.eventEmitter,
                blocks: nested ? this.#nonContainerModules() : this.blockModules,
                block,
                textElement
            });
            this.blockMenu.render();
        });
    }

    // Container blocks (Columns) can't be nested, so they're excluded from the slash
    // menu when it's opened from inside a column.
    #nonContainerModules() {
        return new Map([...this.blockModules].filter(([, module]) => typeof module.prototype.getChildContainers !== 'function'));
    }

    #listenToBlockMenuRemoved() {
        this.eventEmitter.on(blockMenuEvents.blockMenuRemoved, () => {
            this.blockMenu = null;
        });
    }

    #listenToBlockMenuItemSelected() {
        this.eventEmitter.on(blockMenuEvents.blockMenuItemSelected, ({blockToAdd, oldBlock}) => {
            this.#blockQueuedForInsertion = true;
            const referenceElement = oldBlock.getContainer();
            const isFirst = this.#isFirstBlock(oldBlock);
            this.activeBlock = null;
            this.afterActiveBlock = null;
            this.beforeActiveBlock = null;
            if (isFirst) {
                this.renderBlock(blockToAdd, {}, null, 'prepend', true, this.#containerOf(referenceElement));
            } else {
                this.renderBlock(blockToAdd, {}, referenceElement, 'before');
            }
            oldBlock.destroy();
            this.#refreshActiveNeighbours();
            this.#blockQueuedForInsertion = false;
        });
    }


    #listenToInsertBeforeAndAfter() {
        this.eventEmitter.on(events.insertBefore, () => {
            if(!this.activeBlock) return;
            this.renderBlock(Paragraph.name, {}, this.activeBlock.getContainer(), 'before');
        });
        this.eventEmitter.on(events.insertAfter, () => {
            if(!this.activeBlock) return;
            this.renderBlock(Paragraph.name, {}, this.activeBlock.getContainer(), 'after');
        });
    }

    #listenToSetActiveBlock() {
        this.eventEmitter.on(events.setActiveBlock, (id) => {
           this.blocks.get(id)?.focus();
        });
    }

    #listenToContentEditorFinalized() {
        this.eventEmitter.on(contentEditorEvents.contentEditorFinalize, () => {
            this.overviewHandler.renderInitial();
            // Start observing only after the initial content is in place, so loading a
            // post doesn't report itself as a change.
            this.#startContentObserver();
        });
    }

    // The DOM is the source of truth, so one observer on #content notices every content
    // edit across all blocks — typing, paste, Enter, structural changes, and link/media
    // attribute edits — without instrumenting each block individually. Debounced so a
    // burst of mutations (e.g. a run of keystrokes) collapses into a single event.
    #startContentObserver() {
        this.#contentObserver = new MutationObserver(this.#handleContentMutation);
        this.#observeContent();
    }

    #observeContent() {
        this.#contentObserver.observe(this.contentContainer, {
            subtree: true,
            childList: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['href', 'target', 'rel', 'src', 'alt']
        });
    }

    // Runs a DOM-mutating routine (a history restore) without the observer turning it into
    // a contentChanged: disconnect, drop any pending debounce, run, then re-observe. The
    // rebuild's mutations happen while disconnected, so they are never queued.
    runWithoutObserving(fn) {
        if (this.#contentObserver) {
            this.#contentObserver.disconnect();
        }
        clearTimeout(this.#contentChangedTimeout);
        try {
            fn();
        } finally {
            if (this.#contentObserver) {
                this.#observeContent();
            }
        }
    }

    // Restores content from a serialized snapshot (undo/redo) by diffing against the live
    // DOM and only touching what changed — unchanged blocks keep their element, ids, scroll
    // position and (for embeds) avoid a reload. Observer paused so the rebuild isn't
    // recorded as a new change.
    restoreContent(blocksData) {
        this.runWithoutObserving(() => {
            const wasQueued = this.#blockQueuedForInsertion;
            this.#blockQueuedForInsertion = true;
            this.#destroyBlocksAbsentFrom(blocksData);
            this.#reconcileContainer(this.contentContainer, blocksData);
            this.#blockQueuedForInsertion = wasQueued;
            if (this.getBlockCount() === 0) {
                this.renderInitial();
            }
            if (this.activeBlock) {
                this.#refreshActiveNeighbours();
            }
        });
    }

    // Id-based reconcile: walk the snapshot in order, match each entry to its live block by
    // id, and place it. Matching by position instead would compare a reordered block against
    // whichever entry now sits at its index and "replace" it — re-creating a block whose id
    // is still live elsewhere, which silently steals that id's map entry.
    // Deletions are handled by #destroyBlocksAbsentFrom before this runs, so anything left
    // in a container that this pass doesn't claim is a block moving elsewhere in the tree,
    // and the pass that owns it will move it.
    #reconcileContainer(container, blocksData) {
        let anchor = null;   // last placed element; the next one goes directly after it

        blocksData.forEach((target) => {
            const element = this.#reconcileBlock(target, container);
            if (!element) {
                return;
            }
            this.#placeAfter(element, anchor, container);
            anchor = element;
        });
    }

    // Matches the target to its live block by id, so a block that merely moved is reused
    // (and repositioned by the caller) rather than re-created — re-creating it would mint a
    // second instance for an id that is still live, and the map can only hold one.
    #reconcileBlock(target, container) {
        const existing = target.id ? this.blocks.get(String(target.id)) : null;
        if (!existing || !existing.getContainer().isConnected) {
            const added = this.renderBlock(target.type, target, null, 'end', false, container);
            return added ? added.getContainer() : null;
        }
        if (existing.constructor.name !== target.type) {
            return this.#replaceBlock(existing, target, container);
        }
        if (typeof existing.getChildContainers === 'function') {
            // Own data unchanged → recurse, so an edit inside a column doesn't rebuild the
            // whole container (and its children keep their elements).
            if (JSON.stringify(existing.getBlockData()) !== JSON.stringify(this.#withoutColumns(target))) {
                return this.#replaceBlock(existing, target, container);
            }
            const childContainers = existing.getChildContainers();
            (target.columns || []).forEach((columnData, columnIndex) => {
                if (childContainers[columnIndex]) {
                    this.#reconcileContainer(childContainers[columnIndex], columnData);
                }
            });
            return existing.getContainer();
        }
        if (JSON.stringify(this.#serializeBlock(existing)) !== JSON.stringify(target)) {
            return this.#replaceBlock(existing, target, container);
        }
        return existing.getContainer();   // unchanged — leave it alone
    }

    // A move, not a re-insert: an element already in the right place is left untouched, so
    // reordering keeps every instance (and focus, scroll, embed iframes) intact. Moving one
    // in from another container also pulls it out of its old parent.
    #placeAfter(element, anchor, container) {
        const expected = anchor ? anchor.nextElementSibling : container.firstElementChild;
        if (element === expected) {
            return;
        }
        if (anchor) {
            anchor.insertAdjacentElement('afterend', element);
        } else {
            container.prepend(element);
        }
    }

    // Every block id in a snapshot, nested column children included.
    #collectSnapshotIds(blocksData, into = new Set()) {
        blocksData.forEach((data) => {
            if (data.id) {
                into.add(String(data.id));
            }
            (data.columns || []).forEach((columnData) => this.#collectSnapshotIds(columnData, into));
        });
        return into;
    }

    // Blocks the snapshot no longer holds, destroyed up front so their ids are free before
    // anything renders. Runs across the whole editor, not per container, so a block that
    // moved between containers is kept and moved instead of dropped here and re-created.
    #destroyBlocksAbsentFrom(blocksData) {
        const keep = this.#collectSnapshotIds(blocksData);
        [...this.blocks.values()].forEach((block) => {
            if (this.blocks.get(block.id) !== block) {
                return; // already destroyed as a container's descendant
            }
            if (!keep.has(block.id)) {
                block.destroy();
            }
        });
    }

    // Destroy before rendering: the replacement reuses the target's persisted ids — its own
    // and, for a container, its children's — so with the old block still alive the map would
    // hold two instances per id and lookups could not tell them apart. The caller positions
    // the result.
    #replaceBlock(currentBlock, target, container) {
        currentBlock.destroy();
        const newBlock = this.renderBlock(target.type, target, null, 'end', false, container);
        return newBlock ? newBlock.getContainer() : null;
    }

    #withoutColumns(target) {
        const {columns, ...rest} = target;
        return rest;
    }

    #handleContentMutation = () => {
        clearTimeout(this.#contentChangedTimeout);
        this.#contentChangedTimeout = setTimeout(() => {
            if (this.eventEmitter) {
                this.eventEmitter.emit(events.contentChanged);
            }
        }, Blocks.CONTENT_CHANGE_DEBOUNCE);
    };

    #listenToDeleteBLock() {
        this.eventEmitter.on(events.deleteBlock, (id) => {
            const block = this.blocks.get(id);
            if (block && block.constructor.canBeDeleted()) {
                block.destroy();
            }
        });
    }

    #handleBlockSidebar() {
        this.eventEmitter.emit(sidebarEvents.populateBlockSidebar, {
            block: this.activeBlock,
            content: this.activeBlock.renderSidebarContent()}
        );
    }


    #isFirstBlock(block) {
        const container = this.#containerOf(block.getContainer());
        return block.getContainer() === container.querySelector(`:scope > [${contentEditorSelectors.attributes.blockId}]`);
    }

    // The container a block lives in: its nearest [data-block-container] ancestor
    // (a Columns column), or the root #content when it isn't nested.
    // Walks up to the block that sits directly on the canvas — for a block inside a column, the
    // Columns block that owns it. Returns the element itself when it is already top level.
    #topLevelAncestor(element) {
        let node = element;
        while (node && node.parentElement && node.parentElement !== this.contentContainer) {
            node = node.parentElement;
        }
        return node || element;
    }

    #containerOf(element) {
        return element.closest(`[${contentEditorSelectors.attributes.blockContainer}]`) || this.contentContainer;
    }

    // Destroys every block nested inside a container block's columns. Called while the
    // container is still in the DOM (its destroy() emits before removing its element).
    // Guarded so the per-child deletes don't refocus neighbours or trigger renderInitial.
    #destroyDescendants(containerBlock) {
        const wasQueued = this.#blockQueuedForInsertion;
        this.#blockQueuedForInsertion = true;
        containerBlock.getChildContainers().forEach((columnElement) => {
            [...columnElement.querySelectorAll(`[${contentEditorSelectors.attributes.blockId}]`)].forEach((childElement) => {
                const childBlock = this.blockFromBlockElement(childElement);
                // Resolve by identity, not by id alone: ids are reused across a replace, so
                // a lookup off a stale element could hand back a live replacement. Only
                // destroy the instance that actually owns this element.
                if (childBlock && childBlock.getContainer() === childElement) {
                    childBlock.destroy();
                }
            });
        });
        this.#blockQueuedForInsertion = wasQueued;
    }


    /**
     * The blocks, in document order.
     *
     * Document order — not the order they were created. `this.blocks` is a Map keyed by id and so
     * keeps insertion order, which moving a block never changes; the DOM is what holds the order,
     * so that is what this reads.
     *
     * @param {{nested?: boolean, container?: Element}} [options]
     *        `nested: true` also returns blocks inside container blocks (a Columns block's
     *        children), flattened into the same list — use it for "every image in this post".
     *        Leave it off for structure, where top level is what "the blocks" means.
     *        `container` scopes the question: pass a column element for that column's blocks.
     * @returns {object[]} block instances.
     */
    getBlocks({nested = false, container = this.contentContainer} = {}) {
        const attribute = contentEditorSelectors.attributes.blockId;
        const selector = nested ? `[${attribute}]` : `:scope > [${attribute}]`;
        return [...container.querySelectorAll(selector)]
            .map((element) => this.blockFromBlockElement(element))
            // An element carrying the attribute but no live instance shouldn't put a hole in the
            // list — it means the map and the DOM have drifted, which is not the caller's problem.
            .filter(Boolean);
    }

    /**
     * The block at a position within a container.
     *
     * A position only means something inside one container — index 2 of the canvas and index 2 of
     * a column are different blocks — so the container is part of the question rather than an
     * afterthought. Top level only: nesting and indexing don't mix.
     *
     * @returns {object|null} null when nothing sits at that index.
     */
    getBlockAt(index, container = this.contentContainer) {
        const element = container.querySelectorAll(
            `:scope > [${contentEditorSelectors.attributes.blockId}]`
        )[index];
        return element ? this.blockFromBlockElement(element) : null;
    }

    /**
     * One block by id, or null.
     *
     * The id is coerced for the same reason renderBlock mints string ids: it round-trips through a
     * DOM attribute, so a caller holding a numeric 1 would never match the entry stored under "1".
     */
    getBlock(id) {
        return this.blocks.get(String(id)) || null;
    }

    /**
     * Where a block sits **within its own container**, or -1 if it isn't in the document.
     *
     * The container is the block's own, not the canvas: a block inside a column reports its
     * position in that column. Asking for a global index would mean picking an answer for nested
     * blocks, and every answer is wrong for half the callers.
     */
    getBlockIndex(blockOrId) {
        const element = this.#elementOfBlock(blockOrId);
        if (!element || !element.parentElement) {
            return -1;
        }
        const siblings = [...element.parentElement.querySelectorAll(
            `:scope > [${contentEditorSelectors.attributes.blockId}]`
        )];
        return siblings.indexOf(element);
    }

    /**
     * The container block a block sits inside — the Columns block owning the column it's in — or
     * null when it's at the top level.
     *
     * The search starts from the parent rather than the block itself, so a block can never be
     * reported as its own parent.
     */
    getParentBlock(blockOrId) {
        const element = this.#elementOfBlock(blockOrId);
        if (!element || !element.parentElement) {
            return null;
        }
        const parent = element.parentElement.closest(
            `[${contentEditorSelectors.attributes.blockId}]`
        );
        return parent ? this.blockFromBlockElement(parent) : null;
    }

    /**
     * Moves a block to a position, optionally into a different container.
     *
     * `blocks` is keyed by id and holds no order, so only the DOM changes. The content observer
     * turns that into a contentChanged, which is enough for history and the unsaved-changes
     * guard — but not for the overview, which rebuilds on the move events rather than on
     * contentChanged. So the same `blockMoved` the side toggle emits is emitted here, and every
     * listener sees a programmatic move exactly as it sees a dragged one.
     *
     * `index` counts the container's blocks **with the moving one excluded**, so moving a block
     * to index 0 puts it first whether it started above or below that point.
     *
     * `container` defaults to the block's **own** container, not the canvas. For a block inside a
     * column that means it reorders within that column — moving it out is a different operation
     * and has to say so:
     *
     *   moveBlock(block, 0);                                  // first in its own column
     *   moveBlock(block, 0, blocks.contentContainer);         // out of the column, first on the canvas
     *
     * @param {{focus?: boolean}} [options] `focus: false` for a bulk reorder — see below.
     * @returns {boolean} false when the block doesn't exist, or the move would be illegal:
     *          into itself, or a container block into a column, since containers don't nest.
     */
    moveBlock(blockOrId, index, container = null, {focus = true} = {}) {
        const element = this.#elementOfBlock(blockOrId);
        if (!element) {
            return false;
        }
        const target = container || element.parentElement;
        // A container block can't be moved inside itself — the DOM would throw, and the block
        // would be its own ancestor.
        if (!target || element === target || element.contains(target)) {
            return false;
        }
        // Nor into any column: containers don't nest. The same rule the inserter, the paste
        // pipeline and the side toggle each apply, keyed off the block owning child containers.
        const block = this.blockFromBlockElement(element);
        const isContainer = block && typeof block.getChildContainers === 'function';
        if (isContainer && target !== this.contentContainer) {
            return false;
        }

        const siblings = [...target.querySelectorAll(
            `:scope > [${contentEditorSelectors.attributes.blockId}]`
        )].filter((sibling) => sibling !== element);

        const at = Math.max(0, Math.min(Math.round(Number(index) || 0), siblings.length));
        const before = siblings[at] || null;
        before ? target.insertBefore(element, before) : target.appendChild(element);
        // focus() alone is not enough. When the block being moved is the active one it already
        // holds focus, so focus() is a no-op, no focusin fires, and blockFocused — which is what
        // recomputes the neighbours and repositions the side toggle — never runs. The block ends
        // up focused with the toggle still sitting where the block used to be.
        //
        // So both are done by hand afterwards, the same way the gap-insert path does it. Harmless
        // when focusin *did* fire: recomputing neighbours and repositioning are both idempotent.
        if (focus && typeof block.focus === 'function') {
            block.focus();
            this.#refreshActiveNeighbours();
            this.eventEmitter.emit(blockSideToggleEvents.recalculateTogglePosition);
        }
        this.eventEmitter.emit(blockSideToggleEvents.blockMoved, block);
        return true;
    }

    // Accepts an instance or an id, so callers holding either don't have to convert first.
    #elementOfBlock(blockOrId) {
        const block = (blockOrId && typeof blockOrId === 'object') ? blockOrId : this.getBlock(blockOrId);
        return block ? block.getContainer() : null;
    }

    blockFromBlockElement(element) {
        return this.blocks.get(element.getAttribute(contentEditorSelectors.attributes.blockId)) || null;
    }

    blockElementFromId(blockId) {
        return this.contentContainer.querySelector(`[${contentEditorSelectors.attributes.blockId}="${blockId}"]`);
    }


    previousBlockElement(element) {
        return element.previousElementSibling
    }

    nextBlockElement(element) {
        return element.nextElementSibling;
    }


    renderInitial() {
        this.renderBlock(Paragraph.name, {}, null, 'end', false);
    }

    // When the content is just the initial, empty paragraph, render the given block in its
    // place and remove the paragraph. Returns true if it replaced, false otherwise.
    replaceInitialEmptyParagraph(name) {
        const topLevel = [...this.contentContainer.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`)];
        if(topLevel.length !== 1) {
            return false;
        }
        const block = this.blockFromBlockElement(topLevel[0]);
        if(!block || block.constructor !== Paragraph || block.getContainer().textContent.trim() !== '') {
            return false;
        }
        this.#blockQueuedForInsertion = true;
        this.renderBlock(name, {}, null, 'prepend', true, this.contentContainer);
        block.destroy();
        this.#blockQueuedForInsertion = false;
        return true;
    }

    getBlockData() {
        return this.#serializeContainer(this.contentContainer);
    }

    #serializeContainer(container) {
        const data = [];
        container.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`).forEach((element) => {
            const block = this.blockFromBlockElement(element);
            if (block) {
                data.push(this.#serializeBlock(block));
            }
        });
        return data;
    }

    // The full save-shape of one block: its own data + (for container blocks) the
    // serialized children of each of its columns.
    #serializeBlock(block) {
        const blockData = block.getBlockData();
        if (typeof block.getChildContainers === 'function') {
            blockData.columns = block.getChildContainers().map((columnElement) => this.#serializeContainer(columnElement));
        }
        return blockData;
    }

    // Duplicate = serialize the block, deep-clone it (so the copy shares no state — e.g.
    // additionalData / settings objects), and render it right after the original in the
    // same container. renderBlock re-populates a container's columns from data.columns.
    #duplicateBlock(id) {
        const block = this.blocks.get(id);
        if (!block) {
            return;
        }
        if (block.constructor.hidden) {
            return; // auto-managed blocks (e.g. footnotes) must stay unique
        }
        const data = this.#stripIds(structuredClone(this.#serializeBlock(block)));
        this.renderBlock(data.type, data, block.getContainer(), 'after');
    }

    #listenToDuplicateBlock() {
        this.eventEmitter.on(events.duplicateBlock, (id) => {
            this.#duplicateBlock(id);
        });
    }

    static getTopLevelBlockCountFromDom() {
        const blocks = document.querySelectorAll(`#${contentEditorSelectors.ids.contentContainer} > [${contentEditorSelectors.attributes.blockId}]`);
        return blocks.length;
    }

    static getRenderedBlockIdsAndNames() {
        const blocks = document.querySelectorAll(`#${contentEditorSelectors.ids.contentContainer} > [${contentEditorSelectors.attributes.blockId}]`);
        const data = [];
        blocks.forEach((block) => {
            data.push({
                id: block.getAttribute(contentEditorSelectors.attributes.blockId),
                name: block.getAttribute(contentEditorSelectors.attributes.blockName)
            })
        });
        return data;
    }

    isReadOnly() {
        return this.readOnly;
    }

    setReadOnly(value) {
        this.readOnly = value;
    }

    #isEmptyObject(val) {
        return val && typeof val === 'object' && val.constructor === Object && Object.keys(val).length === 0;
    };

    destroy() {
        this.activeBlock = null;
        this.beforeActiveBlock = null;
        this.afterActiveBlock = null;
        this.blocks.forEach((block) => {
           block.destroy();
        });
        this.blockModules.clear();
        this.contentContainer.removeEventListener('keydown', this.#handleOnKeydown);
        this.contentContainer.removeEventListener('focusin', this.#handleFocusIn);
        this.contentContainer.removeEventListener('paste', this.#handlePaste);
        this.contentContainer.removeEventListener('copy', this.#handleCopy);
        this.contentContainer.removeEventListener('cut', this.#handleCut);
        this.contentContainer.removeEventListener('mousedown', this.#handleMouseDown);
        this.contentContainer.removeEventListener('click', this.#handleClick);
        if (this.#contentObserver) {
            this.#contentObserver.disconnect();
            this.#contentObserver = null;
        }
        clearTimeout(this.#contentChangedTimeout);
        this.eventEmitter = null;
        if(this.blockMenu) {
            this.blockMenu.destroy();
            this.blockMenu = null;
        }
        this.blockSideToggle.destroy();
        this.blockSideToggle = null;
        this.formatToolbar.destroy();
        this.formatToolbar = null;
        this.overviewHandler.destroy();
        this.overviewHandler = null;
        this.historyHandler.destroy();
        this.historyHandler = null;
        this.blockInserter.destroy();
        this.blockInserter = null;
        this.blockGapInserter.destroy();
        this.blockGapInserter = null;
        this.footnotesHandler.destroy();
        this.footnotesHandler = null;
        this.entityTriggersHandler.destroy();
        this.entityTriggersHandler = null;
        this.blockSelection.destroy();
        this.blockSelection = null;
    }
}