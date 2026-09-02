import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, textAreaField, textField} from "../fields.js";

export default class Banner extends Block {
    static label = 'Banner';
    static keywords = ["banner","cta"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M0 32C0 14.3 14.3 0 32 0L480 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 64C14.3 64 0 49.7 0 32zM0 480c0-17.7 14.3-32 32-32l448 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 512c-17.7 0-32-14.3-32-32zM64 160c0-17.7 14.3-32 32-32l320 0c17.7 0 32 14.3 32 32l0 96c0 17.7-14.3 32-32 32L96 288c-17.7 0-32-14.3-32-32l0-96z"/></svg>`;
    static isText = false;
    static name = 'app/banner';
    static category = 'Sections';
    static description = 'A banner with a heading, text and one button.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Banner.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonLink = textField({label: 'Button Link', value: data.buttonLink});
        this.body.appendChild(this.fields.buttonLink.element);

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
            buttonText: this.fields.buttonText.getValue(),
            buttonLink: this.fields.buttonLink.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
