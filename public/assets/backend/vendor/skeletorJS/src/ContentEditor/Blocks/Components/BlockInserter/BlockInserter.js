import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import {events} from "../../events.js";
import Dismissible from "../../../Dismissible/Dismissible.js";
import Translator from "../../../../Translator/Translator.js";

export default class BlockInserter {
    #setupComplete = false;
    #dismissible = null;
    eventEmitter;
    blocks;
    blockModules;
    readOnly;
    button;
    container;
    closeButton;
    searchInput;
    listContainer;
    noResults;
    card;
    dropIndicator;
    #tiles = [];
    #hoveredTile = null;
    #draggedName = null;
    #pendingDrop = null;

    static CATEGORY_ORDER = ['text', 'media', 'layout', 'design'];
    static CATEGORY_LABELS = {text: 'Text', media: 'Media', layout: 'Layout', design: 'Design'};

    constructor({eventEmitter, blocks, blockModules, readOnly}) {
        this.eventEmitter = eventEmitter;
        this.blocks = blocks;
        this.blockModules = blockModules;
        this.readOnly = readOnly;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#renderList();
        this.#createNoResults();
        this.#createCard();
        this.#createDropIndicator();
        this.#addListeners();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.container.classList.contains(contentEditorSelectors.classes.active),
            close: () => this.#close(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.button = document.getElementById(contentEditorSelectors.ids.blockInserterButton);
        this.container = document.getElementById(contentEditorSelectors.ids.blockInserter);
        this.closeButton = document.getElementById(contentEditorSelectors.ids.blockInserterClose);
        this.searchInput = document.getElementById(contentEditorSelectors.ids.blockInserterSearch);
        this.listContainer = document.getElementById(contentEditorSelectors.ids.blockInserterList);
    }

    #renderList() {
        this.listContainer.innerHTML = '';
        this.#tiles = [];

        const byCategory = new Map();
        this.blockModules.forEach((module, name) => {
            if (module.hidden) {
                return; // auto-managed blocks (e.g. footnotes) aren't user-insertable
            }
            const category = module.category || 'text';
            if (!byCategory.has(category)) {
                byCategory.set(category, []);
            }
            byCategory.get(category).push({name, module});
        });

        // Known categories first in a fixed order, then anything unexpected.
        const ordered = [
            ...BlockInserter.CATEGORY_ORDER,
            ...[...byCategory.keys()].filter((c) => !BlockInserter.CATEGORY_ORDER.includes(c))
        ];
        ordered.forEach((category) => {
            const entries = byCategory.get(category);
            if (entries && entries.length) {
                this.listContainer.appendChild(this.#renderCategory(category, entries));
            }
        });
    }

    #renderCategory(category, entries) {
        const section = document.createElement('div');
        section.classList.add(contentEditorSelectors.classes.blockInserterCategory);

        const label = document.createElement('span');
        label.classList.add(contentEditorSelectors.classes.blockInserterCategoryLabel);
        label.textContent = Translator.translate(BlockInserter.CATEGORY_LABELS[category] || category);
        section.appendChild(label);

