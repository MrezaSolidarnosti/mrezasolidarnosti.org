import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, tabsField, textAreaField, textField} from "../fields.js";

export default class Faq extends Block {
    static label = 'FAQ';
    static keywords = ["faq","questions","accordion"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM169.8 165.3c7.9-22.3 29.1-37.3 52.8-37.3l58.3 0c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24l0-13.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1l-58.3 0c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 384a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>`;
    static isText = false;
    static name = 'app/faq';
    static category = 'Sections';
    static description = 'Questions and answers, with an optional link out.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Faq.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.buttonText = textField({label: 'Button Text', value: data.buttonText});
        this.body.appendChild(this.fields.buttonText.element);

        this.fields.buttonLink = textField({label: 'Button Link', value: data.buttonLink});
        this.body.appendChild(this.fields.buttonLink.element);

        this.fields.sections = tabsField({
            label: 'Questions', itemLabel: 'Question', items: data.sections,
            build: (item) => ({
                question: textField({label: 'Question', value: item.question}),
                answer: textAreaField({label: 'Answer', value: item.answer}),
            }),
        });
        this.body.appendChild(this.fields.sections.element);

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
            buttonText: this.fields.buttonText.getValue(),
            buttonLink: this.fields.buttonLink.getValue(),
            sections: this.fields.sections.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
