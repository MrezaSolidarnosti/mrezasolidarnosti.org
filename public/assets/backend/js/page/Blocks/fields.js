import {contentEditorSelectors} from "../../../../../vendor/skeletorjs/src/ContentEditor/contentEditorSelectors.js";
import {mediaLibrarySelectors} from "../../../../../vendor/skeletorjs/src/MediaLibrary/mediaLibrarySelectors.js";
import {events as mediaLibraryEvents} from "../../../../../vendor/skeletorjs/src/MediaLibrary/events.js";
import Translator from "../../../../../vendor/skeletorjs/src/Translator/Translator.js";
import TabbedContent from "../../../../../vendor/skeletorjs/src/TabbedContent/TabbedContent.js";
import {tabbedContentSelectors} from "../../../../../vendor/skeletorjs/src/TabbedContent/tabbedContentSelectors.js";
import {events as tabbedContentEvents} from "../../../../../vendor/skeletorjs/src/TabbedContent/events.js";

/**
 * Form controls for the page section blocks.
 *
 * These blocks are configuration, not prose: the old editor rendered each one as a small form
 * and the frontend templates read named keys out of it. So each field here owns one key, keeps
 * its value in the DOM (the editor's rule — the DOM is the source of truth) and exposes
 * `getValue()`; a block's `getData()` is then just a map of key to field value.
 *
 * Every field returns an object with `{element, getValue, destroy}` so a block can append it,
 * read it and tear it down without knowing which kind it is.
 */

const c = contentEditorSelectors.classes;

function labelElement(text) {
    const label = document.createElement('label');
    label.textContent = Translator.translate(text);
    return label;
}

function container() {
    const element = document.createElement('div');
    element.classList.add(c.inputContainer);
    return element;
}

/** Single line text. */
export function textField({label, value = '', placeholder = ''}) {
    const element = container();
    const input = document.createElement('input');
    input.type = 'text';
    input.classList.add(c.input);
    input.value = value ?? '';
    input.placeholder = Translator.translate(placeholder || label);
    element.append(labelElement(label), input);

    return {element, getValue: () => input.value, destroy: () => {}};
}

/** Multi line plain text — no markup, exactly what the old textarea saved. */
export function textAreaField({label, value = '', placeholder = '', rows = 3}) {
    const element = container();
    const input = document.createElement('textarea');
    input.classList.add(c.input);
    input.rows = rows;
    input.value = value ?? '';
    input.placeholder = Translator.translate(placeholder || label);
    element.append(labelElement(label), input);

    return {element, getValue: () => input.value, destroy: () => {}};
}

/** Raw SVG markup — a textarea that shouldn't be spellchecked or line-wrapped into prose. */
export function svgField({label, value = ''}) {
    const field = textAreaField({label, value, placeholder: '<svg ...></svg>', rows: 3});
    const input = field.element.querySelector('textarea');
    input.spellcheck = false;

    return field;
}

/**
 * Rich text. The old blocks used a TextEditor widget writing HTML into a hidden input; here the
 * value lives in a contentEditable carrying the editor's own `editable` class, which is all the
 * format toolbar needs to offer bold / italic / link inside it (same as an accordion body).
 */
export function richTextField({label, value = '', placeholder = ''}) {
    const element = container();
    const input = document.createElement('div');
    input.classList.add(c.input, c.editableBlock);
    input.contentEditable = 'true';
    input.spellcheck = false;
    input.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, Translator.translate(placeholder || label));
    input.innerHTML = value ?? '';
    element.append(labelElement(label), input);

    return {element, getValue: () => input.innerHTML, destroy: () => {}};
}

/** Fixed set of choices. `options` is {value: label}. */
export function selectField({label, value = '', options = {}}) {
    const element = container();
    const select = document.createElement('select');
    select.classList.add(c.input);
    Object.entries(options).forEach(([optionValue, optionLabel]) => {
        const option = document.createElement('option');
        option.value = optionValue;
        option.textContent = Translator.translate(optionLabel);
        option.selected = value === optionValue;
        select.appendChild(option);
    });
    element.append(labelElement(label), select);

    return {element, getValue: () => select.value, destroy: () => {}};
}

/**
 * An image from the media library.
 *
 * Saves two keys, as the old blocks did: the media id and the filename. The filename is stored
 * bare and only prefixed with `imagePath` for display — prefixing the stored value would
 * compound the path on every save/load round trip.
 */
