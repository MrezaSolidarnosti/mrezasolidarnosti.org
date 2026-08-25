import EntitySearch from "../../EntitySearch/EntitySearch.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import Translator from "../../Translator/Translator.js";

const DEFAULT_TRIGGER = '//';

export default class CommandMenu {

    static COMMANDS = new Map();

    static register(definition) {
        if (!definition || !definition.key || typeof definition.onSelect !== 'function') {
            throw new Error('A command needs a `key` and an `onSelect` function.');
        }
        CommandMenu.COMMANDS.set(definition.key, definition);
    }

    static unRegister(key) {
        CommandMenu.COMMANDS.delete(key);
    }

    #setupComplete = false;
    #eventEmitter;
    #blocks;
    #content = null;
    #picker = null;
    #trigger;
    #matcher;

    constructor({eventEmitter, blocks, trigger}) {
        this.#eventEmitter = eventEmitter;
        this.#blocks = blocks;
        this.setTrigger(trigger || DEFAULT_TRIGGER);
    }

    /**
     * Change the sequence that opens the menu — `//` by default, but a project whose authors
     * write a lot of URLs or code may prefer something else. Safe to call at any time; the
     * matcher is rebuilt from it.
     */
    setTrigger(trigger) {
        if (typeof trigger !== 'string' || !trigger.trim() || /\s/.test(trigger)) {
            throw new Error('A command trigger must be a non-empty string with no whitespace.');
        }
        this.#trigger = trigger;
        // The query runs to the caret but stops at a newline or at the trigger's own first
        // character, so a second trigger starts a fresh token instead of extending the old one.
        this.#matcher = new RegExp(`${escapeForRegex(trigger)}([^\\n${escapeForClass(trigger[0])}]*)$`);
    }

