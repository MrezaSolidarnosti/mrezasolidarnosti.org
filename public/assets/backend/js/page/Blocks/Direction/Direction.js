import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, listField, tabsField, textAreaField, textField} from "../fields.js";

export default class Direction extends Block {
    static label = 'Direction';
    static keywords = ["direction","projects","where"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M0 96C0 60.7 28.7 32 64 32l384 0c35.3 0 64 28.7 64 64l0 320c0 35.3-28.7 64-64 64L64 480c-35.3 0-64-28.7-64-64L0 96zM64 96l0 320 144 0 0-320L64 96zm240 0l0 320 144 0 0-320-144 0z"/></svg>`;
    static isText = false;
    static name = 'app/direction';
    static category = 'Sections';
    static description = 'Where the money goes, one linked project per entry.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Direction.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.footerText = textField({label: 'Footer Text', value: data.footerText});
        this.body.appendChild(this.fields.footerText.element);

        this.fields.projects = tabsField({
            label: 'Projects', itemLabel: 'Project', items: data.projects,
            build: (item) => ({
                projectHTMLId: textField({label: 'HTML ID', value: item.projectHTMLId}),
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
                items: listField({label: 'List Items', items: item.items, addText: 'Add list item'}),
                linkText: textField({label: 'Link Text', value: item.linkText}),
                linkUrl: textField({label: 'Link URL', value: item.linkUrl}),
            }),
        });
        this.body.appendChild(this.fields.projects.element);

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
            footerText: this.fields.footerText.getValue(),
            projects: this.fields.projects.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
