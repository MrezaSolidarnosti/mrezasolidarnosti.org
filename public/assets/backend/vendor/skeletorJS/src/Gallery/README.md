# Gallery

A thumbnail gallery with a full-screen lightbox — three layouts, hover captions, a "+N" overflow
tile, swipe/pinch on touch, and deep-linkable images. No dependencies.

```js
import Gallery from './Gallery/Gallery.js';

new Gallery({
    containerId: 'myGallery',
    options: {layout: 'justified', visibleCount: 5},
}).init();
```

## Two ways to give it images

**From markup** — the page already rendered the tiles; the gallery enhances them. The images are
in the HTML for crawlers, and each tile is a real link to the full-size file before JS runs.

```html
<div id="myGallery">
    <a class="galleryItem" href="/big/1.jpg" data-id="1" data-caption="Sunset over the bay">
        <img src="/thumb/1.jpg" alt="Sunset over the bay">
    </a>
    <a class="galleryItem" href="/big/2.jpg" data-id="2" data-caption="">
        <img src="/thumb/2.jpg" alt="">
    </a>
</div>
```

```js
new Gallery({containerId: 'myGallery'}).init();
```

| Attribute | Description |
| --- | --- |
| `data-id` | Used for deep links (`#gallery-<id>`). Falls back to the index. |
| `data-big` | Full-size source. Falls back to the item's `href`, then the thumbnail. |
| `data-caption` | Shown on hover and under the lightbox image. Omit or leave empty for none. |
| `data-alt` | Alt text. Falls back to the `<img alt>`, then the caption. |
| `data-width` / `data-height` | Intrinsic size. Used by `justified` and `masonry` — supply it and they lay out on the first paint instead of re-flowing once the images load. |

**From data** — pass an array and it renders the tiles itself:

```js
new Gallery({
    containerId: 'myGallery',
    images: [
        {id: 1, src: '/thumb/1.jpg', big: '/big/1.jpg', caption: 'Sunset', width: 1600, height: 900},
        {id: 2, src: '/thumb/2.jpg', big: '/big/2.jpg'},
    ],
}).init();
```

## Layouts

```js
new Gallery({containerId: 'g', options: {layout: 'masonry', columns: 4}}).init();
```

| Layout | Description |
| --- | --- |
| `grid` | Uniform tiles, cropped to `tileRatio`, re-flowing at `tileWidth`. The default. |
| `justified` | Rows scaled to fill the width with every aspect ratio preserved (Flickr-style). Measures. |
| `masonry` | Vertical columns, nothing cropped, variable heights. Stays multi-column on a phone — see below. Each tile goes to the shortest column, so the columns even out and the order still reads left-to-right (CSS `column-count` fills top-to-bottom instead, putting image 2 *under* image 1). Measures, like `justified`. |

`setLayout('masonry')` switches at runtime.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `layout` | `'grid'` | `grid` \| `justified` \| `masonry`. |
| `visibleCount` | `null` | Show only the first N tiles and put a **"+N"** overlay on the last one. The rest still exist — the lightbox pages through everything. `null` shows all. |
| `gap` | `10` | Gap between tiles, px. |
| `tileWidth` | `220` | `grid`: minimum tile width before the row re-flows. |
| `tileRatio` | `1.5` | `grid`: tile aspect ratio. |
| `rowHeight` | `220` | `justified`: the height rows aim for. |
| `columns` | `3` | `masonry`: the **most** columns to use. |
| `minColumnWidth` | `140` | `masonry`: how narrow a column may get before one is dropped. |
| `captions` | `true` | Show the caption overlay on hover when an image has one. |
| `lightbox` | `true` | `false` to disable, or an options object (below). |

### Lightbox options

```js
new Gallery({
    containerId: 'g',
    options: {
        lightbox: {
            hashPrefix: 'photo-',
            share: [{key: 'facebook', label: 'Facebook', url: 'https://www.facebook.com/sharer/sharer.php?u={url}'}],
            onNavigate: ({image, index, url}) => gtag('event', 'page_view', {page_location: url}),
        },
    },
}).init();
```

