import {entitySearchSelectors} from "./entitySearchSelectors.js";
import {positionPopup} from "../PositionPopup/positionPopup.js";

/**
 * A backend-agnostic search popup: a floating list of results for a query, with debounce,
 * async race-guarding, keyboard navigation, and mouse selection.
 *
 * It knows nothing about URLs or response shapes — the consumer injects `search(query)`,
 * which returns a promise of any array, and `labelOf`/`renderResult` to display a row. Same
 * philosophy as DiffViewer: the domain lives in the callbacks, not the component.
 *
 *   const picker = new EntitySearch({
 *       search:  async (q) => (await fetch(`/posts?q=${encodeURIComponent(q)}`)).json(),
 *       labelOf: (post) => post.title,
 *       onSelect: (post) => insertLink(post),
 *   }).init();
 *   picker.openAt(caretRect, 'que');   // a DOMRect or an element
 *
 * `showInput: false` (the inline / [[ mode) hides the internal field and drives the query
 * externally via setQuery(), so focus can stay in the host editor while the arrows, Enter and
 * Escape still control the list (handled on a capturing document listener while open).
 */
export default class EntitySearch {

    #search;
    #labelOf;
    #renderResult;
    #onSelect;
    #onClose;
    #container;
    #options;

    #root = null;
    #input = null;
    #list = null;
    #results = [];
    #query = '';
    #activeIndex = -1;
    #requestId = 0;      // monotonic — the newest search wins, stale responses are dropped
    #debounceTimer = null;
    #open = false;

