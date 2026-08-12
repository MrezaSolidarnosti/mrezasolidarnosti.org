import {contentEditorSelectors} from "../contentEditorSelectors.js";

export default class Overlay {
    static overlayElement = document.getElementById(contentEditorSelectors.ids.editorOverlay);
    static showOverlay() {
        Overlay.overlayElement.classList.add(contentEditorSelectors.classes.active);
    }

    static hideOverlay() {
        Overlay.overlayElement.classList.remove(contentEditorSelectors.classes.active);
    }

    static toggleOverlay() {
        Overlay.overlayElement.classList.toggle(contentEditorSelectors.classes.active);
    }

    static isShown() {
        return Overlay.overlayElement.classList.contains(contentEditorSelectors.classes.active);
    }
}