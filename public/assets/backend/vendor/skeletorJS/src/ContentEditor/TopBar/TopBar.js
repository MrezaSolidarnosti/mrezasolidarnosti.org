import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "../Sidebar/events.js";

export default class TopBar {

    eventEmitter;
    container;
    #setupComplete = false;


    constructor({eventEmitter}) {
        this.eventEmitter = eventEmitter;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        if(!this.container || !this.sidebarToggle) {
            return;
        }
        this.#listenForEvents();
        this.#initListeners();

        this.#setupComplete = true;
    }


    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.topBar);
        this.sidebarToggle = document.getElementById(contentEditorSelectors.ids.topBarToggleSidebar);
    }

    #listenForEvents() {
        this.eventEmitter.on(events.sidebarOpened, this.#sidebarOpenedCallback);
        this.eventEmitter.on(events.sidebarClosed, this.#sidebarClosedCallback);
    }

    #sidebarOpenedCallback = () => {
        this.sidebarToggle.classList.add(contentEditorSelectors.classes.active);
    }

    #sidebarClosedCallback = () => {
        this.sidebarToggle.classList.remove(contentEditorSelectors.classes.active);
    }
    #initListeners() {
        this.sidebarToggle.addEventListener('click', this.#sidebarToggleClickCallback);
    }

    #sidebarToggleClickCallback = () => {
        this.eventEmitter.emit(events.toggleSidebar);
    }


    destroy() {
        this.#setupComplete = false;
        if(this.sidebarToggle) {
            this.sidebarToggle.removeEventListener('click', this.#sidebarToggleClickCallback);
        }
        this.eventEmitter = null;
    }
}