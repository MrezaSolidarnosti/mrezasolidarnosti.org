import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import Modal from "../../../../Modal/Modal.js";
import Translator from "../../../../Translator/Translator.js";

const ICONS = {
    bold: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M272-200v-560h221q65 0 120 40t55 111q0 51-23 78.5T602-491q25 11 55.5 41t30.5 90q0 89-65 124.5T501-200H272Zm121-112h104q48 0 58.5-24.5T566-372q0-11-10.5-35.5T494-432H393v120Zm0-228h93q33 0 48-17t15-38q0-24-17-39t-44-15h-95v109Z"/></svg>',
    italic: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-200v-100h160l120-360H320v-100h400v100H580L460-300h140v100H200Z"/></svg>',
    underline: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120v-80h560v80H200Zm123-223q-56-63-56-167v-330h103v336q0 56 28 91t82 35q54 0 82-35t28-91v-336h103v330q0 104-56 167t-157 63q-101 0-157-63Z"/></svg>',
    link: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-280H280q-83 0-141.5-58.5T80-480q0-83 58.5-141.5T280-680h160v80H280q-50 0-85 35t-35 85q0 50 35 85t85 35h160v80ZM320-440v-80h320v80H320Zm200 160v-80h160q50 0 85-35t35-85q0-50-35-85t-85-35H520v-80h160q83 0 141.5 58.5T880-480q0 83-58.5 141.5T680-280H520Z"/></svg>',
    superscript: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M760-600v-80q0-17 11.5-28.5T800-720h80v-40H760v-40h120q17 0 28.5 11.5T920-760v40q0 17-11.5 28.5T880-680h-80v40h120v40H760ZM235-160l185-291-172-269h106l124 200h4l123-200h107L539-451l186 291H618L482-377h-4L342-160H235Z"/></svg>',
    subscript: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M760-160v-80q0-17 11.5-28.5T800-280h80v-40H760v-40h120q17 0 28.5 11.5T920-320v40q0 17-11.5 28.5T880-240h-80v40h120v40H760Zm-525-80 185-291-172-269h106l124 200h4l123-200h107L539-531l186 291H618L482-457h-4L342-240H235Z"/></svg>',
    strikethrough: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M80-400v-80h800v80H80Zm340-160v-120H200v-120h560v120H540v120H420Zm0 400v-160h120v160H420Z"/></svg>',
    highlight: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M544-400 440-504 240-304l104 104 200-200Zm-47-161 104 104 199-199-104-104-199 199Zm-84-28 216 216-229 229q-24 24-56 24t-56-24l-2-2-26 26H60l126-126-2-2q-24-24-24-56t24-56l229-229Zm0 0 227-227q24-24 56-24t56 24l104 104q24 24 24 56t-24 56L629-373 413-589Z"/></svg>',
    more: '<svg class="" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M201.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 338.7 54.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"></path></svg>',
    alignLeft: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#e3e3e3"><rect x="3" y="5" width="18" height="2" rx="1"/><rect x="3" y="9" width="10" height="2" rx="1"/><rect x="3" y="13" width="18" height="2" rx="1"/><rect x="3" y="17" width="10" height="2" rx="1"/></svg>',
    alignCenter: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#e3e3e3"><rect x="3" y="5" width="18" height="2" rx="1"/><rect x="7" y="9" width="10" height="2" rx="1"/><rect x="3" y="13" width="18" height="2" rx="1"/><rect x="7" y="17" width="10" height="2" rx="1"/></svg>',
    alignRight: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#e3e3e3"><rect x="3" y="5" width="18" height="2" rx="1"/><rect x="11" y="9" width="10" height="2" rx="1"/><rect x="3" y="13" width="18" height="2" rx="1"/><rect x="11" y="17" width="10" height="2" rx="1"/></svg>'
};

const ALIGNMENTS = ['left', 'center', 'right'];

// The main row stays the everyday formats (bold/italic/underline/link) plus block alignment;
// everything else lives behind the chevron so the toolbar doesn't grow without bound.
const MENU_COMMANDS = [
    {command: 'strikethrough', icon: 'strikethrough', label: 'Strikethrough'},
    {command: 'highlight', icon: 'highlight', label: 'Highlight'},
    {command: 'superscript', icon: 'superscript', label: 'Superscript'},
    {command: 'subscript', icon: 'subscript', label: 'Subscript'},
];