    constructor({
        search,
        labelOf = null,
        renderResult = null,
        onSelect = null,
        onClose = null,
        container = document.body,
        ...options
    } = {}) {
        this.#search = search;
        this.#labelOf = labelOf || ((item) => String(item));
        this.#renderResult = renderResult;
        this.#onSelect = onSelect || (() => {});
        this.#onClose = onClose;
        this.#container = container;
        this.#options = {
            placeholder: 'Search…',
            minChars: 1,
            debounce: 200,
            hintText: 'Type to search…',
            loadingText: 'Searching…',
            emptyText: 'No results',
            errorText: 'Search failed',
            maxResults: 8,
            showInput: true,
            instant: false,    // local/synchronous data: skip debounce + loading state (no flicker)
            className: null,   // extra class(es) on the root, so a consumer can style its own popup
            ...options,
        };
    }

    init() {
        if (this.#root) {
            return this;
        }
        this.#root = document.createElement('div');
        this.#root.classList.add(entitySearchSelectors.classes.root, entitySearchSelectors.classes.hidden);
        // Extra consumer class(es) — string or array — so a popup (e.g. the command menu) can
        // be styled without touching the shared base class.
        const extra = this.#options.className;
        (Array.isArray(extra) ? extra : [extra])
            .filter(Boolean)
            .forEach((name) => this.#root.classList.add(name));

        if (this.#options.showInput) {
            this.#input = document.createElement('input');
            this.#input.type = 'text';
            this.#input.classList.add(entitySearchSelectors.classes.input);
            this.#input.placeholder = this.#options.placeholder;
            this.#input.addEventListener('input', this.#handleInput);
            this.#root.appendChild(this.#input);
        }

        this.#list = document.createElement('div');
        this.#list.classList.add(entitySearchSelectors.classes.list);
        this.#list.addEventListener('mousedown', this.#handleListMouseDown);
        this.#list.addEventListener('mousemove', this.#handleListMouseMove);
        this.#root.appendChild(this.#list);

        this.#container.appendChild(this.#root);
        return this;
    }

    /* -------------------------------- Control ------------------------------ */

    // anchor: a DOMRect or an element to position below (flips above if there's no room).
    openAt(anchor, query = '') {
        this.init();
        this.#open = true;
        document.addEventListener('keydown', this.#handleKeyDown, true);
        document.addEventListener('mousedown', this.#handleOutside, true);
        this.#position(anchor);
        if (this.#input) {
            this.#input.value = query;
            this.#input.focus();
        }
        this.setQuery(query);
        return this;
    }

    // Update the query without reopening — the inline consumer calls this as the user types.
    setQuery(query) {
        this.#query = query;
        clearTimeout(this.#debounceTimer);
        if (query.length < this.#options.minChars) {
            this.#results = [];
            this.#renderState(this.#options.hintText);
            return;
        }
        if (this.#options.instant) {
            // Local/synchronous data: no debounce and no loading state. Running straight through
            // keeps the previous rows on screen until the new ones replace them, so typing
            // doesn't flash "Searching…" through an empty list on every keystroke.
            this.#runSearch(query);
            return;
        }
        this.#renderState(this.#options.loadingText);
        this.#debounceTimer = setTimeout(() => this.#runSearch(query), this.#options.debounce);
    }

    isOpen() {
        return this.#open;
    }

    getContainer() {
        return this.#root;
    }

    close() {
        if (!this.#open) {
            return;
        }
        this.#open = false;
        this.#root.classList.add(entitySearchSelectors.classes.hidden);
        clearTimeout(this.#debounceTimer);
        this.#requestId++;   // invalidate any in-flight search so it can't render after close
        document.removeEventListener('keydown', this.#handleKeyDown, true);
        document.removeEventListener('mousedown', this.#handleOutside, true);
        if (this.#onClose) {
            this.#onClose();
        }
    }

    destroy() {
        this.close();
        if (this.#input) {
            this.#input.removeEventListener('input', this.#handleInput);
        }
        if (this.#list) {
            this.#list.removeEventListener('mousedown', this.#handleListMouseDown);
            this.#list.removeEventListener('mousemove', this.#handleListMouseMove);
        }
        if (this.#root) {
            this.#root.remove();
        }
        this.#root = null;
        this.#input = null;
        this.#list = null;
        this.#results = [];
    }

    /* -------------------------------- Search ------------------------------- */

    async #runSearch(query) {
        const requestId = ++this.#requestId;
        try {
            const results = await this.#search(query);
            if (requestId !== this.#requestId || !this.#open) {
                return;   // a newer search started, or the popup closed, while this was in flight
            }
            this.#results = Array.isArray(results) ? results.slice(0, this.#options.maxResults) : [];
            this.#renderResults();
        } catch (error) {
            if (requestId !== this.#requestId || !this.#open) {
                return;
            }
            this.#results = [];
            this.#renderState(this.#options.errorText);
        }
    }

    /* -------------------------------- Render ------------------------------- */

    #renderResults() {
        this.#list.innerHTML = '';
        if (!this.#results.length) {
            this.#renderState(this.#options.emptyText);
            return;
        }
        this.#results.forEach((item, index) => {
            const row = document.createElement('div');
            row.classList.add(entitySearchSelectors.classes.result);
            row.setAttribute(entitySearchSelectors.attributes.index, String(index));
            if (this.#renderResult) {
                row.innerHTML = this.#renderResult(item);   // consumer owns this markup
            } else {
                row.textContent = this.#labelOf(item);
            }
            this.#list.appendChild(row);
        });
        this.#setActive(0);
    }

    #renderState(text) {
        this.#list.innerHTML = '';
        this.#activeIndex = -1;
        const state = document.createElement('div');
        state.classList.add(entitySearchSelectors.classes.state);
        state.textContent = text;
        this.#list.appendChild(state);
    }

    #setActive(index) {
        this.#activeIndex = index;
        const rows = this.#list.querySelectorAll(`.${entitySearchSelectors.classes.result}`);
        rows.forEach((row, i) => row.classList.toggle(entitySearchSelectors.classes.resultActive, i === index));
        if (rows[index]) {
            rows[index].scrollIntoView({block: 'nearest'});
        }
    }

    #move(delta) {
        if (!this.#results.length) {
            return;
        }
        const count = this.#results.length;
        this.#setActive((this.#activeIndex + delta + count) % count);
    }

    #choose(item) {
        if (!item) {
            return;
        }
        this.close();
        this.#onSelect(item);
    }

    /* -------------------------------- Events ------------------------------- */

    #handleInput = () => {
        this.setQuery(this.#input.value);
    };

    // Capturing, so it wins over the host editor's own Enter/Arrow handling while open.
    #handleKeyDown = (e) => {
        if (!this.#open) {
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            // stopPropagation as well as preventDefault: while the list is up the arrows belong
            // to it alone. Without this the host still sees them — in the editor that means the
            // keystroke both moves the highlight *and* jumps to the next block.
            e.preventDefault();
            e.stopPropagation();
            this.#move(e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Enter') {
            if (this.#activeIndex >= 0 && this.#results[this.#activeIndex]) {
                e.preventDefault();
                e.stopPropagation();
                this.#choose(this.#results[this.#activeIndex]);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            this.close();
        }
    };

    #handleListMouseDown = (e) => {
        const row = e.target.closest(`.${entitySearchSelectors.classes.result}`);
        if (!row) {
            return;
        }
        e.preventDefault();   // don't blur the host editor before we act
        this.#choose(this.#results[Number(row.getAttribute(entitySearchSelectors.attributes.index))]);
    };

    #handleListMouseMove = (e) => {
        const row = e.target.closest(`.${entitySearchSelectors.classes.result}`);
        if (row) {
            this.#setActive(Number(row.getAttribute(entitySearchSelectors.attributes.index)));
        }
    };

    // A pointer down anywhere outside the popup dismisses it. Selecting a row is a mousedown
    // inside the popup, so it is excluded here and handled by #handleListMouseDown.
    #handleOutside = (e) => {
        if (this.#open && !this.#root.contains(e.target)) {
            this.close();
        }
    };

    /* ------------------------------- Position ------------------------------ */

    #position(anchor) {
        // Reveal invisibly first so it can be measured, then place and show.
        this.#root.style.visibility = 'hidden';
        this.#root.classList.remove(entitySearchSelectors.classes.hidden);
        positionPopup(this.#root, anchor);
        this.#root.style.visibility = '';
    }
}
