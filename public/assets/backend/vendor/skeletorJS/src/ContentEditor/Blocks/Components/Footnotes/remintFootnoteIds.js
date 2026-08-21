import {contentEditorSelectors} from "../../../contentEditorSelectors.js";

/**
 * Gives every footnote marker in a copied block payload a fresh id.
 *
 * A marker's id is identity in exactly the way a block's id is: it names the note that belongs
 * to it. Copying a block verbatim therefore produces two markers claiming one note, and the
 * footnotes controller resolves that badly — the first marker takes the existing note, the
 * second finds it already claimed and builds an empty one under the *same* id. The result is a
 * stray blank note (showing its placeholder) plus numbering that writes into the wrong entry,
 * because the backlink is looked up with querySelector and only ever finds the first match.
 *
 * So duplicate, clipboard copy and paste all remint, the same way they strip the block id.
 *
 * The ids are rewritten in the serialized data rather than the DOM because that is what every
 * copy path already carries: a text block's `html`, but equally an accordion's or a timeline's
 * item bodies. Walking every string in the payload covers those without this having to know
 * which blocks keep markup where.
 *
 * `mapping` is shared across one payload so a marker id occurring in more than one place maps
 * to a single new id, and is *not* shared between payloads — two separately pasted blocks are
 * two separate copies and each needs its own note.
 *
 * @param {object|Array} data  a serialized block (mutated in place, and returned).
 * @param {() => string} generateId  mints one new footnote id.
 * @param {Map<string,string>} [mapping]  old id → new id, for this payload.
 */
export function remintFootnoteIds(data, generateId, mapping = new Map()) {
    if (!data || typeof data !== 'object') {
        return data;
    }
    const attribute = contentEditorSelectors.attributes.footnoteId;
    // Both quote styles: the payload's html comes from innerHTML, but content that arrived by
    // paste or from a backend may not have been normalised.
    const pattern = new RegExp(`${attribute}=("([^"]*)"|'([^']*)')`, 'g');

    Object.keys(data).forEach((key) => {
        const value = data[key];
        if (typeof value === 'string') {
            data[key] = value.replace(pattern, (match, quoted, double, single) => {
                const id = double !== undefined ? double : single;
                if (!mapping.has(id)) {
                    mapping.set(id, generateId());
                }
                return `${attribute}="${mapping.get(id)}"`;
            });
            return;
        }
        if (value && typeof value === 'object') {
            remintFootnoteIds(value, generateId, mapping);   // arrays included — keys are indices
        }
    });
    return data;
}