// Inline commands applied by wrapping the selection rather than via execCommand.
const INLINE_TAGS = Object.freeze({
    superscript: 'sup',
    subscript: 'sub',
    strikethrough: 's',
    highlight: 'mark',
});

export default class FormatToolbar {
    static BUTTONS = new Map();

    static registerButton(definition) {
        if (!definition || !definition.key || typeof definition.onClick !== 'function') {
            throw new Error('A format toolbar button needs a `key` and an `onClick`.');
        }
        FormatToolbar.BUTTONS.set(definition.key, definition);
    }

    static unRegisterButton(key) {
        FormatToolbar.BUTTONS.delete(key);
    }

    eventEmitter;
    readOnly = false;
    #registeredButtons = [];   // {definition, button, handler}
    container = null;
    #activeEditable = null;
    #alignGroup = null;
    #alignButtons = {};   // dir -> button, for reflecting the current alignment
    #buttons = {};        // command -> button, for reflecting the current inline state
    #moreButton = null;
    #moreMenu = null;

    constructor({eventEmitter, readOnly = false}) {
        this.eventEmitter = eventEmitter;
        this.readOnly = readOnly;
    }

    init() {
        this.#render();
        document.addEventListener('selectionchange', this.#handleSelectionChange);
    }

    #render() {
        this.container = document.createElement('div');
        this.container.id = contentEditorSelectors.ids.formatToolbar;
        this.container.classList.add(contentEditorSelectors.classes.hidden);
        this.container.style.position = 'absolute';
        this.container.style.zIndex = '50';

        this.container.append(
            this.#button('bold', ICONS.bold, 'Bold (Ctrl+B)'),
            this.#button('italic', ICONS.italic, 'Italic (Ctrl+I)'),
            this.#button('underline', ICONS.underline, 'Underline (Ctrl+U)'),
            this.#button('link', ICONS.link, 'Link'),
            ...this.#buildRegisteredButtons('toolbar'),
            this.#buildMoreButton(),
            this.#buildAlignGroup(),
            this.#buildMoreMenu()
        );

        this.container.addEventListener('mousedown', (e) => e.preventDefault());
        document.body.appendChild(this.container);
    }

