import {gallerySelectors} from "./gallerySelectors.js";
import {events} from "./events.js";
import GalleryImage from "./GalleryImage.js";
import Lightbox from "./Lightbox.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";

const LAYOUTS = Object.freeze({
    grid: gallerySelectors.classes.layoutGrid,
    justified: gallerySelectors.classes.layoutJustified,
    masonry: gallerySelectors.classes.layoutMasonry,
});

const DEFAULTS = Object.freeze({
    layout: 'grid',
    visibleCount: null,     // null = show every image; a number caps them and adds a "+N" tile
    gap: 10,
    tileWidth: 220,         // grid: minimum tile width before the row re-flows
    tileRatio: 3 / 2,       // grid: tile aspect ratio
    rowHeight: 220,         // justified: the height rows aim for
    columns: 3,             // masonry: the most columns to use
    minColumnWidth: 140,    // masonry: how narrow a column may get before dropping one
    captions: true,         // show a caption overlay on hover when the image has one
    lightbox: true,
});

/**
 * A thumbnail gallery with a lightbox.
 *
 * Works two ways, so it suits both a server-rendered page and a JS-built one:
 *
 *   - **From markup** — the page already contains the `.galleryItem` elements. The gallery just
 *     enhances them, so the images are in the HTML for crawlers and still link somewhere
 *     without JS.
 *   - **From data** — pass `images: [{id, src, big, caption, alt, width, height}]` and it
 *     renders the tiles itself.
 *
 * Three layouts: `grid`, `justified` (rows scaled to full width, aspect preserved) and
 * `masonry` (shortest-column packing). Grid is pure CSS; the other two arrange from aspect
 * ratios, so they measure and re-run on resize.
 */
export default class Gallery {

    #setupComplete = false;
    #container = null;
    #options;
    #images = [];
    #items = [];            // the tile elements, index-aligned with #images
    #placeholders = [];     // skeleton tiles shown while fetching — never part of #images
    #lightbox = null;
    #resizeTimer = null;
    #pendingMeasure = 0;    // images still to report their natural size (measuring layouts)

    eventEmitter;

    static get LAYOUTS() {
        return Object.keys(LAYOUTS);
    }

    constructor({container = null, containerId = null, images = null, options = {}, eventEmitter = null} = {}) {
        this.#container = container || (containerId ? document.getElementById(containerId) : null);
        this.#options = {...DEFAULTS, ...options};
        this.#images = Array.isArray(images) ? images.map((data, i) => GalleryImage.fromData(data, i)) : null;
        this.eventEmitter = eventEmitter || new EventEmitter();
    }

