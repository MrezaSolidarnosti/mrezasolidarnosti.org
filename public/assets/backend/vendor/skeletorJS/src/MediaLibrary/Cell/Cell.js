import MediaLibrary from "../MediaLibrary.js";
import {mediaLibrarySelectors} from "../mediaLibrarySelectors.js";
import Document from "./Document.js";
import Image from "./Image.js";
import {events} from "../events.js";

export default class Cell {

    data;
    id;
    #fileType;
    #eventEmitter;
    #file;
    #selected = false;

    constructor({data, fileType, eventEmitter}) {
        this.data = data.columns ?? null;
        if(!this.data) {
            this.data = data;
        }
        this.id = data.id ?? null;
        this.#fileType = fileType;
        this.#eventEmitter = eventEmitter;
        this.#cleanUpData();
        this.#setFile();
        this.#addListeners();
    }

    getView() {
       if(this.#file) {
           return this.#file.getView();
       }
    }

    #cleanUpData() {
        if(this.data) {
            Object.keys(this.data).forEach((key) => {
                if(this.data[key] && this.data[key].value) {
                    this.data[key] = this.data[key].value;
                }
            });
        }
    }

    #setFile() {
        switch(this.#fileType) {
            case MediaLibrary.FIlE_TYPES.IMAGE:
                this.#file = new Image({data:this.data, id:this.id});
                break;
            case MediaLibrary.FIlE_TYPES.DOCUMENT:
                this.#file = new Document({data:this.data, id:this.id});
                break;
        }
    }

    #addListeners() {
        if(this.#file) {
            this.#file.getView().addEventListener('click', this.#handleClick);
        }
    }

    #handleClick = () => {
        if(this.#selected) {
            this.unselect();
        } else {
           this.select();
        }
    }

    unselect() {
        if(this.#selected) {
            this.#file.getView().classList.remove(mediaLibrarySelectors.classes.selectedCell);
            this.#selected = false;
            this.#eventEmitter.emit(events.cellUnselected, this);
        }
    }

    select() {
        if(!this.#selected) {
            this.#file.getView().classList.add(mediaLibrarySelectors.classes.selectedCell);
            this.#selected = true;
            this.#eventEmitter.emit(events.cellSelected, this);
        }
    }

    getForm() {
        if(this.#file) {
            return this.#file.getForm();
        }
    }

    removeForm() {
        if(this.#file) {
            this.#file.removeForm();
        }
    }

    static generateDummyCell() {
        let dummyContainer = document.createElement('div');
        dummyContainer.classList.add(mediaLibrarySelectors.classes.cell);
        dummyContainer.classList.add(mediaLibrarySelectors.classes.dummyCell);
        return dummyContainer;
    }

    static getLastDummyCell() {
        let dummyCells = document.querySelectorAll(`.${mediaLibrarySelectors.classes.dummyCell}`);
        let length = dummyCells.length;
        if(length > 0) {
            return dummyCells[length-1];
        }
        return null;
    }

    getData() {
        return this.#file.getData();
    }

    destroy() {
        this.data = null;
        this.id = null;
        this.#fileType = null;
        this.#eventEmitter = null;
        if(this.#file) {
            this.#file.getView().removeEventListener('click', this.#handleClick);
            this.#handleClick = null;
            this.#file.destroy();
            this.#file = null;
        }
        this.#selected = null;
    }
}