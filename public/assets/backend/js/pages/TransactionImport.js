import CrudPage from "../../../../vendor/skeletorjs/src/Page/CrudPage.js";


export default class TransactionImport extends CrudPage {
    #data;
    #formTabs;
    #formAction;
    modalConfig = {
        width: '100%',
        height:'100%'
    }

    actionFilter = (action, entity) => {
        const role = document.getElementById('navigation').dataset.role;
        if (action.getName() === 'delete' && role != 1) {
            return false;
        }
        return action;
    }
}