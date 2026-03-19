import CrudPage from "https://skeletor.greenfriends.systems/skeletorjs/src/Page/CrudPage.js";
import PaymentMethods from "./PaymentMethods.js";

export default class Donor extends CrudPage {
    paymentMethods;
    constructor() {
        super();
        this.dataTableOptions = {
            enableCheckboxes: true,
            shiftCheckboxModifier: true
        };
        this.modalOptions = {
            createModalWidth: '70%',
            createModalHeight: '70%',
            editModalWidth: '70%',
            editModalHeight: '70%'
        }
    }

    onFormReady(data) {
        this.paymentMethods = new PaymentMethods();
        this.paymentMethods.init();
    }

    onModalBeforeClose() {
        if(this.paymentMethods) {
            this.paymentMethods.destroy();
            this.paymentMethods = null;
        }
    }

    actionFilter = (action, entity) => {
        const role = document.getElementById('navigation').dataset.role;
        if (action.getName() === 'delete' && role != 1) {
            return false;
        }
        return action;
    }

}