import {textEditorSelectors} from "./textEditorSelectors.js";
import EventEmitter from "../EventEmitter/EventEmitter.js";
import Command from "./Command/Command.js";
import {events} from "./Command/events.js";
import Config from "../Config/Config.js";

export default class TextEditor {

    #container;
    #inputElement;
    #data;
    #commandsContainer;
    #contentContainer;
    #input;
    #viewCode;
    #codeEditorContainer;
    #codeEditor;
    #closeCodeEditorButton;
    #readOnly;
    #quill;

    #commands = new Map();
    commandNames =
        [
            'bold',
            'createLink',
            'unlink',
            'insertOrderedList',
            'insertUnorderedList',
            'italic',
            'underline',
            'justifyLeft',
            'justifyCenter',
            'justifyRight'
        ];
    #eventEmitter = new EventEmitter();
    constructor(container, input, data = null) {
        this.#container = container;
        this.#inputElement = input;
        this.#data = data;
        this.#container.setAttribute('spellcheck', 'false');
    }

    init() {
        const fontSizeArr = ['8px','9px','10px','12px','14px','16px','20px','24px','32px','42px','54px','68px','84px','98px'];
        const Size = Quill.import('attributors/style/size');
        Size.whitelist = fontSizeArr;
        Quill.register(Size, true);


        const frontDomain = Config.get('frontendUrl');
        if(frontDomain) {
            const Link = Quill.import('formats/link');
            class CustomLink extends Link {
                static create(value) {
                    let node = super.create(value);
                    this.applyAttrs(node, value);
                    return node;
                }

                static formats(node) {
                    return node.getAttribute('href');
                }

                format(name, value) {
                    super.format(name, value);

                    if (name === 'link' && value) {
                        CustomLink.applyAttrs(this.domNode, value);
                    }
                }

                static applyAttrs(node, value) {
                    value = this.sanitize(value);

                    node.setAttribute("href", value);

                    if (value.startsWith(frontDomain) || value.startsWith("/")) {
                        node.removeAttribute("target");
                    } else {
                        node.setAttribute("target", "_blank");
                    }

                    // title
                    setTimeout(() => {
                        node.setAttribute("title", node.textContent);
                    }, 300);
                }
            }
            Quill.register(CustomLink);
        }
        this.#quill = new Quill(this.#container, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'size': fontSizeArr }],
                    ['bold', 'italic', 'underline', 'link', 'list'],
                    [{align: ''}, {align: 'center'}, {align: 'right'}],
                     [{ 'header': 1 }, { 'header': 2 }, { 'header': 3 }, { 'header': 4 }, { 'header': 5 }, { 'header': 6 }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }]
                ]
            }
        });
        if(this.#data) {
            this.#quill.root.innerHTML = this.#data;
        }
        this.#quill.on('editor-change', (eventName, ...args) => {
            if (eventName === 'text-change') {
                this.#inputElement.value = this.#quill.root.innerHTML;
            }
        })
        if(this.#container.getAttribute(textEditorSelectors.attributes.readOnly)) {
            this.#quill.disable();
        }
        // try {
        //     this.#setProperties();
        //     if(!this.#readOnly) {
        //         this.#generateCommands();
        //         this.#addListeners();
        //         this.#listenToCommands();
        //     }
        // } catch (e) {
        //     console.error(e);
        // }
    }

    #setProperties() {
        this.#commandsContainer = this.#container.querySelector(`.${textEditorSelectors.classes.commandsContainer}`);
        this.#contentContainer = this.#container.querySelector(`.${textEditorSelectors.classes.contentContainer}`);
        this.#input = this.#container.querySelector(`.${textEditorSelectors.classes.inputValue}`);
        this.#viewCode = this.#container.querySelector(`.${textEditorSelectors.classes.codeButton}`);
        this.#codeEditorContainer = this.#container.querySelector(`.${textEditorSelectors.classes.codeEditorContainer}`);
        this.#codeEditor = this.#container.querySelector(`.${textEditorSelectors.classes.codeEditor}`);
        this.#closeCodeEditorButton = this.#container.querySelector(`.${textEditorSelectors.classes.closeCodeEditorButton}`);
        if(!this.#commandsContainer) {
            throw new Error(`.${textEditorSelectors.classes.commandsContainer} is not found in the container.`);
        }
        if(!this.#contentContainer) {
            throw new Error(`.${textEditorSelectors.classes.contentContainer} is not found in the container.`);
        }
        if(!this.#input) {
            throw new Error(`input is not found in the container.`);
        }
        if(!this.#viewCode) {
            throw new Error(`.${textEditorSelectors.classes.codeButton} is not found in the container.`);
        }
        if(!this.#codeEditorContainer) {
            throw new Error(`.${textEditorSelectors.classes.codeEditorContainer} is not found in the container.`);
        }
        if(!this.#codeEditor) {
            throw new Error(`.${textEditorSelectors.classes.codeEditor} is not found in the container.`);
        }
        if(!this.#closeCodeEditorButton) {
            throw new Error(`.${textEditorSelectors.classes.closeCodeEditorButton} is not found in the container.`);
        }
        this.#readOnly = this.#container.getAttribute(textEditorSelectors.attributes.readOnly);
    }

    #generateCommands() {
        this.commandNames.forEach((commandName) => {
            const command = new Command({commandName, eventEmitter: this.#eventEmitter});
            this.#commands.set(commandName, command);
            this.#commandsContainer.appendChild(command.generateView());
        });
    }

    #addListeners() {
        this.#contentContainer.addEventListener('input', this.#addChangeListener);
        this.#contentContainer.addEventListener('paste', this.#addPasteListener);
        this.#contentContainer.addEventListener('mouseup', this.#handleCommandsBasedOnSelection);
        this.#contentContainer.addEventListener('keyup', this.#handleCommandsBasedOnSelection);
        this.#contentContainer.addEventListener('keydown', this.#wrapSelectionInParagraphs);
        this.#codeEditor.addEventListener('input', this.#addCodeEditorInputListener);
        this.#viewCode.addEventListener('click', this.#toggleCodeEditor);
        this.#closeCodeEditorButton.addEventListener('click', this.#toggleCodeEditor);
    }

    #wrapSelectionInParagraphs = (e) => {
        if(e.key === 'Enter') {
            const selection = document.getSelection();
            const range = selection.getRangeAt(0);
            const startContainer = range.startContainer;
            const endContainer = range.endContainer;
            if (startContainer === endContainer && startContainer.nodeType === 3) {
                const parentElement = startContainer.parentNode;

                // Check if the parent element is a list item
                if (parentElement.tagName.toLowerCase() === 'li') {
                    // Don't wrap the text node in a paragraph if it's within a list item
                    return;
                }

                const text = startContainer.textContent;
                const textBefore = text.substring(0, range.startOffset);
                const textAfter = text.substring(range.startOffset);
                const paragraph = document.createElement('p');
                paragraph.textContent = textBefore;
                startContainer.textContent = textAfter;
                range.insertNode(paragraph);
                range.setStartAfter(paragraph);
                range.setEndAfter(paragraph);
                selection.removeAllRanges();
                selection.addRange(range);
            }
        }
    }


    #addChangeListener = (e) => {
        //unsetting lists creates spans with styles
        const spans = this.#contentContainer.querySelectorAll('span');
        spans.forEach((span) => {
            span.removeAttribute('style');
        });
        this.#input.value = this.#contentContainer.innerHTML;
        this.#input.dispatchEvent(new Event('change'));
        this.#codeEditor.value = this.#contentContainer.innerHTML;
    }

    #addCodeEditorInputListener = () => {
        this.#contentContainer.innerHTML = this.#codeEditor.value;
        this.#input.dispatchEvent(new Event('change'));
        this.#input.value = this.#codeEditor.value;
    }

    #addPasteListener = (e) => {
        e.preventDefault();
        document.execCommand('insertText', false, e.clipboardData.getData('text/plain'));
    }

    #handleCommandsBasedOnSelection = () => {
        this.#commands.get('bold').toggleActiveBasedOnCommandState(document.queryCommandState('bold'));
        this.#commands.get('insertOrderedList').toggleActiveBasedOnCommandState(document.queryCommandState('insertOrderedList'));
        this.#commands.get('insertUnorderedList').toggleActiveBasedOnCommandState(document.queryCommandState('insertUnorderedList'));
        this.#commands.get('italic').toggleActiveBasedOnCommandState(document.queryCommandState('italic'));
        this.#commands.get('underline').toggleActiveBasedOnCommandState(document.queryCommandState('underline'));
        this.#commands.get('justifyLeft').toggleActiveBasedOnCommandState(document.queryCommandState('justifyLeft'));
        this.#commands.get('justifyCenter').toggleActiveBasedOnCommandState(document.queryCommandState('justifyCenter'));
        this.#commands.get('justifyRight').toggleActiveBasedOnCommandState(document.queryCommandState('justifyRight'));
    }

    #toggleCodeEditor = () => {
        this.#codeEditorContainer.classList.toggle(textEditorSelectors.classes.active);
    }

    #listenToCommands() {
        this.#eventEmitter.on(events.commandClicked, (command) => {
            if(command.getCommandName() === 'insertOrderedList' && document.queryCommandState('insertUnorderedList')) {
                this.#commands.get('insertUnorderedList').unHighlight();
            }
            if(command.getCommandName() === 'insertUnorderedList' && document.queryCommandState('insertOrderedList')) {
                this.#commands.get('insertOrderedList').unHighlight();
            }
            const justifyCommands = ['justifyLeft', 'justifyCenter', 'justifyRight'];
            if(justifyCommands.includes(command.getCommandName())) {
                justifyCommands.forEach((commandName) => {
                    if (command.getCommandName() !== commandName) {
                        this.#commands.get(commandName).unHighlight();
                    }
                });
            }
        });
    }

    static generateHTML({inputName, value}) {
        // const container = document.createElement('div');
        // const valueEncoded = value?.replace(/&/g, '&amp;')
        //     .replace(/</g, '&lt;')
        //     .replace(/>/g, '&gt;')
        //     .replace(/"/g, '&quot;')
        //     .replace(/'/g, '&#039;');
        // container.classList.add(textEditorSelectors.classes.container);
        // container.innerHTML = `
        //                     <div class="${textEditorSelectors.classes.commandsContainer}">
        //                         <div class="${textEditorSelectors.classes.codeButton}">
        //                             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></svg>
        //                         </div>
        //                     </div>
        //                     <div class="${textEditorSelectors.classes.codeEditorContainer}">
        //                         <div class="closeCodeEditor">
        //                         <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>
        //                         </div>
        //                         <textarea spellcheck="false" class="${textEditorSelectors.classes.codeEditor}">${value ?? ''}</textarea>
        //                     </div>
        //
        //                     <div spellcheck="false" class="${textEditorSelectors.classes.contentContainer}" contenteditable="true">${value ?? ''}</div>
        //                     <input class="${textEditorSelectors.classes.inputValue} ${textEditorSelectors.classes.input}" type="text" name="${inputName ?? ''}" value="${valueEncoded}">`;
        // return container;
        const container = document.createElement('div');
        return container;
    }

    getContent() {
        // return this.#contentContainer.innerHTML;
        return this.#quill.root.innerHTML;
    }


    destroy() {
        this.#quill = null;
        this.#eventEmitter.destroy();
        this.#eventEmitter = null;
        // this.#contentContainer.removeEventListener('input', this.#addChangeListener);
        // this.#contentContainer.removeEventListener('paste', this.#addPasteListener);
        // this.#contentContainer.removeEventListener('mouseup', this.#handleCommandsBasedOnSelection);
        // this.#contentContainer.removeEventListener('keyup', this.#handleCommandsBasedOnSelection);
        // this.#codeEditor.removeEventListener('input', this.#addCodeEditorInputListener);
        // this.#viewCode.removeEventListener('click', this.#toggleCodeEditor);
        // this.#closeCodeEditorButton.removeEventListener('click', this.#toggleCodeEditor);
        // this.#contentContainer.removeEventListener('keydown', this.#wrapSelectionInParagraphs);
        // this.#commands.forEach((command) => {
        //     command.destroy();
        // });
        // this.#container = null;
        // this.#commandsContainer = null;
        // this.#contentContainer = null;
        // this.#input = null;
        // this.commandNames = null;
        // this.#commands = null;
        // this.#codeEditorContainer = null;
        // this.#viewCode = null;
        // this.#closeCodeEditorButton = null;
        // this.#eventEmitter.destroy();
        // this.#eventEmitter = null;
        // this.#addChangeListener = null;
        // this.#addPasteListener = null;
        // this.#handleCommandsBasedOnSelection = null;
        // this.#toggleCodeEditor = null;
        // this.#addCodeEditorInputListener = null;
        // this.#wrapSelectionInParagraphs = null;
    }
}