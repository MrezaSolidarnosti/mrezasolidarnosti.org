import {contentEditorSelectors} from "../../../contentEditorSelectors.js";
import {events} from "./events.js";
import {events as blockEvents} from "../../events.js";
import Translator from "../../../../Translator/Translator.js";

export default class BlockRepresentation {
    eventEmitter;
    id;
    name;
    label;
    icon;
    readOnly;
    #setupComplete = false;
    container;
    toggleOptionsButton;
    optionsContainer;
    insertBeforeOption;
    insertAfterOption;
    duplicateOption;
    deleteOption;
    constructor({eventEmitter, id, name, label, icon, readOnly, hidden = false}) {
        this.eventEmitter = eventEmitter;
        this.id = id;
        this.name = name;
        this.label = label;
        this.icon = icon;
        this.readOnly = readOnly;
        this.hidden = hidden;
    }


    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#constructView();
        this.#addListeners();
        this.#setupComplete = true;
    }


    #constructView() {
        this.container = document.createElement('div');
        this.container.classList.add(contentEditorSelectors.classes.overviewBlock);
        this.container.setAttribute(contentEditorSelectors.attributes.blockId, this.id);
        this.container.innerHTML = `${this.icon}<span>${Translator.translate(this.label)}</span>`;
        if(this.readOnly) {
            this.container.readOnly = true;
        }
        if(!this.readOnly) {
            this.container.draggable = true;
            this.#constructToggleOptions();
        }
    }

    #constructToggleOptions() {
        this.toggleOptionsButton = document.createElement('div');
        this.toggleOptionsButton.classList.add(contentEditorSelectors.classes.toggleOptionsBlockRepresentation);
        this.toggleOptionsButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-160q-33 0-56.5-23.5T400-240q0-33 23.5-56.5T480-320q33 0 56.5 23.5T560-240q0 33-23.5 56.5T480-160Zm0-240q-33 0-56.5-23.5T400-480q0-33 23.5-56.5T480-560q33 0 56.5 23.5T560-480q0 33-23.5 56.5T480-400Zm0-240q-33 0-56.5-23.5T400-720q0-33 23.5-56.5T480-800q33 0 56.5 23.5T560-720q0 33-23.5 56.5T480-640Z"></path></svg>`;
        this.container.appendChild(this.toggleOptionsButton);
        this.optionsContainer = document.createElement('div');
        this.optionsContainer.classList.add(contentEditorSelectors.classes.blockRepresentationOptionsContainer);
        this.insertBeforeOption = document.createElement('span');
        this.insertBeforeOption.textContent = Translator.translate('Insert Before');
        this.insertAfterOption = document.createElement('span');
        this.insertAfterOption.textContent = Translator.translate('Insert After');
        this.duplicateOption = document.createElement('span');
        this.duplicateOption.textContent = Translator.translate('Duplicate');
        this.deleteOption = document.createElement('span');
        this.deleteOption.textContent = Translator.translate('Delete');
        this.deleteOption.setAttribute(contentEditorSelectors.attributes.dataOptionAction, 'delete');
        this.optionsContainer.append(this.insertBeforeOption, this.insertAfterOption, this.duplicateOption, this.deleteOption);
        // Auto-managed blocks (e.g. footnotes) can't be duplicated or deleted by hand.
        if (this.hidden) {
            this.duplicateOption.classList.add(contentEditorSelectors.classes.hidden);
            this.deleteOption.classList.add(contentEditorSelectors.classes.hidden);
        }
        this.container.appendChild(this.optionsContainer);
    }

    #addListeners() {
        if(!this.readOnly) {
            this.container.addEventListener('click', this.#clickHandle);
            this.toggleOptionsButton.addEventListener('click', this.#handleOptionsClick);
            this.insertBeforeOption.addEventListener('click', this.#insertBefore);
            this.insertAfterOption.addEventListener('click', this.#insertAfter);
            this.duplicateOption.addEventListener('click', this.#duplicate);
            this.deleteOption.addEventListener('click', this.#delete);
        }
    }


    #insertBefore = (e) => {
        // Stop the click bubbling to the container's #clickHandle, which would re-select
        // this block and steal focus back from the newly inserted paragraph.
        e.stopPropagation();
        this.eventEmitter.emit(blockEvents.setActiveBlock, this.id);
        this.eventEmitter.emit(blockEvents.insertBefore);
        this.closeOptions();
    }

    #insertAfter = (e) => {
        e.stopPropagation();
        this.eventEmitter.emit(blockEvents.setActiveBlock, this.id);
        this.eventEmitter.emit(blockEvents.insertAfter);
        this.closeOptions();
    }

    #duplicate = (e) => {
        e.stopPropagation();
        this.eventEmitter.emit(blockEvents.duplicateBlock, this.id);
        this.closeOptions();
    }

    #delete = (e) => {
        e.stopPropagation();
        this.eventEmitter.emit(blockEvents.deleteBlock, this.id);
    }



    #clickHandle = () => {
        this.eventEmitter.emit(events.representationSelected, (this));
    }

    #handleOptionsClick = (e) => {
        // Keep the toggle from bubbling to #clickHandle, which re-selects this block and
        // (via unfocus) would immediately close the menu we're opening.
        e.stopPropagation();
        this.optionsContainer.classList.toggle(contentEditorSelectors.classes.active, !this.isOptionsContainerOpen());
    }

    isOptionsContainerOpen() {
        return this.optionsContainer.classList.contains(contentEditorSelectors.classes.active);
    }

    openOptions() {
        this.optionsContainer.classList.add(contentEditorSelectors.classes.active);
    }

    closeOptions() {
        this.optionsContainer.classList.remove(contentEditorSelectors.classes.active);
    }

    isElementAnOption(element) {
        return element === this.optionsContainer || this.optionsContainer.contains(element) || element === this.toggleOptionsButton;
    }


    focus() {
        this.container.classList.add(contentEditorSelectors.classes.active);
    }

    unfocus() {
        this.container.classList.remove(contentEditorSelectors.classes.active);
        if(this.isOptionsContainerOpen()) {
            this.closeOptions();
        }
    }


    getContainer() {
        return this.container;
    }

    destroy() {
        this.container.removeEventListener('click', this.#clickHandle);
        this.toggleOptionsButton.removeEventListener('click', this.#handleOptionsClick);
        this.insertBeforeOption.removeEventListener('click', this.#insertBefore);
        this.insertAfterOption.removeEventListener('click', this.#insertAfter);
        this.duplicateOption.removeEventListener('click', this.#duplicate);
        this.deleteOption.removeEventListener('click', this.#delete);
        this.eventEmitter = null;
        this.container.remove();
    }
}