import DataTableAction from "../DataTableAction/DataTableAction.js";
import {config as actionConfig} from "./config.js";

export default class DataTableGroupAction {

    config;

    #actions = [];
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
        if(this.config && this.config.actions) {
            this.config.actions.forEach((action) => {
                this.#actions.push(new DataTableAction(action));
            });
        }
    }

    getActions() {
        return this.#actions;
    }

    getContent() {
        return this.config.content;
    }

    getLabel() {
        return this.config.label;
    }

    getOrder() {
        return this.config.order;
    }

    getClassName() {
        return this.config.className;
    }

    getUseLoader() {
        return this.config.useLoader;
    }

    destroy() {
        this.config = null;
        this.#actions = null;
    }

}