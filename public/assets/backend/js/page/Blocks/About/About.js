import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, groupField, richTextField, svgField, textAreaField, textField} from "../fields.js";

export default class About extends Block {
    static label = 'About';
    static keywords = ["about","what we do","projects"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm0-384a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM216 336l24 0 0-64-24 0c-13.3 0-24-10.7-24-24s10.7-24 24-24l48 0c13.3 0 24 10.7 24 24l0 88 8 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-80 0c-13.3 0-24-10.7-24-24s10.7-24 24-24z"/></svg>`;
    static isText = false;
    static name = 'app/about';
    static category = 'Sections';
    static description = 'Two intro passages with the projects that sit between them.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(About.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.firstTitle = textField({label: 'First Title', value: data.firstTitle});
        this.body.appendChild(this.fields.firstTitle.element);

        this.fields.firstDescription = richTextField({label: 'First Description', value: data.firstDescription});
        this.body.appendChild(this.fields.firstDescription.element);

        this.fields.firstFooterText = richTextField({label: 'First Footer Text', value: data.firstFooterText});
        this.body.appendChild(this.fields.firstFooterText.element);

        this.fields.secondTitle = textField({label: 'Second Title', value: data.secondTitle});
        this.body.appendChild(this.fields.secondTitle.element);

        this.fields.secondDescription = richTextField({label: 'Second Description', value: data.secondDescription});
        this.body.appendChild(this.fields.secondDescription.element);

        this.fields.projects = groupField({
            label: 'Projects', itemLabel: 'Project', count: 2, items: data.projects,
            build: (item) => ({
                svg: svgField({label: 'SVG code', value: item.svg}),
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
            }),
        });
        this.body.appendChild(this.fields.projects.element);

        this.fields.secondFooterText = richTextField({label: 'Second Footer Text', value: data.secondFooterText});
        this.body.appendChild(this.fields.secondFooterText.element);

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
            firstTitle: this.fields.firstTitle.getValue(),
            firstDescription: this.fields.firstDescription.getValue(),
            firstFooterText: this.fields.firstFooterText.getValue(),
            secondTitle: this.fields.secondTitle.getValue(),
            secondDescription: this.fields.secondDescription.getValue(),
            projects: this.fields.projects.getValue(),
            secondFooterText: this.fields.secondFooterText.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
