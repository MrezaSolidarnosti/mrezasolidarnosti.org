import {imageCompareSelectors} from "./imageCompareSelectors.js";
import {events} from "./events.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";

const DEFAULTS = Object.freeze({
    position: 50,           // where the divider starts, 0–100 (%)
    orientation: 'horizontal',
    labels: null,           // {before, after} to show corner captions, or null for none
    hover: false,           // move with the pointer instead of requiring a drag
    step: 2,                // keyboard step, in %
    largeStep: 10,          // PageUp/PageDown step
});

/**
 * A before/after image comparison with a draggable divider.
 *
 * The reveal is a `clip-path` on the "before" layer, driven by a single CSS custom property, so
 * dragging only ever updates one number — no resizing, no re-layout, and nothing to recompute
 * when the container changes size. Both images stay at their natural size underneath, which is
 * what keeps the seam sharp at any width.
 *
 * Works from markup, so the images are in the HTML for crawlers and still render without JS:
 *
 *   <div id="compare">
 *       <img class="imageCompareBefore" src="before.jpg" alt="Before the storm">
 *       <img class="imageCompareAfter"  src="after.jpg"  alt="After the storm">
 *   </div>
 *
 * …or from data, via `{before, after}`.
 */
export default class ImageCompare {

    #setupComplete = false;
    #container = null;
    #options;
    #images = null;
    #frame = null;
    #beforePane = null;
    #handle = null;
    #position = 50;
    #dragging = false;

    eventEmitter;

    constructor({container = null, containerId = null, images = null, options = {}, eventEmitter = null} = {}) {
        this.#container = container || (containerId ? document.getElementById(containerId) : null);
        this.#options = {...DEFAULTS, ...options};
        this.#images = images;
        this.eventEmitter = eventEmitter || new EventEmitter();
    }

    init() {
        if (this.#setupComplete) {
            return this;
        }
        if (!this.#container) {
            console.warn('ImageCompare: no container element. Pass { container } or { containerId }.');
            return this;
        }
        const pair = this.#images ? this.#fromData() : this.#fromMarkup();
        if (!pair) {
            console.warn('ImageCompare: needs a before and an after image.');
            return this;
        }
        this.#build(pair);
        this.#addListeners();
        this.setPosition(this.#options.position, {silent: true});
        this.#setupComplete = true;
        this.eventEmitter.emit(events.imageCompareRendered, {compare: this});
        return this;
    }

    /* -------------------------------- Sources -------------------------------- */

    #fromMarkup() {
        const c = imageCompareSelectors.classes;
        const before = this.#container.querySelector(`.${c.before}`);
        const after = this.#container.querySelector(`.${c.after}`);
        return (before && after) ? {before, after} : null;
    }

    #fromData() {
        const {before, after} = this.#images;
        if (!before?.src || !after?.src) {
            return null;
        }
        return {before: this.#image(before), after: this.#image(after)};
    }