export function imageField({label, id = null, filename = null, imagePath = '', chooseText = 'Choose image'}) {
    const element = container();

    const preview = document.createElement('div');
    preview.classList.add(mediaLibrarySelectors.classes.initiator, 'pageBlockImage');
    preview.setAttribute(mediaLibrarySelectors.attributes.insertable, true);
    preview.setAttribute(mediaLibrarySelectors.attributes.allowImages, true);
    preview.setAttribute(mediaLibrarySelectors.attributes.multiple, false);

    const text = document.createElement('span');
    text.textContent = Translator.translate(chooseText);
    preview.appendChild(text);

    let mediaId = id ?? null;
    let src = filename ?? null;
    if (mediaId && src) {
        const image = document.createElement('img');
        image.src = imagePath + src;
        preview.appendChild(image);
    }

    const onInsert = (data) => {
        if (data.initiator !== preview) {
            return;
        }
        const media = data?.mediaData?.[0];
        if (!media?.img) {
            return;
        }
        preview.querySelector('img')?.remove();
        // The media library builds this markup with the full path already in it.
        preview.insertAdjacentHTML('beforeend', media.img);
        mediaId = media.id;
        src = media.filename;
    };
    window.mediaLibrary?.eventEmitter.on(mediaLibraryEvents.mediaReadyForInsert, onInsert);

    const onClick = () => window.mediaLibrary?.open(preview);
    preview.addEventListener('click', onClick);

    element.append(labelElement(label), preview);

    return {
        element,
        getValue: () => ({id: mediaId, filename: src}),
        destroy: () => {
            preview.removeEventListener('click', onClick);
            window.mediaLibrary?.eventEmitter.remove(mediaLibraryEvents.mediaReadyForInsert, onInsert);
        },
    };
}

/**
 * A repeating group of fields, as tabs — one tab per entry, the way the old editor's blocks
 * did it. The tab strip, the add button and the per-tab remove come from the shared
 * TabbedContent component, so these behave like every other tabbed thing in the admin.
 *
 * `build(itemData)` returns `{key: field}` for one entry; `getValue()` returns an array of
 * `{key: value}` objects in tab order.
 */
export function tabsField({label, itemLabel = 'Item', items = [], build, minItems = 0}) {
    const element = document.createElement('div');
    element.classList.add('pageBlockTabs');
    element.append(labelElement(label));

    const tabsContainer = document.createElement('div');
    tabsContainer.classList.add('pageBlockTabsContainer');
    // TabbedContent looks for the strip inside the container it is given, and builds its add
    // button into it — without this element it has nowhere to put one.
    const strip = document.createElement('div');
    strip.classList.add(tabbedContentSelectors.classes.tabs);
    tabsContainer.appendChild(strip);
    element.appendChild(tabsContainer);

    const tabbedContent = new TabbedContent(tabsContainer, {
        tabText: Translator.translate(itemLabel),
        appendNumberToTabText: true,
        tabContent: null,
    });
    tabbedContent.init();

    const entries = [];

    const populate = (tabContent, itemData) => {
        tabContent.classList.add('pageBlockTabPanel');
        const fields = build(itemData ?? {});
        Object.values(fields).forEach((field) => tabContent.appendChild(field.element));
        entries.push({tabContent, fields});
    };

    // A tab the author added: the component builds it, this fills it in.
    const onTabAdded = ({tabContent}) => populate(tabContent, {});
    tabbedContent.eventEmitter.on(tabbedContentEvents.tabAdded, onTabAdded);

    // A removed tab takes its fields with it — the media library listeners they registered
    // would otherwise outlive the DOM they were built for.
    const onTabRemoved = () => {
        for (let index = entries.length - 1; index >= 0; index--) {
            if (!entries[index].tabContent.isConnected) {
                Object.values(entries[index].fields).forEach((field) => field.destroy());
                entries.splice(index, 1);
            }
        }
    };
    tabbedContent.eventEmitter.on(tabbedContentEvents.tabRemoved, onTabRemoved);

    // Saved entries: add the tab silently (no event) and fill it from the data.
    (Array.isArray(items) ? items : []).forEach((itemData) => {
        const {tabContent} = tabbedContent.addTab(false);
        populate(tabContent, itemData);
    });
    // A block whose template expects a set number of entries starts with that many.
    while (entries.length < minItems) {
        const {tabContent} = tabbedContent.addTab(false);
        populate(tabContent, {});
    }
    if (entries.length) {
        tabbedContent.showTabContent('1');
    }

    return {
        element,
        // Tab order is what the author sees, so read the panels out of the DOM rather than
        // trusting the order they were created in.
        getValue: () => [...tabsContainer.querySelectorAll(`.${tabbedContentSelectors.classes.tabContent}`)]
            .map((tabContent) => entries.find((entry) => entry.tabContent === tabContent))
            .filter(Boolean)
            .map(({fields}) => Object.fromEntries(
                Object.entries(fields).map(([key, field]) => [key, field.getValue()])
            )),
        destroy: () => {
            tabbedContent.eventEmitter.remove(tabbedContentEvents.tabAdded, onTabAdded);
            tabbedContent.eventEmitter.remove(tabbedContentEvents.tabRemoved, onTabRemoved);
            entries.forEach((entry) => Object.values(entry.fields).forEach((field) => field.destroy()));
            entries.length = 0;
            tabbedContent.destroy();
        },
    };
}

