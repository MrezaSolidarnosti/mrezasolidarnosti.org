import Block from "../Block.js";
import {mediaLibrarySelectors} from "../../../MediaLibrary/mediaLibrarySelectors.js";
import {events as mediaLibraryEvents} from "../../../MediaLibrary/events.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import Translator from "../../../Translator/Translator.js";

export default class File extends Block {
    static label = 'File';
    static keywords = ['file', 'files'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M176 48L64 48c-8.8 0-16 7.2-16 16l0 384c0 8.8 7.2 16 16 16l256 0c8.8 0 16-7.2 16-16l0-240-88 0c-39.8 0-72-32.2-72-72l0-88zM316.1 160L224 67.9 224 136c0 13.3 10.7 24 24 24l68.1 0zM0 64C0 28.7 28.7 0 64 0L197.5 0c17 0 33.3 6.7 45.3 18.7L365.3 141.3c12 12 18.7 28.3 18.7 45.3L384 448c0 35.3-28.7 64-64 64L64 512c-35.3 0-64-28.7-64-64L0 64z"/></svg>`;
    static isText = false;
    static name = 'core/file';
    static category = 'media';
    static description = 'Add a link to a downloadable file.';
    element;
    previewElement;
    fileId;
    mimeType;
    src;

    static MIME_TYPE_ICONS = {
        'image/': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M176 48L64 48c-8.8 0-16 7.2-16 16l0 384c0 8.8 7.2 16 16 16l256 0c8.8 0 16-7.2 16-16l0-240-88 0c-39.8 0-72-32.2-72-72l0-88zM316.1 160L224 67.9 224 136c0 13.3 10.7 24 24 24l68.1 0zM0 64C0 28.7 28.7 0 64 0L197.5 0c17 0 33.3 6.7 45.3 18.7L365.3 141.3c12 12 18.7 28.3 18.7 45.3L384 448c0 35.3-28.7 64-64 64L64 512c-35.3 0-64-28.7-64-64L0 64zM259.4 432l-134.8 0c-15.8 0-28.6-12.8-28.6-28.6 0-6.4 2.1-12.5 6-17.6l67.6-86.9C175 292 183.3 288 192 288s17 4 22.4 10.9L282 385.9c3.9 5 6 11.2 6 17.6 0 15.8-12.8 28.6-28.6 28.6zM112 224a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></svg>`,
        'audio/': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zM221.9 267.6c-4.7 10-.3 21.9 9.7 26.6 19.2 8.9 32.4 28.3 32.4 50.8s-13.2 41.9-32.4 50.8c-10 4.7-14.4 16.6-9.7 26.6s16.6 14.4 26.6 9.7C281.2 416.8 304 383.6 304 345s-22.8-71.9-55.6-87.1c-10-4.7-21.9-.3-26.6 9.7zM104 305c-13.3 0-24 10.7-24 24l0 32c0 13.3 10.7 24 24 24l16 0 27.2 34c3 3.8 7.6 6 12.5 6l.3 0c8.8 0 16-7.2 16-16l0-128c0-8.8-7.2-16-16-16l-.3 0c-4.9 0-9.5 2.2-12.5 6l-27.2 34-16 0zM223.3 373c9.9-5.4 16.7-16 16.7-28.1s-6.7-22.7-16.7-28.1c-7.8-4.2-15.3 3.3-15.3 12.1l0 32c0 8.8 7.6 16.3 15.3 12.1z"/></svg>`,
        'video/': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zM80 288l0 96c0 17.7 14.3 32 32 32l96 0c17.7 0 32-14.3 32-32l0-24 35 35c3.2 3.2 7.5 5 12 5 9.4 0 17-7.6 17-17l0-94.1c0-9.4-7.6-17-17-17-4.5 0-8.8 1.8-12 5l-35 35 0-24c0-17.7-14.3-32-32-32l-96 0c-17.7 0-32 14.3-32 32z"/></svg>`,
        'application/pdf': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M208 48L96 48c-8.8 0-16 7.2-16 16l0 384c0 8.8 7.2 16 16 16l80 0 0 48-80 0c-35.3 0-64-28.7-64-64L32 64C32 28.7 60.7 0 96 0L229.5 0c17 0 33.3 6.7 45.3 18.7L397.3 141.3c12 12 18.7 28.3 18.7 45.3l0 149.5-48 0 0-128-88 0c-39.8 0-72-32.2-72-72l0-88zM348.1 160L256 67.9 256 136c0 13.3 10.7 24 24 24l68.1 0zM240 380l32 0c33.1 0 60 26.9 60 60s-26.9 60-60 60l-12 0 0 28c0 11-9 20-20 20s-20-9-20-20l0-128c0-11 9-20 20-20zm32 80c11 0 20-9 20-20s-9-20-20-20l-12 0 0 40 12 0zm96-80l32 0c28.7 0 52 23.3 52 52l0 64c0 28.7-23.3 52-52 52l-32 0c-11 0-20-9-20-20l0-128c0-11 9-20 20-20zm32 128c6.6 0 12-5.4 12-12l0-64c0-6.6-5.4-12-12-12l-12 0 0 88 12 0zm76-108c0-11 9-20 20-20l48 0c11 0 20 9 20 20s-9 20-20 20l-28 0 0 24 28 0c11 0 20 9 20 20s-9 20-20 20l-28 0 0 44c0 11-9 20-20 20s-20-9-20-20l0-128z"/></svg>`,
        'application/msword': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm71.3 274.2c-3.2-12.9-16.2-20.7-29.1-17.5S85.5 273 88.7 285.8l32 128c2.5 10.2 11.4 17.5 21.9 18.1s20.1-5.7 23.8-15.5l25.5-68.1 25.5 68.1c3.7 9.8 13.3 16.1 23.8 15.5s19.4-7.9 21.9-18.1l32-128c3.2-12.9-4.6-25.9-17.5-29.1s-25.9 4.6-29.1 17.5l-13.3 53.2-20.9-55.8C211 262.2 202 256 192 256s-19 6.2-22.5 15.6l-20.9 55.8-13.3-53.2z"/></svg>`,
        'application/vnd.ms-excel' :`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm99.2 265.6c-8-10.6-23-12.8-33.6-4.8s-12.8 23-4.8 33.6L162 344 124.8 393.6c-8 10.6-5.8 25.6 4.8 33.6s25.6 5.8 33.6-4.8L192 384 220.8 422.4c8 10.6 23 12.8 33.6 4.8s12.8-23 4.8-33.6L222 344 259.2 294.4c8-10.6 5.8-25.6-4.8-33.6s-25.6-5.8-33.6 4.8L192 304 163.2 265.6z"/></svg>`,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm99.2 265.6c-8-10.6-23-12.8-33.6-4.8s-12.8 23-4.8 33.6L162 344 124.8 393.6c-8 10.6-5.8 25.6 4.8 33.6s25.6 5.8 33.6-4.8L192 384 220.8 422.4c8 10.6 23 12.8 33.6 4.8s12.8-23 4.8-33.6L222 344 259.2 294.4c8-10.6 5.8-25.6-4.8-33.6s-25.6-5.8-33.6 4.8L192 304 163.2 265.6z"/></svg>`,
        'application/vnd.ms-excel.sheet.macroEnabled.12': `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M64 48l112 0 0 88c0 39.8 32.2 72 72 72l88 0 0 240c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16L48 64c0-8.8 7.2-16 16-16zM224 67.9l92.1 92.1-68.1 0c-13.3 0-24-10.7-24-24l0-68.1zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-261.5c0-17-6.7-33.3-18.7-45.3L242.7 18.7C230.7 6.7 214.5 0 197.5 0L64 0zm99.2 265.6c-8-10.6-23-12.8-33.6-4.8s-12.8 23-4.8 33.6L162 344 124.8 393.6c-8 10.6-5.8 25.6 4.8 33.6s25.6 5.8 33.6-4.8L192 384 220.8 422.4c8 10.6 23 12.8 33.6 4.8s12.8-23 4.8-33.6L222 344 259.2 294.4c8-10.6 5.8-25.6-4.8-33.6s-25.6-5.8-33.6 4.8L192 304 163.2 265.6z"/></svg>`,
    };

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;

        this.previewElement = document.createElement('div');
        this.previewElement.classList.add(mediaLibrarySelectors.classes.initiator);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.insertable, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.allowDocuments, true);
        this.previewElement.setAttribute(mediaLibrarySelectors.attributes.multiple, false);
        this.previewElement.classList.add(contentEditorSelectors.classes.fileBlockPreview);
        this.previewElement.innerHTML = this.constructor.icon;
        const text = document.createElement('span');
        text.textContent = Translator.translate('Choose a File');
        this.previewElement.appendChild(text);
        this.element.appendChild(this.previewElement);

