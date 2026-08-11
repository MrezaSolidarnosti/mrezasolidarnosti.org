import LocalStorage from "../LocalStorage/LocalStorage.js";
import {modes} from "./modes.js";
import {theme as themeConfig} from "./theme.js";
export default class ModeSelection {


    #modeToggleInput;
    #mode;
    #theme;
    constructor({modeToggleInput, theme, mode = modes.dark}) {
        this.#modeToggleInput = modeToggleInput;
        this.#theme = theme;
        this.#mode = mode;
        if(!this.#theme) {
            this.#theme = themeConfig
        }
    }


    init() {
        this.#setModeFromStorage();
        this.#addListeners();
    }

    #setModeFromStorage() {
        const mode = LocalStorage.get('mode', true);
        if(mode !== null) {
            this.#setMode(mode);
            if(this.#modeToggleInput) {
                this.#modeToggleInput.checked = mode === modes.dark;
            }
        } else {
            this.#setMode(this.#mode);
            LocalStorage.set('mode', this.#mode);
            if(this.#modeToggleInput) {
                this.#modeToggleInput.checked = this.#mode === modes.dark;
            }
        }
    }

    #addListeners() {
        if(this.#modeToggleInput) {
            this.#modeToggleInput.addEventListener('change', this.#modeToggleCallback);
        }
    }

    #modeToggleCallback = () => {
        this.#setMode(this.#modeToggleInput.checked ? modes.dark : modes.light);
        LocalStorage.set('mode', this.#mode);
    }

    getMode() {
        return this.#mode;
    }

    static getModeFromStorage() {
        return LocalStorage.get('mode');
    }

    #setMode(mode) {
        this.#mode = mode;
        const targetTheme = this.#mode === modes.dark ? this.#theme.scheme.dark : this.#theme.scheme.light;
        Object.keys(targetTheme).forEach(key => {
            document.documentElement.style.setProperty(`--${key}`, targetTheme[key]);
        });
    }

    destroy() {
        if(this.#modeToggleInput) {
            this.#modeToggleInput.removeEventListener('change', this.#modeToggleCallback);
        }
        this.#modeToggleInput = null;
        this.#mode = null;
    }


}