    #image({src, alt = ''}) {
        const element = document.createElement('img');
        element.src = src;
        element.alt = alt;
        return element;
    }

    /* --------------------------------- Build --------------------------------- */

    #build({before, after}) {
        const c = imageCompareSelectors.classes;
        this.#container.innerHTML = '';
        this.#container.classList.add(c.root);
        this.#container.classList.toggle(c.vertical, this.#options.orientation === 'vertical');

        this.#frame = document.createElement('div');
        this.#frame.classList.add(c.frame);

        // The "after" image sits in normal flow and so decides the frame's size; the "before"
        // layer is absolutely positioned over it and clipped. That way the component never needs
        // to know the images' dimensions.
        after.classList.add(c.after);
        before.classList.add(c.before);

        this.#beforePane = document.createElement('div');
        this.#beforePane.classList.add(c.pane);
        this.#beforePane.appendChild(before);

        this.#handle = document.createElement('div');
        this.#handle.classList.add(c.handle);
        this.#handle.tabIndex = 0;
        this.#handle.setAttribute('role', 'slider');
        this.#handle.setAttribute('aria-label', 'Compare images');
        this.#handle.setAttribute('aria-valuemin', '0');
        this.#handle.setAttribute('aria-valuemax', '100');
        this.#handle.setAttribute('aria-orientation', this.#options.orientation);
        const grip = document.createElement('span');
        grip.classList.add(c.grip);
        this.#handle.appendChild(grip);

        this.#frame.append(after, this.#beforePane, this.#handle);
        if (this.#options.labels) {
            this.#frame.append(
                this.#label(this.#options.labels.before, c.labelBefore),
                this.#label(this.#options.labels.after, c.labelAfter),
            );
        }
        this.#container.appendChild(this.#frame);
    }

    #label(text, extraClass) {
        const label = document.createElement('span');
        label.classList.add(imageCompareSelectors.classes.label, extraClass);
        label.textContent = text || '';
        return label;
    }

    /* -------------------------------- Position ------------------------------- */

    getPosition() {
        return this.#position;
    }

    /**
     * Move the divider. One custom property drives the clip — nothing is measured or resized, so
     * this is cheap enough to call on every pointer move.
     */
    setPosition(value, {silent = false} = {}) {
        const next = Math.max(0, Math.min(100, Number(value) || 0));
        const changed = next !== this.#position;
        this.#position = next;
        this.#container.style.setProperty(imageCompareSelectors.variables.position, `${next}%`);
        this.#handle?.setAttribute('aria-valuenow', String(Math.round(next)));
        if (changed && !silent) {
            this.eventEmitter.emit(events.positionChanged, {position: next});
        }
        return this;
    }

    // Where a pointer sits within the frame, as a percentage along the active axis.
    #positionFromPointer(e) {
        const rect = this.#frame.getBoundingClientRect();
        if (this.#options.orientation === 'vertical') {
            return rect.height ? ((e.clientY - rect.top) / rect.height) * 100 : 0;
        }
        return rect.width ? ((e.clientX - rect.left) / rect.width) * 100 : 0;
    }

    /* -------------------------------- Listeners ------------------------------ */

    #addListeners() {
        // One pointer path covers mouse, touch and pen — and because pressing anywhere jumps the
        // divider there, a tap works as well as a drag.
        this.#frame.addEventListener('pointerdown', this.#handlePointerDown);
        this.#frame.addEventListener('pointermove', this.#handlePointerMove);
        this.#frame.addEventListener('pointerup', this.#handlePointerUp);
        this.#frame.addEventListener('pointercancel', this.#handlePointerUp);
        this.#handle.addEventListener('keydown', this.#handleKeydown);
        if (this.#options.hover) {
            this.#frame.addEventListener('mousemove', this.#handleHover);
        }
    }

    #handlePointerDown = (e) => {
        if (e.button !== 0 && e.pointerType === 'mouse') {
            return;
        }
        e.preventDefault();   // stop the browser starting an image drag
        this.#dragging = true;
        this.#container.classList.add(imageCompareSelectors.classes.dragging);
        this.#frame.setPointerCapture?.(e.pointerId);
        this.#handle.focus({preventScroll: true});
        this.setPosition(this.#positionFromPointer(e));
    };

    #handlePointerMove = (e) => {
        if (!this.#dragging) {
            return;
        }
        e.preventDefault();
        this.setPosition(this.#positionFromPointer(e));
    };

    #handlePointerUp = (e) => {
        if (!this.#dragging) {
            return;
        }
        this.#dragging = false;
        this.#container.classList.remove(imageCompareSelectors.classes.dragging);
        this.#frame.releasePointerCapture?.(e.pointerId);
    };

    #handleHover = (e) => {
        if (!this.#dragging) {
            this.setPosition(this.#positionFromPointer(e));
        }
    };

    /**
     * The handle is a real slider to assistive tech, so it answers the keys one is expected to.
     *
     * The map depends on the orientation, because the position grows left→right horizontally but
     * top→bottom vertically. Without flipping Up/Down, "increase the value" would send a vertical
     * divider *downward* on Up — the arrow has to move the divider the way it points.
     */
    #handleKeydown = (e) => {
        const {step, largeStep} = this.#options;
        const keys = this.#options.orientation === 'vertical'
            ? {
                ArrowUp: -step, ArrowDown: step, ArrowLeft: -step, ArrowRight: step,
                PageUp: -largeStep, PageDown: largeStep,
            }
            : {
                ArrowLeft: -step, ArrowRight: step, ArrowDown: -step, ArrowUp: step,
                PageDown: -largeStep, PageUp: largeStep,
            };
        if (e.key in keys) {
            e.preventDefault();
            this.setPosition(this.#position + keys[e.key]);
        } else if (e.key === 'Home') {
            e.preventDefault();
            this.setPosition(0);
        } else if (e.key === 'End') {
            e.preventDefault();
            this.setPosition(100);
        }
    };

    destroy() {
        this.#frame?.removeEventListener('pointerdown', this.#handlePointerDown);
        this.#frame?.removeEventListener('pointermove', this.#handlePointerMove);
        this.#frame?.removeEventListener('pointerup', this.#handlePointerUp);
        this.#frame?.removeEventListener('pointercancel', this.#handlePointerUp);
        this.#frame?.removeEventListener('mousemove', this.#handleHover);
        this.#handle?.removeEventListener('keydown', this.#handleKeydown);
        this.#container?.classList.remove(
            imageCompareSelectors.classes.root,
            imageCompareSelectors.classes.vertical,
            imageCompareSelectors.classes.dragging,
        );
        this.#frame?.remove();
        this.#frame = null;
        this.#beforePane = null;
        this.#handle = null;
        this.#setupComplete = false;
    }
}
