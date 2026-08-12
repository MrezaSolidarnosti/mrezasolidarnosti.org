/**
 * Pure diff engine — no DOM, no library knowledge, no dependencies.
 *
 * Everything here is a plain function over plain data, so it runs anywhere: the viewer, a
 * test, or a backend comparing two revisions. DiffViewer renders what these return; it adds
 * no diffing logic of its own.
 *
 * Text and items are the same algorithm at different granularities — one LCS, applied to
 * lines, to items, and to the words inside a changed pair.
 */

export const STATUS = Object.freeze({
    unchanged: 'unchanged',
    added: 'added',
    removed: 'removed',
    modified: 'modified',
    moved: 'moved',
});

export const OP = Object.freeze({
    keep: 'keep',
    add: 'add',
    remove: 'remove',
});

/* ------------------------------- Equality ------------------------------- */

// Key-order independent, unlike a JSON.stringify comparison.
export function deepEqual(a, b) {
    if (a === b) {
        return true;
    }
    if (a === null || b === null || typeof a !== 'object' || typeof b !== 'object') {
        return false;
    }
    if (Array.isArray(a) !== Array.isArray(b)) {
        return false;
    }
    const aKeys = Object.keys(a);
    const bKeys = Object.keys(b);
    if (aKeys.length !== bKeys.length) {
        return false;
    }
    return aKeys.every((key) => Object.prototype.hasOwnProperty.call(b, key) && deepEqual(a[key], b[key]));
}

/* --------------------------------- LCS ---------------------------------- */

function buildTable(a, b, isEqual) {
    const table = Array.from({length: a.length + 1}, () => new Array(b.length + 1).fill(0));
    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            table[i][j] = isEqual(a[i], b[j])
                ? table[i + 1][j + 1] + 1
                : Math.max(table[i + 1][j], table[i][j + 1]);
        }
    }
    return table;
}

const keepOp = (before, after, beforeIndex, afterIndex) => ({op: OP.keep, before, after, beforeIndex, afterIndex});
const addOp = (after, afterIndex) => ({op: OP.add, before: undefined, after, beforeIndex: null, afterIndex});
const removeOp = (before, beforeIndex) => ({op: OP.remove, before, after: undefined, beforeIndex, afterIndex: null});

/**
 * Longest common subsequence of two arrays, as ops in merged reading order.
 *
 * O(n*m) in time and memory, which is why the common prefix/suffix is trimmed first: the
 * usual case (one edit inside a long document) collapses to a table over just the changed
 * span instead of the whole input.
 *
 * @returns {Array<{op, before, after, beforeIndex, afterIndex}>}
 */
export function lcs(before, after, isEqual = (a, b) => a === b, onTie = null) {
    const ops = [];
    let prefix = 0;
    let endBefore = before.length;
    let endAfter = after.length;

    while (prefix < endBefore && prefix < endAfter && isEqual(before[prefix], after[prefix])) {
        prefix++;
    }
    while (endBefore > prefix && endAfter > prefix && isEqual(before[endBefore - 1], after[endAfter - 1])) {
        endBefore--;
        endAfter--;
    }

    for (let i = 0; i < prefix; i++) {
        ops.push(keepOp(before[i], after[i], i, i));
    }

    const a = before.slice(prefix, endBefore);
    const b = after.slice(prefix, endAfter);
    const table = buildTable(a, b, isEqual);

    let i = 0;
    let j = 0;
    while (i < a.length && j < b.length) {
        if (isEqual(a[i], b[j])) {
            ops.push(keepOp(a[i], b[j], prefix + i, prefix + j));
            i++;
            j++;
        } else if (table[i + 1][j] !== table[i][j + 1]) {
            if (table[i + 1][j] > table[i][j + 1]) {
                ops.push(removeOp(a[i], prefix + i));
                i++;
            } else {
                ops.push(addOp(b[j], prefix + j));
                j++;
            }
        } else if (onTie ? onTie(a[i], b[j], prefix + i, prefix + j) : true) {
            // A genuine tie: both paths yield an equally long subsequence, so the choice is
            // free — and for items it decides which one is reported as having moved. Callers
            // that can tell the difference pass onTie; the default prefers the removal, so a
            // removal is emitted before its matching addition (unified-diff order).
            ops.push(removeOp(a[i], prefix + i));
            i++;
        } else {
            ops.push(addOp(b[j], prefix + j));
            j++;
        }
    }
    while (i < a.length) {
        ops.push(removeOp(a[i], prefix + i));
        i++;
    }
    while (j < b.length) {
        ops.push(addOp(b[j], prefix + j));
        j++;
    }

    for (let k = 0; k < before.length - endBefore; k++) {
        ops.push(keepOp(before[endBefore + k], after[endAfter + k], endBefore + k, endAfter + k));
    }
    return ops;
}

/* -------------------------------- Text ---------------------------------- */

// Whitespace is kept as its own token so the ops can be reassembled into the original text.
export function tokenizeWords(text) {
    return String(text ?? '').match(/\s+|[^\s]+/g) || [];
}

