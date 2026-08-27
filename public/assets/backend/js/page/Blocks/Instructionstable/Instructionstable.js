import Block from "../../../../../../vendor/skeletorjs/src/ContentEditor/Blocks/Block.js";
import {blockHeading} from "../fields.js";

export default class Instructionstable extends Block {
    static label = 'Instructions Table';
    static keywords = ["instructions","table","payments"];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M64 256l160 0 0 128-160 0 0-128zm0 192l160 0 0 64-160 0c-35.3 0-64-28.7-64-64l0-64 64 0 0 64zm224 64l0-64 160 0 0 64-160 0zm224-64c0 35.3-28.7 64-64 64l0-64 64 0zM448 384l-160 0 0-128 160 0 0 128zM288 192l0-128 160 0 0 128-160 0zM224 64l0 128L64 192 64 64l160 0z"/></svg>`;
    static isText = false;
    static name = 'app/instructionstable';
    static category = 'Sections';
    static description = 'The donor\'s payment instructions.';

    element;
    body;
    fields = {};

    render() {
        const data = this.data ?? {};
        this.element = document.createElement('div');
        this.element.tabIndex = -1;
        this.element.classList.add('pageBlock');
        this.element.appendChild(blockHeading(Instructionstable.label));

        this.body = document.createElement('div');
        this.body.classList.add('pageBlockBody');
        this.element.appendChild(this.body);

        const note = document.createElement('p');
        note.classList.add('pageBlockNote');
        note.textContent = 'The rows come from the logged in donor, so this block has nothing to configure.';
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
