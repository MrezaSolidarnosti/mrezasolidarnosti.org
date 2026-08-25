# DiffViewer Class

Renders a diff of two **strings** (text mode) or two **arrays** (items mode). Dependency-free
vanilla JS, no framework, no build step.

It knows nothing about what an item *is*. Blocks, revisions, records, rows — you inject the
semantics with a few callbacks, the same way `Chart` knows nothing about the ContentEditor.

All the diffing lives in `diff.js`, which is pure and DOM-free; `DiffViewer.js` only builds
DOM. You can use the engine on its own — in a test, or on a backend — and never touch the
viewer.

```
src/DiffViewer/
  diff.js               the engine: pure functions over plain data
  DiffViewer.js         the component: renders what the engine returns
  diffViewerAssets.js   frozen class names / attributes / labels
css/_diffViewer.scss    styles (imported by css/style.scss)
```

## Usage

Needs `css/style.css` (which bundles `_diffViewer.scss`) and an element to render into.

```html
<div id="diff"></div>
<script type="module">
    import DiffViewer from '/src/DiffViewer/DiffViewer.js';

    new DiffViewer({
        target: document.getElementById('diff'),
        before: 'The DOM is the source of truth.\nBlocks are matched by position.',
        after:  'The DOM is the single source of truth.\nBlocks are matched by id.',
    }).init();
</script>
```

Passing strings selects **text mode**; passing arrays selects **items mode**.

### Text mode

```js
new DiffViewer({
    target,
    before: oldString,
    after: newString,
    options: {granularity: 'line', view: 'split'},
}).init();
```

Changed lines are paired, so only the words that actually differ get highlighted rather than
the whole line lighting up.

### Items mode

Everything the viewer knows about your items comes from the callbacks:

```js
new DiffViewer({
    target,
    before: revisionA.blocks,       // [{ type, id, html, ... }]
    after:  revisionB.blocks,
    options: {
        keyOf:      (block) => block.id,
        textOf:     (block) => DiffViewer.textFromHtml(block.html ?? ''),
        labelFor:   (block) => block.type,
        ignoreFields: ['id', 'type', 'additionalData'],
        renderItem: (block) => block.html ?? '',
        collapseUnchanged: true,
    },
}).init();
```

**Give it a `keyOf` if you can.** With a stable identity, matching and move detection are
*exact*. Without one it falls back to pairing leftovers by text similarity, which is a guess.
ContentEditor blocks carry a persisted `id`, so revision diffs get the exact path.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `granularity` | `'line'` | Text mode: `line`, `word` or `char`. Non-line renders one flowing diff. |
| `view` | `'unified'` | Text mode, line granularity: `unified` or `split`. |
| `keyOf` | `null` | Items: `(item, index) => string`. Stable identity — enables exact matching and moves. |
| `textOf` | `null` | Items: `(item) => string`. The text to word-diff inside a changed pair. Treated as **plain text**. |
| `equals` | deep equal | Items: `(a, b) => boolean`. |
| `ignoreFields` | `[]` | Items: field names to leave out of the field-level diff. |
| `similarityThreshold` | `0.5` | Items: 0–1. Only used when there is no `keyOf`. |
| `labelFor` | `item.type` | Items: `(item, entry) => string`. The card title. |
| `renderItem` | `null` | Items: `(item, entry) => htmlString`. Preview for added/removed/unchanged cards. **Not escaped** — you own this markup. |
| `formatValue` | `null` | Items: `(key, value) => string`. Return `undefined` to fall back to the default. |
| `collapseFields` | `true` | Field values start collapsed behind a `<details>` naming the changed keys. A serialised value (a gallery's `images`, a table's `rows`) can run to a screenful of JSON; the keys are the part worth seeing at a glance. |
| `collapseUnchanged` | `false` | Start with unchanged entries hidden. |
| `showStats` | `true` | The counts in the toolbar. |
| `showToggle` | `true` | The "Hide unchanged" checkbox. |

## Methods

