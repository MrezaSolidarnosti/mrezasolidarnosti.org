import {gallerySelectors} from "./gallerySelectors.js";
import {galleryAssets} from "./galleryAssets.js";
import {events} from "./events.js";

const ZOOM_MAX = 4;
const ZOOM_STEP = 2.5;      // what a double-tap/double-click zooms to
const SWIPE_THRESHOLD = 40; // px before a horizontal drag counts as a swipe
const DOUBLE_TAP_MS = 300;

/**
 * The full-screen image viewer behind a Gallery.
 *
 * Its DOM — chrome *and* one slide per image — is built **once**, in `init()`. The original
 * implementation this replaces appended every slide again on each `open()`, so reopening
 * duplicated the whole set (and a deep-link firing alongside a click could leave two slides
 * visible side by side — the "two merged images" bug). Building once also means `open()` is
 * just an index change.
 *
 * Slides are created empty and get their `src` only when they're the current image or a
 * neighbour, so a 200-image gallery doesn't fetch 200 files to show one.
 */
export default class Lightbox {

    #setupComplete = false;
    #images = [];
    #options;
    #eventEmitter;

    #root = null;
    #stage = null;
    #countElement = null;
    #footer = null;
    #previousButton = null;
    #nextButton = null;
    #fullscreenButton = null;
    #shareButton = null;
    #shareMenu = null;
    #slides = [];               // {element, image, loaded}

    #open = false;
    #index = 0;

    // Zoom / pan state, reset on every navigation.
    #scale = 1;
    #panX = 0;
    #panY = 0;
    #pinchStart = null;         // {distance, scale}
    #dragStart = null;          // {x, y, panX, panY}
    #touchStart = null;         // {x, y, time} — swipe + double-tap detection
    #lastTapAt = 0;

    constructor({images = [], options = {}, eventEmitter = null}) {
        this.#images = images;
        this.#options = options;
        this.#eventEmitter = eventEmitter;
    }

    init() {
        if (this.#setupComplete) {
            return this;
        }
        this.#build();
        this.#addListeners();
        this.#setupComplete = true;
        return this;
    }

