import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, tabsField, textAreaField, textField} from "../fields.js";

export default class Howitworks extends Block {
    static label = 'How It Works';
    static keywords = ["how it works","steps","process"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM216 336l-16 0c-13.3 0-24 10.7-24 24s10.7 24 24 24l80 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-8 0 0-88c0-13.3-10.7-24-24-24l-32 0c-13.3 0-24 10.7-24 24s10.7 24 24 24l8 0 0 64zm40-144a32 32 0 1 0 0-64 32 32 0 1 0 0 64z"/></svg>`;
    static isText = false;
    static name = 'app/howitworks';
    static category = 'Sections';
    static description = 'The steps of the process, next to an illustration.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Howitworks.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.linkText = textField({label: 'Link Text', value: data.linkText});
        this.body.appendChild(this.fields.linkText.element);

        this.fields.linkUrl = textField({label: 'Link URL', value: data.linkUrl});
        this.body.appendChild(this.fields.linkUrl.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonLink = textField({label: 'Button Link', value: data.buttonLink});
        this.body.appendChild(this.fields.buttonLink.element);

        this.fields.imageId = imageField({label: 'Image', id: data.imageId, filename: data.filename, imagePath: this.config.imagePath ?? ''});
        this.body.appendChild(this.fields.imageId.element);

        this.fields.steps = tabsField({
            label: 'Steps', itemLabel: 'Step', items: data.steps,
            build: (item) => ({
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
            }),
        });
        this.body.appendChild(this.fields.steps.element);

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
            linkUrl: this.fields.linkUrl.getValue(),
            buttonText: this.fields.buttonText.getValue(),
            buttonLink: this.fields.buttonLink.getValue(),
            imageId: this.fields.imageId.getValue().id,
            filename: this.fields.imageId.getValue().filename,
            steps: this.fields.steps.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
