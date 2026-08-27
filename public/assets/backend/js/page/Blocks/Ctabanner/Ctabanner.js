import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, selectField, tabsField, textAreaField, textField} from "../fields.js";

export default class Ctabanner extends Block {
    static label = 'CTA Banner';
    static keywords = ["cta","banner","buttons"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M64 64C28.7 64 0 92.7 0 128L0 384c0 35.3 28.7 64 64 64l448 0c35.3 0 64-28.7 64-64l0-256c0-35.3-28.7-64-64-64L64 64zM224 192l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zm0 64l128 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-128 0c-8.8 0-16-7.2-16-16s7.2-16 16-16zM96 320c0-17.7 14.3-32 32-32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32zm288 0c0-17.7 14.3-32 32-32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32z"/></svg>`;
    static isText = false;
    static name = 'app/ctabanner';
    static category = 'Sections';
    static description = 'A call to action with as many buttons as it needs.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Ctabanner.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.buttons = tabsField({
            label: 'Buttons', itemLabel: 'Button', items: data.buttons,
            build: (item) => ({
                buttonTitle: textField({label: 'Button Title', value: item.buttonTitle}),
                buttonUrl: textField({label: 'Button URL', value: item.buttonUrl}),
                type: selectField({label: 'Type', value: item.type ?? 'primary', options: {"primary":"Primary","secondary":"Secondary"}}),
            }),
        });
        this.body.appendChild(this.fields.buttons.element);

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
            buttons: this.fields.buttons.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
