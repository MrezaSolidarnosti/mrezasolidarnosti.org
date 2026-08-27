import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading} from "../fields.js";

export default class Donate extends Block {
    static label = 'Donate';
    static keywords = ["donate","donation","form"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5l0-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1l0 5.8c0 41.5 17.2 81.2 47.6 109.5z"/></svg>`;
    static isText = false;
    static name = 'app/donate';
    static category = 'Sections';
    static description = 'The donation form.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Donate.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        const note = document.createElement('p');
        note.classList.add('pageBlockNote');
        note.textContent = 'The form is rendered by the frontend, so this block has nothing to configure.';
        this.body.appendChild(note);

        return this.element;
    }

    getContainer() {
        return this.element;
    }

    focus() {
        this.element.focus();
    }

    getData() {
        return {};
    }

    destroy() {
        Object.values(this.fields).forEach((field) => field.destroy());
        this.fields = {};
        super.destroy();
        this.element.remove();
    }
}
