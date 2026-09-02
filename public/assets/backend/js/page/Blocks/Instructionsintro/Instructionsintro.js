import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, richTextField, svgField, textAreaField, textField} from "../fields.js";

export default class Instructionsintro extends Block {
    static label = 'Instructions Intro';
    static keywords = ["instructions","intro","payment"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-128 0c-17.7 0-32-14.3-32-32L224 0 64 0zM224 0l0 128 128 0L224 0zM112 256l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64l160 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-160 0c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>`;
    static isText = false;
    static name = 'app/instructionsintro';
    static category = 'Sections';
    static description = 'The intro above the payment instructions table.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Instructionsintro.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.linkText = richTextField({label: 'Link Text', value: data.linkText});
        this.body.appendChild(this.fields.linkText.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonSvg = svgField({label: 'Button SVG code', value: data.buttonSvg});
        this.body.appendChild(this.fields.buttonSvg.element);

        this.fields.infoTitle = textField({label: 'Info Title', value: data.infoTitle});
        this.body.appendChild(this.fields.infoTitle.element);

        this.fields.infoDescription = textAreaField({label: 'Info Description', value: data.infoDescription});
        this.body.appendChild(this.fields.infoDescription.element);

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
            linkText: this.fields.linkText.getValue(),
            buttonText: this.fields.buttonText.getValue(),
            buttonSvg: this.fields.buttonSvg.getValue(),
            infoTitle: this.fields.infoTitle.getValue(),
            infoDescription: this.fields.infoDescription.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
