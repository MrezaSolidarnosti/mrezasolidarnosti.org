import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import {events} from "../../events.js";
import Translator from "../../../../Translator/Translator.js";

const FOOTNOTES_BLOCK = 'core/footnotes';

export default class Footnotes {

    #eventEmitter;
    #blocks;            // the Blocks manager (for renderBlock / block lookup)
    #content;
    #snapshot = '';     // ordered marker ids of the last sync, to skip no-op reconciles
    #counter = 0;
    #card = null;       // hover preview of a marker's note

    constructor({eventEmitter, blocks}) {
        this.#eventEmitter = eventEmitter;
        this.#blocks = blocks;
    }

    init() {
        this.#content = document.getElementById(contentEditorSelectors.ids.contentContainer);
        this.#buildCard();
        this.#eventEmitter.on(events.contentChanged, this.#reconcile);
        this.#content.addEventListener('click', this.#handleClick);
        this.#content.addEventListener('input', this.#handleInput);
        this.#content.addEventListener('mouseover', this.#handleMouseOver);
        this.#content.addEventListener('mouseout', this.#handleMouseOut);
    }

    // Emptying a contentEditable note leaves a lone <br>, which defeats :empty and hides
    // the placeholder. Clear it so the placeholder shows (same trick the text blocks use).
    #handleInput = (e) => {
        const content = e.target.closest && e.target.closest(`.${contentEditorSelectors.classes.footnotesContent}`);
        if (content && content.innerHTML === '<br>') {
            content.innerHTML = '';
        }
    };


    insert() {
        const selection = window.getSelection();
        if (!selection.rangeCount) {
            return;
        }
        const range = selection.getRangeAt(0);
        if (!this.#editableFromRange(range)) {
            return; // caret isn't inside a text block
        }

        const id = this.#generateId();
        const marker = this.#buildMarker(id);
        range.collapse(false); // to the end of any selection, so we insert (not replace)
        range.insertNode(marker);

        // caret just after the marker so typing continues in normal text
        const after = document.createRange();
        after.setStartAfter(marker);
        after.collapse(true);
        selection.removeAllRanges();
        selection.addRange(after);

        this.#reconcile();     // create list / add item / renumber straight away
        this.#focusItem(id);   // drop the user into the new note to type it
    }

    #buildMarker(id) {
        const marker = document.createElement('sup');
        marker.classList.add(contentEditorSelectors.classes.footnoteRef);
        marker.contentEditable = 'false';
        marker.setAttribute(contentEditorSelectors.attributes.footnoteId, id);
        marker.textContent = '0'; // renumbered by reconcile
        return marker;
    }

    #editableFromRange(range) {
        const node = range.startContainer;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        const editable = element ? element.closest(`.${contentEditorSelectors.classes.editableBlock}`) : null;
        return (editable && this.#content.contains(editable)) ? editable : null;
    }

    #reconcile = () => {
        const markers = this.#markers();
        const snapshot = markers.map((m) => m.getAttribute(contentEditorSelectors.attributes.footnoteId)).join('|');
        if (snapshot === this.#snapshot) {
            return; // marker set/order unchanged — nothing to do (also breaks the mutation loop)
        }
        this.#snapshot = snapshot;

        if (!markers.length) {
            this.#removeList();
            return;
        }
        const list = this.#ensureList();
        if (!list) {
            return;
        }
        this.#syncItems(list, markers);
        this.#renumber(markers, list);
    };

    // All markers in document order, excluding anything inside the list block itself.
    #markers() {
        const block = this.#listBlockElement();
        const selector = `sup.${contentEditorSelectors.classes.footnoteRef}[${contentEditorSelectors.attributes.footnoteId}]`;
        return [...this.#content.querySelectorAll(selector)].filter((m) => !block || !block.contains(m));
    }

    #syncItems(list, markers) {
        const existing = new Map();
        [...list.children].forEach((li) => existing.set(li.getAttribute(contentEditorSelectors.attributes.footnoteId), li));

        const ordered = markers.map((marker) => {
            const id = marker.getAttribute(contentEditorSelectors.attributes.footnoteId);
            const li = existing.get(id);
            if (li) {
                existing.delete(id); // keep (and reuse its note text)
                return li;
            }
            return this.#buildItem(id);
        });

        existing.forEach((li) => li.remove());     // markers gone → drop their notes
        ordered.forEach((li) => list.appendChild(li)); // reorder to match marker order
    }

    #buildItem(id) {
        const li = document.createElement('li');
        li.classList.add(contentEditorSelectors.classes.footnotesItem);
        li.setAttribute(contentEditorSelectors.attributes.footnoteId, id);

        const backlink = document.createElement('a');
        backlink.classList.add(contentEditorSelectors.classes.footnotesBacklink);
        backlink.contentEditable = 'false';
        backlink.setAttribute(contentEditorSelectors.attributes.footnoteId, id);

        const content = document.createElement('div');
        content.classList.add(contentEditorSelectors.classes.footnotesContent);
        content.contentEditable = 'true';
        content.spellcheck = false;
        content.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate('Write the footnote...'));

        li.append(backlink, content);
        return li;
    }

    // Both the inline marker and the list's back-link show the footnote's number.
    #renumber(markers, list) {
        markers.forEach((marker, index) => {
            const number = `${index + 1}`;
            if (marker.textContent !== number) {
                marker.textContent = number;
            }
            const id = marker.getAttribute(contentEditorSelectors.attributes.footnoteId);
            const backlink = list.querySelector(
                `:scope > li[${contentEditorSelectors.attributes.footnoteId}='${id}'] > .${contentEditorSelectors.classes.footnotesBacklink}`
            );
            if (backlink && backlink.textContent !== number) {
                backlink.textContent = number;
            }
        });
    }

    #ensureList() {
        let block = this.#listBlockElement();
        if (!block) {
            this.#blocks.renderBlock(FOOTNOTES_BLOCK, {}, null, 'end', false, this.#content);
            block = this.#listBlockElement();
        }
        return block ? block.querySelector(`.${contentEditorSelectors.classes.footnotesList}`) : null;
    }

    #removeList() {
        const block = this.#listBlockElement();
        if (!block) {
            return;
        }
        const id = block.getAttribute(contentEditorSelectors.attributes.blockId);
        const instance = this.#blocks.blocks.get(id);
        if (instance) {
            instance.destroy();
        }
    }

    #listBlockElement() {
        return this.#content.querySelector(`[${contentEditorSelectors.attributes.blockName}='${FOOTNOTES_BLOCK}']`);
    }

    #focusItem(id) {
        const list = this.#ensureList();
        const item = list ? list.querySelector(`li[${contentEditorSelectors.attributes.footnoteId}='${id}']`) : null;
        if (!item) {
            return;
        }
        item.scrollIntoView({block: 'nearest'});
        const content = item.querySelector(`.${contentEditorSelectors.classes.footnotesContent}`);
        (content || item).focus();
    }

    #scrollToMarker(id) {
        const marker = this.#content.querySelector(
            `sup.${contentEditorSelectors.classes.footnoteRef}[${contentEditorSelectors.attributes.footnoteId}='${id}']`
        );
        if (marker) {
            marker.scrollIntoView({block: 'center'});
        }
    }

    /* -------------------------------- Hover card --------------------------- */

    #buildCard() {
        this.#card = document.createElement('div');
        this.#card.classList.add(contentEditorSelectors.classes.footnotesCard, contentEditorSelectors.classes.hidden);
        document.body.appendChild(this.#card);
    }

    // Hovering an inline marker shows its note in a card, so you can read it without scrolling
    // to the list. Delegated on #content; markers hold only a text node, so mouseover/out fire
    // cleanly with no child-transition noise.
    #handleMouseOver = (e) => {
        const marker = e.target.closest && e.target.closest(`sup.${contentEditorSelectors.classes.footnoteRef}`);
        if (!marker) {
            return;
        }
        const id = marker.getAttribute(contentEditorSelectors.attributes.footnoteId);
        const note = this.#noteContentElement(id);
        const html = note ? note.innerHTML.trim() : '';

        this.#card.innerHTML = '';
        const title = document.createElement('div');
        title.classList.add(contentEditorSelectors.classes.footnotesCardTitle);
        title.textContent = `Footnote ${marker.textContent}`;
        const body = document.createElement('div');
        body.classList.add(contentEditorSelectors.classes.footnotesCardBody);
        body.innerHTML = html || '<em>Empty footnote</em>';   // note is editor-produced markup
        this.#card.append(title, body);

        this.#positionCard(marker);
    };

    #handleMouseOut = (e) => {
        if (e.target.closest && e.target.closest(`sup.${contentEditorSelectors.classes.footnoteRef}`)) {
            this.#card.classList.add(contentEditorSelectors.classes.hidden);
        }
    };

    #noteContentElement(id) {
        return this.#content.querySelector(
            `li[${contentEditorSelectors.attributes.footnoteId}='${id}'] > .${contentEditorSelectors.classes.footnotesContent}`
        );
    }

    // Below the marker, flipping above and clamping horizontally when there's no room.
    #positionCard(marker) {
        const rect = marker.getBoundingClientRect();
        this.#card.style.visibility = 'hidden';
        this.#card.classList.remove(contentEditorSelectors.classes.hidden);
        const width = this.#card.offsetWidth;
        const height = this.#card.offsetHeight;

        let top = rect.bottom + 8;
        if (top + height > window.innerHeight) {
            top = Math.max(8, rect.top - height - 8);
        }
        let left = rect.left;
        if (left + width > window.innerWidth) {
            left = Math.max(8, window.innerWidth - width - 8);
        }
        this.#card.style.top = `${top}px`;
        this.#card.style.left = `${left}px`;
        this.#card.style.visibility = '';
    }

    // Clicking a marker jumps to its note; clicking a note's number jumps back to the marker.
    #handleClick = (e) => {
        const marker = e.target.closest(`sup.${contentEditorSelectors.classes.footnoteRef}`);
        if (marker) {
            this.#focusItem(marker.getAttribute(contentEditorSelectors.attributes.footnoteId));
            return;
        }
        const backlink = e.target.closest(`.${contentEditorSelectors.classes.footnotesBacklink}`);
        if (backlink) {
            this.#scrollToMarker(backlink.getAttribute(contentEditorSelectors.attributes.footnoteId));
        }
    };

    #generateId() {
        return `fn-${Date.now().toString(36)}-${(this.#counter++).toString(36)}`;
    }

    destroy() {
        this.#eventEmitter.remove(events.contentChanged, this.#reconcile);
        if (this.#content) {
            this.#content.removeEventListener('click', this.#handleClick);
            this.#content.removeEventListener('input', this.#handleInput);
            this.#content.removeEventListener('mouseover', this.#handleMouseOver);
            this.#content.removeEventListener('mouseout', this.#handleMouseOut);
        }
        if (this.#card) {
            this.#card.remove();
            this.#card = null;
        }
        this.#content = null;
    }
}