/**
 * A fixed number of identical groups (About's two projects, Three Pillars' three pillars) —
 * a repeater without add/remove, because the frontend template expects exactly that many.
 */
export function groupField({label, itemLabel = 'Item', count = 1, items = [], build}) {
    const element = document.createElement('div');
    element.classList.add('pageBlockGroup');
    element.append(labelElement(label));

    const groups = [];
    for (let index = 0; index < count; index++) {
        const fields = build(items?.[index] ?? {});

        const row = document.createElement('div');
        row.classList.add('pageBlockGroupItem');

        const heading = document.createElement('span');
        heading.classList.add('pageBlockGroupItemLabel');
        heading.textContent = `${Translator.translate(itemLabel)} ${index + 1}`;
        row.appendChild(heading);

        Object.values(fields).forEach((field) => row.appendChild(field.element));
        element.appendChild(row);
        groups.push(fields);
    }

    return {
        element,
        getValue: () => groups.map((fields) => Object.fromEntries(
            Object.entries(fields).map(([key, field]) => [key, field.getValue()])
        )),
        destroy: () => groups.forEach((fields) => Object.values(fields).forEach((field) => field.destroy())),
    };
}

/**
 * A list of short lines - the bullet points a card or a project carries.
 *
 * These used to live inside one rich-text field as markup, which meant the list's colours and
 * spacing were typed into the content instead of coming from the stylesheet. Each entry is its
 * own plain string here, so the template decides how a list looks.
 *
 * `getValue()` returns an array of strings, in the order shown, with blank rows dropped.
 */
export function listField({label, items = [], addText = 'Add item', placeholder = ''}) {
    const element = document.createElement('div');
    element.classList.add('pageBlockList');
    element.append(labelElement(label));

    const rows = document.createElement('div');
    rows.classList.add('pageBlockListRows');
    element.appendChild(rows);

    const entries = [];

    const addRow = (value = '') => {
        const row = document.createElement('div');
        row.classList.add('pageBlockListRow');

        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add(c.input);
        input.value = value ?? '';
        input.placeholder = Translator.translate(placeholder || label);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.classList.add('pageBlockListRemove');
        // A glyph rather than the word: one row is one short line, and a "Remove" button on
        // each of them competes with the value for attention.
        remove.innerHTML = '&times;';
        remove.title = Translator.translate('Remove');
        remove.setAttribute('aria-label', Translator.translate('Remove'));
        const onRemove = () => {
            const entry = entries.find((candidate) => candidate.row === row);
            if (!entry) {
                return;
            }
            remove.removeEventListener('click', onRemove);
            entries.splice(entries.indexOf(entry), 1);
            row.remove();
        };
        remove.addEventListener('click', onRemove);

        row.append(input, remove);
        rows.appendChild(row);
        entries.push({row, input, remove, onRemove});
    };

    (Array.isArray(items) ? items : []).forEach((item) => addRow(typeof item === 'string' ? item : item?.text));

    const add = document.createElement('button');
    add.type = 'button';
    add.classList.add('pageBlockListAdd');
    add.innerHTML = `<span>+</span>${Translator.translate(addText)}`;
    const onAdd = () => addRow('');
    add.addEventListener('click', onAdd);
    element.appendChild(add);

    return {
        element,
        getValue: () => [...rows.querySelectorAll('input')]
            .map((input) => input.value.trim())
            .filter((value) => value !== ''),
        destroy: () => {
            entries.forEach((entry) => entry.remove.removeEventListener('click', entry.onRemove));
            entries.length = 0;
            add.removeEventListener('click', onAdd);
        },
    };
}

/** The heading a block shows above its fields, so a stack of section blocks stays readable. */
export function blockHeading(text) {
    const heading = document.createElement('div');
    heading.classList.add('pageBlockHeading');
    heading.textContent = Translator.translate(text);

    return heading;
}