    getTrigger() {
        return this.#trigger;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#content = document.getElementById(contentEditorSelectors.ids.contentContainer);
        if (!this.#content) {
            return;
        }
        // The popup is the shared EntitySearch component, in its inline mode: the query comes
        // from the typed text, so focus never leaves the block and the caret stays put while
        // the arrows and Enter drive the list.
        this.#picker = new EntitySearch({
            search: (query) => this.#find(query),
            labelOf: (command) => Translator.translate(command.label || command.key),
            renderResult: (command) => this.#renderRow(command),
            onSelect: (command) => this.#run(command),
            showInput: false,
            minChars: 0,      // `//` alone lists everything
            instant: true,    // commands are a local array — filter in place, no loading flash
            emptyText: Translator.translate('No commands'),
            className: contentEditorSelectors.classes.commandMenu,
            maxResults: 99,
        }).init();
        this.#content.addEventListener('input', this.#handleInput);
        this.#content.addEventListener('keydown', this.#handleKeydown);
        this.#setupComplete = true;
    }

    /**
     * A typed token splits into the command name and its argument at the first space:
     * `//uppercase some text` is the command `uppercase` with the argument 'some text'.
     * Only the first word filters the list — otherwise typing an argument would narrow the
     * results to nothing and close the menu before the command could be picked. The trade-off
     * is that a multi-word label can't be searched as a phrase.
     */
    #split(query) {
        const trimmed = query.replace(/^\s+/, '');
        const boundary = trimmed.search(/\s/);
        return boundary === -1
            ? {name: trimmed, argument: ''}
            : {name: trimmed.slice(0, boundary), argument: trimmed.slice(boundary + 1)};
    }

    // Commands matching the typed name, best match first.
    #find(query) {
        const needle = this.#split(query).name.toLowerCase();
        const visible = [...CommandMenu.COMMANDS.values()].filter((command) => (
            typeof command.isVisible !== 'function'
            || command.isVisible({block: this.#blocks.activeBlock})
        ));
        if (!needle) {
            return visible;   // `//` alone lists everything, in registration order
        }
        const matches = [];
        visible.forEach((command, order) => {
            const rank = this.#rank(command, needle);
            if (rank !== null) {
                matches.push({command, rank, order});
            }
        });
        // Registration order breaks ties, so an equally-good match keeps its declared position.
        matches.sort((a, b) => a.rank - b.rank || a.order - b.order);
        return matches.map((match) => match.command);
    }

    /**
     * How well a command matches, lowest first, or null for no match at all.
     *
     * Substring matching alone puts unrelated commands above the obvious one: typing `u` hits
     * the `u` in "Duplicate" and the one in Save's `publish` keyword just as readily as it hits
     * "Uppercase", and whichever registered first wins the highlighted row — so Enter runs the
     * wrong command. Ranking keeps those matches (a partial word should still find things)
     * while sorting them below the command whose *name* actually starts that way.
     */
    #rank(command, needle) {
        const key = String(command.key).toLowerCase();
        const label = String(command.label || '').toLowerCase();
        const translated = Translator.translate(String(command.label || '')).toLowerCase();
        if (key.startsWith(needle) || label.startsWith(needle) || translated.startsWith(needle)) {
            return 0;   // the command's own name starts this way
        }
        const terms = [key, label, translated, ...(command.keywords || []).map(String)];
        if (terms.some((term) => words(term).some((word) => word.startsWith(needle)))) {
            return 1;   // some word of it starts this way
        }
        if (terms.some((term) => term.toLowerCase().includes(needle))) {
            return 2;   // it appears somewhere inside
        }
        return null;
    }

    #renderRow(command) {
        const label = this.#escape(Translator.translate(command.label || command.key));
        return command.icon
            ? `${command.icon}<span>${label}</span>`
            : `<span>${label}</span>`;
    }

    #escape(value) {
        const holder = document.createElement('div');
        holder.textContent = String(value ?? '');
        return holder.innerHTML;
    }

    #handleInput = () => {
        // Every command mutates something, so there is nothing to offer in a read-only editor.
        if (this.#blocks.isReadOnly()) {
            return;
        }
        const match = this.#currentMatch();
        if (!match) {
            this.#picker.close();
            return;
        }
        if (this.#picker.isOpen()) {
            this.#picker.setQuery(match.query);
        } else {
            this.#picker.openAt(match.rect, match.query);
        }
    };

    // The picker owns Up/Down/Enter/Esc; a horizontal caret move leaves the token, so dismiss.
    #handleKeydown = (e) => {
        if (this.#picker.isOpen()
            && (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'Home' || e.key === 'End')) {
            this.#picker.close();
        }
    };

    // The `//query` token immediately before the caret, if there is one.
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
        const found = before.match(this.#matcher);
        if (!found) {
            return null;
        }
        const start = range.startOffset - found[0].length;
        // Don't fire inside a URL: the `//` of `https://` is preceded by a colon. Only a
        // slash-leading trigger can collide that way, so other triggers keep their colons.
        if (this.#trigger.startsWith('/') && before[start - 1] === ':') {
            return null;
        }
        const tokenRange = document.createRange();
        tokenRange.setStart(node, start);
        tokenRange.setEnd(node, range.startOffset);
        return {query: found[1], start, end: range.startOffset, node, rect: tokenRange.getBoundingClientRect()};
    }

    // Remove the typed token first, then hand over. The caret is left where the token was, so a
    // command can insert straight at it — or ignore it entirely and just act.
    #run(command) {
        const match = this.#currentMatch();
        if (!match) {
            return;
        }
        const range = document.createRange();
        range.setStart(match.node, match.start);
        range.setEnd(match.node, match.end);
        range.deleteContents();
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        command.onSelect({
            block: this.#blocks.activeBlock,
            // Whatever was typed after the command name — `//uppercase some text` gives
            // 'some text'. Empty string when the command was invoked bare.
            query: this.#split(match.query).argument,
            insert: (content) => this.#insert(content),
        });
    }

    // Drop content at the caret — a node, or a string inserted as plain text.
    #insert(content) {
        const selection = window.getSelection();
        if (!selection.rangeCount) {
            return;
        }
        const range = selection.getRangeAt(0);
        const node = content instanceof Node ? content : document.createTextNode(String(content));
        range.insertNode(node);
        const after = document.createRange();
        after.setStartAfter(node);
        after.collapse(true);
        selection.removeAllRanges();
        selection.addRange(after);
    }

    destroy() {
        if (this.#content) {
            this.#content.removeEventListener('input', this.#handleInput);
            this.#content.removeEventListener('keydown', this.#handleKeydown);
        }
        if (this.#picker) {
            this.#picker.destroy();
        }
        this.#picker = null;
        this.#content = null;
        this.#eventEmitter = null;
        this.#blocks = null;
        this.#setupComplete = false;
    }
}

// Word starts, so "read-more" and "block_id" match on either half.
function words(term) {
    return term.toLowerCase().split(/[\s\-_/]+/).filter(Boolean);
}

function escapeForRegex(value) {
    return value.replace(/[.*+?^${}()|[\]\\/-]/g, '\\$&');
}

// Inside a character class a different, smaller set is special.
function escapeForClass(value) {
    return value.replace(/[\\\]^-]/g, '\\$&');
}
