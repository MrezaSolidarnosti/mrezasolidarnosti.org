import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, textAreaField, textField} from "../fields.js";

export default class Herotext extends Block {
    static label = 'Hero Text';
    static keywords = ["hero","text","title"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M0 64C0 46.3 14.3 32 32 32l384 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 96C14.3 96 0 81.7 0 64zM0 192c0-17.7 14.3-32 32-32l384 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 224c-17.7 0-32-14.3-32-32zM0 320c0-17.7 14.3-32 32-32l224 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 352c-17.7 0-32-14.3-32-32zM0 448c0-17.7 14.3-32 32-32l320 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 480c-17.7 0-32-14.3-32-32z"/></svg>`;
    static isText = false;
    static name = 'app/herotext';
    static category = 'Sections';
    static description = 'A text-only hero.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Herotext.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.subtitle = textAreaField({label: 'Subtitle', value: data.subtitle});
        this.body.appendChild(this.fields.subtitle.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

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
            subtitle: this.fields.subtitle.getValue(),
            description: this.fields.description.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
