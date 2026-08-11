import {tabbedContentSelectors} from "./tabbedContentSelectors.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";

export default class TabbedContent {

    #container;
    #dynamicTabs;
    #tabs;
    #tabsContainer;
    #tabContents;
    #activeTab;
    #addTabButton;
    eventEmitter = null;

    constructor(container, dynamicTabs = {}) {
        this.#container = container;
        this.#dynamicTabs = dynamicTabs;
        this.#setProperties();
    }

    #setProperties() {
        this.#tabs = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tab}`);
        this.#tabContents = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tabContent}`);
        this.#activeTab = this.#container.querySelector(`.${tabbedContentSelectors.classes.tab}.${tabbedContentSelectors.classes.active}`);
        this.#tabsContainer = this.#container.querySelector(`.${tabbedContentSelectors.classes.tabs}`);
        if(!this.#tabs.length && !this.#dynamicTabs.tabText) {
            throw new Error('Tabs not found in TabbedContent');
        }
        if(!this.#tabContents.length && !this.#dynamicTabs.tabText) {
            throw new Error('Tab contents not found in TabbedContent');
        }
        if(!this.#tabsContainer && !this.#dynamicTabs.tabText) {
            throw new Error('Tabs container not found in TabbedContent');
        }
        if(this.#dynamicTabs.tabText) {
            this.#addTabButton = this.#generateAddTabButton();
            this.#addRemoveTabButtons();
            this.eventEmitter = new EventEmitter();
        }
    }

    #addRemoveTabButtons() {
        this.#tabs.forEach((tab) => {
             this.#addRemoveButtonToTab(tab);
        });
    }

    #addRemoveButtonToTab(tab) {
        const removeButton = document.createElement('div');
        removeButton.classList.add(tabbedContentSelectors.classes.removeTabButton);
        removeButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"/></svg>`;
        removeButton.addEventListener('click', this.#handleRemoveTabButtonClick);
        tab.appendChild(removeButton);
    }

    #handleRemoveTabButtonClick = (e) => {
        const targetIndex = e.target.parentElement.getAttribute(tabbedContentSelectors.attributes.targetIndex);
        this.removeTab(targetIndex);
    }

    removeTab(targetIndex) {
        const targetTab = this.#container.querySelector(`.${tabbedContentSelectors.classes.tab}[${tabbedContentSelectors.attributes.targetIndex}="${targetIndex}"]`);
        const targetTabContent = this.#container.querySelector(`.${tabbedContentSelectors.classes.tabContent}[${tabbedContentSelectors.attributes.targetIndex}="${targetIndex}"]`);
        if(targetTab.classList.contains(tabbedContentSelectors.classes.active)) {
            const previousTab = targetTab.previousElementSibling;
            if(previousTab) {
                this.showTabContent(previousTab.getAttribute(tabbedContentSelectors.attributes.targetIndex));
            } else {
                const nextTab = targetTab.nextElementSibling;
                if(nextTab && !nextTab.classList.contains(tabbedContentSelectors.classes.addTabButton)) {
                    this.showTabContent(nextTab.getAttribute(tabbedContentSelectors.attributes.targetIndex));
                }

            }
        }
        this.eventEmitter.emit(events.beforeTabRemoved, {
            tabContent: targetTabContent,
            index: targetIndex
        });
        targetTab.removeEventListener('click', this.#handleTabClick);
        const removeTabButton = targetTab.querySelector(`.${tabbedContentSelectors.classes.removeTabButton}`);
        if(removeTabButton) {
            removeTabButton.removeEventListener('click', this.#handleRemoveTabButtonClick);
        }
        targetTab.remove();
        targetTabContent.remove();
        this.#tabs = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tab}:not(.${tabbedContentSelectors.classes.addTabButton})`);
        this.#tabContents = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tabContent}`);
        if(this.eventEmitter) {
            this.eventEmitter.emit(events.tabRemoved, {
                tabContent: targetTabContent,
                index: targetIndex
            });
        }
        this.#reindexAfterTabRemoved();
    }

    #reindexAfterTabRemoved() {
        this.#tabs.forEach((tab, index) => {
            const spanNode = tab.querySelector('span');
            tab.setAttribute(tabbedContentSelectors.attributes.targetIndex, index + 1);
            spanNode.innerText = this.#dynamicTabs.tabText;
            if (this.#dynamicTabs.appendNumberToTabText) {
                spanNode.innerText += ` ${index + 1}`;
            }
        });
        this.#tabContents.forEach((tabContent, index) => {
            tabContent.setAttribute(tabbedContentSelectors.attributes.targetIndex, index + 1);
        });
    }

    #generateAddTabButton() {
        const button = document.createElement('div');
        button.classList.add(tabbedContentSelectors.classes.tab);
        button.classList.add(tabbedContentSelectors.classes.addTabButton);
        button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32V224H48c-17.7 0-32 14.3-32 32s14.3 32 32 32H192V432c0 17.7 14.3 32 32 32s32-14.3 32-32V288H400c17.7 0 32-14.3 32-32s-14.3-32-32-32H256V80z"/></svg>`;
        button.addEventListener('click', this.#handleAddTabButtonClick);
        this.#tabsContainer.appendChild(button);
        return button;
    }

    #handleAddTabButtonClick = () => {
        this.addTab();
    }

    addTab(triggerEvent = true) {
        const newTab = document.createElement('div');
        newTab.classList.add(tabbedContentSelectors.classes.tab);
        newTab.setAttribute(tabbedContentSelectors.attributes.targetIndex, this.#tabs.length + 1);
        const tabText = document.createElement('span');
        tabText.textContent = this.#dynamicTabs.tabText;
        if(this.#dynamicTabs.appendNumberToTabText) {
            tabText.textContent += ` ${this.#tabs.length + 1}`;
        }
        newTab.appendChild(tabText);
        this.#tabsContainer.insertBefore(newTab, this.#addTabButton);
        this.#tabs = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tab}:not(.${tabbedContentSelectors.classes.addTabButton})`);
        const data = this.#addTabContent(triggerEvent);
        newTab.addEventListener('click', this.#handleTabClick);
        this.showTabContent(this.#tabs.length.toString());
        this.#addRemoveButtonToTab(newTab);
        return data;
    }

    #addTabContent(triggerEvent = true) {
        const newTabContent = document.createElement('div');
        newTabContent.classList.add(tabbedContentSelectors.classes.tabContent);
        newTabContent.setAttribute(tabbedContentSelectors.attributes.targetIndex, (this.#tabs.length).toString());
        if(this.#dynamicTabs.tabContent && this.#dynamicTabs.tabContent instanceof HTMLElement) {
            newTabContent.appendChild(this.#dynamicTabs.tabContent.cloneNode(true));
        }
        this.#container.appendChild(newTabContent);
        this.#tabContents = this.#container.querySelectorAll(`.${tabbedContentSelectors.classes.tabContent}`);
        const data = {
            tabContent: newTabContent,
            index: newTabContent.getAttribute(tabbedContentSelectors.attributes.targetIndex)
        }
        if(this.eventEmitter && triggerEvent) {
            this.eventEmitter.emit(events.tabAdded, data);
        }
        return data;
    }

    init() {
        if(!this.#container) {
            throw new Error('Container not found in TabbedContent');
        }
        this.#addListeners();
    }

    #addListeners() {
        this.#tabs.forEach((tab) => {
            tab.addEventListener('click', this.#handleTabClick);
        });
    }

    #handleTabClick = (e) => {
        if(e.target === this.#activeTab) {
            return;
        }
        const targetIndex = e.target.getAttribute(tabbedContentSelectors.attributes.targetIndex);
        this.showTabContent(targetIndex);
    }

    #hideAllTabContents() {
        this.#tabContents.forEach((tabContent) => {
            tabContent.classList.add(tabbedContentSelectors.classes.hidden);
        });
    }

    showTabContent(targetIndex) {
        const targetTab = this.#container.querySelector(`.${tabbedContentSelectors.classes.tab}[${tabbedContentSelectors.attributes.targetIndex}="${targetIndex}"]`);
        if(!targetTab) {
            return;
        }
        if(this.#activeTab) {
            this.#activeTab.classList.remove(tabbedContentSelectors.classes.active);
        }
        targetTab.classList.add(tabbedContentSelectors.classes.active);
        this.#activeTab = targetTab;
        this.#hideAllTabContents();
        this.#tabContents.forEach((tabContent) => {
            if(tabContent.getAttribute(tabbedContentSelectors.attributes.targetIndex) === targetIndex) {
                tabContent.classList.remove(tabbedContentSelectors.classes.hidden);
            }
        });
    }

    getTabbedContentWithClassNameInside(classname) {
        for (const tabContent of this.#tabContents) {
            if (tabContent.querySelector(`.${classname}`)) {
                return {
                    tabContent: tabContent,
                    tab: this.#activeTab,
                    index: tabContent.getAttribute(tabbedContentSelectors.attributes.targetIndex)
                };
            }
        }
    }

    getTabContents() {
        return this.#tabContents;
    }

    destroy() {
        this.#tabs.forEach((tab) => {
            tab.removeEventListener('click', this.#handleTabClick);
            const removeTabButton = tab.querySelector(`.${tabbedContentSelectors.classes.removeTabButton}`);
            if(removeTabButton) {
                removeTabButton.removeEventListener('click', this.#handleRemoveTabButtonClick);
            }
        });
        this.#tabs = null;
        this.#tabContents = null;
        this.#container = null;
        this.#activeTab = null;
        this.#dynamicTabs = null;
        if(this.#addTabButton) {
            this.#addTabButton.removeEventListener('click', this.#handleAddTabButtonClick);
        }
        this.#handleAddTabButtonClick = null;
        this.#addTabButton = null;
        this.#tabsContainer = null;
        if(this.eventEmitter) {
            this.eventEmitter.destroy();
        }
        this.eventEmitter = null;
    }
}