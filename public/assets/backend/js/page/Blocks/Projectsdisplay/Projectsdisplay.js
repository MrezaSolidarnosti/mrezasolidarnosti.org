import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, listField, richTextField, tabsField, textField} from "../fields.js";

export default class Projectsdisplay extends Block {
    static label = 'Projects Display';
    static keywords = ["projects","cards","display"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M448 96l0 128-160 0 0-128 160 0zm0 192l0 128-160 0 0-128 160 0zM224 224L64 224 64 96l160 0 0 128zM64 288l160 0 0 128L64 416l0-128zM64 32C28.7 32 0 60.7 0 96L0 416c0 35.3 28.7 64 64 64l384 0c35.3 0 64-28.7 64-64l0-320c0-35.3-28.7-64-64-64L64 32z"/></svg>`;
    static isText = false;
    static name = 'app/projectsdisplay';
    static category = 'Sections';
    static description = 'Projects shown as illustrated cards.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Projectsdisplay.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.projects = tabsField({
            label: 'Projects', itemLabel: 'Project', items: data.projects,
            build: (item) => ({
                image: imageField({label: 'Image', id: item.imageId, filename: item.filename, imagePath: this.config.imagePath ?? ''}),
                className: textField({label: 'Class Name', value: item.className}),
                title: textField({label: 'Title', value: item.title}),
                description: richTextField({label: 'Description', value: item.description}),
                items: listField({label: 'List Items', items: item.items, addText: 'Add list item'}),
                note: richTextField({label: 'Note (below the list)', value: item.note}),
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
            projects: this.fields.projects.getValue().map((item) => ({
                imageId: item.image.id,
                filename: item.image.filename,
                className: item.className,
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