export function diffWords(before, after) {
    return lcs(tokenizeWords(before), tokenizeWords(after));
}

export function diffLines(before, after) {
    return lcs(String(before ?? '').split('\n'), String(after ?? '').split('\n'));
}

export function diffChars(before, after) {
    return lcs([...String(before ?? '')], [...String(after ?? '')]);
}

/* ----------------------------- Similarity -------------------------------- */

function bigrams(text) {
    const normalized = String(text ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    const counts = new Map();
    for (let i = 0; i < normalized.length - 1; i++) {
        const gram = normalized.slice(i, i + 2);
        counts.set(gram, (counts.get(gram) || 0) + 1);
    }
    return counts;
}

/**
 * Sørensen–Dice coefficient over character bigrams, 0..1.
 *
 * Used to pair a removal with an addition when there is no key to match on. Preferred over
 * edit distance because it barely moves when a sentence is reordered or a clause is added,
 * which is exactly what an edited paragraph looks like.
 */
export function similarity(before, after) {
    if (before === after) {
        return 1;
    }
    const a = bigrams(before);
    const b = bigrams(after);
    let total = 0;
    let shared = 0;
    a.forEach((count) => {
        total += count;
    });
    b.forEach((count) => {
        total += count;
    });
    if (total === 0) {
        return before === after ? 1 : 0;
    }
    a.forEach((count, gram) => {
        if (b.has(gram)) {
            shared += Math.min(count, b.get(gram));
        }
    });
    return (2 * shared) / total;
}

/* -------------------------------- Items ---------------------------------- */

function fieldDiff(before, after, ignore) {
    const keys = new Set([...Object.keys(before || {}), ...Object.keys(after || {})]);
    const fields = [];
    keys.forEach((key) => {
        if (ignore.includes(key)) {
            return;
        }
        const a = before ? before[key] : undefined;
        const b = after ? after[key] : undefined;
        if (!deepEqual(a, b)) {
            fields.push({key, before: a, after: b});
        }
    });
    return fields;
}

function describePair(before, after, beforeIndex, afterIndex, moved, options) {
    const {textOf, equals, ignoreFields} = options;
    const same = equals(before, after);
    const entry = {
        status: same ? (moved ? STATUS.moved : STATUS.unchanged) : STATUS.modified,
        moved,
        before,
        after,
        beforeIndex,
        afterIndex,
    };
    if (!same) {
        entry.fields = fieldDiff(before, after, ignoreFields);
        if (textOf) {
            const beforeText = textOf(before) ?? '';
            const afterText = textOf(after) ?? '';
            if (beforeText !== afterText) {
                entry.words = diffWords(beforeText, afterText);
            }
        }
    }
    return entry;
}

// Exact alignment: a key present on both sides is the same item, full stop. Order is decided
// by an LCS over the key sequences — a matched pair outside that subsequence is one that
// moved, and it is reported once, at its new position.
function alignByKey(before, after, options) {
    const {keyOf} = options;
    const beforeKeys = before.map((item, index) => String(keyOf(item, index)));
    const afterKeys = after.map((item, index) => String(keyOf(item, index)));
    const beforeByKey = new Map();
    const afterByKey = new Map();
    beforeKeys.forEach((key, index) => beforeByKey.set(key, index));
    afterKeys.forEach((key, index) => afterByKey.set(key, index));

    /**
     * How far a key travelled. Missing from either side means it did not travel — it was
     * added or removed — so it is infinitely displaced and must never be chosen as an anchor.
     */
    const shift = (key) => (beforeByKey.has(key) && afterByKey.has(key)
        ? Math.abs(beforeByKey.get(key) - afterByKey.get(key))
        : Infinity);

    /**
     * Break ties by anchoring whichever key moved less, so the one that actually travelled is
     * the one reported as moved.
     *
     * Several subsequences can be equally long, and the loser of that tie is what gets called
     * "moved" — so an arbitrary pick can name the wrong block. [heading, a, b] -> [b, a] has
     * both [a] and [b] as longest common subsequences: anchoring b (which jumped from 3rd to
     * 1st) leaves a — sitting still at index 1 the whole time — reported as "moved 2 → 2",
     * while the block that really jumped reads as unchanged. Anchoring the stiller one puts
     * the label on the block that earned it.
     */
    const entries = [];
    lcs(beforeKeys, afterKeys, undefined, (beforeKey, afterKey) => shift(beforeKey) >= shift(afterKey))
        .forEach((op) => {
        if (op.op === OP.keep) {
            const beforeIndex = beforeByKey.get(op.before);
            const afterIndex = afterByKey.get(op.after);
            entries.push(describePair(before[beforeIndex], after[afterIndex], beforeIndex, afterIndex, false, options));
            return;
        }
        if (op.op === OP.remove) {
            if (afterByKey.has(op.before)) {
                return;   // it moved — reported at its new position by the matching add
            }
            entries.push({
                status: STATUS.removed, moved: false,
                before: before[op.beforeIndex], after: undefined,
                beforeIndex: op.beforeIndex, afterIndex: null,
            });
            return;
        }
        if (beforeByKey.has(op.after)) {
            const beforeIndex = beforeByKey.get(op.after);
            entries.push(describePair(before[beforeIndex], after[op.afterIndex], beforeIndex, op.afterIndex, true, options));
            return;
        }
        entries.push({
            status: STATUS.added, moved: false,
            before: undefined, after: after[op.afterIndex],
            beforeIndex: null, afterIndex: op.afterIndex,
        });
    });
    return entries;
}

// No keys: align on equality, then pair what is left over by text similarity so an edited
// item reads as one modification instead of a delete next to an unrelated insert.
function alignBySimilarity(before, after, options) {
    const {equals, textOf, similarityThreshold} = options;
    const ops = lcs(before, after, equals);
    const pairs = new Map();   // afterIndex -> beforeIndex

    if (textOf) {
        const removals = ops.filter((op) => op.op === OP.remove);
        const additions = ops.filter((op) => op.op === OP.add);
        const takenBefore = new Set();
        additions.forEach((addition) => {
            let best = null;
            let bestScore = similarityThreshold;
            removals.forEach((removal) => {
                if (takenBefore.has(removal.beforeIndex)) {
                    return;
                }
                const score = similarity(textOf(removal.before) ?? '', textOf(addition.after) ?? '');
                if (score > bestScore) {
                    bestScore = score;
                    best = removal;
                }
            });
            if (best) {
                takenBefore.add(best.beforeIndex);
                pairs.set(addition.afterIndex, best.beforeIndex);
            }
        });
    }

    const pairedBefore = new Set([...pairs.values()]);
    const entries = [];
    ops.forEach((op) => {
        if (op.op === OP.keep) {
            entries.push(describePair(op.before, op.after, op.beforeIndex, op.afterIndex, false, options));
            return;
        }
        if (op.op === OP.remove) {
            if (pairedBefore.has(op.beforeIndex)) {
                return;   // reported as a modification where its partner was added
            }
            entries.push({
                status: STATUS.removed, moved: false,
                before: op.before, after: undefined,
                beforeIndex: op.beforeIndex, afterIndex: null,
            });
            return;
        }
        if (pairs.has(op.afterIndex)) {
            const beforeIndex = pairs.get(op.afterIndex);
            entries.push(describePair(before[beforeIndex], op.after, beforeIndex, op.afterIndex, false, options));
            return;
        }
        entries.push({
            status: STATUS.added, moved: false,
            before: undefined, after: op.after,
            beforeIndex: null, afterIndex: op.afterIndex,
        });
    });
    return entries;
}

/**
 * Diff two arrays of arbitrary items.
 *
 * Knows nothing about what an item is — the caller injects that:
 *   keyOf(item, index)   stable identity. Given one, matching and move detection are exact
 *                        rather than guessed. Omit it and items are paired by similarity.
 *   textOf(item)         the text to word-diff inside a modified pair.
 *   equals(a, b)         defaults to a deep equality check.
 *   ignoreFields         field names to leave out of the field-level diff.
 *   similarityThreshold  0..1, only used when there is no keyOf. Below it, a removal and an
 *                        addition stay separate instead of pairing as a modification.
 */
export function diffItems(before = [], after = [], options = {}) {
    const resolved = {
        keyOf: options.keyOf || null,
        textOf: options.textOf || null,
        equals: options.equals || deepEqual,
        ignoreFields: options.ignoreFields || [],
        similarityThreshold: options.similarityThreshold ?? 0.5,
    };
    return resolved.keyOf
        ? alignByKey(before, after, resolved)
        : alignBySimilarity(before, after, resolved);
}

/* --------------------------------- API ----------------------------------- */

export function diffText(before, after, {granularity = 'line'} = {}) {
    if (granularity === 'word') {
        return diffWords(before, after);
    }
    if (granularity === 'char') {
        return diffChars(before, after);
    }
    return diffLines(before, after);
}

function textStats(ops) {
    const stats = {unchanged: 0, added: 0, removed: 0, modified: 0, moved: 0};
    ops.forEach((op) => {
        if (op.op === OP.keep) {
            stats.unchanged++;
        } else if (op.op === OP.add) {
            stats.added++;
        } else {
            stats.removed++;
        }
    });
    return stats;
}

function itemStats(entries) {
    const stats = {unchanged: 0, added: 0, removed: 0, modified: 0, moved: 0};
    entries.forEach((entry) => {
        stats[entry.status]++;
        if (entry.moved && entry.status === STATUS.modified) {
            stats.moved++;   // a block can both move and change; count it in each
        }
    });
    return stats;
}

/**
 * Diff two strings (text mode) or two arrays (items mode).
 *
 * @returns {{mode: 'text'|'items', changes: Array, stats: object}}
 */
export function diff(before, after, options = {}) {
    if (typeof before === 'string' || typeof after === 'string') {
        const changes = diffText(before ?? '', after ?? '', options);
        return {mode: 'text', changes, stats: textStats(changes)};
    }
    const changes = diffItems(before || [], after || [], options);
    return {mode: 'items', changes, stats: itemStats(changes)};
}
