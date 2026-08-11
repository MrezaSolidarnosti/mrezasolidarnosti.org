import {commandAssets} from "./commandAssets.js";
import {events} from "./events.js";
import {commandSelectors} from "./commandSelectors.js";

export default class Command {

    #commandName;
    #eventEmitter;
    #nonTogglableCommands;
    #button;
    constructor({commandName, eventEmitter}) {
        this.#commandName = commandName;
        this.#eventEmitter = eventEmitter;
        this.#nonTogglableCommands = ['createLink','unlink'];
    }

    generateView() {
        this.#button = document.createElement('button');
        this.#button.innerHTML = commandAssets.icons[this.#commandName];
        this.#button.title = this.#commandName;
        this.#addCommandListener();
        return this.#button;
    }

    #addCommandListener() {
        this.#button.addEventListener('click', this.#clickCallback);

        this.#button.addEventListener('mousedown', this.#mousedownCallback);
    }


    #clickCallback = (e) => {
        e.preventDefault();
        this.#execCommand();
    }

    #mousedownCallback = (e) => {
        e.preventDefault();
    }

    #execCommand = () => {
        let value = null;
        this.#eventEmitter.emit(events.commandClicked, this);
        if (this.#commandName === 'createLink') {
            let selection = false;
            if(confirm('Should the link be opened in a new tab?')) {
                selection = document.getSelection();
            }
            let link = prompt('Enter a URL:', '');
            document.execCommand(this.#commandName, false, link);
            if(selection) {
                selection.anchorNode.parentElement.target = '_blank';
                selection.anchorNode.parentElement.title = selection.anchorNode.textContent;
            }
            return;
        }
        if(!this.#nonTogglableCommands.includes(this.#commandName)) {
            this.#button.classList.toggle(commandSelectors.classes.active);
        }
        document.execCommand(this.#commandName, false, value);
    }

    toggleActiveBasedOnCommandState(isActive) {
        isActive ? this.highlight() : this.unHighlight();
    }

    highlight() {
        this.#button.classList.add(commandSelectors.classes.active);
    }

    unHighlight() {
        this.#button.classList.remove(commandSelectors.classes.active);
    }

    getCommandName() {
        return this.#commandName;
    }

    destroy() {
        this.#button.removeEventListener('click', this.#clickCallback);
        this.#button.removeEventListener('mousedown', this.#mousedownCallback);
        this.#button = null;
        this.#commandName = null;
        this.#eventEmitter = null;
        this.#nonTogglableCommands = null;
        this.#execCommand = null;
    }
}