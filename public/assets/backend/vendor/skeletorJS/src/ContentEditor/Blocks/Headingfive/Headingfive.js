import Heading from "../Heading/Heading.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";

export default class Headingfive extends Heading {
    static label = 'Heading 5';
    static keywords = ['heading', 'h5'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-280v-400h80v160h160v-160h80v400h-80v-160H200v160h-80Zm400 0v-80h240v-80H520v-240h320v80H600v80h160q33 0 56.5 23.5T840-440v80q0 33-23.5 56.5T760-280H520Z"/></svg>`;
    static name = 'core/headingfive';
    static category = 'text';
    static description = 'A fifth-level section heading.';
    static isText = true;
    static tags = ['h5'];

    render() {
        this.element = document.createElement('h5');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        this.element.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, slashCommandPlaceholder(this.eventEmitter));
        this.addListeners();
        return this.element;
    }
}
