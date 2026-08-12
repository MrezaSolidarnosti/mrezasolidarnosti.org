/**
 * A registry of dismissible surfaces — the editor's modals and panels (SEO, Shortcuts,
 * Revisions, the block inserter, the overview, …). One document-level Escape listener closes
 * whatever is open, so the behaviour is defined once and a new panel joins with a single
 * register() call rather than its own key handling.
 *
 * A panel registers a small handle rather than itself:
 *
 *     this.#dismissible = Dismissible.register({
 *         isOpen: () => this.container.classList.contains('active'),
 *         close:  () => this.#close(),
 *     });
 *     // in destroy():
 *     Dismissible.unregister(this.#dismissible);
 *
 * Passing closures keeps each panel's open/close logic in its own (often private) methods —
 * nothing has to be renamed to a shared interface.
 *
 * The Escape listener is on the bubble phase and only swallows the event when it actually
 * closes something. More specific popups (the slash menu, entity search, the link modal) and
 * the block-selection Escape handle the key earlier and stopPropagation, so this never fires
 * while one of those owns the moment — they win, this is the fallback. And an Escape that
 * closes nothing stays transparent.
 */
export default class Dismissible {

    static #handlers = new Set();
    static #listening = false;

    static register(handler) {
        Dismissible.#handlers.add(handler);
        Dismissible.#ensureListening();
        return handler;   // returned so the caller can hold it for unregister()
    }

    static unregister(handler) {
        Dismissible.#handlers.delete(handler);
    }

    static #ensureListening() {
        if (Dismissible.#listening) {
            return;
        }
        document.addEventListener('keydown', Dismissible.#handleKeydown);
        Dismissible.#listening = true;
    }

    static #handleKeydown = (e) => {
        if (e.key !== 'Escape') {
            return;
        }
        let closedAny = false;
        // Iterate a copy: a close() that unregisters (its own, or another's) would otherwise
        // mutate the set mid-loop.
        [...Dismissible.#handlers].forEach((handler) => {
            let open = false;
            try {
                open = handler.isOpen();
            } catch (err) {
                // A panel mid-teardown may no longer resolve its element — treat as closed.
                open = false;
            }
            if (open) {
                handler.close();
                closedAny = true;
            }
        });
        if (closedAny) {
            e.stopPropagation();
        }
    };
}
