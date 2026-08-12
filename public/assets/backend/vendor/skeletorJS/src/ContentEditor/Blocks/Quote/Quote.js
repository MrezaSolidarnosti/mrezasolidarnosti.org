import Block from "../Block.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";
import BlockMenu from "../Components/BlockMenu/BlockMenu.js";
import Translator from "../../../Translator/Translator.js";

export default class Quote extends Block {
    static label = 'Quote';
    static keywords = ['quote', 'blockquote', 'citation', 'cite'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M580-360q-25 0-42.5-17.5T520-420v-140q0-25 17.5-42.5T580-620h140q25 0 42.5 17.5T780-560v250q0 47-16 82.5T707-146q-11 11-27.5 12.5T651-142q-13-11-14.5-27t8.5-30q20-25 33.5-50t18.5-61h-57q-25 0-42.5-17.5T520-360Zm-360 0q-25 0-42.5-17.5T160-420v-140q0-25 17.5-42.5T220-620h140q25 0 42.5 17.5T420-560v250q0 47-16 82.5T347-146q-11 11-27.5 12.5T291-142q-13-11-14.5-27t8.5-30q20-25 33.5-50t18.5-61h-57q-25 0-42.5-17.5T160-360Z"/></svg>`;
    static isText = true;
    static name = 'core/quote';
    static category = 'text';
    static description = 'Give quoted text visual emphasis.';
    static tags = ['blockquote'];

    element;      // the <figure> root
    quote;        // the editable <blockquote>
    cite;         // the editable <cite>

    /**
     * The root is a <figure> wrapping the quote and its citation, because a citation is *about*
     * the quote rather than part of it — keeping it inside the blockquote would put the
     * attribution into the quoted text itself, which is wrong semantically and wrong in the
     * saved html.
     *
     * That means the editable is no longer the root, so `setContent` and `focus` are overridden
     * below to target the blockquote. Everything else that looks for an editable finds it by
     * class or by `[contenteditable]`, so it keeps working unchanged.
     */
    render() {
        this.element = document.createElement('figure');
        this.element.classList.add(contentEditorSelectors.classes.quoteBlock);

        this.quote = document.createElement('blockquote');
        this.quote.contentEditable = 'true';
        this.quote.spellcheck = false;
        this.quote.classList.add(
            contentEditorSelectors.classes.editableBlock,
            contentEditorSelectors.classes.quoteText
        );
        this.quote.setAttribute(
            contentEditorSelectors.attributes.dataPlaceholder,
            slashCommandPlaceholder(this.eventEmitter)
        );

        this.cite = document.createElement('cite');
        this.cite.contentEditable = 'true';
        this.cite.spellcheck = false;
        this.cite.classList.add(contentEditorSelectors.classes.quoteCite);
        // Deliberately not `.editableBlock`: that class is what the format toolbar looks for, and
        // an attribution is a plain name — bold and links inside it would only produce markup the
        // frontend has to strip back out.
        this.cite.setAttribute(
            contentEditorSelectors.attributes.dataPlaceholder,
            Translator.translate('Add a citation')
        );
        if (typeof this.data?.cite === 'string') {
            this.cite.textContent = this.data.cite;
        }

        this.element.append(this.quote, this.cite);
        this.#addListeners();
        return this.element;
    }

    // The base sets innerHTML on `this.element`, which is now the figure — that would wipe the
    // cite and the structure with it. renderBlock calls this with the saved `html`, so it has to
    // land on the blockquote instead.
    setContent(html) {
        if (this.quote) {
            this.quote.innerHTML = html;
        }
    }

    #addListeners() {
        this.quote.addEventListener('input', this.#handleOnInput);
        this.quote.addEventListener('keydown', this.#handleKeydown);
        this.cite.addEventListener('input', this.#handleCiteInput);
        this.cite.addEventListener('keydown', this.#handleCiteKeydown);
    }

    #handleOnInput = () => {
        if(this.quote.innerHTML === '<br>') {
            this.quote.innerHTML = '';
        }
        if(this.quote.textContent === '/') {
            this.renderBLockMenu(this.quote);
        }
    }

    #handleKeydown = (e) => {
        if(BlockMenu.isOpen() && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            return;
        }
        // Backspace in an empty quote deletes the block — but not while a citation is still there
        // to lose, or clearing the quote to retype it would silently take the attribution too.
        if(e.key === 'Backspace'
            && this.quote.textContent.trim() === ''
            && this.cite.textContent.trim() === '') {
            e.preventDefault();
            this.destroy();
        }
    }

    #handleCiteInput = () => {
        // Emptying a contentEditable leaves a stray <br>, which blocks the :empty placeholder.
        if(this.cite.innerHTML === '<br>') {
            this.cite.innerHTML = '';
        }
    }

    #handleCiteKeydown = (e) => {
        // A citation is one line. Enter would otherwise reach the editor's global handler and add
        // a paragraph after the block, which is not what pressing Enter in a name should mean.
        if(e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            return;
        }
        // Backspace out of an empty citation returns to the quote rather than deleting the block;
        // the block-level delete lives on the quote itself, above.
        if(e.key === 'Backspace' && this.cite.textContent.trim() === '') {
            e.preventDefault();
            e.stopPropagation();
            this.focus();
        }
    }

    getContainer() {
        return this.element;
    }

    focus() {
        if(!this.quote || !this.quote.isConnected) return;
        this.quote.focus();
        if (window.getSelection && document.createRange) {
            const range = document.createRange();
            range.selectNodeContents(this.quote);
            range.collapse(false); // false collapses the range to the end of the node

            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }
    }

    getData() {
        // textContent for the cite, not innerHTML: it is plain text by design, so anything pasted
        // into it is stored as the words it reads as rather than the markup it arrived with.
        return {
            html: this.quote.innerHTML,
            cite: this.cite.textContent.trim(),
        };
    }

    destroy() {
        this.quote.removeEventListener('input', this.#handleOnInput);
        this.quote.removeEventListener('keydown', this.#handleKeydown);
        this.cite.removeEventListener('input', this.#handleCiteInput);
        this.cite.removeEventListener('keydown', this.#handleCiteKeydown);
        this.element.remove();
        super.destroy();
    }
}
