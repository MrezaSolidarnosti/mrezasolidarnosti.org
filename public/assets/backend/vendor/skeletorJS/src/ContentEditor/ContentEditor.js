import TopBar from "./TopBar/TopBar.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import Sidebar from "./Sidebar/Sidebar.js";
import Title from "./Title/Title.js";
import {events as titleEvents} from "./Title/events.js";
import Categories from "./Category/Categories.js";
import Authors from "./Author/Authors.js";
import Tags from "./Tag/Tags.js";
import FeaturedImage from "./FeaturedImage/FeaturedImage.js";
import {events} from "./events.js";
import Statuses from "./Status/Statuses.js";
import SaveButton from "./SaveButton/SaveButton.js";
import {events as saveEvents} from "./SaveButton/events.js";
import SaveValidation from "./SaveValidation/SaveValidation.js";
import Message from "../Message/Message.js";
import {contentEditorSelectors} from "./contentEditorSelectors.js";
import Blocks from "./Blocks/Blocks.js";
import Shortcuts from "./Shortcuts/Shortcuts.js";
import Shortcut from "../Shortcuts/Shortcut.js";
import Slug from "./Slug/Slug.js";
import SEO from "./SEO/SEO.js";
import Revisions from "./Revisions/Revisions.js";
import EntityTriggers from "./Blocks/Components/EntityTriggers/EntityTriggers.js";
import BlockSelection from "./Blocks/Components/BlockSelection/BlockSelection.js";
import {events as blockEvents} from "./Blocks/events.js";
import UnsavedGuard from "./UnsavedGuard/UnsavedGuard.js";
import CommandMenu from "./CommandMenu/CommandMenu.js";
import {transformText} from "./CommandMenu/transformText.js";
import CommandPalette from "./CommandPalette/CommandPalette.js";
import Translator from "../Translator/Translator.js";
import UserSettings from "./UserSettings/UserSettings.js";
import EditLock, {STATES as EDIT_LOCK_STATES} from "./EditLock/EditLock.js";
import {events as editLockEvents} from "./EditLock/events.js";

export default class ContentEditor {

    /**
     * Every module the editor can build, keyed by the name used in `config.modules`.
     *
     * `contentKey` is the module's property in the content object — the key it is read from
     * in `initialContent` *and* written to in the save payload. It is not always the module
     * name (`categories` owns `category`), which is why each entry states it.
     *
     * `getData: null` marks a module load-only: it reads `initialContent` but contributes
     * nothing to the save payload, because the data is not the editor's to own — revisions
     * belong to the backend. An *omitted* `getData` still means "use the default".
     */
    static MODULES = {
        title:         { class: Title,         contentKey: 'title',         getData: (m) => m.getValue(),                setData: (m, v) => m.setValue(v) },
        slug:          { class: Slug,          contentKey: 'slug',          getData: (m) => m.getData(),                 setData: (m, v) => m.setValue(v) },
        categories:    { class: Categories,    contentKey: 'category',      getData: (m) => m.getSelectedCategoryIds(),  setData: (m, v) => m.setSelectedCategoryIds(v) },
        authors:       { class: Authors,       contentKey: 'authors',       getData: (m) => m.getSelectedAuthorIds(),    setData: (m, v) => m.setSelectedAuthorIds(v) },
        tags:          { class: Tags,          contentKey: 'tags',          getData: (m) => m.getData(),                 setData: (m,v) => m.setData(v)},
        featuredImage: { class: FeaturedImage, contentKey: 'featuredImage', getData: (m) => m.getData(),                 setData: (m, v) => m.setFeaturedImage(v) },
        status:        { class: Statuses,      contentKey: 'status',        getData: (m) => m.getData(),                 setData: (m, v) => m.setData(v) },
        seo:           { class: SEO,           contentKey: 'seo',           getData: (m) => m.getData(),                 setData: (m, v) => m.setData(v) },
        revisions:     { class: Revisions,     contentKey: 'revisions',     getData: null,                               setData: (m, v) => m.setData(v) },
    };

