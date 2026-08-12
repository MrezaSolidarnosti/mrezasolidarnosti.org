/**
 * Rewrite every text node under `root` through `fn`, leaving the markup untouched.
 *
 * The naive version — `root.textContent = fn(root.textContent)` — flattens the block: every
 * <strong>, <a>, <mark> and <sup> inside it is destroyed, because assigning textContent
 * replaces all children with a single text node. Walking the text nodes instead means the
 * elements around them survive.
 *
 * Only safe for per-character transforms (upper/lower case). Anything that depends on word or
 * sentence boundaries — title case, sentence case — can't be done node-by-node, because inline
 * markup splits words and sentences across nodes: in `Hello <strong>world</strong>` the second
 * node starts mid-sentence but looks like a fresh start on its own.
 *
 * @param {Node} root  the element to transform in place.
 * @param {(text: string) => string} fn  applied to each text node's content.
 */
export function transformText(root, fn) {
    if (!root) {
        return;
    }
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }
    const caret = saveCaret(root);
    // Collect first, mutate after: rewriting a node while the walker is still traversing
    // invalidates its position.
    nodes.forEach((node) => {
        node.textContent = fn(node.textContent);
    });
    restoreCaret(caret);
}

/**
 * Assigning `textContent` to a text node replaces all of its character data, and the DOM's
 * range-adjustment rules collapse any live range sitting inside that data to its start. The
 * caret would land at offset 0 of whichever node it was in — visibly wrong when the node
 * follows an inline element, since the caret ends up against the boundary and inherits that
 * element's formatting (type after a superscript and the new text comes out raised).
 *
 * The nodes themselves survive — only their data is rewritten — so the caret can be restored
 * by identity. Offsets are clamped in case `fn` changed a node's length.
 */
function saveCaret(root) {
    const selection = window.getSelection();
    if (!selection || !selection.rangeCount) {
        return null;
    }
    const {anchorNode, anchorOffset, focusNode, focusOffset} = selection;
    if (!anchorNode || !focusNode || !root.contains(anchorNode) || !root.contains(focusNode)) {
        return null;   // the caret is elsewhere — leave it alone
    }
    return {selection, anchorNode, anchorOffset, focusNode, focusOffset};
}

function restoreCaret(caret) {
    if (!caret) {
        return;
    }
    const {selection, anchorNode, anchorOffset, focusNode, focusOffset} = caret;
    if (!anchorNode.isConnected || !focusNode.isConnected) {
        return;
    }
    const range = document.createRange();
    range.setStart(anchorNode, clampOffset(anchorNode, anchorOffset));
    range.setEnd(focusNode, clampOffset(focusNode, focusOffset));
    selection.removeAllRanges();
    selection.addRange(range);
}

function clampOffset(node, offset) {
    const length = node.nodeType === Node.TEXT_NODE ? node.textContent.length : node.childNodes.length;
    return Math.min(offset, length);
}
