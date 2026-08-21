import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import Paragraph from "../../Paragraph/Paragraph.js";
import {resolveGap} from "./resolveGap.js";
import Translator from "../../../../Translator/Translator.js";

// How close (px) to a block boundary the cursor must be for a gap to count.
const BAND = 18;
// How long the cursor must rest, still, in a gap before the "+" appears.
const DWELL_MS = 1000;

/**
 * The between-blocks "+" inserter. A single floating control (like the side toggle) that appears
 * in a block gap once the cursor has been **stationary there** for `DWELL_MS` — hover intent, so
 * it stays out of the way while you read or move around. Clicking it drops an empty paragraph at
 * the gap and focuses it, so you can type or hit `/`.
 *
 * Two phases: it only *appears* on a still mouse (every move restarts the dwell), but once shown
 * it *stays* while the cursor remains in that same gap — otherwise the centered button, which you
 * have to move to reach, would vanish before you could click it. It hides when the cursor leaves
 * the gap (into a block, another gap, or off the canvas).
 *
 * Container-aware: a gap inside a Columns column inserts into that column, via the same
 * renderBlock(reference, position, container) the drag drop and Insert-After paths use.
 */
export default class BlockGapInserter {

    #setupComplete = false;
    #eventEmitter;
    #blocks;
    readOnly;

    #content = null;
    #widget = null;
    #button = null;
    #rafPending = false;
    #lastEvent = null;
    #dwellTimer = null;
    #shownKey = null;   // identity of the currently-shown gap, or null when hidden
    #target = null;     // {reference, position, container} — where a click would insert

    constructor({eventEmitter, blocks, readOnly = false}) {
        this.#eventEmitter = eventEmitter;
        this.#blocks = blocks;
        this.readOnly = readOnly;
    }

    init() {
        if (this.#setupComplete) {
            return;
        }
        this.#content = document.getElementById(contentEditorSelectors.ids.contentContainer);
        if (!this.#content) {
            return;
        }
        this.#build();
        this.#content.addEventListener('mousemove', this.#handleMouseMove);
        this.#content.addEventListener('mouseleave', this.#handleMouseLeave);
        window.addEventListener('scroll', this.#reset, true);   // fixed-positioned; a scroll stales it
        this.#setupComplete = true;
    }

    #build() {
        this.#widget = document.createElement('div');
        this.#widget.classList.add(contentEditorSelectors.classes.blockGapInserter);
        this.#button = document.createElement('div');
        this.#button.title = Translator.translate('Add block');
        this.#button.classList.add(contentEditorSelectors.classes.blockGapInserterButton);
        this.#button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"></path></svg>`;
        this.#button.addEventListener('click', this.#insert);
        this.#widget.appendChild(this.#button);
        document.body.appendChild(this.#widget);
    }

    // rAF-throttled: mousemove fires far faster than we need to react.
    #handleMouseMove = (e) => {
        this.#lastEvent = e;
        if (this.#rafPending) {
            return;
        }
        this.#rafPending = true;
        requestAnimationFrame(() => {
            this.#rafPending = false;
            this.#update(this.#lastEvent);
        });
    };

    // The button is a <body> child over the canvas, so moving onto it counts as leaving #content
    // — don't reset then, or it would vanish before it can be clicked.
    #handleMouseLeave = (e) => {
        if (e.relatedTarget && this.#widget.contains(e.relatedTarget)) {
            return;
        }
        this.#reset();
    };

    #update(e) {
        if (!e || this.readOnly || this.#blocks.isReadOnly() || !e.target || !this.#content.contains(e.target)) {
            this.#reset();
            return;
        }
        const gap = this.#gapUnder(e);
        // Already showing: keep it while the cursor stays in the same gap (so the button stays
        // reachable). Any move within the same gap is a no-op — don't restart the dwell.
        if (this.#shownKey) {
            if (gap && gap.key === this.#shownKey) {
                return;
            }
            this.#hide();
        }
        // Appearance phase: a still mouse only. Every move lands here and restarts the timer, so
        // it fires solely after DWELL_MS of no movement while over a gap.
        clearTimeout(this.#dwellTimer);
        if (gap) {
            this.#dwellTimer = setTimeout(() => this.#show(gap), DWELL_MS);
        }
    }

    // The gap under the cursor, with a stable key for the "still in the same gap" check.
    #gapUnder(e) {
        const container = e.target.closest(`[${contentEditorSelectors.attributes.blockContainer}]`) || this.#content;
        const elements = this.#visibleBlocks(container);
        const containerId = container.getAttribute(contentEditorSelectors.attributes.blockId) || 'content';
        if (!elements.length) {
            const rect = container.getBoundingClientRect();
            return {reference: null, position: 'end', container, y: rect.top + 16, containerRect: rect, key: `${containerId}|end`};
        }
        // Only between blocks: no "+" above the first (Enter in the title already gets you a
        // block at the top) nor below the last (a click there already adds a trailing paragraph).
        const gap = resolveGap(elements.map((el) => el.getBoundingClientRect()), e.clientY, BAND, {
            includeBeforeFirst: false,
            includeAfterLast: false,
        });
        if (!gap) {
            return null;
        }
        const reference = elements[gap.refIndex];
        const referenceId = reference.getAttribute(contentEditorSelectors.attributes.blockId);
        return {
            reference,
            position: gap.position,
            container,
            y: gap.y,
            containerRect: container.getBoundingClientRect(),
            key: `${containerId}|${referenceId}|${gap.position}`,
        };
    }

    // A container's own top-level blocks, minus hidden/system ones (footnotes).
    #visibleBlocks(container) {
        return [...container.querySelectorAll(`:scope > [${contentEditorSelectors.attributes.blockId}]`)]
            .filter((el) => {
                const block = this.#blocks.blockFromBlockElement(el);
                return block && !block.constructor.hidden;
            });
    }

    #show(gap) {
        this.#target = {reference: gap.reference, position: gap.position, container: gap.container};
        this.#shownKey = gap.key;
        this.#widget.style.top = `${gap.y}px`;
        this.#widget.style.left = `${gap.containerRect.left}px`;
        this.#widget.style.width = `${gap.containerRect.width}px`;
        this.#widget.classList.add(contentEditorSelectors.classes.active);
    }

    #hide() {
        this.#shownKey = null;
        this.#target = null;
        if (this.#widget) {
            this.#widget.classList.remove(contentEditorSelectors.classes.active);
        }
    }

    // Hide and cancel any pending dwell — for leaving the canvas, scrolling, or after inserting.
    #reset = () => {
        clearTimeout(this.#dwellTimer);
        this.#hide();
    };

    #insert = () => {
        if (!this.#target || this.readOnly || this.#blocks.isReadOnly()) {
            return;
        }
        const {reference, position, container} = this.#target;
        this.#blocks.renderBlock(Paragraph.name, {}, reference, position, true, container);
        this.#reset();
    };

    destroy() {
        clearTimeout(this.#dwellTimer);
        if (this.#content) {
            this.#content.removeEventListener('mousemove', this.#handleMouseMove);
            this.#content.removeEventListener('mouseleave', this.#handleMouseLeave);
        }
        window.removeEventListener('scroll', this.#reset, true);
        if (this.#button) {
            this.#button.removeEventListener('click', this.#insert);
        }
        if (this.#widget) {
            this.#widget.remove();
        }
        this.#widget = null;
        this.#button = null;
        this.#content = null;
        this.#eventEmitter = null;
        this.#blocks = null;
        this.#setupComplete = false;
    }
}
