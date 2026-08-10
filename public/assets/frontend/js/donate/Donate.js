import Project from "./Project.js?v=0.0.4";
import EventEmitter from "../EventEmitter/EventEmitter.js?v=0.0.4";
import Form from "./Form.js?v=0.0.4";

export default class Donate {
    #setupComplete = false;
    #initiatorElements;
    #container;
    #projects = [];
    #form;
    eventEmitter = new EventEmitter();
    #isForInstruction = false;

    constructor({initiatorElements, container}) {
        this.#initiatorElements = initiatorElements;
        this.#container = container;
    }
    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setIsForInstruction();
        this.#setProjects();
        this.#setForm();
        this.#listenToEvents();
        if(!this.#isForInstruction) {
            this.#openExistingProject();
        }
        this.#setupComplete = true;
    }

    #setIsForInstruction() {
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        this.#isForInstruction = urlParams.get('action') === 'instruction';
    }

    #openExistingProject() {
        const existingData = this.#getExistingData();
        if (!existingData || existingData.projectId === null || existingData.projectId === undefined) {
            return;
        }
        const project = this.#projects.find((project) => project.projectId === String(existingData.projectId));
        if (!project) {
            return;
        }
        this.eventEmitter.emit('showForm', project);
        this.eventEmitter.emit('prefillForm', existingData);
    }

    #getExistingData() {
        const element = document.getElementById('donationExistingData');
        if (!element) {
            return null;
        }
        try {
            return JSON.parse(element.textContent);
        } catch (e) {
            return null;
        }
    }

    #setProjects() {
        this.#initiatorElements.forEach((initiatorElement) => {
           const project = new Project({
               container:initiatorElement,
               thankYouTitle: initiatorElement.getAttribute('data-title') ?? 'Hvala što ste izabrali da donirate za Mrežu solidarnosti',
               eventEmitter: this.eventEmitter
           });
           project.init();
           this.#projects.push(project);
        });
    }


    #setForm() {
        this.#form = new Form({
            form: document.getElementById('donationForm'),
            eventEmitter: this.eventEmitter,
            isForInstruction: this.#isForInstruction
        });
        this.#form.init();
    }

    #listenToEvents() {
        this.eventEmitter.on('projectHovered', (hoveredProject) => {
            this.#projects.forEach((project) => {
                if(project === hoveredProject) {
                    project.focusVisual();
                } else {
                    project.disableVisual();
                }
            });
        });
        this.eventEmitter.on('projectUnhovered', (unhoveredProject) => {
            this.#projects.forEach((project) => {
                project.defaultVisual();
            });
        });

        this.eventEmitter.on('showForm', (project) => {
            this.#container.classList.add('hidden');
        });
        this.eventEmitter.on('formClosed', () => {
            this.#container.classList.remove('hidden');
        });
    }

}