| Option | Default | Description |
| --- | --- | --- |
| `share` | built-in list | An array of `{key, label, url}` where `{url}` is replaced with the encoded page URL. `false` removes the button. |
| `nativeShare` | `true` | Use the OS share sheet wherever the browser offers one (`navigator.share`) — desktop included. Set `false` to always use the `share` menu instead. Browsers without the API fall back to the menu regardless. |
| `fullscreen` | `true` | `false` removes the fullscreen button. |
| `hash` | `true` | `false` stops the URL updating. |
| `hashPrefix` | `'gallery-'` | The hash becomes `#<prefix><image id>`. |
| `onNavigate` | — | `({image, index, url}) => {}`, called whenever the visible image changes. This is where analytics goes — nothing is hardwired. Not called for the initial deep-link open. |

## Deep links

With `hash` on, viewing an image rewrites the URL to `#gallery-<id>` (via `replaceState`, so Back
leaves the page instead of stepping through every image you looked at). On load, a matching hash
reopens the lightbox at that image.

### Supporting old links

Two things to line up when replacing an existing gallery.

**1. Keep the hash format you already publish**, so links people have shared keep working:

```js
options: {lightbox: {hashPrefix: 'gfallery-'}}   // -> #gfallery-123
```

**2. Handle any older format yourself.** The component only recognises its own
`#<prefix><id>`; anything else you parse and hand to `openById`:

```js
const gallery = new Gallery({
    containerId: 'gallery',
    options: {lightbox: {hashPrefix: 'gfallery-'}},
}).init();

// Legacy scheme: #/ML/123
const legacy = location.hash.match(/^#\/ML\/(\d+)/);
if (legacy) {
    gallery.openById(legacy[1], {silent: true});
}
```

`{silent: true}` matches how the built-in deep link behaves — the image opens without firing
`onNavigate`, so an arrival isn't counted as a navigation. As soon as it opens, the URL is
rewritten to the current format, so the old link quietly upgrades itself.

## Touch & keyboard

| Input | Action |
| --- | --- |
| Swipe left / right | Previous / next image (ignored while zoomed — there a drag pans). |
| Pinch | Zoom, up to 4×. |
| Double-tap / double-click | Toggle zoom. |
| Drag | Pan a zoomed image. |
| `←` / `→` | Previous / next. |
| `Esc` | Close. |

## API

| Method | Description |
| --- | --- |
| `init()` | Build and wire up. Returns `this`. |
| `open(index, options?)` | Open the lightbox at an index. |
| `openById(id, options?)` | Open by the image's `id`. Pass `{silent: true}` for an on-load deep link so it doesn't fire `onNavigate`. |
| `addImages(images)` | Append one or more images after `init()` — a "load more", a finished upload, a widened filter. |
| `setImages(images)` | Replace the whole set — a filter change or a different album. |
| `removeImage(idOrIndex)` | Remove by `id`, or by position if given a number. |
| `showPlaceholders(count)` / `hidePlaceholders()` | Skeleton tiles while a fetch is in flight. |
| `showAll()` | Reveal the tiles `visibleCount` hid, and drop the "+N" overlay. |
| `setLayout(layout)` | Switch layout at runtime. |
| `getImages()` / `getLightbox()` | The `GalleryImage[]` and the `Lightbox` instance. |
| `destroy()` | Remove every listener and the lightbox DOM. |

## Events

`gallery.eventEmitter.on(name, callback)` — pass your own emitter in as `eventEmitter` to share one.

| Event | Payload |
| --- | --- |
| `galleryRendered` | `{gallery, count}` |
| `imageClicked` | `{index, image}` |
| `lightboxOpened` / `lightboxClosed` | `{index}` |
| `lightboxNavigated` | `{index, image}` |
| `imagesChanged` | `{images, count}` — after `addImages` / `setImages` / `removeImage` |

## Masonry on small screens

The column count follows the **container width**, not screen breakpoints: keep adding columns
while they stay at least `minColumnWidth` wide, up to `columns`. It's the same idea the grid
layout expresses with `minmax(tileWidth, 1fr)`.

