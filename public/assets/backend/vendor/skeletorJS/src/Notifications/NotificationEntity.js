import {notificationsSelectors} from "./notificationsSelectors.js";
import {events} from "./events.js";
import Translator from "../Translator/Translator.js";

export default class NotificationEntity {

    static TYPES = {
        INFO: 'info',
        SUCCESS: 'success',
        WARNING: 'warning',
        ERROR: 'error'
    };

    #id;
    #message;
    #type;
    #url;
    #container = null;
    #dismissNotificationElement;
    #eventEmitter;
    constructor({id, message, url = null, type = NotificationEntity.TYPES.INFO, eventEmitter}) {
        this.#id = id;
        this.#message = message;
        this.#type = type;
        this.#url = url;
        this.#eventEmitter = eventEmitter;
    }

    getView() {
        if(this.#container === null) {
            if(this.#url === null) {
                this.#container = document.createElement('div');
            } else {
                this.#container = document.createElement('a');
                this.#container.href = this.#url;
            }
            this.#container.classList.add(notificationsSelectors.classes.notification);
            this.#container.innerHTML = `
                <div class="type ${this.#type}"></div>
                ${this.#message}
            `;
            this.#dismissNotificationElement = document.createElement('div');
            this.#dismissNotificationElement.classList.add(notificationsSelectors.classes.dismiss);
            this.#dismissNotificationElement.textContent = Translator.translate('Dismiss');
            this.#dismissNotificationElement.addEventListener('click', this.#dismissCallback);
            this.#container.appendChild(this.#dismissNotificationElement);
        }
        return this.#container;
    }

    #dismissCallback = (e) => {
        e.preventDefault();
        this.#container.style.transition = 'opacity 0.3s ease';
        this.#container.style.opacity = '0';
        setTimeout(() => {
            this.#eventEmitter.emit(events.notificationDismissed, this.#id);
        }, 300);
    }

    destroy() {
        this.#id = null;
        this.#message = null;
        this.#type = null;
        this.#url = null;
        this.#dismissNotificationElement.removeEventListener('click', this.#dismissCallback);
        this.#dismissCallback = null;
        this.#dismissNotificationElement = null;
        this.#eventEmitter = null;
        this.#container.remove();
        this.#container = null;
    }
}