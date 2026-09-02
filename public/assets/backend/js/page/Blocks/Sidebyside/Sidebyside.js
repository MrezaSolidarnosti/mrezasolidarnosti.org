import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, listField, richTextField, selectField, textAreaField, textField} from "../fields.js";

export default class Sidebyside extends Block {
    static label = 'Side by Side Content';
    static keywords = ["side by side","text","columns"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M0 96C0 60.7 28.7 32 64 32l384 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zM64 96l0 320 144 0 0-320L64 96zm240 0l0 320 144 0 0-320-144 0z"/></svg>`;
    static isText = false;
    static name = 'app/sidebyside';
    static category = 'Sections';
    static description = 'A heading beside its text, with adjustable padding.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Sidebyside.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textAreaField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = richTextField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.items = listField({label: 'List Items', items: data.items, addText: 'Add list item'});
        this.body.appendChild(this.fields.items.element);

        this.fields.linkText = textField({label: 'Link Text (optional)', value: data.linkText});
        this.body.appendChild(this.fields.linkText.element);

        this.fields.linkUrl = textField({label: 'Link URL (optional)', value: data.linkUrl});
        this.body.appendChild(this.fields.linkUrl.element);

        this.fields.topPadding = selectField({label: 'Top Padding', value: data.topPadding ?? 'big', options: {"big":"Big","small":"Small"}});
        this.body.appendChild(this.fields.topPadding.element);

        this.fields.bottomPadding = selectField({label: 'Bottom Padding', value: data.bottomPadding ?? 'big', options: {"big":"Big","small":"Small"}});
        this.body.appendChild(this.fields.bottomPadding.element);

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
            items: this.fields.items.getValue(),
            linkText: this.fields.linkText.getValue(),
            linkUrl: this.fields.linkUrl.getValue(),
            topPadding: this.fields.topPadding.getValue(),
            bottomPadding: this.fields.bottomPadding.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
