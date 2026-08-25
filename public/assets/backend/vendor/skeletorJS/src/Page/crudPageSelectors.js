export const crudPageSelectors = Object.freeze({
    ids: {
        createNewButton: 'create',
        messageContainer: 'messageContainer',
        messageContainerFixed: 'messageContainerFixed',
        formTabs: 'formTabs',
        main: 'main',
    },
    classes: {
        hidden: 'hidden',
        freeze: 'freeze',
        input: 'input',
        button: 'btn',
        primary: 'primary',
        ajaxInputSearch: 'ajaxInputSearch',
        ajaxInputSearchViewInput: 'viewInput',
        ajaxInputSearchTargetInput: 'targetInput',
        inputContainer: 'inputContainer',
        preventEnterSubmit: 'preventEnterSubmit',
        buttonSmall: 'small',
        buttonHollow: 'hollow',
        buttonGlow: 'glow',
        textEditorInput: 'textEditorInput',
    },
    attributes: {
        ajaxInputSearchEndpoint: 'data-endpoint',
        ajaxInputSearchViewColumnName: 'data-view-column-name',
        ajaxInputSearchIdColumnName: 'data-id-column-name',
        ajaxInputSearchSearchFilters: 'data-search-filters',
        ajaxInputSearchInputName: 'data-input-name',
        ajaxMultipleValuesSearchNewInputName: 'data-new-input-name'
    }
})