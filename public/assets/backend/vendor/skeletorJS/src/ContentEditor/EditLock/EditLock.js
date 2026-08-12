import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import Translator from "../../Translator/Translator.js";

const LOCK_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm0-80h480v-400H240v400Zm296.5-143.5Q560-327 560-360t-23.5-56.5Q513-440 480-440t-56.5 23.5Q400-393 400-360t23.5 56.5Q447-280 480-280t56.5-23.5ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80ZM240-160v-400 400Z"/></svg>`;

// Shown in the avatar slot when the lock holder has no picture — or when we don't know who
// they are at all. The slot is never left empty, so the dialog is the same height either way.
const AVATAR_FALLBACK_ICON = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M367-527q-47-47-47-113t47-113q47-47 113-47t113 47q47 47 47 113t-47 113q-47 47-113 47t-113-47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm296.5-343.5Q560-607 560-640t-23.5-56.5Q513-720 480-720t-56.5 23.5Q400-673 400-640t23.5 56.5Q447-560 480-560t56.5-23.5ZM480-640Zm0 400Z"/></svg>`;

export const STATES = Object.freeze({
    alreadyEditing: 'alreadyEditing',
    takenOver: 'takenOver'
});

const COPY = Object.freeze({
    [STATES.alreadyEditing]: {
        title: 'This post is already being edited',
        message: '%s is currently editing this post.',
        fallbackMessage: 'Someone else is currently editing this post.',
    },
    [STATES.takenOver]: {
        title: 'Editing was taken over',
        message: '%s has taken over editing this post.',
        fallbackMessage: 'Someone else has taken over editing this post.',
    },
});

export default class EditLock {

    /**
     * The buttons, keyed so a project can replace or drop one.
     *
     *   EditLock.registerAction({
     *       key: 'contact',
     *       label: 'Message them',
     *       states: [STATES.alreadyEditing],   // omit for both dialogs
     *       order: 5,                          // lower renders first (built-ins are 1 and 2)
     *       primary: false,
     *       onClick: ({state, user, lock}) => { … },   // optional — see below
     *   });
     *
     *   EditLock.unRegisterAction('takeOver');
     *
     * `onClick` is optional because every button also emits `editLockAction` with its key; a
     * project can drive everything off that one listener instead of supplying callbacks.
     */
    static ACTIONS = new Map();

    static registerAction(definition) {
        if (!definition || !definition.key) {
            throw new Error('An edit-lock action needs a `key`.');
        }
        if (!definition.label) {
            throw new Error(`Edit-lock action "${definition.key}" needs a \`label\`.`);
        }
        EditLock.ACTIONS.set(definition.key, definition);
    }

    static unRegisterAction(key) {
        EditLock.ACTIONS.delete(key);
    }

    #setupComplete = false;
    #eventEmitter;
    #dialog = null;
    #titleElement = null;
    #messageElement = null;
    #metaElement = null;
    #avatarElement = null;
    #actionsElement = null;
    #state = null;
    #user = null;
    #listeners = [];   // {element, event, handler} for the action buttons of the current render

    constructor({eventEmitter}) {
        this.#eventEmitter = eventEmitter;
    }

    init() {
        if (this.#setupComplete) {
            return this;
        }
        this.#build();
        this.#setupComplete = true;
        return this;
    }

    showAlreadyEditing({user = null, since = null} = {}) {
        return this.#show(STATES.alreadyEditing, user, since);
    }

    /** The lock was taken while this editor was open. */
    showTakenOver({user = null} = {}) {
        return this.#show(STATES.takenOver, user, null);
    }

    #show(state, user, since) {
        if (!this.#setupComplete) {
            return this;
        }
        this.#state = state;
        this.#user = user;

        const copy = COPY[state];
        this.#titleElement.textContent = Translator.translate(copy.title);
        this.#messageElement.textContent = user && user.name
            ? Translator.translate(copy.message).replace('%s', user.name)
            : Translator.translate(copy.fallbackMessage);

        this.#renderAvatar(user);
        this.#renderMeta(since);
        this.#renderActions();

        if (!this.#dialog.open) {
            this.#dialog.showModal();
        }
        // Focus the primary action (or the first one) rather than letting the dialog pick, so
        // Enter does the expected thing straight away.
        const preferred = this.#actionsElement
            .querySelector(`.${contentEditorSelectors.classes.editLockActionPrimary}`)
            || this.#actionsElement.firstElementChild;
        preferred?.focus();

