import {config as actionConfig} from "./config.js";

export default class DataTableAction {

    config;
    constructor(config) {
        this.config = config;
        this.#parseConfig();
    }
    
    #parseConfig() {
        Object.keys(actionConfig).forEach((key) => {
            if (this.config && typeof this.config !== 'undefined' && typeof this.config[key] !== 'undefined') {
                this.config[key] = this.config[key];
            } else {
                this.config[key] = actionConfig[key];
            }
        });
    }

    getName() {
        return this.config.name;
    }

    getLabel() {
        return this.config.label;
    }

    getPromptMessage() {
        return this.config.promptMessage;
    }
    
    getContent() {
        return this.config.content;
    }

    getOrder() {
        return this.config.order;
    }
    
    getUseLoader() {
        return this.config.useLoader;
    }
    
    getClassName() {
        return this.config.className;
    }

    getFlashOnSuccess() {
        return this.config.flashOnSuccess;
    }

    getLockDuringCallback() {
        return this.config.lockRowDuringCallback;
    }
    
    getCallback() {
        return this.config.callback;
    }

    getAsText() {
        return this.config.asText;
    }

    getTextType() {
        return this.config.textType;
    }

    destroy() {
        if(this.config?.callback) {
            this.config.callback = null;
        }
        this.config = null;
    }
}