The practical result is that masonry **stays masonry on a phone** — a 375px screen still fits two
columns at the default. Dropping to one would turn it into a plain vertical stack, which is the
one arrangement masonry exists to avoid.

| Container | Columns (`columns: 3`, `minColumnWidth: 140`) |
| --- | --- |
| 320px (small phone) | 2 |
| 375px (phone) | 2 |
| 768px (tablet) | 3 |
| 250px (narrow sidebar) | 1 — genuinely too tight for two |

Raise `minColumnWidth` for bigger tiles, lower it for denser ones. The `gap` is counted too, so a
wide gap can cost a column.

## Changing images after init

| Method | Use |
| --- | --- |
| `addImages(images)` | Append. Takes an array **or a single object**. |
| `setImages(images)` | Replace the whole set. |
| `removeImage(idOrIndex)` | Remove by `id`, or by position if given a number. |

Each call re-renders the tiles, re-runs the layout (re-justifying rows, re-packing masonry
columns), recalculates the `visibleCount` "+N" overlay, and rebuilds the lightbox — including
while it's open. Then `imagesChanged` fires.

### Placeholders while fetching

```js
gallery.showPlaceholders(6);        // skeleton tiles hold the space
const more = await fetch('/api/photos?page=2').then((r) => r.json());
gallery.addImages(more);            // clears them and renders the real thing
```

| Method | Description |
| --- | --- |
| `showPlaceholders(count)` | Append `count` skeleton tiles. |
| `hidePlaceholders()` | Remove them — only needed if the fetch fails. |
| `isShowingPlaceholders()` | Whether any are up. |

`addImages` and `setImages` clear them automatically, so the happy path needs no teardown. Call
`hidePlaceholders()` in a `catch` if the request fails.

Placeholders are **never images**: they don't appear in `getImages()`, don't reach the lightbox,
and don't count towards the `visibleCount` "+N" overlay. They're laid out like tiles and nothing
more — clicking one does nothing.

They take the shape of `tileRatio`, so the skeleton is the size the real tile will be. And if
`visibleCount` has already been reached, `showPlaceholders` returns without doing anything —
the tiles would be hidden the moment they appeared.

### A complete "load more"

```js
const gallery = new Gallery({containerId: 'photos', images: firstPage}).init();

const button = document.getElementById('loadMore');
const counter = document.getElementById('counter');

button.addEventListener('click', async () => {
    button.disabled = true;
    const more = await fetch(`/api/photos?page=${++page}`).then((r) => r.json());
    gallery.addImages(more);
});

// One place to reflect the new state, however the images changed.
gallery.eventEmitter.on('imagesChanged', ({count}) => {
    counter.textContent = `${count} of ${total}`;
    button.disabled = count >= total;
});
```

### Notes

- **Supply `width`/`height` on added images** if you can. The `justified` and `masonry` layouts
  need aspect ratios, so without them the new tiles lay out with a guess and re-flow once they
  load.
- **Removing from the middle renumbers everything after it.** A tile's `data-index` is how the
  layouts and the lightbox address an image, so without renumbering a deep link or an arrow key
  would land on the wrong photo. This is handled for you — worth knowing if you keep your own
  indices alongside.
- **Markup-built galleries can still be added to** — the new tiles are rendered from data and sit
  alongside the server-rendered ones. But `setImages` removes the original tiles, so after that
  the gallery is entirely JS-owned; fine for a filter UI, worth knowing if you were relying on
  those tiles being in the HTML for crawlers.

## Notes

- The lightbox builds its slides **once**, at init, and `open()` only changes the index. Building
  per-open duplicates the DOM, and a deep-link open racing a click can leave two slides visible
  at the same time.
- Slides load lazily — only the current image and its immediate neighbours get a `src`, so a
  200-image gallery doesn't fetch 200 files to show one.
- Styles live in `css/_gallery.scss`; every dimension is a CSS custom property set from the
  options, so layout stays in the stylesheet.
