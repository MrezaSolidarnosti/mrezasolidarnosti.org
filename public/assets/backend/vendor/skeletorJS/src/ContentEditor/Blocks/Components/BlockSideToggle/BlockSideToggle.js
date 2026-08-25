import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import {events} from "../../events.js";
import {events as sidebarEvents} from "../../../Sidebar/events.js";
import {events as blockSideToggleEvents} from "./events.js";
import Blocks from "../../Blocks.js";
import {appliesToBlock} from "../../blockScope.js";
import Dismissible from "../../../Dismissible/Dismissible.js";
import Translator from "../../../../Translator/Translator.js";

export default class BlockSideToggle {

    /**
     * Project-registered entries for the block's "more" menu, keyed so registering the same key
     * twice replaces rather than duplicates.
     *
     *   BlockSideToggle.registerAction({
     *       key: 'copyBlock',
     *       label: 'Copy block',
     *       blocks: ['core/image'],        // only these        (optional)
     *       excludeBlocks: ['core/table'], // all except these  (optional)
     *       showOnHidden: false,           // hidden/system blocks are skipped by default
     *       isVisible: (block) => true,    // re-checked on every focus (optional)
     *       onClick: (block) => { … },
     *   });
     *
     * Menu-only by design: the toolbar row's position is derived from how many buttons it has
     * (`actionsCount`), so adding to it would shift the whole toolbar.
     */
    static ACTIONS = new Map();

    static registerAction(definition) {
        if (!definition || !definition.key || typeof definition.onClick !== 'function') {
            throw new Error('A side-menu action needs a `key` and an `onClick`.');
        }
        BlockSideToggle.ACTIONS.set(definition.key, definition);
    }

    static unRegisterAction(key) {
        BlockSideToggle.ACTIONS.delete(key);
    }

