# AjaxSearchInput

### Usage
____

The inputs should always be wrapped in a container.

There are two inputs. 
One is used for displaying the chosen data and triggering the search.
The other is used to hold the entity id value.
```html
<div class="myContainer">
    <input name="lesson" type="hidden" value="someId">
    <input type="text" class="" readonly value="Name of the lesson">
</div>
```

In this example, we have a lesson entity. Clicking on the input with the type text
will trigger a search box to appear. When we select a result from the search box, the hidden input will be injected with
a lesson id as its value and the type input will have its value set as the name of the lesson.


Let's see how we configure this example in JS.

```js
const inputText = document.getElementById('selector');
const inputHoldingTheId = document.getElementById('selector');
const ajaxSearch = new AjaxInputSearch({
    container: document.querySelector('.myContainer'),
    input: inputText, 
    targetInput: inputHoldingTheId,
    endpoint: '/lesson/tableHandler/',
    viewColumnName: 'name',
    idColumnName: 'id'
});
ajaxSearch.init();
```

Here we have to pass an object with the following properties:

``input``: Is a reference to the text input (the one that is not hidden) which has our lesson name, and which triggers the search box.

``targetInput``: Is a reference to the hidden input which holds our lesson ID

``endpoint``: Endpoint that will return our entity data

``viewColumnName``: The name of the column which will be printed in the text input (in our case that is 'name' which is the lesson name)

``idColumnName``: The name of the column which will be injected into the hidden input that holds our lesson ID. In our case that is 'id'

``searchFilters``(optional): Optional property which will be used to filter the entities. It should be passed as an object with key value pairs for the filter.

``afterEntitySelectCallback``(optional): A callback to run after the entity ID has been inserted.  Takes ``entity`` as an argument.

``validateBeforeEntitySelectCallback``(optional): A callback which validates if the insertion should be made or not. Takes ``entity`` as an argument. The callback should return true or false whether the validation passed or not.


For example:
If we want to filter lessons which have the status of 1 and duration of 1 we pass it like this:

```js
const inputText = document.getElementById('selector');
const inputHoldingTheId = document.getElementById('selector');
const ajaxSearch = new AjaxInputSearch({
    container: document.querySelector('.myContainer'),
    input: inputText, 
    targetInput: inputHoldingTheId,
    endpoint: '/lesson/tableHandler/',
    viewColumnName: 'name',
    idColumnName: 'id',
    searchFilters: {status: 1, duration: 1}
});
ajaxSearch.init();
```

If we want to disallow the same ids from being inserted, we can do that using the ``validateBeforeEntitySelectCallback`` property.


Let's say that the inputs that contain our ids have a class name of ```lessonId```
We can use the callback to check if there are already any inputs with the ID of the entity a user wishes to insert, 
and if so, return false, alerting the user that it is already inserted.

```js
const inputText = document.getElementById('selector');
const inputHoldingTheId = document.getElementById('selector');
const ajaxSearch = new AjaxInputSearch({
    container: document.querySelector('.myContainer'),
    input: inputText, 
    targetInput: inputHoldingTheId,
    endpoint: '/lesson/tableHandler/',
    viewColumnName: 'name',
    idColumnName: 'id',
    validateBeforeEntitySelectCallback: (entity) => {
        if(document.querySelector(`.lessonId[value="${entity.columns.id}"]`)) { //entity.columns.id is the ID that the user wishes to insert
            alert('Lesson already inserted.');
            return false;
        }
        return true;
    }
});
ajaxSearch.init();
```

### Public methods
`setConfigProperty(propertyName, value)`: Sets a property in the config object.


#### Be sure to call the destroy() method when you are done with the instance