    #setupComplete = false;
    #readOnly = false;
    config;
    contentContainer;
    initialContent = null;
    messagesContainer = null;
    eventEmitter = new EventEmitter();
    topBar = null;
    sidebar = null;
    saveHandler = null;
    dataForSave = null;
    saveValidationHandler = null;
    blockHandler = null;
    shortcutsHandler = null;
    unsavedGuard = null;
    commandMenu = null;
    commandPalette = null;
    userSettings = null;
    editLock = null;
    modules = new Map();
    constructor({config = {}, initialContent = {}}) {
        this.config = config;
        this.initialContent = initialContent;
        // Read-only can start on from config, or be flipped before init() via setReadOnly().
        // Either way it is applied to every part at the top of init(), before content renders.
        this.#readOnly = config.readOnly === true;
        this.loadHandlers();
    }

    async init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#applyConfigToElements();
        // Only when whole-editor read-only was actually requested (config.readOnly or a pre-init
        // setReadOnly) do we push it onto every part. Otherwise we leave each part alone, so a
        // developer can still freeze *some* parts before init and leave the rest editable.
        if (this.#readOnly) {
            this.#applyReadOnly();
        }
        this.blockHandler.init();
        await this.blockHandler.loadBlockModules();
        this.eventEmitter.emit(events.contentEditorPreload);

        this.messagesContainer = document.getElementById(contentEditorSelectors.ids.messagesContainer);
        if(!this.messagesContainer) {
            throw new Error(`#${contentEditorSelectors.ids.messagesContainer} element missing.`)
        }

        this.#applyModuleVisibility();

        this.topBar.init();
        this.sidebar.init();
        this.saveHandler.init();
        this.#registerShortcuts();
        this.shortcutsHandler.init();
        this.#registerBlockCommands();
        this.commandMenu.init();
        this.commandPalette.init();
        this.editLock.init();
        // After #applyConfigToElements: a stored width is the person's own override of the
        // project's config.width, so it has to be applied second to win.
        this.userSettings.init();
        this.unsavedGuard.init();   // before finalize — it baselines on that event
        this.modules.forEach(({instance}) => instance.init());

        // Answer text blocks' placeholder request with the configured command trigger. Must be
        // registered before #setInitialContent, which renders the initial blocks (they ask on
        // render); the rest of the event wiring in #listenToEvents runs after that.
        this.eventEmitter.on(events.commandTriggerRequested, (request) => {
            request.trigger = this.commandMenu.getTrigger();
        });

        this.#setInitialContent();
        this.#runContentTransforms();   // before finalize: the baselines are captured there

        this.eventEmitter.emit(events.contentEditorFinalize);