    #setupComplete = false;
    readOnly = false;
    #registeredItems = [];   // {definition, element, handler}
    moveDownButton;
    moveUpButton;
    deleteButton;
    container;
    activeBlock;
    actionsCount;
    blockSideToggleMore;
    blockSideToggleMoreMenu;
    insertBeforeButton;
    insertAfterButton;
    duplicateButton;
    dragHandle;
    contentContainer;
    #draggedBlockElement = null;
    #draggedIsContainer = false;
    #pendingDrop = null;
    #dropIndicator = null;
    #dragImageElement = null;
    #dismissible = null;
    constructor({eventEmitter, readOnly = false}) {
        this.eventEmitter = eventEmitter;
        this.readOnly = readOnly;
    }


    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#buildRegisteredActions();
        this.#addListeners();
        this.#listenToEvents();
        // Escape closes the more-menu like any other dismissible surface. It only claims the
        // key while the menu is actually open, so Escape otherwise still reaches the block
        // selection and the panels.
        this.#dismissible = Dismissible.register({
            isOpen: () => this.isMoreMenuOpen(),
            close: () => this.#closeMoreMenu(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.blockSideToggle);
        this.moveUpButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleMoveUp);
        this.moveDownButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleMoveDown);
        this.deleteButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleDelete);
        this.actionsCount = this.container.querySelectorAll(`.${contentEditorSelectors.classes.blockSideAction}`).length;
        this.blockSideToggleMore = document.getElementById(contentEditorSelectors.ids.blockSideToggleMore);
        this.blockSideToggleMoreMenu = document.getElementById(contentEditorSelectors.ids.blockSideToggleMoreMenu);
        this.insertBeforeButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleAddBefore);
        this.insertAfterButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleAddAfter);
        this.duplicateButton = document.getElementById(contentEditorSelectors.ids.blockSideToggleDuplicate);
        this.dragHandle = document.getElementById(contentEditorSelectors.ids.blockSideToggleDragHandle);
        this.contentContainer = document.getElementById(contentEditorSelectors.ids.contentContainer);
        this.dragHandle.draggable = !this.readOnly;
        this.#dropIndicator = document.createElement('div');
        this.#dropIndicator.classList.add(contentEditorSelectors.classes.blockDropIndicator);
        document.body.appendChild(this.#dropIndicator);
    }

    // The side toggle is a singleton — one element repositioned per focus, not rebuilt — so
    // registered items are created once here and only shown/hidden as the active block changes.
    // That's why there's no per-focus listener churn to clean up.
    #buildRegisteredActions() {
        BlockSideToggle.ACTIONS.forEach((definition) => {
            const element = document.createElement('span');
            element.textContent = Translator.translate(definition.label || definition.key);
            const handler = () => {
                if (this.activeBlock) {
                    definition.onClick(this.activeBlock);
                }
                this.#closeMoreMenu();
            };
            element.addEventListener('click', handler);
            this.blockSideToggleMoreMenu.appendChild(element);
            this.#registeredItems.push({definition, element, handler});
        });
    }

    // Re-evaluated on every focus: scoping (blocks / excludeBlocks / hidden) plus the
    // definition's own isVisible predicate.
    #syncRegisteredActions(block) {
        this.#registeredItems.forEach(({definition, element}) => {
            let visible = appliesToBlock(definition, block.constructor);
            if (visible && typeof definition.isVisible === 'function') {
                visible = !!definition.isVisible(block);
            }
            element.classList.toggle(contentEditorSelectors.classes.hidden, !visible);
        });
    }

    #repositionBlockSideToggle = () => {
        if (this.activeBlock && this.container.classList.contains(contentEditorSelectors.classes.active)) {
            this.#handleBlockSideToggle();
        }
    }

    #handleClickWindow = (e) => {
        if(e.target !== this.blockSideToggleMoreMenu && !this.blockSideToggleMoreMenu.contains(e.target) && e.target !== this.blockSideToggleMore) {
            this.#closeMoreMenu();
        }
    }

    #handleBlockSideToggle() {
        // Every action it offers — move, duplicate, delete, insert, drag — is a mutation, so
        // in read-only it has nothing to do. Blocks can still take focus (arrow navigation,
        // the block sidebar), the toolbar just never appears.
        if(this.readOnly) {
            this.container.classList.remove(contentEditorSelectors.classes.active);
            return;
        }
        if(this.activeBlock) {
            // Hidden/system blocks (footnotes, unknown) are never duplicated by hand; deletion
            // is separate — a hidden block can still be deletable (the unknown placeholder is).
            const module = this.activeBlock.constructor;
            this.duplicateButton.classList.toggle(contentEditorSelectors.classes.hidden, !!module.hidden);
            this.deleteButton.classList.toggle(contentEditorSelectors.classes.hidden, !module.canBeDeleted());
            this.#syncRegisteredActions(this.activeBlock);
            const rect = this.activeBlock.getContainer().getBoundingClientRect();
            this.container.style.left = `${rect.left - (38 * this.actionsCount)}px`;
            this.container.style.top = `${rect.top - 6}px`;
            this.container.classList.add(contentEditorSelectors.classes.active);
        } else {
            this.container.classList.remove(contentEditorSelectors.classes.active);
        }
    }


    #addListeners() {
        window.addEventListener('scroll', this.#repositionBlockSideToggle, true);
        window.addEventListener('resize', this.#repositionBlockSideToggle);
        window.addEventListener('click', this.#handleClickWindow);
        this.moveUpButton.addEventListener('click', this.#handleMoveUp);
        this.moveDownButton.addEventListener('click', this.#handleMoveDown);
        this.deleteButton.addEventListener('click', this.#handleDelete);
        this.blockSideToggleMore.addEventListener('click', this.#handleMoreMenu);
        this.insertBeforeButton.addEventListener('click', this.#handleInsertBefore);
        this.insertAfterButton.addEventListener('click', this.#handleInsertAfter);
        this.duplicateButton.addEventListener('click', this.#handleDuplicate);
        this.dragHandle.addEventListener('dragstart', this.#handleBlockDragStart);
        this.dragHandle.addEventListener('dragend', this.#handleBlockDragEnd);
        this.contentContainer.addEventListener('dragover', this.#handleBlockDragOver);
        this.contentContainer.addEventListener('drop', this.#handleBlockDrop);
    }

    #handleBlockDragStart = (e) => {
        if(!this.activeBlock) {
            return;
        }
        this.#draggedBlockElement = this.activeBlock.getContainer();
        this.#draggedIsContainer = typeof this.activeBlock.getChildContainers === 'function';
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('application/x-posteditor-block', '');
        // Drag with a small icon chip instead of a snapshot of the whole block.
        this.#dragImageElement = document.createElement('div');
        this.#dragImageElement.classList.add(contentEditorSelectors.classes.blockDragImage);
        this.#dragImageElement.innerHTML = this.activeBlock.constructor.icon;
        document.body.appendChild(this.#dragImageElement);
        e.dataTransfer.setDragImage(this.#dragImageElement, 20, 20);
        this.#draggedBlockElement.classList.add(contentEditorSelectors.classes.blockDragging);
        this.#closeMoreMenu();
        requestAnimationFrame(() => {
            this.container.classList.remove(contentEditorSelectors.classes.active);
        });
    }

    #handleBlockDragOver = (e) => {
        if(!this.#draggedBlockElement) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const drop = this.#computeDrop(e);
        this.#pendingDrop = drop;
        if(drop) {
            this.#showDropIndicator(drop.rect, drop.after);
        } else {
            this.#hideDropIndicator();
        }
    }

    #computeDrop(e) {
        let container = e.target.closest(`[${contentEditorSelectors.attributes.blockContainer}]`);
        if(!container && this.contentContainer.contains(e.target)) {
            container = this.contentContainer;
        }
        if(!container || this.#draggedBlockElement.contains(container)) {
            return null;   // no container, or would drop into its own subtree
        }
        if(this.#draggedIsContainer && container !== this.contentContainer) {
            return null;   // a Columns block can only live at the root
        }

        const children = [...container.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`)]
            .filter((child) => child !== this.#draggedBlockElement);

        for(const child of children) {
            const rect = child.getBoundingClientRect();
            if(e.clientY < rect.top + rect.height / 2) {
                return {container, reference: child, rect, after: false};
            }
        }
        const last = children[children.length - 1];
        if(last) {
            return {container, reference: null, rect: last.getBoundingClientRect(), after: true};
        }
        return {container, reference: null, rect: container.getBoundingClientRect(), after: false};
    }

    #showDropIndicator(rect, after) {
        this.#dropIndicator.style.left = `${rect.left}px`;
        this.#dropIndicator.style.width = `${rect.width}px`;
        this.#dropIndicator.style.top = `${after ? rect.bottom : rect.top}px`;
        this.#dropIndicator.classList.add(contentEditorSelectors.classes.active);
    }

    #hideDropIndicator() {
        this.#dropIndicator.classList.remove(contentEditorSelectors.classes.active);
    }

    #handleBlockDrop = (e) => {
        if(!this.#draggedBlockElement) {
            return;
        }
        e.preventDefault();
        if(this.#pendingDrop) {
            this.#pendingDrop.container.insertBefore(this.#draggedBlockElement, this.#pendingDrop.reference);
        }
    }

    #handleBlockDragEnd = () => {
        if(!this.#draggedBlockElement) {
            return;
        }
        this.#draggedBlockElement.classList.remove(contentEditorSelectors.classes.blockDragging);
        this.#hideDropIndicator();
        if(this.#dragImageElement) {
            this.#dragImageElement.remove();
            this.#dragImageElement = null;
        }
        this.#draggedBlockElement = null;
        this.#draggedIsContainer = false;
        this.#pendingDrop = null;
        if(this.activeBlock) {
            this.activeBlock.focus();
            this.eventEmitter.emit(blockSideToggleEvents.blockMoved, this.activeBlock);
        }
    }

    #handleMoveUp = () => {
        const container = this.activeBlock.getContainer();
        const previousElement = container.previousElementSibling;
        if(previousElement) {
            container.parentNode.insertBefore(previousElement, container.nextElementSibling);
            this.activeBlock.focus();
            this.eventEmitter.emit(blockSideToggleEvents.blockMovedUp, this.activeBlock);
        }
    }

    #handleMoveDown = () => {
        const nextElement = this.activeBlock.getContainer().nextElementSibling;
        if(nextElement) {
            nextElement.parentNode.insertBefore(nextElement, this.activeBlock.getContainer());
            this.activeBlock.focus();
            this.eventEmitter.emit(blockSideToggleEvents.blockMovedDown, this.activeBlock);
        }
    }

    #handleDelete = () => {
        this.activeBlock.destroy();
        this.#closeMoreMenu();
    }

    #handleDuplicate = () => {
        this.eventEmitter.emit(events.duplicateBlock, this.activeBlock.id);
        this.#closeMoreMenu();
    }

    #handleMoreMenu = () => {
        const open = this.blockSideToggleMoreMenu.classList.toggle(contentEditorSelectors.classes.active);
        // Re-check registered actions as the menu opens, not only when a block takes focus:
        // the block's state can change while it stays focused (typing into an empty paragraph,
        // filling a table), and this menu is the only place those entries are ever seen.
        if (open && this.activeBlock) {
            this.#syncRegisteredActions(this.activeBlock);
        }
    }

    isMoreMenuOpen() {
        return this.blockSideToggleMoreMenu.classList.contains(contentEditorSelectors.classes.active);
    }

    #closeMoreMenu() {
        this.blockSideToggleMoreMenu.classList.remove(contentEditorSelectors.classes.active);
    }

    #listenToEvents() {
        this.eventEmitter.on(events.blockFocused, (block) => {
            this.activeBlock = block;
            this.#handleBlockSideToggle();
        });
        this.eventEmitter.on(sidebarEvents.sidebarClosed, () => {
            this.container.classList.remove(contentEditorSelectors.classes.active);
            if(Blocks.getTopLevelBlockCountFromDom() > 1) {
                setTimeout(() => {
                    this.#handleBlockSideToggle();
                    this.container.classList.add(contentEditorSelectors.classes.active);
                }, 300);
            }
        });
        this.eventEmitter.on(sidebarEvents.sidebarOpened, () => {
            this.container.classList.remove(contentEditorSelectors.classes.active);
            if(Blocks.getTopLevelBlockCountFromDom() > 1) {
                setTimeout(() => {
                    this.#handleBlockSideToggle();
                    this.container.classList.add(contentEditorSelectors.classes.active);
                }, 300);
            }
        });
        this.eventEmitter.on(events.blockDeleted, (block) => {
            if (this.activeBlock === block) {
                this.activeBlock = null;
                this.container.classList.remove(contentEditorSelectors.classes.active);
            }
        });
        // Selecting blocks stands the active block down, and the toggle acts on exactly one
        // block — so there is nothing left for it to point at.
        this.eventEmitter.on(events.blockBlurred, () => {
            this.activeBlock = null;
            this.container.classList.remove(contentEditorSelectors.classes.active);
            this.#closeMoreMenu();
        });

        this.eventEmitter.on(blockSideToggleEvents.recalculateTogglePosition, () => {
           this.#handleBlockSideToggle();
        });
    }


    #handleInsertBefore = () => {
        this.eventEmitter.emit(events.insertBefore);
        this.#closeMoreMenu();
    }

    #handleInsertAfter = () => {
        this.eventEmitter.emit(events.insertAfter);
        this.#closeMoreMenu();
    }

    destroy() {
        window.removeEventListener('scroll', this.#repositionBlockSideToggle, true);
        window.removeEventListener('resize', this.#repositionBlockSideToggle);
        window.removeEventListener('click', this.#handleClickWindow);
        this.moveUpButton.removeEventListener('click', this.#handleMoveUp);
        this.moveDownButton.removeEventListener('click', this.#handleMoveDown);
        this.deleteButton.removeEventListener('click', this.#handleDelete);
        this.blockSideToggleMore.removeEventListener('click', this.#handleMoreMenu);
        this.insertBeforeButton.removeEventListener('click', this.#handleInsertBefore);
        this.insertAfterButton.removeEventListener('click', this.#handleInsertAfter);
        this.duplicateButton.removeEventListener('click', this.#handleDuplicate);
        this.dragHandle.removeEventListener('dragstart', this.#handleBlockDragStart);
        this.dragHandle.removeEventListener('dragend', this.#handleBlockDragEnd);
        this.contentContainer.removeEventListener('dragover', this.#handleBlockDragOver);
        this.contentContainer.removeEventListener('drop', this.#handleBlockDrop);
        this.#registeredItems.forEach(({element, handler}) => {
            element.removeEventListener('click', handler);
            element.remove();
        });
        this.#registeredItems = [];
        this.#dropIndicator.remove();
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.#setupComplete = false;
    }
}