import {mediaLibrarySelectors} from "../mediaLibrarySelectors.js";
import {crudPageSelectors} from "../../Page/crudPageSelectors.js";
import MediaLibrary from "../MediaLibrary.js";
import Response from "../../Response/Response.js";
import Message from "../../Message/Message.js";
import Loader from "../../Loader/Loader.js";
import Translator from "../../Translator/Translator.js";

export default class Image {

    #container;
    #form;
    #copyUrlButton;
    data;
    id;
    #messageContainer;

    constructor({data,id}) {
        this.data = data;
        this.id = id;
        try {
            this.#setProperties();
            this.#generateView();
        } catch(e) {
            console.error(e);

        }
    }
    #setProperties() {
        this.#messageContainer = document.querySelector(`#${mediaLibrarySelectors.ids.sidebarMessageContainer}`);
        if(!this.#messageContainer) {
            throw new Error(`${mediaLibrarySelectors.ids.sidebarMessageContainer} not found`);
        }
    }

    #generateView() {
        this.#container = document.createElement('div');
        this.#container.classList.add(mediaLibrarySelectors.classes.cell);
        const image = document.createElement('img');
        image.src = `${MediaLibrary.imagePath}${this.data.filename}`;
        this.#container.appendChild(image);
    }

    #generateForm() {
        this.#form = document.createElement('form');
        //@todo csrf token?
        this.#form.innerHTML = `
            <div class="previewAndData"> 
                    <img src="${MediaLibrary.imagePath}${this.data.filename}" alt="">
                    <div class="data">
                        <div><span>ID:</span><span class="value">${this.data.id}</span></div>
                        <div><span>Created At:</span><span class="value">${this.data.createdAt}</span></div>
                        <div class="dataPreviewActions">
                             <a href="${MediaLibrary.imagePath}${this.data.filename}" target="_blank" title="${this.data.filename}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32h82.7L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3V192c0 17.7 14.3 32 32 32s32-14.3 32-32V32c0-17.7-14.3-32-32-32H320zM80 32C35.8 32 0 67.8 0 112V432c0 44.2 35.8 80 80 80H400c44.2 0 80-35.8 80-80V320c0-17.7-14.3-32-32-32s-32 14.3-32 32V432c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16H192c17.7 0 32-14.3 32-32s-14.3-32-32-32H80z"/></svg>
                            </a>
                            <span title="${Translator.translate('Copy')}" data-path="${MediaLibrary.imagePath}${this.data.filename}">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                    <path d="M384 336l-192 0c-8.8 0-16-7.2-16-16l0-256c0-8.8 7.2-16 16-16l140.1 0L400 115.9 400 320c0 8.8-7.2 16-16 16zM192 384l192 0c35.3 0 64-28.7 64-64l0-204.1c0-12.7-5.1-24.9-14.1-33.9L366.1 14.1c-9-9-21.2-14.1-33.9-14.1L192 0c-35.3 0-64 28.7-64 64l0 256c0 35.3 28.7 64 64 64zM64 128c-35.3 0-64 28.7-64 64L0 448c0 35.3 28.7 64 64 64l192 0c35.3 0 64-28.7 64-64l0-32-48 0 0 32c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16l0-256c0-8.8 7.2-16 16-16l32 0 0-48-32 0z"/>
                                </svg>
                            </span>
                        </div>
                    </div>
            </div>
            <div class="inputContainer">
                <label>${Translator.translate('Source')}</label>
                <input readonly class="input" type="text" name="filename" value="${this.data.filename}">
            </div>
            <div class="inputContainer">
                <label>${Translator.translate('Alt')}</label>
                <input class="input" type="text" name="alt" value="${this.data.alt}">
            </div>
            <div class="inputContainer">
                <label>${Translator.translate('Label')}</label>
                <input class="input" type="text" name="label" value="${this.data.label}">
            </div>
            <div class="inputContainer">
                <label>${Translator.translate('Author')}</label>
                <input class="input" type="text" name="author" value="${this.data.author}">
            </div>
        `;
        const submitContainer = document.createElement('div');
        submitContainer.classList.add(mediaLibrarySelectors.classes.sidebarFormSubmitContainer);
        const formSubmitButton = document.createElement('input');
        formSubmitButton.type = 'submit';
        formSubmitButton.value = Translator.translate('Save');
        formSubmitButton.classList.add(crudPageSelectors.classes.button, crudPageSelectors.classes.primary);
        submitContainer.appendChild(formSubmitButton);
        this.#form.appendChild(submitContainer);
        this.#form.addEventListener('submit', this.#handleFormSubmit);
        this.#copyUrlButton = this.#form.querySelector('.dataPreviewActions span');
        if(this.#copyUrlButton) {
            this.#copyUrlButton.addEventListener('click', this.#copyPathCallback);
        }
    }

    #handleFormSubmit = async (e) => {
        e.preventDefault();
        let loader = new Loader({size:'28px', thickness:'4px'});
        loader.start(
            this.#form.querySelector(`.${mediaLibrarySelectors.classes.sidebarFormSubmitContainer}`),
            ['input']
        );
        const formData = new FormData();
        Object.keys(this.data).forEach((key) => {
            switch(key) {
                case 'img':
                case 'createdAt':
                case 'updatedAt':
                    break;
                case 'alt':
                case 'label':
                case 'author':
                    formData.append(key, this.#form.querySelector(`[name="${key}"]`).value);
                    break;
                default:
                    formData.append(key, this.data[key]);
            }
        });
        const req = await fetch(`${MediaLibrary.imageUpdateEndpoint}${this.id}/`, {
            method: 'POST',
            body: formData
        });
        const res = await req.json();
        const response = new Response(res);
        Message.removeMessages(this.#messageContainer);
        if (response.getErrors()) {
            response.getErrors().forEach((error) => {
                Message.spawn({
                    message: error.message,
                    type: Message.TYPES.ERROR,
                    view: {
                        container: this.#messageContainer,
                    },
                });
            })
        }
        if(response.getGeneralErrors()) {
            response.getGeneralErrors().forEach((error) => {
                Message.spawn({
                    message: error.message,
                    type: Message.TYPES.ERROR,
                    view: {
                        container: this.#messageContainer,
                    },
                });
            })
        }
        if(response.getMessage()) {
            Message.spawn({
                message: response.getMessage(),
                type: response.getStatus() ? Message.TYPES.SUCCESS : Message.TYPES.ERROR,
                view: {
                    container: this.#messageContainer,
                },
            });
        }
        if(response.getStatus()) {
            if(response.getData()) {
                // Update the data, this will update the cell data as well
                Object.keys(response.getData()).forEach((key) => {
                    this.data[key] = response.getData()[key];
                });
            }
        }
        loader.stop(
            this.#form.querySelector(`.${mediaLibrarySelectors.classes.sidebarFormSubmitContainer}`),
            ['input']
        );
        loader.destroy();
        loader = null;
    }


    #copyPathCallback = (e) => {
        const path = e.target.getAttribute('data-path');
        navigator.clipboard.writeText(path).then(() => {
            Message.spawn({
                message: Translator.translate('Path copied to clipboard'),
                type: Message.TYPES.SUCCESS,
                view: {
                    container: this.#messageContainer
                }
            });
        }).catch((e) => {
            Message.spawn({
                message: Translator.translate('Could not copy path to clipboard'),
                type: Message.TYPES.ERROR,
                view: {
                    container: this.#messageContainer
                }
            });
        });
    }

    getView() {
        return this.#container;
    }

    getForm() {
        if(!this.#form) {
            this.#generateForm();
        }
        return this.#form;
    }

    removeForm() {
        if(this.#form) {
            this.#form.removeEventListener('submit', this.#handleFormSubmit);
            if(this.#copyUrlButton) {
                this.#copyUrlButton.removeEventListener('click', this.#copyPathCallback);
                this.#copyUrlButton = null;
            }
            this.#form.remove();
            this.#form = null;
        }
        Message.removeMessages(this.#messageContainer);
    }

    getData() {
        return this.data;
    }

    destroy() {
        this.data = null;
        this.id = null;
        Message.removeMessages(this.#messageContainer);
        if(this.#form) {
            this.#form.removeEventListener('submit', this.#handleFormSubmit);
            if(this.#copyUrlButton) {
                this.#copyUrlButton.removeEventListener('click', this.#copyPathCallback);
                this.#copyUrlButton = null;
            }
            this.#form.remove();
            this.#form = null;
        }
        this.#handleFormSubmit = null;
        this.#messageContainer = null;
        this.#container.remove();
        this.#container = null;
    }
}