        this.#eventEmitter.emit(events.editLockShown, {state, user});
        return this;
    }

    hide() {
        // State is cleared *before* closing: #handleClose re-opens whenever a state is still
        // active, so leaving it set here would make hide() fight itself.
        const state = this.#state;
        this.#state = null;
        this.#user = null;
        if (this.#dialog && this.#dialog.open) {
            this.#dialog.close();
        }
        this.#clearActionListeners();
        this.#eventEmitter.emit(events.editLockHidden, {state});
        return this;
    }

    isOpen() {
        return !!this.#dialog && this.#dialog.open;
    }

    getState() {
        return this.#state;
    }

    #build() {
        const c = contentEditorSelectors.classes;
        this.#dialog = document.createElement('dialog');
        this.#dialog.classList.add(c.editLock);
        // An alert dialog rather than a plain one: it demands a decision, and screen readers
        // should announce it on open rather than waiting to be explored.
        this.#dialog.setAttribute('role', 'alertdialog');
        this.#dialog.setAttribute('aria-modal', 'true');

        const icon = document.createElement('span');
        icon.classList.add(c.editLockIcon);
        icon.innerHTML = LOCK_ICON;

        this.#avatarElement = document.createElement('span');
        this.#avatarElement.classList.add(c.editLockAvatar);

        this.#titleElement = document.createElement('h2');
        this.#titleElement.classList.add(c.editLockTitle);
        this.#dialog.setAttribute('aria-labelledby', contentEditorSelectors.ids.editLockTitle);
        this.#titleElement.id = contentEditorSelectors.ids.editLockTitle;

        this.#messageElement = document.createElement('p');
        this.#messageElement.classList.add(c.editLockMessage);

        this.#metaElement = document.createElement('p');
        this.#metaElement.classList.add(c.editLockMeta, contentEditorSelectors.classes.hidden);

        this.#actionsElement = document.createElement('div');
        this.#actionsElement.classList.add(c.editLockActions);

        this.#dialog.append(
            icon,
            this.#avatarElement,
            this.#titleElement,
            this.#messageElement,
            this.#metaElement,
            this.#actionsElement
        );
        document.body.appendChild(this.#dialog);

        this.#dialog.addEventListener('cancel', this.#handleCancel);
        // Cancelling `cancel` is not absolute — a browser may force a modal dialog shut (Chrome's
        // close-watcher does this for repeated close requests without user activation), and a
        // stray close() from anywhere else would do the same. Either would leave the editor fully
        // exposed with no barrier and no way back, so a close while a state is still active is
        // treated as an accident and undone.
        this.#dialog.addEventListener('close', this.#handleClose);
    }

    #handleCancel = (e) => {
        e.preventDefault();
    }

    #handleClose = () => {
        // hide() clears #state before closing, so a deliberate close lands here as a no-op.
        if (this.#state && this.#dialog && !this.#dialog.open) {
            this.#dialog.showModal();
        }
    }

    // A photo when there is one, the fallback glyph otherwise. The name is already in the
    // message below, so the image is decorative — alt stays empty rather than repeating it.
    #renderAvatar(user) {
        const fallbackClass = contentEditorSelectors.classes.editLockAvatarFallback;
        this.#avatarElement.innerHTML = '';
        if (user && user.avatar) {
            const image = document.createElement('img');
            image.src = user.avatar;
            image.alt = '';
            this.#avatarElement.appendChild(image);
            this.#avatarElement.classList.remove(fallbackClass);
            return;
        }
        this.#avatarElement.innerHTML = AVATAR_FALLBACK_ICON;
        this.#avatarElement.classList.add(fallbackClass);
    }

    #renderMeta(since) {
        const hidden = contentEditorSelectors.classes.hidden;
        if (since) {
            this.#metaElement.textContent = Translator.translate('Editing since %s').replace('%s', since);
            this.#metaElement.classList.remove(hidden);
            return;
        }
        this.#metaElement.textContent = '';
        this.#metaElement.classList.add(hidden);
    }

    #renderActions() {
        const c = contentEditorSelectors.classes;
        this.#clearActionListeners();
        this.#actionsElement.innerHTML = '';

        this.#applicableActions().forEach((definition) => {
            const button = document.createElement('div');
            button.classList.add(c.editLockAction);
            if (definition.primary) {
                button.classList.add(c.editLockActionPrimary);
            }
            button.textContent = Translator.translate(definition.label);
            const handler = () => this.#handleAction(definition);
            button.addEventListener('click', handler);
            this.#listeners.push({element: button, event: 'click', handler});
            this.#actionsElement.appendChild(button);
        });
    }

    #applicableActions() {
        return [...EditLock.ACTIONS.values()]
            .filter((definition) => !definition.states || definition.states.includes(this.#state))
            .map((definition, index) => ({definition, index}))
            .sort((a, b) => {
                const byOrder = (a.definition.order ?? 100) - (b.definition.order ?? 100);
                return byOrder !== 0 ? byOrder : a.index - b.index;
            })
            .map((entry) => entry.definition);
    }

    #handleAction(definition) {
        const context = {state: this.#state, user: this.#user, lock: this};
        this.#eventEmitter.emit(events.editLockAction, {key: definition.key, ...context});
        if (typeof definition.onClick === 'function') {
            definition.onClick(context);
        }
    }

    #clearActionListeners() {
        this.#listeners.forEach(({element, event, handler}) => {
            element.removeEventListener(event, handler);
        });
        this.#listeners = [];
    }

    destroy() {
        this.#clearActionListeners();
        if (this.#dialog) {
            // Both listeners go before the close below, or #handleClose would re-open the
            // dialog we are in the middle of tearing down.
            this.#dialog.removeEventListener('cancel', this.#handleCancel);
            this.#dialog.removeEventListener('close', this.#handleClose);
            this.#state = null;
            if (this.#dialog.open) {
                this.#dialog.close();
            }
            this.#dialog.remove();
        }
        this.#dialog = null;
        this.#titleElement = null;
        this.#messageElement = null;
        this.#metaElement = null;
        this.#avatarElement = null;
        this.#actionsElement = null;
        this.#state = null;
        this.#user = null;
        this.#setupComplete = false;
    }
}

EditLock.registerAction({
    key: 'takeOver',
    label: 'Take over',
    states: [STATES.alreadyEditing],
    primary: true,
    order: 10,
});

EditLock.registerAction({
    key: 'exit',
    label: 'Exit the editor',
    order: 20,
});
