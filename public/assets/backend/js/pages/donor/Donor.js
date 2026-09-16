import PaymentMethods from "./PaymentMethods.js";
import CrudPage from "../../../../../vendor/skeletorjs/src/Page/CrudPage.js";

export default class Donor extends CrudPage {
    paymentMethods;
    constructor() {
        super();
        this.dataTableOptions = {
            enableCheckboxes: true,
            shiftCheckboxModifier: true
        };
        // modalConfig, not modalOptions: the latter was never read by CrudPage, so the modal
        // sat at the framework default. Full screen because the Instrukcije tab iframes the
        // whole transaction list, and that needs the room.
        this.modalConfig = {
            width: '100%',
            height: '100%'
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