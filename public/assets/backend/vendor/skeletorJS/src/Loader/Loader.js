import {config as defaultConfig} from "./config.js";
import {loaderSelectors} from "./loaderSelectors.js";

export default class Loader {

    #loader;
    #config;
    constructor(config) {
        this.#config = {};
        this.#loader = null;
        this.#parseConfig(config);
        this.#prepare();
    }

    #prepare() {
        this.#loader = this.#generateLoader();
        this.#applyConfig();
    }

    start(container, hideElementSelectors = null) {
        if(!container) {
            throw new Error('Loader requires a container to start.');
        }
        if(!container.querySelector(`:scope >.${loaderSelectors.classes.loader}`)) {
            if(hideElementSelectors) {
                hideElementSelectors.forEach((selector) => {
                    let target = container.querySelector(selector);
                    if(target) {
                        container.querySelector(selector).style.display = 'none';
                    }
                });
            }
            container.appendChild(this.#loader);
        }
    }

    stop(container = null, showElementsSelectors = null) {
        if(this.#loader) {
            if(container && showElementsSelectors) {
                showElementsSelectors.forEach((selector) => {
                    let target = container.querySelector(selector);
                    if(target) {
                        target.style.removeProperty('display');
                    }
                });
            }
            this.#loader.remove();
        }
    }

    #generateLoader() {
        let loader =  document.createElement('div');
        loader.classList.add(loaderSelectors.classes.loader);
        return loader;
    }

    #applyConfig() {
        this.#loader.style.border = `${this.#config.thickness} solid ${this.#config.trackColor}`;
        this.#loader.style.borderTop = `${this.#config.thickness} solid ${this.#config.innerTrackColor}`;
        this.#loader.style.width = this.#config.size;
        this.#loader.style.height = this.#config.size;
    }

    #parseConfig(config = {}) {
        Object.keys(defaultConfig).forEach((defaultConfigKey) => {
            if(!config || typeof config === 'undefined' || typeof config[defaultConfigKey] === 'undefined') {
                config[defaultConfigKey] = defaultConfig[defaultConfigKey];
            }
        });
        this.#config = config;
    }

    destroy() {
        this.#loader = null;
        this.#config = null;
    }
}