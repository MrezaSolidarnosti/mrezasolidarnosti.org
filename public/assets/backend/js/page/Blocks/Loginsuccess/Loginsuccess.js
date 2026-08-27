import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, textAreaField, textField} from "../fields.js";

export default class Loginsuccess extends Block {
    static label = 'Login Success';
    static keywords = ["login","success","message"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M16.1 260.2c-22.6 12.9-20.5 47.3 3.6 57.3L160 376l0 103.3c0 18.1 14.6 32.7 32.7 32.7c9.7 0 18.9-4.3 25.1-11.8l62-74.3 123.9 51.6c18.9 7.9 40.8-4.5 43.9-24.7l64-416c1.9-12.1-3.4-24.3-13.5-31.2s-23.3-7.5-34-1.4l-448 256zm52.1 25.5L409.7 90.6 190.1 376l-1.2 1.5-120.7-91.8zM403.3 425.4L236.7 355.7 450.8 90.4 403.3 425.4z"/></svg>`;
    static isText = false;
    static name = 'app/loginsuccess';
    static category = 'Sections';
    static description = 'The message shown after a successful login.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Loginsuccess.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        return this.element;
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    getData() {
        return {
            title: this.fields.title.getValue(),
            description: this.fields.description.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
