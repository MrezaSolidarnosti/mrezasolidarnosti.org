# ImageCompare

A before/after image comparison with a draggable divider — horizontal or vertical, keyboard
accessible, no dependencies.

```js
import ImageCompare from './ImageCompare/ImageCompare.js';

new ImageCompare({containerId: 'compare'}).init();
```

```html
<div id="compare">
    <img class="imageCompareBefore" src="/before.jpg" alt="The bridge before the flood">
    <img class="imageCompareAfter"  src="/after.jpg"  alt="The bridge after the flood">
</div>
```

The images are **your markup**, so they're in the HTML for crawlers and both still render if JS
never runs. Or build it from data:

```js
new ImageCompare({
    containerId: 'compare',
    images: {
        before: {src: '/before.jpg', alt: 'Before the flood'},
        after:  {src: '/after.jpg',  alt: 'After the flood'},
    },
    options: {labels: {before: 'March 2019', after: 'Today'}},
}).init();
```

## How the reveal works

The "after" image sits in normal flow and decides the frame's size; the "before" image is layered
over it and clipped with `clip-path`, driven by a single CSS custom property.

Dragging therefore updates **one number** — no resizing, no re-layout, nothing to recompute when
the container changes width. `clip-path` only repaints, so the drag stays smooth, and because both
images keep their natural size underneath, the seam stays sharp at any width.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `position` | `50` | Where the divider starts, 0–100 (%). |
| `orientation` | `'horizontal'` | `'vertical'` wipes top-to-bottom instead. |
| `labels` | `null` | `{before, after}` for corner captions. Omit for none. |
| `hover` | `false` | Follow the pointer without needing a drag. |
| `step` | `2` | Keyboard arrow step, in %. |
| `largeStep` | `10` | PageUp / PageDown step. |

## API

| Method | Description |
| --- | --- |
| `init()` | Build and wire up. Returns `this`. |
| `setPosition(value, {silent})` | Move the divider (0–100, clamped). |
| `getPosition()` | Current position. |
| `destroy()` | Remove every listener and leave the container as it was found. |

## Events

| Event | Payload |
| --- | --- |
| `imageCompareRendered` | `{compare}` |
| `positionChanged` | `{position}` |

```js
compare.eventEmitter.on('positionChanged', ({position}) => console.log(position));
```

## Input

| Input | Behaviour |
| --- | --- |
| Drag | Move the divider. One pointer path covers mouse, touch and pen. |
| Click / tap anywhere | Jump the divider there — no need to grab the handle exactly. |
| `←` `→` (or `↑` `↓`) | Move by `step`. Arrows always move the divider **the way they point** — in vertical mode `↑` moves it up, even though that *decreases* the position value. |
| `PageUp` / `PageDown` | Move by `largeStep`. |
| `Home` / `End` | Jump to either extreme. |

The frame sets `touch-action: none`, so dragging across the image doesn't pan the page on a phone.

## Accessibility

The handle is focusable and exposed as `role="slider"` with `aria-valuemin`/`max`/`now`, so screen
readers announce it as a slider and report the position as it moves. Give it a meaningful pair of
`alt` texts — those, not the labels, are what a non-sighted reader gets.

## Notes

- The two images should be the same dimensions. If they aren't, the clipped one uses
  `object-fit: cover` so they stay aligned rather than one stretching.
- Safe inside a flex or grid parent (`min-width: 0`, `max-width: 100%`), unlike a raw image row.
- Sizing is entirely from the "after" image, so an `aspect-ratio` or `max-height` on
  `.imageCompareAfter` controls the whole component.
