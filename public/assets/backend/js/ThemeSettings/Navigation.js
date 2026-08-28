import Loader from "../../../../vendor/skeletorjs/src/Loader/Loader.js";
import Translator from "../../../../vendor/skeletorjs/src/Translator/Translator.js";
import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";
import DNDI from "../../../../vendor/skeletorjs/src/DNDI/DNDI.js";


export default class Navigation {

    #navigationSelect;
    #createNewNavigationButton;
    #deleteNavigationButton;
    #navigationContent;
    #createNavigationEndpoint = '/navigation/create/';
    #deleteNavigationEndpoint = '/navigation/delete/';
    #getNavigationEndpoint = '/navigation/get/';
    #saveEndpoint = '/navigation/save/';
    #dndi = null;
    #dndiForm = null;
    #addNewItem = null;
    #submitLoader = new Loader({size: '22px', thickness: '3px'});

    #setProperties() {
        this.#navigationSelect = document.getElementById('navigationSelect');
        this.#createNewNavigationButton = document.getElementById('createNewNavigation');
        this.#deleteNavigationButton = document.getElementById('deleteNavigation');
        this.#navigationContent = document.getElementById('navigationContent');
    }

    init() {
        this.#setProperties();
        this.#addListeners();
    }

    #addListeners() {
        this.#addCreateNewNavigationListener();
        this.#addDeleteNavigationListener();
        this.#addSelectNavigationListener();
    }

    #addCreateNewNavigationListener() {
        this.#createNewNavigationButton.addEventListener('click', this.#createNewNavigationCallback);
    }

    #createNewNavigationCallback = () => {
        let modal;
        const container = document.createElement('div');
        container.classList.add('inputContainer');
        const label = document.createElement('label');
        label.innerHTML = Translator.translate('Navigation Name');
        const input = document.createElement('input');
        input.classList.add('input');
        container.appendChild(label);
        container.appendChild(input);
        const submit = document.createElement('button');
        submit.classList.add('btn', 'hollow', 'glow');
        submit.innerHTML = Translator.translate('Create');
        const inputKeydownCallback = async (e) => {
            if(e.key === 'Enter') {
               await submitCallback(e);
            }
        }
        input.addEventListener('keydown', inputKeydownCallback);
        const submitCallback = async () => {
            const response = await fetch(this.#createNavigationEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({name: input.value})
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
            if(data.status && data.id) {
                this.#addNavigationToSelect(data.id, input.value);
                this.#navigationSelect.value = data.id;
                const event = new Event('change');
                this.#navigationSelect.dispatchEvent(event);
            }
            modal.destroy();
        }
        submit.addEventListener('click', submitCallback)
        container.appendChild(submit);
        const beforeModalClose = () => {
            submit.removeEventListener('click', submitCallback);
            input.removeEventListener('keydown', inputKeydownCallback);
        }

        modal = new Modal({
            destroyOnClose: true,
            beforeHideCallback: beforeModalClose
        });
        document.body.appendChild(modal.getView());
        modal.populateWithElement(container);
        modal.show();
    }

    #addDeleteNavigationListener() {
        this.#deleteNavigationButton.addEventListener('click', this.#deleteNavigationCallback);
    }

    #deleteNavigationCallback = async () => {
        const selected = this.#navigationSelect.value;
        if (selected === '-1') {
            return;
        }
        const response = await fetch(this.#deleteNavigationEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({id: selected})
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
        if(data.status === true) {
            this.#removeNavigationFromSelect(selected);
            this.#deleteNavigationButton.classList.remove('active');
            this.#clearNavigationContent();
        }
    }

    #addSelectNavigationListener() {
        this.#navigationSelect.addEventListener('change', this.#selectNavigationCallback);
    }

    #selectNavigationCallback = async () => {
        const selected = this.#navigationSelect.value;
        this.#clearNavigationContent();
        if (selected === '-1') {
            this.#deleteNavigationButton.classList.remove('active');
        } else {
            const response = await fetch(`${this.#getNavigationEndpoint}${selected}/`);
            const data = await response.json();
            if(data.status === false) {
                Message.spawn({
                    message: data.message,
                    type: Message.TYPES.ERROR,
                    view: {
                        type: Message.VIEW_TYPES.NOTIFICATION,
                        container: document.getElementById('messageContainerFixed'),
                    },
                    ephemeralTimeout: 5000
                });
                return;
            }
            this.#initDNDI(data.items ?? []);
            this.#deleteNavigationButton.classList.add('active');
        }
    }
    #removeNavigationFromSelect(value) {
        const options = this.#navigationSelect.options;
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === value) {
                this.#navigationSelect.remove(i);
                break;
            }
        }
    }

    #addNavigationToSelect(value, text) {
        const option = document.createElement('option');
        option.value = value;
        option.innerHTML = text;
        this.#navigationSelect.appendChild(option);
    }


    #initDNDI(items) {
        this.#addNewItem = document.createElement('button');
        this.#addNewItem.classList.add('btn', 'hollow', 'glow', 'smallFont');
        this.#addNewItem.textContent = Translator.translate('Add New Item');
        this.#addNewItem.addEventListener('click', this.#addNewItemCallback);
        this.#navigationContent.appendChild(this.#addNewItem);
        this.#dndiForm = document.createElement('form');
        const submitButtonContainer = document.createElement('div');
        submitButtonContainer.classList.add('submitContainer');
        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.classList.add('btn', 'smallFont', 'primary', 'noMargin', 'hollow', 'glow');
        submit.textContent = Translator.translate('Save');
        submitButtonContainer.appendChild(submit);
        this.#dndiForm.appendChild(submitButtonContainer);
        this.#dndiForm.id = 'dndiForm';
        this.#dndiForm.classList.add('dndiContainer');
        this.#dndiForm.addEventListener('submit', this.#submitDNDIForm);
        this.#navigationContent.appendChild(this.#dndiForm);
        this.#dndi = new DNDI({
            containerId: 'dndiForm',
            baseInputName: 'navigation'
        });
        if(items.length > 0) {
            items.forEach((item) => {
                this.#insertDNDIItemWithChildren(item);
            });
        }
    }

    #insertDNDIItemWithChildren(item, level = 1) {
        this.#dndi.insert([
            {label: 'Label', value: item.label, inputName: 'label', type: 'text'},
            {label: 'https://example.com', value: item.url, inputName: 'url', type:'text'},
            {label: 'Open in new tab', value: item.openInNewTab, inputName: 'openInNewTab', type: 'checkbox'},
        ], level, false);
        if(item.children.length > 0) {
            item.children.forEach((child) => {
                this.#insertDNDIItemWithChildren(child, level + 1);
            });
        }
    }

    #addNewItemCallback = () => {
        this.#dndi.insert([
            {label: 'Label', value:'', inputName: 'label'},
            {label: 'https://example.com', value:'', inputName: 'url'},
            {label: 'Open in new tab', value: 0, inputName: 'openInNewTab', type: 'checkbox'},
        ]);
    }

    #submitDNDIForm = async (e) => {
        this.#submitLoader.start(this.#dndiForm.querySelector('.submitContainer'), ['button']);
        e.preventDefault();
        const formData = this.#dndi.getFormData();
        formData.append('navigationId', this.#navigationSelect.value);
        const response = await fetch(this.#saveEndpoint, {
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
        this.#submitLoader.stop(this.#dndiForm.querySelector('.submitContainer'), ['button']);
    }

    #clearNavigationContent() {
        if(this.#dndiForm) {
            this.#dndiForm.removeEventListener('submit', this.#submitDNDIForm);
        }
        if(this.#addNewItem) {
            this.#addNewItem.removeEventListener('click', this.#addNewItemCallback);
        }
        this.#navigationContent.innerHTML = '';
    }

    destroy() {
        this.#createNewNavigationButton.removeEventListener('click', this.#createNewNavigationCallback);
        this.#createNewNavigationCallback = null;
        this.#deleteNavigationButton.removeEventListener('click', this.#deleteNavigationCallback);
        this.#deleteNavigationCallback = null;
        this.#navigationSelect.removeEventListener('change', this.#selectNavigationCallback);
        this.#selectNavigationCallback = null;
        if(this.#dndi) {
            this.#dndi.destroy();
        }
        if(this.#addNewItem) {
            this.#addNewItem.removeEventListener('click', this.#addNewItemCallback);
        }
        this.#addNewItemCallback = null;
        this.#dndi = null;
        this.#submitLoader.destroy();
        this.#submitLoader = null;
    }
}