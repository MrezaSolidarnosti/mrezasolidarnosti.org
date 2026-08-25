import {crudPageSelectors} from "../Page/crudPageSelectors.js";
import {promptSelectors} from "./promptSelectors.js";

export default class Prompt {

    #message = '';
    #description = '';
    #choices;
    #buttons;
    constructor({message = '', description = '', choices = []}) {
        this.#message = message;
        this.#description = description;
        this.#choices = choices;
        this.#buttons = this.#generateButtons();
    }

    #generateButtons() {
        return this.#choices.map(choice => {
            const button = document.createElement('button');
            button.classList.add(crudPageSelectors.classes.button);
            if(choice.classList) {
                choice.classList.forEach(className => button.classList.add(className));
            } else {
                button.classList.add(crudPageSelectors.classes.buttonHollow, crudPageSelectors.classes.buttonGlow);
            }
            button.title = choice.text;
            button.setAttribute('data-value', choice.value);
            button.textContent = choice.text;
            return button;
        });
    }

    async prompt() {
        const dialog = document.createElement('dialog');
        dialog.classList.add(promptSelectors.classes.dialog, promptSelectors.classes.hidden);
        dialog.classList.add('dialog');
        const title = document.createElement('span');
        title.textContent = this.#message;
        dialog.appendChild(title);
        const description = document.createElement('p');
        description.textContent = this.#description;
        dialog.appendChild(description);
        const buttonContainer = document.createElement('div');
        buttonContainer.classList.add(promptSelectors.classes.buttonContainer);
        this.#buttons.forEach(button => buttonContainer.appendChild(button));
        dialog.appendChild(buttonContainer);
        document.body.appendChild(dialog);
        dialog.showModal();
        dialog.classList.remove(promptSelectors.classes.hidden);
        return new Promise(resolve => {
            dialog.addEventListener('close', () => {
                dialog.remove();
                resolve(null);
            }, {once: true});
            this.#buttons.forEach(button => {
                button.addEventListener('click', () => {
                    dialog.remove();
                    resolve(button.getAttribute('data-value'));
                }, {once: true});
            });
        });
    }
}