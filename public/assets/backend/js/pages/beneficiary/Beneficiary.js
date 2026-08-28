import CrudPage from "../../../../../vendor/skeletorjs/src/Page/CrudPage.js";
// Versioned because crud.js only busts the page module itself — a relative import stays
// cached under its own URL, and a stale RegisteredProjects.js would post rows with no id.
import RegisteredProjects from "./RegisteredProjects.js?v=0.0.6";
import PaymentMethods from "./PaymentMethods.js";

export default class Beneficiary extends CrudPage {

    registeredProjectsInForm = null;
    paymentMethods = null;
    constructor() {
        super();
        this.dataTableOptions = {
            enableCheckboxes: true,
            shiftCheckboxModifier: true
        };
        this.modalConfig = {
            width: '70%',
            height: '70%'
        }
    }

    onFormReady(data) {
        this.#initRegisteredPeriods();
        this.#initPaymentMethods();
    }

    onModalBeforeClose() {
        if(this.registeredProjectsInForm) {
            this.registeredProjectsInForm.destroy();
            this.registeredProjectsInForm = null;
        }
        if(this.paymentMethods) {
            this.paymentMethods.destroy();
            this.paymentMethods = null;
        }
    }

    onFormSubmitStart() {
        if(this.registeredProjectsInForm) {
            this.registeredProjectsInForm.enableAllPeriodOptions();
        }
        if(this.paymentMethods) {
            this.paymentMethods.injectPaymentMethodsData();
        }
    }

    onFormSubmitEnd(response) {
        if(!response.status) {
            if(this.paymentMethods) {
                this.paymentMethods.removeInjectedPaymentMethodsData();
            }
            if(this.registeredProjectsInForm) {
                this.registeredProjectsInForm.reEvaluateSelects();
            }
        }
    }

    #initRegisteredPeriods() {
        this.registeredProjectsInForm = new RegisteredProjects();
        this.registeredProjectsInForm.init();
    }

    #initPaymentMethods() {
        this.paymentMethods = new PaymentMethods();
        this.paymentMethods.init();
    }

    actionFilter = (action, entity) => {
        const role = document.getElementById('navigation').dataset.role;
        if (action.getName() === 'delete' && role != 1) {
            return false;
        }
        return action;
    }

    tdStyler = (td, columnName, columnValue, entity) => {
        if (columnName === 'delegateVerified') {
            switch (columnValue) {
                case 'Da':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GREEN);
                    break;
                case 'Ne':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.RED);
                    break;
            }
        }
        if (columnName === 'status') {
            switch (columnValue) {
                case 'Ok':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GREEN);
                    break;
                case 'Problem':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GRAY);
                    break;
                case 'Gave up':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GRAY);
                    break;
            }
        }
        return td;
    }
}
