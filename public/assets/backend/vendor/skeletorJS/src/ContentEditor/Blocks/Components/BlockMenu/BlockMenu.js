import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import {events} from "./events.js";
import {positionPopup} from "../../../../PositionPopup/positionPopup.js";
import Translator from "../../../../Translator/Translator.js";

export default class BlockMenu {

    eventEmitter;
    blocks;
    block;
    textElement;
    container;
    blockItems = [];
    categoryHeaders = [];
    activeItem = null;
    constructor({eventEmitter, blocks, block, textElement}) {
        this.eventEmitter = eventEmitter;
        this.blocks = blocks;
        this.block = block;
        this.textElement = textElement;
    }

    render() {
        this.container = document.createElement('div');
        this.container.id = contentEditorSelectors.ids.blockMenu;
        this.#groupByCategory().forEach((group) => {
            this.container.appendChild(this.#renderCategoryHeader(group.category));
            group.blocks.forEach((block) => {
                const item = this.#renderBlockItem(block, this.activeItem === null);
                item.setAttribute(contentEditorSelectors.attributes.blockMenuItemCategory, group.category);
                this.blockItems.push(item);
                this.container.appendChild(item);
            });
        });
        // Append before placing: the menu has to be laid out before it can be measured.
        document.body.appendChild(this.container);
        this.#position();
        window.addEventListener('input', this.#handleOnInput);
        window.addEventListener('keydown', this.#handleKeyDown);
        window.addEventListener('click', this.#handleOnClick);
        // Hovering an item makes it active, so the keyboard continues from under the mouse —
        // same as the entity-search popup. Removed with the container in destroy().
        this.container.addEventListener('mousemove', this.#handleItemHover);
    }

    #handleOnInput = () => {
        const searchValue = this.textElement.textContent.trim().replace('/', '').toLowerCase();
        const isTrulyEmpty = this.textElement.textContent.trim() === '';
        let hasItems = false;
        let itemToActivate = null;
        this.blockItems.forEach((item) => {
            const keywordsRaw = item.getAttribute(
                contentEditorSelectors.attributes.blockMenuItemKeywords
            );

            let keywords = [];

            try {
                keywords = JSON.parse(keywordsRaw || '[]');
            } catch (e) {
                keywords = [];
            }

            const match = keywords.some(k =>
                k.toLowerCase().includes(searchValue)
            );

            item.classList.toggle(contentEditorSelectors.classes.hidden, !match);

            if (match) {
                hasItems = true;

                if (itemToActivate === null) {
                    itemToActivate = item;
                }
            }
        });
        if(itemToActivate) {
            this.#makeItemActive(itemToActivate);
        }
        this.#syncCategoryHeaders();

        if(!hasItems || isTrulyEmpty) {
            this.destroy();
            return;
        }
        // Filtering changes the height, so a menu that had to open upwards would otherwise
        // drift away from the block as it shrinks.
        this.#position();
    }

    // Anchored under the block, flipping above it when the block sits near the bottom of the
    // viewport. Absolute, so it scrolls with the page rather than hanging in place.
    #position() {
        positionPopup(this.container, this.block.getContainer(), {gap: 8, absolute: true});
    }

    // A category heading is only shown while its group still has a visible item.
    #syncCategoryHeaders() {
        const attr = contentEditorSelectors.attributes.blockMenuItemCategory;
        this.categoryHeaders.forEach((header) => {
            const category = header.getAttribute(attr);
            const anyVisible = this.blockItems.some((item) =>
                item.getAttribute(attr) === category
                && !item.classList.contains(contentEditorSelectors.classes.hidden)
            );
            header.classList.toggle(contentEditorSelectors.classes.hidden, !anyVisible);
        });
    }


