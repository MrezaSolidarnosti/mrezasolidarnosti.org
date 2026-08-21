# EntitySearch Class

A backend-agnostic search popup: a floating, debounced, keyboard-navigable list of results
for a query. Dependency-free vanilla JS.

It knows nothing about URLs, auth, or response shapes. You inject `search(query)` — a promise
of any array — and `labelOf`/`renderResult` to display a row. The domain lives in the
callbacks, the same split as `Chart` (generic component) vs its consumers.

## Usage

Include `css/style.css` (which bundles `_entitySearch.scss`).

```js
import EntitySearch from '/src/EntitySearch/EntitySearch.js';

const picker = new EntitySearch({
    search:  async (q) => (await fetch(`/posts?q=${encodeURIComponent(q)}`)).json(),
    labelOf: (post) => post.title,
    onSelect: (post) => console.log('picked', post),
}).init();

picker.openAt(anchor, 'que');   // anchor: a DOMRect or an element to position below
```

## Options

| Option | Default | Description |
| --- | --- | --- |
| `search` | — | `async (query) => any[]`. **The only backend touchpoint.** Your fetch, URL and response shape live entirely here. |
| `labelOf` | `String` | `(item) => string`. The row's text (rendered as text — safe). |
| `renderResult` | `null` | `(item) => htmlString`. Richer row. **Not escaped** — you own this markup. |
| `onSelect` | — | `(item) => void`. Called with the chosen item. |
| `onClose` | `null` | Called when the popup closes without a pick. |
| `container` | `document.body` | Where the popup is appended. |
| `showInput` | `true` | `false` = inline mode: no internal field, query driven by `setQuery()`, focus stays in the host. |
| `minChars` | `1` | Below this the popup shows the hint and doesn't search. |
| `debounce` | `200` | ms before a query fires. |
| `instant` | `false` | For local/synchronous `search`: skip the debounce **and** the loading state, so typing filters in place instead of flashing through an empty "Searching…" list on every keystroke. |
| `maxResults` | `8` | Results kept from the returned array. |
| `className` | `null` | Extra class(es) on the root — a string or array — so a consumer can style its own popup without touching the shared `entitySearch` base class. |
| `placeholder` / `hintText` / `loadingText` / `emptyText` / `errorText` | — | The strings for each state. |

## Methods

| Method | Description |
| --- | --- |
| `init()` | Build the DOM. Returns `this`. |
| `openAt(anchor, query?)` | Show, positioned below `anchor` (a DOMRect or element; flips above if no room). |
| `setQuery(query)` | Update the query without reopening — for inline mode as the user types. |
| `isOpen()` / `close()` / `getContainer()` | — |
| `destroy()` | Remove listeners and the DOM. |

## Notes

- **Async race guard.** Each search takes a monotonic id; a response is dropped if a newer
  search started (or the popup closed) while it was in flight — so a slow early query can
  never overwrite a fast later one.
- **Keyboard.** While open it listens on a *capturing* document handler, so ↑/↓/Enter/Escape
  drive the list even when focus is elsewhere (inline mode). Enter on a highlighted row
  `stopPropagation`s, so a host editor's own Enter doesn't also fire.
- **Selection is a `mousedown`**, not a click, with `preventDefault`, so picking a row never
  blurs the host editor first.
- Styling hangs off the classes in `entitySearchSelectors.js` and the editor's CSS variables.