    init() {
        if (this.#setupComplete) {
            return this;
        }
        if (!this.#container) {
            console.warn('Gallery: no container element. Pass { container } or { containerId }.');
            return this;
        }
        if (!LAYOUTS[this.#options.layout]) {
            console.warn(`Gallery: unknown layout "${this.#options.layout}" — falling back to grid.`);
            this.#options.layout = 'grid';
        }
        // Data given? Render the tiles. Otherwise read whatever the page already rendered.
        this.#images ? this.#renderItems() : this.#collectItems();
        if (!this.#images.length) {
            console.warn('Gallery: no images found.');
            return this;
        }
        // Visibility first: the measuring layouts arrange only the visible tiles, so they need
        // to know what `visibleCount` hid before they run.
        this.#applyVisibility();
        this.#applyLayout();
        this.#addListeners();
        if (this.#options.lightbox !== false) {
            this.#initLightbox();
        }
        this.#setupComplete = true;
        this.eventEmitter.emit(events.galleryRendered, {gallery: this, count: this.#images.length});
        return this;
    }

    getImages() {
        return this.#images;
    }

    getLightbox() {
        return this.#lightbox;
    }

    open(index = 0, options = {}) {
        this.#lightbox?.open(index, options);
        return this;
    }

    /**
     * Open by the image's `id` — how a deep link, or a project's own legacy URL scheme, gets in.
     * Pass `{silent: true}` for an on-load deep link so it doesn't fire `onNavigate`, matching
     * what the built-in hash handling does.
     */
    openById(id, options = {}) {
        const index = this.#images.findIndex((image) => image.id === String(id));
        if (index !== -1) {
            this.#lightbox?.open(index, options);
        }
        return this;
    }

    /* ------------------------------ Placeholders ----------------------------- */

    /**
     * Show skeleton tiles while a fetch is in flight, so the gallery reserves its space instead
     * of the page jumping when the images land.
     *
     * They are deliberately **not** images: they never enter `#images`, so `getImages()`, the
     * lightbox, `openById` and the "+N" count all carry on describing real content only. They're
     * laid out like tiles and nothing more.
     *
     * Cleared automatically by `addImages` / `setImages`, so the usual flow needs no teardown.
     */
    showPlaceholders(count = 4) {
        if (!this.#container || count < 1) {
            return this;
        }
        const limit = this.#options.visibleCount;
        // Already at the cap: extra tiles would be hidden the moment they appeared, so a
        // placeholder would just flicker. Nothing to show.
        if (limit && this.#items.length >= limit) {
            return this;
        }
        this.#flattenColumns();
        for (let i = 0; i < count; i++) {
            const element = document.createElement('div');
            element.classList.add(gallerySelectors.classes.item, gallerySelectors.classes.placeholder);
            element.setAttribute('aria-hidden', 'true');   // decorative; nothing to announce
            this.#placeholders.push(element);
            this.#container.appendChild(element);
        }
        this.#applyLayout();
        return this;
    }

    hidePlaceholders() {
        if (!this.#placeholders.length) {
            return this;
        }
        this.#flattenColumns();
        this.#placeholders.forEach((element) => element.remove());
        this.#placeholders = [];
        this.#applyLayout();
        return this;
    }

    isShowingPlaceholders() {
        return this.#placeholders.length > 0;
    }

    // Every element a layout should arrange: real tiles first, then any placeholders — which is
    // also their DOM order, since placeholders are always appended last.
    #tiles() {
        return [...this.#items, ...this.#placeholders];
    }

    // A tile's aspect ratio. Placeholders have no image behind them, so they take the configured
    // tile shape — that's what makes the skeleton the size the real thing will be.
    #ratioFor(item) {
        if (item.classList.contains(gallerySelectors.classes.placeholder)) {
            return this.#options.tileRatio;
        }
        const index = Number(item.getAttribute(gallerySelectors.attributes.index));
        return this.#images[index]?.aspectRatio() || 1.5;
    }

    /* ----------------------------- Changing images --------------------------- */

    /**
     * Append images after `init()` — for a "load more" button, an upload finishing, or a filter
     * widening. Accepts the same `{id, src, big, caption, alt, width, height}` shape as `images`.
     */
    addImages(images) {
        const list = Array.isArray(images) ? images : [images];
        if (!list.length) {
            return this;
        }
        // Columns own the tiles in masonry, so flatten before appending or the new ones land in
        // the container while the rest sit inside column wrappers.
        this.#flattenColumns();
        // The images these were standing in for have arrived.
        this.#placeholders.forEach((element) => element.remove());
        this.#placeholders = [];
        list.forEach((data) => {
            const image = GalleryImage.fromData(data, this.#images.length);
            const item = this.#createItem(image);
            this.#images.push(image);
            this.#items.push(item);
            this.#container.appendChild(item);
        });
        this.#refresh();
        return this;
    }

    /** Replace the whole set — a filter or a new album. */
    setImages(images) {
        this.#flattenColumns();
        this.#items.forEach((item) => item.remove());
        this.#items = [];
        this.#images = [];
        return this.addImages(images || []);
    }

    /** Remove by image `id`, or by position if you pass a number. */
    removeImage(idOrIndex) {
        const index = typeof idOrIndex === 'number'
            ? idOrIndex
            : this.#images.findIndex((image) => image.id === String(idOrIndex));
        if (index < 0 || index >= this.#images.length) {
            return this;
        }
        this.#flattenColumns();
        this.#items[index].remove();
        this.#items.splice(index, 1);
        this.#images.splice(index, 1);
        this.#refresh();
        return this;
    }

    /**
     * Put the gallery back in a consistent state after the image set changed.
     *
     * Reindexing first is what makes the rest safe: a `GalleryImage.index` and the tile's
     * `data-index` are how the layouts and the lightbox address an image, so after an insert or a
     * removal every position has to be renumbered before anything reads them again.
     */
    #refresh() {
        this.#images.forEach((image, index) => {
            image.index = index;
            this.#items[index]?.setAttribute(gallerySelectors.attributes.index, String(index));
        });
        this.#applyVisibility();   // the "+N" count moved
        this.#applyLayout();       // re-measure and re-pack
        this.#lightbox?.setImages(this.#images);
        this.eventEmitter.emit(events.imagesChanged, {images: this.#images, count: this.#images.length});
    }

    /* --------------------------- Items: read or build ------------------------ */

    // Enhance existing markup: every `.galleryItem` in the container becomes an image.
    #collectItems() {
        const c = gallerySelectors.classes;
        this.#items = [...this.#container.querySelectorAll(`.${c.item}`)];
        this.#images = this.#items.map((element, index) => GalleryImage.fromElement(element, index));
        this.#items.forEach((element, index) => this.#decorateItem(element, this.#images[index]));
    }

    #renderItems() {
        this.#container.innerHTML = '';
        this.#items = this.#images.map((image) => {
            const item = this.#createItem(image);
            this.#container.appendChild(item);
            return item;
        });
    }

    // One tile. Split out from #renderItems so images can also be added after init.
    #createItem(image) {
        const item = document.createElement(image.big ? 'a' : 'div');
        if (image.big) {
            item.href = image.big;   // still a real link before JS runs
        }
        item.classList.add(gallerySelectors.classes.item);
        const element = document.createElement('img');
        element.src = image.src;
        element.alt = image.alt;
        element.loading = 'lazy';
        item.appendChild(element);
        this.#decorateItem(item, image);
        return item;
    }

    // Shared between both modes: the index marker and the hover caption.
    #decorateItem(item, image) {
        const c = gallerySelectors.classes;
        item.setAttribute(gallerySelectors.attributes.index, String(image.index));
        const existing = item.querySelector(`.${c.caption}`);
        if (!this.#options.captions || !image.hasCaption()) {
            existing?.remove();   // an empty caption element would still darken the tile on hover
            return;
        }
        if (existing) {
            return;
        }
        const caption = document.createElement('span');
        caption.classList.add(c.caption);
        caption.textContent = image.caption;
        item.appendChild(caption);
    }

    /* --------------------------------- Layout -------------------------------- */

    #applyLayout() {
        const c = gallerySelectors.classes;
        const options = this.#options;
        this.#container.classList.add(c.gallery);
        Object.values(LAYOUTS).forEach((cls) => this.#container.classList.remove(cls));
        this.#container.classList.add(LAYOUTS[options.layout]);

        // Everything the CSS needs is a custom property, so layouts stay in the stylesheet.
        this.#container.style.setProperty('--galleryGap', `${options.gap}px`);
        this.#container.style.setProperty('--galleryTileWidth', `${options.tileWidth}px`);
        this.#container.style.setProperty('--galleryTileRatio', String(options.tileRatio));
        this.#container.style.setProperty('--galleryColumns', String(options.columns));
        this.#container.style.setProperty('--galleryRowHeight', `${options.rowHeight}px`);

        if (this.#measures()) {
            this.#measureThenLayout();
        }
    }

    // Justified and masonry arrange from aspect ratios, so they measure and re-run on resize.
    // Grid is pure CSS and needs neither.
    #measures() {
        return this.#options.layout === 'justified' || this.#options.layout === 'masonry';
    }

    // Run whichever measuring layout is active. Masonry moves tiles into column wrappers, so
    // anything that re-lays out has to put them back first.
    #relayout() {
        this.#flattenColumns();
        if (this.#options.layout === 'justified') {
            this.#layoutJustified();
        } else if (this.#options.layout === 'masonry') {
            this.#layoutMasonry();
        }
    }

    // Both measuring layouts need each image's aspect ratio. Anything without width/height in
    // its data has to load first, so we lay out once now (with a neutral guess) and again once
    // the real sizes arrive.
    #measureThenLayout() {
        this.#relayout();
        const unknown = this.#images.filter((image) => !image.aspectRatio());
        this.#pendingMeasure = unknown.length;
        if (!this.#pendingMeasure) {
            return;
        }
        unknown.forEach((image) => {
            const element = this.#items[image.index]?.querySelector('img');
            if (!element) {
                this.#pendingMeasure--;
                return;
            }
            const record = () => {
                image.width = element.naturalWidth || null;
                image.height = element.naturalHeight || null;
                if (--this.#pendingMeasure <= 0) {
                    this.#relayout();
                }
            };
            element.complete && element.naturalWidth
                ? record()
                : element.addEventListener('load', record, {once: true});
        });
    }

    /**
     * Fill each row to the container's width, keeping every image's aspect ratio.
     *
     * Images are accumulated until scaling the row to full width would push it below the target
     * height, which is the point where the row is "full". The final row keeps the target height
     * rather than stretching a stray image across the page.
     */
    #layoutJustified() {
        const {gap, rowHeight} = this.#options;
        const available = this.#container.clientWidth;
        if (!available) {
            return;
        }
        const visible = this.#tiles().filter((item) => !item.classList.contains(gallerySelectors.classes.itemHidden));
        let row = [];
        let ratioSum = 0;

        const flush = (isLast) => {
            if (!row.length) {
                return;
            }
            const width = available - gap * (row.length - 1);
            const height = isLast ? Math.min(rowHeight, width / ratioSum) : width / ratioSum;
            row.forEach(({item, ratio}) => {
                item.style.width = `${Math.floor(height * ratio)}px`;
                item.style.height = `${Math.floor(height)}px`;
            });
            row = [];
            ratioSum = 0;
        };

        visible.forEach((item) => {
            const ratio = this.#ratioFor(item);
            row.push({item, ratio});
            ratioSum += ratio;
            const width = available - gap * (row.length - 1);
            if (width / ratioSum <= rowHeight) {
                flush(false);
            }
        });
        flush(true);
    }

    /**
     * Distribute tiles into columns, each going to whichever column is currently shortest.
     *
     * CSS `column-count` can do columns with no JS, but it fills them *top to bottom*: with three
     * columns, image 2 lands underneath image 1 instead of beside it, so a chronological set
     * reads down-then-across. Placing the tiles ourselves keeps the natural left-to-right order
     * and gives true shortest-column packing, which is what makes the columns end up even.
     */
    #layoutMasonry() {
        const {gap} = this.#options;
        const available = this.#container.clientWidth;
        if (!available) {
            return;
        }
        const count = this.#resolveColumns(available);
        const columnWidth = (available - gap * (count - 1)) / count;
        const columns = [];
        const heights = new Array(count).fill(0);

        for (let i = 0; i < count; i++) {
            const column = document.createElement('div');
            column.classList.add(gallerySelectors.classes.column);
            columns.push(column);
            this.#container.appendChild(column);
        }

        this.#tiles()
            .filter((item) => !item.classList.contains(gallerySelectors.classes.itemHidden))
            .forEach((item) => {
                // Ties resolve to the leftmost column, which is what keeps the first row in order.
                let shortest = 0;
                for (let i = 1; i < count; i++) {
                    if (heights[i] < heights[shortest]) {
                        shortest = i;
                    }
                }
                const ratio = this.#ratioFor(item);
                item.style.width = '';
                item.style.height = '';
                columns[shortest].appendChild(item);
                heights[shortest] += (columnWidth / ratio) + gap;
            });
    }

    /**
     * How many columns fit. JS owns this now that it builds the columns — a CSS media query
     * can't reach the packing decision.
     *
     * Driven by `minColumnWidth` rather than screen breakpoints, so it's the same idea the grid
     * layout already expresses with `minmax(tileWidth, 1fr)`: keep adding columns while they stay
     * wide enough to be worth looking at. `columns` is the ceiling.
     *
     * The practical upshot is that masonry **stays masonry on a phone** — a 375px screen still
     * fits two columns at the default. Dropping to one would turn it into a plain stack, which is
     * the one arrangement masonry exists to avoid.
     */
    #resolveColumns(available) {
        const wanted = Math.max(1, Number(this.#options.columns) || 1);
        const minWidth = Math.max(1, Number(this.#options.minColumnWidth) || 1);
        const gap = this.#options.gap;
        // n columns need n*minWidth + (n-1)*gap, which rearranges to this.
        const fits = Math.floor((available + gap) / (minWidth + gap));
        return Math.max(1, Math.min(fits, wanted));
    }

    // Put the tiles back in the container and drop the column wrappers, so the next layout (or a
    // re-run of this one) starts from a flat list.
    #flattenColumns() {
        const columns = [...this.#container.querySelectorAll(`.${gallerySelectors.classes.column}`)];
        if (!columns.length) {
            return;
        }
        this.#tiles().forEach((item) => this.#container.appendChild(item));
        columns.forEach((column) => column.remove());
    }

    /* ------------------------- Visible count / "+N" tile --------------------- */

    // With `visibleCount` set, later tiles are hidden and the last visible one gets a "+N"
    // overlay. The hidden images still exist — the lightbox pages through all of them.
    #applyVisibility() {
        const c = gallerySelectors.classes;
        const limit = this.#options.visibleCount;
        this.#items.forEach((item) => {
            item.classList.remove(c.itemHidden, c.lastVisible);
            item.querySelector(`.${c.extras}`)?.remove();
        });
        if (!limit || limit >= this.#items.length) {
            return;
        }
        this.#items.forEach((item, index) => {
            if (index >= limit) {
                item.classList.add(c.itemHidden);
            }
        });
        const last = this.#items[limit - 1];
        last.classList.add(c.lastVisible);
        const overlay = document.createElement('span');
        overlay.classList.add(c.extras);
        overlay.textContent = `+${this.#items.length - limit}`;
        last.appendChild(overlay);
    }

    // Reveal everything that `visibleCount` hid — for a "show all" control.
    showAll() {
        this.#options.visibleCount = null;
        this.#applyVisibility();
        this.#relayout();
        return this;
    }

    setLayout(layout) {
        if (!LAYOUTS[layout]) {
            return this;
        }
        this.#options.layout = layout;
        // Leave no trace of the previous layout: column wrappers and the px sizes justified set.
        this.#flattenColumns();
        this.#items.forEach((item) => { item.style.width = ''; item.style.height = ''; });
        this.#applyLayout();
        return this;
    }

    /* -------------------------------- Lightbox ------------------------------- */

    #initLightbox() {
        const lightboxOptions = typeof this.#options.lightbox === 'object' ? this.#options.lightbox : {};
        this.#lightbox = new Lightbox({
            images: this.#images,
            options: lightboxOptions,
            eventEmitter: this.eventEmitter,
        }).init();
        // Reopen a shared link. Done after init so every slide already exists.
        const hashId = this.#lightbox.readHashId();
        if (hashId) {
            const index = this.#images.findIndex((image) => image.id === hashId);
            if (index !== -1) {
                this.#lightbox.open(index, {silent: true});   // silent: a deep link isn't a navigation
            }
        }
    }

    /* -------------------------------- Listeners ------------------------------ */

    #addListeners() {
        this.#container.addEventListener('click', this.#handleClick);
        // Always listen: setLayout() can switch to a measuring layout after init.
        window.addEventListener('resize', this.#handleResize);
    }

    #handleClick = (e) => {
        const item = e.target.closest(`.${gallerySelectors.classes.item}`);
        if (!item || !this.#container.contains(item)) {
            return;
        }
        // Placeholders share the tile class but stand for nothing — clicking one must not try to
        // open a lightbox slide that doesn't exist.
        if (item.classList.contains(gallerySelectors.classes.placeholder)) {
            return;
        }
        e.preventDefault();   // the tile may be an <a> to the full-size image (the no-JS path)
        const index = Number(item.getAttribute(gallerySelectors.attributes.index));
        this.eventEmitter.emit(events.imageClicked, {index, image: this.#images[index]});
        this.#lightbox?.open(index);
    };

    #handleResize = () => {
        if (!this.#measures()) {
            return;
        }
        clearTimeout(this.#resizeTimer);
        this.#resizeTimer = setTimeout(() => this.#relayout(), 150);
    };

    destroy() {
        clearTimeout(this.#resizeTimer);
        this.#container?.removeEventListener('click', this.#handleClick);
        window.removeEventListener('resize', this.#handleResize);
        this.#lightbox?.destroy();
        this.#lightbox = null;
        this.#placeholders.forEach((element) => element.remove());
        this.#placeholders = [];
        this.#items = [];
        this.#images = [];
        this.#container = null;
        this.#setupComplete = false;
    }
}