        if(this.data.mediaId && this.data.src && this.data.mimeType) {
            this.src = this.data.src;
            this.fileId = this.data.mediaId;
            this.mimeType = this.data.mimeType;
            this.previewElement.appendChild(this.#generateFilePreview(this.src, this.mimeType));
        }
        this.#listenToEvents();
        this.#addListeners();
        return this.element;
    }

    #listenToEvents() {
        window.mediaLibrary.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, (data) => {
            if(data.initiator === this.previewElement) {
                if(data?.mediaData[0]) {
                    const filename = data.mediaData[0]?.filename?.value ?? data.mediaData[0]?.filename ?? '';
                    const mimeType = data.mediaData[0]?.mimeType;
                    const existing = this.previewElement.querySelector(`.${contentEditorSelectors.classes.insertedFilePreviewContainer}`);
                    if(existing) {
                        existing.remove();
                    }
                    this.previewElement.prepend(this.#generateFilePreview(filename, mimeType));
                    this.fileId = data?.mediaData[0].id
                    this.src = filename;
                    this.mimeType = mimeType;
                }
            }
        });
    }

    #generateFilePreview(filename, mimeType) {
        const container = document.createElement('div');
        container.classList.add(contentEditorSelectors.classes.insertedFilePreviewContainer);
        const icon = document.createElement('div');
        icon.innerHTML = this.getIconForMimeType(mimeType);
        container.appendChild(icon);
        const label = document.createElement('span');
        label.textContent = filename;
        container.appendChild(label);
        return container;
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

    getData() {
        return {mediaId: this.fileId ?? null, src: this.src ?? null, mimeType: this.mimeType ?? null};
    }


    getIconForMimeType(mimeType) {
        if (!mimeType) {
            return this.constructor.icon;
        }

        if (File.MIME_TYPE_ICONS[mimeType]) {
            return File.MIME_TYPE_ICONS[mimeType];
        }

        const typePrefix = Object.keys(File.MIME_TYPE_ICONS).find((key) => {
            return key.endsWith('/') && mimeType.startsWith(key);
        });

        if (typePrefix) {
            return File.MIME_TYPE_ICONS[typePrefix];
        }

        return this.constructor.icon;
    }


    destroy() {
        this.previewElement.removeEventListener('click', this.#openMediaLibrary);
        this.element.remove();
        super.destroy();
    }
}
