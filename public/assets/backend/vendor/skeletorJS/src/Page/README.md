# CrudPage Class

## Lifecycle Methods

### init()

The `init()` method initializes the page and should be called when the page is loaded. By default, it calls the `preload()` method, which can be overridden in subclasses to perform additional initialization tasks. It then performs internal setups. After the internal setups it calls the `finalize()` method, which can also be overridden in subclasses to perform additional initialization tasks.

### preload()

The `preload()` method is called as the first step of the initialization and can be overridden in subclasses to perform any necessary preloading tasks, setting up event listeners, etc.

### finalize()
The `finalize()` method is called as the last step of the initialization and can be overridden in subclasses to perform any necessary finalization tasks.

### destroy()

The `destroy()` method cleans up resources associated with the page and should be called when the page is unloaded or no longer needed.

## Modal Event Methods
### onModalBeforeClose()
The `onModalBeforeClose()` method is called when the modal is about to close. It can be overridden in subclasses to perform any necessary tasks before the modal is closed.

### onModalClosed()
The `onModalClosed()` method is called when the modal is closed. It can be overridden in subclasses to perform any necessary tasks after the modal is closed.

## Form Event Methods
### onFormReady()
The `onFormReady(data)` method is called when the form is ready. It can be overridden in subclasses to perform any necessary tasks when the form is ready.

### onFormSubmitStart()

### onFormSubmitSuccess(response)

### onFormSubmitFail(response)

### onFormSubmitEnd(response)

## Public properties
`baseAction` - The base action for the page. This is used to construct the action for the page's CRUD operations. By default, it is set to the second segment of the current URL path, or to an empty string if the URL path doesn't have a second segment.
For example:

If the URL path is `https://example.com/product/`, the `baseAction` will be set to `product`.


`tableDataEndpoint` - The action to use for fetching table data. By default, it is set to `/${baseAction}/tableHandler/`.

`formEndpoint` - The action to use for getting the form content. By default, it is set to `/${baseAction}/form/`.

`getEntityDataByIdEndpoint` - The action to use for fetching entity data by ID. By default, it is set to `/${baseAction}/getEntityData/`. ID of the entity to be fetched will be appended to this endpoint.

`deleteEntityEndpoint` - The action to use for deleting an entity. By default, it is set to `/${baseAction}/delete/`. ID of the entity to be deleted will be appended to this endpoint.

`openModalIfIdParamExists` - A boolean value indicating whether to open the modal if the ID parameter exists in the URL. By default, it is set to `true`.

---
`modal` - The modal instance to use for displaying forms.
### Modal Public methods
`openModal()` - Opens the modal.

`closeModal()` - Closes the modal.

`populateModal(content)` - Populates the modal with the given content.

`populateModalStrict(content)` - Populates the modal with the given content only if the modal is open. This is useful if an asynchronous operation is performed and the modal is closed before the operation is complete.

`getMessageContainer()` - Returns the message container of the modal.

`emptyMessageContainer()` - Empties the message container of the modal.

`isModalOpen()` - Returns true if the modal is open, otherwise false.

`startLoader()` - Starts the loader in the modal.

`stopLoader()` - Stops the loader in the modal.

`startLoaderInSubmitButton()` - Starts the loader in the submit button of the modal form.

`stopLoaderInSubmitButton()` - Stops the loader in the submit button of the modal form.

`getConfigValue(key)` - Returns the value of the given key from the modal config.

`updateCSRFToken(tokenInputString)` - Updates the CSRF token input in the modal form.

`scrollToTop()` - Scrolls the modal to the top.

`getAjaxInputSearches()` - Returns the array of AjaxInputSearch instances in the modal.

`destroy()` - Destroys the modal instance.

---

`modalConfig` - The configuration object to use for the modal instance.
Default config is:
```javascript
{
    closeOnEscape: true,
    closeOnClickOutsideOfModal: true,
    width:'1200px',
    height:'800px',
    warnUserIfFormEditedOnModalClose: false
}
```

`dataTableConfig` - The configuration object to use for the data table instance.
Default config is:
```javascript
{
    delayOnInputSearchInMs: 300,
    enableCheckboxes: true,
    shiftCheckboxModifier: false,
    userViewOptions: true,
    minimumCharactersForSearch: 3,
    mobileBreakpoint: 1024,
    showAdditionalContentOnLoad: false,
    defaultSort: [
        {order: 'DESC', orderBy: 'createdAt'},
        {order: 'DESC', orderBy: 'updatedAt'}
    ]
}
```
Default sort will apply to the table with the priority of the order of the array. If the first sort orderBy column doesn't exist in the table, it will apply the second sort and so on.
## Overriding default properties

