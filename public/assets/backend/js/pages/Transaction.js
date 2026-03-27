import CrudPage from "https://skeletor.greenfriends.systems/skeletorjs/src/Page/CrudPage.js";
import Loader from "https://skeletor.greenfriends.systems/skeletorjs/src/Loader/Loader.js";
import Message from "https://skeletor.greenfriends.systems/skeletorjs/src/Message/Message.js";


export default class Educator extends CrudPage {
    #data;
    #formTabs;
    #formAction;
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

    preload() {
        this.setDataTableAction({
            name: 'confirm',
            label: 'Potvrdi',
            asText: true,
            content: 'Potvrdi',
            order: 1,
            callback: async (entity) => {
                // change status here with fetch
                const req = await fetch('/transaction/updateStatus/' + entity.id + '/?status=3');
                const res = await req.json();
                if (res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }

                this.reloadTable(true);
            }
        });
        this.setDataTableAction({
            name: 'cancel',
            label: 'Otkaži',
            asText: true,
            content: 'Otkaži',
            order: 1,
            callback: async (entity) => {
                // change status here with fetch
                const req = await fetch('/transaction/updateStatus/' + entity.id + '/?status=4');
                const res = await req.json();
                if (res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }

                this.reloadTable(true);
            }
        });

        this.setDataTableBulkAction({
            name: 'confirm',
            content: 'Potvrdi',
            useLoader: true,
            // promptMessage: Translator.translate('Are you sure?'),
            callback: async (ids) => {
                if(ids.length === 0) {
                    return;
                }
                const req = await fetch('/transaction/updateStatusBulk/?status=3', {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                });
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${'Your session has expired'}. ${'Please'} <a style="color:#4fc46d" href="/" title="log in">${'log in'}</a> ${'again'}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                if(res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                    this.reloadTable();
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }
            }
        });

        this.setDataTableBulkAction({
            name: 'cancel',
            content: 'Otkaži',
            useLoader: true,
            // promptMessage: Translator.translate('Are you sure?'),
            callback: async (ids) => {
                if(ids.length === 0) {
                    return;
                }
                const req = await fetch('/transaction/updateStatusBulk/?status=4', {
                    method: 'POST',
                    body: JSON.stringify({ids: ids})
                });
                if(req.redirected && req.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${'Your session has expired'}. ${'Please'} <a style="color:#4fc46d" href="/" title="log in">${'log in'}</a> ${'again'}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const res = await req.json();
                if(res.success) {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.SUCCESS,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                    this.reloadTable();
                } else {
                    Message.spawn({
                        message: res.message,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        },
                        ephemeralTimeout: 4000
                    });
                }
            }
        });
    }

    actionFilter = (action, entity) => {
        const role = document.getElementById('navigation').dataset.role;
        if (action.getName() === 'delete' && role != 1) {
            return false;
        }
        if (entity.columns.status !== 'Čeka se uplata' && (action.getName() === 'confirm' || action.getName() === 'cancel')) {
            return false;
        }

        return action;
    }

    tdStyler = (td, columnName, columnValue, entity) => {
        if (columnName === 'project') {
            switch (columnValue) {
                case 'MSP':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.BLUE);
                    break;
                case 'MSPR':
                    this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GREEN);
                    break;
            }
        }
        return td;
    }
}