    // Block-level text alignment, so it is grouped off from the inline commands. Shown only
    // for a text block's own editable (not footnote note bodies) — see #handleSelectionChange.
    #buildAlignGroup() {
        this.#alignGroup = document.createElement('div');
        this.#alignGroup.classList.add(contentEditorSelectors.classes.formatToolbarAlign);
        const separator = document.createElement('span');
        separator.classList.add(contentEditorSelectors.classes.formatToolbarSeparator);
        this.#alignGroup.appendChild(separator);
        ALIGNMENTS.forEach((dir) => {
            const button = this.#button(
                `align${dir[0].toUpperCase()}${dir.slice(1)}`,
                ICONS[`align${dir[0].toUpperCase()}${dir.slice(1)}`],
                `Align ${dir}`,
            );
            this.#alignButtons[dir] = button;
            this.#alignGroup.appendChild(button);
        });
        return this.#alignGroup;
    }

    // The chevron next to the link button. Opens the overflow menu.
    #buildMoreButton() {
        this.#moreButton = document.createElement('button');
        this.#moreButton.type = 'button';
        this.#moreButton.title = Translator.translate('More formats');
        this.#moreButton.innerHTML = ICONS.more;
        this.#moreButton.classList.add(contentEditorSelectors.classes.formatToolbarButton, contentEditorSelectors.classes.formatToolbarMoreButton);
        this.#moreButton.addEventListener('click', this.#toggleMoreMenu);
        return this.#moreButton;
    }

    #buildMoreMenu() {
        this.#moreMenu = document.createElement('div');
        this.#moreMenu.classList.add(
            contentEditorSelectors.classes.formatToolbarMenu,
            contentEditorSelectors.classes.hidden
        );
        MENU_COMMANDS.forEach(({command, icon, label}) => {
            this.#moreMenu.appendChild(this.#menuButton(command, ICONS[icon], label));
        });
        this.#buildRegisteredButtons('menu').forEach((button) => this.#moreMenu.appendChild(button));
        return this.#moreMenu;
    }

    // A menu row: icon plus a label, unlike the icon-only buttons in the main row.
    #menuButton(command, icon, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.title = Translator.translate(label);
        button.innerHTML = icon;
        button.append(document.createTextNode(Translator.translate(label)));
        button.classList.add(contentEditorSelectors.classes.formatToolbarButton);
        button.addEventListener('click', () => {
            this.applyCommand(command);
            this.#closeMoreMenu();
        });
        this.#buttons[command] = button;
        return button;
    }

    #toggleMoreMenu = () => {
        this.#moreMenu.classList.toggle(contentEditorSelectors.classes.hidden);
    }

    #closeMoreMenu() {
        if (this.#moreMenu) {
            this.#moreMenu.classList.add(contentEditorSelectors.classes.hidden);
        }
    }

    // Built once, alongside the built-in inline commands. `placement` decides whether a
    // registered button lands in the main row or in the overflow menu (the default).
    #buildRegisteredButtons(placement) {
        const elements = [];
        FormatToolbar.BUTTONS.forEach((definition) => {
            if ((definition.placement || 'menu') !== placement) {
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.title = Translator.translate(definition.title || definition.label || definition.key);
            button.classList.add(contentEditorSelectors.classes.formatToolbarButton);
            if (definition.icon) {
                button.innerHTML = definition.icon;
            }
            if (placement === 'menu' || !definition.icon) {
                button.append(document.createTextNode(Translator.translate(definition.label || definition.title || definition.key)));
            }
            const handler = () => {
                if (!this.#activeEditable) {
                    return;
                }
                definition.onClick(this.#buttonContext());
                // The command just changed the selection's state, so refresh immediately —
                // selectionchange may not fire for a command that leaves the range intact.
                this.#syncAllButtons();
            };
            button.addEventListener('click', handler);
            this.#registeredButtons.push({definition, button, handler});
            elements.push(button);
        });
        return elements;
    }

    #syncAllButtons() {
        this.#syncBuiltInButtons();
        this.#syncRegisteredButtons();
    }

    // Reflect the current inline state on the built-in buttons, the way the alignment group
    // already does. bold/italic/underline come from the browser; superscript/subscript and
    // link are applied by hand, so they're detected from the DOM around the selection.
    #syncBuiltInButtons() {
        ['bold', 'italic', 'underline'].forEach((command) => {
            this.#setActive(command, this.#queryState(command));
        });
        Object.entries(INLINE_TAGS).forEach(([command, tag]) => {
            this.#setActive(command, !!this.#enclosingTagForSelection(tag));
        });
        this.#setActive('link', !!this.#selectionAnchor());
    }

    #setActive(command, active) {
        const button = this.#buttons[command];
        if (button) {
            button.classList.toggle(contentEditorSelectors.classes.active, !!active);
        }
    }

    // queryCommandState throws in some selection states rather than returning false.
    #queryState(command) {
        try {
            return document.queryCommandState(command);
        } catch (e) {
            return false;
        }
    }

    #enclosingTagForSelection(tag) {
        const selection = window.getSelection();
        if (!selection || !selection.rangeCount) {
            return null;
        }
        return this.#enclosingInline(selection.getRangeAt(0), tag);
    }

    #buttonContext() {
        return {editable: this.#activeEditable, selection: window.getSelection()};
    }

    // Re-evaluated on every selection change — the moment before the toolbar is shown, and the
    // only moment the state a button reflects can have changed.
    #syncRegisteredButtons() {
        if (!this.#registeredButtons.length) {
            return;
        }
        const context = this.#buttonContext();
        this.#registeredButtons.forEach(({definition, button}) => {
            const visible = typeof definition.isVisible === 'function'
                ? !!definition.isVisible(context)
                : true;
            button.classList.toggle(contentEditorSelectors.classes.hidden, !visible);
            const active = visible && typeof definition.isActive === 'function'
                ? !!definition.isActive(context)
                : false;
            button.classList.toggle(contentEditorSelectors.classes.active, active);
        });
    }

    #button(command, icon, title) {
        const button = document.createElement('button');
        button.type = 'button';
        button.title = Translator.translate(title);
        button.innerHTML = icon;
        button.classList.add(contentEditorSelectors.classes.formatToolbarButton);
        button.addEventListener('click', () => this.applyCommand(command));
        this.#buttons[command] = button;
        return button;
    }

    #handleSelectionChange = () => {
        // Text stays selectable in read-only (so it can be read and copied), but every command
        // here writes to the content — so the toolbar must not appear.
        if (this.readOnly) {
            return;
        }
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
            this.#hide();
            return;
        }
        const editable = this.#editableFromSelection(selection);
        if (!editable) {
            this.#hide();
            return;
        }
        if (this.#withinFootnote(selection)) {
            this.#hide(); // markers are managed atoms — never let formatting touch them
            return;
        }
        this.#activeEditable = editable;
        this.#syncAlignGroup(editable);
        this.#syncAllButtons();
        this.#showAtSelection(selection);
    }

    // Alignment applies to a block's own root (which carries data-block-id); a footnote note
    // body is a formattable surface but not a block, so it gets no alignment control.
    #syncAlignGroup(editable) {
        const isBlockRoot = editable.hasAttribute(contentEditorSelectors.attributes.blockId);
        this.#alignGroup.classList.toggle(contentEditorSelectors.classes.hidden, !isBlockRoot);
        if (!isBlockRoot) {
            return;
        }
        const current = editable.getAttribute(contentEditorSelectors.attributes.blockAlign) || 'left';
        ALIGNMENTS.forEach((dir) => {
            this.#alignButtons[dir].classList.toggle(contentEditorSelectors.classes.active, dir === current);
        });
    }

    #withinFootnote(selection) {
        const node = selection.getRangeAt(0).commonAncestorContainer;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        return !!(element && element.closest(`.${contentEditorSelectors.classes.footnoteRef}`));
    }

    #editableFromSelection(selection) {
        const node = selection.anchorNode;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        // Text blocks (.editable) and footnote note bodies are both formattable surfaces.
        const selector = `.${contentEditorSelectors.classes.editableBlock}, .${contentEditorSelectors.classes.footnotesContent}`;
        const editable = element ? element.closest(selector) : null;
        const content = document.getElementById(contentEditorSelectors.ids.contentContainer);
        return (editable && content && content.contains(editable)) ? editable : null;
    }

    #showAtSelection(selection) {
        const rect = selection.getRangeAt(0).getBoundingClientRect();
        if (!rect.width && !rect.height) {
            this.#hide();
            return;
        }
        this.container.classList.remove(contentEditorSelectors.classes.hidden);
        const top = rect.top + window.scrollY - this.container.offsetHeight - 8;
        const left = rect.left + window.scrollX + rect.width / 2 - this.container.offsetWidth / 2;
        this.container.style.top = `${Math.max(window.scrollY + 4, top)}px`;
        this.container.style.left = `${Math.max(4, left)}px`;
    }

    #hide() {
        this.container.classList.add(contentEditorSelectors.classes.hidden);
        this.#closeMoreMenu();   // don't leave it open to reappear with the next selection
        this.#activeEditable = null;
    }

    async applyCommand(command) {
        if (!this.#activeEditable) {
            return;
        }
        if (command === 'link') {
            await this.#applyLink();
            this.#syncAllButtons();
            return;
        }
        if (command.startsWith('align')) {
            this.#applyAlign(command.slice('align'.length).toLowerCase());
            return;   // #applyAlign syncs the align group itself
        }
        // These are done by hand rather than via execCommand: its toggling relies on the
        // browser's (boundary-buggy) state detection, which unwraps an adjacent <sup> when you
        // format the letter next to it. Wrapping only the selection avoids that — and gives a
        // predictable tag (<s>, <mark>) rather than whatever the browser happens to emit.
        if (INLINE_TAGS[command]) {
            this.#toggleInline(INLINE_TAGS[command]);
            this.#syncAllButtons();
            return;
        }
        document.execCommand(command);
        // Refresh straight away: a command that leaves the range intact may not fire
        // selectionchange, so the button would otherwise show a stale state until the next
        // selection move.
        this.#syncAllButtons();
    }

    // Alignment is a property of the whole block, so it is set on the editable root rather
    // than the selection. Left is the default and stored as *absence* of the attribute, so a
    // saved paragraph only carries `align` when it is actually centered or right-aligned.
    #applyAlign(dir) {
        const editable = this.#activeEditable;
        if (!editable || !editable.hasAttribute(contentEditorSelectors.attributes.blockId)) {
            return;
        }
        if (dir === 'left') {
            editable.removeAttribute(contentEditorSelectors.attributes.blockAlign);
        } else {
            editable.setAttribute(contentEditorSelectors.attributes.blockAlign, dir);
        }
        this.#syncAlignGroup(editable);
    }

    #toggleInline(tag) {
        const selection = window.getSelection();
        if (!selection.rangeCount || selection.isCollapsed) {
            return;
        }
        const range = selection.getRangeAt(0);
        const enclosing = this.#enclosingInline(range, tag);
        if (enclosing) {
            this.#unwrapInline(enclosing, range); // selection sits in this tag → toggle off
        } else {
            this.#wrapInline(range, tag);
        }
    }

    // The tag element wrapping the whole selection, if any (and within this editable).
    #enclosingInline(range, tag) {
        const node = range.commonAncestorContainer;
        const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
        const match = element ? element.closest(tag) : null;
        return (match && this.#activeEditable && this.#activeEditable.contains(match)) ? match : null;
    }

    // Wrap exactly the selected content in a fresh tag; neighbouring elements are
    // untouched. Caret lands just after the new tag so typing continues normally.
    #wrapInline(range, tag) {
        const wrapper = document.createElement(tag);
        try {
            wrapper.appendChild(range.extractContents());
            range.insertNode(wrapper);
        } catch (e) {
            return; // selection crossed element boundaries in a way we can't cleanly wrap
        }
        const after = document.createRange();
        after.setStartAfter(wrapper);
        after.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(after);
    }

    // Unwrap only the selected slice of the tag: the parts before/after the selection
    // stay wrapped, the selection becomes plain. (When the selection covers the whole
    // tag, the before/after parts are empty, so it collapses to a full unwrap.)
    #unwrapInline(element, range) {
        const parent = element.parentNode;
        if (!parent || !element.firstChild) {
            return;
        }
        const doc = element.ownerDocument;
        const tag = element.tagName.toLowerCase();

        const beforeRange = doc.createRange();
        beforeRange.setStartBefore(element.firstChild);
        beforeRange.setEnd(range.startContainer, range.startOffset);
        const beforeFrag = beforeRange.cloneContents();

        const middleFrag = range.cloneContents();

        const afterRange = doc.createRange();
        afterRange.setStart(range.endContainer, range.endOffset);
        afterRange.setEndAfter(element.lastChild);
        const afterFrag = afterRange.cloneContents();

        const output = doc.createDocumentFragment();
        if (beforeFrag.textContent.length) {
            const before = doc.createElement(tag);
            before.appendChild(beforeFrag);
            output.appendChild(before);
        }
        const middleNodes = [...middleFrag.childNodes];
        output.appendChild(middleFrag); // the selected slice, now un-tagged
        if (afterFrag.textContent.length) {
            const after = doc.createElement(tag);
            after.appendChild(afterFrag);
            output.appendChild(after);
        }

        parent.replaceChild(output, element);

        const lastMiddle = middleNodes[middleNodes.length - 1];
        if (lastMiddle && lastMiddle.parentNode) {
            const caret = doc.createRange();
            caret.setStartAfter(lastMiddle);
            caret.collapse(true);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(caret);
        }
    }

    async #applyLink() {
        const existing = this.#selectionAnchor();
        if (existing) {
            await this.#editLink(existing);
            return;
        }

        const selection = window.getSelection();
        if (!selection.rangeCount || selection.isCollapsed) {
            return;
        }
        const editable = this.#activeEditable;            // snapshot BEFORE the modal nulls it
        const savedRange = selection.getRangeAt(0).cloneRange();

        const result = await this.#openLinkModal();
        if (!result || result.action !== 'save' || !editable) {
            return;
        }

        editable.focus();
        selection.removeAllRanges();
        selection.addRange(savedRange);

        document.execCommand('createLink', false, result.href);

        const liveRange = window.getSelection().getRangeAt(0);
        this.#anchorsInRange(liveRange, editable).forEach((a) => this.#applyAnchorAttrs(a, result));
    }

    async #editLink(anchor) {
        const selection = window.getSelection();
        const editable = this.#activeEditable;            // snapshot BEFORE the modal nulls it
        const savedRange = selection.getRangeAt(0).cloneRange();

        const initial = {
            href: anchor.getAttribute('href') || '',
            newTab: anchor.getAttribute('target') === '_blank',
            rel: anchor.getAttribute('rel') || ''
        };

        const result = await this.#openLinkModal(initial, true);
        if (!result || !editable) {
            return;
        }

        editable.focus();

        if (result.action === 'unlink') {
            this.#unlink(anchor);
            return;
        }

        selection.removeAllRanges();
        selection.addRange(savedRange);
        anchor.setAttribute('href', result.href);
        this.#applyAnchorAttrs(anchor, result);
    }

    #anchorsInRange(range, editable) {
        return [...editable.querySelectorAll('a')].filter((a) => range.intersectsNode(a));
    }

    #selectionAnchor() {
        const selection = window.getSelection();
        if (!selection.rangeCount) {
            return null;
        }
        const range = selection.getRangeAt(0);
        let node = range.commonAncestorContainer;
        if (node.nodeType === Node.TEXT_NODE) {
            node = node.parentElement;
        }
        const anchor = node ? node.closest('a') : null;
        return (anchor && anchor.contains(range.startContainer) && anchor.contains(range.endContainer))
            ? anchor
            : null;
    }

    #unlink(anchor) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(anchor);
        selection.removeAllRanges();
        selection.addRange(range);
        document.execCommand('unlink');
    }

    #applyAnchorAttrs(anchor, {newTab, rel}) {
        if (newTab) {
            anchor.setAttribute('target', '_blank');
        } else {
            anchor.removeAttribute('target');
        }
        rel ? anchor.setAttribute('rel', rel) : anchor.removeAttribute('rel');
        const href = anchor.getAttribute('href');
        href ? anchor.setAttribute('title', href) : anchor.removeAttribute('title');
    }

    #mergeRel(a, b) {
        return [...new Set(`${a} ${b}`.split(/\s+/).filter(Boolean))].join(' ');
    }

    #openLinkModal(initial = {href: '', newTab: false, rel: ''}, isEdit = false) {
        return new Promise((resolve) => {
            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                resolve(value);
                modal.hide();
            };

            const modal = new Modal({
                width: '420px',
                destroyOnClose: true,
                afterHideCallback: () => { if (!settled) { settled = true; resolve(null); } }
            });

            const form = document.createElement('form');
            form.classList.add('flexColumn');
            form.innerHTML = `
            <h2>${isEdit ? Translator.translate('Edit link') : Translator.translate('Add link')}</h2>
            <div class="inputContainer">
                <label>${Translator.translate('URL')}</label>
                <input type="text" class="input linkHref" placeholder="https://example.com" value="${initial.href}">
            </div>
            <div class="inputContainer">
                <label>${Translator.translate('Rel')}</label>
                <input type="text" class="input linkRel" spellcheck="false" placeholder="nofollow" value="${initial.rel}">
            </div>
            <label class="linkNewTab">
                <input type="checkbox" class="linkNewTabInput input" ${initial.newTab ? 'checked' : ''}>
                <span>${Translator.translate('Open in new tab')}</span>
            </label>
            <div class="submitContainer">
                <button type="submit" class="btn primary">${Translator.translate('Save')}</button>
                ${isEdit ? `<button type="button" class="btn linkUnlink">${Translator.translate('Unlink')}</button>` : ''}
            </div>
        `;

            const onSubmit = (e) => {
                e.preventDefault();
                const href = form.querySelector('.linkHref').value.trim();
                const rel = form.querySelector('.linkRel').value.trim();
                const newTab = form.querySelector('.linkNewTabInput').checked;
                finish(href ? {action: 'save', href, newTab, rel} : null);
            };
            form.addEventListener('submit', onSubmit);

            if (isEdit) {
                form.querySelector('.linkUnlink').addEventListener('click', () => finish({action: 'unlink'}));
            }

            document.body.appendChild(modal.getView());
            modal.show();
            modal.populateWithElement(form);
            form.querySelector('.linkHref').focus();
        });
    }

    destroy() {
        document.removeEventListener('selectionchange', this.#handleSelectionChange);
        this.#registeredButtons.forEach(({button, handler}) => {
            button.removeEventListener('click', handler);
        });
        this.#registeredButtons = [];
        if (this.#moreButton) {
            this.#moreButton.removeEventListener('click', this.#toggleMoreMenu);
        }
        this.#moreButton = null;
        this.#moreMenu = null;
        this.#buttons = {};
        this.container.remove();
        this.container = null;
        this.eventEmitter = null;
    }
}