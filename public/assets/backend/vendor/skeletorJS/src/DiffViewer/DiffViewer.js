import {diff, diffWords, OP, STATUS} from "./diff.js";
import {diffViewerSelectors} from "./diffViewerSelectors.js";
import {contentEditorSelectors} from "../ContentEditor/contentEditorSelectors.js";

const {classes, attributes, labels} = diffViewerSelectors;

/**
 * Renders a diff of two strings (text mode) or two arrays (items mode).
 *
 * Knows nothing about what an item is — pass keyOf/textOf/labelFor/renderItem and it will
 * render anything. All diffing lives in ./diff.js; this file only builds DOM.
 */
export default class DiffViewer {

    #target;
    #before;
    #after;
    #options;
    #root = null;
    #result = null;
    #toggleInput = null;

    constructor({target, before, after, options = {}}) {
        this.#target = target;
        this.#before = before;
        this.#after = after;
        this.#options = this.#mergeOptions(options);
    }

    /**
     * Plain text out of an HTML string, for items that carry markup (a block's `html`).
     * `textOf` output is rendered as text, so without this the tags would be diffed and
     * shown literally.
     */
    static textFromHtml(html) {
        const holder = document.createElement('div');
        holder.innerHTML = String(html ?? '');
        return (holder.textContent || '').replace(/\s+/g, ' ').trim();
    }

    init() {
        if (!this.#target) {
            console.warn('DiffViewer: no target element provided. Did you pass { target } ?');
            return this;
        }
        this.#render();
        return this;
    }

