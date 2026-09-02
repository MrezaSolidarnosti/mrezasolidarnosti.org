import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, tabsField, textField} from "../fields.js";

export default class Contactcards extends Block {
    static label = 'Contact Cards';
    static keywords = ["contact","cards","people"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M512 80c8.8 0 16 7.2 16 16l0 320c0 8.8-7.2 16-16 16l-372.5 0c5.4-9.4 8.5-20.3 8.5-32l0-8c0-30.9-25.1-56-56-56l-48 0c-30.9 0-56 25.1-56 56l0 8c0 11.7 3.1 22.6 8.5 32L64 432c-8.8 0-16-7.2-16-16L48 96c0-8.8 7.2-16 16-16l448 0zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32zM272 256a64 64 0 1 0 -128 0 64 64 0 1 0 128 0zM368 152c-13.3 0-24 10.7-24 24s10.7 24 24 24l112 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-112 0zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24l112 0c13.3 0 24-10.7 24-24s-10.7-24-24-24l-112 0z"/></svg>`;
    static isText = false;
    static name = 'app/contactcards';
    static category = 'Sections';
    static description = 'People to contact, each with a photo and an email.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Contactcards.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.cards = tabsField({
            label: 'Cards', itemLabel: 'Card', items: data.cards,
            build: (item) => ({
                image: imageField({label: 'Image', id: item.imageId, filename: item.filename, imagePath: this.config.imagePath ?? ''}),
                title: textField({label: 'Title', value: item.title}),
                email: textField({label: 'Email', value: item.email}),
            }),
        });
        this.body.appendChild(this.fields.cards.element);

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
            cards: this.fields.cards.getValue().map((item) => ({
                imageId: item.image.id,
                filename: item.image.filename,
                title: item.title,
                email: item.email,
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
