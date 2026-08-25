import Validator from "./Validator.js";
import Identifier from "./Identifier.js";
import Item from "./Item/Item.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import {selectors} from "./selectors.js";

export default class DNDI {

    #config;
    #container;
    #baseInputName;
    #validator = new Validator();
    #items = new Map();
    #identifier = new Identifier();
    #eventEmitter = new EventEmitter();
    constructor(config) {
        this.#config = config;
        try {
            this.#initConfig();
            this.#addContainerListeners();
            this.#listenToItemEvents();
        } catch(e) {
            console.error(e);
        }
    }

    #initConfig() {
        this.#validator.setConfig(this.#config);
        this.#validator.validate();

        this.#container = document.getElementById(this.#config.containerId);
        this.#baseInputName = this.#config.baseInputName;
    }

    #getItems() {
        return this.#items;
    }

    #getItem(key) {
        return this.#items.get(key);
    }

    #setItem(key, value) {
        return this.#items.set(key, value);
    }

    #deleteItem(key) {
        this.#items.get(key).destroy();
        this.#items.delete(key);
    }

    #listenToItemEvents() {
        this.#eventEmitter.on(events.itemDeleted, (data) => {
            this.#deleteItem(data.id);
            this.#correctFirstElementLevel();
            this.#correctLevelThree();
            this.#reindexInputNames();
        });

        this.#eventEmitter.on(events.itemIsBeingDragged, (data) => {
            if(data.item.getLevel() < 3) {
                this.#groupChildren(data.item);
            }
            this.#container.classList.add(selectors.classes.childIsDragging);
        });

        this.#eventEmitter.on(events.itemStoppedBeingDragged, (data) => {
            this.#container.classList.remove(selectors.classes.childIsDragging);
            this.#correctFirstElementLevel();
            if(data.item.getInitialLevel() < 3) {
                this.#ungroupChildren();
            }
            this.#correctLevelThree();
            this.#reindexInputNames();
        });
    }

    #getItemChildren(mainItem) {
        let foundMain = false;
        let children = [];
        const mainItemLevel = this.#getItemLevel(mainItem);
        document.querySelectorAll(`.${selectors.classes.item}`).forEach((item) => {
            if(mainItemLevel >= this.#getItemLevel(item) && foundMain) {
                foundMain = false;
            }
            if(!foundMain && item === mainItem) {
                foundMain = true;
            }
            if(foundMain && item !== mainItem) {
                children.push(item);
            }
        });
        return children;
    }

    #groupChildren(item) {
        const itemElement = item.getView();
        itemElement.classList.add(selectors.classes.groupMain);
        const children = this.#getItemChildren(itemElement);
        children.forEach((child) => {
            child.classList.add(selectors.classes.grouped);
        });
    }

    #ungroupChildren() {
        const mainElement = this.#container.querySelector(`.${selectors.classes.groupMain}`);
        const mainElementLevel = this.#getItemLevel(mainElement);
        let target = null;
        this.#container.querySelectorAll(`.${selectors.classes.grouped}`).forEach((item) => {
            item.classList.remove(selectors.classes.grouped);
            if(!target) {
                target = mainElement;
            }
            this.#container.insertBefore(item, target.nextSibling);
            target = item;
            if(mainElementLevel === 1 && this.#getItemLevel(item) === 3) {
                this.#setItemLevel(item, 2);
            }
            if(mainElementLevel === 2 && this.#getItemLevel(item) === 2 || mainElementLevel === 3) {
                this.#setItemLevel(item, 3);
            }
        });
        if(mainElement) {
            mainElement.classList.remove(selectors.classes.groupMain);
        }
    }

    #correctFirstElementLevel() {
        const firstElement = this.#container.querySelector(`.${selectors.classes.item}:not(.${selectors.classes.grouped})`);
        if(firstElement) {
            if(!firstElement.classList.contains(selectors.classes.levelOne)) {
                firstElement.classList.remove(selectors.classes.levelTwo, selectors.classes.levelThree);
                firstElement.classList.add(selectors.classes.levelOne);
            }
        }
    }

    #correctLevelThree() {
        const levelThrees = this.#container.querySelectorAll(`.${selectors.classes.item}.${selectors.classes.levelThree}`);
        levelThrees.forEach((item) => {
            const previousElement = item.previousElementSibling;
            if(previousElement && previousElement.classList.contains(selectors.classes.levelOne)) {
                item.classList.remove(selectors.classes.levelThree);
                item.classList.add(selectors.classes.levelTwo);
            }
        });
    }

    #addContainerListeners() {
        this.#container.addEventListener('dragenter', this.#onDragEnter);
        this.#container.addEventListener('dragover', this.#onDragOver);

        this.#container.addEventListener('drag', this.#onDrag);
    }

    #onDragEnter = (e) => {
        e.preventDefault();
    }

    #onDragOver = (e) => {
        e.preventDefault();
    }

    #onDrag = (e) => {
        /*
           * @todo fix, when the element is dragged out of the container bounds, it is appended, and the
           *   previous element changes which messes up item drag, allowing the dragged element to be a level
           *   it shouldn't. Example: Item.js ln:191 > ln:194
           * */
        if(e.clientY > this.#container.getBoundingClientRect().bottom) {
            this.#container.appendChild(this.#getDraggingElement());
        }
    }

    #getDraggingElement() {
        return this.#container.querySelector(`.${selectors.classes.dragging}`);
    }

    #reindexInputNames() {
        let currentLevelOneId = -1;
        let currentLevelTwoId = -1;
        let currentLevelThreeId = -1;
        this.#container.querySelectorAll('.item').forEach((item) => {
            if(this.#getItemLevel(item) === 1) {
                currentLevelTwoId = -1;
                this.#setItemInputNames(item,`${this.#baseInputName}[${++currentLevelOneId}]`)
            }
            if(this.#getItemLevel(item) === 2) {
                currentLevelThreeId = -1;
                this.#setItemInputNames(item, `${this.#baseInputName}[${currentLevelOneId}][children][${++currentLevelTwoId}]`)
            }
            if(this.#getItemLevel(item) === 3) {
                this.#setItemInputNames(item,`${this.#baseInputName}[${currentLevelOneId}][children][${currentLevelTwoId}][children][${++currentLevelThreeId}]`)
            }
        });
    }

    #setItemInputNames(item, name) {
        item.querySelectorAll('input').forEach((input) => {
            input.setAttribute('name', `${name}[${input.getAttribute(`${selectors.attributes.inputDataName}`)}]`);
        });
    }

    #getItemLevel(item) {
        if(item.classList.contains(selectors.classes.levelTwo)) {
            return 2;
        }
        if(item.classList.contains(selectors.classes.levelThree)) {
            return 3;
        }
        return 1;
    }

    #setItemLevel(item, level) {
        item.classList.remove(selectors.classes.levelOne);
        item.classList.remove(selectors.classes.levelTwo);
        item.classList.remove(selectors.classes.levelThree);
        switch(level) {
            case 1:
                item.classList.add(selectors.classes.levelOne);
                break;
            case 2:
                item.classList.add(selectors.classes.levelTwo);
                break;
            case 3:
                item.classList.add(selectors.classes.levelThree);
                break;
        }
    }


    insert(values, level = 1, open = true) {
        const id = this.#identifier.generateId();
        const item = new Item(id, values, this.#eventEmitter, this.#container);
        if(open) {
            item.open();
        }
        item.setLevel(level);
        this.#setItem(id, item);
        this.#container.appendChild(item.getView());
        this.#reindexInputNames();
    }

    getFormData() {
        return new FormData(this.#container);
    }


    destroy() {
        this.#container.removeEventListener('dragenter', this.#onDragEnter);
        this.#container.removeEventListener('dragover', this.#onDragOver);
        this.#container.removeEventListener('drag', this.#onDrag);
        this.#items.forEach((item) => {
            item.destroy();
        });
        this.#items.clear();
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
    }


}