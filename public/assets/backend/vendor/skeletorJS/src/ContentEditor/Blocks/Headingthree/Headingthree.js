import Heading from "../Heading/Heading.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";

export default class Headingthree extends Heading {
    static label = 'Heading 3';
    static keywords = ['heading', 'h3'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-280v-400h80v160h160v-160h80v400h-80v-160H200v160h-80Zm400 0v-80h240v-80H600v-80h160v-80H520v-80h240q33 0 56.5 23.5T840-600v240q0 33-23.5 56.5T760-280H520Z"/></svg>`;
    static name = 'core/headingthree';
    static category = 'text';
    static description = 'A third-level section heading.';
    static isText = true;
    static tags = ['h3'];

    render() {
        this.element = document.createElement('h3');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        this.element.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, slashCommandPlaceholder(this.eventEmitter));
        this.addListeners();
        return this.element;
    }
}
