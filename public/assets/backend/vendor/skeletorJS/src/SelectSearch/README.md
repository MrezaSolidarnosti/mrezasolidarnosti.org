# Select Search Component

The Select Search component is a JavaScript class-based solution for enhancing the user experience when dealing with `<select>` elements by providing a searchable dropdown feature.

### Usage

1. **HTML Structure**:

```html
<div class="selectSearchContainer">
    <label>Filter: Filter Name</label>
    <select name="someName" class="input tableFilter">
        <option value="-1">---</option>
        <option value="Value 1">Value 1</option>
        <option value="Value 2">Value 2</option>
        <!-- Add more options as needed -->
    </select>
    <div class="selectSearchOverlay"></div>
</div>
```

2. **JavaScript Initialization**:

```javascript
const selectSearch = new SelectSearch(document.querySelector('.selectSearchContainer') {
    name: 'someName',
    options: {
        '-1': '---',
        'Value 1': 'Text 1',
        'Value 2': 'Text 2'
    },
    label: 'Filter: Filter Name',
    className: 'input tableFilter'
});
selectSearch.init();
```

You have access to the static method `generateHTML()` to generate the HTML structure for the component. This method takes an object as an argument with the following properties:

- `name`: The name attribute for the select element.
- `options`: An object with the options for the select element. The key is the value and the value is the text for the option.
for example:
```javascript
const options = {
    '-1': '---',
    'Value 1': 'Text 1',
    'Value 2': 'Text 2'
};
```
- `label`(optional): The label text for the select element.
- `className`(optional): The class name for the container element.




Don't forget to call `destroy()` method when the component is no longer needed to avoid memory leaks.