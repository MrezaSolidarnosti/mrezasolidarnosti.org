const ALLOWED_TAGS = Object.freeze({
    strong: [],
    em: [],
    u: [],
    sup: [],
    sub: [],
    s: [],
    mark: [],
    a: ['href', 'target', 'rel'],
    br: []
});

const RENAME = Object.freeze({
    b: 'strong',
    i: 'em'
});

const DROP_ENTIRELY = new Set(['script', 'style', 'meta', 'link', 'title', 'head', 'noscript']);

export function sanitizeInline(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const wrapper = document.createElement('div');
    wrapper.appendChild(sanitizeNodes(template.content));
    return wrapper.innerHTML;
}

function sanitizeNodes(parent) {
    const fragment = document.createDocumentFragment();
    parent.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            fragment.appendChild(document.createTextNode(node.textContent));
            return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }
        let tag = node.tagName.toLowerCase();
        if (DROP_ENTIRELY.has(tag)) {
            return;
        }
        if (RENAME[tag]) {
            tag = RENAME[tag];
        }
        const childrenFragment = sanitizeNodes(node);
        if (!ALLOWED_TAGS[tag] || (tag === 'a' && !hasSafeHref(node))) {
            fragment.appendChild(childrenFragment);
            return;
        }
        const element = document.createElement(tag);
        ALLOWED_TAGS[tag].forEach((attribute) => {
            if (node.hasAttribute(attribute)) {
                element.setAttribute(attribute, node.getAttribute(attribute));
            }
        });
        element.appendChild(childrenFragment);
        fragment.appendChild(element);
    });
    return fragment;
}

function hasSafeHref(node) {
    if (!node.hasAttribute('href')) {
        return false;
    }
    const href = node.getAttribute('href').trim().toLowerCase();
    return !href.startsWith('javascript:') && !href.startsWith('data:') && !href.startsWith('vbscript:');
}
