# ContentEditor

A block-based, dependency-free content editor (Gutenberg / Lexical style) in vanilla
JavaScript. It edits an **entity** (a post, a page, …) as an ordered list of **blocks**
plus a set of **modules** (title, slug, categories, SEO, …) and produces a plain JSON
payload for your backend to persist and render.

- **No framework, no dependencies** — plain ES modules + SCSS.
- **The DOM is the source of truth** — no parallel model; serialization walks the live DOM,
  and each block's `getData()` reads from its own element.
- **Config-driven** — which blocks and modules an instance has is decided entirely by config,
  so one HTML shell serves posts, pages, or anything else.

---

## Contents

1. [Quick start](#quick-start)
2. [The HTML shell](#the-html-shell)
3. [Config](#config)
4. [Save & load](#save--load)
5. [Read-only](#read-only)
6. [Translation](#translation)
7. [API reference](#api-reference)
8. [Adding a block](#adding-a-block)
9. [Adding a module](#adding-a-module)
10. [Events](#events)

---

## Quick start

```js
import ContentEditor from './ContentEditor/ContentEditor.js';

const editor = new ContentEditor({
    config: {
        appBlocksPath: '/js/app-blocks/',                 // where your app/* blocks live
        modules: ['title', 'slug', 'seo', 'status'],       // entity fields to enable
        blocks:  ['core/paragraph', 'core/heading', 'core/image', 'core/chart'],
    },
    initialContent: {
        title: 'Hello world',
        blocks: [
            { type: 'core/heading',  html: 'Introduction' },
            { type: 'core/paragraph', html: 'First paragraph.' },
        ],
    },
});

await editor.init();   // async — it dynamically imports the block modules first
```

Include the compiled stylesheet (`css/style.css`, built from the SCSS) and mount the editor
on the HTML shell below. Call `editor.destroy()` to tear it all down.

---

## The HTML shell

The editor attaches to existing markup — it does **not** generate the chrome. Every element
below is resolved by id/class from `contentEditorSelectors.js`. This is the minimum shell;
the module sidebar sections are only needed for the modules you enable (see
[Adding a module](#adding-a-module)).

```html
<div id="contentEditor">

    <!-- Top bar -->
    <div id="topBar">
        <div id="topBarLeft">
            <div id="blockInserterButton" title="Add block"><!-- + icon --></div>
            <div id="overviewButton" title="Overview"><!-- outline icon --></div>
            <div id="undoRedoContainer">
                <div id="undoButton" title="Undo"><!-- icon --></div>
                <div id="redoButton" title="Redo"><!-- icon --></div>
            </div>
        </div>
        <div id="topBarCenter"></div>
        <div id="topBarRight">
            <!-- one entry per module that has a top-bar control, e.g. SEO: -->
            <div id="seo" title="SEO" data-module="seo"><span>SEO</span><!-- icon --></div>
            <div id="save" title="Save"><span>Save</span><!-- icon --></div>
            <div id="userSettings" title="Editor settings"><!-- gear icon, optional --></div>
            <div id="toggleSidebar" title="Toggle Sidebar"><!-- icon --></div>
        </div>
    </div>

    <!-- Messages (validation errors, notices) -->
    <div id="messagesContainer"></div>

    <!-- The canvas -->
    <div id="contentContainer">
        <h1 id="title" contenteditable="true" data-placeholder="Add title" data-module="title"></h1>
        <div id="content"><!-- blocks are rendered here --></div>
    </div>

    <!-- Sidebar: "Content" tab (entity/modules) + "Block" tab (active block's settings) -->
    <div id="sidebar" class="active">
        <div id="sidebarNavigation">
            <span data-target="sidebarEntityContent">Content</span>
            <span data-target="sidebarBlockContent">Block</span>
            <span id="closeSidebar"><!-- close icon --></span>
        </div>
        <div id="sidebarEntityContent" class="sidebarContent">
            <!-- one [data-module="..."] section per enabled sidebar module (slug, status, …) -->
        </div>
        <div id="sidebarBlockContent" class="sidebarContent">
            <!-- filled at runtime with the focused block's sidebar -->
        </div>
    </div>

    <div id="bottomBar">
        <div id="shortcutsButton" title="Shortcuts"><!-- icon --></div>
    </div>

    <!-- Floating block hover toolbar -->
    <div id="blockSideToggle">
        <div id="blockSideToggleDragHandle" class="blockSideAction" title="Drag"><!-- icon --></div>
        <div id="blockSideToggleMoveUp"   class="blockSideAction" title="Move up"><!-- icon --></div>
        <div id="blockSideToggleMoveDown" class="blockSideAction" title="Move down"><!-- icon --></div>
        <div id="blockSideToggleMore"     class="blockSideAction" title="More"><!-- icon --></div>
        <div id="blockSideToggleMoreMenu">
            <span id="blockSideToggleAddBefore">Insert Before</span>
            <span id="blockSideToggleAddAfter">Insert After</span>
            <span id="blockSideToggleDuplicate">Duplicate</span>
            <span id="blockSideToggleDelete">Delete</span>
        </div>
    </div>

    <!-- Panels -->
    <div id="shortcutsContainer"><div id="shortcutsHeader"><h2>Keyboard Shortcuts</h2>
        <div id="closeShortcuts" title="Close"><!-- icon --></div></div></div>
    <div id="overviewContainer"></div>
    <div id="blockInserter">
        <div id="blockInserterHeader"><span>Blocks</span>
            <div id="blockInserterClose" title="Close"><!-- icon --></div></div>
        <input type="text" id="blockInserterSearch" class="input" placeholder="Search">
        <div id="blockInserterList"></div>
    </div>

</div>
```

Rules of thumb:

- `#content` is the block canvas; `#title` is the entity title (a contentEditable outside
  the canvas, tagged `data-module="title"`).
- Every enabled **sidebar** module needs a `[data-module="<name>"]` section under
  `#sidebarEntityContent`; **top-bar / modal** modules (like SEO) live in `#topBarRight` and
  as their own modal. A module whose section is missing is skipped with a console warning; a
  module **not** in `config.modules` has its section auto-removed.
- The block menu (`#blockMenu`) and format toolbar (`#formatToolbar`) are created at runtime
  and appended to `<body>` — you don't add them.

---

## Config

```js
{
    appBlocksPath: '/js/app-blocks/', // base path for app/* block modules (dynamic import)
        imagePath: '/uploads/',           // prefix for stored image filenames
        width: '80%',                       // editor width (default 60%)
        modules: ['title', 'slug', 'seo', 'categories', 'authors', 'tags', 'featuredImage', 'status'],
        blocks:  ['core/paragraph', 'core/heading', /* … */ 'app/readmore'],
        unsavedGuard: true,               // warn before leaving with unsaved changes (default true)
        commandTrigger: '//',             // what opens the command menu (default '//')
        readOnly: false,                  // start the whole editor read-only (default false)
        blockDefaults: {                  // project starting values, by block type (optional)
        'core/image': {align: 'center'},
        'core/divider': {height: 4, color: '#b30000'},
    },
    userSettings: {                   // per-person preferences, kept in localStorage
        key: 'myApp.contentEditor.settings',
    },
    commandPalette: {                 // Ctrl/Cmd+K palette — off unless enabled is explicitly true
        enabled: true,                  // required to turn it on (default false); flips at runtime
            search: async (q) => [ /*…*/ ], // optional backend search endpoint (see below)
    },
}
```

- **`commandTrigger`** — the sequence that opens the command menu, `//` by default. Any
  whitespace-free string works (`::`, `>>`, …); pick something your authors don't type by
  accident. See [`CommandMenu.register`](#commandmenuregisterdefinition).
- **`imagePath`** — prefixed onto image filenames that came from **saved content**, so the
  editor can display them. Used by the Image and Gallery blocks and by the Featured Image and
  SEO modules. Defaults to `''`.

  Two things follow from this:

    - **Only the filename is saved.** `getData()` stores what the media library gave it and never
      the prefix, so moving your uploads is a config change and not a content migration. Don't
      prefix `this.src` in a block — only the `src` you put in the DOM.
    - **Images picked live are not prefixed.** A fresh pick arrives as `mediaData[0].img`, which
      is markup the media library already built with the full path in it. Insert that as-is;
      prefixing it doubles the path. `imagePath` is for the *restore* path only.

- **`readOnly`** — start the entire editor read-only (blocks, save, every module). Off by
  default. See [Read-only](#read-only).
- **`commandPalette`** — the Ctrl/Cmd+K global palette. **Off by default**: it turns on only with
  an explicit `enabled: true` (a bare `commandPalette: {}` stays off). The Ctrl+K binding is
  created only when it starts enabled. See [`CommandPalette`](#commandpalette--ctrlk).
- **`blockDefaults`** — starting values per block type, so a project's blocks begin at its own
  house style instead of the library's. See [Block defaults](#block-defaults).
- **`userSettings.key`** — the localStorage key the editor's per-person preferences live
  under. See [User settings](#user-settings).
- **`unsavedGuard`** — set `false` to turn off the leave-with-unsaved-changes prompt entirely.
  Defaults to on. Can also be toggled at runtime with `editor.unsavedGuard.setEnabled(…)` (see
  [Save & load](#save--load)).
- **`modules`** — omit to enable all registered modules. Built-ins: `title`, `slug`,
  `categories`, `authors`, `tags`, `featuredImage`, `status`, `seo`.
- **`blocks`** — the exact set that can be inserted/loaded. `core/*` load from `Blocks/`,
  `app/*` from `appBlocksPath`. Built-in blocks: `core/paragraph`, `core/heading`,
  `core/headingtwo`, `core/headingthree`, `core/headingfour`, `core/headingfive`,
  `core/headingsix`, `core/unorderedList`, `core/orderedList`,
  `core/quote`, `core/html`, `core/image`, `core/gallery`, `core/divider`, `core/embed`,
  `core/spacer`, `core/columns`, `core/file`, `core/table`, `core/chart`,
  `core/accordion`, `core/tabs`, `core/timeline`, `core/footnotes`.

---

### User settings

The gear in the top bar, between Save and the sidebar toggle. These are *view* preferences —
how the editor looks to one person on one machine. Nothing here is post content: none of it
reaches `getDataForSave()`, and none of it is per-post.

They live in localStorage under a key the project picks:

```js
userSettings: {key: 'myApp.contentEditor.settings'},
```

Give the posts editor and the pages editor the **same** key and a writer sets their font size
once for both. Give them **different** keys and each page remembers separately. Omit it and
everything still works under a default key.

Two settings ship built in, both acting on `#content`:

| Setting | Base | Medium | Large |
| --- | --- | --- | --- |
| Content font size | the stylesheet's own size | `1.2rem` | `1.4rem` |
| Content width | `config.width`, or the stylesheet | `70%` | `80%` |

**Base means "the project's default", not "no width"** — `config.width` is written to the same
inline style at init, so choosing Base restores it rather than clearing it. A project that sets
`width: '90%'` gets 90% back, not the stylesheet's 60%.

#### Registering your own

```js
import UserSettings from '/js/ContentEditor/UserSettings/UserSettings.js';

UserSettings.register({
    key: 'spellcheck',                  // also the property it is stored under
    label: 'Spellcheck',
    description: 'Underline misspelled words while you write.',   // optional
    default: 'off',
    options: [
        {value: 'off', label: 'Off'},
        {value: 'on',  label: 'On'},
    ],
    apply: (value, {editor}) => {
        editor.contentContainer.spellcheck = value === 'on';
    },
});

UserSettings.unRegister('contentWidth');   // or drop a built-in
```

Register **before** constructing the editor, like every other registry.

`apply` is the whole contract. It runs once at init with the stored value and again on every
change, so a setting never needs to know whether it is being restored or chosen. It receives
`{editor, settings}` — the editor for the canvas and config, the `UserSettings` instance if you
need to read another value.

Settings render in registration order. The two built-ins register at the bottom of
`UserSettings.js`, so project code always lands after them. Registering an existing key
**replaces** it, which is how you redefine a built-in — different labels, a third size — rather
than removing and rebuilding it.

#### Behaviour worth knowing

- **Stored values are re-validated on read.** If you rename an option's value, anyone still on
  the old one falls back to the default instead of ending up with no button selected.
- **A throwing `apply` is caught and logged**, so one bad setting can't stop the others from
  applying — the same isolation content transforms get.
- **Not read-only aware, deliberately.** Someone reviewing a locked post has as much reason to
  want larger text as the person who wrote it.
- **The side toggle re-measures itself** after every change. A setting that moves the canvas
  — width, font size, anything — would otherwise leave the block toolbar at the old
  coordinates, since it is positioned in fixed units off the active block. This fires for
  your settings too, so you don't have to think about it.
- **`setValue(key, value)` is public**, so a project can set a preference from code. Unknown
  keys and values are ignored rather than stored.

#### Markup

The button and the modal shell belong to your HTML (see [The HTML shell](#the-html-shell)); the
rows inside `#userSettingsList` are rendered from the registry, so adding a setting needs no
markup at all.

```html
<div id="userSettings" title="Editor settings"><!-- icon --></div>

<div id="userSettingsModal">
  <div id="userSettingsHeader">
    <h2>Editor settings</h2>
    <div id="closeUserSettings" title="Close"><!-- icon --></div>
  </div>
  <div id="userSettingsList"></div>
</div>
```

If the button or the modal is missing, the editor initialises normally and the feature is simply
absent — the same way a module with no DOM section is.

---

## Save & load

**Save** — `editor.getDataForSave()` returns:

```js
{
  blocks: [ /* one entry per block, in DOM order */ ],
  title, slug, category, author, tags, featuredImage, status, seo   // only enabled modules
}
```

A saved block is `{ type, id, ...block.getData(), additionalData }` (+ `columns` for
containers).

### Save button state

The save button has a busy state, so an async save can show progress instead of looking dead:

```js
editor.saveHandler.saving();      // spinner in the button, `saving` class on it
await fetch('/api/posts/1', {method: 'PUT', body: JSON.stringify(editor.getDataForSave())});
editor.saveHandler.notSaving();   // back to normal
```

`saving()` swaps the button's icon and label for a spinner and adds the `saving` class;
`notSaving()` reverses both. Call them in pairs — including from a `catch`, or a failed request
leaves the button spinning forever.

Neither is called for you. The editor emits `beforeSave` and `afterSave` around
`getDataForSave()`, and the request itself is the project's, so the state is the project's too.


**Unknown blocks are preserved, not dropped.** If a loaded block's `type` isn't in
`config.blocks` — a stripped-down config, an app block that failed to import, content from a
newer schema — it renders as an inert placeholder and **re-emits its original entry verbatim
on save** (type, id, every field, `additionalData`, nested `columns`). So a round trip through
an editor that doesn't understand a block never destroys its content; it just can't edit it.
The user can still delete the placeholder deliberately.

**`id`** is the block's stable identity, managed for you: minted when the block is created,
persisted, and reused on load — so it survives a save/load round trip *and* undo/redo. That
lets revision diffs match blocks exactly and detect moves, instead of guessing by content
similarity. A **duplicated** block gets a fresh id (a copy is a new block). It's optional in
`initialContent` — content saved without one just gets an id minted on load. Block authors
never touch it.

> **`id` and `type` are reserved.** `getData()` must not return either — `getBlockData()`
> writes them over your payload, so an `id` of your own would be silently replaced by the
> block identity (and would double as the block id on load). Name payload fields something
> else: the media blocks use `mediaId`, not `id`.

**Load** — `initialContent` mirrors the save shape exactly (modules set from their save key,
blocks rendered in order). Examples:

```js
// text block with inline formatting + Advanced (per-block) settings
{ type: 'core/paragraph',
    html: 'Bold <strong>text</strong> and a <a href="https://x.com">link</a>.',
    additionalData: { classNames: 'lead', htmlId: 'intro', inlineCss: 'color:#b30000;' } }

// container block
{ type: 'core/columns', layout: '33-66',
    columns: [ [ /* left blocks */ ], [ /* right blocks */ ] ] }

// chart block
{ type: 'core/chart', chartType: 'groupedbar',
    labels: ['Q1','Q2'], series: [ { name:'Revenue', values:[120,200] } ] }

// media blocks — mediaId is the media library id, distinct from the block's own id
{ type: 'core/image', mediaId: 1, src: 'https://placehold.co/600x300' }
{ type: 'core/file', mediaId: 1, src: 'test.mp3', mimeType: 'audio/mpeg' }
{ type: 'core/gallery',
    images: [ { mediaId: 11, src: 'https://placehold.co/300x300?text=1' },
    { mediaId: 12, src: 'https://placehold.co/300x300?text=2' } ] }
```

`beforeSave` / `afterSave` events fire around the save, and `SaveValidation` runs between
them. (The bundled `getDataForSave` builds the payload; wiring it to your backend is up to
you — do it in an `afterSave` handler.)

### Unsaved-changes guard

Closing the tab, reloading, or navigating away with unsaved work triggers the browser's
built-in "Leave site?" prompt. It's on by default and needs nothing from the host page. Turn
it off entirely with `config.unsavedGuard: false`, or at runtime with `setEnabled` (below).

"Unsaved" is decided by diffing `getDataForSave()` against a baseline, so it covers **blocks,
title and every module** (categories, authors, tags, status, SEO …) and any module you add
later — a change to any of them is caught. The baseline is the editor's own serialization
captured after load (not `initialContent`, which is a different shape and wouldn't match), and
it re-captures on `afterSave`, so a saved document reloads without a prompt. Reverting an edit
back to the saved state clears the warning — it's an equality check, not a sticky flag.

The prompt's wording is fixed by the browser and can't be customised.

**Suppressing it for an intentional navigation.** Any programmatic navigation you trigger
yourself — a redirect after save, a "back to list" button — would otherwise be caught by the
guard just like a stray tab close. Call `editor.unsavedGuard.setEnabled(false)` before you
navigate:

```js
editor.eventEmitter.on('afterSave', () => {
    editor.unsavedGuard.setEnabled(false);   // this redirect is deliberate — don't warn
    window.location.href = '/posts';
});
```

`setEnabled(false)` only silences the prompt; it doesn't reset the baseline, so if the
navigation is cancelled or deferred, re-enable it and the dirty state is exactly as it was:

```js
editor.unsavedGuard.setEnabled(true);
```

You rarely need `false` for the built-in save — that path is in-page and re-baselines on
`afterSave`, so the document is already clean by the time any prompt could fire. It's only for
*your own* navigation away from the editor.

**Runtime API.** `config.unsavedGuard` only chooses the starting state; everything is
adjustable afterwards on `editor.unsavedGuard`:

| Method | Description |
| --- | --- |
| `setEnabled(enabled)` | Silence or restore the prompt. The baseline keeps updating either way, so re-enabling reflects the true dirty state rather than a stale one. |
| `isEnabled()` | Whether the prompt is currently armed. |
| `isDirty()` | Whether the document differs from the baseline. `false` until the first baseline is captured. |
| `markClean()` | Treat the current state as saved — re-baselines immediately. |

`markClean()` is for saves the editor doesn't know about. The guard re-baselines on `afterSave`,
so the built-in save needs nothing; but an app that posts through its own endpoint, restores a
draft, or swaps content in programmatically would otherwise stay dirty against a baseline the
backend no longer holds:

```js
document.getElementById('myCustomSave').addEventListener('click', async () => {
    await fetch('/api/posts/1', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(editor.getDataForSave()),
    });
    editor.unsavedGuard.markClean();   // the backend now holds this — stop warning about it
});
```

`isDirty()` also covers the case `beforeunload` can't see: **in-app navigation**. A single-page
app changing route never unloads the document, so no browser prompt fires. Ask the guard
yourself and use whatever confirmation UI you already have:

```js
router.beforeEach((to, from, next) => {
    if (editor.unsavedGuard.isDirty() && !window.confirm('Discard unsaved changes?')) {
        next(false);
        return;
    }
    next();
});
```

---

## Read-only

### The whole editor at once

To freeze **everything** — blocks, the Save button, and every module — use one switch instead of
toggling each part. Either start from config, or call `setReadOnly` before `init()`:

```js
// From config:
const contentEditor = new ContentEditor({ config: { readOnly: true, /* … */ }, initialContent });

// …or programmatically, any time before init():
const contentEditor = new ContentEditor({ config, initialContent });
contentEditor.setReadOnly(true);

await contentEditor.init();
```

`contentEditor.isReadOnly()` reports the current state, and `setReadOnly` returns the editor so
it chains. Applied at (or before) `init()`, every part picks the state up as it builds — blocks
render uneditable, modules wire their guards, the Save button disables. A custom module or plugin
can react to the state via the `readOnlyChanged` event.

> Calling `setReadOnly` **after** `init()` flips every part and emits `readOnlyChanged`, but
> live re-application to already-rendered blocks and modules is a separate, in-progress piece —
> the pre-init path above is the fully-supported one.

### Per part

Read-only can also be applied **per part** — so you can freeze the blocks but leave SEO editable,
or lock the taxonomy while the title stays writable. Call `setReadOnly(true)` on whichever pieces
the current user shouldn't change:

```js
const contentEditor = new ContentEditor({config, initialContent});

// Must be before init() — see below.
contentEditor.blockHandler.setReadOnly(true);          // the canvas: blocks, inserter, overview
contentEditor.saveHandler.setReadOnly(true);           // disables the Save button

contentEditor.getModule('title')?.setReadOnly(true);
contentEditor.getModule('slug')?.setReadOnly(true);
contentEditor.getModule('categories')?.setReadOnly(true);
contentEditor.getModule('authors')?.setReadOnly(true);
contentEditor.getModule('tags')?.setReadOnly(true);
contentEditor.getModule('featuredImage')?.setReadOnly(true);
contentEditor.getModule('status')?.setReadOnly(true);
contentEditor.getModule('seo')?.setReadOnly(true);
contentEditor.getModule('revisions')?.setReadOnly(true);
// …and any module you registered yourself
contentEditor.getModule('allowComments')?.setReadOnly(true);

await contentEditor.init();
```

`getModule()` returns `null` for a module that isn't enabled in `config.modules`, hence the `?.`.

> **Set it before `init()`.** Read-only is a setup-time decision, not a runtime toggle.
> `blockHandler.setReadOnly()` must be called before `init()` because `loadBlockModules()`
> constructs the **block inserter** and the **overview** with the value it has at that moment —
> set it afterwards and those two stay interactive (the content is still protected, but their
> controls would look live and do nothing). Modules read the flag in their own `init()`, which
> `ContentEditor.init()` calls, so the same rule applies to them.

Setting `blockHandler` read-only covers the whole canvas: blocks don't take new content, the
inserter doesn't bind its listeners, and overview rows lose their drag handles and
insert/duplicate/delete options.

---

## Translation

Every string the editor puts on screen goes through `Translator.translate()`, so the whole UI —
block names and descriptions, sidebar labels, menu items, tooltips, placeholders, toasts — can be
shown in another language without touching the editor.

Do nothing and everything stays in English: with no catalogue set, `translate()` returns what it
was given.

### Setting it up

Configure the translator **before `init()`**, then build the editor as usual:

```js
import ContentEditor from './ContentEditor/ContentEditor.js';
import Translator from './Translator/Translator.js';

Translator.setTranslations({
    'Paragraph':      {sr: 'Pasus', de: 'Absatz'},
    'Choose Images':  {sr: 'Izaberite slike', de: 'Bilder auswählen'},
    'Advanced':       {sr: 'Napredno', de: 'Erweitert'},
    'Add Block':      {sr: 'Dodaj blok', de: 'Block hinzufügen'},
});
Translator.setLanguage('sr');

const editor = new ContentEditor({
    config: {
        blocks: ['core/paragraph', 'core/heading', 'core/image', 'core/gallery'],
        modules: ['title', 'slug', 'categories', 'status'],
    },
    initialContent: {},
});
await editor.init();
```

The **key is the English string exactly as the editor writes it** — `'Choose Images'`, not
`'choose images'`. A key that isn't in the catalogue falls through unchanged, so a partial
catalogue is fine: translate what you have and the rest stays English.

`Translator` is a singleton shared with the rest of the library, so one catalogue covers the
editor and any other skeletorjs component on the page.

### Strings with a value in them

A sentence with something interpolated is stored as a whole template with a `%s` (or `%n`)
placeholder, because word order moves between languages and a sentence glued together from
fragments can't be translated properly:

```js
Translator.setTranslations({
    "Type '/' for blocks or '%s' for commands": {sr: "Ukucajte '/' za blokove ili '%s' za komande"},
    'Copied "%s" to clipboard':                 {sr: 'Kopirano "%s" u ostavu'},
    'Copied %n blocks to clipboard':            {sr: 'Kopirano %n blokova u ostavu'},
    'This revision — %s':                       {sr: 'Ova revizija — %s'},
});
```

The value is substituted **after** the lookup, so it survives translation.

### Your own blocks and commands

Definitions keep plain English literals — `static label`, `static description`, a registered
command's `label`, a sidebar control's `label`. Translation happens where the editor renders
them, which means anything you register is translated too, as long as its label is in the
catalogue:

```js
class Callout extends Block {
    static label = 'Callout';                       // plain literal…
    static description = 'A highlighted aside.';
}

Translator.setTranslations({
    'Callout': {sr: 'Isticanje'},                   // …translated when it's rendered
    'A highlighted aside.': {sr: 'Istaknuta napomena.'},
});
```

This is also why the literals stay put: a module-level `const` or a `static` field is evaluated
when the file is imported, which for the core modules is **before** your bootstrap code runs.
Translating at that point would freeze every label in the base language no matter what you set
afterwards.

Search matches both languages — the block inserter, the `//` menu and the Ctrl+K palette all
search the translated label *and* the original — so an English-speaking editor and a Serbian one
can both find "Gallery" by typing what they expect.

### What isn't covered

Text that lives in your own HTML shell (the Save button, sidebar section headings you wrote
yourself, the status dropdown's options) is yours to translate — the editor never wrote it. The
one exception is the title field's `data-placeholder`, which is read from markup and then passed
through `translate()`, so a single English attribute is enough.

---



## API reference

### `ContentEditor`

| Member | Description |
| --- | --- |
| `new ContentEditor({ config, initialContent })` | Construct. Nothing touches the DOM yet. |
| `await init()` | Async. Loads block modules, builds the UI, renders `initialContent`. |
| `destroy()` | Tears down all handlers, modules, and listeners. |
| `getDataForSave()` | The save payload (`{ blocks, ...moduleValues }`). |
| `getModule(name)` | The module instance, or `null`. |
| `static registerModule(name, definition)` | Register a project module before construction. |
| `unsavedGuard.setEnabled(enabled)` | Turn the unsaved-changes prompt off/on. Pass `false` before a deliberate navigation away (see [Save & load](#save--load)); the baseline is untouched, so `true` restores the prior state. |
| `unsavedGuard.isEnabled()` | Whether the prompt is currently armed. |
| `unsavedGuard.isDirty()` | Whether the document differs from the last baseline — use it to guard in-app navigation, which `beforeunload` never sees. |
| `unsavedGuard.markClean()` | Re-baseline now. For saves made outside the editor's own save path. |
| `.blockHandler` | The block manager (see below). |
| `.shortcutsHandler` | `registerShortcut(shortcut)` — add a keyboard shortcut. |
| `.eventEmitter` | The shared `EventEmitter` (see below). |

### Messages — `notify()`, `message()`, `clearMessages()`

Put something on screen using the same surface the editor uses for its own messages, without
reaching for `Message` or hunting down the container.

```js
editor.notify('Draft saved');                                   // transient toast, 3s
editor.notify('Could not reach the server', {type: 'error', timeout: 6000});
editor.message('Category is required');                          // stays until cleared
editor.message(['Title is required', 'Category is required']);   // one per entry
editor.clearMessages();                                          // all of them
editor.clearMessages('error');                                   // just that type
```

There's also one method per type, which is what you'll reach for most:

```js
editor.info('Restored from an autosave');
editor.success('Draft saved');
editor.warning('That headline is over the limit');
editor.error('Category is required');
```

| Method | View | Stays? |
| --- | --- | --- |
| `info(text, options)` | notification | no — 3000 ms |
| `success(text, options)` | notification | no — 3000 ms |
| `warning(text, options)` | static | yes |
| `error(text, options)` | static | yes |
| `notify(text, {type, timeout, prepend})` | notification | no — `timeout` ms, default 3000 |
| `message(text, {type, prepend})` | static | yes |
| `clearMessages(type?)` | — | removes all, or one `Message.TYPES` value |

**The default view differs by type deliberately.** Something informational has been read by the
time it fades; a warning or an error is asking for a change, and one that disappears before it's
acted on may as well not have been shown.

Override it per call — both directions work, and `timeout` applies to either view, so a static
message can self-dismiss and a toast can be made permanent:

```js
editor.error('Upload failed', {view: 'notification'});      // a toast after all
editor.success('Published', {view: 'static'});              // keep it on screen
editor.warning('Slow connection', {timeout: 8000});         // static, but gone in 8s
editor.info('Read-only mode', {timeout: null});             // toast that never leaves
```

`text` may be a string or an array. `type` is one of `Message.TYPES` — `'info'`, `'success'`,
`'warning'`, `'error'`. All three return `this`.

This is the same call the editor makes itself: a failed save validation is
`editor.message(failed.getMessages())`, nothing more. So a project's messages and the editor's
look and behave identically by construction rather than by convention.

Text is passed through the `Translator` like every other user-facing string, so a catalogue
covers these too — see [Translation](#translation).

> **The text is inserted as HTML.** `Message.spawn` assigns it with `innerHTML`, which is what
> lets a message carry `<strong>` or a link. It also means **never pass user-derived text
> straight in** — escape it first (`Revisions.escape()` does the job) or you have an injection
> point. Developer-authored strings are fine; anything from a post, a form or an API is not.

Called before `init()` there is no container yet, so it warns and does nothing rather than
silently dropping the message.

### Block defaults

`config.blockDefaults` gives a block type its starting values for this project:

```js
config: {
    blockDefaults: {
        'core/image': {align: 'center'},
        'core/divider': {height: 4, color: '#b30000'},
        'core/gallery': {options: {layout: 'masonry', gap: 16, captions: false}},
        'core/accordion': {settings: {allowMultiple: false, firstItemOpen: true}},
    },
}
```

The image case is worth pointing at, because `align` isn't part of the block's own data — it
lives on the `data-align` attribute. The defaults are merged into the payload *before*
`renderBlock` reads `data.align` to set that attribute, so a default reaches it exactly as a
saved value would. Anything `renderBlock` takes from the payload can be defaulted this way.

They are merged **under** the block's own data, so anything the payload already carries wins.
That single rule covers every case without the editor having to tell a new block from a loaded
one:

| Case | Result |
| --- | --- |
| A block inserted now | carries nothing, so it takes the defaults whole |
| A duplicated or pasted block | keeps the values it was copied from |
| A saved block | keeps everything it has, and only picks up keys it never had |

**That last row cuts both ways.** A divider saved before anyone chose a height will follow the
project default rather than the library's — which is usually what you want, and is why changing
a default reaches old content. But it *does* reach old content, so set one knowing that.

The merge is **shallow**. A saved `options` object replaces the default outright rather than
being combined with it, because a deep merge would have to decide what to do with arrays, and
"combine the default images with the saved ones" is never what anyone means. Blocks that have
their own internal option defaults (Gallery does) still apply those underneath.

**A key the block doesn't recognise is ignored**, not an error — no block spreads its input into
`getData()`, so a typo is dropped rather than saved. A misspelled *block name* is a silent no-op
for the same reason. Both fail quietly, so check the spelling if a default seems to do nothing.

**`id` is the exception and is stripped**, with a warning. `renderBlock` only mints an id when the
payload lacks one, so defaulting it would give every block of that type the same identity — and
`blocks` is a Map keyed by id, so they would displace each other. Ids are also what revisions match
on. Other keys `renderBlock` reads off the payload are fine to default: `align` is the documented
image case, and `html` is a reasonable "new paragraphs start with this".

### `editor.blockHandler` — the block manager

| Member | Description |
| --- | --- |
| `renderBlock(name, data, referenceElement=null, position='end', focus=true, container=#content)` | Create + insert a block; returns the instance (or `null` if `name` isn't registered). `referenceElement` is the anchor DOM element for `'before'`/`'after'` (e.g. `someBlock.getContainer()`); `position`: `'end' \| 'prepend' \| 'before' \| 'after'`; `container` is where `'end'`/`'prepend'` land. |
| `getBlockData()` | Array of saved blocks, in DOM order (recurses into containers). |
| `focusBlock(id)` | Focus one block by id. Every block's `focus()` ends in an element `focus()`, so the platform scrolls it into view — nothing here does that separately. Returns `false` if no block holds that id, so a caller jumping to a block (a validation message pointing at the offending one, a deep link, the overview) can tell rather than fail silently. Focus goes through the block's own `focus()`, so the active block, side toggle and sidebar follow as they would for a click. |
| `focusFirstBlock()` | Focus the first non-hidden block. Returns whether it found one. |
| `getBlocks({nested = false, container = #content})` | The blocks in **document order** (the DOM holds the order; `.blocks` is a Map and keeps insertion order, which moving a block never changes). `nested: true` flattens container-block children in — use it for "every image in this post"; leave it off for structure. `container` scopes it, e.g. a column element for that column's blocks. |
| `getBlockAt(index, container = #content)` | The block at a position, or `null`. Top level only — a position only means something inside one container, so index 2 of the canvas and index 2 of a column are different blocks. |
| `getBlock(id)` | One block by id, or `null`. Coerces the id to a string, since ids round-trip through a DOM attribute and a numeric `1` would never match the entry stored under `"1"`. |

| `getBlockIndex(blockOrId)` | Where a block sits **within its own container**, or `-1`. A block in a column reports its position in that column, not on the canvas — a global index would have to pick an answer for nested blocks, and every answer is wrong for half the callers. |
| `getParentBlock(blockOrId)` | The container block it sits inside (the `Columns` owning its column), or `null` at the top level. The search starts from the parent, so a block is never reported as its own. |
| `moveBlock(blockOrId, index, container, {focus = true})` | Move a block, optionally into another container. **`container` defaults to the block's own, not the canvas** — so a block inside a column reorders *within that column*; moving it out means passing `blockHandler.contentContainer` explicitly. `index` counts the container's blocks **excluding the one moving**, so index 0 puts it first whether it started above or below. The moved block is refocused, which is also what repositions the side toggle — pass `{focus: false}` for a bulk reorder. Emits `blockMoved`, the same event a drag emits, because the overview rebuilds on that rather than on `contentChanged`. Returns `false` for an unknown block, a move into itself, or a container block into a column (containers don't nest). |
| `getBlockCount()` | **Every** block, nested ones included — it reads `.blocks.size`, and `renderBlock` registers container children in the same map. For top level only, use the `Blocks.getTopLevelBlockCountFromDom()` static. |
| `undo()` / `redo()` | History. |
| `insertFootnote()` | Insert a footnote at the caret (if `core/footnotes` is enabled). |
| `runWithoutObserving(fn)` | Run a DOM-mutating routine without it counting as an edit — see below. |
| `.blocks` | `Map<id, blockInstance>`. |
| `.activeBlock` | The focused block instance (or `null`). |
| `.contentContainer` | The `#content` element. |

**Editing the DOM directly.** A `MutationObserver` on `#content` watches `childList`,
`characterData` and the attributes `href`, `target`, `rel`, `src`, `alt`, and emits a debounced
`contentChanged` — that's what drives history, the unsaved guard, and the overview. So *any*
script that touches block DOM registers as a user edit:

```js
// this fires contentChanged → a history entry, and marks the document dirty
editor.blockHandler.contentContainer.querySelectorAll('a')
    .forEach((a) => { a.rel = 'noopener'; });
```

Wrap it to make the change invisible to the editor:

```js
editor.blockHandler.runWithoutObserving(() => {
    editor.blockHandler.contentContainer.querySelectorAll('a')
        .forEach((a) => { a.rel = 'noopener'; });
});
```

It disconnects the observer, **drops any already-pending debounced event**, runs `fn`, and
re-observes in a `finally` — so a throw can't leave the editor permanently deaf to changes.
(It's a callback rather than `pause()`/`resume()` precisely so you can't forget to resume.)

Which one you want depends on intent: **normalising content on load** should be silent, but a
**user-initiated bulk action** should *not* be wrapped — let `contentChanged` fire so the change
is undoable and the document is correctly marked dirty.

### `Blocks/Block.js` — base class for blocks

**`this.config`** — the editor's whole config (`config.contentEditor`), on every block and
every module. Read the key you own:

```js
const palette = this.config.myProject?.palette;
```

It is the whole object rather than a slice, so the editor never has to know what each class
wants. It is `{}` when the class is built outside a full editor.

> **Do not declare a pass-through constructor.** `constructor({data, id, eventEmitter}) {
> super({data, id, eventEmitter}); }` is what JavaScript already does for free, and it
> *drops* anything the base class gains later — `config` included. If you need a constructor,
> forward the whole bag: `constructor(options) { super(options); }`.


Statics to declare: `label`, `keywords`, `icon` (SVG string), `isText`, `name`
(`'core/x'` — shadows `Function.name`), `category`, `description`; optional `tags` (HTML tags
that paste into this block) and the system-block flags below.

- **`hidden`** — a managed/system block (footnotes, the unknown-block placeholder): kept out of
  the inserter, slash menu, overview and multi-selection, and never duplicated by hand. By
  default it also can't be deleted.
- **`deletable`** — for a `hidden` block, set `true` to let the user delete it anyway (the
  unknown-block placeholder does this). Deletion is gated by `canBeDeleted()` =
  `!hidden || deletable`, so a normal block is always deletable and this flag only matters
  alongside `hidden`.
- **`advancedSidebarOpen`** — whether the built-in **Advanced** section starts expanded
  (default `true`). A block that adds a settings section of its own should set this to `false`, so
  its own controls are what you see first and Advanced sits collapsed beneath them — see
  [Adding a block](#adding-a-block).

### Adding sidebar controls to blocks you don't own

`Block.registerSidebarControl(definition)` adds a control to **every** block's sidebar (or to
named types), without touching core or subclassing anything. Register before constructing the
editor, like the other registries.

```js
import Block from '.../Blocks/Block.js';

// checkbox — every block, inside Advanced. `default` applies until the user changes it.
Block.registerSidebarControl({
    key: 'showOnMobile',        // → additionalData.showOnMobile  (boolean)
    label: 'Show on mobile',
    type: 'checkbox',
    default: true,
});

// text — the default type, so `type` can be omitted.
Block.registerSidebarControl({
    key: 'trackingId',          // → additionalData.trackingId  (string)
    label: 'Tracking ID',
});

// textarea — for longer values.
Block.registerSidebarControl({
    key: 'schemaJson',          // → additionalData.schemaJson  (string)
    label: 'Schema.org JSON',
    type: 'textarea',
});

// select — `options` is [{value, label}]; `label` falls back to `value` if omitted.
// The stored value is always the option's `value` (a string).
Block.registerSidebarControl({
    key: 'width',               // → additionalData.width  ('default' | 'wide' | 'full')
    label: 'Width',
    type: 'select',
    default: 'default',
    options: [
        {value: 'default', label: 'Content width'},
        {value: 'wide',    label: 'Wide'},
        {value: 'full',    label: 'Full bleed'},
    ],
});

// `blocks` limits it to named types; `section` puts it in its own collapsible section
// (rendered above Advanced) instead of inside Advanced.
Block.registerSidebarControl({
    key: 'visibleFrom',
    label: 'Visible from',
    type: 'text',
    blocks: ['core/image', 'core/table'],
    section: 'Visibility',
});

// Two controls sharing a `section` name are grouped into that one section.
Block.registerSidebarControl({
    key: 'visibleUntil',
    label: 'Visible until',
    type: 'text',
    blocks: ['core/image', 'core/table'],
    section: 'Visibility',
});
```

**`render(block, {listen, value})` — the escape hatch.** For anything the built-in types can't
express, return your own element. You own the markup *and* the wiring: write the value back to
`block.sidebarData[key]` yourself. `type` and `options` are ignored — but **`default` is not**,
so declare it here too (see the two helpers below).

- **`value`** — the stored value, or your declared `default` if there's none yet. Use this
  instead of reading `block.sidebarData[key]` by hand, so the default lives in one place.
- **`listen(target, event, handler)`** — use this instead of `addEventListener`. It registers
  into the same list the built-in controls use, so your listeners are removed on
  `destroySidebar()` automatically. The sidebar is rebuilt every time a block takes focus, so
  an untracked listener accumulates.

```js
Block.registerSidebarControl({
    key: 'opacity',
    blocks: ['core/image'],
    section: 'Appearance',
    default: 100,               // seeds additionalData *and* arrives below as `value`
    render: (block, {listen, value}) => {
        const container = document.createElement('div');
        container.classList.add('inputContainer');

        const label = document.createElement('label');
        label.textContent = 'Opacity';

        const input = document.createElement('input');
        input.type = 'range';
        input.min = '0';
        input.max = '100';
        input.value = value;    // stored value, or the default above

        // `listen`, not addEventListener — this one is tracked and auto-removed.
        listen(input, 'input', () => {
            block.sidebarData.opacity = Number(input.value);   // …write it back yourself
        });

        container.append(label, input);
        return container;
    },
});
```

Declaring `default` matters for the same reason it does on the built-in types: without one,
the key is **absent from `additionalData` until the user touches the control**, and your
renderer has to guess what "missing" means.

Note `render` is also the only way to store a **non-string** value (the range above saves a
number) — the built-in `text`/`textarea`/`select` types always write strings, and `checkbox`
always writes a boolean.

**`destroy(block, element)`** — optional, for cleanup that isn't a listener (a timer, a
`MutationObserver`, a third-party widget). Called with the element `render` returned, when the
sidebar is torn down:

```js
Block.registerSidebarControl({
    key: 'preview',
    render: (block, {listen}) => { /* … */ },
    destroy: (block, element) => {
        clearInterval(element.dataset.timerId);
    },
});
```

| Field | Description |
| --- | --- |
| `key` | **Required.** The `additionalData` key it reads and writes. Registering the same key twice replaces. |
| `label` | Shown next to the control (defaults to `key`). |
| `type` | `checkbox` \| `text` \| `textarea` \| `select` (default `text`). |
| `default` | Used when the block has no stored value. **Also written into `additionalData` on save**, so the key is present before the control is ever touched. Applies to `render` controls too (arrives as `value`). |
| `options` | `select` only: `[{value, label}]`. |
| `blocks` | Whitelist of block type names. Omit for all blocks. |
| `excludeBlocks` | Blacklist — all blocks *except* these. Combines with `blocks` (whitelist first, then this removes from it). |
| `showOnHidden` | `true` to also show on hidden/system blocks (footnotes), which are skipped by default. (`showOnUnknown` has no effect here — see below.) |
| `section` | Group into its own collapsible section (placed above Advanced). Omit to render inside Advanced. |
| `render(block, {listen, value})` | Escape hatch: return your own element and do your own wiring (write to `block.sidebarData`). `value` is the stored value or `default`; bind events with `listen` so they're auto-removed. |
| `destroy(block, element)` | Optional cleanup for a `render` control — timers, observers. Listeners bound via `listen` are already handled. |

**It persists for free.** A control writes to `sidebarData`, which *is* `additionalData` — so
the value round-trips through save and load with no extra plumbing, and appears in
`getDataForSave()` under the block's `additionalData`. Listeners are torn down with the sidebar
automatically. Use `Block.unRegisterSidebarControl(key)` to remove one.

A stored `false` or `''` beats the `default` (the lookup is `??`, not `||`), so a checkbox the
user unticked stays unticked.

> The editor only **stores** these values. Acting on them — e.g. actually hiding a block on
> mobile — is your frontend renderer's job.

| Instance member | Provided by base? | Description |
| --- | --- | --- |
| `constructor({ data, id, eventEmitter })` | ✓ | `this.data`, `this.id` (the block's stable, saved identity), `this.eventEmitter` set for you. |
| `render()` | you implement | Build + return the root element. |
| `getContainer()` | you implement | Return the root element. |
| `focus()` | you implement | Focus the block. |
| `getData()` | you implement | Return this block's save slice (object). |
| `destroy()` | you override | Remove listeners/DOM, then call `super.destroy()` (emits `blockDeleted`). |
| `getChildContainers()` | container blocks only | Return the child container elements (Columns). |
| `setContent(html)` | ✓ | Sets `this.element.innerHTML` (text blocks). |
| `getBlockData()` | ✓ | `{ ...getData(), type, id, additionalData }`. |
| `renderSidebarContent()` | ✓ (override to extend) | The block's sidebar; base adds the "Advanced" section (CSS classes / HTML id / inline CSS → `this.sidebarData` → `additionalData`). |
| `destroySidebar()` | ✓ (override to extend) | Tear down the sidebar. |
| `renderBLockMenu(textElement)` | ✓ | Open the slash menu for a text block. |
| `this.sidebarData` | ✓ | Persisted per-block settings (saved as `additionalData`). |

### Recipe — showing a block's state on the block itself

A registered control *stores* a value, but nothing about it is visible until you select that
block and open the sidebar. This recipe adds the missing half: an **editorial note** on any
block, plus a marker on the block itself so you can see at a glance which blocks carry one.

It generalises to any per-block state you want visible in the content — needs-review, locked,
paywall boundary, wire-service attribution.

**Why the control alone can't do it.** `render(block, …)` *is* handed the block, so it can reach
`block.getContainer()`. But it runs only for the block that currently has focus, and it's torn
down on `destroySidebar()` — the sidebar is rebuilt on every selection. Every other block in the
document never gets a chance to draw itself. So the marker needs two hooks:

| Hook | Covers |
| --- | --- |
| `blockInserted` | every block view that comes into existence, whatever created it |
| the control's own `listen` handler | the block being edited, as you type — no new view, so no event |

**`blockInserted` is the universal one.** Every path that creates a block goes through
`renderBlock()`, which emits it unconditionally: the initial content load, paste, duplicate, the
inserter, the gap inserter, Enter, and the rebuild half of undo / redo / revision revert. Column
children come through it recursively, so they're covered too. It fires after the view is in the
DOM and before the block takes focus.

Undo and revert are worth spelling out, because they're where a naive decorator breaks. Restoring
content reconciles rather than re-renders: a block whose data didn't change keeps its element, so
your attribute is still on it, and a block that *was* rebuilt comes back through `renderBlock`
and re-emits. Either way the marker ends up correct without you tracking it.

The second hook can't be generalised — only the code that changes a value knows it changed.

**Mark with an attribute, never an injected element.** This is the part that isn't obvious. The
content observer watches `childList`, `characterData` and `subtree`, but for attributes it
listens to `href`, `target`, `rel`, `src` and `alt` only. So:

- appending a marker `<span>` fires `childList` → `contentChanged` → a history entry **and** a
  false unsaved-changes warning, every time a marker is drawn;
- setting `data-editorial-note` is an attribute outside that filter, so the observer never sees
  it. Free.

A custom attribute also can't leak into the save payload: a block's `getData()` returns explicit
fields, and the only DOM attribute read back out is `data-align`. The value lives in
`additionalData`; the attribute is only a *rendering* of it, reapplied on load.

If you ever do need a real element in the content, `editor.blockHandler.runWithoutObserving(fn)`
is public and exists for exactly that.

```js
import ContentEditor from './ContentEditor/ContentEditor.js';
import Block from './ContentEditor/Blocks/Block.js';
import {events as blockEvents} from './ContentEditor/Blocks/events.js';

const NOTE_KEY = 'editorialNote';
const NOTE_ATTRIBUTE = 'data-editorial-note';

// Reflect the stored note onto the block's root. An attribute, not an appended icon: the
// content observer's attributeFilter doesn't include this one, so marking a block is invisible
// to it — no history entry, no false dirty state. The attribute is never serialized, so it is
// reapplied every time a block view is created, which is what `blockInserted` below is for.
function reflectNote(block) {
    const root = block.getContainer();
    if (!root) {
        return;
    }
    const note = String((block.sidebarData || {})[NOTE_KEY] || '').trim();
    note ? root.setAttribute(NOTE_ATTRIBUTE, note) : root.removeAttribute(NOTE_ATTRIBUTE);
}

// 1. The control. `render` rather than `type: 'textarea'` for one reason: it lets us reflect the
//    marker as the user types. A built-in type writes to sidebarData but gives us no hook.
//    No `default` is declared on purpose — see the note below.
Block.registerSidebarControl({
    key: NOTE_KEY,              // → additionalData.editorialNote, so it round-trips on save
    section: 'Review',          // its own collapsible section, above Advanced
    render: (block, {listen, value}) => {
        const container = document.createElement('div');
        container.classList.add('inputContainer');

        const label = document.createElement('label');
        label.textContent = 'Editorial note';

        const input = document.createElement('textarea');
        input.classList.add('input');
        input.rows = 3;
        input.value = value ?? '';

        // `listen`, not addEventListener — tracked, and removed with the sidebar.
        listen(input, 'input', () => {
            block.sidebarData[NOTE_KEY] = input.value;   // a render control writes its own value
            reflectNote(block);                          // marker appears/disappears live
        });

        container.append(label, input);
        return container;
    },
});

const editor = new ContentEditor({
    config: {
        blocks: ['core/paragraph', 'core/heading', 'core/image', 'core/gallery'],
        modules: ['title', 'slug', 'categories', 'status'],
    },
    initialContent: {},
});

// 2. Every block view, from every path. `blockInserted` emits the block object itself, so the
//    listener is the reflect function unchanged.
//
//    Subscribe BEFORE init(): `eventEmitter` exists as soon as the editor is constructed, while
//    the initial content is rendered inside init() — so this catches the blocks already in the
//    post as well as everything added later. Subscribe after init() and the initial ones are
//    silently missed. It is still early enough that the markers are in place before
//    `contentEditorFinalize`, where UnsavedGuard and History take their baselines.
editor.eventEmitter.on(blockEvents.blockInserted, reflectNote);

await editor.init();
```

The appearance is entirely CSS, and because `attr()` can read the attribute you get a hover
tooltip without any extra JavaScript:

```scss
#content [data-editorial-note] {
  position: relative;

  // The marker sits in the LEFT gutter: the right-hand side is where #blockSideToggle appears
  // on the focused block.
  &::after {
    content: '\1F4DD';
    position: absolute;
    top: 0;
    left: -1.75rem;
    font-size: 0.9rem;
    opacity: 0.7;
  }

  &:hover::before {
    content: attr(data-editorial-note);
    position: absolute;
    top: 1.5rem;
    left: -1.75rem;
    z-index: 5;
    max-width: 220px;
    padding: 0.4rem 0.6rem;
    border-radius: 4px;
    background: var(--colorSurface-700);
    color: var(--colorSurface-100);
    font-size: 0.8rem;
    white-space: pre-wrap;
  }
}
```

Four things worth knowing:

- **Don't declare a `default`.** A `default` is written into every applicable block's
  `additionalData` on save even if nobody opened the sidebar (that's deliberate — see the table
  above). Declare one here and every block in every post carries `editorialNote: ''`. Leaving it
  undefined lets absence mean "no note".
- **`additionalData` travels with the content.** The note is in the saved post, so it reaches
  anywhere the post JSON goes, including a public API. Strip it on your read path unless you're
  happy publishing internal editorial notes.
- **Pseudo-elements are safe inside a `contenteditable`.** They aren't DOM nodes, so they never
  appear in `textContent`, in `getData()`, or in a copied selection.
- **This is per-block state, not a conversation.** It belongs in `additionalData` precisely
  because it describes the block: a revision revert restores the note along with the content it
  was about, and undo takes it back. Threaded comments between people are the opposite case —
  they need their own store, or a revert would resurrect resolved discussions and undo would
  delete someone's message.

### Built-in block settings

Most blocks save only their content. These also carry settings, edited in a section of their own
above **Advanced** — except the quote's citation, which is edited in the block itself.

| Block | Field | Where it's edited |
| --- | --- | --- |
| `core/image` | `align` — `'left' \| 'center' \| 'right'`, or absent | Image section |
| | `link` — `{href, newTab, rel}`, or `null` | Image section |
| `core/quote` | `cite` — plain text | **in the block**, under the quote |
| `core/divider` | `color` — hex, or `''` for the theme's own | Divider section |
| | `height` — px, 1–20 | Divider section |
| `core/accordion` | `settings.allowMultiple` — more than one section open at once | Accordion section |
| | `settings.firstItemOpen` — open the first section when none are | Accordion section |
| `core/spacer` | `height` — px, 24–300 | Spacer section |
| `core/gallery` | `options` — layout, gap, captions, lightbox, … | Gallery section |
| `core/table` | `settings` — colours, search, sort, filters | Table sections |
| `core/columns` | `layout` — e.g. `'33-66'` | Layout select |

Three of these are worth a note.

**`core/image` — `align` is not part of the block's data.** It lives on the block's `data-align`
attribute, which `Block.getBlockData()` reads and `renderBlock()` restores — the same plumbing
the format toolbar uses for text alignment. So in the payload it sits at the top level next to
`type` and `id`, not inside the block's own fields, and `Image.getData()` deliberately doesn't
return it. "None" removes the attribute rather than storing an empty string.

**`core/quote` — the citation is in the editor, not the sidebar.** A citation is *about* the
quote rather than part of it, so the block's root is a `<figure>` holding an editable
`<blockquote>` and an editable `<cite>`; keeping the attribution inside the blockquote would put
it into the quoted text and into the saved `html`. It's stored as plain text, and it is
deliberately not given the editable class the format toolbar looks for — an attribution is a
name, not rich text.

**`core/divider` — the settings are written as CSS custom properties**, `--dividerColor` and
`--dividerHeight`, rather than as inline `height` and `background`. That keeps the stylesheet in
charge of how a rule is actually drawn. An empty `color` removes the property entirely so the
theme's own value wins, which is what the "Use theme colour" button restores.

> These are **stored**, not rendered. Making an image's alignment or link do something, showing
> the citation, honouring the accordion's settings and drawing the divider's colour are all your
> frontend renderer's job — the editor only saves the intent.

### `BaseModule.js` — base class for modules

`new Module({ eventEmitter, config })` · `this.eventEmitter` · `this.config` ·
`this.readOnly` · `setReadOnly(v)` · `isReadOnly()` · `destroy()`. By convention a module implements
`init()` and `getData()` / `setData(value)` (or supplies `getData`/`setData` adapters in the
registry entry).

`this.config` is the editor's whole config — see the note under
[`Block.js`](#blocksblockjs--base-class-for-blocks); the same pass-through-constructor
warning applies to modules.

### `EventEmitter` (`src/EventEmitter`)

`on(name, callback)` · `emit(name, data)` · `remove(name, callback?)` · `destroy()`.
Synchronous, registration-ordered, re-entrant. **`remove(name)` with no callback drops ALL
listeners for that event** — always pass the callback to remove just yours.

### `Shortcut` (`src/Shortcuts/Shortcut.js`)

```js
new Shortcut({
    container,                    // fires only when it contains document.activeElement (or is .active)
    modifier: Shortcut.MODIFIERS.CTRL,  // ALT | SHIFT | CTRL (one modifier only)
    key: 'S',
    event: 'keyup',              // or 'keydown'
    preventDefault: false,
    description: 'Save',
    callback: () => {},
    constraints: [() => true],   // all must return true for the shortcut to fire
});
editor.shortcutsHandler.registerShortcut(shortcut);
```

### `Dismissible` (`Dismissible/Dismissible.js`)

A registry that gives every modal and panel **Escape-to-close** from one place — SEO,
Shortcuts, Revisions, the block inserter and the overview all use it, and a new one joins
with a single `register()` call instead of its own key handling.

```js
// in the panel's init()
this.dismissible = Dismissible.register({
    isOpen: () => this.container.classList.contains(contentEditorSelectors.classes.active),
    close:  () => this.#close(),          // your own close logic — nothing to rename
});

// in destroy()
Dismissible.unregister(this.dismissible);
```

One `document` keydown listener (installed on the first `register`) closes whatever is open
when Escape is pressed. It runs on the **bubble phase** and only calls `stopPropagation` when
it actually closed something — so more specific handlers that stop the event first (the slash
menu, entity search, the link modal, the block-selection Escape) always win, and an Escape
that closes nothing stays transparent. A `close()` may unregister freely; a throwing
`isOpen()` (a panel caught mid-teardown) is treated as closed.

### `SidebarSection` (`Sidebar/SidebarSection/SidebarSection.js`)

`SidebarSection.generate(label, id, eventEmitter, active=false)` → an instance with a
`.container` (append it into the block sidebar) and `.destroy()`. Content goes in the inner
`#<id>` element. Used by blocks to add collapsible sidebar sections.

### `BlockSideToggle.registerAction(definition)`

Adds an entry to a block's **"more" menu** — the `⋯` on the floating hover toolbar. Register
before constructing the editor.

```js
import BlockSideToggle from '.../Blocks/Components/BlockSideToggle/BlockSideToggle.js';

BlockSideToggle.registerAction({
    key: 'copyBlockJson',
    label: 'Copy block JSON',
    onClick: (block) => {
        navigator.clipboard.writeText(JSON.stringify(block.getBlockData(), null, 2));
    },
});

BlockSideToggle.registerAction({
    key: 'copyBlockText',
    label: 'Copy text',
    blocks: ['core/paragraph', 'core/heading'],           // only these
    isVisible: (block) => block.getContainer().textContent.trim() !== '',
    onClick: (block) => navigator.clipboard.writeText(block.getContainer().textContent.trim()),
});
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Registering the same key twice replaces. |
| `label` | Menu text (defaults to `key`). |
| `onClick(block)` | **Required.** Receives the active block instance. The menu closes afterwards. |
| `blocks` | Whitelist of block type names. Omit for all blocks. |
| `excludeBlocks` | Blacklist — all blocks *except* these. |
| `showOnHidden` | `true` to also show on hidden/system blocks (footnotes), which are skipped by default. |
| `showOnUnknown` | `true` to also show on the unknown-block placeholder — separate from `showOnHidden`. |
| `isVisible(block)` | Optional predicate, re-checked every time a block is focused. |

`unRegisterAction(key)` removes one.

**Menu-only by design.** The toolbar row's horizontal position is derived from how many buttons
it holds (`left = rect.left - 38 * actionsCount`), so adding to that row would shift the whole
toolbar. Menu entries have no such coupling.

Unlike sidebar controls, the side toggle is a **singleton** — one element repositioned per
focus, not rebuilt — so entries are created once at `init()` and only shown/hidden as the active
block changes. There's no per-focus listener churn, and therefore no `listen`/`destroy` needed.

### `FormatToolbar.registerButton(definition)`

The selection toolbar has two areas:

- **The main row** — the everyday formats: **bold, italic, underline, link**, then the
  block-level **alignment** group.
- **An overflow menu**, opened by the chevron next to Link — **strikethrough, highlight,
  superscript, subscript**. Menu entries show an icon *and* a label.

A registered button goes in the menu by default; pass `placement: 'toolbar'` to put it in the
main row instead. Register before constructing the editor.

```js
import FormatToolbar from '.../Blocks/Components/FormatToolbar/FormatToolbar.js';

FormatToolbar.registerButton({
    key: 'brandColor',
    label: 'Brand colour',        // menu text
    title: 'Brand colour',        // tooltip
    icon: '<svg…>',
    onClick: () => {
        document.execCommand('styleWithCSS', false, true);
        document.execCommand('foreColor', false, '#b30000');
    },
});
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Registering the same key twice replaces. |
| `onClick({editable, selection})` | **Required.** `editable` is the block's editable element; `selection` is the live `window.getSelection()`. |
| `placement` | `'menu'` (default) or `'toolbar'`. |
| `icon` | SVG string. In the menu it's shown next to the label; in the main row it's the whole button. |
| `label` | Menu text. Falls back to `title`, then `key`. |
| `title` | Tooltip. Falls back to `label`, then `key`. |
| `isActive({editable, selection})` | Optional. Re-checked on every selection change **and** right after a click; toggles the button's `active` class. |
| `isVisible({editable, selection})` | Optional. Same timing; hides the button entirely. |

`unRegisterButton(key)` removes one.

The toolbar is a singleton — shown and positioned per selection, never rebuilt — so buttons are
created once at init and only their state is re-synced. The menu closes when a command runs and
whenever the toolbar hides, so it can't reappear open with the next selection.

**A custom format without `execCommand`.** Not every format has an `execCommand` equivalent
(`<code>` doesn't). Do the wrapping yourself against the selection's Range — here in the main
row rather than the menu:

```js
FormatToolbar.registerButton({
    key: 'inlineCode',
    title: 'Inline code',
    placement: 'toolbar',
    icon: '<svg…>',
    isActive: ({editable}) => !!tagAroundSelection('code', editable),
    onClick: ({editable}) => {
        const existing = tagAroundSelection('code', editable);
        existing ? unwrapElement(existing) : wrapSelectionIn('code');
    },
});

// The tag enclosing the selection — but only one inside this block, so a selection that
// escapes the editable can't reach out and unwrap markup belonging to another block.
function tagAroundSelection(tag, editable) {
    const selection = window.getSelection();
    if (!selection || !selection.rangeCount || !editable) {
        return null;
    }
    const node = selection.getRangeAt(0).commonAncestorContainer;
    const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
    const match = element ? element.closest(tag) : null;
    return (match && editable.contains(match)) ? match : null;
}

function wrapSelectionIn(tag) {
    const selection = window.getSelection();
    if (!selection.rangeCount || selection.isCollapsed) {
        return;
    }
    const range = selection.getRangeAt(0);
    const wrapper = document.createElement(tag);
    try {
        wrapper.appendChild(range.extractContents());
        range.insertNode(wrapper);
    } catch (e) {
        return;   // selection crossed element boundaries in a way we can't cleanly wrap
    }
    // Leave the caret just after the new element so typing continues unformatted.
    const after = document.createRange();
    after.setStartAfter(wrapper);
    after.collapse(true);
    selection.removeAllRanges();
    selection.addRange(after);
}

function unwrapElement(element) {
    const parent = element.parentNode;
    while (element.firstChild) {
        parent.insertBefore(element.firstChild, element);
    }
    parent.removeChild(element);
    parent.normalize();   // merge the text nodes back, or the html gains split fragments
}
```

Things that are easy to get wrong:

- **Scope the lookup to the block's `editable`** — otherwise a selection that escapes the block
  can reach out and unwrap markup belonging to another one.
- **`normalize()` the parent after unwrapping**, or the saved html accumulates split text-node
  fragments every time you toggle.
- A custom tag **round-trips through save/load for free** (a text block persists its
  `innerHTML`), but the **paste** sanitizer has its own whitelist — `strong`, `em`, `u`, `sup`,
  `sub`, `s`, `mark`, `a`, `br`. Anything else (a `<code>`, a `<span style>` from `foreColor`)
  survives save but is stripped when *pasted in from elsewhere*, unless you add it to
  `ALLOWED_TAGS` in `Paste/sanitizeInline.js`.
- `execCommand` with a colour needs **`styleWithCSS` set first**, or it emits a legacy `<font>`
  tag. And `queryCommandValue('foreColor')` returns `rgb()` strings, so an `isActive` comparing
  against a hex has to normalise first.

**Built-in state.** Every built-in reflects the current selection too: bold/italic/underline via
`queryCommandState`; strikethrough, highlight, superscript, subscript and link from the DOM
around the selection. Those four are applied by wrapping the Range rather than by `execCommand`
— its toggling relies on boundary-buggy state detection, and this way the emitted tag is
predictable (`<s>`, `<mark>`, `<sup>`, `<sub>`) rather than whatever the browser chooses.

### `CommandMenu.register(definition)`

Typing **`//`** in any text block opens the command menu. It is deliberately not the slash
menu: `/` opens only when a block is **empty** and inserts a **block**; `//` matches at the
caret so it works **mid-sentence**, and runs an **action**.

```js
import CommandMenu from '.../CommandMenu/CommandMenu.js';

const contentEditor = new ContentEditor({config, initialContent});

// Registered after the editor exists, so the callback closes over `contentEditor` — that's
// how a command reaches modules and the block manager.
CommandMenu.register({
    key: 'title',
    label: 'Insert post title',
    keywords: ['title', 'heading', 'name'],
    onSelect: ({insert}) => {
        const title = contentEditor.getModule('title')?.getValue();
        if (title) {
            insert(title);
        }
    },
});

// Uses the block manager instead of inserting at the caret: adds paragraphs after the
// current block.
CommandMenu.register({
    key: 'lorem',
    label: 'Insert 3 placeholder paragraphs',
    keywords: ['lorem', 'placeholder', 'dummy'],
    isVisible: ({block}) => !!block,
    onSelect: ({block}) => {
        let reference = block.getContainer();
        LOREM.forEach((html) => {
            const paragraph = contentEditor.blockHandler.renderBlock('core/paragraph', {html}, reference, 'after');
            if (paragraph) {
                reference = paragraph.getContainer();
            }
        });
    },
});

const LOREM = [
    'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.',
    'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi.',
    'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore.',
];

await contentEditor.init();
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Registering the same key twice replaces. Also searchable. |
| `onSelect({block, query, insert})` | **Required.** See the context note below. |
| `label` | Shown in the menu (defaults to `key`). Searchable. |
| `keywords` | Extra search terms. |
| `icon` | Optional SVG string, shown before the label. |
| `isVisible({block})` | Optional. Re-checked as the menu filters — use it to hide a command that needs a block when there isn't one. |

Matching is by substring over the key, label and keywords, but results are **ranked**:
a command whose key or label *starts* with what you typed comes first, then one where
some word starts that way, then a plain substring hit. Without that, typing `u` puts
"D**u**plicate" above "Uppercase" if it registered first — and Enter runs the wrong
command. Ties keep registration order.

`unRegister(key)` removes one.

**The context is deliberately small: `{block, query, insert}`.** Those are the only things that vary
per invocation. Everything else — the editor, its modules, the block manager — comes from the
**closure the command was registered in**: the built-in commands are registered inside
`ContentEditor` so they close over `this`, and a project registers alongside its own
`contentEditor`. Passing the editor through the context as well would just be a second route to
something already in scope.

**The typed token is removed before `onSelect` runs**, with the caret left where it was. That's
what lets insert-commands and action-commands share one contract — there's no "did you consume
the token?" question, and `insert(stringOrNode)` simply drops content at the caret. A command
that ignores `insert` entirely (delete the block, open a panel, save) just acts.

**Arguments.** Anything typed after the command name is passed as `query`:
`//uppercase some text` runs the `uppercase` command with `query: 'some text'`. Only the first
word filters the list — otherwise typing an argument would narrow the results to nothing and
close the menu before you could pick anything. The trade-off is that a multi-word label can't
be searched as a phrase.

A command can read `query` or ignore it, and the two modes can coexist. The built-in case
commands do exactly that — with an argument they insert, bare they rewrite the block:

```js
CommandMenu.register({
    key: 'uppercase',
    label: 'Uppercase',
    isVisible: ({block}) => !!block && block.constructor.isText,
    onSelect: ({block, query, insert}) => {
        if (query) {
            insert(query.toUpperCase());        // `//uppercase hello` -> HELLO at the caret
            return;
        }
        transformText(block.getContainer(), (text) => text.toUpperCase());
    },
});
```

**There is never a selection to act on.** The menu only opens on a collapsed caret — typing the
trigger would have replaced a selection anyway — so a command that transforms text has to name
its own target. The whole block is the sane default: when the caret is at the end of what you
just typed (the usual case) it's indistinguishable from "everything I wrote", and when you go
back to fix an older block it does the obvious thing instead of splitting the block into two
casings.

Rewriting a block means walking its **text nodes**, which is what the exported `transformText`
helper does. Assigning `textContent` would flatten every `<strong>`, `<a>` and `<mark>` inside
it, and it saves and restores the caret across the rewrite — replacing a text node's data
collapses any live range inside it to that node's start, which would drop the caret against
the preceding inline element and make the next thing typed inherit its formatting.

It's only safe for per-character transforms: title case and sentence case depend on
word and sentence boundaries, and inline markup splits those across nodes.

Built in: `//date`, `//time`, `//emdash`, `//ellipsis`, `//arrow`, `//copyright`, `//euro`, `//pound`, `//check`, `//footnote` (only when `core/footnotes` is enabled), `//uppercase`, `//lowercase`, `//duplicate`, `//delete`. These
are **block/content** commands — the `//` menu is scoped to the active block and its text.

**Typing a URL doesn't trigger it.** The menu is suppressed when the `//` is preceded by a
colon, so `https://` and `http://` are left alone. Other stray `//` (a code comment, say) opens
a menu that closes itself as soon as the query matches nothing — and Escape or a click outside
always dismisses it.

**Changing the trigger.** `//` still isn't right for everyone — authors who paste URLs or write
code constantly may want it out of the way. Set `config.commandTrigger` (or call
`editor.commandMenu.setTrigger('::')` at runtime) to any whitespace-free string:

```js
const contentEditor = new ContentEditor({
    config: {commandTrigger: '::', /* … */},
    initialContent,
});
```

The query then runs from the trigger to the caret, stopping at a newline or at the trigger's
own first character — so a second trigger always starts a fresh token. The `https://` guard is
specific to slash-leading triggers, so a trigger of `::` isn't crippled by it. Avoid clashing
with a sequence already registered as an entity trigger (`{{` by default).

The menu never opens when the block manager is read-only, since every command mutates something.

### `CommandPalette` — Ctrl/Cmd+K

A global, searchable palette, distinct from the `//` menu: `//` is inline and acts on the text
under the caret; **Ctrl/Cmd+K** opens from anywhere and is for editor-wide actions — jump to an
admin page, view the post on the site, search your content. It is **opt-in** and entirely
developer-populated (nothing ships built in): turn it on with an explicit `enabled: true`, then
feed it from three sources.

```js
// config — off by default; `enabled: true` is required (a bare `{}` stays off)
{ commandPalette: { enabled: true } }   // also flips at runtime via editor.commandPalette.setEnabled()
```

**1. Commands** — static, client-side entries grouped by `category`, filtered locally as you type.

```js
import CommandPalette from '.../CommandPalette/CommandPalette.js';

// A plain link. `url` makes Enter navigate; Ctrl/Cmd+Enter opens it in a new tab.
CommandPalette.registerCommand({
    category: 'Navigation',
    key: 'homepage-admin',
    label: 'Open homepage admin',
    keywords: ['home', 'admin'],
    url: '/admin/pages/home',
});

// A link whose target depends on live state. `url` as a function is resolved at render time,
// so this stays a real <a> (right-click, middle-click, Ctrl/Cmd-click all work) even though
// the slug is dynamic — no need to drop to onSelect just because the URL isn't static.
CommandPalette.registerCommand({
    category: 'This post',
    key: 'view-on-site',
    label: 'View on site',
    url: () => `/posts/${editor.getModule('slug')?.getData() || ''}`,
});

// `onSelect` is for true actions, not navigation — a side effect with no URL. It gets
// `newTab` (from Ctrl/Cmd+Enter) and `close`. (Registered after the editor exists so it can
// close over `editor`.)
CommandPalette.registerCommand({
    category: 'This post',
    key: 'copy-permalink',
    label: 'Copy permalink',
    onSelect: () => {
        navigator.clipboard?.writeText(`${location.origin}/posts/${editor.getModule('slug')?.getData() || ''}`);
    },
});
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Re-registering a key replaces it. |
| `url` **or** `onSelect` | **Required (one).** `url` (a string **or** `() => string`, resolved at render) → navigation; `onSelect({newTab, close})` → a side-effect action. A `url` row renders as a real `<a href>`, so right-click, middle-click and Ctrl/Cmd-click all behave like any link — prefer it over `onSelect` for anything that's really a navigation, even a dynamic one. |
| `category` | Group header (defaults to `Commands`). |
| `label` | Shown (defaults to `key`). Searchable. |
| `keywords` | Extra search terms. |
| `icon` | Optional SVG string. |
| `isVisible()` | Optional — return `false` to hide the command. |

**2. The search endpoint** — the backend's own searchable surface. It decides what is searchable
and returns a flat list of items; **`category` is per item**, so one response can mix types and
the palette splits them into groups on its own. Set it in config (`commandPalette.search`) or at
runtime with `editor.commandPalette.setSearch(fn)`.

```js
editor.commandPalette.setSearch(async (query) => {
    // Your backend returns whatever it deems searchable — posts, pages, authors, … — each
    // item tagging itself with a `category`. Shown here as a static list; do the fetch instead.
    const everything = [
        { category: 'Posts',   label: 'Breaking: local election results', url: '/admin/posts/1' },
        { category: 'Posts',   label: 'How the new budget affects you',    url: '/admin/posts/2' },
        { category: 'Pages',   label: 'About us',                          url: '/admin/pages/about' },
        { category: 'Pages',   label: 'Contact',                           url: '/admin/pages/contact' },
        { category: 'Authors', label: 'Jane Doe', subtitle: '42 articles', url: '/admin/authors/7' },
    ];
    // The backend does the filtering; done here so the example reacts to the query.
    return everything.filter((item) => item.label.toLowerCase().includes(query.toLowerCase()));
});
```

That single response renders as three groups — **Posts**, **Pages**, **Authors** — in first-seen
order. Items that don't tag themselves fall under a default "Results" group. Give each type its
own `icon` to tell them apart at a glance.

**3. Entity sections** — a titled group loaded from an endpoint you choose, shown while
**browsing** (before anything is typed) — "recent posts", "your drafts", and so on.

```js
CommandPalette.registerSection({
    key: 'recent-posts',
    title: 'Recent posts',
    load: async (query) => {                 // `query` is passed if you want to filter server-side
        const posts = await fetch('/api/posts/recent').then((r) => r.json());
        return posts.map((post) => ({
            label: post.title,
            subtitle: post.when,             // a second, muted line — e.g. "2 hours ago"
            url: post.editUrl,               // Enter edits it; Ctrl/Cmd+Enter opens a new tab
        }));
    },
});
```

The items that `load` returns are what the group shows. Concretely, a "Recent posts" section
whose endpoint hands back the last five posts:

```js
CommandPalette.registerSection({
    key: 'recent-posts',
    title: 'Recent posts',
    load: async () => ([
        { label: 'Weather warning issued for the weekend', subtitle: '2 hours ago', url: '/admin/posts/11' },
        { label: 'City council approves new park',         subtitle: 'Yesterday',   url: '/admin/posts/12' },
        { label: 'Op-ed: the case for cycling lanes',      subtitle: '2 days ago',  url: '/admin/posts/13' },
        { label: 'Sports roundup: week 14',                subtitle: '3 days ago',  url: '/admin/posts/14' },
        { label: 'Restaurant review: the new bistro',      subtitle: '4 days ago',  url: '/admin/posts/15' },
    ]),
});
```

Register more than one section and each is its own group — "Recent posts", "Your drafts",
"Scheduled" — all shown together while browsing, in registration order.

An **item** (from any source) is `{ label, url?, onSelect?, icon?, subtitle?, category? }` — `url`
or `onSelect`, same contract as a command. Both are async and race-guarded: a newer query drops
any in-flight result. Commands are painted instantly; endpoint results fold in as they resolve.

Everything is keyboard-drivable — ↑/↓ to move (wrapping), Enter to activate, **Ctrl/Cmd+Enter to
open a link in a new tab** (stated in the footer), Esc or a backdrop click to close. Toggle the
whole thing at runtime with `editor.commandPalette.setEnabled(true|false)` / `isEnabled()`.

### `EditLock` — "someone else is editing this"

The barrier shown when this editor isn't the one in charge of the post. Two states:

| Method | When | Meaning |
| --- | --- | --- |
| `showAlreadyEditing({user, since})` | on open — someone already holds the lock | you haven't started; nothing of yours is at risk |
| `showTakenOver({user})` | mid-session — your lock was taken | **you may have unsaved work** |

They're separate methods rather than one with a flag because they mean different things to the
person reading them, and they offer different actions.

**It renders, and nothing else.** No polling, no endpoints, no idea what a lock is — the project
decides when to call these, whether that comes from a heartbeat, a socket, or a 409 on save.
Same split as `CommandPalette`'s `search` and `UnsavedGuard`'s `getData`.

```js
import ContentEditor from './ContentEditor/ContentEditor.js';
import EditLock, {STATES} from './ContentEditor/EditLock/EditLock.js';
import {events as editLockEvents} from './ContentEditor/EditLock/events.js';

const postId = 42;
const editor = new ContentEditor({
    config: {
        blocks: ['core/paragraph', 'core/heading', 'core/image'],
        modules: ['title', 'slug', 'status'],
    },
    initialContent: {},
});

// ---- before init(): registering actions, and reacting to them -------------------------------
// Every button emits editLockAction with its key, so one listener drives them all — including
// any you add — rather than an onClick per action.
editor.eventEmitter.on(editLockEvents.editLockAction, ({key, user}) => {
    if (key === 'exit') {
        window.location.assign('/posts');
    }
    if (key === 'takeOver') {
        // Only hide once the server confirms the lock is actually yours.
        fetch(`/api/posts/${postId}/lock/take`, {method: 'POST'})
            .then(() => editor.editLock.hide());
    }
    if (key === 'contact') {
        openChatWith(user);
    }
});

// Drop a built-in, add your own. Both built-ins are registered at module load, so anything here
// runs afterwards and always wins.
EditLock.unRegisterAction('takeOver');       // e.g. this project never allows stealing a lock
EditLock.registerAction({
    key: 'contact',
    label: 'Message them',
    states: [STATES.alreadyEditing],          // omit for both dialogs
    order: 0,                                 // built-ins are 1 (take over) and 2 (exit)
});

// ---- after init(): showing it ---------------------------------------------------------------
await editor.init();

// Whether the post was already locked when this editor opened.
const initial = await (await fetch(`/api/posts/${postId}/lock`)).json();
if (initial.heldBy) {
    editor.editLock.showAlreadyEditing({user: initial.heldBy, since: initial.since});
}

// And whether it gets taken while you're working. Poll however you like — this is the whole
// integration surface.
setInterval(async () => {
    const lock = await (await fetch(`/api/posts/${postId}/lock`, {method: 'POST'})).json();
    if (lock.heldBy) {
        editor.editLock.showTakenOver({user: lock.heldBy});
    }
}, 15000);
```

**Order matters.** Register actions and listeners *before* `init()`; call the `show…` methods
*after* it. The dialog is built during `init()`, so a `show…` before that returns silently —
nothing throws, nothing appears.

`user` is `{name, avatar}` and both are optional. With no name the message falls back to a
generic one; with no picture the avatar falls back to a person glyph rather than collapsing, so
the dialog is the same height either way. `since` is rendered exactly as given, so format it
for your locale before passing it.

| Method | Description |
| --- | --- |
| `init()` | Build the dialog. Returns `this`. Called for you by `ContentEditor`. |
| `showAlreadyEditing({user, since})` / `showTakenOver({user})` | Show a state. Safe to call while already open — it swaps. |
| `hide()` | Close it. |
| `isOpen()` / `getState()` | `getState()` is `null`, `'alreadyEditing'` or `'takenOver'`. |
| `destroy()` | Unbind and remove the dialog. |

Events (`EditLock/events.js`): `editLockShown` `{state, user}`, `editLockHidden` `{state}`,
`editLockAction` `{key, state, user, lock}`.

**Being taken over turns the editor read-only.** `ContentEditor` listens for `editLockShown` and
calls `setReadOnly(true)` when the state is `takenOver` — the dialog is the real barrier, but this
keeps `isReadOnly()` and the save button honest about what happened.

#### The actions

Two ship by default, and both go through the same registry as anything you add — which is what
makes removing one a single call rather than a special opt-out:

| key | label | Shown on | Order |
| --- | --- | --- | --- |
| `takeOver` | Take over | `alreadyEditing` only | 1 (primary) |
| `exit` | Exit the editor | both | 2 |

`takeOver` isn't offered on `takenOver`: taking it back just starts a ping-pong between two
people. Register it for that state yourself if you disagree.

Neither declares an `onClick` — what "exit" means is your decision. **Every button emits
`editLockAction` with its key**, so a project can drive all of them from one listener, or supply
`onClick` per action, or both.

```js
EditLock.registerAction({
    key: 'contact',
    label: 'Message them',
    states: [STATES.alreadyEditing],   // omit for both dialogs
    order: 0,                          // lower renders first; the built-ins are 1 and 2
    primary: false,
    onClick: ({state, user, lock}) => openChatWith(user),   // optional
});

EditLock.unRegisterAction('takeOver');   // drop a built-in
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Identifies the action; emitted with `editLockAction`. Re-registering replaces. |
| `label` | **Required.** Button text, translated when rendered. |
| `states` | Which dialogs it appears on. Omit for both. |
| `order` | Sort order, default `100`. Registration order breaks ties. The built-ins are `1` and `2`, so an unordered action lands after them. |
| `primary` | Styles it as the main action, and focuses it when the dialog opens. |
| `onClick({state, user, lock})` | Optional — `editLockAction` fires regardless. |

Built-ins are registered at **module load**, not in `init()`, so your code always runs afterwards
and an `unRegisterAction` can't be silently undone by a later init.

#### Why it can't be dismissed

It's a native `<dialog>` opened with `showModal()`, which brings the focus trap and top-layer
stacking (so it covers an already-open modal) for free. On top of that: the `cancel` event is
prevented so `Escape` does nothing, there's no close button, and there is deliberately no
backdrop-click handler. It also never registers with `Dismissible` — that registry is opt-in, so
staying out of it is all that's needed.

> **Order of work.** `showTakenOver()` takes the editor away from someone who may have unsaved
> changes, and there's currently nowhere for that work to go — `#handleSave` is still a stub. Wire
> `showAlreadyEditing()` first (it can't lose anything), and hold `showTakenOver()` until a save
> or draft endpoint exists behind it. WordPress can afford to yank the editor away because it
> stashes the loser's content as an autosave first.

### `ContentEditor.registerContentTransform(definition)`

Normalise the content once, on load — force `rel` on external links, rewrite legacy image
paths, strip a deprecated attribute. Register before constructing the editor.

```js
import ContentEditor from '.../ContentEditor.js';

ContentEditor.registerContentTransform({
    key: 'externalLinkRel',
    transform: (content, {editor}) => {
        content.querySelectorAll('a[href^="http"]').forEach((anchor) => {
            anchor.setAttribute('rel', 'noopener');
            anchor.setAttribute('target', '_blank');
        });
    },
});
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Registering the same key twice replaces. |
| `transform(content, {editor})` | **Required.** `content` is the `#content` element. Return value ignored. |

`unRegisterContentTransform(key)` removes one.

**Why this exists rather than just doing it after `init()`.** The same loop written by hand has
two problems, and the registry fixes both:

- It runs inside **`runWithoutObserving`**, so the edits don't fire `contentChanged` — no
  history entry, and the document isn't marked dirty. (`rel`, `href`, `target`, `src` and `alt`
  are all on the observer's attribute filter, so an unwrapped version *would* trigger it.)
- It runs **before `contentEditorFinalize`**, which is when History and the unsaved guard take
  their baselines. So the transformed content *is* the baseline. Run it after `init()` and the
  DOM and the baseline disagree, and the editor opens already reporting unsaved changes.

Transforms run in registration order. One that throws is logged and the rest still run — a bad
transform shouldn't stop the editor loading.

> This is for **normalising what loaded**. A *user-initiated* bulk action should not be a
> transform: run it normally so it's undoable and correctly marks the document dirty.

### `Blocks.registerPasteHandler(definition)`

Claim a paste before the editor's normal parsing — turning a pasted YouTube URL into an embed,
a CSV blob into a table, a tracking link into a plain one. Register before constructing the
editor.

```js
import Blocks from '.../Blocks/Blocks.js';

Blocks.registerPasteHandler({
    key: 'youtubeEmbed',
    handle: ({text, block, blocks}) => {
        const url = (text || '').trim();
        if (!isYoutubeUrl(url)) {
            return false;         // not mine — the next handler, then the default, runs
        }
        // Only embed when the paste lands on an *empty text block* — an empty line is
        // unambiguous intent. Pasting a URL into a paragraph that already has content should
        // stay an ordinary text paste, so decline and let the default handling run.
        if (!block || !block.constructor.isText || block.getContainer().textContent.trim() !== '') {
            return false;
        }
        blocks.renderBlock('core/embed', {src: url}, block.getContainer(), 'after');
        block.destroy();   // the empty placeholder it replaced
        return true;              // claimed — nothing else touches this paste
    },
});

// Only a bare URL on its own — pasting a paragraph that happens to contain a YouTube link
// should stay text.
function isYoutubeUrl(text) {
    return /^https?:\/\/(www\.)?(youtube\.com\/watch\?v=[\w-]+|youtu\.be\/[\w-]+)/.test(text)
        && !/\s/.test(text);
}
```

| Field | Description |
| --- | --- |
| `key` | **Required.** Registering the same key twice replaces. |
| `handle({text, html, block, blocks})` | **Required.** Return truthy to claim the paste (the editor calls `preventDefault()` for you); anything falsy passes it on. |

`unRegisterPasteHandler(key)` removes one.

The `handle` context:

- **`text`** — the clipboard's `text/plain`.
- **`html`** — its `text/html`, if any.
- **`block`** — the focused block, or `null`. Handlers run whatever the block type is, so check
  it yourself if you only want to act inside text blocks.
- **`blocks`** — the block manager, for `renderBlock(...)` and friends.

**Where they sit in the pipeline:**

1. A **copied-block payload** from this editor — always wins, handlers never see it.
2. **Registered handlers**, in registration order. The first truthy return claims the paste.
3. The default html/plain-text parsing.

Handlers are **skipped entirely for a plain-text paste** (`Ctrl`/`Cmd`+`Shift`+`V`) — that
gesture means "no smart handling, just the text", which also gives users a reliable way to
paste a URL *as a URL*.

A handler that throws is logged and treated as "not mine", so one bad handler can't break
pasting for everything else.

### Scoping — `registerSidebarControl` and `registerAction`

These two share the same block-scoping rules. (`FormatToolbar.registerButton` does **not** —
it acts on a text selection rather than on a block type, so it takes `isVisible` only.)

```js
blocks:        ['core/image', 'core/table']   // only these        (whitelist)
excludeBlocks: ['core/spacer']                // all except these  (blacklist)
showOnHidden:  true                           // include hidden/system blocks (footnotes)
showOnUnknown: true                           // include the unknown-block placeholder
```

Give neither list and it applies everywhere. Give both and the whitelist applies first, then
the blacklist removes from it — so **`excludeBlocks` wins** for a type named in both.

Two kinds of block are skipped by default, each with **its own** opt-in, because they're
different situations:

- **Hidden/system blocks** (footnotes) — auto-managed, but real content. Opt in with
  `showOnHidden`.
- **The unknown-block placeholder** — inert content this editor can't interpret, so most
  actions are meaningless on it. Opt in with `showOnUnknown`.

The placeholder is *also* `hidden`, but `showOnHidden` deliberately does **not** cover it —
`showOnUnknown` alone is enough, and neither flag leaks into the other's case.

> **`showOnUnknown` only applies to side-menu actions.** The unknown-block placeholder replaces
> the whole block sidebar with its own "can't be edited here" note — it overrides
> `renderSidebarContent()` without calling `super`, so registered *sidebar controls* never run
> for it and the flag has nothing to switch on.

---

## Adding a block

A complete example: a non-text **Button** block (a link preview, edited in the sidebar).
This is an **app block** — it lives in *your* project (under `config.appBlocksPath`),
registered as `app/button`. It uses **your own** class names and CSS; you never edit the
library's files. (Core blocks — the ones shipped inside the library — follow the same
contract; you just wouldn't author those as a consumer.)

**1. The block** — `Button/Button.js` in your app-blocks folder. Import the library's
`Block` base and `SidebarSection` from wherever the library is installed. Optionally import
the exported `contentEditorSelectors` to *reuse* the editor's shared form classes (`input`,
`inputContainer`) so your sidebar controls look native — but everything block-specific is
your own class:

```js
import Block from "<content-editor>/Blocks/Block.js";
import SidebarSection from "<content-editor>/Sidebar/SidebarSection/SidebarSection.js";
import {contentEditorSelectors} from "<content-editor>/contentEditorSelectors.js"; // optional, for shared UI classes

export default class Button extends Block {
    static label = 'Button';
    static keywords = ['button', 'cta', 'link'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" width="24" height="24" fill="#e3e3e3"><path d="M240-160q-33 0-56.5-23.5T160-240v-480q0-33 23.5-56.5T240-800h480q33 0 56.5 23.5T800-720v480q0 33-23.5 56.5T720-160H240Zm0-80h480v-480H240v480Z"/></svg>`;
    static isText = false;
    static name = 'app/button';
    static category = 'media';
    static description = 'A call-to-action button.';
    static advancedSidebarOpen = false;   // this block has its own section — see below

    element;
    #listeners = [];

    render() {
        this.element = document.createElement('div');
        this.element.tabIndex = -1;                 // focusable so the side toggle appears
        this.element.classList.add('buttonBlock');  // your own class

        this.link = document.createElement('a');
        this.link.classList.add('buttonBlockLink'); // your own class
        this.link.textContent = (this.data && this.data.label) || 'Button';
        this.link.href = (this.data && this.data.url) || '#';
        this.element.appendChild(this.link);
        return this.element;
    }

    getContainer() { return this.element; }
    focus() { this.element.focus(); }

    getData() {
        return { label: this.link.textContent, url: this.link.getAttribute('href') };
    }

    // Custom sidebar: keep the base "Advanced" section, prepend our controls.
    // (`advancedSidebarOpen = false` above leaves Advanced collapsed, so these show first.)
    renderSidebarContent() {
        super.renderSidebarContent();
        if (!this.section) {
            this.section = SidebarSection.generate('Button', 'buttonSidebar', this.eventEmitter, true);
            const content = this.section.container.querySelector('#buttonSidebar');
            content.append(this.#field('Label', 'label'), this.#field('URL', 'url'));
            this.sidebarContainer.prepend(this.section.container);
        }
        return this.sidebarContainer;
    }

    #field(labelText, key) {
        const wrap = document.createElement('div');
        // reuse the editor's shared form classes so the control looks native (or use your own)
        wrap.classList.add(contentEditorSelectors.classes.inputContainer);
        const label = document.createElement('label');
        label.textContent = labelText;
        const input = document.createElement('input');
        input.classList.add(contentEditorSelectors.classes.input);
        input.value = key === 'label' ? this.link.textContent : this.link.getAttribute('href');
        const handler = () => {
            if (key === 'label') this.link.textContent = input.value;
            else this.link.href = input.value;
        };
        input.addEventListener('input', handler);
        this.#listeners.push({input, handler});
        wrap.append(label, input);
        return wrap;
    }

    destroySidebar() {
        this.#listeners.forEach(({input, handler}) => input.removeEventListener('input', handler));
        this.#listeners = [];
        if (this.section) { this.section.destroy(); this.section = null; }
        super.destroySidebar();
    }

    destroy() {
        super.destroy();       // emits blockDeleted
        this.element.remove();
    }
}
```

**2. Config** — add `'app/button'` to `config.blocks`, and point `config.appBlocksPath` at
your app-blocks folder.

**3. Styles** — in **your own stylesheet**, targeting your own classes, however you like. The
editor's theme variables (`--colorPrimary-500`, `--colorSurface-100`, …) are global if you
want to match its look, or use your own values:

```css
.buttonBlock { outline: none; }
.buttonBlockLink {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    background: var(--colorPrimary-500);
    color: var(--colorSurface-100);
}
```

That's the whole surface: statics for discovery, `render`/`getData` for content,
`renderSidebarContent`/`destroySidebar` for settings, config to enable, and your own CSS to
style. You ship your block's classes and styles yourself — the library's
`contentEditorSelectors.js` and SCSS are never touched.

---

## Adding a module

A complete example: an **Allow Comments** toggle.

**1. The module** — `AllowComments/AllowComments.js`:

```js
import BaseModule from "../BaseModule.js";

export default class AllowComments extends BaseModule {
    #input;

    init() {
        this.#input = document.getElementById('allowComments');
        if (!this.#input) return;                    // bail cleanly if the section is absent
    }

    getData() { return this.#input ? this.#input.checked : false; }
    setData(value) { if (this.#input) this.#input.checked = !!value; }

    destroy() {
        this.#input = null;
        super.destroy();
    }
}
```

**2. Register it** — before constructing the editor:

```js
import AllowComments from './AllowComments/AllowComments.js';
ContentEditor.registerModule('allowComments', { class: AllowComments });
// contentKey — the property this module owns in the content object, read from
// initialContent and written to the save payload — defaults to the module name.
// Add getData/setData adapters if your method names differ from the defaults.
```

**3. Config** — add `'allowComments'` to `config.modules`.

**4. HTML** — add its section (with `data-module`) inside `#sidebarEntityContent`:

```html
<div class="sidebarContentSection" data-module="allowComments">
    <div class="sidebarContentSectionHandle"><span>Allow Comments</span><!-- chevron --></div>
    <div class="sidebarContentSectionContent">
        <label><input type="checkbox" id="allowComments" class="input"> <span>Allow comments</span></label>
    </div>
</div>
```

On save its value appears under `allowComments`; on load `setData` is called with
`initialContent.allowComments`. (Core modules are already in `MODULES`; you only need
`registerModule` for project modules.)

**Load-only modules** — pass `getData: null` and the module reads `initialContent` but stays
out of the save payload. That's for data the editor displays but doesn't own, which is how
`revisions` works. An *omitted* `getData` still means "use the default"; only an explicit
`null` opts out.

```js
ContentEditor.registerModule('revisions', { class: Revisions, getData: null });
```

---

## Revisions

Enable `'revisions'` in `config.modules`, add a `[data-module="revisions"]` section
containing `<div id="revisions"></div>`, and pass them with the content:

```js
initialContent = {
    blocks: [ /* ... */ ],
    revisions: [
        { id: 6, date: '2026-07-17 08:55:00', author: 'Test Author',
            content: { title: 'Release notes', blocks: [ /* ... */ ] } },
        // ...
    ],
}
```

The sidebar lists them newest first; clicking one opens a modal that diffs it against the
live content (via [DiffViewer](../DiffViewer/README.md), matching blocks on their persisted
`id` so a reorder reads as a *move*) and offers a revert.

**A revision's `content` is the save shape** — exactly what `getDataForSave()` returned. That
matters literally: `getBlockData()` always emits `additionalData` (`{}` when the Advanced
sidebar is untouched), so hand-written revision blocks that omit the key diff as *modified*
on every block. Store what the editor gave you.

**Revert** applies the whole snapshot: every module whose `contentKey` the content carries
gets its `setData`, and the blocks are reconciled rather than re-rendered, so untouched blocks
keep their elements. It lands in undo history, so a revert can be undone like any other edit.

Only the keys a revision *carries* are diffed and reverted — a `{title, blocks}` snapshot
leaves slug, categories and the rest of the editor alone.

### Previewing blocks in the diff

By default only blocks with `html` are visualised. Every other block is reported by name plus
whichever of its fields changed (`chartType: bar → line`), which is usually what you want —
a revision diff is for reading changes, not re-rendering the post.

To visualise a specific type, register a renderer **before constructing the editor**. This
works for any block, core or your own — nothing in the module knows the type exists:

```js
import Revisions from '/src/ContentEditor/Revisions/Revisions.js';

Revisions.registerPreview('core/image', (block) =>
    `<img src="${Revisions.escape(block.src)}" alt="">`);

Revisions.unRegisterPreview('core/image');   // back to no preview
```

| Member | Description |
| --- | --- |
| `static registerPreview(type, renderer)` | `renderer: (block) => htmlString`. Overrides the default for that block type. Return `''` for no preview. |
| `static unRegisterPreview(type)` | Remove a renderer. |
| `static escape(value)` | HTML-escape a value. **Use it** — a renderer's return value is inserted as HTML, and block data is user content. |

A preview only affects *display*. Blocks are still matched by `id` and word-diffed on their
`html`, so registering one never changes what counts as added, removed or moved. In the
side-by-side view each side renders its own copy, so the two versions sit next to each other.

#### Example: a chart

A chart has no text at all, so describe the data rather than drawing it — that keeps the card
readable and avoids mounting a live component inside a diff:

```js
Revisions.registerPreview('core/chart', (block) => {
    const labels = Revisions.escape((block.labels || []).join(', '));
    const series = (block.series || [])
        .map((s) => `${Revisions.escape(s.name || '(unnamed)')} `
            + `[${Revisions.escape((s.values || []).join(', '))}]`)
        .join('<br>');
    return `<em>${Revisions.escape(block.chartType)}</em> · ${labels}`
        + (series ? `<br>${series}` : '');
});
```

```
groupedbar · Q1, Q2, Q3, Q4
Revenue [120, 200, 150, 280]
Cost [80, 130, 100, 160]
```

#### Example: a timeline

A different problem: a timeline *does* have text, but it lives in `items[]` rather than
`html`, so the default preview finds nothing and the field diff prints the whole array as
JSON. Walk the items instead:

```js
Revisions.registerPreview('core/timeline', (block) => (block.items || [])
    .map((item) => `<strong>${Revisions.escape(item.time)}</strong> ${item.content ?? ''}`)
    .join(''));
```

```
12:07
Some event goes here.
12:09
Event goes here with a link
```

(The time and its text land on separate lines because `item.content` is a `<p>` — block
markup the editor produced. Wrap it if you want them inline.)

Note `item.time` is escaped but `item.content` is not: it is editor HTML, the same trust
level as `block.html`, which the default preview also passes through verbatim. Escape
anything that is *data* (a name, a filename, a chart label); pass through anything that is
already *markup the editor produced*. When in doubt, escape — a mangled tag is a smaller
problem than an injected one.

The same shape works for any block whose content lives in a custom field — `core/accordion`
(`items[].summary` / `items[].content`), `core/table` (`headers` / `rows`), or your own
`app/*` blocks. Nothing in the module knows these types exist.

| Event | Payload | Description |
| --- | --- | --- |
| `revisionRevertRequested` | the revision | A revert was asked for. The editor applies it. |
| `revisionReverted` | the revision | Emitted after the snapshot is applied. |
| `currentContentRequested` | `{}` → `{content}` | How a module reads the live content: the emitter is synchronous, so the listener fills the payload in before `emit()` returns. |
| `blockLabelsRequested` | `{}` → `{labels}` | Same shape. A `Map` of block type → the class's `static label`, for naming a block to a person. Only registered types are in it. |

---

## Entity triggers — register a sequence that fires an entity search

Register a **trigger sequence** (`[[`, `@`, `#`, …) and typing it in editable text opens a
search popup; pick a result and the `sequence + query` is rewritten into whatever the
trigger's `render(item)` returns. Registration is a static call **before constructing the
editor**, the same pattern as `Revisions.registerPreview`.

It's backend-agnostic: the editor calls `search(query)` and knows nothing about the endpoint
or response shape — that lives entirely in your closure. With nothing registered, nothing
fires.

```js
import EntityTriggers from '.../Blocks/Components/EntityTriggers/EntityTriggers.js';

EntityTriggers.register('[[', {
    allowSpaces: true,   // query may contain spaces (post titles); default is one token
    search:  async (q) => (await fetch(`/posts?q=${encodeURIComponent(q)}`)).json(),
    labelOf: (post) => post.title,   // each result row
    // render owns what gets inserted; a DOM node is safest (textContent is escaped):
    render:  (post) => { const a = document.createElement('a'); a.href = post.url; a.textContent = post.title; return a; },
    description: 'Search and link a post',   // optional; listed in the shortcuts panel
});

EntityTriggers.register('@', {
    search:  async (q) => searchUsers(q),
    labelOf: (u) => u.name,
    render:  (u) => `<span class="mention" data-id="${u.id}">@${u.name}</span>`,   // or an HTML string
});
```

| Member | Description |
| --- | --- |
| `static register(trigger, definition)` | `definition`: `{ search, labelOf, render, renderResult?, allowSpaces?, description?, maxResults?, minChars?, debounce? }`. |
| `static unRegister(trigger)` | Remove a registered trigger. |

`render(item)` decides what's inserted — return a **DOM element** (safest: its `textContent`
is escaped) or an **HTML string** (which you own, so escape any interpolated data). Without a
`render`, the item's label is inserted as plain text. `allowSpaces` decides whether the query
is a phrase or a single token. The registry is global (shared across editor instances), like
`Revisions.PREVIEWS`.

The generic popup is [`src/EntitySearch`](../EntitySearch/README.md); reuse it directly in a
block to pick entities for anything (a related-posts list, an author picker, …) with the same
`{search, labelOf, onSelect}` contract.

---

## Events

**Top-level** (`events.js`): `contentEditorPreload`, `contentEditorFinalize`, `beforeSave`,
`afterSave`, `renderBlockMenu`.

**EditLock** (`EditLock/events.js`): `editLockShown`, `editLockHidden`, `editLockAction` — see [`EditLock`](#editlock--someone-else-is-editing-this).

**Blocks** (`Blocks/events.js`): `blocksReady`, `blockInserted`, `blockDeleted`,
`blockFocused`, `contentChanged`, `insertAfter`, `insertBefore`, `setActiveBlock`,
`deleteBlock`, `duplicateBlock`, `leftPanelOpened`.

`blockInserted` carries a guarantee worth relying on: it is emitted by `renderBlock()` for
**every** block view that is created, whatever created it — initial load, paste, duplicate, the
inserter, Enter, column children (recursively), and the rebuild half of undo / redo / revert. It
fires once the view is in the DOM and before the block is focused, and its payload is the block
instance. Subscribe before `init()` to include the blocks already in the post. That makes it the
hook for anything that has to decorate or inspect every block — see
[Recipe — showing a block's state on the block itself](#recipe--showing-a-blocks-state-on-the-block-itself).

```js
editor.eventEmitter.on('afterSave', (payload) => fetch('/save', {method:'POST', body: JSON.stringify(payload)}));
```


