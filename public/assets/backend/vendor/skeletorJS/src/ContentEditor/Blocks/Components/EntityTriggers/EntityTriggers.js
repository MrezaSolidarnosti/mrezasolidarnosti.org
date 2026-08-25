import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import EntitySearch from "../../../../EntitySearch/EntitySearch.js";

export default class EntityTriggers {

    // Keyed by trigger sequence, shared across editor instances (like Revisions.PREVIEWS).
    static TRIGGERS = new Map();

    static register(trigger, definition) {
        EntityTriggers.TRIGGERS.set(trigger, {trigger, ...definition});
    }

    static unRegister(trigger) {
        EntityTriggers.TRIGGERS.delete(trigger);
    }

    #eventEmitter;
    #content = null;
    #triggers = [];    // { def, matcher, picker }
    #active = null;    // the currently open picker
    #setupComplete = false;

    constructor({eventEmitter}) {
        this.#eventEmitter = eventEmitter;
    }

    init() {
        if (this.#setupComplete || !EntityTriggers.TRIGGERS.size) {
            return;
        }
        this.#content = document.getElementById(contentEditorSelectors.ids.contentContainer);
        if (!this.#content) {
            return;
        }
        this.#triggers = [...EntityTriggers.TRIGGERS.values()]
            .filter((def) => def && def.trigger && typeof def.search === 'function')
            .map((def) => ({
                def,
                matcher: this.#buildMatcher(def),
                // Spread the definition first so a trigger can tune the popup — maxResults,
                // minChars, debounce, the state texts — then pin the parts this mode owns.
                // EntitySearch ignores keys it doesn't know (render/trigger/description/…).
                picker: new EntitySearch({
                    ...def,
                    onSelect: (item) => this.#insert(def, item),
                    showInput: false,   // inline: query comes from the editor text, focus stays put
                }).init(),
            }));
        if (!this.#triggers.length) {
            return;
        }
        this.#content.addEventListener('input', this.#handleInput);
        this.#content.addEventListener('keydown', this.#handleKeydown);
        this.#setupComplete = true;
    }

    // A regex matching `<trigger><query>` at the end of a string. The query excludes the
    // trigger's own characters (so it can't span or re-open a trigger) and newlines; unless
    // `allowSpaces`, it also stops at whitespace — a single token for `@`/`#`, a phrase for `[[`.
    #buildMatcher(def) {
        const literal = def.trigger.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const triggerChars = [...new Set(def.trigger)].join('').replace(/[\]\\^-]/g, '\\$&');
        const space = def.allowSpaces ? '' : '\\s';
        return new RegExp(`${literal}([^\\n${space}${triggerChars}]*)$`);
    }

    // The active trigger for the current caret: the registered trigger whose sequence sits
    // closest to the caret, plus its query and the on-screen rect of the whole token.
    #currentMatch() {
        const selection = window.getSelection();
        if (!selection.rangeCount || !selection.isCollapsed) {
            return null;
        }
        const range = selection.getRangeAt(0);
        const node = range.startContainer;
        if (node.nodeType !== Node.TEXT_NODE) {
            return null;
        }
        const editable = node.parentElement
            ? node.parentElement.closest(`.${contentEditorSelectors.classes.editableBlock}, .${contentEditorSelectors.classes.footnotesContent}`)
            : null;
        if (!editable || !this.#content.contains(editable)) {
            return null;
        }
        const before = node.textContent.slice(0, range.startOffset);
        let best = null;
        this.#triggers.forEach((trigger) => {
            const match = before.match(trigger.matcher);
            if (!match) {
                return;
            }
            const start = range.startOffset - match[0].length;
            if (!best || start > best.start) {
                best = {trigger, query: match[1], start, end: range.startOffset, node};
            }
        });
        if (!best) {
            return null;
        }
        const tokenRange = document.createRange();
        tokenRange.setStart(node, best.start);
        tokenRange.setEnd(node, best.end);
        best.rect = tokenRange.getBoundingClientRect();
        return best;
    }

    #handleInput = () => {
        const match = this.#currentMatch();
        if (!match) {
            this.#closeActive();
            return;
        }
        const picker = match.trigger.picker;
        if (this.#active && this.#active !== picker) {
            this.#active.close();   // a different trigger took over
        }
        this.#active = picker;
        if (picker.isOpen()) {
            picker.setQuery(match.query);
        } else {
            picker.openAt(match.rect, match.query);
        }
    };

    // The picker owns Up/Down/Enter/Esc; a horizontal caret move leaves the token, so dismiss.
    #handleKeydown = (e) => {
        if (this.#active && this.#active.isOpen()
            && (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End')) {
            this.#closeActive();
        }
    };

    #closeActive() {
        if (this.#active) {
            this.#active.close();
            this.#active = null;
        }
    }

    #insert(def, item) {
        const match = this.#currentMatch();
        if (!match || match.trigger.def !== def) {
            return;   // caret moved, or a different trigger is now active — nothing safe to rewrite
        }
        const range = document.createRange();
        range.setStart(match.node, match.start);
        range.setEnd(match.node, match.end);
        range.deleteContents();

        const fragment = this.#buildInsertion(def, item);
        const lastNode = fragment.lastChild;
        range.insertNode(fragment);

        if (lastNode) {
            const after = document.createRange();
            after.setStartAfter(lastNode);
            after.collapse(true);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(after);
        }
    }

    // The trigger owns what gets inserted: `render(item)` returns an element (safest — its
    // textContent is auto-escaped) or an HTML string (the consumer owns that markup). Without
    // a render, the item's label is inserted as plain text.
    #buildInsertion(def, item) {
        const fragment = document.createDocumentFragment();
        const rendered = typeof def.render === 'function' ? def.render(item) : null;
        if (rendered instanceof Node) {
            fragment.appendChild(rendered);
        } else if (typeof rendered === 'string') {
            const holder = document.createElement('div');
            holder.innerHTML = rendered;   // the consumer owns this markup
            while (holder.firstChild) {
                fragment.appendChild(holder.firstChild);
            }
        } else {
            const label = typeof def.labelOf === 'function' ? def.labelOf(item) : String(item);
            fragment.appendChild(document.createTextNode(label));
        }
        return fragment;
    }

    destroy() {
        if (this.#content) {
            this.#content.removeEventListener('input', this.#handleInput);
            this.#content.removeEventListener('keydown', this.#handleKeydown);
        }
        this.#triggers.forEach((trigger) => trigger.picker.destroy());
        this.#triggers = [];
        this.#active = null;
        this.#content = null;
        this.#setupComplete = false;
    }
}
