import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, listField, richTextField, tabsField, textField} from "../fields.js";

export default class Valuecards extends Block {
    static label = 'Value Cards';
    static keywords = ["values","cards"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M448 256A192 192 0 1 0 64 256a192 192 0 1 0 384 0zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm256 80a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm0-224a144 144 0 1 1 0 288 144 144 0 1 1 0-288zM224 256a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>`;
    static isText = false;
    static name = 'app/valuecards';
    static category = 'Sections';
    static description = 'The values, one illustrated card each.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Valuecards.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.cards = tabsField({
            label: 'Cards', itemLabel: 'Card', items: data.cards,
            build: (item) => ({
                image: imageField({label: 'Image', id: item.imageId, filename: item.filename, imagePath: this.config.imagePath ?? ''}),
                title: textField({label: 'Title', value: item.title}),
                description: richTextField({label: 'Description', value: item.description}),
                items: listField({label: 'List Items', items: item.items, addText: 'Add list item'}),
                note: richTextField({label: 'Note (below the list)', value: item.note}),
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
                description: item.description,
                items: item.items,
                note: item.note,
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
