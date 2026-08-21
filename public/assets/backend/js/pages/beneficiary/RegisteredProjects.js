export default class RegisteredProjects {

    #initCompleted = false;
    addButton;
    template;
    #nextAvailableIdentifier = 0;
    #registeredProjects = new Map();
    init() {
        if(this.#initCompleted) {
            throw new Error('Init of RegisteredProjects had already been completed.');
        }
        this.#setElements();
        this.#addListeners();
        this.#initExisting();
        this.#initCompleted = true;
    }

    #setElements() {
        this.container = document.getElementById('registeredProjectsList');
        this.addButton = document.getElementById('addRegisteredPeriod');
        this.template = document.getElementById('registeredProjectTemplate');
        if(!this.container || !this.addButton || !this.template) {
            throw new Error('Required elements for RegisteredProjects are missing in the DOM');
        }
    }

    #addListeners() {
        if(this.addButton) {
            this.addButton.addEventListener('click', this.#addHandler);
        }
    }

    #addHandler = (e) => {
        e.preventDefault();
        this.#attachRegisteredProjectFunctionality();
    };

    #attachRegisteredProjectFunctionality(container = null) {
        let containerExisted = container;
        if(!container) {
            const fragment = this.template.content.cloneNode(true);
            container = fragment.querySelector('.registeredPeriod');
        }
        const identifier = this.#nextAvailableIdentifier;
        const projectSelect = container.querySelector('.registeredProjectSelect');
        const periodSelect = container.querySelector('.registeredPeriodSelect');
        const amountInput = container.querySelector('.registeredAmountInput');
        // Empty for a row added in the browser; carries the stored RegisteredPeriods id for
        // one that came from the database, which is what lets the save match them up.
        const idInput = container.querySelector('.registeredIdInput');
        const deleteButton = container.querySelector('.deleteRegisteredPeriod');
        container.setAttribute('data-id', identifier);


        const projectSelectCallback = () => {
            if(periodSelect.value !== '-1') {
                periodSelect.value = '-1';
            }
            periodSelect.disabled = false;
            this.reEvaluateSelects();
        };

        const periodSelectCallback = () => {
            this.reEvaluateSelects();
        }

        projectSelect.addEventListener('change', projectSelectCallback);

        periodSelect.addEventListener('change', periodSelectCallback);


        const deleteCallback = (e) => {
            e.preventDefault();
            const id = parseInt(container.getAttribute('data-id'));
            if(this.#registeredProjects.has(id)) {
                this.#registeredProjects.delete(id);
            }
            container.remove();
            this.reEvaluateSelects();
        };
        deleteButton.addEventListener('click', deleteCallback, {once: true});

        if(projectSelect && periodSelect && deleteButton) {
            projectSelect.name = `registeredProjects[${identifier}][project]`;
            periodSelect.name = `registeredProjects[${identifier}][period]`;
            amountInput.name = `registeredProjects[${identifier}][amount]`;
            if(idInput) {
                idInput.name = `registeredProjects[${identifier}][id]`;
            }
            this.#registeredProjects.set(identifier, {
                container,
                projectSelect,
                periodSelect,
                deleteButton,
                deleteCallback,
                projectSelectCallback,
                periodSelectCallback
            });
            if(!containerExisted) {
                this.container.appendChild(container);
            }
            this.#nextAvailableIdentifier++;
        }
    }

    reEvaluateSelects() {
        this.#registeredProjects.forEach((data) => {
           this.reEvaluateSelect(data.projectSelect, data.periodSelect);
        });
    }

    reEvaluateSelect(projectSelect, periodSelect) {
        const id = projectSelect.value;
        const options = periodSelect.querySelectorAll('option');
        options.forEach((option) => {
            if((option.getAttribute('data-project-id') === id || option.value === '-1')) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
            if(this.#registerProjectCombinationExists(option.getAttribute('data-project-id'), option.value) || option.value === '-1') {
                option.disabled = true;
            } else {
                option.disabled = false;
            }
        });
    }

    enableAllPeriodOptions() {
        this.#registeredProjects.forEach((data) => {
            data.periodSelect.querySelectorAll('option').forEach((option) => {
               option.disabled = false;
            });
        });
    }

    #registerProjectCombinationExists(projectId, periodId) {
        let exists = false;
        const containers = document.querySelectorAll('.registeredPeriod');
        containers.forEach((container) => {
            const projectIdInRegistered = container.querySelector('.registeredProjectSelect').value;
            const periodIdInRegistered = container.querySelector('.registeredPeriodSelect').value;
            if(projectIdInRegistered === projectId && periodIdInRegistered === periodId) {
                exists = true;
            }
        });

        return exists;
    }

    #initExisting() {
        const containers = document.querySelectorAll('.registeredPeriod');
        containers.forEach((container) => {
            this.#attachRegisteredProjectFunctionality(container);
        });
        this.reEvaluateSelects();
    }

    destroy() {
        if(this.#registeredProjects.size) {
            this.#registeredProjects.forEach((data) => {
                data.deleteButton.removeEventListener('click', data.deleteCallback);
                data.projectSelect.removeEventListener('change', data.projectSelectCallback);
                data.periodSelect.removeEventListener('change', data.periodSelectCallback);
            });
            this.#registeredProjects.clear();
        }
        if(this.addButton) {
            this.addButton.removeEventListener('click', this.#addHandler);
        }
    }
}