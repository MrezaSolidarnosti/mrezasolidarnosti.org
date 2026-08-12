import Parent from "./Parent.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import {elementRelationSelectors} from "./elementRelationSelectors.js";

export default class ElementRelation {

    #parents = [];
    #nextAvailableId = 0;
    #eventEmitter = new EventEmitter();
    #config;
   constructor(config) {
       this.#config = config;
   }

   init() {
       this.#addListeners();
       this.#initExisting();
   }

    #addListeners() {
        this.#config.createParentButton.addEventListener('click', this.#createParent);
        this.#eventEmitter.on(events.parentDestroyed, (data) => {
            delete this.#parents[data.id];
        });
   }

    #initExisting() {
        this.#config.container.querySelectorAll(`.${elementRelationSelectors.classes.parent}`).forEach((parent) => {
            const newParent = new Parent(this.#config, this.#nextAvailableId++, this.#eventEmitter, parent);
            this.#parents.push(newParent);
        });
    }

    #createParent = () => {
        const parent = new Parent(this.#config, this.#nextAvailableId++, this.#eventEmitter);
        this.#parents.push(parent);
        this.#config.container.appendChild(parent.getView());
    }

    on(event, callback) {
        this.#eventEmitter.on(event, callback);
    }

    destroy() {
        this.#config.createParentButton.removeEventListener('click', this.#createParent);
        this.#config.createParentButton.remove();
        this.#config.container.remove();
        this.#parents.forEach(parent => parent.destroy());
        this.#config = null;
        this.#nextAvailableId = null;
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        this.#parents = null;
    }
}