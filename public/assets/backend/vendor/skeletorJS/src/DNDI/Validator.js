export default class Validator {
    #config;
    constructor() {}


    setConfig(config) {
        this.#config = config;
    }

    validate() {
        if(!this.#config) {
            throw new Error('No config provided');
        }
        if(!this.#config.containerId) {
            throw new Error('containerId parameter missing in the config');
        }
        const container = document.getElementById(this.#config.containerId);
        if(!container) {
            throw new Error(`Container with the id "${this.#config.containerId}" was not found`);
        }
        if(!(container instanceof HTMLFormElement)) {
            throw new Error('The container must be an instance of HTMLFormElement');
        }
        if(!this.#config.baseInputName) {
            throw new Error('baseInputName is a required parameter');
        }
    }
}