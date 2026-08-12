import Project from "./Project.js?v=0.0.9";
import EventEmitter from "../EventEmitter/EventEmitter.js?v=0.0.9";
import Form from "./Form.js?v=0.0.9";
import Translator from "../Translator/Translator.js";

export default class Donate {
    #setupComplete = false;
    #initiatorElements;
    #container;
    #projects = [];
    #form;
    #selectedProject = null;
    eventEmitter = new EventEmitter();

    constructor({initiatorElements, container}) {
        this.#initiatorElements = initiatorElements;
        this.#container = container;
    }
    init() {
        if(this.#setupComplete) {
            return;
        }
        this.#setProjects();
        this.#setForm();
        this.#listenToEvents();
        this.#preselectExistingProject();
        this.#setupComplete = true;
    }

    /**
     * Mark a card as chosen using the same visual language as hover (.active on the
     * chosen one, .disabled on the rest). Unlike hover this survives mouseleave,
     * because the unhover handler falls back to the selection instead of clearing.
     */
    selectProject(projectId) {
        const project = this.#projects.find((project) => project.projectId === String(projectId));
        if (!project) {
            return;
        }
        this.#selectedProject = project;
        this.#applyVisual(project);
    }

    clearSelection() {
        this.#selectedProject = null;
        this.#applyVisual(null);
    }

    /** Focus one card and dim the others; null returns every card to neutral. */
    #applyVisual(focused) {
        this.#projects.forEach((project) => {
            if (!focused) {
                project.defaultVisual();
            } else if (project === focused) {
                project.focusVisual();
            } else {
                project.disableVisual();
            }
        });
    }

    #preselectExistingProject() {
        const existingData = this.#getExistingData();
        if (!existingData || existingData.projectId === null || existingData.projectId === undefined) {
            return;
        }
        this.selectProject(existingData.projectId);
    }

    /**
     * Hand the donor's saved monthly amounts to the form, but only when they opened the
     * card they are already pledged to — the amounts belong to that project, so
     * prefilling them into a different one would misrepresent the pledge.
     */
    #prefillIfSaved(project) {
        const existingData = this.#getExistingData();
        if (!existingData || String(existingData.projectId) !== project.projectId) {
            return;
        }
        this.eventEmitter.emit('prefillForm', existingData);
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
               thankYouTitle: initiatorElement.getAttribute('data-title')
                   ?? Translator.translate('Thank you for choosing to donate to the Solidarity Network'),
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
        });
        this.#form.init();
    }

    #listenToEvents() {
        this.eventEmitter.on('projectHovered', (hoveredProject) => {
            this.#applyVisual(hoveredProject);
        });
        this.eventEmitter.on('projectUnhovered', () => {
            // Back to the selection, not to neutral — otherwise hovering anything
            // would silently drop the pre-select.
            this.#applyVisual(this.#selectedProject);
        });

        this.eventEmitter.on('showForm', (project) => {
            this.#container.classList.add('hidden');
            // Deliberately does not touch #selectedProject: opening a card is not choosing
            // it, so cancelling out with X leaves the donor's existing choice highlighted.
            this.#prefillIfSaved(project);
        });
        this.eventEmitter.on('donationSaved', (projectId) => {
            this.selectProject(projectId);
        });
        this.eventEmitter.on('formClosed', () => {
            this.#container.classList.remove('hidden');
            this.#applyVisual(this.#selectedProject);
        });
    }

}