    #handleKeyDown = (e) => {
        switch(e.key) {
            case 'ArrowDown':
                this.#move(1);
                break;
            case 'ArrowUp':
                this.#move(-1);
                break;
            case 'Enter':
                this.#selectItem();
                break;
            case 'Escape':
                // Dismiss the menu but keep the typed "/" — Escape cancels the menu, not the edit.
                e.preventDefault();
                e.stopPropagation();
                this.destroy();
                break;
        }
    };

    // Wraps around like the entity-search popup: past the last item goes to the first, and up
    // from the first goes to the last. Hidden items (filtered out by search) are skipped.
    #move(delta) {
        const items = this.#visibleItems();
        if (!items.length) {
            return;
        }
        const currentIndex = items.indexOf(this.activeItem);
        const nextIndex = currentIndex === -1
            ? (delta > 0 ? 0 : items.length - 1)   // no/hidden active item — enter at an end
            : (currentIndex + delta + items.length) % items.length;
        this.#makeItemActive(items[nextIndex]);
    }

    #visibleItems() {
        return [...this.container.querySelectorAll(`.${contentEditorSelectors.classes.blockMenuItem}`)].filter(
            (item) => !item.classList.contains(contentEditorSelectors.classes.hidden)
        );
    }

    #handleOnClick = (e) => {
        if(!this.container.contains(e.target) || e.target !== this.container) {
            this.eventEmitter.emit(events.blockMenuRemoved);
            this.destroy();
        }
    };

    #selectItem() {
        if(this.activeItem) {
            const blockToAdd = this.activeItem.getAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName);
            if(blockToAdd === this.block.constructor.name) {
                return;
            }
            this.eventEmitter.emit(events.blockMenuItemSelected, {
                blockToAdd,
                oldBlock: this.block
            });
            this.destroy();
        }
    }

    #makeItemActive(item) {
        if(this.activeItem) {
            this.activeItem.classList.remove(contentEditorSelectors.classes.active);
        }
        this.activeItem = item;
        this.activeItem.classList.add(contentEditorSelectors.classes.active);
        this.#scrollActiveIntoView();
    }

    #scrollActiveIntoView() {
        const item = this.activeItem;

        if (item === this.container.firstElementChild) {
            this.container.scrollTop = 0;
            return;
        }
        if (item === this.container.lastElementChild) {
            this.container.scrollTop = this.container.scrollHeight;
            return;
        }

        const top = item.offsetTop;
        const bottom = top + item.offsetHeight;
        if (top < this.container.scrollTop) {
            this.container.scrollTop = top;
        } else if (bottom > this.container.scrollTop + this.container.clientHeight) {
            this.container.scrollTop = bottom - this.container.clientHeight;
        }
    }

    #handleItemClick = (e) => {
        const item = e.target.closest(`.${contentEditorSelectors.classes.blockMenuItem}`);
        if(item) {
            this.#makeItemActive(item);
            this.#selectItem();
        }
    }

    #handleItemHover = (e) => {
        const item = e.target.closest(`.${contentEditorSelectors.classes.blockMenuItem}`);
        if (item && item !== this.activeItem
            && !item.classList.contains(contentEditorSelectors.classes.hidden)) {
            this.#makeItemActive(item);
        }
    }

    // Insertable blocks grouped by category. A Map keeps insertion order, so both the groups
    // (by the category's first appearance) and the blocks within them follow config.blocks —
    // the order the consumer already chose. Nothing to hardcode or maintain.
    #groupByCategory() {
        const byCategory = new Map();
        this.blocks.forEach((block) => {
            if (block.hidden) {
                return; // auto-managed blocks (e.g. footnotes) can't be inserted by hand
            }
            const category = block.category || 'other';
            if (!byCategory.has(category)) {
                byCategory.set(category, []);
            }
            byCategory.get(category).push(block);
        });
        return [...byCategory].map(([category, blocks]) => ({category, blocks}));
    }

    #renderCategoryHeader(category) {
        const header = document.createElement('div');
        header.classList.add(contentEditorSelectors.classes.blockMenuCategory);
        header.setAttribute(contentEditorSelectors.attributes.blockMenuItemCategory, category);
        header.textContent = Translator.translate(category.charAt(0).toUpperCase() + category.slice(1));
        this.categoryHeaders.push(header);
        return header;
    }

    #renderBlockItem(block, active) {
        const blockContainer = document.createElement('div');
        blockContainer.classList.add(contentEditorSelectors.classes.blockMenuItem);
        blockContainer.innerHTML = `${block.icon}<span>${Translator.translate(block.label)}</span>`;
        if(active) {
            this.#makeItemActive(blockContainer);
        }
        blockContainer.setAttribute(contentEditorSelectors.attributes.blockMenuItemBlockName, block.name);
        blockContainer.setAttribute(contentEditorSelectors.attributes.blockMenuItemLabel, Translator.translate(block.label));
        blockContainer.setAttribute(contentEditorSelectors.attributes.blockMenuItemKeywords, JSON.stringify(block.keywords));
        blockContainer.addEventListener('click', this.#handleItemClick);
        return blockContainer;
    }

    static isOpen() {
        return !!document.getElementById(contentEditorSelectors.ids.blockMenu);
    }

    destroy() {
        this.eventEmitter.emit(events.blockMenuRemoved);
        this.eventEmitter = null;
        this.blocks = null;
        this.block = null;
        this.textElement = null;
        this.activeItem = null;
        this.categoryHeaders = [];
        window.removeEventListener('input', this.#handleOnInput);
        window.removeEventListener('keydown', this.#handleKeyDown);
        window.removeEventListener('click', this.#handleOnClick);
        this.#handleOnInput = null;
        this.container.remove();
    }
}