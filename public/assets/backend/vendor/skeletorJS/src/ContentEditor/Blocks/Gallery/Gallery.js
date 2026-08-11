import Block from "../Block.js";
import {mediaLibrarySelectors} from "../../../MediaLibrary/mediaLibrarySelectors.js";
import {events as mediaLibraryEvents} from "../../../MediaLibrary/events.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";
import Translator from "../../../Translator/Translator.js";

const REMOVE_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>`;

// Frontend defaults, mirrored here so the sidebar has something to show before anything is set.
const DEFAULT_OPTIONS = Object.freeze({
    layout: 'grid',
    visibleCount: null,
    gap: 10,
    captions: true,
    lightbox: true,
    tileWidth: 220,
    tileRatio: 1.5,
    rowHeight: 220,
    columns: 3,
    minColumnWidth: 140,
});

/**
 * The three layouts, each with a miniature of how it arranges images.
 *
 * The preview is a diagram rather than a description because the difference between these is
 * purely visual — "rows scaled to full width, aspect preserved" means far less than seeing it.
 * `fields` lists which settings that layout actually uses, which is what drives the conditional
 * controls: showing `columns` while a grid is selected would just be noise.
 */
const LAYOUTS = Object.freeze([
    {
        key: 'grid',
        label: 'Grid',
        description: 'Uniform tiles, cropped to a fixed shape. Tidy, but edges may be cut off.',
        fields: ['tileWidth', 'tileRatio'],
        icon: '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="6" height="6" rx="1"/><rect x="9" y="3" width="6" height="6" rx="1"/><rect x="16" y="3" width="6" height="6" rx="1"/><rect x="2" y="10" width="6" height="6" rx="1"/><rect x="9" y="10" width="6" height="6" rx="1"/><rect x="16" y="10" width="6" height="6" rx="1"/></svg>',
        preview: '<svg viewBox="0 0 120 76"><rect x="0" y="0" width="38" height="34" rx="2"/><rect x="41" y="0" width="38" height="34" rx="2"/><rect x="82" y="0" width="38" height="34" rx="2"/><rect x="0" y="37" width="38" height="34" rx="2"/><rect x="41" y="37" width="38" height="34" rx="2"/><rect x="82" y="37" width="38" height="34" rx="2"/></svg>',
    },
    {
        key: 'justified',
        label: 'Justified',
        description: 'Rows filled to the full width, every aspect ratio kept. Nothing is cropped.',
        fields: ['rowHeight'],
        icon: '<svg viewBox="0 0 24 24"><rect x="2" y="4" width="9" height="6" rx="1"/><rect x="12" y="4" width="4" height="6" rx="1"/><rect x="17" y="4" width="5" height="6" rx="1"/><rect x="2" y="11" width="5" height="6" rx="1"/><rect x="8" y="11" width="8" height="6" rx="1"/><rect x="17" y="11" width="5" height="6" rx="1"/></svg>',
        preview: '<svg viewBox="0 0 120 76"><rect x="0" y="0" width="52" height="30" rx="2"/><rect x="55" y="0" width="26" height="30" rx="2"/><rect x="84" y="0" width="36" height="30" rx="2"/><rect x="0" y="33" width="30" height="26" rx="2"/><rect x="33" y="33" width="48" height="26" rx="2"/><rect x="84" y="33" width="36" height="26" rx="2"/><rect x="0" y="62" width="44" height="14" rx="2"/><rect x="47" y="62" width="30" height="14" rx="2"/></svg>',
    },
    {
        key: 'masonry',
        label: 'Masonry',
        description: 'Columns of varying heights, nothing cropped. Each image joins the shortest column, and it stays multi-column on a phone.',
        fields: ['columns', 'minColumnWidth'],
        icon: '<svg viewBox="0 0 24 24"><rect x="2" y="3" width="6" height="8" rx="1"/><rect x="2" y="12" width="6" height="5" rx="1"/><rect x="9" y="3" width="6" height="5" rx="1"/><rect x="9" y="9" width="6" height="8" rx="1"/><rect x="16" y="3" width="6" height="10" rx="1"/><rect x="16" y="14" width="6" height="3" rx="1"/></svg>',
        preview: '<svg viewBox="0 0 120 76"><rect x="0" y="0" width="38" height="40" rx="2"/><rect x="0" y="43" width="38" height="26" rx="2"/><rect x="41" y="0" width="38" height="26" rx="2"/><rect x="41" y="29" width="38" height="40" rx="2"/><rect x="82" y="0" width="38" height="52" rx="2"/><rect x="82" y="55" width="38" height="20" rx="2"/></svg>',
    },
]);

// Every numeric/boolean setting the sidebar can render, with how to read and clamp it.
const FIELDS = Object.freeze({
    tileWidth:    {label: 'Tile width (px)', type: 'number', min: 60, max: 800, step: 10},
    tileRatio:    {label: 'Tile ratio (w ÷ h)', type: 'number', min: 0.2, max: 4, step: 0.1},
    rowHeight:    {label: 'Row height (px)', type: 'number', min: 60, max: 600, step: 10},
    columns:      {label: 'Max columns', type: 'number', min: 1, max: 6, step: 1},
    minColumnWidth: {label: 'Min column width (px)', type: 'number', min: 80, max: 400, step: 10},
    gap:          {label: 'Gap (px)', type: 'number', min: 0, max: 60, step: 1},
    visibleCount: {label: 'Show only first N (blank = all)', type: 'number', min: 1, max: 50, step: 1, nullable: true},
});

export default class Gallery extends Block {
    static label = 'Gallery';
    static keywords = ['gallery', 'images', 'photos', 'grid'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M360-400h400L622-580l-92 120-62-80-108 140Zm-40 160q-33 0-56.5-23.5T240-320v-480q0-33 23.5-56.5T320-880h480q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H320ZM160-80q-33 0-56.5-23.5T80-160v-560h80v560h560v80H160Z"/></svg>`;
    static isText = false;
    static name = 'core/gallery';
    static category = 'media';
    static description = 'Display multiple images in a rich grid.';
    static advancedSidebarOpen = false;
    element;
    previewElement;
    imagesContainer;
    images = [];
    options = {...DEFAULT_OPTIONS};
    #draggedItem = null;
    #gallerySidebarModule = null;
    #layoutGrid = null;
    #layoutCard = null;
    #conditionalGroups = new Map();   // layout key -> the element holding its own settings
    #sidebarListeners = [];

    render() {
        // Options travel with the block, so a saved gallery comes back looking the way it was
        // arranged. Unknown or missing keys fall back to the frontend defaults.
        this.options = {...DEFAULT_OPTIONS, ...(this.data?.options || {})};

        this.element = document.createElement('div');
        this.element.tabIndex = -1;

        this.imagesContainer = document.createElement('div');
        this.imagesContainer.classList.add(contentEditorSelectors.classes.galleryBlockGrid);
        this.element.appendChild(this.imagesContainer);

        this.previewElement = document.createElement('div');
        this.previewElement.classList.add(mediaLibrarySelectors.classes.initiator);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.insertable, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.allowImages, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.multiple, true);
        this.previewElement.classList.add(contentEditorSelectors.classes.imageBlockPreview);
        this.previewElement.innerHTML = this.constructor.icon;
        const text = document.createElement('span');
        text.textContent = Translator.translate('Choose Images');
        this.previewElement.appendChild(text);
        this.element.appendChild(this.previewElement);

        if (Array.isArray(this.data.images)) {
            this.data.images.forEach((image) => {
                if (image.mediaId && image.src) {
                    this.#appendImageFromData(image);
                }
            });
        }

        this.#listenToEvents();
        this.#addListeners();
        return this.element;
    }

    #listenToEvents() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
    }

    #handleMediaReadyForInsert = (data) => {
        if (data.initiator !== this.previewElement || !Array.isArray(data.mediaData)) {
            return;
        }
        data.mediaData.forEach((media) => {
            if (media?.img) {
                this.#appendImageFromMedia(media);
            }
        });
    }

    #appendImageFromMedia(media) {
        // media.id is the MediaLibrary payload's own key, not this block's data shape.
        this.#addImage({mediaId: media.id, src: media.filename, imgHtml: media.img});
    }

    #appendImageFromData(image) {
        this.#addImage({mediaId: image.mediaId, src: image.src, imgHtml: null});
    }

    #addImage({mediaId, src, imgHtml}) {
        const wrapper = document.createElement('div');
        wrapper.classList.add(contentEditorSelectors.classes.galleryBlockItem);
        wrapper.draggable = true;
        if (imgHtml) {
            wrapper.insertAdjacentHTML('afterbegin', imgHtml);
        } else {
            const img = document.createElement('img');
            img.src = this.config.imagePath ?? '' + src;
            wrapper.appendChild(img);
        }
        wrapper.querySelectorAll('img').forEach((img) => {
            img.draggable = false;
        });
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.classList.add(contentEditorSelectors.classes.galleryBlockRemove);
        removeButton.innerHTML = REMOVE_ICON;
        const entry = {mediaId, src, wrapper, removeButton, removeHandler: null};
        entry.removeHandler = () => this.#removeImage(entry);
        removeButton.addEventListener('click', entry.removeHandler);
        wrapper.appendChild(removeButton);
        this.imagesContainer.appendChild(wrapper);
        this.images.push(entry);
    }

    #removeImage(entry) {
        entry.removeButton.removeEventListener('click', entry.removeHandler);
        entry.wrapper.remove();
        this.images = this.images.filter((image) => image !== entry);
    }

    #detachImageListeners() {
        this.images.forEach((entry) => {
            entry.removeButton.removeEventListener('click', entry.removeHandler);
        });
    }

    #clearImages() {
        this.#detachImageListeners();
        this.images = [];
        this.imagesContainer.innerHTML = '';
    }

    #addListeners() {
        this.previewElement.addEventListener('click', this.#openMediaLibrary);
        this.imagesContainer.addEventListener('dragstart', this.#handleDragStart);
        this.imagesContainer.addEventListener('dragover', this.#handleDragOver);
        this.imagesContainer.addEventListener('drop', this.#handleDrop);
        this.imagesContainer.addEventListener('dragend', this.#handleDragEnd);
    }

    #handleDragStart = (e) => {
        const item = e.target.closest(`.${contentEditorSelectors.classes.galleryBlockItem}`);
        if (!item) {
            return;
        }
        this.#draggedItem = item;
        e.dataTransfer.effectAllowed = 'move';
        item.classList.add(contentEditorSelectors.classes.galleryBlockDragging);
    }

    #handleDragOver = (e) => {
        if (!this.#draggedItem) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const target = e.target.closest(`.${contentEditorSelectors.classes.galleryBlockItem}`);
        if (!target || target === this.#draggedItem) {
            return;
        }
        const rect = target.getBoundingClientRect();
        const insertAfter = (e.clientX - rect.left) > rect.width / 2;
        this.imagesContainer.insertBefore(this.#draggedItem, insertAfter ? target.nextSibling : target);
    }

    #handleDrop = (e) => {
        e.preventDefault();
    }

    #handleDragEnd = () => {
        if (this.#draggedItem) {
            this.#draggedItem.classList.remove(contentEditorSelectors.classes.galleryBlockDragging);
        }
        this.#draggedItem = null;
        this.#syncImagesOrderFromDom();
    }

    #syncImagesOrderFromDom() {
        const orderedWrappers = Array.from(this.imagesContainer.children);
        this.images.sort((a, b) => orderedWrappers.indexOf(a.wrapper) - orderedWrappers.indexOf(b.wrapper));
    }

    #openMediaLibrary = () => {
        window.mediaLibrary.open(this.previewElement);
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }


    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.#gallerySidebarModule) {
            const c = contentEditorSelectors.classes;
            this.#gallerySidebarModule = SidebarSection.generate(
                Translator.translate('Gallery'),
                contentEditorSelectors.ids.gallerySidebar,
                this.eventEmitter,
                true
            );
            const content = this.#gallerySidebarModule.container
                .querySelector(`#${contentEditorSelectors.ids.gallerySidebar}`);

            content.appendChild(this.#buildLayoutGrid());

            // One group per layout, all built up front and shown or hidden as the layout changes.
            // Rebuilding on every switch would drop focus out of whichever field was being
            // edited, and these are cheap to keep around.
            LAYOUTS.forEach((layout) => {
                const group = document.createElement('div');
                group.classList.add(c.gallerySettingGroup);
                layout.fields.forEach((key) => group.appendChild(this.#buildField(key)));
                this.#conditionalGroups.set(layout.key, group);
                content.appendChild(group);
            });

            content.appendChild(this.#buildField('gap'));
            content.appendChild(this.#buildField('visibleCount'));
            content.appendChild(this.#buildToggle('captions', 'Show captions on hover'));
            content.appendChild(this.#buildToggle('lightbox', 'Open images in a lightbox'));

            this.#syncConditionalGroups();
            this.sidebarContainer.prepend(this.#gallerySidebarModule.container);
        }
        return this.sidebarContainer;
    }

    // The layout picker: icon buttons, the same shape as the chart type grid.
    #buildLayoutGrid() {
        const c = contentEditorSelectors.classes;
        this.#layoutGrid = document.createElement('div');
        this.#layoutGrid.classList.add(c.galleryLayoutGrid);
        LAYOUTS.forEach((layout) => {
            const option = document.createElement('div');
            option.classList.add(c.galleryLayoutOption);
            option.setAttribute(contentEditorSelectors.attributes.galleryLayout, layout.key);
            option.title = Translator.translate(layout.label);
            option.innerHTML = layout.icon;
            this.#track(option, 'click', () => this.#setLayout(layout.key));
            // Hovering explains the choice with a diagram: the difference between these layouts
            // is purely visual, so a picture says more than any label can.
            this.#track(option, 'mouseenter', () => this.#showLayoutCard(option, layout));
            this.#track(option, 'mouseleave', () => this.#hideLayoutCard());
            this.#layoutGrid.appendChild(option);
        });
        this.#updateLayoutActive();
        return this.#layoutGrid;
    }

    #updateLayoutActive() {
        const c = contentEditorSelectors.classes;
        this.#layoutGrid?.querySelectorAll(`.${c.galleryLayoutOption}`).forEach((option) => {
            const key = option.getAttribute(contentEditorSelectors.attributes.galleryLayout);
            option.classList.toggle(c.galleryLayoutOptionActive, key === this.options.layout);
        });
    }

    #setLayout(key) {
        if (this.options.layout === key) {
            return;
        }
        this.options.layout = key;
        this.#updateLayoutActive();
        this.#syncConditionalGroups();
        this.#hideLayoutCard();
        this.#commit();
    }

    // Only the settings the chosen layout actually uses are on screen — showing `columns` while
    // a grid is selected is just noise.
    #syncConditionalGroups() {
        const hidden = contentEditorSelectors.classes.hidden;
        this.#conditionalGroups.forEach((group, key) => {
            group.classList.toggle(hidden, key !== this.options.layout);
        });
    }

    /* ------------------------------ Hover card -------------------------------- */

    #showLayoutCard(option, layout) {
        const c = contentEditorSelectors.classes;
        if (!this.#layoutCard) {
            this.#layoutCard = document.createElement('div');
            this.#layoutCard.id = contentEditorSelectors.ids.galleryLayoutCard;
            this.#layoutCard.classList.add(c.galleryLayoutCard, c.hidden);
            document.body.appendChild(this.#layoutCard);
        }
        this.#layoutCard.innerHTML = `
            <div class="${c.galleryLayoutCardTitle}">${Translator.translate(layout.label)}</div>
            <div class="${c.galleryLayoutCardPreview}">${layout.preview}</div>
            <div class="${c.galleryLayoutCardDescription}">${Translator.translate(layout.description)}</div>`;
        this.#layoutCard.classList.remove(c.hidden);
        // To the left of the button: the sidebar is already against the screen edge, so a card
        // on the right would be off-screen.
        const rect = option.getBoundingClientRect();
        this.#layoutCard.style.top = `${rect.top}px`;
        this.#layoutCard.style.left = `${rect.left - this.#layoutCard.offsetWidth - 12}px`;
    }

    #hideLayoutCard() {
        this.#layoutCard?.classList.add(contentEditorSelectors.classes.hidden);
    }

    /* -------------------------------- Fields ---------------------------------- */

    #buildField(key) {
        const c = contentEditorSelectors.classes;
        const field = FIELDS[key];
        const wrapper = document.createElement('div');
        wrapper.classList.add(c.gallerySettingField);

        const label = document.createElement('label');
        label.textContent = Translator.translate(field.label);

        const input = document.createElement('input');
        input.type = 'number';
        input.classList.add(c.input);
        input.min = String(field.min);
        input.max = String(field.max);
        input.step = String(field.step);
        input.value = this.options[key] ?? '';

        this.#track(input, 'input', () => {
            const raw = input.value.trim();
            // Blank in a nullable field means "no limit" — not zero, which would hide everything.
            if (raw === '' && field.nullable) {
                this.options[key] = null;
            } else {
                const value = parseFloat(raw);
                this.options[key] = isFinite(value)
                    ? Math.min(Math.max(value, field.min), field.max)
                    : DEFAULT_OPTIONS[key];
            }
            this.#commit();
        });

        wrapper.append(label, input);
        return wrapper;
    }

    #buildToggle(key, labelText) {
        const wrapper = document.createElement('div');
        wrapper.classList.add(contentEditorSelectors.classes.gallerySettingRow, contentEditorSelectors.classes.inputContainer);

        const input = document.createElement('input');
        input.classList.add(contentEditorSelectors.classes.input);
        input.type = 'checkbox';
        input.checked = this.options[key] !== false;
        this.#track(input, 'change', () => {
            this.options[key] = input.checked;
            this.#commit();
        });

        const label = document.createElement('label');
        label.append(input, document.createTextNode(` ${Translator.translate(labelText)}`));
        wrapper.appendChild(label);
        return wrapper;
    }

    /**
     * Settings live on the instance, but the DOM is what the editor watches — so a change has to
     * land in an attribute for the observer to see it. Without this, adjusting a setting would
     * never be recorded in history and the unsaved-changes guard would think nothing happened.
     */
    #commit() {
        this.element.setAttribute(
            contentEditorSelectors.attributes.galleryOptions,
            JSON.stringify(this.options)
        );
    }

    #track(element, event, handler) {
        element.addEventListener(event, handler);
        this.#sidebarListeners.push({element, event, handler});
    }

    #clearSidebarListeners() {
        this.#sidebarListeners.forEach(({element, event, handler}) => {
            element.removeEventListener(event, handler);
        });
        this.#sidebarListeners = [];
    }

    destroySidebar() {
        this.#clearSidebarListeners();
        this.#hideLayoutCard();
        this.#gallerySidebarModule = null;
        this.#layoutGrid = null;
        this.#conditionalGroups.clear();
        super.destroySidebar();
    }

    getData() {
        return {
            images: this.images.map((image) => ({mediaId: image.mediaId, src: image.src})),
            options: {...this.options},
        };
    }

    destroy() {
        this.previewElement.removeEventListener('click', this.#openMediaLibrary);
        this.imagesContainer.removeEventListener('dragstart', this.#handleDragStart);
        this.imagesContainer.removeEventListener('dragover', this.#handleDragOver);
        this.imagesContainer.removeEventListener('drop', this.#handleDrop);
        this.imagesContainer.removeEventListener('dragend', this.#handleDragEnd);
        window.mediaLibrary.eventEmitter.remove(mediaLibraryEvents.mediaReadyForInsert, this.#handleMediaReadyForInsert);
        this.#detachImageListeners();
        this.#clearSidebarListeners();
        this.#layoutCard?.remove();
        this.#layoutCard = null;
        this.element.remove();
        super.destroy();
    }
}