To override these in your class, simply set them as the class property.
```javascript
class ProductPage extends CrudPage {
    baseAction = 'baseOverridden';
    tableDataEndpoint = '/getTableDataOverridden/';
    formEndpoint = '/formOverridden/';
    getEntityDataByIdEndpoint = '/getByIdOverridden/';
    deleteEntityEndpoint = '/deleteOverridden/';
    modalConfig = {
        closeOnEscape: false,
        closeOnClickOutsideOfModal: false,
        width:'800px',
        height:'600px',
        warnUserIfFormEditedOnModalClose: true
    }
    dataTableConfig = {
        delayOnInputSearchInMs: 500,
        enableCheckboxes: false,
        shiftCheckboxModifier: false,
        userViewOptions: false,
        minimumCharactersForSearch: 5,
        mobileBreakpoint: 768,
        showAdditionalContentOnLoad: true,
        defaultSort: [
            {order: 'ASC', orderBy: 'createdAt'},
            {order: 'ASC', orderBy: 'updatedAt'}
        ]
    }
}
```
None of the properties are required to be overridden. You can override any of them as per your requirement.

Options passed to the configs will be merged with the default options. If you want to override the default options, you can do so by passing the options you want to override. For example, if you want to override the `closeOnEscape` option, you can do so like this:
```javascript
class ProductPage extends CrudPage {
    modalConfig = {
        closeOnEscape: false
    }
}
```

---

Any non-valid options will be ignored.


`createNewCallback` - The callback to be called when the user clicks the "Create New" button.

To override this in your class, simply set it as the class property.
```javascript
class ProductPage extends CrudPage {
    createNewCallback = async () => {
        console.log('Create New button clicked');
    }
}
```

`submitFormCallback` - The callback to be called when the user submits the form.

By default, it handles the submission based on the form action, and should be changed only if necessary
```javascript
class ProductPage extends CrudPage {
    submitFormCallback = async (e) => {
        console.log('Form submitted');
    }
}
```

`getMessageContainer()` - The method to get the message container. By default, it returns the message container of the page.

`emptyMessageContainer()` - The method to empty the message container. By default, it empties the page message container.

`getMessagesContainerFixed()` - The method to get the fixed messages container. By default, it returns the fixed messages container of the page.

`emptyMessagesContainerFixed()` - The method to empty the fixed messages container. By default, it empties the fixed messages container of the page.

`handleResponseFromForm(response)` - The method to handle the response from a form. It takes a `Response` object as an argument.

`handleResponse(response)` - The method to handle a response. It takes a `Response` object as an argument.

`spawnMessageNotification(message, type)` - The method to spawn a message notification. It takes the message and the type of the message. The type is found in Message.TYPES.

`spawnMessageStatic(message, type)` - The method to spawn a static message. It takes the message and the type of the message. The type is found in Message.TYPES.

### Data Table Action Methods

`setDataTableAction(config)` - The method to set the action for the data table. It takes a config object as an argument.

The config object should have the following properties:

`name` - The name of the action.

`label` - The label of the action.

`content` - The content of the action.

`order` - The order of the action.

`useLoader` - A boolean value indicating whether to use a loader for the action.

`className` - The class name of the action.

`promptMessage` - The prompt message for the action. If provided, the action will prompt the user before performing the action.

`callback(entity)` - The callback to be called when the action is clicked. It takes the entity as an argument.

For example:
```javascript
this.setDataTableAction({
    name: 'copy',
    content: '<svg>Some svg</svg>',
    order: 1,
    useLoader: true,
    className: 'copyEntity',
    promptMessage: 'Are you sure you want to copy this entity?',
    callback: async (entity) => {
        console.log('Copy button clicked');
    }
});
```

---

`getDataTableAction(actionName)` - The method to get the action for the data table. It takes an action name as an argument.

`getDataTableActions()` - The method to get all the actions for the data table.

`removeDataTableAction(actionName)` - The method to remove the action for the data table. It takes an action name as an argument.

`setDataTableBulkAction(config)` - The method to set the bulk action for the data table. It takes a config object as an argument.

The config object should have the following properties:

`name` - The name of the action.

`content` - The content of the action.

`useLoader` - A boolean value indicating whether to use a loader for the action.

`callback(ids)` - The callback to be called when the action is clicked. It takes the ids of the selected entities as an argument.

For example:
```javascript
this.setDataTableBulkAction({
    name: 'copy',
    content: '<svg>Some svg</svg>',
    useLoader: true,
    callback: async (ids) => {
        console.log('Copying entities');
    }
});
```

`getDataTableBulkAction(actionName)` - The method to get the bulk action for the data table. It takes an action name as an argument.

`getDataTableBulkActions()` - The method to get all the actions for the data table.

`removeDataTableBulkAction(actionName)` - The method to remove the bulk action for the data table. It takes an action name as an argument.

