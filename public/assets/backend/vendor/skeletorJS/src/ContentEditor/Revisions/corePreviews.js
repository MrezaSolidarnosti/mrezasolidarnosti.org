import Revisions from "./Revisions.js";

const escape = Revisions.escape;

/**
 * Revision-diff previews for the core blocks that carry no `html`.
 *
 * A block whose data is `html` needs nothing: the default preview shows it. These are the
 * rest — the ones whose content lives in `src`, `items[]`, `rows[]` and so on, where the
 * default finds nothing and the card would be a bare name.
 *
 * They live outside Revisions on purpose. The module knows nothing about block types, so
 * anything type-specific is the consumer's call — which is what makes an app/* block a
 * first-class citizen here rather than a special case.
 *
 *   import {registerCorePreviews} from '.../Revisions/corePreviews.js';
 *   registerCorePreviews();                          // all of them
 *   registerCorePreviews(['core/image']);            // only some
 *   Revisions.registerPreview('core/image', mine);   // or override one afterwards
 *
 * Every value interpolated from block data is escaped: a preview's return is inserted as
 * HTML, and a filename, a chart label or a table cell is user content. The exception is
 * anything the editor itself produced as markup (`item.content`), which is passed through
 * exactly as the default preview passes through `block.html`.
 */
export const corePreviews = Object.freeze({

    'core/image': (block) => (block.src
        ? `<img src="${escape(block.src)}" alt="">`
        : ''),

    // Thumbnails: a gallery of full-width images would bury the rest of the diff.
    'core/gallery': (block) => (block.images || [])
        .filter((image) => image.src)
        .map((image) => `<img src="${escape(image.src)}" alt="" style="max-width:72px;margin:0 4px 4px 0">`)
        .join(''),

    'core/file': (block) => (block.src
        ? `<code>${escape(block.src)}</code>${block.mimeType ? ` (${escape(block.mimeType)})` : ''}`
        : ''),

    // The url only. Rendering the embed would load a third-party iframe into the editor.
    'core/embed': (block) => (block.src ? `<code>${escape(block.src)}</code>` : ''),

    // This block's payload is markup held as *text*. Escaping is not optional here — it is
    // the whole point: the reviewer needs to read the source, not run it.
    'core/html': (block) => (block.value
        ? `<pre><code>${escape(block.value)}</code></pre>`
        : ''),

    // Values only. chartType and labels change on their own and are reported as fields; the
    // numbers are what a reviewer is actually here to compare.
    'core/chart': (block) => (block.series || [])
        .map((series) => `<div><strong>${escape(series.name || '(unnamed)')}</strong> `
            + `${escape((series.values || []).join(', '))}</div>`)
        .join(''),

    // Values only — the styling settings (colours, search, filters) are reported as fields.
    'core/table': (block) => {
        const head = (block.headers || []).map((cell) => `<th>${escape(cell)}</th>`).join('');
        const body = (block.rows || [])
            .map((row) => `<tr>${(row || []).map((cell) => `<td>${escape(cell)}</td>`).join('')}</tr>`)
            .join('');
        if (!head && !body) {
            return '';
        }
        return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
    },

    // summary is data (escaped); content is editor markup (passed through).
    'core/accordion': (block) => (block.items || [])
        .map((item) => `<div><strong>${escape(item.summary ?? '')}</strong>${item.content ?? ''}</div>`)
        .join(''),

    // label is data (escaped); content is editor markup (passed through).
    'core/tabs': (block) => (block.items || [])
        .map((item) => `<div><strong>${escape(item.label ?? '')}</strong>${item.content ?? ''}</div>`)
        .join(''),

    'core/timeline': (block) => (block.items || [])
        .map((item) => `<div><strong>${escape(item.time ?? '')}</strong> ${item.content ?? ''}</div>`)
        .join(''),
});

/**
 * Register the previews above. Call before constructing the editor.
 *
 * @param {string[]|null} only Limit to these block types; omit for all of them.
 */
export function registerCorePreviews(only = null) {
    Object.entries(corePreviews).forEach(([type, renderer]) => {
        if (!only || only.includes(type)) {
            Revisions.registerPreview(type, renderer);
        }
    });
}