    setImages(images) {
        this.#images = images;
        if (!this.#setupComplete) {
            return this;
        }
        this.#buildSlides();
        // #buildSlides throws the old slides away, so an open lightbox would be left showing
        // nothing. Re-show the current image (clamped, in case the set shrank).
        if (this.#open && this.#slides.length) {
            this.show(Math.min(this.#index, this.#slides.length - 1), {silent: true});
        }
        return this;
    }

    isOpen() {
        return this.#open;
    }

    getIndex() {
        return this.#index;
    }

    /* --------------------------------- Build -------------------------------- */

    #build() {
        const c = gallerySelectors.classes;
        const icons = galleryAssets.icons;

        this.#root = document.createElement('div');
        this.#root.classList.add(c.lightbox);
        this.#root.setAttribute('role', 'dialog');
        this.#root.setAttribute('aria-modal', 'true');
        this.#root.setAttribute('aria-hidden', 'true');

        // Top bar: counter on the left, actions on the right.
        const topBar = document.createElement('div');
        topBar.classList.add(c.lightboxTopBar);
        this.#countElement = document.createElement('span');
        this.#countElement.classList.add(c.lightboxCount);
        const actions = document.createElement('div');
        actions.classList.add(c.lightboxActions);

        if (this.#options.share !== false) {
            this.#shareButton = this.#action(icons.share, 'Share', c.lightboxShare);
            this.#shareMenu = document.createElement('div');
            this.#shareMenu.classList.add(c.lightboxShareMenu);
            this.#shareButton.appendChild(this.#shareMenu);
            actions.appendChild(this.#shareButton);
        }
        if (this.#options.fullscreen !== false) {
            this.#fullscreenButton = this.#action(icons.fullscreen, 'Fullscreen');
            actions.appendChild(this.#fullscreenButton);
        }
        const closeButton = this.#action(icons.close, 'Close');
        closeButton.addEventListener('click', this.#handleClose);
        actions.appendChild(closeButton);
        topBar.append(this.#countElement, actions);

        // Stage holds every slide; only the active one is shown.
        this.#stage = document.createElement('div');
        this.#stage.classList.add(c.lightboxStage);

        this.#previousButton = this.#action(icons.previous, 'Previous', c.lightboxPrevious);
        this.#nextButton = this.#action(icons.next, 'Next', c.lightboxNext);

        this.#footer = document.createElement('div');
        this.#footer.classList.add(c.lightboxFooter);

        this.#root.append(topBar, this.#stage, this.#previousButton, this.#nextButton, this.#footer);
        document.body.appendChild(this.#root);
        this.#buildSlides();
    }

    #action(icon, label, extraClass = null) {
        const button = document.createElement('button');
        button.type = 'button';
        button.classList.add(gallerySelectors.classes.lightboxAction);
        if (extraClass) {
            button.classList.add(extraClass);
        }
        button.title = label;
        button.setAttribute('aria-label', label);
        button.innerHTML = icon;
        return button;
    }

    // One slide per image, created empty. #ensureLoaded fills in the src on demand.
    #buildSlides() {
        const c = gallerySelectors.classes;
        this.#stage.innerHTML = '';
        this.#slides = this.#images.map((image, index) => {
            const slide = document.createElement('div');
            slide.classList.add(c.lightboxSlide);
            slide.setAttribute(gallerySelectors.attributes.index, String(index));
            const loader = document.createElement('div');
            loader.classList.add(c.lightboxLoader);
            const element = document.createElement('img');
            element.alt = image.alt;
            element.draggable = false;
            element.addEventListener('load', () => loader.classList.add(c.hidden));
            slide.append(loader, element);
            this.#stage.appendChild(slide);
            return {slide, element, image, loaded: false};
        });
    }

    // Load the current image and its neighbours, so paging feels instant without fetching all.
    #ensureLoaded(index) {
        [index - 1, index, index + 1].forEach((i) => {
            const entry = this.#slides[i];
            if (entry && !entry.loaded) {
                entry.element.src = entry.image.big;
                entry.loaded = true;
            }
        });
    }

    /* --------------------------------- Open --------------------------------- */

    open(index = 0, {silent = false} = {}) {
        if (!this.#slides.length) {
            return this;
        }
        this.#open = true;
        this.#root.classList.add(gallerySelectors.classes.lightboxOpen);
        this.#root.setAttribute('aria-hidden', 'false');
        document.body.classList.add(gallerySelectors.classes.scrollLocked);
        this.show(index, {silent});
        this.#emit(events.lightboxOpened, {index: this.#index});
        return this;
    }

    close() {
        if (!this.#open) {
            return this;
        }
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
        this.#open = false;
        this.#root.classList.remove(gallerySelectors.classes.lightboxOpen);
        this.#root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove(gallerySelectors.classes.scrollLocked);
        this.#resetZoom();
        this.#closeShareMenu();
        this.#clearHash();
        this.#emit(events.lightboxClosed, {index: this.#index});
        return this;
    }

    show(index, {silent = false} = {}) {
        const c = gallerySelectors.classes;
        if (index < 0 || index >= this.#slides.length) {
            return this;
        }
        this.#slides.forEach(({slide}) => slide.classList.remove(c.lightboxSlideActive));
        this.#index = index;
        this.#resetZoom();
        this.#ensureLoaded(index);
        this.#slides[index].slide.classList.add(c.lightboxSlideActive);

        const image = this.#images[index];
        this.#countElement.textContent = `${index + 1} / ${this.#images.length}`;
        this.#footer.textContent = image.caption;
        this.#footer.classList.toggle(c.hidden, !image.hasCaption());
        // At the ends the arrows are disabled rather than hidden, so the chrome doesn't jump.
        this.#previousButton.classList.toggle(c.disabled, index === 0);
        this.#nextButton.classList.toggle(c.disabled, index === this.#slides.length - 1);

        this.#writeHash(image);
        this.#renderShareMenu();
        if (!silent && typeof this.#options.onNavigate === 'function') {
            this.#options.onNavigate({image, index, url: window.location.href});
        }
        this.#emit(events.lightboxNavigated, {index, image});
        return this;
    }

    next() {
        if (this.#index < this.#slides.length - 1) {
            this.show(this.#index + 1);
        }
        return this;
    }

    previous() {
        if (this.#index > 0) {
            this.show(this.#index - 1);
        }
        return this;
    }

    /* ------------------------------ Deep linking ---------------------------- */

    // `#gallery-<id>` by default, so an image is linkable. replaceState (not a hash assignment)
    // keeps it out of the browser's back stack — Back should leave the page, not step through
    // every image you viewed.
    #writeHash(image) {
        if (this.#options.hash === false || !image.id) {
            return;
        }
        const hash = `#${this.#hashPrefix()}${image.id}`;
        history.replaceState('', document.title, `${location.pathname}${location.search}${hash}`);
    }

    #clearHash() {
        if (this.#options.hash === false) {
            return;
        }
        history.replaceState('', document.title, `${location.pathname}${location.search}`);
    }

    #hashPrefix() {
        return this.#options.hashPrefix || 'gallery-';
    }

