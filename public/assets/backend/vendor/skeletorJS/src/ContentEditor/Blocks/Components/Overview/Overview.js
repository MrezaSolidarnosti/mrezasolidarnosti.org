import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import BlockRepresentation from "./BlockRepresentation.js";
import {events} from "./events.js";
import {events as blockEvents} from "../../events.js";
import {events as blockSideToggleEvents} from "../BlockSideToggle/events.js";
import Dismissible from "../../../Dismissible/Dismissible.js";

export default class Overview {
    static TOGGLE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M201.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 338.7 54.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg>`;

    #setupComplete = false;
    #dismissible = null;
    overviewButton;
    overviewContainer;
    eventEmitter;
    blockModules;
    readOnly;
    #renderedInitial = false;
    representations = new Map();
    activeRepresentation;
    contentContainer;
    #draggedNode = null;
    #draggedIsContainer = false;
    #rebuildScheduled = false;
    #focusedBlockId = null;
    #containerMap = new Map();
    #collapsedIds = new Set();

    constructor({eventEmitter, blockModules, readOnly}) {
        this.eventEmitter = eventEmitter;
        this.blockModules = blockModules;
        this.readOnly = readOnly;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#addListeners();
        this.#listenToEvents();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.overviewContainer.classList.contains(contentEditorSelectors.classes.active),
            close: () => this.#closePanel(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        this.overviewButton = document.getElementById(contentEditorSelectors.ids.overviewButton);
        this.overviewContainer = document.getElementById(contentEditorSelectors.ids.overviewContainer);
        if(this.readOnly) {
            this.overviewContainer.setAttribute(contentEditorSelectors.attributes.readOnly, 'true');
        }
        this.contentContainer = document.getElementById(contentEditorSelectors.ids.contentContainer);
    }


    renderInitial() {
        if(this.#renderedInitial) {
            return;
        }
        this.#renderTree();
        this.#renderedInitial = true;
    }

    // Rebuilds the whole overview to mirror the content tree, reusing representation
    // instances (so their focus/options/active state survives) and pruning ones whose
    // blocks are gone. Cheap enough since it only runs on structural changes, debounced.
    #renderTree() {
        const usedIds = new Set();
        // Maps each overview container (the root, or a column group) to its content
        // counterpart, so a cross-container drop knows where to move the block.
        this.#containerMap = new Map([[this.overviewContainer, this.contentContainer]]);
        this.overviewContainer.innerHTML = '';
        this.#renderContainer(this.contentContainer, this.overviewContainer, usedIds);
        [...this.representations.keys()].forEach((id) => {
            if(!usedIds.has(id)) {
                const representation = this.representations.get(id);
                if(representation === this.activeRepresentation) {
                    this.activeRepresentation = null;
                }
                representation.destroy();
                this.representations.delete(id);
            }
        });
        // A block can be focused before its representation exists (insert fires blockFocused
        // synchronously, but the rebuild is deferred), so re-apply focus once it's built.
        const focused = this.#focusedBlockId !== null ? this.representations.get(this.#focusedBlockId) : null;
        if(focused) {
            this.#focusRepresentation(focused);
        }
    }

    // Walks a content container's direct block children in DOM order, rendering each as a
    // node (representation + a nested area for its columns, recursively).
    #renderContainer(contentElement, overviewParent, usedIds) {
        contentElement.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`).forEach((blockElement) => {
            const id = blockElement.getAttribute(contentEditorSelectors.attributes.blockId);
            const name = blockElement.getAttribute(contentEditorSelectors.attributes.blockName);
            const representation = this.#getOrCreateRepresentation(id, name);
            if(!representation) {
                return;
            }
            usedIds.add(id);

            const node = document.createElement('div');
            node.classList.add(contentEditorSelectors.classes.overviewNode);

            const childContainers = [...blockElement.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockContainer}]`)];
            if(childContainers.length) {
                const toggle = document.createElement('div');
                toggle.classList.add(contentEditorSelectors.classes.overviewToggle);
                toggle.innerHTML = Overview.TOGGLE_ICON;
                node.appendChild(toggle);
                if(this.#collapsedIds.has(id)) {
                    node.classList.add(contentEditorSelectors.classes.overviewNodeCollapsed);
                }
            }

            node.appendChild(representation.getContainer());

            if(childContainers.length) {
                const nest = document.createElement('div');
                nest.classList.add(contentEditorSelectors.classes.overviewNest);
                childContainers.forEach((columnElement) => {
                    const group = document.createElement('div');
                    group.classList.add(contentEditorSelectors.classes.overviewColumnGroup);
                    this.#containerMap.set(group, columnElement);
                    this.#renderContainer(columnElement, group, usedIds);
                    nest.appendChild(group);
                });
                node.appendChild(nest);
            }
            overviewParent.appendChild(node);
        });
    }

    #getOrCreateRepresentation(id, name) {
        return this.representations.get(id) || this.#createRepresentation(id, name);
    }

    #createRepresentation(id, name) {
        const blockModule = this.blockModules.get(name);
        if(!blockModule) {
            return null;
        }
        const representation = new BlockRepresentation({
            eventEmitter: this.eventEmitter,
            id,
            name,
            label: blockModule.label,
            icon: blockModule.icon,
            readOnly: this.readOnly,
            hidden: !!blockModule.hidden
        });
        representation.init();
        this.representations.set(id, representation);
        return representation;
    }

    #scheduleRebuild() {
        if(this.#rebuildScheduled || !this.#renderedInitial) {
            return;
        }
        this.#rebuildScheduled = true;
        // Coalesce a burst of structural events (e.g. a history restore) into one rebuild.
        queueMicrotask(() => {
            this.#rebuildScheduled = false;
            if(this.overviewContainer) {
                this.#renderTree();
            }
        });
    }


    #addListeners() {
        this.overviewButton.addEventListener('click', this.#toggleOverview);
        this.overviewContainer.addEventListener('click', this.#handleCollapseClick);
        if(!this.readOnly) {
            this.overviewContainer.addEventListener('dragstart', this.#handleDragStart);
            this.overviewContainer.addEventListener('dragover', this.#handleDragOver);
            this.overviewContainer.addEventListener('drop', this.#handleDrop);
            this.overviewContainer.addEventListener('dragend', this.#handleDragEnd);
            window.addEventListener('click', this.#handleWindowClick);
        }
    }

    // Collapses / expands a container node's nested children. Tracked by block id so the
    // state survives the tree rebuilds.
    #handleCollapseClick = (e) => {
        const toggle = e.target.closest(`.${contentEditorSelectors.classes.overviewToggle}`);
        if(!toggle) {
            return;
        }
        const node = toggle.closest(`.${contentEditorSelectors.classes.overviewNode}`);
        const collapsed = node.classList.toggle(contentEditorSelectors.classes.overviewNodeCollapsed);
        const id = this.#nodeId(node);
        collapsed ? this.#collapsedIds.add(id) : this.#collapsedIds.delete(id);
    }

    #handleDragStart = (e) => {
        const representation = e.target.closest(`.${contentEditorSelectors.classes.overviewBlock}`);
        const node = representation ? representation.closest(`.${contentEditorSelectors.classes.overviewNode}`) : null;
        if(!node) {
            return;
        }
        this.#draggedNode = node;
        // A container block (Columns) can't be nested into a column.
        this.#draggedIsContainer = this.#isContainerBlock(this.#blockElementForNode(node));
        e.dataTransfer.effectAllowed = 'move';
        representation.classList.add(contentEditorSelectors.classes.overviewBlockDragging);
    }

    // Live preview follows the cursor across containers: the block can move out of a column
    // to the root, into a column, or between columns. Content is only touched on drop.
    #handleDragOver = (e) => {
        if(!this.#draggedNode) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const container = this.#dropContainerFromPoint(e.target);
        if(!container
            || this.#draggedNode.contains(container)                                 // can't drop into itself
            || (this.#draggedIsContainer && container !== this.overviewContainer)) {  // no columns-in-columns
            return;
        }
        const afterNode = this.#getDragAfterNode(container, e.clientY);
        if(afterNode === this.#draggedNode) {
            return;
        }
        if(afterNode) {
            container.insertBefore(this.#draggedNode, afterNode);
        } else {
            container.appendChild(this.#draggedNode);
        }
    }

    // The overview container the cursor is over: the nearest mapped column group, else the
    // root.
    #dropContainerFromPoint(target) {
        const group = target.closest(`.${contentEditorSelectors.classes.overviewColumnGroup}`);
        if(group && this.#containerMap.has(group)) {
            return group;
        }
        return this.overviewContainer.contains(target) ? this.overviewContainer : null;
    }

    #handleDrop = (e) => {
        if(!this.#draggedNode) {
            return;
        }
        e.preventDefault();
        this.#applyOrderToContent(this.#draggedNode);
    }

    #handleDragEnd = () => {
        if(!this.#draggedNode) {
            return;
        }
        this.#draggedNode.querySelector(`:scope > .${contentEditorSelectors.classes.overviewBlock}`)
            ?.classList.remove(contentEditorSelectors.classes.overviewBlockDragging);
        this.#draggedNode = null;
        this.#draggedIsContainer = false;
        // Content is the source of truth: snap the overview back in line with it.
        this.#renderTree();
    }

    #getDragAfterNode(parent, y) {
        const siblings = [...parent.children].filter(
            (child) => child.classList.contains(contentEditorSelectors.classes.overviewNode) && child !== this.#draggedNode
        );
        return siblings.find((sibling) => {
            const rect = sibling.getBoundingClientRect();
            return y < rect.top + rect.height / 2;
        }) || null;
    }

    // Moves the dragged block element to mirror its node's new spot, anchoring it before
    // the block of the next node (both are in the same content container after a
    // it corresponds to (root or a specific column), before the block of the next node.
    #applyOrderToContent(node) {
        const blockElement = this.#blockElementForNode(node);
        const parentContent = this.#containerMap.get(node.parentElement);
        if(!blockElement || !parentContent) {
            return;
        }
        // Columns can't be nested: never move a container block into anything but the root.
        // (dragend then rebuilds the overview, snapping the node back into place.)
        if(parentContent !== this.contentContainer && this.#isContainerBlock(blockElement)) {
            return;
        }
        const nextNode = node.nextElementSibling;
        const nextBlockElement = (nextNode && nextNode.classList.contains(contentEditorSelectors.classes.overviewNode))
            ? this.#blockElementForNode(nextNode)
            : null;
        const reference = (nextBlockElement && nextBlockElement.parentElement === parentContent) ? nextBlockElement : null;
        parentContent.insertBefore(blockElement, reference);

        const representation = this.representations.get(this.#nodeId(node));
        if(representation && representation !== this.activeRepresentation) {
            this.#focusRepresentation(representation);
            this.eventEmitter.emit(blockEvents.setActiveBlock, representation.id);
        } else {
            this.eventEmitter.emit(blockSideToggleEvents.recalculateTogglePosition);
        }
    }

    #nodeId(node) {
        const representation = node.querySelector(`:scope > .${contentEditorSelectors.classes.overviewBlock}`);
        return representation ? representation.getAttribute(contentEditorSelectors.attributes.blockId) : null;
    }

    #blockElementForNode(node) {
        const id = this.#nodeId(node);
        return id === null ? null : this.contentContainer.querySelector(`[${contentEditorSelectors.attributes.blockId}="${id}"]`);
    }

    // A block is a container (holds columns) if its class exposes getChildContainers —
    // the same test the block manager uses. Robust regardless of the block's DOM shape.
    #isContainerBlock(blockElement) {
        const name = blockElement ? blockElement.getAttribute(contentEditorSelectors.attributes.blockName) : null;
        const module = name ? this.blockModules.get(name) : null;
        return !!(module && typeof module.prototype.getChildContainers === 'function');
    }

    #listenToEvents() {
        this.eventEmitter.on(blockEvents.leftPanelOpened, (id) => {
            if(id !== this.overviewContainer.id) {
                this.#closePanel();
            }
        });
        if(!this.readOnly) {
            this.#listenToRepresentationSelected();
            this.#listenToBlockFocused();
            this.#listenToStructuralChanges();
        }
    }


    #listenToRepresentationSelected() {
        this.eventEmitter.on(events.representationSelected, (representation) => {
            if(this.activeRepresentation !== representation) {
                this.#focusRepresentation(representation);
                this.eventEmitter.emit(blockEvents.setActiveBlock, this.activeRepresentation.id);
            }
        });
    }

    #listenToBlockFocused() {
        this.eventEmitter.on(blockEvents.blockFocused, (block) => {
           this.#focusedBlockId = block.id;
           const representation = this.representations.get(block.id);
           if(representation) {
               this.#focusRepresentation(representation);
           } else {
               // Rep not built yet (e.g. this block was just inserted) — the deferred
               // rebuild will apply focus once it exists.
               this.#removeActiveRepresentation();
           }
        });
        // No active block (a multi-block selection took over), so nothing is highlighted.
        // Clearing the id too, or a later rebuild would restore the old highlight.
        this.eventEmitter.on(blockEvents.blockBlurred, () => {
            this.#focusedBlockId = null;
            this.#removeActiveRepresentation();
        });
    }

    // Any insert / delete / move (top-level or nested) is reflected by rebuilding the tree
    // from the content DOM — one code path that handles nesting naturally.
    #listenToStructuralChanges() {
        const rebuild = () => this.#scheduleRebuild();
        this.eventEmitter.on(blockEvents.blockInserted, rebuild);
        this.eventEmitter.on(blockEvents.blockDeleted, rebuild);
        this.eventEmitter.on(blockSideToggleEvents.blockMovedUp, rebuild);
        this.eventEmitter.on(blockSideToggleEvents.blockMovedDown, rebuild);
        this.eventEmitter.on(blockSideToggleEvents.blockMoved, rebuild);
    }

    #focusRepresentation(representation) {
        if(representation === this.activeRepresentation) {
            return;
        }
        if (this.activeRepresentation) {
            this.activeRepresentation.unfocus();
        }
        this.activeRepresentation = representation;
        this.activeRepresentation.focus();
    }

    #removeActiveRepresentation() {
        if(this.activeRepresentation) {
            this.activeRepresentation.unfocus();
            this.activeRepresentation = null;
        }
    }

    #toggleOverview = () => {
        const open = this.overviewContainer.classList.toggle(contentEditorSelectors.classes.active);
        this.overviewButton.classList.toggle(contentEditorSelectors.classes.active, open);
        if(open) {
            this.eventEmitter.emit(blockEvents.leftPanelOpened, this.overviewContainer.id);
        }
    }

    #closePanel() {
        this.overviewContainer.classList.remove(contentEditorSelectors.classes.active);
        this.overviewButton.classList.remove(contentEditorSelectors.classes.active);
    }

    #handleWindowClick = (e) => {
        if(this.activeRepresentation?.isOptionsContainerOpen() && !this.activeRepresentation?.isElementAnOption(e.target)
        && e.target !== this.activeRepresentation.getContainer()) {
            this.activeRepresentation.closeOptions();
        }
    }

    destroy() {
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        this.overviewButton.removeEventListener('click', this.#toggleOverview);
        this.overviewContainer.removeEventListener('click', this.#handleCollapseClick);
        this.overviewContainer.removeEventListener('dragstart', this.#handleDragStart);
        this.overviewContainer.removeEventListener('dragover', this.#handleDragOver);
        this.overviewContainer.removeEventListener('drop', this.#handleDrop);
        this.overviewContainer.removeEventListener('dragend', this.#handleDragEnd);
        window.removeEventListener('click', this.#handleWindowClick);
        this.overviewButton = null;
        this.overviewContainer = null;
        this.contentContainer = null;
        this.eventEmitter = null;
        this.activeRepresentation = null;
        this.#draggedNode = null;
    }
}
