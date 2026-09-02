import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";
import Loader from "../../../../vendor/skeletorjs/src/Loader/Loader.js";


export default class Social {

    #form;
    #submitEndpoint = '/social/save/'
    #submitLoader = new Loader({size: '22px', thickness: '3px'});

    init() {
        this.#form = document.getElementById('socialLinks');
        this.#form.addEventListener('submit', this.#submitForm);
    }

    #submitForm = async (e) => {
        e.preventDefault();
        this.#submitLoader.start(document.querySelector('.submitContainer'), ['input']);
        const formData = new FormData(this.#form);
        const response = await fetch(this.#submitEndpoint, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        Message.spawn({
            message: data.message,
            type: data.status ? Message.TYPES.SUCCESS : Message.TYPES.ERROR,
            view: {
                type: Message.VIEW_TYPES.NOTIFICATION,
                container: document.getElementById('messageContainerFixed'),
            },
            ephemeralTimeout: 5000
        });
        this.#submitLoader.stop(document.querySelector('.submitContainer'), ['input']);
    }

    destroy() {
        this.#form.removeEventListener('submit', this.#submitForm);
        this.#submitForm = null;
    }
}