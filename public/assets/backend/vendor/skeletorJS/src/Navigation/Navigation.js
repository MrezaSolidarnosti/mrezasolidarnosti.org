import EventEmitter from "../EventEmitter/EventEmitter.js";
import {navigationSelectors} from "./navigationSelectors.js";
import Item from "./Item.js";
import {events} from "./events.js";
import ModeSelection from "../Theme/ModeSelection.js";
import {modes} from "../Theme/modes.js";
import LocalStorage from "../LocalStorage/LocalStorage.js";

export default class Navigation {
    #sidebar;
    #itemsContainer;
    #toggleButton;
    #searchButton
    #searchInput;
    #navigationUserButton;
    #hamburger;
    #noResults;
    #settingsContainer;
    #modeSelection;
    #items = [];
    #eventEmitter = new EventEmitter();
    #isOpen = false;
    #theme;
    #defaultTheme;
    #isOpenOnInit = false;

    constructor({theme, defaultTheme = modes.dark, isOpenOnInit = false}) {
        this.#theme = theme;
        this.#defaultTheme = defaultTheme;
        this.#isOpenOnInit = isOpenOnInit;

    }
    init() {
        try {
            this.#setProperties();
            this.#validateProperties();
            this.#addListeners();
            this.#listen();
            this.#handleMenuInitialPosition();
        } catch (e) {
            console.error(e);
        }
    }

