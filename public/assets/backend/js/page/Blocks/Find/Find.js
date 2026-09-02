import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, tabsField, textAreaField, textField} from "../fields.js";

export default class Find extends Block {
    static label = 'Find';
    static keywords = ["find","segments","cards"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/></svg>`;
    static isText = false;
    static name = 'app/find';
    static category = 'Sections';
    static description = 'Illustrated segments, each with its own button.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Find.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.segments = tabsField({
            label: 'Segments', itemLabel: 'Segment', items: data.segments,
            build: (item) => ({
                image: imageField({label: 'Image', id: item.imageId, filename: item.filename, imagePath: this.config.imagePath ?? ''}),
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
                buttonText: textField({label: 'Button Text', value: item.buttonText}),
                buttonLink: textField({label: 'Button Link', value: item.buttonLink}),
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
            segments: this.fields.segments.getValue().map((item) => ({
                imageId: item.image.id,
                filename: item.image.filename,
                title: item.title,
                description: item.description,
                buttonText: item.buttonText,
                buttonLink: item.buttonLink,
            })),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