| Method | Description |
| --- | --- |
| `init()` | Render. Returns `this`. |
| `getContainer()` | The root element. |
| `getResult()` | The raw `{mode, changes, stats}` — the same thing `diff()` returns. |
| `setContent({before, after})` | Re-diff and re-render. Returns `this`. |
| `setOptions(options)` | Merge options and re-render. Returns `this`. |
| `destroy()` | Remove listeners and the DOM. |
| `DiffViewer.textFromHtml(html)` | *(static)* Plain text out of an HTML string, for `textOf`. |

## The engine

`diff.js` is importable on its own and has no DOM dependency:

```js
import {diff, diffItems, diffWords, lcs, similarity, STATUS, OP} from '/src/DiffViewer/diff.js';

diff(before, after, options);   // -> { mode: 'text'|'items', changes, stats }
```

| Export | Description |
| --- | --- |
| `diff(before, after, options)` | Auto-detects mode. Returns `{mode, changes, stats}`. |
| `diffItems(before, after, options)` | Array diff. Returns the entries below. |
| `diffText(before, after, {granularity})` | String diff. Returns ops. |
| `diffLines` / `diffWords` / `diffChars` | String diff at one granularity. Returns ops. |
| `lcs(before, after, isEqual?)` | The shared core, over any two arrays. Returns ops. |
| `similarity(a, b)` | Sørensen–Dice over character bigrams, 0–1. |
| `deepEqual(a, b)` | Key-order independent deep equality. |
| `STATUS` / `OP` | The status and op vocabularies. |

An **op** (text, and the `words` inside an entry):

```js
{ op: 'keep' | 'add' | 'remove', before, after, beforeIndex, afterIndex }
```

An **entry** (items):

```js
{
  status: 'unchanged' | 'added' | 'removed' | 'modified' | 'moved',
  moved: boolean,            // an item can be modified *and* moved
  before, after,             // the items (undefined where n/a)
  beforeIndex, afterIndex,   // null where n/a
  words,                     // word ops, when textOf is set and the text changed
  fields,                    // [{key, before, after}], when the data changed
}
```

## Notes

- **Text and items are the same algorithm.** One LCS runs at three granularities: lines,
  items, and the words inside a changed pair. Text mode is items mode where the item is a
  line and its text is itself.
- **`textOf` output is rendered as text, not HTML.** Word-diffing raw markup would both
  break the tags and inject them into the page. Use `DiffViewer.textFromHtml()` if your items
  carry HTML. `renderItem` is the deliberate exception — that markup is yours.
- **The field the word diff already shows is dropped** from the field list automatically, so
  a changed paragraph doesn't render its `html` twice.
- **A swap is one move, not two.** Which side reads as "moved" in a straight two-item swap is
  a genuine tie (`[a,b] -> [b,a]` is equally "a moved down" or "b moved up"); the LCS
  tie-break prefers the removal, so the earlier item is reported.
- **Performance.** The LCS is O(n·m), but the common prefix and suffix are trimmed first, so
  the usual case — one edit in a long document — only builds a table over the changed span.
  400 blocks with a single edit diffs in well under a millisecond.
- Styling hangs off `data-diff-status` and a handful of CSS custom properties declared on
  `.diffViewer`. Override them on any ancestor to restyle.

  | Property | Default | |
  | --- | --- | --- |
  | `--diffSurface` | `--colorSurface-200` | Cards, toolbar, gutters, column headings. |
  | `--diffBorder` | `--colorSurface-300` | |
  | `--diffMuted` | `--colorSurface-600` | Secondary text. |
  | `--diffAdded` / `--diffAddedBg` | success | Also `--diffRemoved`, `--diffModified`, `--diffMoved` (+`Bg`). |
  | `--diffFont` | monospace stack | |

  **Set `--diffSurface` whenever the viewer sits on anything other than `--colorSurface-100`.**
  It defaults to `--colorSurface-200`, so on a surface-200 background every card, the toolbar
  and both column headings land on exactly their own background and disappear. Move it (and
  `--diffBorder`) up a step:

  ```css
  .myPanel .diffViewer {
      --diffSurface: var(--colorSurface-300);
      --diffBorder: var(--colorSurface-400);
  }
  ```
