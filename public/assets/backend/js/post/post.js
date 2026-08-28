import { config } from "./config.js";
import Excerpt from "./Modules/Excerpt.js";
import {configLocal} from "../config/config-local.js?v=2.0.2";
import ContentEditor from "../../../../vendor/skeletorjs/src/ContentEditor/ContentEditor.js?v=3.0.0";
import MediaLibrary from "../../../../vendor/skeletorjs/src/MediaLibrary/MediaLibrary.js";
import SaveResponse from "../../../../vendor/skeletorjs/src/ContentEditor/SaveResponse.js";
import {events} from "../../../../vendor/skeletorjs/src/ContentEditor/events.js";
import CommandPalette from "../../../../vendor/skeletorjs/src/ContentEditor/CommandPalette/CommandPalette.js";
import Translator from "../../../../vendor/skeletorjs/src/Translator/Translator.js";
import Message from "../../../../vendor/skeletorjs/src/Message/Message.js";
import Back from "../../../../vendor/skeletorjs/src/ContentEditor/Back/Back.js";

window.mediaLibrary = new MediaLibrary();
window.mediaLibrary.init();

Back.registerBackUrl('/post/view');
ContentEditor.registerModule('excerpt', {
    class: Excerpt
});


let viewOnSiteRegistered = false;
let slug = initialContent.slug ?? '';
let copyUrlRegistered = false;

CommandPalette.registerCommand({
    category: 'Navigation',
    key: 'post-list',
    label: Translator.translate('Open post list'),
    keywords: ['posts', 'list'],
    url: '/post/view',
});
CommandPalette.registerCommand({
    category: 'Navigation',
    key: 'homepage-frontend',
    label: Translator.translate('View homepage on the site'),
    keywords: ['home', 'frontend', 'live'],
    url: configLocal.frontendUrl,
});

const contentEditor = new ContentEditor({
    config: config.contentEditor,
    initialContent: initialContent ?? null
});

contentEditor.eventEmitter.on(events.beforeSave, (data) => {
    const csrf = document.querySelector('input[name^="_csrf"]');
    data[csrf.name] = csrf.value;
});

contentEditor.save = async (data) => {
    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        if (typeof value === 'object' && value !== null) {
            formData.append(key, JSON.stringify(value));
        } else {
            formData.append(key, value);
        }
    });
    const res = await fetch(action, {
        method: 'POST',
        body: formData,
    });
    const resData = await res.json();
    const messages = [];
    if(resData.errors.length > 0) {
        resData.errors.forEach((error) => {
            messages.push(error.message);
        });
    }
    if(resData.generalErrors.length > 0) {
        resData.generalErrors.forEach((error) => {
            messages.push(error.message);
        });
    }
    if(resData.status) {
        messages.push(resData.message);
        if(resData.data.id) {
            action = `/post/update/${resData.data.id}/`;
            registerViewPostOnSite();
            registerCopyPostUrl();
        }
        if(resData.data.slug) {
            contentEditor.getModule('slug')?.setValue(resData.data.slug);
            slug = resData.data.slug;
        }
    }
    if(resData.token) {
        const csrf = document.querySelector('input[name^="_csrf"]');
        if(csrf) {
            csrf.remove();
        }
        document.body.insertAdjacentHTML('afterbegin', resData.token);
    }
    return new SaveResponse({success: resData.status, messages});
};

await contentEditor.init();

if(action.includes('update')) {
   registerViewPostOnSite();
   registerCopyPostUrl();
}


function registerViewPostOnSite() {
    if(viewOnSiteRegistered) return;
    CommandPalette.registerCommand({
        category: 'This post',
        key: 'view-on-site',
        label: Translator.translate('View on site'),
        keywords: ['preview', 'frontend', 'permalink'],
        url: () => {
            return `${configLocal.frontendUrl}/blog/${slug}`
        },
    });
    viewOnSiteRegistered = true;
}

function registerCopyPostUrl() {
    if(copyUrlRegistered) return;
    CommandPalette.registerCommand({
        category: 'This post',
        key: 'copy-permalink',
        label: Translator.translate('Copy permalink'),
        keywords: ['link', 'url', 'share'],
        onSelect: () => {
            navigator.clipboard?.writeText(`${configLocal.frontendUrl}/blog/${slug}`);
            Message.spawn({
                message: 'Permalink copied to clipboard',
                type: Message.TYPES.SUCCESS,
                view: {type: Message.VIEW_TYPES.NOTIFICATION, container: contentEditor.messagesContainer},
                ephemeralTimeout: 2500,
            });
        },
    });
    copyUrlRegistered = true;
}