        const items = document.createElement('div');
        items.classList.add(contentEditorSelectors.classes.blockInserterItems);
        entries.forEach(({name, module}) => {
            const tile = this.#renderTile(name, module);
            this.#tiles.push({tile, name, module});
            items.appendChild(tile);
        });
        section.appendChild(items);
        return section;
    }

    #renderTile(name, module) {
        const tile = document.createElement('div');
        tile.classList.add(contentEditorSelectors.classes.blockInserterItem);
        tile.setAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName, name);
        tile.draggable = true;
        tile.innerHTML = `${module.icon}<span class="${contentEditorSelectors.classes.blockInserterItemLabel}">${Translator.translate(module.label)}</span>`;
        return tile;
    }

    #createNoResults() {
        this.noResults = document.createElement('div');
        this.noResults.classList.add(contentEditorSelectors.classes.blockInserterNoResults);
        this.noResults.classList.add(contentEditorSelectors.classes.hidden);
        this.noResults.textContent = Translator.translate('No results found.');
        this.listContainer.appendChild(this.noResults);
    }

    #createCard() {
        this.card = document.createElement('div');
        this.card.id = contentEditorSelectors.ids.blockInserterCard;
        this.card.classList.add(contentEditorSelectors.classes.hidden);
        document.body.appendChild(this.card);
    }

    #createDropIndicator() {
        this.dropIndicator = document.createElement('div');
        this.dropIndicator.classList.add(contentEditorSelectors.classes.blockDropIndicator);
        document.body.appendChild(this.dropIndicator);
    }

    #addListeners() {
        this.button.addEventListener('click', this.#toggle);
        this.closeButton.addEventListener('click', this.#close);
        this.searchInput.addEventListener('input', this.#handleSearch);
        if(!this.readOnly) {
            this.listContainer.addEventListener('click', this.#handleListClick);
            this.listContainer.addEventListener('mouseover', this.#handleListMouseOver);
            this.listContainer.addEventListener('mouseleave', this.#handleListLeave);
            this.listContainer.addEventListener('dragstart', this.#handleTileDragStart);
            this.listContainer.addEventListener('dragend', this.#handleDragEnd);
            this.blocks.contentContainer.addEventListener('dragover', this.#handleContentDragOver);
            this.blocks.contentContainer.addEventListener('drop', this.#handleContentDrop);
        }
        this.eventEmitter.on(events.leftPanelOpened, (id) => {
            if (id !== this.container.id) {
                this.#close();
            }
        });
    }

    #toggle = () => {
        const open = this.container.classList.toggle(contentEditorSelectors.classes.active);
        this.button.classList.toggle(contentEditorSelectors.classes.active, open);
        if (open) {
            this.searchInput.focus();
            this.button.title = Translator.translate('Close');
            this.eventEmitter.emit(events.leftPanelOpened, this.container.id);
        } else {
            this.button.title = Translator.translate('Add Block');
            this.#reset();
        }
    }

    #close = () => {
        this.container.classList.remove(contentEditorSelectors.classes.active);
        this.button.classList.remove(contentEditorSelectors.classes.active);
        this.#reset();
    }

    // Clears the search (and its filtered state) and hides the transient card/indicator so
    // reopening the panel always starts from the full, unfiltered list.
    #reset() {
        if (this.searchInput.value !== '') {
            this.searchInput.value = '';
            this.#handleSearch();
        }
        this.#hideCard();
        this.#hideIndicator();
    }

    #handleSearch = () => {
        const query = this.searchInput.value.trim().toLowerCase();
        this.#tiles.forEach(({tile, module}) => {
            const haystack = `${Translator.translate(module.label)} ${module.label} ${(module.keywords || []).join(' ')}`.toLowerCase();
            tile.classList.toggle(contentEditorSelectors.classes.hidden, query !== '' && !haystack.includes(query));
        });
        // Hide category sections that have no visible tiles left.
        this.listContainer.querySelectorAll(`.${contentEditorSelectors.classes.blockInserterCategory}`).forEach((section) => {
            const anyVisible = section.querySelector(`.${contentEditorSelectors.classes.blockInserterItem}:not(.${contentEditorSelectors.classes.hidden})`);
            section.classList.toggle(contentEditorSelectors.classes.hidden, !anyVisible);
        });
        // Show the empty-state message when nothing matches.
        const anyMatch = this.#tiles.some(({tile}) => !tile.classList.contains(contentEditorSelectors.classes.hidden));
        this.noResults.classList.toggle(contentEditorSelectors.classes.hidden, anyMatch);
    }

    #handleListClick = (e) => {
        const tile = e.target.closest(`.${contentEditorSelectors.classes.blockInserterItem}`);
        if (!tile) {
            return;
        }
        this.#insert(tile.getAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName));
    }

    // Click inserts below the focused block (matching the drop-indicator preview), or at
    // the end of the content when nothing is focused. The new block becomes active, so
    // repeated inserts stack.
    #insert(name) {
        // Fresh editor (just the initial empty paragraph) — swap that for the clicked block.
        if (this.blocks.replaceInitialEmptyParagraph(name)) {
            this.#positionIndicatorBelowActive();
            return;
        }
        const module = this.blockModules.get(name);
        const isContainer = module && typeof module.prototype.getChildContainers === 'function';
        const active = this.blocks.activeBlock;
        if (active && active.getContainer().isConnected) {
            const container = active.getContainer().closest(`[${contentEditorSelectors.attributes.blockContainer}]`) || this.blocks.contentContainer;
            // Columns can't nest — if this would drop one inside a column, put it at the root end.
            if (isContainer && container !== this.blocks.contentContainer) {
                this.blocks.renderBlock(name, {}, null, 'end');
            } else {
                this.blocks.renderBlock(name, {}, active.getContainer(), 'after');
            }
        } else {
            this.blocks.renderBlock(name, {}, null, 'end');
        }
        this.#positionIndicatorBelowActive();
    }

    #handleListMouseOver = (e) => {
        const tile = e.target.closest(`.${contentEditorSelectors.classes.blockInserterItem}`);
        if (!tile) {
            // Over a gap or a category label — not a block tile — so hide the card.
            this.#handleListLeave();
            return;
        }
        if (tile === this.#hoveredTile) {
            return;
        }
        this.#hoveredTile = tile;
        const module = this.blockModules.get(tile.getAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName));
        if (module) {
            this.#showCard(tile, module);
        }
        // Preview where a click would land.
        this.#positionIndicatorBelowActive();
    }

    #handleListLeave = () => {
        this.#hoveredTile = null;
        this.#hideCard();
        this.#hideIndicator();
    }

    #showCard(tile, module) {
        this.card.innerHTML = `
            <div class="${contentEditorSelectors.classes.blockInserterCardTitle}">${module.icon}<span>${Translator.translate(module.label)}</span></div>
            <div class="${contentEditorSelectors.classes.blockInserterCardDescription}">${Translator.translate(module.description || '')}</div>`;
        const tileRect = tile.getBoundingClientRect();
        const panelRect = this.container.getBoundingClientRect();
        this.card.classList.remove(contentEditorSelectors.classes.hidden);
        this.card.style.top = `${tileRect.top}px`;
        this.card.style.left = `${panelRect.right + 12}px`;
    }

    #hideCard() {
        this.card.classList.add(contentEditorSelectors.classes.hidden);
    }

    // --- Drag and drop into the content -------------------------------------

    #handleTileDragStart = (e) => {
        const tile = e.target.closest(`.${contentEditorSelectors.classes.blockInserterItem}`);
        if (!tile) {
            return;
        }
        this.#draggedName = tile.getAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName);
        e.dataTransfer.effectAllowed = 'copy';
        this.#hideCard();
    }

    #handleContentDragOver = (e) => {
        if (!this.#draggedName) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        this.#pendingDrop = this.#computeDrop(e);
        if (this.#pendingDrop) {
            this.#showIndicatorAt(this.#pendingDrop.rect, this.#pendingDrop.after);
        } else {
            this.#hideIndicator();
        }
    }

    #handleContentDrop = (e) => {
        if (!this.#draggedName) {
            return;
        }
        e.preventDefault();
        const name = this.#draggedName;
        const drop = this.#pendingDrop;
        this.#draggedName = null;
        this.#pendingDrop = null;
        this.#hideIndicator();
        if (!drop) {
            return;
        }
        if (drop.reference) {
            this.blocks.renderBlock(name, {}, drop.reference, drop.after ? 'after' : 'before', true, drop.container);
        } else {
            this.blocks.renderBlock(name, {}, null, 'end', true, drop.container);
        }
    }

    #handleDragEnd = () => {
        this.#draggedName = null;
        this.#pendingDrop = null;
        this.#hideIndicator();
    }

    // Mirrors the side-toggle's drop computation: resolve the container under the cursor
    // (a column or the root), then the neighbour to drop before/after by vertical midpoint.
    #computeDrop(e) {
        let container = e.target.closest(`[${contentEditorSelectors.attributes.blockContainer}]`);
        if (!container && this.blocks.contentContainer.contains(e.target)) {
            container = this.blocks.contentContainer;
        }
        if (!container) {
            return null;
        }
        // A container block (Columns) can only live at the root.
        const module = this.blockModules.get(this.#draggedName);
        const isContainer = module && typeof module.prototype.getChildContainers === 'function';
        if (isContainer && container !== this.blocks.contentContainer) {
            return null;
        }

        const children = [...container.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`)];
        for (const child of children) {
            const rect = child.getBoundingClientRect();
            if (e.clientY < rect.top + rect.height / 2) {
                return {container, reference: child, rect, after: false};
            }
        }
        const last = children[children.length - 1];
        if (last) {
            return {container, reference: null, rect: last.getBoundingClientRect(), after: true};
        }
        return {container, reference: null, rect: container.getBoundingClientRect(), after: false};
    }

    #positionIndicatorBelowActive() {
        const active = this.blocks.activeBlock;
        if (!active || !active.getContainer().isConnected) {
            this.#hideIndicator();
            return;
        }
        this.#showIndicatorAt(active.getContainer().getBoundingClientRect(), true);
    }

    #showIndicatorAt(rect, after) {
        this.dropIndicator.style.left = `${rect.left}px`;
        this.dropIndicator.style.width = `${rect.width}px`;
        this.dropIndicator.style.top = `${after ? rect.bottom : rect.top}px`;
        this.dropIndicator.classList.add(contentEditorSelectors.classes.active);
    }

    #hideIndicator() {
        this.dropIndicator.classList.remove(contentEditorSelectors.classes.active);
    }

    destroy() {
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        if (this.readOnly) {
            this.eventEmitter = null;
            this.blocks = null;
            return;
        }
        this.button.removeEventListener('click', this.#toggle);
        this.closeButton.removeEventListener('click', this.#close);
        this.searchInput.removeEventListener('input', this.#handleSearch);
        this.listContainer.removeEventListener('click', this.#handleListClick);
        this.listContainer.removeEventListener('mouseover', this.#handleListMouseOver);
        this.listContainer.removeEventListener('mouseleave', this.#handleListLeave);
        this.listContainer.removeEventListener('dragstart', this.#handleTileDragStart);
        this.listContainer.removeEventListener('dragend', this.#handleDragEnd);
        this.blocks.contentContainer.removeEventListener('dragover', this.#handleContentDragOver);
        this.blocks.contentContainer.removeEventListener('drop', this.#handleContentDrop);
        this.card.remove();
        this.dropIndicator.remove();
        this.eventEmitter = null;
        this.blocks = null;
    }
}
