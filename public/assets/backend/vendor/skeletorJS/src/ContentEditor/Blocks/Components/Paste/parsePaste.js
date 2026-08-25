import {sanitizeInline} from "./sanitizeInline.js";

const HEADING_OR_PARAGRAPH = new Set(['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
const LIST_TAGS = new Set(['ul', 'ol']);
const CONTAINER_TAGS = new Set(['div', 'section', 'article', 'main', 'body', 'blockquote']);

export function parsePaste(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const context = {pieces: [], inlineHtml: '', pendingList: null};
    processNodes(template.content.childNodes, context);
    flushWordList(context);
    flushInline(context);
    return context.pieces.filter((piece) => piece.html);
}

export function parsePlainText(text) {
    return text
        .split(/\r\n|\r|\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .map((line) => ({tag: 'p', html: escapeHtml(line)}));
}

function processNodes(nodes, context) {
    nodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            // Whitespace between Word list-item paragraphs shouldn't break the list.
            if (node.textContent.trim() !== '') {
                flushWordList(context);
            }
            context.inlineHtml += node.textContent;
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }
        const tag = node.tagName.toLowerCase();
        // Word emits list items as styled <p mso-list> paragraphs, not <ul>/<ol>/<li>.
        // Collect consecutive ones and rebuild a real list rather than paragraphs.
        if (tag === 'p' && isWordListItem(node)) {
            flushInline(context);
            addWordListItem(node, context);
            return;
        }
        flushWordList(context);
        if (LIST_TAGS.has(tag)) {
            flushInline(context);
            context.pieces.push(listPiece(node, tag));
            return;
        }
        if (HEADING_OR_PARAGRAPH.has(tag)) {
            flushInline(context);
            context.pieces.push({tag, html: sanitizeInline(node.innerHTML).trim()});
            return;
        }
        if (CONTAINER_TAGS.has(tag) && hasBlockChild(node)) {
            flushInline(context);
            processNodes(node.childNodes, context);
            return;
        }
        context.inlineHtml += node.outerHTML;
    });
}

function flushInline(context) {
    const clean = sanitizeInline(context.inlineHtml).trim();
    if (clean) {
        context.pieces.push({tag: 'p', html: clean});
    }
    context.inlineHtml = '';
}

function listPiece(node, tag) {
    return {tag, html: listItemsHtml(node)};
}

// Recurses so a nested <ul>/<ol> inside an <li> survives. sanitizeInline drops block tags,
// so it is applied only to each item's own inline content; the nested list is rebuilt around
// it rather than passed through the sanitizer.
function listItemsHtml(listNode) {
    return Array.from(listNode.children)
        .filter((child) => child.tagName.toLowerCase() === 'li')
        .map((li) => `<li>${listItemContent(li)}</li>`)
        .join('');
}

function listItemContent(li) {
    let inline = '';
    let nested = '';
    Array.from(li.childNodes).forEach((child) => {
        if (child.nodeType === Node.ELEMENT_NODE && LIST_TAGS.has(child.tagName.toLowerCase())) {
            const childTag = child.tagName.toLowerCase();
            nested += `<${childTag}>${listItemsHtml(child)}</${childTag}>`;
            return;
        }
        if (child.nodeType === Node.ELEMENT_NODE) {
            inline += child.outerHTML;
            return;
        }
        if (child.nodeType === Node.TEXT_NODE) {
            inline += child.textContent;
        }
    });
    return sanitizeInline(inline).trim() + nested;
}

// A Word list item is a paragraph flagged with an mso-list style or a MsoListParagraph class.
function isWordListItem(node) {
    const style = node.getAttribute('style') || '';
    const className = node.getAttribute('class') || '';
    return /mso-list\s*:/i.test(style) || /MsoListParagraph/i.test(className);
}

function addWordListItem(node, context) {
    const tag = wordListOrdered(node) ? 'ol' : 'ul';
    const html = wordListItemHtml(node);
    // A change of list type (bullets -> numbers) starts a fresh list.
    if (!context.pendingList || context.pendingList.tag !== tag) {
        flushWordList(context);
        context.pendingList = {tag, items: []};
    }
    if (html) {
        context.pendingList.items.push(html);
    }
}

function flushWordList(context) {
    if (context.pendingList && context.pendingList.items.length) {
        const items = context.pendingList.items.map((item) => `<li>${item}</li>`).join('');
        context.pieces.push({tag: context.pendingList.tag, html: items});
    }
    context.pendingList = null;
}

// Word puts the visible bullet/number in a span it flags with mso-list; a digit there
// means an ordered list, anything else (a bullet glyph) is unordered.
function wordListOrdered(node) {
    const marker = node.querySelector('span[style*="mso-list"]');
    const text = marker ? marker.textContent : node.textContent.trim().slice(0, 6);
    return /\d/.test(text);
}

function wordListItemHtml(node) {
    const clone = node.cloneNode(true);
    clone.querySelectorAll('span[style*="mso-list"]').forEach((span) => span.remove());
    let html = sanitizeInline(clone.innerHTML).trim();
    // Strip any leftover leading marker (bullet glyph or number) and its trailing spacing.
    html = html.replace(/^(?:&nbsp;|\s)*(?:\d+[.)]|[a-z][.)]|[•·▪◦‣o+\-•·▪●◦])(?:&nbsp;|\s)*/i, '');
    return html.trim();
}

function hasBlockChild(node) {
    return Array.from(node.children).some((child) => {
        const tag = child.tagName.toLowerCase();
        return HEADING_OR_PARAGRAPH.has(tag) || LIST_TAGS.has(tag) || CONTAINER_TAGS.has(tag);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
