import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading, tabsField, textAreaField, textField} from "../fields.js";

export default class Whywearedifferent extends Block {
    static label = 'Why We Are Different';
    static keywords = ["why","different","reasons"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M297.2 248.9C311.6 228.3 320 203.2 320 176c0-70.7-57.3-128-128-128S64 105.3 64 176c0 27.2 8.4 52.3 22.8 72.9c3.7 5.3 8.1 11.3 12.8 17.7l0 0c12.9 17.7 28.3 38.9 39.8 59.8c10.4 19 15.7 38.8 18.3 57.5L109 384c-2.2-12-5.9-23.7-11.8-34.5c-9.9-18-22.2-34.9-34.5-51.8l0 0c-5.2-7.1-10.4-14.2-15.4-21.4C27.6 247.9 16 213.3 16 176C16 78.8 94.8 0 192 0s176 78.8 176 176c0 37.3-11.6 71.9-31.4 100.3c-5 7.2-10.2 14.3-15.4 21.4l0 0c-12.3 16.8-24.6 33.7-34.5 51.8c-5.9 10.8-9.6 22.5-11.8 34.5L143.4 384c2.6-18.7 7.9-38.5 18.3-57.5c11.5-20.9 26.9-42.1 39.8-59.8l0 0 0 0c4.7-6.4 9-12.4 12.7-17.7zM192 512c-44.2 0-80-35.8-80-80l0-16 160 0 0 16c0 44.2-35.8 80-80 80z"/></svg>`;
    static isText = false;
    static name = 'app/whywearedifferent';
    static category = 'Sections';
    static description = 'What sets the network apart, reason by reason.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Whywearedifferent.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        this.fields.title = textField({label: 'Title', value: data.title});
        this.body.appendChild(this.fields.title.element);

        this.fields.subtitle = textField({label: 'Subtitle', value: data.subtitle});
        this.body.appendChild(this.fields.subtitle.element);

        this.fields.coloredSubtitle = textField({label: 'Colored Subtitle', value: data.coloredSubtitle});
        this.body.appendChild(this.fields.coloredSubtitle.element);

        this.fields.description = textAreaField({label: 'Description', value: data.description});
        this.body.appendChild(this.fields.description.element);

        this.fields.footerText = textField({label: 'Footer Text', value: data.footerText});
        this.body.appendChild(this.fields.footerText.element);

        this.fields.reasons = tabsField({
            label: 'Reasons', itemLabel: 'Reason', items: data.reasons,
            build: (item) => ({
                title: textField({label: 'Title', value: item.title}),
                description: textAreaField({label: 'Description', value: item.description}),
            }),
        });
        this.body.appendChild(this.fields.reasons.element);

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
            coloredSubtitle: this.fields.coloredSubtitle.getValue(),
            description: this.fields.description.getValue(),
            footerText: this.fields.footerText.getValue(),
            reasons: this.fields.reasons.getValue(),
        };
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