`setDynamicDataTableAction(config)` - The method to set the dynamic action for the data table. It takes a config object as an argument.

The config object should have the following properties:

`name` - The name of the action.

`order` - The order of the action.

`useLoader` - A boolean value indicating whether to use a loader for the action.

`lockRowDuringCallback` - A boolean value indicating whether to lock the row during the callback.

`setInitialState(entity)` - The method to set the initial state of the action. It takes the entity as an argument and should return the name of the initial state.

`states` - An object containing the states of the action.

The states object should have objects which keys are the state names

Each state object can have the following properties:

`label` - The label of the action.

`content` - The content of the action.

`flashOnSuccess` - A boolean value indicating whether to flash the action on success.

`lockRowDuringCallback` - A boolean value indicating whether to lock the row during the callback.

`promptMessage` - The prompt message for the action. If provided, the action will prompt the user before performing the action.

`setNextState(entity)` - The method to set the next state of the action. It takes the entity as an argument. It should return the name of the next state if needed.

For example:
```javascript
this.setDynamicDataTableAction({
    name: 'publish',
    order: 1,
    useLoader: true,
    lockRowDuringCallback: true,
    setInitialState: (entity) => {
        switch (entity.columns.status) {
            case 'draft':
                return 'draft';
            case 'pending':
                return 'pending';
            case 'published':
                return 'published';
            default:
                return null;
        }
    },
    states: {
        draft: {
            label: 'Draft',
            content: '<svg>Some svg</svg>',
            flashOnSuccess: true,
            lockRowDuringCallback: true,
            promptMessage: 'Are you sure you want to publish this entity?',
            setNextState: (entity) => {
                // Perform any necessary tasks here
                const td = this.getRowTDByColumnName(this.getRowById(entity.id), 'status');
                td.textContent = 'pending';
                entity.columns.status = 'pending';
                return 'pending';
            }
        },
        pending: {
            label: 'Pending',
            content: '<svg>Some svg</svg>',
            flashOnSuccess: true,
            lockRowDuringCallback: true,
            promptMessage: 'Are you sure you want to publish this entity?',
            setNextState: (entity) => {
                // Perform any necessary tasks here
                const td = this.getRowTDByColumnName(this.getRowById(entity.id), 'status');
                td.textContent = 'published';
                entity.columns.status = 'published';
                return 'published';
            }
        },
        published: {
            label: 'Published',
            content: '<svg>Some svg</svg>',
            flashOnSuccess: true,
            lockRowDuringCallback: true,
            promptMessage: 'Are you sure you want to unpublish this entity?',
            setNextState: (entity) => {
                // Perform any necessary tasks here
                const td = this.getRowTDByColumnName(this.getRowById(entity.id), 'status');
                td.textContent = 'draft';
                entity.columns.status = 'draft';
                return 'draft';
            }
        }
    }
});
```

`setDataTableGroupAction(config)` - The method to set the group action for the data table. It takes a config object as an argument.

The config object can have the following properties:

`name` - The name of the action.

`content` - The content of the action.

`label` - The label of the action.

`order` - The order of the action.

`actions` - An array of actions to be included in the group action.

The actions take the same config object as the `setDataTableAction` method.

For example:
```javascript
this.setDataTableGroupAction({
    name: 'groupAction',
    content: '<svg>Some svg</svg>',
    label: 'Group Action',
    order: 1,
    actions: [
        {
            name: 'action1',
            content: 'Group Action 1',
            useLoader: true,
            className: 'action1',
            lockRowDuringCallback: true,
            flashOnSuccess: true,
            promptMessage: 'Are you sure you want to perform action 1?',
            callback: async (entity) => {
                console.log('Action 1 button clicked');
            }
        },
        {
            name: 'action2',
            content: 'Group Action 2',
            useLoader: true,
            className: 'action2',
            lockRowDuringCallback: true,
            flashOnSuccess: true,
            promptMessage: 'Are you sure you want to perform action 2?',
            callback: async (entity) => {
                console.log('Action 2 button clicked');
            }
        }
    ]
});
```

`initDataTable()` - The method to initialize the data table. By default, calls the `init()` method of the data table instance. Should be overridden in subclasses only if necessary.

`actionFilter(entity)` - The method to filter the actions for the data table. It takes the entity as an argument and should return an instance of `DataTableAction`. By default, it returns the action as is.

For example, let's add a filter to the edit action when the entity status is published:
```javascript
  actionFilter = (action, entity) => {
    if(entity.columns.status === 'published' && action.getName() === 'edit') {
      return new DataTableAction({
        name: action.getName(), // if we want to keep the same name
        content: action.getContent(), // if we want to keep the same content
        order: 0,
        useLoader: false,
        className: '',
        callback: (entity) => {
          console.log('This is a custom callback for the edit action when the status is published');
        }
      });
    }
    return action;
  }
```
Never change action and it's properties directly. Always create a new instance of `DataTableAction` and return it because the action is shared among all the entities.

