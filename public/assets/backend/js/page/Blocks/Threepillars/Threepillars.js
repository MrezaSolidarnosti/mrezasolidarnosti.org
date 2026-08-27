import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, imageField, richTextField, svgField, tabsField, textAreaField, textField} from "../fields.js";

export default class Threepillars extends Block {
    static label = 'Three Pillars';
    static keywords = ["pillars","three","columns"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M32 32C14.3 32 0 46.3 0 64S14.3 96 32 96l0 320c-17.7 0-32 14.3-32 32s14.3 32 32 32l320 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l0-320c17.7 0 32-14.3 32-32s-14.3-32-32-32L32 32zM96 128l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm0 128l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zm96 128c0-17.7 14.3-32 32-32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32zM224 128l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32zM192 256c0-17.7 14.3-32 32-32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32zM96 384l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32z"/></svg>`;
    static isText = false;
    static name = 'app/threepillars';
    static category = 'Sections';
    static description = 'The three pillars, with the illustration behind them.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Threepillars.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.imageDesktopId = imageField({label: 'Desktop Illustration', id: data.imageDesktopId, filename: data.imageDesktopFilename, imagePath: this.config.imagePath ?? ''});
        this.body.appendChild(this.fields.imageDesktopId.element);

        this.fields.imageDesktopSvg = svgField({label: 'Desktop Illustration SVG code (overrides image)', value: data.imageDesktopSvg});
        this.body.appendChild(this.fields.imageDesktopSvg.element);

        this.fields.imageMobileId = imageField({label: 'Mobile Illustration', id: data.imageMobileId, filename: data.imageMobileFilename, imagePath: this.config.imagePath ?? ''});
        this.body.appendChild(this.fields.imageMobileId.element);

        this.fields.imageMobileSvg = svgField({label: 'Mobile Illustration SVG code (overrides image)', value: data.imageMobileSvg});
        this.body.appendChild(this.fields.imageMobileSvg.element);

        this.fields.pillars = tabsField({
            label: 'Pillars', itemLabel: 'Pillar', items: data.pillars, minItems: 3,
            build: (item) => ({
                title: textField({label: 'Title', value: item.title}),
                description: richTextField({label: 'Description', value: item.description}),
                buttonText: textField({label: 'Button Text', value: item.buttonText}),
                buttonLink: textField({label: 'Button Link', value: item.buttonLink}),
            }),
        });
        this.body.appendChild(this.fields.pillars.element);

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
            imageDesktopId: this.fields.imageDesktopId.getValue().id,
            imageDesktopFilename: this.fields.imageDesktopId.getValue().filename,
            imageDesktopSvg: this.fields.imageDesktopSvg.getValue(),
            imageMobileId: this.fields.imageMobileId.getValue().id,
            imageMobileFilename: this.fields.imageMobileId.getValue().filename,
            imageMobileSvg: this.fields.imageMobileSvg.getValue(),
            pillars: this.fields.pillars.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
