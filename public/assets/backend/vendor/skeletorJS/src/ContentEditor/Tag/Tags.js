import {contentEditorSelectors} from "../contentEditorSelectors.js";
import BaseModule from "../BaseModule.js";
import AjaxMultipleValuesSearch from "../../AjaxMultipleValuesSearch/AjaxMultipleValuesSearch.js";
import {crudPageSelectors} from "../../Page/crudPageSelectors.js";
import {ajaxMultipleValuesSearchSelectors} from "../../AjaxMultipleValuesSearch/ajaxMultipleValuesSearchSelectors.js";
import Translator from "../../Translator/Translator.js";


export default class Tags extends BaseModule {

    #setupComplete = false;
    container;
    viewInput;
    ajaxInputContainer;
    ajaxMultipleValuesSearch;
    init() {
        if(this.#setupComplete) {
            return;
        }

        this.#setElements();

        this.#setupComplete = true;
    }

    #setElements() {
        this.container = document.getElementById(contentEditorSelectors.ids.tagsContainer);
        this.ajaxInputContainer = document.getElementById(contentEditorSelectors.ids.tagSearchContainer);
        this.viewInput = document.getElementById(contentEditorSelectors.ids.tagViewInput);
        if(this.ajaxInputContainer) {
            this.ajaxMultipleValuesSearch = new AjaxMultipleValuesSearch({
                container: this.container,
                input: this.ajaxInputContainer.querySelector(`.${crudPageSelectors.classes.ajaxInputSearchViewInput}`),
                searchValuesInput: this.ajaxInputContainer.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.searchValuesInput}`),
                endpoint: this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxInputSearchEndpoint),
                viewColumnName: this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxInputSearchViewColumnName),
                idColumnName: this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxInputSearchIdColumnName),
                inputName: this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxInputSearchInputName),
                inputNameNew: this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxMultipleValuesSearchNewInputName) ?? null,
                valuesContainer: this.ajaxInputContainer.querySelector(`.${ajaxMultipleValuesSearchSelectors.classes.valuesContainer}`),
                searchFilters: JSON.parse(this.ajaxInputContainer.getAttribute(crudPageSelectors.attributes.ajaxInputSearchSearchFilters) ?? null),
                searchPlaceholder: Translator.translate('Search')
            });
            this.ajaxMultipleValuesSearch.init();
            if(this.isReadOnly()) {
                this.viewInput.setAttribute('disabled', true);
                const values = this.container.querySelectorAll(`.${contentEditorSelectors.classes.tagValue}`);
                values.forEach((value) => {
                   value.setAttribute('disabled', true);
                });
            }
        }
    }

    getData() {
        const existing = [];
        const newTags = [];
        this.container.querySelectorAll('input[name="tags[]"]').forEach((input) => {
           existing.push(parseInt(input.value));
        });

        this.container.querySelectorAll('input[name="newTags[]"]').forEach((input) => {
            newTags.push(input.value);
        });
        return {existing, newTags};
    }


    setData(data) {
        if(data.length > 0) {
            data.forEach((tag) => {
                const element = AjaxMultipleValuesSearch.generateValue('tags[]', tag.id, tag.name);
                if(this.isReadOnly()) {
                    element.setAttribute('disabled', 'true');
                }
                this.ajaxMultipleValuesSearch.addElementValue(element);
            });
        }
    }

    destroy() {
        super.destroy();
        if(this.ajaxMultipleValuesSearch) {
            this.ajaxMultipleValuesSearch.destroy();
        }
    }
}