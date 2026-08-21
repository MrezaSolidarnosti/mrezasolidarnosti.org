import {events} from "../events.js";
import {selectors} from "../selectors.js";

export default class Item {
    #id;

    #values;

    #eventEmitter;

    #view;

    #anchor;

    #fieldsContainer;

    #removeButton;

    #initialLevel = 1;

    #initialX = null;

    #mainContainer
    constructor(id, values, eventEmitter, mainContainer) {
        this.#id = id;
        this.#values = values;
        this.#eventEmitter = eventEmitter;
        this.#mainContainer = mainContainer;
        this.#view = this.#generateView();
        this.#addListeners();
    }

    #generateView() {
        this.#view = this.#generateContainer();
        this.#generateContent();
        return this.#view;
    }

    #generateContainer() {
        const view = document.createElement('div');
        view.draggable = true;
        view.classList.add(selectors.classes.item, selectors.classes.levelOne);
        return view;
    }

    #generateContent() {
        this.#generateFields();
        this.#generateAnchor();
        this.#generateRemoveButton();

        this.#view.appendChild(this.#anchor);
        this.#view.appendChild(this.#fieldsContainer);
        this.#fieldsContainer.appendChild(this.#removeButton);
    }

    #generateAnchor() {
        this.#anchor = document.createElement('h2');
        this.#anchor.innerText = this.#getLabel();
        this.#addAnchorListener();
    }
    #getLabel() {
        return this.#values[0].value;
    }

    #addAnchorListener() {
        this.#anchor.addEventListener('click', this.#onAnchorClick);
    }

    #onAnchorClick = () => {
        this.#fieldsContainer.classList.toggle(selectors.classes.active);
    }

    #generateFields() {
        this.#fieldsContainer = document.createElement('div');
        this.#fieldsContainer.classList.add(selectors.classes.itemContent);
        this.#values.forEach((objValue, index) => {
            const input = this.#generateValue(objValue);
            if(index === 0) {
                input.addEventListener('input', this.#onInputInput);
            }
            this.#fieldsContainer.appendChild(input);
        });
    }


    #onInputInput = (e) => {
        this.#anchor.innerText = e.target.value;
        this.#values[0].value = e.target.value;
    }

    #generateValue(objValue) {
        let label = null;
        const input = document.createElement('input');
        input.classList.add(selectors.classes.input)
        if(input.type) {
            input.type = objValue.type;
        } else {
            input.type = 'text';
        }
        input.placeholder = objValue.label;
        if(input.type === 'checkbox') {
            label = document.createElement('label');
            label.classList.add('inputContainer');
            label.innerText = objValue.label;
            if(objValue.value) {
                input.checked = true;
            }
        } else {
            input.value = objValue.value;
        }

        input.spellcheck = false;
        input.autocomplete = 'off';
        input.setAttribute(selectors.attributes.inputDataName, objValue.inputName);
        input.draggable = true;
        input.addEventListener('dragstart', this.#onInputDragStart);
        if(input.type === 'checkbox' && label) {
            label.appendChild(input);
            return label;
        }
        return input;
    }

    #onInputDragStart = (e) => {
        e.stopPropagation();
        e.preventDefault();
    }

    #generateRemoveButton() {
        this.#removeButton = document.createElement('div');
        this.#removeButton.classList.add(selectors.classes.removeItem);
        this.#removeButton.innerText = 'Remove';
        this.#addRemoveButtonListener();
    }

    #addRemoveButtonListener() {
        this.#removeButton.addEventListener('click', this.#onRemoveClick);
    }

    #onRemoveClick = () => {
        this.#view.remove();
        this.#eventEmitter.emit(events.itemDeleted, {id: this.#id})
    }

    #addListeners() {
        this.#view.addEventListener('dragstart', this.#viewOnDragStart);
        this.#view.addEventListener('dragend', this.#viewOnDragEnd);
        this.#view.addEventListener('dragover', this.#viewOnDragOver);
        this.#view.addEventListener('drag', this.#viewOnDrag);
    }


    #viewOnDragStart = (e) => {
        e.stopPropagation();
        this.#view.classList.add(selectors.classes.dragging);
        this.#initialX = e.clientX;
        this.#initialLevel = 1;
        if(this.#view.classList.contains(selectors.classes.levelTwo)) {
            this.#initialLevel = 2;
        }
        if(this.#view.classList.contains(selectors.classes.levelThree)) {
            this.#initialLevel = 3;
        }
        this.#eventEmitter.emit(events.itemIsBeingDragged, {item:this});
    }

    #viewOnDragEnd = (e) => {
        this.#view.classList.remove(selectors.classes.dragging);
        this.#eventEmitter.emit(events.itemStoppedBeingDragged, {item: this});

        const nextSibling = this.#view.nextElementSibling;
        const previousSibling = this.#view.previousElementSibling;
        if(nextSibling && this.#view.classList.contains(selectors.classes.levelOne) &&
            nextSibling.classList.contains(selectors.classes.levelThree)) {
            nextSibling.classList.remove(selectors.classes.levelThree);
            nextSibling.classList.add(selectors.classes.levelTwo);
        }
        if(previousSibling && this.#view.classList.contains(selectors.classes.levelThree) &&
            previousSibling.classList.contains(selectors.classes.levelOne)) {
            this.#view.classList.remove(selectors.classes.levelThree);
            this.#view.classList.add(selectors.classes.levelTwo);
        }
        this.#initialX = null;
    }

    #viewOnDragOver = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const draggingElement = this.#getDraggingElement();
        const targetBox = this.#view.getBoundingClientRect();
        if(this.#view === draggingElement) {
            return;
        }
        if(e.clientY === Math.ceil(targetBox.top)) {
            this.#view.parentElement.insertBefore(draggingElement, this.#view);
        }
        // When the cursor hits the middle of the element insert after
        if(e.clientY === Math.ceil(targetBox.top + targetBox.height / 2)) {
            this.#view.parentElement.insertBefore(draggingElement, this.#view.nextSibling);
        }
    }

    #viewOnDrag = (e) => {
        if(e.clientX === 0) {
            return;
        }
        const previousElementLevel = this.#getPreviousElementLevel();
        if(!previousElementLevel) {
            return;
        }
        let difference = e.clientX - this.#initialX;
        if(this.#initialLevel === 1) {
            if (difference >= 50 && difference < 100) {
                this.#view.classList.add(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelOne);
                this.#view.classList.remove(selectors.classes.levelThree);
            }
            if (difference < 50) {
                this.#view.classList.add(selectors.classes.levelOne);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
            }

            if(difference >= 100) {
                if(this.#view.previousElementSibling.classList.contains(selectors.classes.levelOne)) {
                    return;
                }
                this.#view.classList.add(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
            if(difference < 100 && difference > 50) {
                this.#view.classList.add(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
        }
        if(this.#initialLevel === 2) {
            if (difference >= 50 && difference < 100) {
                if(this.#view.previousElementSibling.classList.contains(selectors.classes.levelOne)) {
                    return;
                }
                this.#view.classList.add(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
            if (difference < 50 && difference > 0) {
                this.#view.classList.add(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
            if(difference <= 0) {
                this.#view.classList.add(selectors.classes.levelOne);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
            }
        }
        if(this.#initialLevel === 3) {
            if(difference > 0) {
                if(this.#view.previousElementSibling.classList.contains(selectors.classes.levelOne)) {
                    return;
                }
                this.#view.classList.add(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
            if(difference <= 0 && difference > -30) {
                this.#view.classList.add(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
                this.#view.classList.remove(selectors.classes.levelOne);
            }
            if(difference <= -30) {
                this.#view.classList.add(selectors.classes.levelOne);
                this.#view.classList.remove(selectors.classes.levelTwo);
                this.#view.classList.remove(selectors.classes.levelThree);
            }
        }
    }

    #getDraggingElement() {
        return this.#mainContainer.querySelector(`.${selectors.classes.dragging}`);
    }

    #getPreviousElementLevel() {
        const previousElement = this.#view.previousElementSibling;
        if(!previousElement) {
            return null;
        }
        if(previousElement.classList.contains(selectors.classes.levelOne)) {
            return 1;
        }
        if(previousElement.classList.contains(selectors.classes.levelTwo)) {
            return 2;
        }
        if(previousElement.classList.contains(selectors.classes.levelThree)) {
            return 3;
        }
    }

    #getChildNodes() {
        return this.#view.querySelectorAll(`.${selectors.classes.item}`);
    }

    getId() {
        return this.#id;
    }

    getValues() {
        return this.#values;
    }

    getView() {
        return this.#view;
    }

    getLevel() {
        if(this.#view.classList.contains(selectors.classes.levelTwo)) {
            return 2;
        }
        if(this.#view.classList.contains(selectors.classes.levelThree)) {
            return 3;
        }
        return 1;
    }

    getInitialLevel() {
        return this.#initialLevel;
    }

    setLevel(level = 1) {
        this.#view.classList.remove(selectors.classes.levelTwo);
        this.#view.classList.remove(selectors.classes.levelOne);
        this.#view.classList.remove(selectors.classes.levelThree);
        switch(level) {
            case 1:
                this.#view.classList.add(selectors.classes.levelOne);
                break;
            case 2:
                this.#view.classList.add(selectors.classes.levelTwo);
                break;
            case 3:
                this.#view.classList.add(selectors.classes.levelThree);
                break;
        }
    }

    open() {
        this.#fieldsContainer.classList.add(selectors.classes.active);
    }

    destroy() {
        this.#anchor.removeEventListener('click', this.#onAnchorClick);
        this.#removeButton.removeEventListener('click', this.#onRemoveClick);
        this.#view.removeEventListener('dragstart', this.#viewOnDragStart);
        this.#view.removeEventListener('dragend', this.#viewOnDragEnd);
        this.#view.removeEventListener('dragover', this.#viewOnDragOver);
        this.#view.removeEventListener('drag', this.#viewOnDrag);
    }

}