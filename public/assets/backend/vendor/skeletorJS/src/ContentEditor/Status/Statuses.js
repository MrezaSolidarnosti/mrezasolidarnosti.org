import {contentEditorSelectors} from "../contentEditorSelectors.js";
import {events} from "./events.js";
import BaseModule from "../BaseModule.js";

export default class Statuses extends BaseModule {
    container;
    input;
    statusButton;
    scheduleInput;
    statusesPopup;
    statusesPopupClose;
    #setupComplete = false;
    #isOpen = false;
    statusInputs;

    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setElements();
        if(!this.input || !this.statusButton || !this.statusesPopup || !this.statusesPopupClose) {
            return;
        }
        this.#addListeners();
        this.#setupComplete = true;
    }

    #setElements() {
        this.input = document.getElementById(contentEditorSelectors.ids.statusInput);
        this.scheduleInput = document.getElementById(contentEditorSelectors.ids.scheduleInput);
        this.statusButton = document.getElementById(contentEditorSelectors.ids.statusViewButton);
        this.statusesPopup = document.getElementById(contentEditorSelectors.ids.statusesPopup);
        this.statusesPopupClose = document.getElementById(contentEditorSelectors.ids.closeStatuses);
        this.statusInputs = this.statusesPopup.querySelectorAll('input[type="radio"]');
        if(this.isReadOnly()) {
            this.statusButton.classList.add(contentEditorSelectors.classes.disabled);
            this.scheduleInput.classList.add(contentEditorSelectors.classes.disabled);
        }
    }

    getStatus() {
        return parseInt(this.input.value, 10);
    }

    getData() {
        const data = {
            status: this.getStatus()
        };
        if(this.scheduleInput.value.trim() !== '') {
            data.schedule = this.scheduleInput.value;
        }
        return data;
    }

    setData({status, schedule}) {
        const statusInput = Array.from(this.statusInputs)
            .find((input) => parseInt(input.value, 10) === parseInt(status, 10));
        if(!statusInput) {
            return;
        }
        statusInput.checked = true;
        this.input.value = statusInput.value;
        this.statusButton.textContent = statusInput.parentElement
            .querySelector(`.${contentEditorSelectors.classes.statusName}`).textContent;

        const isSchedule = statusInput.getAttribute('data-schedule') === 'true';
        this.scheduleInput.classList.toggle(contentEditorSelectors.classes.active, isSchedule);
        this.scheduleInput.value = isSchedule && schedule ? schedule : '';
        this.eventEmitter.emit(events.statusChange, {oldStatus: null, newStatus: this.getStatus()});
    }

    #addListeners() {
        if(this.isReadOnly()) return;
        this.statusButton.addEventListener('click', this.toggle);
        this.statusesPopupClose.addEventListener('click', this.close);
        window.addEventListener('click', this.#handleWindowClick);
        this.statusInputs.forEach((input) => {
           input.addEventListener('change', this.#handleStatusInputChange);
        });
    }

    toggle = () => {
        if(this.statusesPopup.classList.contains(contentEditorSelectors.classes.active)) {
            this.close();
            return;
        }
        this.open();
    }

    open = () => {
        this.statusesPopup.classList.add(contentEditorSelectors.classes.active);
        this.#isOpen = true;
        this.eventEmitter.emit(events.statusesPopupOpened, 1);
    }

    close = () => {
        this.statusesPopup.classList.remove(contentEditorSelectors.classes.active);
        this.#isOpen = false;
        this.eventEmitter.emit(events.statusesPopupClosed, 2);
    }

    #handleWindowClick = (e) => {
        e.stopPropagation();
        if(!this.statusesPopup.contains(e.target) && this.isOpen() && e.target !== this.statusButton) {
            this.close();
        }
    }


    #handleStatusInputChange = (e) => {
        const oldStatus = parseInt(this.input.value, 10);
        this.input.value = e.target.value;
        this.statusButton.textContent = e.target.parentElement.querySelector(`.${contentEditorSelectors.classes.statusName}`).textContent;
        const isSchedule = e.target.getAttribute('data-schedule');
        this.scheduleInput.classList.toggle(contentEditorSelectors.classes.active, isSchedule === 'true');
        if(isSchedule !== 'true') {
            this.scheduleInput.value = '';
        }
        this.eventEmitter.emit(events.statusChange, {oldStatus: oldStatus, newStatus: this.getStatus()});
    };


    isOpen() {
        return this.#isOpen;
    }


    destroy() {
        super.destroy();
        if(this.statusButton) {
            this.statusButton.removeEventListener('click', this.toggle);
        }
        if(this.statusesPopupClose) {
            this.statusesPopupClose.removeEventListener('click', this.close);
        }
        window.removeEventListener('click', this.#handleWindowClick);
        this.#handleWindowClick = null
        this.statusInputs.forEach((input) => {
            input.removeEventListener('change', this.#handleStatusInputChange);
        });
        this.#handleStatusInputChange = null;
    }
}