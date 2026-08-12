# ElementRelation Library

The `ElementRelation` library facilitates the creation and management of parent-child relationships between HTML elements. It allows for dynamic addition and removal of parent and child elements.

## Usage

### Initializing `ElementRelation`

To use the `ElementRelation` class, initialize it with the following parameters:

```javascript
const elementRelation = new ElementRelation({
    container: document.querySelector('.sectionsContainer'),
    createParentButton: document.querySelector('.createSection'),
    parent: `<div class="section parent" data-base="section">
                button class="removeParent">Remove Parent</button>
                <button class="createChild">Create Child</button>
                <!-- Parent element HTML -->
            </div>`,
    child: `<div class="lesson child" data-base="lesson">
                <button class="removeChild">remove</button>
                <!-- Child element HTML -->
            </div>`,
    callbacks: [
        // Callback configurations
    ]
});
elementRelation.init();
```
The parent,removeParent,createChild,child and removeChild classes are used to identify the elements that will be created and destroyed, so they are mendatory.
## Configuration Options

- `container`: The container element where the parent and child elements will be appended.
- `createParentButton`: The button element that triggers the creation of a new parent element.
- `parent`: HTML template for the parent element. The `data-base` attribute specifies the base name.
- `child`: HTML template for the child element. The `data-base` attribute specifies the base name.
- `callbacks`: Array of callback configurations for interacting with specific elements.

## Input Naming and Class Properties

The `data-base` and `data-name` attributes are used to generate input names and class properties dynamically within parent and child elements.

- **data-base Attribute**: Specifies the base name for a group of related elements.
- **data-name Attribute**: Generates input names and class properties based on the base name.

### Example Usage

Suppose we have a parent element with `data-base="sections"` and a child element with `data-base="lesson"`:

- If the parent input has `data-name="sectionName"`, its generated input name would be "sections[][sectionName]".
- If the child input within this parent has `data-name="lessonName"`, its generated input name would be "sections[][lesson][][lessonName]".

## Capturing Events

You can use the `on()` method to capture events emitted by `ElementRelation`:

```javascript
elementRelation.on(eventName, callbackFunction);
```

- `eventName`: The event name to capture. (`parentCreated`, `childCreated`, `parentDestroyed`, `childDestroyed`)
- `callbackFunction({id, inputs})`: The function to execute when the event is captured. The `id` parameter is the unique identifier of the element, and the `inputs` parameter is an array of input elements within the element.

## Destroying `ElementRelation`

To clean up resources and remove event listeners, call the `destroy` method:

```javascript
elementRelation.destroy();
```

## Example
```html
<div class="sectionsContainer">
    <button class="createSection">Create</button>
</div>
```
```js
const elementRelation = new ElementRelation({
    container: document.querySelector('.sectionsContainer'),
    createParentButton: document.querySelector('.createSection'),
    parent: `<div class="section parent" data-base="section">
                <h2>Parent</h2>
                <button class="removeParent">Remove Parent</button>
                <button class="createChild">Create Child</button>
                <input type="hidden" data-name="id">
                <input type="text" data-name="sectionName">
                <input type="text" data-name="sectionDescription">
                <div class="childrenContainer"></div>
            </div>`,
    child: `<div class="lesson child" data-base="lesson">
                 <label>Lesson</label>
                <input class="featuredLesson" data-name="lessonId" type="hidden"
                       value="">
                <input type="text" data-name="lessonName" readonly value="">
                <button class="removeChild">remove</button>
            </div>`,
    callbacks: [
        {
            target: 'lessonName',
            callbackData: {
                event: 'click',
                callback: (event, inputs) => {
                    console.log(inputs);
                }
            }
        }
    ]
});
elementRelation.init();
```
Existing elements (when rendered on the server) should be printed in the same manner as the parent and child templates. The `data-base` and `data-name` attributes should be added to the elements to generate input names and class properties dynamically.

