import {formFieldSelectors} from "./formFieldSelectors.js";

export default class FormValidatorHelper {

    makeFieldValid(field, targetContainer) {
        this.removeNotice(field, targetContainer);
        field.classList.remove(formFieldSelectors.classes.invalidFormField);
    }

    invalidateField(field, message, targetContainer) {
        this.removeNotice(field);
        this.printNotice(field, message, targetContainer);
        field.classList.add(formFieldSelectors.classes.invalidFormField);
    }

    printNotice(field, message, targetContainer = true) {
        let target = targetContainer ? field.parentElement : field;
        let notice = this.generateNotice();
        notice.innerText = message;
        target.appendChild(notice);
    }

    removeNotice(field, targetContainer = true) {
        let target = targetContainer ? field.parentElement : field;
        let notice = target.querySelector('.formFieldNotice');
        if(notice) {
            notice.remove();
        }
    }

    generateNotice() {
        let notice = document.createElement('span');
        notice.classList.add('formFieldNotice');
        return notice;
    }

    scrollTo(targetElement, scrollableContainer = window) {
        const y = targetElement.getBoundingClientRect().top + scrollableContainer.scrollY;
        scrollableContainer.scroll({
            top: y,
            behavior: 'smooth'
        });
    }

}