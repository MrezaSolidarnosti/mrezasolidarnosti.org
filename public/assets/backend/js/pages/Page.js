import Translator from "../../../../vendor/skeletorjs/src/Translator/Translator.js";
import CrudPage from "../../../../vendor/skeletorjs/src/Page/CrudPage.js";
import Config from "../../../../vendor/skeletorjs/src/Config/Config.js";
import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";

export default class Page extends CrudPage {
    preload() {
        this.setDataTableAction({
            name: 'view',
            label: 'View',
            content: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></svg>',
            order: 2,
            callback: (entity) => {
                let url = `${Config.get('frontendUrl')}`;
                if(entity.columns.languageCode === 'en') {
                    url += '/en'
                }
                url+= `/${entity.columns.slug}`;
                window.open(url, '_blank');

            }
        });

        this.setDataTableAction({
            name: 'createTranslation',
            label: 'Create Translation',
            content: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M160 0c17.7 0 32 14.3 32 32l0 32 128 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-9.6 0-8.4 23.1c-16.4 45.2-41.1 86.5-72.2 122 14.2 8.8 29 16.6 44.4 23.5l50.4 22.4 62.2-140c5.1-11.6 16.6-19 29.2-19s24.1 7.4 29.2 19l128 288c7.2 16.2-.1 35.1-16.2 42.2s-35.1-.1-42.2-16.2l-20-45-157.5 0-20 45c-7.2 16.2-26.1 23.4-42.2 16.2s-23.4-26.1-16.2-42.2l39.8-89.5-50.4-22.4c-23-10.2-45-22.4-65.8-36.4-21.3 17.2-44.6 32.2-69.5 44.7L78.3 380.6c-15.8 7.9-35 1.5-42.9-14.3s-1.5-35 14.3-42.9l34.5-17.3c16.3-8.2 31.8-17.7 46.4-28.3-13.8-12.7-26.8-26.4-38.9-40.9L81.6 224.7c-11.3-13.6-9.5-33.8 4.1-45.1s33.8-9.5 45.1 4.1l10.2 12.2c11.5 13.9 24.1 26.8 37.4 38.7 27.5-30.4 49.2-66.1 63.5-105.4l.5-1.2-210.3 0C14.3 128 0 113.7 0 96S14.3 64 32 64l96 0 0-32c0-17.7 14.3-32 32-32zM416 270.8L365.7 384 466.3 384 416 270.8z"/></svg>',
            order: 3,
            callback: async (entity) => {
                const res = await fetch(`/page/createTranslation/${entity.id}`);
                if(res.redirected && res.url.includes('loginForm')) {
                    Message.spawn({
                        message: `<div>${Translator.translate('Your session has expired')}. ${Translator.translate('Please')} <a style="color:#4fc46d" href="/" title="log in">${Translator.translate('log in')}</a> ${Translator.translate('again')}.</div>`,
                        type: Message.TYPES.ERROR,
                        view: {
                            container: this.getMessagesContainerFixed(),
                            type: Message.VIEW_TYPES.NOTIFICATION,
                        }
                    });
                    return;
                }
                const resData = await res.json();
                const success = this.handleResponseFromForm(new Response(resData));
                if(success) {
                    this.reloadTable();
                }
            }
        });
    }

    actionFilter = (action, entity) => {
        if(action.getName() === 'createTranslation' && !entity.canCreateTranslationPage) {
            return null;
        }

        return action;
    }

    finalize() {
        this.setDataTableAction({
            name: 'edit',
            label: Translator.translate('Edit'),
            order: 0,
            className: 'editEntity',
            content: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M441 58.9L453.1 71c9.4 9.4 9.4 24.6 0 33.9L424 134.1 377.9 88 407 58.9c9.4-9.4 24.6-9.4 33.9 0zM209.8 256.2L344 121.9 390.1 168 255.8 302.2c-2.9 2.9-6.5 5-10.4 6.1l-58.5 16.7 16.7-58.5c1.1-3.9 3.2-7.5 6.1-10.4zM373.1 25L175.8 222.2c-8.7 8.7-15 19.4-18.3 31.1l-28.6 100c-2.4 8.4-.1 17.4 6.1 23.6s15.2 8.5 23.6 6.1l100-28.6c11.8-3.4 22.5-9.7 31.1-18.3L487 138.9c28.1-28.1 28.1-73.7 0-101.8L474.9 25C446.8-3.1 401.2-3.1 373.1 25zM88 64C39.4 64 0 103.4 0 152V424c0 48.6 39.4 88 88 88H360c48.6 0 88-39.4 88-88V312c0-13.3-10.7-24-24-24s-24 10.7-24 24V424c0 22.1-17.9 40-40 40H88c-22.1 0-40-17.9-40-40V152c0-22.1 17.9-40 40-40H200c13.3 0 24-10.7 24-24s-10.7-24-24-24H88z"/></svg>',
            callback: (entity) => {
                window.location.href = '/page/form/' + entity.id;
            }
        })
    }

    createNewCallback = (e) => {
        window.location.href = '/page/form/';
    }
}