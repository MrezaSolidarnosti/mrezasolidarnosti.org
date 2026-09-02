import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, richTextField, svgField, textAreaField, textField} from "../fields.js";

export default class Login extends Block {
    static label = 'Login';
    static keywords = ["login","form","donor"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M217.9 105.9L340.7 228.7c7.5 7.5 11.7 17.7 11.7 28.3s-4.2 20.8-11.7 28.3L217.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-96 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l96 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM352 416l64 0c17.7 0 32-14.3 32-32l0-256c0-17.7-14.3-32-32-32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l64 0c53 0 96 43 96 96l0 256c0 53-43 96-96 96l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>`;
    static isText = false;
    static name = 'app/login';
    static category = 'Sections';
    static description = 'The donor login form.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Login.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.subtitle = textField({label: 'Subtitle', value: data.subtitle});
        this.body.appendChild(this.fields.subtitle.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonSvg = svgField({label: 'Button SVG code', value: data.buttonSvg});
        this.body.appendChild(this.fields.buttonSvg.element);

        this.fields.footerText = richTextField({label: 'Footer Text', value: data.footerText});
        this.body.appendChild(this.fields.footerText.element);

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
            subtitle: this.fields.subtitle.getValue(),
            buttonText: this.fields.buttonText.getValue(),
            buttonSvg: this.fields.buttonSvg.getValue(),
            footerText: this.fields.footerText.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
