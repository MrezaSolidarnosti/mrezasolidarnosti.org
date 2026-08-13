import ContentEditor from "../../vendor/skeletorJS/src/ContentEditor/ContentEditor.js";
import { config } from "./config.js";
import MediaLibrary from "../../vendor/skeletorJS/src/MediaLibrary/MediaLibrary.js";
import SaveResponse from "../../vendor/skeletorJS/src/ContentEditor/SaveResponse.js";
import {events} from "../../vendor/skeletorJS/src/ContentEditor/events.js";
import Excerpt from "./Modules/Excerpt.js";

window.mediaLibrary = new MediaLibrary();
window.mediaLibrary.init();

ContentEditor.registerModule('excerpt', {
    class: Excerpt
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