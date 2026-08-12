# Dynamic Inputs
Dynamic inputs is used to create a set of inputs that can be added or removed by the user. This is useful for creating parts of forms that require a variable number of set inputs.

## Usage
```javascript
const dynamicInputs = new DynamicInputs({
                container: container,
                baseInputName: 'someName',
                config: JSON.parse(container.getAttribute(dynamicInputsSelectors.attributes.dynamicInputsConfig) ?? null)
            });
dynamicInputs.init();
```

```html

<div class="dynamicInputs"
     data-dynamic-inputs-config='{"inputs":[{"type": "text", "name":"label[]", "placeholder":"Label"},{"type": "text", "name":"url[]", "placeholder":"URL"}]}'>
    <div class="addDynamicInput btn primary">Add Inputs</div>
    <div class="dynamicInputContainer">
        <div class="dynamicInputWrapper"><input class="input" type="text" name="label[]" placeholder="Label"
                                                value="Google"><input class="input" type="text" name="url[]"
                                                                      placeholder="URL" value="https://google.com">
            <div class="removeDynamicInput">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM175 175c-9.4 9.4-9.4 24.6 0 33.9l47 47-47 47c-9.4 9.4-9.4 24.6 0 33.9s24.6 9.4 33.9 0l47-47 47 47c9.4 9.4 24.6 9.4 33.9 0s9.4-24.6 0-33.9l-47-47 47-47c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-47 47-47-47c-9.4-9.4-24.6-9.4-33.9 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
```

The base input name is used to create the name of the inputs that are added to the dynamic inputs. The name of the inputs will be the base input name followed by an index. For example, if the base input name is `someName`, the first input group will start with the name `someName[0]`, the second input group will start with the name `someName[1]`, and so on.

The container should have a `data-dynamic-inputs-config` attribute that contains a JSON object with the following properties:
- `inputs`: An array of input objects. Each object should have the following properties:
    - `type`: The type of input to create.
    - `name`: The name of the input.
    - `label`: The label text for the input.

If you want to add existing inputs to the dynamic inputs, you can do so by adding them to the `dynamicInputWrapper` div. The inputs should have the same name as the inputs in the `data-dynamic-inputs-config` attribute.
Each set of inputs should be wrapped in a `dynamicInputWrapper` div.

If you want the component to be read-only you can set the `data-readonly="true"` to the container.

```html
<div class="dynamicInputs" data-readonly="true">...</div>
```

## Methods

`init()`: Initializes the dynamic inputs.

`addInput(data = null)`: Adds a new set of inputs to the dynamic inputs. If you want to add inputs with existing data, you can pass an object with the data to the method. The object should contain key value pairs with the key being the name of the input from the `data-dynamic-inputs-config` attribute and the value being the value of the input.

For example:
```javascript
dynamicInputs.addInput({
  "label[]": "Facebook",
  "url[]": "https://facebook.com"
});
```

`getAddButton()`: Returns the add button element.

`getInputs()` : Returns all the inputs in the dynamic inputs.

`removeAllInputs()`: Removes all inputs from the dynamic inputs.

`destroy()`: Cleans up the dynamic inputs and removes all event listeners.


Don't forget to destroy when you are done with it.
```javascript
dynamicInputs.destroy();
```