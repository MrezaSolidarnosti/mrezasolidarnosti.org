import Block from "../Block.js";
import {mediaLibrarySelectors} from "../../../MediaLibrary/mediaLibrarySelectors.js";
import {events as mediaLibraryEvents} from "../../../MediaLibrary/events.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";
import SidebarSection from "../../Sidebar/SidebarSection/SidebarSection.js";

export default class Image extends Block {
    static label = 'Image';
    static keywords = ['img', 'image'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm40-80h480L570-480 450-320l-90-120-120 160Zm-40 80v-560 560Z"/></svg>`;
    static isText = false;
    static name = 'core/image';
    static category = 'media';
    static description = 'Insert an image to make a visual statement.';
    // Alignment reuses the editor's own `data-align` plumbing rather than inventing a second
    // one: Block.getBlockData() already reads that attribute off the container and renderBlock
    // already restores it, so setting it here is all the persistence this needs. The comment on
    // that code says the plumbing is type-agnostic — this is the first non-text block to use it.
    static ALIGNMENTS = Object.freeze([
        {value: '',       label: 'None',   icon: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="1"/></svg>'},
        {value: 'left',   label: 'Left',   icon: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="11" height="14" rx="1"/><rect x="16" y="7" width="5" height="2" rx="1"/><rect x="16" y="11" width="5" height="2" rx="1"/><rect x="16" y="15" width="5" height="2" rx="1"/></svg>'},
        {value: 'center', label: 'Center', icon: '<svg viewBox="0 0 24 24"><rect x="6" y="5" width="12" height="14" rx="1"/></svg>'},
        {value: 'right',  label: 'Right',  icon: '<svg viewBox="0 0 24 24"><rect x="10" y="5" width="11" height="14" rx="1"/><rect x="3" y="7" width="5" height="2" rx="1"/><rect x="3" y="11" width="5" height="2" rx="1"/><rect x="3" y="15" width="5" height="2" rx="1"/></svg>'},
    ]);
    static advancedSidebarOpen = false;

    element;
    previewElement;
    imageId;
    src;
    link = {href: '', newTab: false, rel: ''};
    #sidebarModule = null;
    #alignGrid = null;
    #hrefInput = null;
    #newTabInput = null;
    #relInput = null;

    render() {
        this.link = Image.resolveLink(this.data && this.data.link);
        this.element = document.createElement('div');
        this.element.tabIndex = -1;

        this.previewElement = document.createElement('div');
        this.previewElement.classList.add(mediaLibrarySelectors.classes.initiator);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.insertable, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.allowImages, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.multiple, false);
        this.previewElement.classList.add(contentEditorSelectors.classes.imageBlockPreview);
        this.previewElement.innerHTML = this.constructor.icon;
        const text = document.createElement('span');
        text.textContent = Translator.translate('Choose an Image');
        this.previewElement.appendChild(text);
        this.element.appendChild(this.previewElement);

        if(this.data.mediaId && this.data.src) {
            const img = document.createElement('img');
            // Saved content stores the bare filename, so the display path goes on here. `this.src`
            // stays unprefixed — it is what getData() saves, and prefixing it would compound the
            // path on every save/load round trip.
            img.src = (this.config.imagePath ?? '') + this.data.src;
            this.src = this.data.src;
            this.imageId = this.data.mediaId;
            this.previewElement.appendChild(img);
        }
        this.#listenToEvents();
        this.#addListeners();
        return this.element;
    }

    #listenToEvents() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, (data) => {
            if(data.initiator === this.previewElement) {
                if(data?.mediaData[0]?.img) {
                    const existingImg = this.previewElement.querySelector('img');
                    if(existingImg) {
                        existingImg.remove();
                    }
                    // `img` is markup the media library already built with the full path in it —
                    // no prefix here, or the path would be doubled. Same as FeaturedImage.
                    this.previewElement.insertAdjacentHTML('beforeend', data.mediaData[0].img);
                    this.imageId = data?.mediaData[0].id
                    this.src = data?.mediaData[0].filename;
                }
            }
        });
    }

    #addListeners() {
        this.previewElement.addEventListener('click', this.#openMediaLibrary);
    }

    #openMediaLibrary = (e) => {
        window.mediaLibrary.open(this.previewElement);
    }

    getContainer() {
        return this.element;
    }

    getValue() {
        return this.element.value;
    }

    focus() {
        this.element.focus();
    }


    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.#sidebarModule) {
            this.#sidebarModule = SidebarSection.generate(
                Translator.translate('Image Settings'),
                contentEditorSelectors.ids.imageSidebar,
                this.eventEmitter,
                true
            );
            const content = this.#sidebarModule.container
                .querySelector(`#${contentEditorSelectors.ids.imageSidebar}`);

            content.appendChild(this.#buildAlignmentControl());
            content.appendChild(this.#buildLinkControl());
            this.sidebarContainer.prepend(this.#sidebarModule.container);
        }
        return this.sidebarContainer;
    }

    #buildAlignmentControl() {
        const c = contentEditorSelectors.classes;
        const container = document.createElement('div');
        container.classList.add(c.inputContainer);

        const label = document.createElement('label');
        label.textContent = Translator.translate('Alignment');

        this.#alignGrid = document.createElement('div');
        this.#alignGrid.classList.add(c.imageAlignGrid);
        Image.ALIGNMENTS.forEach((alignment) => {
            const option = document.createElement('div');
            option.classList.add(c.imageAlignOption);
            option.title = Translator.translate(alignment.label);
            option.setAttribute(contentEditorSelectors.attributes.blockAlign, alignment.value);
            option.innerHTML = alignment.icon;
            option.addEventListener('click', this.#handleAlignmentClick);
            this.#alignGrid.appendChild(option);
        });

        container.append(label, this.#alignGrid);
        this.#updateAlignmentActive();
        return container;
    }

    #buildLinkControl() {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.inputContainer);

        const label = document.createElement('label');
        label.textContent = Translator.translate('Link');

        this.#hrefInput = document.createElement('input');
        this.#hrefInput.type = 'text';
        this.#hrefInput.classList.add(contentEditorSelectors.classes.input);
        this.#hrefInput.placeholder = 'https://example.com';
        this.#hrefInput.value = this.link.href;
        this.#hrefInput.addEventListener('input', this.#handleLinkInput);

        const newTabLabel = document.createElement('label');
        this.#newTabInput = document.createElement('input');
        this.#newTabInput.type = 'checkbox';
        this.#newTabInput.classList.add(contentEditorSelectors.classes.input);
        this.#newTabInput.checked = this.link.newTab;
        this.#newTabInput.addEventListener('change', this.#handleLinkInput);
        newTabLabel.append(
            this.#newTabInput,
            document.createTextNode(' ' + Translator.translate('Open in new tab'))
        );

        const relLabel = document.createElement('label');
        relLabel.textContent = Translator.translate('Rel');
        this.#relInput = document.createElement('input');
        this.#relInput.type = 'text';
        this.#relInput.classList.add(contentEditorSelectors.classes.input);
        this.#relInput.spellcheck = false;
        this.#relInput.placeholder = 'nofollow';
        this.#relInput.value = this.link.rel;
        this.#relInput.addEventListener('input', this.#handleLinkInput);

        container.append(label, this.#hrefInput, newTabLabel, relLabel, this.#relInput);
        return container;
    }

    #handleAlignmentClick = (e) => {
        const option = e.target.closest('.' + contentEditorSelectors.classes.imageAlignOption);
        if (!option) {
            return;
        }
        const value = option.getAttribute(contentEditorSelectors.attributes.blockAlign);
        // Removed rather than set to '' for "none": getBlockData() only persists the attribute
        // when it holds a value, so an empty one would leave a stale `align` in the payload.
        if (value) {
            this.element.setAttribute(contentEditorSelectors.attributes.blockAlign, value);
        } else {
            this.element.removeAttribute(contentEditorSelectors.attributes.blockAlign);
        }
        this.#updateAlignmentActive();
    }

    #updateAlignmentActive() {
        const c = contentEditorSelectors.classes;
        const current = this.element.getAttribute(contentEditorSelectors.attributes.blockAlign) || '';
        if (!this.#alignGrid) {
            return;
        }
        this.#alignGrid.querySelectorAll('.' + c.imageAlignOption).forEach((option) => {
            const value = option.getAttribute(contentEditorSelectors.attributes.blockAlign);
            option.classList.toggle(c.imageAlignOptionActive, value === current);
        });
    }

    #handleLinkInput = () => {
        this.link = {
            href: this.#hrefInput.value.trim(),
            newTab: this.#newTabInput.checked,
            rel: this.#relInput.value.trim(),
        };
        // The content observer watches attributes only for href/target/rel/src/alt, and none of
        // those change here — so the settings are mirrored onto a watched attribute to make the
        // edit visible to history and to the unsaved-changes guard.
        this.element.setAttribute(
            contentEditorSelectors.attributes.imageSettings,
            JSON.stringify(this.link)
        );
    }

    // Shape-checked rather than trusted: an older payload has no `link` at all, and an import
    // can carry a bare URL string where the object is expected.
    static resolveLink(link) {
        if (typeof link === 'string') {
            return {href: link, newTab: false, rel: ''};
        }
        if (!link || typeof link !== 'object') {
            return {href: '', newTab: false, rel: ''};
        }
        return {
            href: typeof link.href === 'string' ? link.href : '',
            newTab: link.newTab === true,
            rel: typeof link.rel === 'string' ? link.rel : '',
        };
    }

    destroySidebar() {
        if (this.#alignGrid) {
            this.#alignGrid.querySelectorAll('.' + contentEditorSelectors.classes.imageAlignOption)
                .forEach((option) => option.removeEventListener('click', this.#handleAlignmentClick));
        }
        this.#hrefInput?.removeEventListener('input', this.#handleLinkInput);
        this.#newTabInput?.removeEventListener('change', this.#handleLinkInput);
        this.#relInput?.removeEventListener('input', this.#handleLinkInput);
        this.#alignGrid = null;
        this.#hrefInput = null;
        this.#newTabInput = null;
        this.#relInput = null;
        this.#sidebarModule = null;
        super.destroySidebar();
    }

    getData() {
        // `align` is deliberately absent: Block.getBlockData() reads it off the container, so
        // returning it here too would put the same value in the payload twice.
        return {
            mediaId: this.imageId ?? null,
            src: this.src ?? null,
            link: this.link.href ? {...this.link} : null,
        };
    }


    destroy() {
        this.previewElement.removeEventListener('click', this.#openMediaLibrary);
        this.element.remove();
        super.destroy();
    }
}
