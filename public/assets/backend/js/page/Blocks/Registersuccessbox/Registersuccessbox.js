import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, svgField, textAreaField, textField} from "../fields.js";

export default class Registersuccessbox extends Block {
    static label = 'Register Success Box';
    static keywords = ["register","success","message"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/></svg>`;
    static isText = false;
    static name = 'app/registersuccessbox';
    static category = 'Sections';
    static description = 'The confirmation shown once registration is done.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Registersuccessbox.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.subtitle = textField({label: 'Subtitle', value: data.subtitle});
        this.body.appendChild(this.fields.subtitle.element);

        this.fields.secondDescription = textAreaField({label: 'Second Description', value: data.secondDescription});
        this.body.appendChild(this.fields.secondDescription.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonLink = textField({label: 'Button Link', value: data.buttonLink});
        this.body.appendChild(this.fields.buttonLink.element);

        this.fields.buttonSvg = svgField({label: 'Button SVG code', value: data.buttonSvg});
        this.body.appendChild(this.fields.buttonSvg.element);

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
            secondDescription: this.fields.secondDescription.getValue(),
            buttonText: this.fields.buttonText.getValue(),
            buttonLink: this.fields.buttonLink.getValue(),
            buttonSvg: this.fields.buttonSvg.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