`tdStyler(td, columnName, columnValue, entity)` - The method to style the table data. It takes the `td` element, the column name, the column value, and the entity as arguments and should return the `td` element. By default, it returns the `td` element as is.

For example, lets add a red background to the merchant column when the merchant is 'Test merchant' and the entity status is 'pending' (This is just an example, you can use any condition you want to style the `td` element.
```javascript
 tdStyler = (td, columnName, columnValue, entity) => {
    if(columnName === 'merchant' && columnValue === 'Test merchant' && entity.columns.status === 'pending') {
      td.style.backgroundColor = 'red';
    }
    return td
  }
```

`trStyler(tr, entity)` - The method to style the table row. It takes the `tr` element and the entity as arguments and should return the `tr` element. By default, it returns the `tr` element as is.

For example, let's add a red background to the row when the entity status is 'pending'.
```javascript
trStyler = (tr, entity) => {
    if(entity.columns.status === 'pending') {
        tr.style.backgroundColor = 'red';
    }
    return tr;
}
```

`makeTDValueToBadge(td, value, type)` - The method to convert the table data value to a badge. It takes the `td` element, the value, and the type of the badge as arguments and should return the `td` element. By default, it returns the `td` element as is.
The values for the type are found in CrudPage.BADGE_TYPES and are:
- GREEN
- RED
- BLUE
- YELLOW
- PURPLE
- ORANGE
- GRAY

If no type is provided, it will default to GREEN.

For example, let's convert the value of a status column to green or gray depending on the value.
```javascript
 tdStyler = (td, columnName, columnValue, entity) => {
    if (columnName === 'status') {
        switch(columnValue) {
            case 'Published':
                this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.GREEN);
                break;
            case 'Draft':
                this.makeTDValueToBadge(td, columnValue, CrudPage.BADGE_TYPES.ORANGE);
                break;
        }
    }
    return td
}
```

`afterRowRender(row, entity)`
The method to perform any necessary tasks after the row is rendered.
```javascript
afterRowRender = (row, entity) => {
    console.log('Row rendered');
}
```

`countTdFilter(td, columnName, columnValue, type)` - The method to filter the count of the table data TD elements if countColumnData is present in the response. It takes the `td` element, the column name, the column value, and the type (`page` or `total`) as arguments and should return the `td` element. By default, it returns the `td` element as is.

For example, let's divide the total count by 2 if the column name is 'amount'
```javascript
countTdFilter = (td, columnName, columnValue, type) => {
    if(columnName === 'amount' && type === 'total') {
        td.textContent = parseInt(columnValue) / 2;
    }
    return td;
}
```

`reloadTable(keepCurrentPage = false)` - The method to reload the table. Should be used when the table data is updated.

`populateTable()` - The method to populate the table. Should be used when the table data is not updated but the table needs to be re-rendered.

---
`applySort(sort)` - The method to apply the sort to the table. It takes the sort object as an argument.
The sort object should have the following properties:

`order` - The order of the sort.

`orderBy` - The column to sort by.

---

`applyFilter(filter)` - The method to apply the filter to the table. It takes the filter object as an argument.
The filter object should have the following properties:

`name` - The name of the filter.


`value` - The value of the filter.

---

`onTableInitialized()` - The method to perform any necessary tasks after the table is initialized.

`onTablePopulated()` - The method to perform any necessary tasks after the table is populated. Avoid using async operations in this method.

`onBeforeTablePopulate()` - The method to perform any necessary tasks before the table is populated. Avoid using async operations in this method.

`onTableFirstTimePopulated()` - The method to perform any necessary tasks after the table is populated for the first time.

---

`addFilterCheckboxModifier({
label,
filterName,
modifierName,
reloadTable = true,
keepPagination = true
})`

The method to add a filter checkbox modifier to an existing filter.If the checkbox is checked, it will set a filter for the data table with the given modifier name. It will not override the filterName filter, it simply adds a new filter with the modifier name.

The reloadTable parameter is optional and defaults to true. If set to false, the table will not be reloaded after the filter is applied.

The keepPagination parameter is optional and defaults to true. By itself it does nothing if reloadTable is false. If set to true, the table will keep the current page after the filter is applied and table is reloaded.

---

`getRowById(id)` - The method to get the row element by ID. It takes the ID of the entity as an argument and returns the row element.

`getRowTDByColumnName(row, columnName)` - The method to get the TD element by column name. It takes the row element and the column name as arguments and returns the TD element.

