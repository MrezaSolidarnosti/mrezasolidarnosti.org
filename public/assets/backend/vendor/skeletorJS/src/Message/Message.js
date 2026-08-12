import {messageAssets} from "./messageAssets.js";
import {messageSelectors} from "./messageSelectors.js";

export default class Message {

    static TYPES = {
        INFO: 'info',
        SUCCESS: 'success',
        WARNING: 'warning',
        ERROR: 'error'
    }

    static VIEW_TYPES = {
        STATIC: 'static',
        NOTIFICATION: 'notification',
    }

    static spawn(
        {
            message = '',
            type = Message.TYPES.INFO,
            view = {
              type: Message.VIEW_TYPES.STATIC,
              container: null,
              prepend: false,
            },
            ephemeralTimeout = null,
        }) {
        if(!view.container) {
            console.error('Message container is required when spawning a message.');
            return;
        }
        const messageElement = document.createElement('div');
        messageElement.classList.add(messageSelectors.classes.message);
        messageElement.classList.add(type);
        if(view.type === Message.VIEW_TYPES.NOTIFICATION) {
            messageElement.classList.add(messageSelectors.classes.notification);
        }
        const icon = document.createElement('span');
        icon.classList.add(messageSelectors.classes.messageIcon);
        icon.innerHTML = Message.getIconForType(type);
        messageElement.innerHTML = message;
        messageElement.prepend(icon);

        const removeButton = document.createElement('div');
        removeButton.classList.add(messageSelectors.classes.removeButton);
        removeButton.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512">
                <path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"></path>
            </svg>`;


        let timeout = null;
        const removeMessage = (e) => {
            if(e) {
                e.stopPropagation();
            }
            if(timeout) {
                clearTimeout(timeout);
            }
            messageElement.style.transition = 'opacity 0.3s ease';
            messageElement.style.opacity = '0';
            setTimeout(() => {
                messageElement.remove();
            }, 300)
        }
        removeButton.addEventListener('click', removeMessage,{ once: true });
        messageElement.appendChild(removeButton);
        if(view.prepend) {
            view.container.prepend(messageElement);
        } else {
            view.container.appendChild(messageElement);
        }
        if(ephemeralTimeout !== null) {
            timeout = setTimeout(() => {
                removeMessage();
            }, ephemeralTimeout);
        }
    }

    static removeMessages(container, type = null) {
        if(type) {
            container.querySelectorAll(`.${messageSelectors.classes.message}.${type}`).forEach((message) => {
                message.remove();
            });
            return;
        }
        container.querySelectorAll(`.${messageSelectors.classes.message}`).forEach((message) => {
            message.remove();
        });
    }

    static getIconForType(type) {
        switch(type) {
            case Message.TYPES.INFO:
                return messageAssets.icons.info;
            case Message.TYPES.SUCCESS:
                return messageAssets.icons.success;
            case Message.TYPES.WARNING:
                return messageAssets.icons.warning;
            case Message.TYPES.ERROR:
                return messageAssets.icons.error;
        }
    }
}