    #mergeOptions(options) {
        return {
            granularity: 'line',      // text mode: line | word | char
            view: 'unified',          // unified | split (both modes)
            sideLabels: null,         // split: {before, after} column headings
            keyOf: null,              // items: stable identity -> exact matching + moves
            textOf: null,             // items: the text to word-diff inside a changed pair
            equals: null,             // items: defaults to a deep equality check
            ignoreFields: [],         // items: fields to leave out of the field diff
            similarityThreshold: 0.5, // items: only used when there is no keyOf
            labelFor: null,           // items: card title. Defaults to item.type
            renderItem: null,         // items: HTML preview for a card
            formatValue: null,        // items: (key, value) -> display string
            collapseFields: true,     // items: field values start collapsed behind their keys
            collapseUnchanged: false,
            showStats: true,
            showToggle: true,
            ...options,
        };
    }


    getContainer() {
        return this.#root;
    }

    // The raw {mode, changes, stats} — the same thing diff() returns.
    getResult() {
        return this.#result;
    }

    setContent({before, after}) {
        this.#before = before;
        this.#after = after;
        this.#render();
        return this;
    }

    setOptions(options) {
        this.#options = {...this.#options, ...options};
        this.#render();
        return this;
    }

    destroy() {
        this.#detachToggle();
        if (this.#root) {
            this.#root.remove();
        }
        this.#root = null;
        this.#result = null;
        this.#target = null;
    }


    #render() {
        this.#result = diff(this.#before, this.#after, this.#options);
        this.#detachToggle();
        if (this.#root) {
            this.#root.remove();
        }
        this.#root = document.createElement('div');
        this.#root.classList.add(classes.root);
        this.#root.setAttribute(attributes.mode, this.#result.mode);
        this.#root.setAttribute(attributes.view, this.#options.view);
        this.#root.classList.toggle(classes.hideUnchanged, !!this.#options.collapseUnchanged);

        const toolbar = this.#buildToolbar();
        if (toolbar) {
            this.#root.appendChild(toolbar);
        }
        this.#root.appendChild(this.#result.mode === 'text' ? this.#buildText() : this.#buildItems());
        this.#target.appendChild(this.#root);
    }

    #buildToolbar() {
        if (!this.#options.showStats && !this.#options.showToggle) {
            return null;
        }
        const bar = document.createElement('div');
        bar.classList.add(classes.toolbar);
        if (this.#options.showStats) {
            bar.appendChild(this.#buildStats());
        }
        if (this.#options.showToggle) {
            bar.appendChild(this.#buildToggle());
        }
        return bar;
    }

    #buildStats() {
        const wrap = document.createElement('div');
        wrap.classList.add(classes.stats);
        [STATUS.added, STATUS.removed, STATUS.modified, STATUS.moved].forEach((status) => {
            const count = this.#result.stats[status];
            if (!count) {
                return;
            }
            const stat = document.createElement('span');
            stat.classList.add(classes.stat);
            stat.setAttribute(attributes.status, status);
            const number = document.createElement('span');
            number.classList.add(classes.statCount);
            number.textContent = String(count);
            stat.append(number, document.createTextNode(` ${labels[status].toLowerCase()}`));
            wrap.appendChild(stat);
        });
        if (!wrap.children.length) {
            const stat = document.createElement('span');
            stat.classList.add(classes.stat);
            stat.textContent = labels.identical;
            wrap.appendChild(stat);
        }
        return wrap;
    }

    #buildToggle() {
        const label = document.createElement('label');
        label.classList.add(classes.toggle);
        this.#toggleInput = document.createElement('input');
        this.#toggleInput.type = 'checkbox';
        this.#toggleInput.classList.add(classes.toggleInput, contentEditorSelectors.classes.input);
        this.#toggleInput.checked = !!this.#options.collapseUnchanged;
        this.#toggleInput.addEventListener('change', this.#handleToggle);
        label.append(this.#toggleInput, document.createTextNode(` ${labels.hideUnchanged}`));
        return label;
    }

    #handleToggle = () => {
        this.#root.classList.toggle(classes.hideUnchanged, this.#toggleInput.checked);
    };

    #detachToggle() {
        if (this.#toggleInput) {
            this.#toggleInput.removeEventListener('change', this.#handleToggle);
            this.#toggleInput = null;
        }
    }


    #buildText() {
        if (this.#options.granularity !== 'line') {
            const wrap = document.createElement('div');
            wrap.classList.add(classes.text);
            const content = document.createElement('div');
            content.classList.add(classes.content);
            this.#appendOps(content, this.#result.changes, true, true);
            wrap.appendChild(content);
            return wrap;
        }
        return this.#options.view === 'split' ? this.#buildSplit() : this.#buildUnified();
    }

    /**
     * A run of removals followed by a run of additions is one edit seen from both sides.
     * Pairing them index-wise is what lets a changed line highlight only the words that
     * actually changed, instead of lighting up the whole line.
     */
    #groupRows(ops) {
        const rows = [];
        let i = 0;
        while (i < ops.length) {
            if (ops[i].op === OP.keep) {
                rows.push({type: 'keep', op: ops[i]});
                i++;
                continue;
            }
            const removes = [];
            while (i < ops.length && ops[i].op === OP.remove) {
                removes.push(ops[i]);
                i++;
            }
            const adds = [];
            while (i < ops.length && ops[i].op === OP.add) {
                adds.push(ops[i]);
                i++;
            }
            const paired = Math.min(removes.length, adds.length);
            for (let p = 0; p < paired; p++) {
                rows.push({type: 'change', remove: removes[p], add: adds[p]});
            }
            removes.slice(paired).forEach((op) => rows.push({type: 'remove', op}));
            adds.slice(paired).forEach((op) => rows.push({type: 'add', op}));
        }
        return rows;
    }

    #buildUnified() {
        const wrap = document.createElement('div');
        wrap.classList.add(classes.text);
        this.#groupRows(this.#result.changes).forEach((row) => {
            if (row.type === 'keep') {
                wrap.appendChild(this.#textRow(STATUS.unchanged, row.op.beforeIndex, row.op.afterIndex,
                    (content) => { content.textContent = row.op.after; }));
                return;
            }
            if (row.type === 'change') {
                const words = diffWords(row.remove.before, row.add.after);
                wrap.appendChild(this.#textRow(STATUS.removed, row.remove.beforeIndex, null,
                    (content) => this.#appendOps(content, words, true, false)));
                wrap.appendChild(this.#textRow(STATUS.added, null, row.add.afterIndex,
                    (content) => this.#appendOps(content, words, false, true)));
                return;
            }
            if (row.type === 'remove') {
                wrap.appendChild(this.#textRow(STATUS.removed, row.op.beforeIndex, null,
                    (content) => { content.textContent = row.op.before; }));
                return;
            }
            wrap.appendChild(this.#textRow(STATUS.added, null, row.op.afterIndex,
                (content) => { content.textContent = row.op.after; }));
        });
        return wrap;
    }

    #buildSplit() {
        const wrap = document.createElement('div');
        wrap.classList.add(classes.split);
        const before = this.#buildSide(classes.sideBefore, labels.before);
        const after = this.#buildSide(classes.sideAfter, labels.after);

        this.#groupRows(this.#result.changes).forEach((row) => {
            if (row.type === 'keep') {
                before.appendChild(this.#textRow(STATUS.unchanged, row.op.beforeIndex, null,
                    (content) => { content.textContent = row.op.before; }));
                after.appendChild(this.#textRow(STATUS.unchanged, null, row.op.afterIndex,
                    (content) => { content.textContent = row.op.after; }));
                return;
            }
            if (row.type === 'change') {
                const words = diffWords(row.remove.before, row.add.after);
                before.appendChild(this.#textRow(STATUS.removed, row.remove.beforeIndex, null,
                    (content) => this.#appendOps(content, words, true, false)));
                after.appendChild(this.#textRow(STATUS.added, null, row.add.afterIndex,
                    (content) => this.#appendOps(content, words, false, true)));
                return;
            }
            // Unmatched rows get a filler opposite them so the two sides stay aligned.
            if (row.type === 'remove') {
                before.appendChild(this.#textRow(STATUS.removed, row.op.beforeIndex, null,
                    (content) => { content.textContent = row.op.before; }));
                after.appendChild(this.#filler());
                return;
            }
            before.appendChild(this.#filler());
            after.appendChild(this.#textRow(STATUS.added, null, row.op.afterIndex,
                (content) => { content.textContent = row.op.after; }));
        });

        wrap.append(before, after);
        return wrap;
    }

    #buildSide(sideClass, labelText) {
        const side = document.createElement('div');
        side.classList.add(classes.side, sideClass);
        const label = document.createElement('div');
        label.classList.add(classes.sideLabel);
        label.textContent = labelText;
        side.appendChild(label);
        return side;
    }

    #textRow(status, beforeIndex, afterIndex, fill) {
        const row = document.createElement('div');
        row.classList.add(classes.row);
        row.setAttribute(attributes.status, status);
        row.append(
            this.#gutter(classes.gutterBefore, beforeIndex),
            this.#gutter(classes.gutterAfter, afterIndex),
        );
        const content = document.createElement('span');
        content.classList.add(classes.content);
        fill(content);
        row.appendChild(content);
        return row;
    }

    #gutter(sideClass, index) {
        const gutter = document.createElement('span');
        gutter.classList.add(classes.gutter, sideClass);
        gutter.textContent = (index === null || index === undefined) ? '' : String(index + 1);
        return gutter;
    }

    #filler() {
        const row = document.createElement('div');
        row.classList.add(classes.row, classes.filler);
        return row;
    }

    // Text nodes, never innerHTML: op values are plain text, and a block's markup would
    // otherwise be injected here verbatim.
    #appendOps(container, ops, showRemoved, showAdded) {
        const visible = ops.filter((op) => (
            op.op === OP.keep || (op.op === OP.add ? showAdded : showRemoved)
        ));
        this.#mergeOps(visible).forEach(({op, text}) => {
            if (op === OP.keep) {
                container.appendChild(document.createTextNode(text));
                return;
            }
            container.appendChild(op === OP.add
                ? this.#mark('ins', classes.ins, text)
                : this.#mark('del', classes.del, text));
        });
    }

    /**
     * Collapses a run of same-kind ops into one.
     *
     * Whitespace is tokenized separately, so "their stable id." arrives as five ops and would
     * otherwise render as five padded, rounded <ins> elements — a striped highlight instead
     * of a continuous one. Runs on the already-filtered list so that dropping one side's ops
     * lets the survivors merge.
     */
    #mergeOps(ops) {
        const merged = [];
        ops.forEach((op) => {
            const text = op.op === OP.remove ? op.before : op.after;
            const last = merged[merged.length - 1];
            if (last && last.op === op.op) {
                last.text += text;
                return;
            }
            merged.push({op: op.op, text});
        });
        return merged;
    }

    #mark(tag, className, text) {
        const el = document.createElement(tag);
        el.classList.add(className);
        el.textContent = text;
        return el;
    }


    #buildItems() {
        return this.#options.view === 'split' ? this.#buildItemsSplit() : this.#buildItemsUnified();
    }

    #buildItemsUnified() {
        const list = document.createElement('div');
        list.classList.add(classes.items);
        if (!this.#result.changes.length) {
            list.appendChild(this.#emptyNotice());
            return list;
        }
        this.#result.changes.forEach((entry) => list.appendChild(this.#buildItem(entry)));
        return list;
    }

    /**
     * Side by side: cells of one grid rather than two independent columns, so a pair always
     * shares a row and the sides cannot drift apart. Labelling the columns also removes the
     * need to read a direction into "added"/"removed": which side holds the item says it.
     */
    #buildItemsSplit() {
        const grid = document.createElement('div');
        grid.classList.add(classes.itemsSplit);
        grid.append(this.#itemsSideLabel('before'), this.#itemsSideLabel('after'));
        if (!this.#result.changes.length) {
            const empty = this.#emptyNotice();
            empty.classList.add(classes.spanBoth);
            grid.appendChild(empty);
            return grid;
        }
        this.#splitRows().forEach((row) => {
            grid.append(
                row.left ? this.#buildItem(row.entry, 'before') : this.#itemFiller(row.entry),
                row.right ? this.#buildItem(row.entry, 'after') : this.#itemFiller(row.entry),
            );
        });
        return grid;
    }

    /**
     * Rows for the split view, each column in its own true order.
     *
     * A moved item sits at a different index on each side, so it cannot occupy one row and
     * still be in the right place in both columns. It therefore gets two rows: leaving its
     * old position, and arriving at its new one — which is what the text view already does.
     * Collapsing it into a single row instead would list one column in an order that side
     * never had, and a column labelled "before" must not misreport what came before.
     *
     * The columns are woven around the anchors — matched items that did not move. An LCS
     * guarantees those appear in the same relative order on both sides, so walking the two
     * sequences and emitting whichever is not an anchor keeps each column monotonic.
     */
    #splitRows() {
        const entries = this.#result.changes;
        const left = entries.filter((entry) => entry.before !== undefined)
            .sort((a, b) => a.beforeIndex - b.beforeIndex);
        const right = entries.filter((entry) => entry.after !== undefined)
            .sort((a, b) => a.afterIndex - b.afterIndex);
        const anchored = (entry) => entry.before !== undefined && entry.after !== undefined && !entry.moved;

        const rows = [];
        let i = 0;
        let j = 0;
        while (i < left.length || j < right.length) {
            if (i < left.length && j < right.length && left[i] === right[j]) {
                rows.push({entry: left[i], left: true, right: true});
                i++;
                j++;
            } else if (i < left.length && !anchored(left[i])) {
                rows.push({entry: left[i], left: true, right: false});
                i++;
            } else if (j < right.length && !anchored(right[j])) {
                rows.push({entry: right[j], left: false, right: true});
                j++;
            } else if (i < left.length) {
                rows.push({entry: left[i], left: true, right: false});
                i++;
            } else {
                rows.push({entry: right[j], left: false, right: true});
                j++;
            }
        }
        return rows;
    }

    #itemFiller(entry) {
        const cell = document.createElement('div');
        cell.classList.add(classes.item, classes.filler);
        cell.setAttribute(attributes.status, entry.status);
        return cell;
    }

    #emptyNotice() {
        const empty = document.createElement('div');
        empty.classList.add(classes.empty);
        empty.textContent = labels.identical;
        return empty;
    }

    #itemsSideLabel(side) {
        const label = document.createElement('div');
        label.classList.add(classes.sideLabel);
        label.textContent = this.#sideLabelText(side);
        return label;
    }

    #sideLabelText(side) {
        return (this.#options.sideLabels || {})[side] || labels[side];
    }

    // side null renders the whole entry (unified); 'before'/'after' renders one cell of it.
    #buildItem(entry, side = null) {
        const item = side ? entry[side] : (entry.after ?? entry.before);
        const cell = document.createElement('div');
        cell.classList.add(classes.item);
        cell.setAttribute(attributes.status, entry.status);
        if (side && !item) {
            // Nothing on this side — an addition seen from the left, a removal from the
            // right. The cell still occupies its half of the row so the sides stay aligned.
            cell.classList.add(classes.filler);
            return cell;
        }
        cell.appendChild(this.#buildItemHeader(entry, side));
        const body = this.#buildItemBody(entry, side, item);
        if (body) {
            cell.appendChild(body);
        }
        return cell;
    }

    #buildItemHeader(entry, side = null) {
        const header = document.createElement('div');
        header.classList.add(classes.itemHeader);
        const label = document.createElement('span');
        label.classList.add(classes.itemLabel);
        label.textContent = this.#labelFor(entry);

        const badges = document.createElement('span');
        badges.classList.add(classes.itemBadges);
        if (this.#showsBadges(entry, side)) {
            badges.appendChild(this.#badge(entry.status,
                entry.status === STATUS.moved ? this.#movedLabel(entry) : labels[entry.status]));
            // A block can move and change in the same revision; say both.
            if (entry.moved && entry.status !== STATUS.moved) {
                badges.appendChild(this.#badge(STATUS.moved, this.#movedLabel(entry)));
            }
        }
        header.append(label, badges);
        return header;
    }

    // Split view badges once, on the side that carries the meaning: "Removed" belongs with
    // the copy that is gone, everything else with the copy that survived. A move is the
    // exception — it occupies a row on each side, and an unbadged half would read as a
    // deletion. Badging both also says which arrival belongs to which departure.
    #showsBadges(entry, side) {
        if (!side) {
            return true;
        }
        if (entry.status === STATUS.unchanged) {
            return false;
        }
        if (entry.moved) {
            return true;
        }
        return side === (entry.status === STATUS.removed ? 'before' : 'after');
    }

    #movedLabel(entry) {
        return `${labels.moved} ${entry.beforeIndex + 1} → ${entry.afterIndex + 1}`;
    }

    #badge(status, text) {
        const badge = document.createElement('span');
        badge.classList.add(classes.badge);
        badge.setAttribute(attributes.status, status);
        badge.textContent = text;
        return badge;
    }

    #buildItemBody(entry, side = null, item = null) {
        const body = document.createElement('div');
        body.classList.add(classes.itemBody);
        const preview = this.#buildPreview(entry, side, item ?? (entry.after ?? entry.before));
        if (preview) {
            body.appendChild(preview);
        }
        const fields = this.#buildFields(entry, side);
        if (fields) {
            body.appendChild(fields);
        }
        return body.children.length ? body : null;
    }

    #buildPreview(entry, side, item) {
        const preview = document.createElement('div');
        preview.classList.add(classes.preview);
        if (entry.words) {
            // Unified shows both sides of the word diff; a split cell shows only its own.
            this.#appendOps(preview, entry.words, side !== 'after', side !== 'before');
            return preview;
        }
        if (this.#options.renderItem) {
            const html = this.#options.renderItem(item, entry);
            if (!html) {
                return null;
            }
            preview.innerHTML = html;   // the consumer owns this markup
            return preview;
        }
        if (this.#options.textOf) {
            const text = this.#options.textOf(item);
            if (!text) {
                return null;
            }
            preview.textContent = text;
            return preview;
        }
        return null;
    }

    #buildFields(entry, side = null) {
        if (!entry.fields || !entry.fields.length) {
            return null;
        }
        const fields = entry.words ? entry.fields.filter((field) => !this.#isTextField(field, entry)) : entry.fields;
        if (!fields.length) {
            return null;
        }
        // <details> rather than a JS toggle: nothing to bind, nothing to tear down, and it is
        // keyboard-accessible for free. The summary names the changed keys — which is the
        // part worth seeing at a glance — and keeps the serialised values, which can run to
        // a screenful of JSON, out of the way until they are asked for.
        const list = document.createElement('details');
        list.classList.add(classes.fields);
        list.open = !this.#options.collapseFields;

        const summary = document.createElement('summary');
        summary.classList.add(classes.fieldsSummary);
        summary.textContent = fields.map((field) => field.key).join(', ');
        list.appendChild(summary);

        const rows = document.createElement('div');
        rows.classList.add(classes.fieldsList);
        fields.forEach((field) => rows.appendChild(this.#buildField(field, side)));
        list.appendChild(rows);
        return list;
    }

    // The field textOf read is already rendered as the word diff — showing it again as a
    // raw before/after pair would just repeat it.
    #isTextField(field, entry) {
        const {textOf} = this.#options;
        if (!textOf) {
            return false;
        }
        const beforeText = entry.before ? textOf(entry.before) : undefined;
        const afterText = entry.after ? textOf(entry.after) : undefined;
        return field.before === beforeText && field.after === afterText;
    }

    #buildField(field, side = null) {
        const row = document.createElement('div');
        row.classList.add(classes.field);
        const key = document.createElement('span');
        key.classList.add(classes.fieldKey);
        key.textContent = field.key;
        const values = document.createElement('span');
        values.classList.add(classes.fieldValues);
        const before = this.#formatValue(field.key, field.before);
        const after = this.#formatValue(field.key, field.after);
        if (side !== 'after' && before !== '') {
            values.appendChild(this.#mark('del', classes.del, before));
        }
        if (side !== 'before' && after !== '') {
            values.appendChild(this.#mark('ins', classes.ins, after));
        }
        row.append(key, values);
        return row;
    }

    #formatValue(key, value) {
        if (this.#options.formatValue) {
            const custom = this.#options.formatValue(key, value);
            if (custom !== undefined && custom !== null) {
                return String(custom);
            }
        }
        if (value === undefined || value === null) {
            return '';
        }
        return typeof value === 'object' ? JSON.stringify(value) : String(value);
    }

    #labelFor(entry) {
        const item = entry.after ?? entry.before;
        if (this.#options.labelFor) {
            return String(this.#options.labelFor(item, entry) ?? '');
        }
        return item && typeof item === 'object' && item.type ? String(item.type) : '';
    }
}
