import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Platform from "../../Platform/Platform.js";
import Translator from "../../Translator/Translator.js";

/**
 * Ctrl/Cmd+K global command palette — a searchable modal, distinct from the `//` block menu.
 *
 * `//` is inline and block-scoped (it acts on the text under the caret); this is editor-wide
 * and opens from anywhere. It is entirely developer-populated — nothing ships built in — from
 * three sources:
 *
 *   - **Commands** (`registerCommand`) — static, client-side entries grouped by `category`
 *     (Navigation, This post, …). Filtered locally as you type.
 *   - **Sections** (`registerSection`) — a titled group whose items come from an endpoint you
 *     choose (e.g. "the last 5 posts"). Shown while browsing, i.e. before anything is typed.
 *   - **Search** (the `search` endpoint) — the backend's own search surface. It decides what is
 *     searchable and returns results, optionally tagged with a `category` to group them.
 *
 * An item carries either a `url` (Enter navigates; Ctrl/Cmd+Enter opens a new tab) or an
 * `onSelect` callback. Everything is keyboard-drivable; the footer states the new-tab combo.
 */
export default class CommandPalette {

    static COMMANDS = new Map();
    static SECTIONS = new Map();

    /**
     * A static command. `{category, key, label, icon?, keywords?, url?, onSelect?, isVisible?}`.
     * Needs a `key` and at least one of `url` / `onSelect`. Re-registering a key replaces it.
     */
    static registerCommand(definition) {
        if (!definition || !definition.key) {
            throw new Error('A command needs a `key`.');
        }
        if (!definition.url && typeof definition.onSelect !== 'function') {
            throw new Error(`Command "${definition.key}" needs a \`url\` or an \`onSelect\` function.`);
        }
        CommandPalette.COMMANDS.set(definition.key, definition);
    }

    static unRegisterCommand(key) {
        CommandPalette.COMMANDS.delete(key);
    }

    /**
     * A browse section backed by an endpoint. `{key, title, load, icon?}` where
     * `load(query)` returns a promise of item objects. Re-registering a key replaces it.
     */
    static registerSection(definition) {
        if (!definition || !definition.key || typeof definition.load !== 'function') {
            throw new Error('A section needs a `key` and a `load` function.');
        }
        CommandPalette.SECTIONS.set(definition.key, definition);
    }

    static unRegisterSection(key) {
        CommandPalette.SECTIONS.delete(key);
    }

    #setupComplete = false;
    #eventEmitter;
    #enabled;
    #search;

    #dialog = null;
    #input = null;
    #body = null;
    #footer = null;
    #groups = [];       // the group descriptors currently painted
    #itemEls = [];      // {el, item} in visual order — the flat list the keyboard walks
    #activeIndex = -1;
    #query = '';
    #requestId = 0;     // monotonic; a newer query/open invalidates in-flight loads
    #debounceTimer = null;

    constructor({eventEmitter, enabled = false, search = null}) {
        this.#eventEmitter = eventEmitter;
        this.#enabled = enabled;
        this.#search = typeof search === 'function' ? search : null;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#build();
        this.#setupComplete = true;
    }

    // The backend search endpoint — `async (query) => items[]`. Settable at runtime so a project
    // can wire it up after construction (the config path passes it in instead).
    setSearch(search) {
        this.#search = typeof search === 'function' ? search : null;
    }

    setEnabled(enabled) {
        this.#enabled = !!enabled;
        if (!this.#enabled) {
            this.close();
        }
    }

    isEnabled() {
        return this.#enabled;
    }

    isOpen() {
        return !!this.#dialog && this.#dialog.open;
    }

    toggle() {
        this.isOpen() ? this.close() : this.open();
    }

    open() {
        if (!this.#enabled || !this.#dialog || this.isOpen()) {
            return;
        }
        this.#input.value = '';
        this.#dialog.showModal();
        this.#input.focus();
        this.#runQuery('');
    }

    close() {
        if (this.isOpen()) {
            this.#dialog.close();
        }
    }


