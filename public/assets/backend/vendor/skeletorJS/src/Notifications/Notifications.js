import NotificationEntity from "./NotificationEntity.js";
import {notificationsSelectors} from "./notificationsSelectors.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import {events} from "./events.js";
import Translator from "../Translator/Translator.js";

export default class Notifications {

    #container;
    #endpoint;
    #notifications = new Map();
    #notificationsContainer;
    #notificationHeader;
    #notificationCountContainer;
    #checkInterval;
    #interval;
    #notificationCount = 0;
    #eventEmitter = new EventEmitter();
    #lastNotificationUpdateTime;
    #clearAllButton;
    constructor({container, endpoint, checkInterval = 60000}) {
        this.#container = container;
        this.#endpoint = endpoint;
        this.#checkInterval = checkInterval;
    }

    init() {
        this.#fetchNotifications().then((res) => {
            this.#generateNotificationsContainer();
            this.#generateNotificationCountContainer();
            this.#addListeners();
            this.#listenToEvents();
            this.#handleVisibilityChange();
            this.#startInterval();
            //@todo change data when backend is ready
            res.forEach((notification) => {
                this.addNotification(notification);
            });
        });
    }

    #generateNotificationsContainer() {
        this.#notificationsContainer = document.createElement('div');
        this.#notificationsContainer.id = notificationsSelectors.ids.notificationsContainer;
        this.#notificationsContainer.appendChild(this.#generateNotificationHeader());
        document.body.appendChild(this.#notificationsContainer);
    }

    #generateNotificationHeader() {
        this.#notificationHeader = document.createElement('h2');
        this.#notificationHeader.id = notificationsSelectors.ids.header;
        this.#notificationHeader.textContent = 'Notifications';
        this.#generateClearAllButton();
        this.#notificationHeader.appendChild(this.#clearAllButton);
        return this.#notificationHeader;
    }

    #generateClearAllButton() {
        this.#clearAllButton = document.createElement('button');
        this.#clearAllButton.classList.add(notificationsSelectors.classes.dismiss);
        this.#clearAllButton.textContent = Translator.translate('Clear All');
        this.#clearAllButton.addEventListener('click', this.#clearAllCallback);
        return this.#clearAllButton;
    }

    #clearAllCallback = () => {
        this.#notifications.forEach((notification) => {
            notification.destroy();
        });
        this.#notifications.clear();
        this.#setNotificationCount(0);
        this.hide();
    }

    #generateNotificationCountContainer() {
        this.#notificationCountContainer = document.createElement('div');
        this.#notificationCountContainer.id = notificationsSelectors.ids.notificationCountContainer;
        this.#container.appendChild(this.#notificationCountContainer);
    }

    #addListeners() {
        this.#container.addEventListener('click', this.#clickCallback);
        window.addEventListener('click', this.#windowClickCallback);
    }

    #clickCallback = (e) => {
        if(this.#notificationCount === 0) {
            return;
        }
        this.#notificationsContainer.classList.toggle('show');
    }

    #handleVisibilityChange() {
        document.addEventListener('visibilitychange', this.#visibilityChangeCallback);
    }

    #visibilityChangeCallback = () => {
        if(document.hidden) {
            this.#stopInterval();
        } else {
            this.#startInterval();
        }
    }

    #windowClickCallback = (e) => {
        if (e.target !== this.#container &&
            !this.#container.contains(e.target) &&
            e.target !== this.#notificationsContainer &&
            !this.#notificationsContainer.contains(e.target) &&
            !e.target.classList.contains(notificationsSelectors.classes.dismiss)
        ) {
            this.hide();
        }
    }

    addNotification(notification, prepend = false) {
        if(this.#notifications.has(notification.id)) {
            return;
        }
        const notificationEntity = new NotificationEntity({
            id: notification.id,
            message: notification.title,
            url: notification.url,
            type: notification.type,
            eventEmitter: this.#eventEmitter
        });
        if(prepend) {
            this.#notificationHeader.after(notificationEntity.getView())
        } else {
            this.#notificationsContainer.appendChild(notificationEntity.getView());
        }
        this.#notifications.set(notification.id, notificationEntity);
        this.#setNotificationCount(this.#notificationCount + 1);
    }

    async #fetchNotifications(withTime = false) {
        try {
            // const postData = {};
            // if(withTime) {
            //     postData.time = this.#lastNotificationUpdateTime ?? null;
            // }
            // const response = await fetch(this.#endpoint, {method: 'POST', body: JSON.stringify(postData)});
            // return await response.json();
            this.#lastNotificationUpdateTime = this.#getCurrentDateTime();
            // @todo remove this when backend is ready
            return [
                {
                    id: 1,
                    title: 'Notification Info',
                    type: NotificationEntity.TYPES.INFO,
                    url: 'https://google.com'
                },
                {
                    id: 2,
                    title: 'Notification Success',
                    type: NotificationEntity.TYPES.SUCCESS,
                    url: 'https://google.com'
                },
                {
                    id: 3,
                    title: 'Notification Warning',
                    type: NotificationEntity.TYPES.WARNING,
                    url: 'https://google.com'
                },
                {
                    id: 4,
                    title: 'Notification Error',
                    type: NotificationEntity.TYPES.ERROR,
                    url: 'https://google.com'
                }
            ]
        } catch (e) {
            console.error(e);
        }
    }

    #refreshNotificationCount() {
        if(this.#notificationCount === 0) {
            this.#notificationCountContainer.textContent = '';
            return;
        }
        this.#notificationCountContainer.textContent = this.#notificationCount;
    }

    #setNotificationCount(count) {
        this.#notificationCount = count;
        this.#refreshNotificationCount();
    }


    #getCurrentDateTime() {
        const now = new Date();

        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0'); // Month is zero-indexed
        const day = String(now.getDate()).padStart(2, '0');

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    #startInterval() {
        this.#interval = setInterval(() => {
            this.#fetchNotifications(true).then((res) => {
                res.forEach((notification) => {
                    this.addNotification(notification, true);
                });
            });
        }, this.#checkInterval);
    }

    #stopInterval() {
        clearInterval(this.#interval);
    }

    #listenToEvents() {
        this.#eventEmitter.on(events.notificationDismissed, (id) => {
            this.#removeNotification(id);
            if(this.#notificationCount === 0) {
                this.hide();
            }
        });
    }

    #removeNotification(id) {
        if(this.#notifications.has(id)) {
            this.#notifications.get(id).destroy();
            this.#notifications.delete(id);
            this.#setNotificationCount(this.#notificationCount - 1);
        }
    }

    hide() {
        this.#notificationsContainer.classList.remove(notificationsSelectors.classes.show);
    }

    destroy() {
        this.#container.removeEventListener('click', this.#clickCallback);
        window.removeEventListener('click', this.#windowClickCallback);
        this.#clickCallback = null;
        this.#windowClickCallback = null;
        this.#notifications.forEach((notification) => {
            notification.destroy();
        });
        this.#notifications.clear();
        this.#notificationsContainer = null;
        this.#notifications = null;
        this.#container = null;
        this.#endpoint = null;
        this.#notificationCountContainer = null;
        clearInterval(this.#interval);
        this.#interval = null;
        this.#checkInterval = null;
        document.removeEventListener('visibilitychange', this.#visibilityChangeCallback);
        this.#visibilityChangeCallback = null;
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        this.#lastNotificationUpdateTime = null;
        this.#notificationCount = null;
        this.#notificationHeader = null;
        this.#clearAllButton.removeEventListener('click', this.#clearAllCallback);
        this.#clearAllButton = null;
        this.#clearAllCallback = null;
    }
}