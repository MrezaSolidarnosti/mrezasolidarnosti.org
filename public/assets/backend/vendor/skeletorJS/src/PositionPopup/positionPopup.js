/**
 * Place a floating element next to an anchor, kept inside the viewport.
 *
 * Preferred spot is directly below the anchor. It flips above only when the element doesn't
 * fit below *and* there is genuinely more room above — a plain "doesn't fit" test makes menus
 * jump upward near the fold even when that's the worse side. Horizontally it slides back into
 * view rather than flipping, since a popup that swaps sides under the caret is disorienting.
 *
 * The element must already be in the DOM and measurable (laid out, not `display:none`) —
 * callers that keep it hidden should reveal it with `visibility:hidden` first.
 *
 * @param {HTMLElement} element  the popup to place.
 * @param {DOMRect|Element} anchor  what to place it against — a caret rect or an element.
 * @param {{gap?: number, margin?: number, absolute?: boolean}} [options]
 *        `gap` — space between anchor and popup. `margin` — minimum gap to the viewport edge.
 *        `absolute` — set for a `position:absolute` popup, which needs page (scroll-offset)
 *        coordinates instead of the viewport ones a `position:fixed` popup takes.
 * @returns {'above'|'below'} where it ended up, for callers that style the two differently.
 */
export function positionPopup(element, anchor, {gap = 4, margin = 4, absolute = false} = {}) {
    const rect = anchor instanceof Element ? anchor.getBoundingClientRect() : anchor;
    const width = element.offsetWidth;
    const height = element.offsetHeight;

    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    const flip = height > spaceBelow && spaceAbove > spaceBelow;

    let top = flip ? rect.top - height - gap : rect.bottom + gap;
    let left = rect.left;
    // Clamp last, so a popup too tall for either side is still fully on screen.
    top = clamp(top, margin, window.innerHeight - height - margin);
    left = clamp(left, margin, window.innerWidth - width - margin);

    if (absolute) {
        top += window.scrollY;
        left += window.scrollX;
    }
    element.style.top = `${top}px`;
    element.style.left = `${left}px`;
    return flip ? 'above' : 'below';
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), Math.max(min, max));
}
