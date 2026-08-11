/**
 * Which block boundary the cursor is nearest, for the between-blocks "+" inserter.
 *
 * Given the on-screen rects of a container's blocks (in DOM order) and the cursor's Y, returns
 * the closest insertion boundary — but only if it's within `band` pixels, so the "+" appears as
 * you approach a gap rather than everywhere. `null` means "not near a gap; hide".
 *
 * Boundaries: above the first block (insert `before` it), between each pair (insert `after` the
 * upper one), and below the last (insert `after` it). Each boundary anchors on the block *above*
 * the gap, so `refIndex` + `position` map straight onto renderBlock(). The two edge boundaries
 * can be switched off — the inserter drops both, so the "+" only ever sits *between* two blocks.
 *
 * @param {Array<{top:number, bottom:number}>} rects  block rects, top-to-bottom (DOM order).
 * @param {number} cursorY
 * @param {number} band  max distance (px) from a boundary to still count.
 * @param {{includeBeforeFirst?: boolean, includeAfterLast?: boolean}} [options]
 *        Whether to include the above-first / below-last edges (both default true).
 * @returns {{refIndex:number, position:'before'|'after', y:number}|null}
 */
export function resolveGap(rects, cursorY, band, {includeBeforeFirst = true, includeAfterLast = true} = {}) {
    if (!rects.length) {
        return null;
    }
    const boundaries = [];
    if (includeBeforeFirst) {
        boundaries.push({y: rects[0].top, refIndex: 0, position: 'before'});
    }
    for (let i = 1; i < rects.length; i++) {
        boundaries.push({y: (rects[i - 1].bottom + rects[i].top) / 2, refIndex: i - 1, position: 'after'});
    }
    if (includeAfterLast) {
        boundaries.push({y: rects[rects.length - 1].bottom, refIndex: rects.length - 1, position: 'after'});
    }
    if (!boundaries.length) {
        return null;   // e.g. a single block with both edges excluded — nothing "between".
    }

    let best = null;
    boundaries.forEach((boundary) => {
        const distance = Math.abs(boundary.y - cursorY);
        if (!best || distance < best.distance) {
            best = {...boundary, distance};
        }
    });
    return best.distance <= band
        ? {refIndex: best.refIndex, position: best.position, y: best.y}
        : null;
}
