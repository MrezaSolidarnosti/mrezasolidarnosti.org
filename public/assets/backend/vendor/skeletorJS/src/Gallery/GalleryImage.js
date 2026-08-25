import {gallerySelectors} from "./gallerySelectors.js";

/**
 * One image in a gallery — a plain descriptor, deliberately free of DOM behaviour.
 *
 * It can be built two ways, which is what lets the gallery work from server-rendered markup
 * (good for SEO and no-JS) *or* from a JS array:
 *
 *   GalleryImage.fromElement(itemElement, index)   // reads data-* attributes
 *   GalleryImage.fromData({src, big, …}, index)
 *
 * `big` falls back to the thumbnail, so a gallery with only one size per image still works.
 */
export default class GalleryImage {

    id;
    src;        // thumbnail
    big;        // full-size, shown in the lightbox
    caption;
    alt;
    width;      // intrinsic size, when known — the justified layout needs the aspect ratio
    height;

    constructor({id = null, src = '', big = null, caption = '', alt = '', width = null, height = null, index = 0}) {
        this.id = id !== null ? String(id) : String(index);
        this.src = src;
        this.big = big || src;
        this.caption = caption || '';
        this.alt = alt || caption || '';
        this.width = Number(width) || null;
        this.height = Number(height) || null;
        this.index = index;
    }

    /**
     * From existing markup. The item carries the metadata as data-* attributes; an <a href>
     * item doubles as the no-JS full-size link, so it's used as the `big` fallback.
     */
    static fromElement(element, index) {
        const attributes = gallerySelectors.attributes;
        const image = element.querySelector('img');
        const href = element.tagName === 'A' ? element.getAttribute('href') : null;
        return new GalleryImage({
            index,
            id: element.getAttribute(attributes.id),
            src: image ? image.getAttribute('src') : '',
            big: element.getAttribute(attributes.big) || href,
            caption: element.getAttribute(attributes.caption),
            alt: element.getAttribute(attributes.alt) || (image ? image.getAttribute('alt') : ''),
            width: element.getAttribute(attributes.width) || (image ? image.getAttribute('width') : null),
            height: element.getAttribute(attributes.height) || (image ? image.getAttribute('height') : null),
        });
    }

    static fromData(data, index) {
        return new GalleryImage({...data, index});
    }

    hasCaption() {
        return this.caption.trim() !== '';
    }

    // Width ÷ height, or null while unknown. The justified layout falls back to a square-ish
    // guess until the real image reports its natural size.
    aspectRatio() {
        return (this.width && this.height) ? (this.width / this.height) : null;
    }
}