    #setProperties() {
        this.#sidebar = document.getElementById(navigationSelectors.ids.navigation);
        this.#itemsContainer = document.getElementById(navigationSelectors.ids.itemsContainer);
        this.#toggleButton = document.getElementById(navigationSelectors.ids.toggleButton);
        this.#searchButton = document.getElementById(navigationSelectors.ids.searchButton);
        this.#searchInput = document.getElementById(navigationSelectors.ids.searchInput);
        this.#navigationUserButton = document.getElementById(navigationSelectors.ids.navigationUserButton);
        this.#noResults = document.getElementById(navigationSelectors.ids.noResults);
        this.#settingsContainer = document.getElementById(navigationSelectors.ids.settingsItem);
        this.#hamburger = document.getElementById(navigationSelectors.ids.hamburger);
        this.#setItems(this.#itemsContainer.querySelectorAll(`.${navigationSelectors.classes.item}`));
        this.#modeSelection = this.#setModeSelection();
    }

    #validateProperties() {
        if(!this.#sidebar) {
            throw new Error(`${navigationSelectors.ids.navigation} not found`);
        }
        if(!this.#itemsContainer) {
            throw new Error(`${navigationSelectors.ids.itemsContainer} not found`);
        }
        if(!this.#toggleButton) {
            throw new Error(`${navigationSelectors.ids.toggleButton} not found`);
        }
        if(!this.#searchButton) {
            throw new Error(`${navigationSelectors.ids.searchButton} not found`);
        }
        if(!this.#searchInput) {
            throw new Error(`${navigationSelectors.ids.searchInput} not found`);
        }
        if(!this.#navigationUserButton) {
            throw new Error(`${navigationSelectors.ids.navigationUserButton} not found`);
        }
        if(!this.#noResults) {
            throw new Error(`${navigationSelectors.ids.noResults} not found`);
        }
        if(!this.#settingsContainer) {
            throw new Error(`${navigationSelectors.ids.settingsItem} not found`);
        }
    }

    #addListeners() {
        this.#addToggleListener();
        this.#addSearchListener();
        this.#addUserListener();
        this.#addHamburgerListener();
        this.#addCloseMenuListenerOnOutsideClickForMobile();
    }

    #addToggleListener() {
        this.#toggleButton.addEventListener('click', this.#toggleCallback);
    }

    #toggleCallback = () => {
        if(!this.#isOpen) {
            this.open();
            return;
        }
        this.close();
    }

    #addSearchListener() {
        this.#searchButton.addEventListener('click', this.#searchCallback);

        this.#searchInput.addEventListener('blur', this.#searchBlurCallback);
        this.#searchInput.addEventListener('focus', this.#searchFocusCallback);

        this.#searchInput.addEventListener('input', this.#searchInputCallback);
    }

    #searchCallback = () => {
        if(!this.#isOpen) {
            this.open();
            this.#searchButton.classList.add(navigationSelectors.classes.active);
            setTimeout(() => {
                this.#focusSearch();
            }, 300);
        } else {
            this.#focusSearch();
        }
    }

    #searchBlurCallback = () => {
        this.#searchButton.classList.remove(navigationSelectors.classes.active);
    };

    #searchFocusCallback = () => {
        this.#searchButton.classList.add(navigationSelectors.classes.active);
    }

    #searchInputCallback = () => {
        let numberOfShownItems = 1; // Settings item
        this.#items.forEach((item) => {
            if(item.hasId(navigationSelectors.ids.settingsItem)) {
                return;
            }
            numberOfShownItems += item.filter(this.#searchInput.value.trim()) ? 1 : 0;
        });
        if(numberOfShownItems === 1) {
            this.#noResults.classList.add(navigationSelectors.classes.active);
            return;
        }
        this.#noResults.classList.remove(navigationSelectors.classes.active);
    }

    #addUserListener() {
        this.#navigationUserButton.addEventListener('click', this.#userClickCallback);
    }

    #addHamburgerListener() {
        this.#hamburger.addEventListener('click', this.#toggleCallback);
    }

    #addCloseMenuListenerOnOutsideClickForMobile() {
        document.addEventListener('click', this.#closeMenuOnOutsideClickCallback);
    }

    #closeMenuOnOutsideClickCallback = (e) => {
        // check window size
        if(window.innerWidth <= 1024) {
            if(!this.#isOpen) {
                return;
            }
            if(this.#sidebar.contains(e.target) || this.#hamburger.contains(e.target)) {
                return;
            }
            this.close();
        }
    }

    #userClickCallback = () => {
        if (!this.#isOpen) {
            this.open();
        }
    };


    #setItems(items) {
        if(items && items.length > 0) {
            items.forEach((item) => {
                this.#items.push(new Item(item, this.#eventEmitter));
            });
        }
        this.#items.push(new Item(this.#settingsContainer, this.#eventEmitter));
    }

    #setModeSelection() {
        const modeToggleInput = document.getElementById(navigationSelectors.ids.modeToggleInput);
        if(!modeToggleInput) {
            throw new Error(`${navigationSelectors.ids.modeToggleInput} not found`);
        }
        this.#modeSelection = new ModeSelection({modeToggleInput: modeToggleInput, theme: this.#theme, mode: this.#defaultTheme});
        this.#modeSelection.init();
        return this.#modeSelection;
    }

    #listen() {
        this.#eventEmitter.on(events.ITEM_CLICKED, () => {
            if(!this.#isOpen) {
                this.open();
            }
        });
    }

    #handleMenuInitialPosition() {
        if(this.#isOpenOnInit) {
            this.open();
        }
    }

    #focusSearch() {
        this.#searchInput.focus();
    }

    #blurSearch() {
        this.#searchInput.blur();
    }

    #closeItems() {
        this.#items.forEach((item) => {
            item.close();
        });
    }

    #showAllHiddenItems() {
        this.#items.forEach((item) => {
            item.filter('');
        });
    }

    open() {
        this.#isOpen = true;
        this.#sidebar.classList.add(navigationSelectors.classes.active);
        this.#toggleButton.classList.add(navigationSelectors.classes.active);
    }

    close() {
        this.#isOpen = false;
        this.#closeItems();
        this.#blurSearch();
        this.#noResults.classList.remove(navigationSelectors.classes.active);
        this.#searchInput.value = '';
        this.#sidebar.classList.remove(navigationSelectors.classes.active);
        this.#toggleButton.classList.remove(navigationSelectors.classes.active);
        this.#showAllHiddenItems();
    }


    destroy() {
        this.#toggleButton.removeEventListener('click', this.#toggleCallback);
        this.#searchButton.removeEventListener('click', this.#searchCallback);
        this.#searchInput.removeEventListener('blur', this.#searchBlurCallback);
        this.#searchInput.removeEventListener('focus', this.#searchFocusCallback);
        this.#searchInput.removeEventListener('input', this.#searchInputCallback);
        this.#navigationUserButton.removeEventListener('click', this.#userClickCallback);
        this.#modeSelection.destroy();
        this.#modeSelection = null;
        this.#sidebar.remove();
        this.#hamburger.removeEventListener('click', this.#toggleCallback);
        this.#hamburger.remove();
        this.#hamburger = null;
        this.#sidebar = null;
        this.#itemsContainer = null;
        this.#toggleButton = null;
        this.#searchButton = null;
        this.#searchInput = null;
        this.#navigationUserButton = null;
        this.#noResults = null;
        this.#settingsContainer = null;
        this.#items.forEach((item) => {
            item.destroy();
        });
        this.#items = null;
        this.#eventEmitter = null;
        this.#theme = null;
        document.removeEventListener('click', this.#closeMenuOnOutsideClickCallback);
        this.#closeMenuOnOutsideClickCallback = null;
        this.#toggleCallback = null;
        this.#searchCallback = null;
        this.#searchBlurCallback = null;
        this.#searchFocusCallback = null;
        this.#searchInputCallback = null;
        this.#userClickCallback = null;
        this.#closeMenuOnOutsideClickCallback = null;
    }
}