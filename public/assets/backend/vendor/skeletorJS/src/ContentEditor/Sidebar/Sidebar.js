import {events} from "./events.js";
import {events as blockEvents} from "../Blocks/events.js";
import {contentEditorSelectors} from "../contentEditorSelectors.js";
import SidebarSection from "./SidebarSection/SidebarSection.js";

export default class Sidebar {
    eventEmitter;
    #open = false;
    #setupComplete = false;
    navigationItems = null;
    activeSidebarContent = null;
    activeNavigationItem = null;
    sections = [];
    currentBlockInSidebar = null;
    constructor({eventEmitter}) {
        this.eventEmitter = eventEmitter;
    }

    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();
        this.#listenForEvents();
        this.#addListeners();

        const sections = document.querySelectorAll(`.${contentEditorSelectors.classes.sidebarContentSection}`);
        if (sections) {
            sections.forEach((section) => {
                const sidebarSection = new SidebarSection(
                    {container: section, eventEmitter: this.eventEmitter}
                );
                sidebarSection.init();
                this.sections.push(sidebarSection);
            });
        }

        this.#setupComplete = true;
    }


    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.sidebar);
        this.closeSidebar = document.getElementById(contentEditorSelectors.ids.closeSidebar);
        if(this.container.classList.contains(contentEditorSelectors.classes.active)) {
            this.#open = true;
        }
        this.sidebarPostContentContainer = document.getElementById(contentEditorSelectors.ids.sidebarPostContent);
        this.sidebarBlockContentContainer = document.getElementById(contentEditorSelectors.ids.sidebarBlockContent);
        this.navigationItems = document.querySelectorAll(`#${contentEditorSelectors.ids.sidebarNavigationContainer} [${contentEditorSelectors.attributes.sidebarNavigationTarget}]`);
    }


    #listenForEvents() {
        this.eventEmitter.on(events.toggleSidebar, this.#toggleSidebar);
        this.eventEmitter.on(events.openSidebar, this.#openSidebar);
        this.eventEmitter.on(events.closeSidebar, this.#closeSidebar);
        this.eventEmitter.on(events.populateBlockSidebar, ({block, content}) => {
            if(block !== this.currentBlockInSidebar) {
                this.#destroyCurrentBlockInSidebar();
                this.#populateBlockSidebar(block, content);
                this.openNavItem(this.sidebarBlockContentContainer);
            }
        });
        // Selecting blocks deactivates the active block, so there is nothing left to
        // configure — its controls have to go, or they look like they still apply.
        this.eventEmitter.on(blockEvents.blockBlurred, () => {
            this.#destroyCurrentBlockInSidebar();
        });
        // A block can be removed without another taking focus (e.g. an undo/restore), which
        // would otherwise leave its now-orphaned sidebar content showing.
        this.eventEmitter.on(blockEvents.blockDeleted, (block) => {
            if(block === this.currentBlockInSidebar) {
                this.#destroyCurrentBlockInSidebar();
            }
        });
    }


    #addListeners() {
        this.closeSidebar.addEventListener('click', this.#closeSidebar);
        if(this.navigationItems) {
            this.navigationItems.forEach((item) => {
                    item.addEventListener('click', this.openNavItem);
            });
        }
    }

    openNavItem = (e) => {
        let targetID = null;
        let target = null;
        if (e instanceof Event) {
            targetID = e.target.getAttribute(contentEditorSelectors.attributes.sidebarNavigationTarget);
            target = document.getElementById(targetID);
        }
        if (e instanceof Element) {
            targetID = e.id;
            target = e;
        }
        if(target === this.activeSidebarContent) {
            return;
        }
        if(target) {
            target.classList.add(contentEditorSelectors.classes.active);
            if(this.activeSidebarContent) {
                this.activeSidebarContent.classList.remove(contentEditorSelectors.classes.active);
            }
            if(this.activeNavigationItem) {
                this.activeNavigationItem.classList.remove(contentEditorSelectors.classes.active);
            }
            this.activeNavigationItem = this.getNavigationItemByContentID(targetID);
            this.activeNavigationItem.classList.add(contentEditorSelectors.classes.active)
            this.activeSidebarContent = target;
            this.activeSidebarContent.classList.add(contentEditorSelectors.classes.active);
            this.eventEmitter.emit(events.sidebarContentActive, {contentID: targetID, element: target});
        }
    }


    #toggleSidebar = () => {
        this.#open = !this.#open;
        this.#open ? this.eventEmitter.emit(events.sidebarOpened) : this.eventEmitter.emit(events.sidebarClosed);
        this.container.classList.toggle(contentEditorSelectors.classes.active);
    }

    #openSidebar = (data) => {
        this.#open = true;
        this.container.classList.add(contentEditorSelectors.classes.active);
        this.eventEmitter.emit(events.sidebarOpened);
        if(data.showPostContent) {
            this.openNavItem(this.sidebarPostContentContainer);
        }
    }

    #closeSidebar = () => {
        this.#open = false;
        this.container.classList.remove(contentEditorSelectors.classes.active);
        this.eventEmitter.emit(events.sidebarClosed);
    }

    #destroyCurrentBlockInSidebar() {
        if(this.currentBlockInSidebar) {
            this.currentBlockInSidebar.destroySidebar();
            this.sidebarBlockContentContainer.innerHTML = '';
            this.currentBlockInSidebar = null;
        }
    }

    #populateBlockSidebar(block, content) {
        this.currentBlockInSidebar = block;
        this.sidebarBlockContentContainer.appendChild(content);
    }



    getNavigationItemByContentID(contentID) {
        if(!this.navigationItems) {
            return null;
        }
        for(let i = 0; i < this.navigationItems.length; i++) {
            const item = this.navigationItems[i];
            if(item.getAttribute(contentEditorSelectors.attributes.sidebarNavigationTarget) === contentID) {
                return item;
            }
        }
        return null;
    }

    destroy() {
        this.closeSidebar.removeEventListener('click', this.#closeSidebar);
        if(this.navigationItems) {
            this.navigationItems.forEach((item) => {
                item.removeEventListener('click', this.openNavItem);
            });
        }
        if(this.sections) {
            this.sections.forEach((section) => {
                section.destroy();
            });
            this.sections = null;
        }
        this.eventEmitter = null;
        this.currentBlockInSidebar = null;
    }
}