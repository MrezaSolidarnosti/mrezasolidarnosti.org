import BaseModule from "../BaseModule.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "../events.js";
import Overlay from "../Overlay/Overlay.js";
import Dismissible from "../Dismissible/Dismissible.js";
import DiffViewer from "../../DiffViewer/DiffViewer.js";
import {deepEqual} from "../../DiffViewer/diff.js";
import Translator from "../../Translator/Translator.js";

/**
 * Read-only module: revisions come in with the initial content and are never saved back —
 * the backend writes them. Clicking one opens a panel that diffs it against the live content
 * and offers a revert.
 *
 * It owns no content itself. Reverting emits `revisionRevertRequested` and the editor applies
 * the snapshot, because a module only ever gets an eventEmitter — it cannot reach the blocks
 * or the other modules directly.
 *
 * The panel is the editor's own markup toggled with `.active` over the shared Overlay, the
 * same as the SEO panel — not the Modal component, so it scales in with the rest of the
 * editor's chrome rather than as a native <dialog>.
 */
export default class Revisions extends BaseModule {

    #setupComplete = false;
    #dismissible = null;
    #revisions = [];
    #selectedId = null;
    #viewer = null;
    modal;
    closeButton;
    diffTarget;
    fieldsTarget;
    summary;
    revertButton;
    modalList;
    container;

    /**
     * Per-type preview renderers, keyed by block type: `(block) => htmlString`.
     *
     * Empty by default, so only blocks carrying `html` are visualised — any other block is
     * reported by name and by whichever of its fields changed. Register a type to opt it in;
     * nothing here needs to know the type exists, so an app block works the same as a core
     * one. Set these before constructing the editor.
     *
     *   Revisions.registerPreview('core/image', (b) => `<img src="${Revisions.escape(b.src)}">`);
     */
    static PREVIEWS = new Map();

    static registerPreview(type, renderer) {
        Revisions.PREVIEWS.set(type, renderer);
    }

    static unRegisterPreview(type) {
        Revisions.PREVIEWS.delete(type);
    }

    // Everything the diff needs to know about a block. Same contract as any DiffViewer
    // consumer — nothing here is special-cased inside the viewer.
    // labelFor is not here: it needs the live block registry, so #renderDetail supplies it.
    static DIFF_OPTIONS = {
        keyOf: (block) => block.id,
        textOf: (block) => (block.html ? DiffViewer.textFromHtml(block.html) : ''),
        // Looked up per render, not captured, so a preview registered later still applies.
        renderItem: (block) => {
            const preview = Revisions.PREVIEWS.get(block.type);
            return preview ? (preview(block) ?? '') : (block.html ?? '');
        },
        // Type-agnostic on purpose: a list of primitives reads better joined than as JSON,
        // whatever block it came from. Anything else falls through to the default.
        formatValue: (key, value) => {
            if (Array.isArray(value) && value.every((entry) => typeof entry !== 'object')) {
                return value.join(', ');
            }
            return undefined;
        },
        // Side by side, with the columns named: which side holds a block says what happened
        // to it, so there is no direction to misread next to a Revert button.
        view: 'split',
        // Unchanged blocks stay visible — they are the context that makes the changed ones
        // legible. The toolbar toggle is still there to collapse them.
        collapseUnchanged: false,
    };