        this.#listenToEvents();
        this.#setupComplete = true;

    }


    #setElements() {
        this.contentContainer = document.getElementById(contentEditorSelectors.ids.contentContainer);
    }

    #applyConfigToElements() {
        if(this.config.width) {
            this.contentContainer.style.width = this.config.width;
        }
    }

    loadHandlers() {
        this.topBar = new TopBar({eventEmitter: this.eventEmitter});
        this.sidebar = new Sidebar({eventEmitter: this.eventEmitter});
        // Also a BaseModule, just an always-on one rather than a config.modules entry — so it
        // gets config on the same terms as the modules built in #buildModules().
        this.saveHandler = new SaveButton({eventEmitter: this.eventEmitter, config: this.config});
        this.saveValidationHandler = new SaveValidation();
        this.blockHandler = new Blocks({config:this.config, eventEmitter: this.eventEmitter});
        this.shortcutsHandler = new Shortcuts();
        this.commandMenu = new CommandMenu({
            eventEmitter: this.eventEmitter,
            blocks: this.blockHandler,
            trigger: this.config.commandTrigger,
        });
        this.unsavedGuard = new UnsavedGuard({
            eventEmitter: this.eventEmitter,
            getData: () => this.getDataForSave(),
            // On by default; a project opts out with `config.unsavedGuard: false`. Even when
            // off it still baselines, so setEnabled(true) can switch it on later mid-session.
            enabled: this.config.unsavedGuard !== false,
        });
        // Opt-in and off by default: the palette turns on only with an explicit
        // `config.commandPalette.enabled === true`. It can also be flipped at runtime, but the
        // Ctrl+K binding is created (and listed) only when it starts enabled.
        const paletteConfig = this.config.commandPalette;
        this.commandPalette = new CommandPalette({
            eventEmitter: this.eventEmitter,
            enabled: !!paletteConfig && paletteConfig.enabled === true,
            search: paletteConfig ? paletteConfig.search : null,
        });
        this.editLock = new EditLock({eventEmitter: this.eventEmitter});
        // Takes the editor itself, not just the emitter: a setting's `apply` acts on the canvas
        // and may read config, and both live here.
        this.userSettings = new UserSettings({eventEmitter: this.eventEmitter, editor: this});
        this.#buildModules();
    }

    #buildModules() {
        const names = this.config.modules || Object.keys(ContentEditor.MODULES);
        names.forEach((name) => {
            const entry = ContentEditor.MODULES[name];
            if (!entry) {
                return;
            }
            if(!document.querySelector(`[${contentEditorSelectors.attributes.module}="${name}"]`)) {
                console.warn(`Module ${name} does not have its DOM section.`);
                return;
            }
            this.modules.set(name, {
                instance: new entry.class({eventEmitter: this.eventEmitter, config: this.config}),
                entry
            });
        });
    }

    getModule(name) {
        const module = this.modules.get(name);
        return module ? module.instance : null;
    }

    isReadOnly() {
        return this.#readOnly;
    }

    /**
     * Make the whole editor read-only (or editable again) in one call, instead of toggling the
     * block handler, save button and each module by hand.
     *
     * Call it *before* `init()` (or use `config.readOnly`) and every part picks the state up as
     * it builds — the fully-supported path. Calling it *after* init flips every part's flag and
     * emits `readOnlyChanged`, but the live re-application to already-rendered blocks/modules is
     * a separate, in-progress piece; the pre-init path is what this change guarantees.
     */
    setReadOnly(value) {
        this.#readOnly = !!value;
        if (this.#setupComplete) {
            this.#applyReadOnly();
            this.eventEmitter.emit(events.readOnlyChanged, this.#readOnly);
        }
        return this;
    }

    // Fan the current read-only state out to every part that understands it.
    #applyReadOnly() {
        this.blockHandler.setReadOnly(this.#readOnly);
        this.saveHandler.setReadOnly(this.#readOnly);
        this.modules.forEach(({instance}) => instance.setReadOnly?.(this.#readOnly));
    }

    static CONTENT_TRANSFORMS = new Map();

    static registerContentTransform(definition) {
        if (!definition || !definition.key || typeof definition.transform !== 'function') {
            throw new Error('A content transform needs a `key` and a `transform` function.');
        }
        ContentEditor.CONTENT_TRANSFORMS.set(definition.key, definition);
    }

    static unRegisterContentTransform(key) {
        ContentEditor.CONTENT_TRANSFORMS.delete(key);
    }

    static registerModule(name, definition) {
        ContentEditor.MODULES[name] = {contentKey: name, ...definition};
    }

    // The block/content commands that ship with the editor — the `//` menu is scoped to the
    // active block and its text (global, editor-wide commands get their own menu). Registered
    // here rather than in the CommandMenu module so their callbacks close over `this` — that's
    // how a command reaches the editor, its modules and the block manager, without the menu
    // having to pass any of it through. A project registers its own the same way, closing over
    // its `contentEditor`.
    #registerBlockCommands() {
        CommandMenu.register({
            key: 'date',
            label: 'Insert date',
            keywords: ['date', 'today'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-80q-33 0-56.5-23.5T120-160v-560q0-33 23.5-56.5T200-800h40v-80h80v80h320v-80h80v80h40q33 0 56.5 23.5T840-720v560q0 33-23.5 56.5T760-80H200Zm0-80h560v-400H200v400Zm0-480h560v-80H200v80Zm0 0v-80 80Z"/></svg>`,
            onSelect: ({insert}) => insert(new Date().toLocaleDateString()),
        });
        CommandMenu.register({
            key: 'time',
            label: 'Insert time',
            keywords: ['time', 'now'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M582-298 440-440v-200h80v167l118 118-56 57ZM440-720v-80h80v80h-80Zm280 280v-80h80v80h-80ZM440-160v-80h80v80h-80ZM160-440v-80h80v80h-80ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>`,
            onSelect: ({insert}) => insert(new Date().toLocaleTimeString()),
        });
        // Characters that are awkward to type.
        CommandMenu.register({
            key: 'emdash',
            label: 'Em dash',
            keywords: ['emdash', 'dash', 'em', '\u2014'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 448 512" fill="#e3e3e3"><path d="M0 256c0-17.7 14.3-32 32-32l384 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 288c-17.7 0-32-14.3-32-32z"/></svg>`,
            onSelect: ({insert}) => insert('\u2014'),
        });
        CommandMenu.register({
            key: 'ellipsis',
            label: 'Ellipsis',
            keywords: ['ellipsis', 'dots', '\u2026'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 448 512" fill="#e3e3e3"><path d="M0 256a56 56 0 1 1 112 0 56 56 0 1 1 -112 0zm168 0a56 56 0 1 1 112 0 56 56 0 1 1 -112 0zm224-56a56 56 0 1 1 0 112 56 56 0 1 1 0-112z"/></svg>`,
            onSelect: ({insert}) => insert('\u2026'),
        });
        CommandMenu.register({
            key: 'arrow',
            label: 'Arrow',
            keywords: ['arrow', 'right', '\u2192'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 576 512" fill="#e3e3e3"><path d="M566.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-128-128c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L466.7 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l434.7 0-73.4 73.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l128-128z"/></svg>`,
            onSelect: ({insert}) => insert('\u2192'),
        });
        CommandMenu.register({
            key: 'copyright',
            label: 'Copyright',
            keywords: ['copyright', 'copy'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M400-320h160q17 0 28.5-11.5T600-360v-80h-80v40h-80v-160h80v40h80v-80q0-17-11.5-28.5T560-640H400q-17 0-28.5 11.5T360-600v240q0 17 11.5 28.5T400-320Zm80 240q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>`,
            onSelect: ({insert}) => insert('\u00A9'),
        });
        CommandMenu.register({
            key: 'euro',
            label: 'Euro',
            keywords: ['euro', 'eur', '\u20AC', 'currency'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120q-118 0-210-67T260-360H120v-80h122q-2-11-2-20v-40q0-9 2-20H120v-80h140q38-106 130-173t210-67q69 0 130.5 24T840-748l-70 70q-35-29-78.5-45.5T600-740q-75 0-136.5 38.5T370-600h230v80H344q-2 11-3 20t-1 20q0 11 1 20t3 20h256v80H370q32 63 93.5 101.5T600-220q48 0 92.5-16.5T770-282l70 70q-48 44-109.5 68T600-120Z"/></svg>`,
            onSelect: ({insert}) => insert('\u20AC'),
        });
        CommandMenu.register({
            key: 'pound',
            label: 'Pound',
            keywords: ['pound', 'gbp', 'sterling', '\u00A3', 'currency'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-120v-80l16.5-10q16.5-10 36-29.5t35.5-50q16-30.5 16-70.5 0-11-1.5-21t-3.5-19h-99v-80h60q-21-33-40.5-69.5T240-640q0-92 64-156t156-64q71 0 126 39t79 101l-74 31q-15-40-50.5-65.5T460-780q-58 0-99 41t-41 99q0 48 24 80t49 80h167v80H421q2 9 2.5 19t.5 21q0 50-17.5 90T364-200h196q40 0 61-21t29-54l70 35q-11 55-56.5 87.5T560-120H240Z"/></svg>`,
            onSelect: ({insert}) => insert('\u00A3'),
        });
        CommandMenu.register({
            key: 'check',
            label: 'Check',
            keywords: ['check', 'tick', 'done', '\u2713'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z"/></svg>`,
            onSelect: ({insert}) => insert('\u2713'),
        });
        // Inserts a footnote marker at the caret and drops focus into the new note. Only offered
        // when core/footnotes is in config.blocks — without it the controller has nowhere to
        // append the note. The token is already removed by the time this runs, so the marker
        // lands where `//footnote` was typed.
        CommandMenu.register({
            key: 'footnote',
            label: 'Footnote',
            keywords: ['footnote', 'note', 'reference', 'citation'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-120v-264L254-197l-57-57 187-186H120v-80h264L197-706l57-57 186 187v-264h80v264l186-187 57 57-187 186h264v80H576l187 186-57 57-186-187v264h-80Z"/></svg>`,
            isVisible: ({block}) => !!block
                && block.constructor.isText
                && this.blockHandler.isBlockRegistered('core/footnotes'),
            onSelect: () => this.blockHandler.insertFootnote(),
        });
        CommandMenu.register({
            key: 'duplicate',
            label: 'Duplicate this block',
            keywords: ['duplicate', 'copy', 'clone'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240q-33 0-56.5-23.5T240-320v-480q0-33 23.5-56.5T320-880h480q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H320Zm0-80h480v-320H520v-160H320v480ZM160-80q-33 0-56.5-23.5T80-160v-560h80v560h560v80H160Zm160-720v480-480Z"/></svg>`,
            isVisible: ({block}) => !!block && !block.constructor.hidden,
            onSelect: ({block}) => this.eventEmitter.emit(blockEvents.duplicateBlock, block.id),
        });
        CommandMenu.register({
            key: 'delete',
            label: 'Delete this block',
            keywords: ['delete', 'remove'],
            icon: `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M560-280H120v-400h720v120h-80v-40H200v240h360v80Zm-360-80v-240 240Zm440 104 84-84-84-84 56-56 84 84 84-84 56 56-83 84 83 84-56 56-84-83-84 83-56-56Z"/></svg>`,
            isVisible: ({block}) => !!block && block.constructor.canBeDeleted(),
            onSelect: ({block}) => this.eventEmitter.emit(blockEvents.deleteBlock, block.id),
        });
        // Bare, these rewrite the whole block: there is never a selection to act on, because
        // the menu only opens on a collapsed caret. With an argument they insert instead —
        // `//uppercase hello` drops HELLO at the caret and leaves the block alone.
        this.#registerCaseCommand('uppercase', 'Uppercase', ['uppercase', 'caps', 'shout'],
            `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M660-240v-248l-64 64-56-56 160-160 160 160-56 56-64-64v248h-80Zm-540 0 165-440h79l165 440h-76l-39-113H236l-40 113h-76Zm139-177h131l-65-182h-4l-62 182Z"/></svg>`,
            (text) => text.toUpperCase());
        this.#registerCaseCommand('lowercase', 'Lowercase', ['lowercase', 'small'],
            `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M300-240q-51 0-81-27.5T189-340q0-44 34.5-72.5T312-441q23 0 45 4t38 11v-12q0-29-20.5-47T320-503q-23 0-42 9.5T245-466l-47-35q24-29 54.5-43t68.5-14q69 0 103 32.5t34 97.5v178h-63v-37h-4q-14 23-38 35t-53 12Zm12-54q35 0 59.5-24t24.5-56q-14-8-33.5-12.5T324-391q-32 0-50 14t-18 37q0 20 16 33t40 13Zm388 54L540-400l56-56 64 64v-248h80v248l64-64 56 56-160 160Z"/></svg>`,
            (text) => text.toLowerCase());
    }

    // Shared shape for the case commands: argument -> insert it transformed, bare -> rewrite
    // the block in place. Hidden on non-text blocks, which have no prose to speak of.
    #registerCaseCommand(key, label, keywords, icon, transform) {
        CommandMenu.register({
            key,
            label,
            keywords,
            icon,
            isVisible: ({block}) => !!block && block.constructor.isText,
            onSelect: ({block, query, insert}) => {
                if (query) {
                    insert(transform(query));
                    return;
                }
                transformText(block.getContainer(), transform);
            },
        });
    }

    // Registered transforms, in registration order, over the rendered content. The observer is
    // paused for the whole batch so none of it counts as an edit. A transform that throws is
    // reported and the rest still run — one bad transform shouldn't stop the editor loading.
    #runContentTransforms() {
        if (!ContentEditor.CONTENT_TRANSFORMS.size) {
            return;
        }
        if (!this.contentContainer) {
            return;
        }
        this.blockHandler.runWithoutObserving(() => {
            ContentEditor.CONTENT_TRANSFORMS.forEach((definition) => {
                try {
                    definition.transform(this.contentContainer, {editor: this});
                } catch (error) {
                    console.error(`ContentEditor: content transform "${definition.key}" threw.`, error);
                }
            });
        });
    }

    #applyModuleVisibility() {
        const enabled = new Set(this.config.modules || Object.keys(ContentEditor.MODULES));
        document.querySelectorAll(`[${contentEditorSelectors.attributes.module}]`).forEach((element) => {
            if (!enabled.has(element.getAttribute(contentEditorSelectors.attributes.module))) {
                element.remove();
            }
        });
    }


    #registerShortcuts() {
        // Don't hijack Ctrl+Z/Y while a form field or the title (a contentEditable outside
        // the block content) is focused — those keep their native undo. Focus outside the
        // content (e.g. <body>, after a restore drops focus) still counts, so consecutive
        // undos keep working.
        const notEditingAField = () => {
            const active = document.activeElement;
            if (!active) {
                return true;
            }
            if (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT') {
                return false;
            }
            return !(active.isContentEditable && !this.contentContainer.contains(active));
        };

        this.shortcutsHandler.registerShortcut(new Shortcut({
            container: document.body,
            modifier: Shortcut.MODIFIERS.CTRL,
            key: 'Z',
            event: 'keydown',
            preventDefault: true,
            description: 'Undo',
            callback: () => this.blockHandler.undo(),
            constraints: [notEditingAField]
        }));

        this.shortcutsHandler.registerShortcut(new Shortcut({
            container: document.body,
            modifier: Shortcut.MODIFIERS.CTRL,
            key: 'Y',
            event: 'keydown',
            preventDefault: true,
            description: 'Redo',
            callback: () => this.blockHandler.redo(),
            constraints: [notEditingAField]
        }));

        // Ctrl/Cmd+K opens the command palette. Bound (and listed in the shortcuts panel) only
        // when the palette is enabled — a disabled palette shouldn't advertise a dead shortcut.
        // preventDefault stops the browser's own Ctrl+K (focus the search/address bar).
        if (this.commandPalette.isEnabled()) {
            this.shortcutsHandler.registerShortcut(new Shortcut({
                container: document.body,
                modifier: Shortcut.MODIFIERS.CTRL,
                key: 'K',
                event: 'keydown',
                preventDefault: true,
                description: 'Command palette',
                callback: () => this.commandPalette.toggle(),
            }));
        }

        // Footnotes are only available when the (auto-managed) footnotes block is enabled.
        if ((this.config.blocks || []).includes('core/footnotes')) {
            this.shortcutsHandler.registerShortcut(new Shortcut({
                container: document.body,
                modifier: Shortcut.MODIFIERS.ALT,
                key: 'F',
                event: 'keydown',
                preventDefault: true,
                description: 'Insert footnote',
                callback: () => this.blockHandler.insertFootnote(),
                constraints: [() => content.contains(document.activeElement)]
            }));
        }

        // List what a block selection can do, in its own section of the shortcuts panel.
        BlockSelection.SHORTCUTS.forEach(({keys, description}) => {
            this.shortcutsHandler.registerSelectionShortcut(keys, description);
        });

        // List the registered entity triggers in their own section of the shortcuts panel.
        EntityTriggers.TRIGGERS.forEach((definition, sequence) => {
            this.shortcutsHandler.registerSequence(sequence, definition.description || '');
        });

        // The command menu's own trigger (default '//', but configurable via config.commandTrigger
        // or commandMenu.setTrigger). Passed as a function so the panel shows the current value —
        // it re-reads on each open, so a runtime change is reflected.
        this.shortcutsHandler.registerSequence(
            () => this.commandMenu.getTrigger(),
            'Open the command menu'
        );
    }

    #listenToEvents() {
        this.eventEmitter.on(saveEvents.saveInitiated, this.#handleSave);
        // Being taken over means this editor is no longer the authority on the post, so the
        // whole thing goes read-only behind the dialog. The dialog is the real barrier — this
        // keeps isReadOnly() and the save button honest about what just happened.
        this.eventEmitter.on(editLockEvents.editLockShown, ({state}) => {
            if (state === EDIT_LOCK_STATES.takenOver) {
                this.setReadOnly(true);
            }
        });
        // Modules only ever get an eventEmitter, so this is how one reads the live content.
        // The emitter is synchronous: the payload is filled in before emit() returns.
        this.eventEmitter.on(events.currentContentRequested, (request) => {
            request.content = this.getDataForSave();
        });
        this.eventEmitter.on(events.blockLabelsRequested, (request) => {
            request.labels = this.blockHandler.getBlockLabels();
        });
        this.eventEmitter.on(events.revisionRevertRequested, (revision) => {
            this.applyContent(revision.content);
            this.eventEmitter.emit(events.revisionReverted, revision);
        });
        // Enter in the title moves into the body — focus the first block.
        this.eventEmitter.on(titleEvents.titleEnter, () => {
            this.blockHandler.focusFirstBlock();
        });
    }

    #handleSave = async () => {
        this.clearMessages();
        const valid = this.saveValidationHandler.validate();
        if(!valid.ok) {
            // The same call a project makes for its own messages — a validation failure has no
            // special surface, it just gets there first.
            valid.failed.forEach((failed) => this.message(failed.getMessages()));
            return;
        }
        this.dataForSave = this.getDataForSave();
        this.eventEmitter.emit(events.beforeSave, this.dataForSave);
        const response = await this.save(this.dataForSave);
        this.clearMessages();
        if(response.messages) {
            response.messages.forEach((message) => {
                const type = response.success ? Message.TYPES.SUCCESS : Message.TYPES.ERROR;
                this.notify(message, {type, timeout: null});
            });
        }
        if(response.success) {
            if(this.unsavedGuard.isEnabled()) {
                this.unsavedGuard.markClean();
            }
            this.eventEmitter.emit(events.afterSave, this.dataForSave);
        }
    }

    save = async (data) => {
        throw new Error('ContentEditor.save() is abstract; a project must implement it. Save must return an instance of SaveResponse.');
    }

    /**
     * Put a message on screen, using the same surface the editor uses for its own.
     *
     * Two shapes, because the editor already needed both: `notify()` is the transient toast a
     * clipboard copy shows, `message()` is the persistent panel a failed save validation writes.
     * The difference is only how long it stays, so they share everything else.
     *
     * Text is passed through the Translator like every other user-facing string, so a project
     * that ships a catalogue gets these translated too.
     *
     *   editor.notify('Draft saved');
     *   editor.notify('Could not reach the server', {type: 'error', timeout: 6000});
     *   editor.message('Category is required', {type: 'error'});
     *   editor.message(['Title is required', 'Category is required'], {type: 'error'});
     *   editor.clearMessages();
     *
     * @param {string|string[]} message  one message, or several spawned in order.
     * @param {{type?: string, timeout?: number|null, prepend?: boolean}} [options]
     *        `type` is one of Message.TYPES ('info' | 'success' | 'warning' | 'error').
     * @returns {this}
     */
    notify(message, {type = Message.TYPES.SUCCESS, timeout = 3000, prepend = false} = {}) {
        return this.#spawnMessages(message, type, Message.VIEW_TYPES.NOTIFICATION, timeout, prepend);
    }

    /** A message that stays until dismissed or cleared — what a failed validation uses. */
    message(message, {type = Message.TYPES.ERROR, prepend = false} = {}) {
        return this.#spawnMessages(message, type, Message.VIEW_TYPES.STATIC, null, prepend);
    }

    /**
     * One method per Message.TYPES value, so the common case reads as what it is.
     *
     *   editor.success('Draft saved');
     *   editor.error('Category is required');
     *   editor.warning('That headline is over the limit');
     *   editor.info('Restored from an autosave');
     *
     * The default view differs by type on purpose: something informational has been read by the
     * time it fades, but a warning or an error is asking for a change — one that disappears
     * before it is acted on may as well not have been shown. Override per call with
     * `{view: 'notification' | 'static'}`, and set `timeout` on either view (a static message
     * with a timeout self-dismisses; a notification with `timeout: null` stays).
     */
    info(message, options = {}) {
        return this.#typedMessage(Message.TYPES.INFO, Message.VIEW_TYPES.NOTIFICATION, message, options);
    }

    success(message, options = {}) {
        return this.#typedMessage(Message.TYPES.SUCCESS, Message.VIEW_TYPES.NOTIFICATION, message, options);
    }

    warning(message, options = {}) {
        return this.#typedMessage(Message.TYPES.WARNING, Message.VIEW_TYPES.STATIC, message, options);
    }

    error(message, options = {}) {
        return this.#typedMessage(Message.TYPES.ERROR, Message.VIEW_TYPES.STATIC, message, options);
    }

    #typedMessage(type, defaultView, message, {view = defaultView, timeout, prepend = false} = {}) {
        // `timeout` is honoured on both views; undefined means "whatever this view usually does",
        // which is why it can't just default in the signature — an explicit null has to be able
        // to mean "never dismiss".
        const ephemeralTimeout = timeout === undefined
            ? (view === Message.VIEW_TYPES.NOTIFICATION ? 3000 : null)
            : timeout;
        return this.#spawnMessages(message, type, view, ephemeralTimeout, prepend);
    }

    /** Remove what's on screen. `type` narrows it to one Message.TYPES value. */
    clearMessages(type = null) {
        if (this.messagesContainer) {
            Message.removeMessages(this.messagesContainer, type);
        }
        return this;
    }

    #spawnMessages(message, type, viewType, ephemeralTimeout, prepend) {
        // The container is resolved in init(); calling earlier would silently spawn nothing, so
        // say so rather than letting the message disappear.
        if (!this.messagesContainer) {
            console.warn('ContentEditor: no messages container yet — call this after init().');
            return this;
        }
        const messages = Array.isArray(message) ? message : [message];
        messages.forEach((entry) => {
            Message.spawn({
                message: Translator.translate(entry),
                type,
                view: {type: viewType, container: this.messagesContainer, prepend},
                ephemeralTimeout,
            });
        });
        return this;
    }

    getDataForSave() {
        const data = {blocks: this.blockHandler.getBlockData()};
        this.modules.forEach(({instance, entry}) => {
            if (!entry.contentKey) {
                return;
            }
            // An explicit null getData means load-only: the module reads from initialContent
            // but contributes nothing to the save payload. Undefined still means "default".
            const getData = entry.getData === undefined ? ((m) => m.getData()) : entry.getData;
            if (!getData) {
                return;
            }
            data[entry.contentKey] = getData(instance);
        });
        return data;
    }

    /**
     * Applies a whole content snapshot in the save shape — what a revision revert does.
     *
     * Mirrors #setInitialContent, except the blocks are reconciled rather than re-rendered,
     * so untouched blocks keep their elements and ids.
     */
    applyContent(content = {}) {
        this.modules.forEach(({instance, entry}) => {
            if (!entry.contentKey || content[entry.contentKey] === undefined) {
                return;
            }
            const setData = entry.setData || ((m, v) => m.setData(v));
            setData(instance, content[entry.contentKey]);
        });
        this.blockHandler.restoreContent(content.blocks || []);
        // restoreContent deliberately runs with the observer off, so without this nothing
        // downstream would notice: History would not record the revert (leaving it
        // un-undoable) and the footnotes list would not renumber.
        this.eventEmitter.emit(blockEvents.contentChanged);
    }


    #setInitialContent() {
        this.modules.forEach(({instance, entry}) => {
            if (!entry.contentKey) {
                return;
            }
            const value = this.#getInitialContentProp(entry.contentKey);
            if (value === null) {
                return;
            }
            const setData = entry.setData || ((m, v) => m.setData(v));
            setData(instance, value);
        });
        if(!this.initialContent.blocks || this.initialContent.blocks.length === 0) {
            this.blockHandler.renderInitial();
            const titleModule = this.getModule('title');
            if(titleModule && (!this.initialContent.title || this.initialContent.title.trim() === '')) {
                titleModule.focus();
            }
        } else {
            this.initialContent.blocks.forEach((block) => {
                this.blockHandler.renderBlock(block.type, block, null, 'end', false);
            });
        }
        this.sidebar.openNavItem(document.getElementById(contentEditorSelectors.ids.sidebarEntityContent));
    }

    #getInitialContentProp(prop) {
        if(this.initialContent && this.initialContent[prop]) {
            return this.initialContent[prop];
        }
        return null;
    }

    destroy() {
        this.topBar.destroy();
        this.topBar = null;
        this.sidebar.destroy();
        this.sidebar = null;
        this.saveHandler.destroy();
        this.saveHandler = null;
        this.saveValidationHandler.destroy();
        this.saveValidationHandler = null;
        this.blockHandler.destroy();
        this.blockHandler = null;
        this.shortcutsHandler.destroy();
        this.shortcutsHandler = null;
        this.unsavedGuard.destroy();
        this.unsavedGuard = null;
        this.commandMenu.destroy();
        this.commandMenu = null;
        this.commandPalette.destroy();
        this.commandPalette = null;
        this.editLock.destroy();
        this.editLock = null;
        this.userSettings.destroy();
        this.userSettings = null;

        this.modules.forEach(({instance}) => {
            instance.destroy();
        });
        this.modules.clear();

        this.eventEmitter.destroy();
        this.eventEmitter = null;
    }
}