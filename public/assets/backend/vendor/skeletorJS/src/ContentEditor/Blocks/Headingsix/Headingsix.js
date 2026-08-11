import Heading from "../Heading/Heading.js";
import {contentEditorSelectors} from "../../contentEditorSelectors.js";
import {slashCommandPlaceholder} from "../blockPlaceholder.js";

export default class Headingsix extends Heading {
    static label = 'Heading 6';
    static keywords = ['heading', 'h6'];
    static icon = `<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-280v-400h80v160h160v-160h80v400h-80v-160H200v160h-80Zm480 0q-33 0-56.5-23.5T520-360v-240q0-33 23.5-56.5T600-680h240v80H600v80h160q33 0 56.5 23.5T840-440v80q0 33-23.5 56.5T760-280H600Zm0-160v80h160v-80H600Z"/></svg>`;
    static name = 'core/headingsix';
    static category = 'text';
    static description = 'A sixth-level section heading.';
    static isText = true;
    static tags = ['h6'];

    render() {
        this.element = document.createElement('h6');
        this.element.contentEditable = 'true';
        this.element.spellcheck = false;
        this.element.classList.add(contentEditorSelectors.classes.editableBlock);
        this.element.setAttribute(contentEditorSelectors.attributes.dataPlaceholder, slashCommandPlaceholder(this.eventEmitter));
        this.addListeners();
        return this.element;
    }
}