    // A preview's return value is inserted as HTML, so anything a renderer interpolates from
    // block data has to be escaped — a chart label or a filename is user content.
    static escape(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[character]));
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#setElements();
        if (!this.container || !this.modal) {
            return;
        }
        this.#addListeners();
        this.#renderSidebarList();
        this.#dismissible = Dismissible.register({
            isOpen: () => this.modal.classList.contains(contentEditorSelectors.classes.active),
            close: () => this.close(),
        });
        this.#setupComplete = true;
    }

    #setElements() {
        const {ids} = contentEditorSelectors;
        this.container = document.getElementById(ids.revisionsContainer);
        this.modal = document.getElementById(ids.revisionsModal);
        this.closeButton = document.getElementById(ids.closeRevisions);
        this.modalList = document.getElementById(ids.revisionsModalList);
        this.summary = document.getElementById(ids.revisionsSummary);
        this.revertButton = document.getElementById(ids.revisionsRevert);
        this.fieldsTarget = document.getElementById(ids.revisionsFields);
        this.diffTarget = document.getElementById(ids.revisionsDiff);
    }

    #addListeners() {
        this.container.addEventListener('click', this.#handleListClick);
        this.modalList.addEventListener('click', this.#handleModalListClick);
        this.closeButton.addEventListener('click', this.close);
        this.revertButton.addEventListener('click', this.#handleRevert);
    }


    // Called by the editor from initialContent.revisions. Newest first, whatever order the
    // caller supplied — a revision list reads newest-first everywhere else.
    setData(revisions) {
        this.#revisions = (Array.isArray(revisions) ? [...revisions] : [])
            .sort((a, b) => this.#time(b.date) - this.#time(a.date));
        if (this.#setupComplete) {
            this.#renderSidebarList();
        }
    }

    getData() {
        return this.#revisions;
    }

    #time(date) {
        // "2026-07-16 14:32:00" is not an ISO string; Safari returns NaN for it.
        const parsed = Date.parse(String(date ?? '').replace(' ', 'T'));
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    #formatDate(date) {
        const parsed = this.#time(date);
        if (!parsed) {
            return String(date ?? '');
        }
        return new Date(parsed).toLocaleString(undefined, {
            day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    }

    #find(id) {
        return this.#revisions.find((revision) => String(revision.id) === String(id)) || null;
    }

    // The editor holds the live content; a module only has the emitter to ask with.
    #currentContent() {
        const request = {};
        this.eventEmitter.emit(events.currentContentRequested, request);
        return request.content || {};
    }

    /**
     * `Paragraph` reads better than `core/paragraph` on a card meant for a person.
     *
     * Asked for per render rather than cached at init, because the editor registers these
     * listeners after it inits its modules — the same reason #currentContent() is lazy.
     */
    #blockLabels() {
        const request = {};
        this.eventEmitter.emit(events.blockLabelsRequested, request);
        return request.labels || new Map();
    }


    #renderSidebarList() {
        this.container.innerHTML = '';
        if (!this.#revisions.length) {
            const empty = document.createElement('div');
            empty.classList.add(contentEditorSelectors.classes.revisionsEmpty);
            empty.textContent = Translator.translate('No revisions yet.');
            this.container.appendChild(empty);
            return;
        }
        const list = document.createElement('div');
        list.classList.add(contentEditorSelectors.classes.revisionsList);
        this.#revisions.forEach((revision, index) => {
            list.appendChild(this.#buildItem(revision, index === 0));
        });
        this.container.appendChild(list);
    }

    #buildItem(revision, isLatest) {
        const item = document.createElement('div');
        item.classList.add(contentEditorSelectors.classes.revisionsItem);
        item.setAttribute(contentEditorSelectors.attributes.revisionId, revision.id);

        const date = document.createElement('span');
        date.classList.add(contentEditorSelectors.classes.revisionsItemDate);
        date.textContent = this.#formatDate(revision.date);
        item.appendChild(date);

        const meta = document.createElement('span');
        meta.classList.add(contentEditorSelectors.classes.revisionsItemMeta);
        meta.textContent = revision.author || '';
        item.appendChild(meta);

        if (isLatest) {
            const badge = document.createElement('span');
            badge.classList.add(contentEditorSelectors.classes.revisionsItemBadge);
            badge.textContent = Translator.translate('Latest');
            item.appendChild(badge);
        }
        return item;
    }

    #handleListClick = (e) => {
        const item = e.target.closest(`[${contentEditorSelectors.attributes.revisionId}]`);
        if (!item) {
            return;
        }
        this.open(item.getAttribute(contentEditorSelectors.attributes.revisionId));
    };


    open(id = null) {
        if (!this.#revisions.length) {
            return;
        }
        const revision = this.#find(id) || this.#revisions[0];
        this.#selectedId = revision.id;
        this.#renderModalList();
        this.#renderDetail();
        this.modal.classList.add(contentEditorSelectors.classes.active);
        Overlay.showOverlay();
    }

    close = () => {
        this.modal.classList.remove(contentEditorSelectors.classes.active);
        Overlay.hideOverlay();
    };

    #renderModalList() {
        this.modalList.innerHTML = '';
        this.#revisions.forEach((revision, index) => {
            const item = this.#buildItem(revision, index === 0);
            item.classList.toggle(
                contentEditorSelectors.classes.active,
                String(revision.id) === String(this.#selectedId),
            );
            this.modalList.appendChild(item);
        });
    }

    #handleModalListClick = (e) => {
        const item = e.target.closest(`[${contentEditorSelectors.attributes.revisionId}]`);
        if (!item) {
            return;
        }
        this.#selectedId = item.getAttribute(contentEditorSelectors.attributes.revisionId);
        this.#renderModalList();
        this.#renderDetail();
    };

    #renderDetail() {
        const revision = this.#find(this.#selectedId);
        if (!revision) {
            return;
        }
        const current = this.#currentContent();
        this.summary.textContent = `Reverting replaces the current content with this revision.`;
        this.revertButton.classList.toggle(contentEditorSelectors.classes.disabled, this.isReadOnly());

        this.#renderFieldChanges(revision, current);

        if (this.#viewer) {
            this.#viewer.destroy();
        }
        const labels = this.#blockLabels();
        this.#viewer = new DiffViewer({
            target: this.diffTarget,
            before: (revision.content && revision.content.blocks) || [],
            after: current.blocks || [],
            options: {
                ...Revisions.DIFF_OPTIONS,
                // A revision can hold a type the config has since dropped, so fall back to
                // the raw name rather than labelling the card with nothing.
                labelFor: (block) => labels.get(block.type) || block.type,
                sideLabels: {
                    // Interpolated, so the date is substituted into the translated template.
                    before: Translator.translate('This revision — %s').replace('%s', this.#formatDate(revision.date)),
                    after: Translator.translate('Current'),
                },
            },
        }).init();
    }

    /**
     * Everything a revert would change that is not a block — the title, and whatever else the
     * snapshot recorded.
     *
     * Only keys the revision actually carries are compared. A revision holds whatever
     * getDataForSave() produced at the time; walking the union with the live content instead
     * would report every key the snapshot predates as a change.
     */
    #renderFieldChanges(revision, current) {
        this.fieldsTarget.innerHTML = '';
        const content = revision.content || {};
        const changed = Object.keys(content)
            .filter((key) => key !== 'blocks')
            .map((key) => ({key, before: content[key], after: current[key]}))
            .filter((field) => !deepEqual(field.before, field.after));
        if (!changed.length) {
            return;
        }
        changed.forEach((field) => this.fieldsTarget.appendChild(this.#buildFieldRow(field)));
    }

    #buildFieldRow(field) {
        const row = document.createElement('div');
        row.classList.add(contentEditorSelectors.classes.revisionsField);

        const key = document.createElement('span');
        key.classList.add(contentEditorSelectors.classes.revisionsFieldKey);
        key.textContent = field.key;

        const before = document.createElement('span');
        before.classList.add(contentEditorSelectors.classes.revisionsFieldValue);
        before.appendChild(this.#value('del', field.before));

        const after = document.createElement('span');
        after.classList.add(contentEditorSelectors.classes.revisionsFieldValue);
        after.appendChild(this.#value('ins', field.after));

        row.append(key, before, after);
        return row;
    }

    #value(tag, value) {
        const element = document.createElement(tag);
        const empty = value === undefined || value === null || value === '';
        element.textContent = empty
            ? '—'
            : (typeof value === 'object' ? JSON.stringify(value) : String(value));
        if (empty) {
            element.classList.add(contentEditorSelectors.classes.revisionsFieldEmpty);
        }
        return element;
    }

    #handleRevert = () => {
        if (this.isReadOnly()) {
            return;
        }
        const revision = this.#find(this.#selectedId);
        if (!revision || !revision.content) {
            return;
        }
        this.eventEmitter.emit(events.revisionRevertRequested, revision);
        this.close();
    };


    // The panel is the page's markup, not ours: it outlives the module, so every listener has
    // to come back off and the panel has to be left closed.
    destroy() {
        if (this.#viewer) {
            this.#viewer.destroy();
            this.#viewer = null;
        }
        Dismissible.unregister(this.#dismissible);
        this.#dismissible = null;
        if (this.#setupComplete) {
            this.container.removeEventListener('click', this.#handleListClick);
            this.modalList.removeEventListener('click', this.#handleModalListClick);
            this.closeButton.removeEventListener('click', this.close);
            this.revertButton.removeEventListener('click', this.#handleRevert);
            this.close();
        }
        this.container = null;
        this.modal = null;
        this.closeButton = null;
        this.diffTarget = null;
        this.fieldsTarget = null;
        this.summary = null;
        this.revertButton = null;
        this.modalList = null;
        this.#revisions = [];
        this.#setupComplete = false;
        super.destroy();
    }
}
