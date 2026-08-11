import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {events} from "./events.js";

export default class SidebarSection {

    container = null;
    eventEmitter;
    #setupComplete = false;
    handleElement = null;
    contentElement = null;
    iconElement = null;
    constructor({container, eventEmitter}) {
        this.container = container;
        this.eventEmitter = eventEmitter;
    }


    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        this.#addListeners();

        this.#setupComplete = true;
    }

    #setElements() {
        this.handleElement = this.container.querySelector(`.${contentEditorSelectors.classes.sidebarContentSectionHandle}`);
        this.contentElement = this.container.querySelector(`.${contentEditorSelectors.classes.sidebarContentSectionContent}`);
        if(this.handleElement) {
            this.iconElement = this.handleElement.querySelector(`&:scope>svg`);
        }

    }

    #addListeners() {
        if (this.handleElement) {
            this.handleElement.addEventListener('click', this.#handleClick);
        }
    }

    #handleClick = () => {
        if(this.contentElement) {
            const open = this.contentElement.classList.toggle(contentEditorSelectors.classes.active);
            const event = open ? events.sidebarSectionOpened : events.sidebarSectionClosed;
            this.eventEmitter.emit(event, {section: this});
        }
        if(this.iconElement) {
            this.iconElement.classList.toggle(contentEditorSelectors.classes.active);
        }
    }

    static generate(label, id, eventEmitter, active = false) {
        const html = `<div class="sidebarContentSection">
                    <div class="sidebarContentSectionHandle">
                        <span>${label}</span>
                        <svg${active ? ' class="active"' : ''} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M201.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 338.7 54.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>
                    </div>
                    <div class="sidebarContentSectionContent${active ? ' active' : ''}">
                        <div id="${id}">
                        </div>
                    </div>
                </div>`;
        const template = document.createElement('template');
        template.innerHTML = html.trim();

        const root = template.content.firstElementChild;

        const instance = new SidebarSection({
            container: root,
            eventEmitter
        });

        instance.init();

        return instance;
    }

    destroy() {
        if (this.handleElement) {
            this.handleElement.removeEventListener('click', this.#handleClick);
        }
        this.eventEmitter = null;
    }
}