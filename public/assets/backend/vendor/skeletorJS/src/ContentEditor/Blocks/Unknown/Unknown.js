import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

/**
 * The fallback for a block whose `type` isn't registered in `config.blocks` — a post loaded
 * under a stripped-down config, an app block that failed to import, a type from a newer schema.
 *
 * Without it, the block manager drops the unregistered entry on load, so the next save
 * silently deletes that content. This renders an inert placeholder instead and, crucially,
 * **re-emits the original saved entry verbatim** — type, id, every field, additionalData and
 * columns — so a round trip through an editor that doesn't understand the block preserves it
 * exactly rather than destroying it.
 *
 * It is never inserted by hand: `hidden` keeps it out of the slash menu and inserter, and it
 * is not in `config.blocks` — the block manager reaches for it only as the unknown-type
 * fallback.
 */
export default class Unknown extends Block {
    static label = 'Unknown block';
    static keywords = [];
    static isText = false;
    static name = 'core/unknown';
    static category = 'other';
    static hidden = true;
    static deletable = true;   // system block, but the user may remove the placeholder
    static isUnknown = true;

    element;
    #sidebar = null;

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;   // focusable so the side toggle (delete/move) still works
        this.element.classList.add(contentEditorSelectors.classes.unknownBlock);

        const heading = document.createElement('strong');
        heading.textContent = Translator.translate('Unrecognised block');

        const type = document.createElement('code');
        type.classList.add(contentEditorSelectors.classes.unknownBlockType);
        type.textContent = this.#originalType();

        const note = document.createElement('p');
        note.textContent = Translator.translate('This block type isn’t available in this editor. Its content is preserved and will be saved unchanged.');

        this.element.append(heading, type, note);
        return this.element;
    }

    #originalType() {
        return (this.data && this.data.type) ? this.data.type : 'unknown';
    }

    getContainer() {
        return this.element;
    }

    // The manager calls setContent(data.html) for any entry with an html field. The base would
    // write that into the placeholder, replacing it with the raw markup — no-op here; the html
    // is preserved in data and re-emitted by getBlockData().
    setContent() {
    }

    focus() {
        this.element.focus();
    }

    // Not used for saving — getBlockData() below returns the original entry directly — but the
    // base declares it abstract, so it must exist.
    getData() {
        return {};
    }

    // Return the original saved entry, untouched. This overrides the base, which would stamp
    // type = 'core/unknown' and rebuild the payload from getData()/sidebarData — both of which
    // would lose the real block's type and fields. A deep clone so the stored original can't be
    // mutated by whatever consumes the save payload.
    getBlockData() {
        return structuredClone(this.data);
    }

    // The block can't be configured here, so the base Advanced sidebar (CSS class / id / inline
    // CSS) would only present controls whose edits getBlockData() ignores. Show a short note
    // instead of those controls — but still a real element, since the sidebar appends it.
    renderSidebarContent() {
        this.#sidebar = document.createElement('div');
        const note = document.createElement('p');
        note.textContent = `“${this.#originalType()}” isn’t available in this editor. Its saved `
            + 'content is preserved as-is and can’t be edited here.';
        note.classList.add(contentEditorSelectors.classes.blockSidebarNotice);
        this.#sidebar.appendChild(note);
        return this.#sidebar;
    }

    destroySidebar() {
        if (this.#sidebar) {
            this.#sidebar.remove();
            this.#sidebar = null;
        }
    }

    destroy() {
        this.element.remove();
        super.destroy();
    }
}
