import {config as bulkActionConfig} from "./config.js"

export default class DataTableBulkAction {

    config;
    constructor(config) {
        this.config = config;
        this.#parseConfig();
    }

    #parseConfig() {
        Object.keys(bulkActionConfig).forEach((key) => {
            if (this.config && typeof this.config !== 'undefined' && typeof this.config[key] !== 'undefined') {
                this.config[key] = this.config[key];
            } else {
                this.config[key] = bulkActionConfig[key];
            }
        });
    }

    getName() {
        return this.config.name;
    }

    getContent() {
        return this.config.content;
    }

    getCallback() {
        return this.config.callback;
    }

    getUseLoader() {
        return this.config.useLoader;
    }

    getPromptMessage() {
        return this.config.promptMessage;
    }

    destroy() {
        if(this.config?.callback) {
            this.config.callback = null;
        }
        this.config = null;
    }
}