# positionPopup

Places a floating element next to an anchor and keeps it inside the viewport. One function, no
state, no dependencies — the shared placement logic behind every popup in the project (the entity
search popup, the block menu, and anything else that needs to hang off a caret or an element).

```js
import {positionPopup} from '/src/PositionPopup/positionPopup.js';

positionPopup(menu, button);                    // sits below the button
positionPopup(menu, caretRect, {gap: 8});       // …or below a caret
```

## Signature

```js
positionPopup(element, anchor, options?) -> 'above' | 'below'
```

| Parameter | Description |
| --- | --- |
| `element` | The popup to place. It sets `element.style.top` / `left`. |
| `anchor` | What to place it against — an **element**, or a **DOMRect** (e.g. a caret's `range.getBoundingClientRect()`). |
| `options.gap` | Space between the anchor and the popup. Default `4`. |
| `options.margin` | Minimum distance to the viewport edge. Default `4`. |
| `options.absolute` | `true` for a `position:absolute` popup — adds the scroll offset. Default `false` (for `position:fixed`). |

Returns `'above'` or `'below'`, so a caller can style the two differently (flip a pointer arrow,
reverse a slide-in animation).

## The element must be measurable

It reads `offsetWidth`/`offsetHeight` to decide where to go, and an element hidden with
`display:none` measures zero. Reveal it with `visibility:hidden` first, place it, then show —
which is exactly what `EntitySearch` does:

```js
#position(anchor) {
    // Reveal invisibly first so it can be measured, then place and show.
    this.#root.style.visibility = 'hidden';
    this.#root.classList.remove(entitySearchSelectors.classes.hidden);
    positionPopup(this.#root, anchor);
    this.#root.style.visibility = '';
}
```

## `fixed` vs `absolute`

This is the option people get wrong, so it's worth being explicit.

- **`position: fixed`** popup → omit `absolute`. Coordinates are viewport-relative, so the popup
  stays put while the page scrolls (usually you also close or reposition it on scroll).
- **`position: absolute`** popup → pass `absolute: true`. The scroll offset is added, so the popup
  scrolls *with* the page.

The block menu is the absolute case — it stays glued to its block as you scroll:

```js
// Anchored under the block, flipping above it when the block sits near the bottom of the
// viewport. Absolute, so it scrolls with the page rather than hanging in place.
#position() {
    positionPopup(this.container, this.block.getContainer(), {gap: 8, absolute: true});
}
```

## Anchoring to a caret

A text caret has no element, but a collapsed `Range` has a rect — which is how the `//` command
menu follows the typing position:

```js
const range = window.getSelection().getRangeAt(0);
positionPopup(popup, range.getBoundingClientRect());
```

## Placement rules

**Below by default; flips above only when above is genuinely better.** The test is *not* the
naive "doesn't fit below" — that makes menus jump upward near the fold even when there's less
room up there. It flips only when the popup doesn't fit below **and** there's more space above.

**Horizontally it slides, never flips.** A popup that jumps to the other side of the caret is
disorienting, so when it would overflow the right edge it simply shifts left until it fits.

**Clamping happens last**, so a popup taller than the viewport is still fully on screen (pinned
to the top margin) instead of hanging off the top edge.

```js
const placement = positionPopup(menu, button);
menu.classList.toggle('popupAbove', placement === 'above');   // flip the arrow
```

## Repositioning

The function is a one-shot placement — it doesn't watch anything. Anchors move when the page
scrolls or resizes, so re-call it (or hide the popup) on those events:

```js
const reposition = () => { if (isOpen) positionPopup(menu, button); };
window.addEventListener('scroll', reposition, true);   // capture: catches scrolling containers
window.addEventListener('resize', reposition);
```

For a `position:fixed` popup anchored to a caret, hiding on scroll is usually better than
following it — the caret can scroll out of view entirely.
