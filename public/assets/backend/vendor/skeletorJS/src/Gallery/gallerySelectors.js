export const gallerySelectors = Object.freeze({
    classes: {
        gallery: 'gallery',
        item: 'galleryItem',
        image: 'galleryImage',
        caption: 'galleryCaption',
        extras: 'galleryExtras',
        itemHidden: 'galleryItemHidden',
        placeholder: 'galleryPlaceholder',
        lastVisible: 'galleryItemLast',

        // One class per layout, so the whole arrangement is CSS-driven.
        layoutGrid: 'galleryGrid',
        layoutJustified: 'galleryJustified',
        layoutMasonry: 'galleryMasonry',
        column: 'galleryColumn',      // masonry only: a JS-built column wrapper

        // Lightbox
        lightbox: 'lightbox',
        lightboxOpen: 'lightboxOpen',
        lightboxTopBar: 'lightboxTopBar',
        lightboxCount: 'lightboxCount',
        lightboxActions: 'lightboxActions',
        lightboxAction: 'lightboxAction',
        lightboxShare: 'lightboxShare',
        lightboxShareMenu: 'lightboxShareMenu',
        lightboxStage: 'lightboxStage',
        lightboxSlide: 'lightboxSlide',
        lightboxSlideActive: 'lightboxSlideActive',
        lightboxLoader: 'lightboxLoader',
        lightboxFooter: 'lightboxFooter',
        lightboxPrevious: 'lightboxPrevious',
        lightboxNext: 'lightboxNext',
        lightboxZoomed: 'lightboxZoomed',
        disabled: 'galleryDisabled',
        active: 'active',
        hidden: 'galleryHidden',
        scrollLocked: 'galleryScrollLocked',
    },
    attributes: {
        id: 'data-id',
        big: 'data-big',
        caption: 'data-caption',
        alt: 'data-alt',
        width: 'data-width',
        height: 'data-height',
        index: 'data-index',
    },
});