    #build() {
        const c = contentEditorSelectors.classes;
        this.#dialog = document.createElement('dialog');
        this.#dialog.classList.add(c.commandPalette);

        const header = document.createElement('div');
        header.classList.add(c.commandPaletteHeader);
        const searchIcon = document.createElement('span');
        searchIcon.classList.add(c.commandPaletteSearchIcon);
        searchIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376C296.3 401.1 253.9 416 208 416 93.1 416 0 322.9 0 208S93.1 0 208 0 416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path></svg>`;
        this.#input = document.createElement('input');
        this.#input.type = 'text';
        this.#input.classList.add(c.commandPaletteInput);
        this.#input.placeholder = Translator.translate('Type a command or search…');
        this.#input.spellcheck = false;
        header.append(searchIcon, this.#input);

        this.#body = document.createElement('div');
        this.#body.classList.add(c.commandPaletteBody);

        this.#footer = document.createElement('div');
        this.#footer.classList.add(c.commandPaletteFooter);
        this.#footer.innerHTML = this.#footerHtml();

        this.#dialog.append(header, this.#body, this.#footer);
        document.body.appendChild(this.#dialog);

        this.#input.addEventListener('input', this.#handleInput);
        this.#input.addEventListener('keydown', this.#handleKeydown);
        this.#body.addEventListener('mousemove', this.#handleBodyMouseMove);
        this.#body.addEventListener('click', this.#handleBodyClick);
        this.#dialog.addEventListener('mousedown', this.#handleBackdrop);
        this.#dialog.addEventListener('close', this.#handleClose);
    }

    #footerHtml() {
        const kbd = (text) => `<span class="${contentEditorSelectors.classes.commandPaletteKbd}">${text}</span>`;
        const mod = Platform.primaryModifier();
        return `${kbd('↑')}${kbd('↓')} ${Translator.translate('navigate')} &nbsp; ${kbd('↵')} ${Translator.translate('open')} &nbsp; `
            + `${kbd(`${mod}+↵`)} ${Translator.translate('new tab')} &nbsp; ${kbd('Esc')} ${Translator.translate('close')}`;
    }


    #handleInput = () => {
        clearTimeout(this.#debounceTimer);
        const value = this.#input.value;
        // Local command filtering is instant; only the backend hop is worth debouncing.
        if (value.trim() === '') {
            this.#runQuery('');
        } else {
            this.#debounceTimer = setTimeout(() => this.#runQuery(value), 150);
        }
    };

    #runQuery(query) {
        const requestId = ++this.#requestId;
        this.#query = query;
        const browse = query.trim() === '';

        // Commands are synchronous — paint them at once so typing feels immediate.
        const groups = this.#commandGroups(query, browse);
        this.#paint(groups, true);

        // Then fold in the async sources as they resolve, if still current.
        const pending = browse ? this.#loadSections() : this.#loadSearch(query);
        pending.forEach((promise) => {
            promise.then((resolvedGroups) => {
                if (requestId !== this.#requestId) {
                    return;
                }
                if (resolvedGroups.length) {
                    this.#paint([...this.#currentGroups(), ...resolvedGroups], false);
                } else {
                    this.#paint(this.#currentGroups(), false);   // re-evaluate the empty state
                }
            }).catch(() => { /* a failed source just contributes nothing */ });
        });
    }

    // Command definitions grouped by category, filtered by the query and their own isVisible.
    #commandGroups(query, browse) {
        const needle = query.trim().toLowerCase();
        const byCategory = new Map();
        CommandPalette.COMMANDS.forEach((command) => {
            if (typeof command.isVisible === 'function' && !command.isVisible()) {
                return;
            }
            if (!browse && !this.#matches(command, needle)) {
                return;
            }
            const category = command.category || 'Commands';
            if (!byCategory.has(category)) {
                byCategory.set(category, []);
            }
            byCategory.get(category).push(this.#normalize(command));
        });
        return [...byCategory.entries()].map(([title, items]) => ({title, items}));
    }

    #matches(command, needle) {
        const haystack = [Translator.translate(command.label || ''), command.label || '',
            command.key, ...(command.keywords || [])];
        return haystack.some((entry) => String(entry).toLowerCase().includes(needle));
    }

    // Each section becomes a promise of a (possibly empty) one-group array.
    #loadSections() {
        return [...CommandPalette.SECTIONS.values()].map((section) => (
            Promise.resolve(section.load(this.#query))
                .then((items) => this.#toGroups(items, section.title))
        ));
    }

    // The backend search endpoint becomes a promise of groups, split by each result's category.
    #loadSearch(query) {
        if (!this.#search) {
            return [];
        }
        return [
            Promise.resolve(this.#search(query)).then((items) => this.#toGroups(items, 'Results')),
        ];
    }

    // Normalize an item array, then split into groups by each item's `category` (falling back
    // to `fallbackTitle`), preserving first-seen order.
    #toGroups(items, fallbackTitle) {
        const list = Array.isArray(items) ? items : [];
        const byCategory = new Map();
        list.forEach((raw) => {
            const item = this.#normalize(raw);
            const title = raw.category || fallbackTitle;
            if (!byCategory.has(title)) {
                byCategory.set(title, []);
            }
            byCategory.get(title).push(item);
        });
        return [...byCategory.entries()].map(([title, groupItems]) => ({title, items: groupItems}));
    }

    #normalize(raw) {
        return {
            label: raw.label || raw.title || '',
            subtitle: raw.subtitle || '',
            icon: raw.icon || '',
            url: raw.url || null,
            onSelect: typeof raw.onSelect === 'function' ? raw.onSelect : null,
        };
    }


    #currentGroups() {
        return this.#groups || [];
    }

    #paint(groups, resetActive) {
        this.#groups = groups;
        const c = contentEditorSelectors.classes;
        const previousIndex = this.#activeIndex;
        this.#body.innerHTML = '';
        this.#itemEls = [];

        groups.forEach((group) => {
            const groupEl = document.createElement('div');
            groupEl.classList.add(c.commandPaletteGroup);
            const titleEl = document.createElement('div');
            titleEl.classList.add(c.commandPaletteGroupTitle);
            titleEl.textContent = Translator.translate(group.title);
            groupEl.appendChild(titleEl);
            group.items.forEach((item) => {
                const {el, isLink} = this.#renderItem(item);
                groupEl.appendChild(el);
                this.#itemEls.push({el, item, isLink});
            });
            this.#body.appendChild(groupEl);
        });

        if (!this.#itemEls.length) {
            const state = document.createElement('div');
            state.classList.add(c.commandPaletteState);
            state.textContent = this.#query.trim() ? Translator.translate('No results') : Translator.translate('Type to search');
            this.#body.appendChild(state);
            this.#activeIndex = -1;
            return;
        }
        const next = resetActive ? 0 : Math.min(Math.max(previousIndex, 0), this.#itemEls.length - 1);
        this.#setActive(next);
    }

    // `url` may be a string or a `() => string` — the latter lets a command whose target depends
    // on live state (the current slug, say) still be a link rather than an onSelect action.
    #resolveUrl(item) {
        try {
            return typeof item.url === 'function' ? item.url() : item.url;
        } catch (error) {
            return null;
        }
    }

    #renderItem(item) {
        const c = contentEditorSelectors.classes;
        // A link becomes a real <a href> so the browser's own affordances work — right-click
        // menu, middle-click / Ctrl-click to open in a new tab, drag-to-bookmark, hover status.
        // tabIndex=-1 keeps it out of the Tab order (arrows drive selection instead).
        const href = this.#resolveUrl(item);
        const isLink = !!href && !item.onSelect;
        const el = document.createElement(isLink ? 'a' : 'div');
        el.classList.add(c.commandPaletteItem);
        if (isLink) {
            el.href = href;
            el.tabIndex = -1;
        }

        if (item.icon) {
            const icon = document.createElement('span');
            icon.classList.add(c.commandPaletteItemIcon);
            icon.innerHTML = item.icon;
            el.appendChild(icon);
        }
        const text = document.createElement('span');
        text.classList.add(c.commandPaletteItemText);
        const label = document.createElement('span');
        label.classList.add(c.commandPaletteItemLabel);
        label.textContent = Translator.translate(item.label);
        text.appendChild(label);
        if (item.subtitle) {
            const sub = document.createElement('span');
            sub.classList.add(c.commandPaletteItemSub);
            sub.textContent = Translator.translate(item.subtitle);
            text.appendChild(sub);
        }
        el.appendChild(text);
        return {el, isLink};
    }

    #setActive(index) {
        const activeClass = contentEditorSelectors.classes.commandPaletteItemActive;
        if (this.#itemEls[this.#activeIndex]) {
            this.#itemEls[this.#activeIndex].el.classList.remove(activeClass);
        }
        this.#activeIndex = index;
        const current = this.#itemEls[index];
        if (current) {
            current.el.classList.add(activeClass);
            current.el.scrollIntoView({block: 'nearest'});
        }
    }

    #move(delta) {
        if (!this.#itemEls.length) {
            return;
        }
        const count = this.#itemEls.length;
        this.#setActive((this.#activeIndex + delta + count) % count);
    }


    #handleKeydown = (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.#move(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.#move(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const current = this.#itemEls[this.#activeIndex];
            if (current) {
                this.#activate(current.item, e.ctrlKey || e.metaKey);
            }
        }
        // Escape is handled by the native dialog (fires `close`).
    };

    #handleBodyMouseMove = (e) => {
        const row = e.target.closest(`.${contentEditorSelectors.classes.commandPaletteItem}`);
        if (!row) {
            return;
        }
        const index = this.#itemEls.findIndex((entry) => entry.el === row);
        if (index !== -1 && index !== this.#activeIndex) {
            this.#setActive(index);
        }
    };

    #handleBodyClick = (e) => {
        const row = e.target.closest(`.${contentEditorSelectors.classes.commandPaletteItem}`);
        if (!row) {
            return;
        }
        const entry = this.#itemEls.find((candidate) => candidate.el === row);
        if (!entry) {
            return;
        }
        // A link row is a real <a> — let the browser navigate it (plain click same tab,
        // Ctrl/Cmd/middle-click a new tab). Only action rows need JS to fire.
        if (entry.isLink) {
            return;
        }
        this.#activate(entry.item, e.ctrlKey || e.metaKey);
    };

    // A mousedown on the dialog itself (not its content) is a backdrop click — close.
    #handleBackdrop = (e) => {
        if (e.target === this.#dialog) {
            this.close();
        }
    };

    #handleClose = () => {
        this.#requestId++;   // drop any in-flight loads
        this.#body.innerHTML = '';
        this.#itemEls = [];
        this.#activeIndex = -1;
    };

    #activate(item, newTab) {
        if (!item) {
            return;
        }
        if (item.onSelect) {
            item.onSelect({newTab, close: () => this.close()});
            this.close();
            return;
        }
        const href = this.#resolveUrl(item);
        if (href) {
            if (newTab) {
                window.open(href, '_blank', 'noopener');
            } else {
                window.location.assign(href);
            }
            this.close();
        }
    }

    destroy() {
        clearTimeout(this.#debounceTimer);
        if (this.#dialog) {
            this.#input.removeEventListener('input', this.#handleInput);
            this.#input.removeEventListener('keydown', this.#handleKeydown);
            this.#body.removeEventListener('mousemove', this.#handleBodyMouseMove);
            this.#body.removeEventListener('click', this.#handleBodyClick);
            this.#dialog.removeEventListener('mousedown', this.#handleBackdrop);
            this.#dialog.removeEventListener('close', this.#handleClose);
            this.#dialog.remove();
        }
        this.#dialog = null;
        this.#input = null;
        this.#body = null;
        this.#footer = null;
        this.#itemEls = [];
        this.#eventEmitter = null;
        this.#search = null;
        this.#setupComplete = false;
    }
}
