import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, tabsField, textAreaField, textField} from "../fields.js";

export default class Connect extends Block {
    static label = 'Connect';
    static keywords = ["connect","contact","segments"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M579.8 267.7c56.5-56.5 56.5-148 0-204.5c-50-50-128.8-56.5-186.3-15.4l-1.6 1.1c-14.4 10.3-17.7 30.3-7.4 44.6s30.3 17.7 44.6 7.4l1.6-1.1c32.1-22.9 76-19.3 103.8 8.6c31.5 31.5 31.5 82.5 0 114l-112 112c-31.5 31.5-82.5 31.5-114 0c-27.9-27.9-31.5-71.8-8.6-103.8l1.1-1.6c10.3-14.4 6.9-34.4-7.4-44.6s-34.4-6.9-44.6 7.4l-1.1 1.6C50.7 244.9 57.2 323.7 107.2 373.7c56.5 56.5 148 56.5 204.5 0l112-112zM60.2 244.3c-56.5 56.5-56.5 148 0 204.5c50 50 128.8 56.5 186.3 15.4l1.6-1.1c14.4-10.3 17.7-30.3 7.4-44.6s-30.3-17.7-44.6-7.4l-1.6 1.1c-32.1 22.9-76 19.3-103.8-8.6C81.8 372.1 81.8 321.1 113.3 289.6l112-112c31.5-31.5 82.5-31.5 114 0c27.9 27.9 31.5 71.8 8.6 103.9l-1.1 1.6c-10.3 14.4-6.9 34.4 7.4 44.6s34.4 6.9 44.6-7.4l1.1-1.6C589.3 267.1 582.8 188.3 532.8 138.3c-56.5-56.5-148-56.5-204.5 0l-112 112z"/></svg>`;
    static isText = false;
    static name = 'app/connect';
    static category = 'Sections';
    static description = 'An intro with a button and the ways to get in touch.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Connect.label));

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

        this.fields.segments = tabsField({
            label: 'Segments', itemLabel: 'Segment', items: data.segments,
            build: (item) => ({
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
            }),
        });
        this.body.appendChild(this.fields.segments.element);

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
            segments: this.fields.segments.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
