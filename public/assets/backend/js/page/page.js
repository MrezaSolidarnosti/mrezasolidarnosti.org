import MediaLibrary from "../../../../vendor/skeletorjs/src/MediaLibrary/MediaLibrary.js";
import ContentEditor from "../../../../vendor/skeletorjs/src/ContentEditor/ContentEditor.js?v=3.0.0";
import FailedValidation from "../../../../vendor/skeletorjs/src/ContentEditor/SaveValidation/FailedValidation.js";
import SaveResponse from "../../../../vendor/skeletorjs/src/ContentEditor/SaveResponse.js";
import {config} from "./config.js";
import {events} from "../../../../vendor/skeletorjs/src/ContentEditor/events.js";
import LanguageCode from "./Modules/LanguageCode.js";
import LoginProtected from "./Modules/LoginProtected.js";
import Back from "../../../../vendor/skeletorjs/src/ContentEditor/Back/Back.js";

window.mediaLibrary = new MediaLibrary();
window.mediaLibrary.init();

let slug = initialContent.slug ?? '';


Back.registerBackUrl('/page/view');

ContentEditor.registerModule('languageCode', {
    class: LanguageCode
});

ContentEditor.registerModule('loginProtected', {
    class: LoginProtected,
    getData: (m) => m.getSelected(),
    setData: (m, v) => m.setSelected(v)
});

const contentEditor = new ContentEditor({
    config: config.contentEditor,
    initialContent: initialContent ?? null
});

contentEditor.eventEmitter.on(events.beforeSave, (data) => {
    const csrf = document.querySelector('input[name^="_csrf"]');
    data[csrf.name] = csrf.value;
});

contentEditor.saveValidationHandler.registerValidation({
    name: 'titleValidation',
    callback: () => {
        const title = contentEditor.getModule('title');
        if (title && title.getValue().trim() === '') {
            return new FailedValidation(['Title is required.']);
        }
        return true;
    }
});

contentEditor.saveValidationHandler.registerValidation({
    name: 'featuredImageValidation',
    callback: () => {
        const featuredImage = contentEditor.getModule('featuredImage').getData();
        if (!featuredImage.id || !featuredImage.src) {
            return new FailedValidation(['Featured image is required.']);
        }
        return true;
    }
});

contentEditor.saveValidationHandler.registerValidation({
    name: 'seoValidation',
    callback: () => {
        const seo = contentEditor.getModule('seo').getData();
        const errors = [];
        if(seo.title.trim() === '') {
            errors.push(['SEO title is required.']);
        }
        if(seo.description.trim() === '') {
            errors.push(['SEO description is required.']);
        }
        if(!seo.image.id || !seo.image.src) {
            errors.push(['SEO image is required.']);
        }
        if(errors.length > 0) {
            return new FailedValidation(errors);
        }
        return true;
    }
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
            action = `/page/update/${resData.data.id}/`;
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