import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import BlockMenu from "../Components/BlockMenu/BlockMenu.js";
import Translator from "../../../Translator/Translator.js";

export default class OrderedList extends Block {
    static label = 'Numbered List';
    static keywords = ['ol', 'number', 'ordered', 'list'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-80v-60h100v-30h-60v-60h60v-30H120v-60h120q17 0 28.5 11.5T280-280v40q0 17-11.5 28.5T240-200q17 0 28.5 11.5T280-160v40q0 17-11.5 28.5T240-80H120Zm0-280v-110q0-17 11.5-28.5T160-510h60v-30H120v-60h120q17 0 28.5 11.5T280-560v70q0 17-11.5 28.5T240-450h-60v30h100v60H120Zm60-280v-180h-60v-60h120v240h-60Zm180 440v-80h480v80H360Zm0-240v-80h480v80H360Zm0-240v-80h480v80H360Z"/></svg>`;
    static isText = true;
    static name = 'core/orderedList';
    static category = 'text';
    static description = 'Create a numbered list.';
    static tags = ['ol'];
    element;

    render() {
        this.element = document.createElement('ol');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        const li = document.createElement('li');
        li.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('List'));
        this.element.appendChild(li);
        this.#addListeners();
        return this.element;
    }

    #addListeners() {
        this.element.addEventListener('input', this.#handleOnInput);
        this.element.addEventListener('keydown', this.#handleKeydown);
    }

    #handleOnInput = () => {
        if(this.element.textContent === '/') {
            this.renderBLockMenu(this.element);
        }
    }

    #currentListItem() {
        const selection = window.getSelection();
        if (!selection || !selection.anchorNode) {
            return null;
        }
        const node = selection.anchorNode;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        const li = element ? element.closest('li') : null;
        return (li && this.element.contains(li)) ? li : null;
    }

    #handleKeydown = (e) => {
        if (BlockMenu.isOpen() && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            return;
        }

        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            this.#handleVerticalArrow(e);
            return;
        }

        if (e.key === 'Tab' && !BlockMenu.isOpen()) {
            const currentLi = this.#currentListItem();
            if (currentLi) {
                e.preventDefault();
                if (e.shiftKey) {
                    this.#outdentItem(currentLi);
                } else {
                    this.#indentItem(currentLi);
                }
            }
            return;
        }

        if (e.key === 'Enter' && !BlockMenu.isOpen()) {
            const currentLi = this.#currentListItem();
            const isEmpty = currentLi && currentLi.textContent.trim() === '';

            // An empty nested item outdents one level, rather than nesting deeper forever or
            // exiting the block. Enter again (now top-level) exits as usual.
            if (isEmpty && currentLi.parentElement !== this.element) {
                e.preventDefault();
                this.#outdentItem(currentLi);
                return;
            }

            const isLast = currentLi && currentLi === this.element.lastElementChild;
            if (isEmpty && isLast) {
                if (this.element.querySelectorAll('li').length === 1) {
                    this.destroy();
                } else {
                    currentLi.remove();
                }
                return;
            }
            e.stopPropagation();
        }

        if (e.key === 'Backspace' && this.element.textContent.trim() === '') {
            e.preventDefault();
            this.destroy();
        }
    }

    // Up/Down step between list items, keeping the caret's offset. Only at the first item
    // (Up) or the last (Down) is the event left alone, so it bubbles to the block navigation
    // and moves to the neighbouring block — the same edge hand-off the table uses.
    // querySelectorAll returns document order, so nested items are stepped through in the
    // order they read on screen.
    #handleVerticalArrow(e) {
        const currentLi = this.#currentListItem();
        if (!currentLi) {
            return;
        }
        const items = [...this.element.querySelectorAll('li')];
        const index = items.indexOf(currentLi);
        if (index === -1) {
            return;
        }
        const target = items[index + (e.key === 'ArrowDown' ? 1 : -1)];
        if (!target) {
            return;   // at an edge — hand off to the block navigation
        }
        e.preventDefault();
        e.stopPropagation();
        this.#placeCaret(target, this.#caretOffset(currentLi));
    }

    // Move a list item into a sublist under its previous sibling (Tab). The first item at any
    // level has no previous sibling to nest under, so it stays put.
    #indentItem(li) {
        const previous = li.previousElementSibling;
        if (!previous) {
            return;
        }
        const offset = this.#caretOffset(li);
        const levelTag = li.parentElement.tagName;   // matches this list's type at every depth
        let sublist = previous.lastElementChild;
        if (!sublist || sublist.tagName !== levelTag) {
            sublist = document.createElement(levelTag);
            previous.appendChild(sublist);
        }
        sublist.appendChild(li);
        this.#placeCaret(li, offset);
    }

    // Lift a nested item out to its parent level (Shift+Tab). Items that were after it in the
    // sublist become nested under it, so they keep their depth instead of jumping a level.
    #outdentItem(li) {
        const sublist = li.parentElement;
        const owningItem = sublist.parentElement;
        if (!owningItem || owningItem.tagName !== 'LI') {
            return;   // already at the top level
        }
        const offset = this.#caretOffset(li);

        const trailing = [];
        for (let sib = li.nextElementSibling; sib; sib = sib.nextElementSibling) {
            trailing.push(sib);
        }
        if (trailing.length) {
            let ownSublist = li.lastElementChild;
            if (!ownSublist || ownSublist.tagName !== sublist.tagName) {
                ownSublist = document.createElement(sublist.tagName);
                li.appendChild(ownSublist);
            }
            trailing.forEach((item) => ownSublist.appendChild(item));
        }

        owningItem.after(li);
        if (!sublist.children.length) {
            sublist.remove();
        }
        this.#placeCaret(li, offset);
    }

    // Caret as a character offset within the item's own text. A cloned Range breaks once the
    // <li> is reparented — it resolves against the old tree and the caret lands on the item's
    // former parent — so we measure an offset and rebuild the caret inside the moved item.
    #caretOffset(li) {
        const selection = window.getSelection();
        if (!selection.rangeCount) {
            return 0;
        }
        const range = selection.getRangeAt(0);
        if (!li.contains(range.startContainer)) {
            return 0;
        }
        const measure = range.cloneRange();
        measure.selectNodeContents(li);
        measure.setEnd(range.startContainer, range.startOffset);
        return measure.toString().length;
    }

    #placeCaret(li, offset) {
        const walker = document.createTreeWalker(li, NodeFilter.SHOW_TEXT);
        let remaining = offset;
        let lastText = null;
        let node;
        while ((node = walker.nextNode())) {
            lastText = node;
            if (remaining <= node.textContent.length) {
                this.#collapseInto(node, remaining);
                return;
            }
            remaining -= node.textContent.length;
        }
        // No text yet (an empty item) or the offset ran past the end — sit at the end.
        if (lastText) {
            this.#collapseInto(lastText, lastText.textContent.length);
        } else {
            this.#collapseInto(li, 0);
        }
    }

    #collapseInto(node, offset) {
        const range = document.createRange();
        range.setStart(node, offset);
        range.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
        const target = this.element.querySelector('li') || this.element;
        if (window.getSelection && document.createRange) {
            const range = document.createRange();
            range.selectNodeContents(target);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    getData() {
        return {html: this.element.innerHTML};
    }


    destroy() {
        this.element.removeEventListener('input', this.#handleOnInput);
        this.element.removeEventListener('keydown', this.#handleKeydown);
        this.element.remove();
        super.destroy();
    }
}