    // The id in the current URL hash, or null. The gallery uses this on load to reopen a
    // shared link.
    readHashId() {
        if (this.#options.hash === false || !location.hash) {
            return null;
        }
        const prefix = `#${this.#hashPrefix()}`;
        return location.hash.startsWith(prefix) ? location.hash.slice(prefix.length) : null;
    }

    /* --------------------------------- Share -------------------------------- */

    #renderShareMenu() {
        if (!this.#shareMenu) {
            return;
        }
        this.#shareMenu.innerHTML = '';
        const targets = Array.isArray(this.#options.share) ? this.#options.share : galleryAssets.shareTargets;
        const url = encodeURIComponent(window.location.href);
        targets.forEach((target) => {
            const link = document.createElement('a');
            link.href = String(target.url).replace('{url}', url);
            link.target = '_blank';
            link.rel = 'noopener';
            link.textContent = target.label || target.key;
            this.#shareMenu.appendChild(link);
        });
    }

    // Use the OS share sheet wherever the browser offers one — it reaches apps and contacts a
    // link list can't, on desktop as well as mobile. `nativeShare: false` forces the menu.
    #useNativeShare() {
        if (typeof navigator === 'undefined' || !navigator.share) {
            return false;
        }
        return this.#options.nativeShare !== false;
    }

    // The share menu is the fallback for browsers without the API (and for anyone who opts out).
    #handleShare = (e) => {
        e.stopPropagation();
        if (this.#useNativeShare()) {
            const image = this.#images[this.#index];
            navigator.share({
                title: document.title,
                text: image ? image.caption : '',
                url: window.location.href,
            }).catch(() => {});
            return;
        }
        this.#shareMenu.classList.toggle(gallerySelectors.classes.active);
    };

    #closeShareMenu() {
        if (this.#shareMenu) {
            this.#shareMenu.classList.remove(gallerySelectors.classes.active);
        }
    }

    /* ---------------------------------- Zoom -------------------------------- */

    #applyTransform() {
        const c = gallerySelectors.classes;
        const entry = this.#slides[this.#index];
        if (!entry) {
            return;
        }
        entry.element.style.transform = `translate(${this.#panX}px, ${this.#panY}px) scale(${this.#scale})`;
        this.#root.classList.toggle(c.lightboxZoomed, this.#scale > 1);
    }

    #resetZoom() {
        this.#scale = 1;
        this.#panX = 0;
        this.#panY = 0;
        this.#slides.forEach(({element}) => { element.style.transform = ''; });
        this.#root.classList.remove(gallerySelectors.classes.lightboxZoomed);
    }

    #setScale(scale) {
        this.#scale = Math.min(Math.max(scale, 1), ZOOM_MAX);
        if (this.#scale === 1) {
            this.#panX = 0;
            this.#panY = 0;
        }
        this.#applyTransform();
    }

    #toggleZoom() {
        this.#setScale(this.#scale > 1 ? 1 : ZOOM_STEP);
    }

    /* -------------------------------- Listeners ------------------------------ */

    #addListeners() {
        this.#previousButton.addEventListener('click', this.#handlePrevious);
        this.#nextButton.addEventListener('click', this.#handleNext);
        if (this.#fullscreenButton) {
            this.#fullscreenButton.addEventListener('click', this.#handleFullscreen);
        }
        if (this.#shareButton) {
            this.#shareButton.addEventListener('click', this.#handleShare);
        }
        this.#root.addEventListener('click', this.#handleBackdrop);
        this.#root.addEventListener('dblclick', this.#handleDoubleClick);
        this.#root.addEventListener('mousedown', this.#handleMouseDown);
        this.#stage.addEventListener('touchstart', this.#handleTouchStart, {passive: true});
        this.#stage.addEventListener('touchmove', this.#handleTouchMove, {passive: false});
        this.#stage.addEventListener('touchend', this.#handleTouchEnd);
        document.addEventListener('keydown', this.#handleKeydown);
        document.addEventListener('fullscreenchange', this.#handleFullscreenChange);
    }

    #handleClose = () => this.close();
    #handlePrevious = (e) => { e.stopPropagation(); this.previous(); };
    #handleNext = (e) => { e.stopPropagation(); this.next(); };

    // A click on the backdrop (never on the image or the chrome) closes.
    #handleBackdrop = (e) => {
        if (e.target === this.#root || e.target === this.#stage) {
            this.close();
        }
        this.#closeShareMenu();
    };

    #handleKeydown = (e) => {
        if (!this.#open) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            this.close();
        } else if (e.key === 'ArrowRight') {
            this.next();
        } else if (e.key === 'ArrowLeft') {
            this.previous();
        }
    };

    #handleFullscreen = (e) => {
        e.stopPropagation();
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        } else {
            this.#root.requestFullscreen?.().catch(() => {});
        }
    };

    #handleFullscreenChange = () => {
        if (this.#fullscreenButton) {
            const icons = galleryAssets.icons;
            this.#fullscreenButton.innerHTML = document.fullscreenElement ? icons.fullscreenExit : icons.fullscreen;
        }
    };

    #handleDoubleClick = (e) => {
        if (e.target.tagName === 'IMG') {
            this.#toggleZoom();
        }
    };

    // Desktop panning: only meaningful once zoomed in.
    #handleMouseDown = (e) => {
        if (this.#scale === 1 || e.target.tagName !== 'IMG') {
            return;
        }
        e.preventDefault();
        this.#dragStart = {x: e.clientX, y: e.clientY, panX: this.#panX, panY: this.#panY};
        document.addEventListener('mousemove', this.#handleMouseMove);
        document.addEventListener('mouseup', this.#handleMouseUp);
    };

    #handleMouseMove = (e) => {
        if (!this.#dragStart) {
            return;
        }
        this.#panX = this.#dragStart.panX + (e.clientX - this.#dragStart.x);
        this.#panY = this.#dragStart.panY + (e.clientY - this.#dragStart.y);
        this.#applyTransform();
    };

    #handleMouseUp = () => {
        this.#dragStart = null;
        document.removeEventListener('mousemove', this.#handleMouseMove);
        document.removeEventListener('mouseup', this.#handleMouseUp);
    };

    #handleTouchStart = (e) => {
        if (e.touches.length === 2) {
            this.#pinchStart = {distance: this.#touchDistance(e.touches), scale: this.#scale};
            this.#touchStart = null;
            return;
        }
        const touch = e.touches[0];
        this.#touchStart = {x: touch.clientX, y: touch.clientY, time: Date.now()};
        if (this.#scale > 1) {
            this.#dragStart = {x: touch.clientX, y: touch.clientY, panX: this.#panX, panY: this.#panY};
        }
    };

    #handleTouchMove = (e) => {
        if (e.touches.length === 2 && this.#pinchStart) {
            e.preventDefault();   // stop the browser's own page zoom taking over
            const ratio = this.#touchDistance(e.touches) / this.#pinchStart.distance;
            this.#setScale(this.#pinchStart.scale * ratio);
            return;
        }
        // Panning a zoomed image; at scale 1 the move is a potential swipe, handled on touchend.
        if (this.#dragStart && this.#scale > 1 && e.touches.length === 1) {
            e.preventDefault();
            this.#panX = this.#dragStart.panX + (e.touches[0].clientX - this.#dragStart.x);
            this.#panY = this.#dragStart.panY + (e.touches[0].clientY - this.#dragStart.y);
            this.#applyTransform();
        }
    };

    #handleTouchEnd = (e) => {
        this.#pinchStart = null;
        this.#dragStart = null;
        if (!this.#touchStart) {
            return;
        }
        const touch = e.changedTouches[0];
        const deltaX = touch.clientX - this.#touchStart.x;
        const deltaY = touch.clientY - this.#touchStart.y;
        const elapsed = Date.now() - this.#touchStart.time;
        this.#touchStart = null;

        // A double-tap zooms. Only count taps that barely moved.
        if (Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10 && elapsed < DOUBLE_TAP_MS) {
            const now = Date.now();
            if (now - this.#lastTapAt < DOUBLE_TAP_MS) {
                this.#toggleZoom();
                this.#lastTapAt = 0;
                return;
            }
            this.#lastTapAt = now;
            return;
        }
        // Swiping pages only at scale 1 — while zoomed a drag is a pan, not a navigation.
        if (this.#scale === 1 && Math.abs(deltaX) > SWIPE_THRESHOLD && Math.abs(deltaX) > Math.abs(deltaY)) {
            deltaX < 0 ? this.next() : this.previous();
        }
    };

    #touchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.hypot(dx, dy) || 1;
    }

    #emit(name, payload) {
        this.#eventEmitter?.emit(name, payload);
    }

    destroy() {
        document.removeEventListener('keydown', this.#handleKeydown);
        document.removeEventListener('fullscreenchange', this.#handleFullscreenChange);
        document.removeEventListener('mousemove', this.#handleMouseMove);
        document.removeEventListener('mouseup', this.#handleMouseUp);
        document.body.classList.remove(gallerySelectors.classes.scrollLocked);
        this.#root?.remove();
        this.#root = null;
        this.#stage = null;
        this.#slides = [];
        this.#images = [];
        this.#setupComplete = false;
    }
}
