# FormValidator

### Usage
_____
####  JavaScript

```js
let form = document.getElementById('yourForm');
const formValidator = new FormValidator({
    form: form,
    formFieldClassNames: 'yourInputClassName',
    formScrollableContainer: document.getElementById('yourScrollableContainer')
});
formValidator.init();
```
#### arguments
```form``` represents the form element inside your HTML. (required)

```formFieldClassName``` represents the class name of your inputs. (required)

```formScrollableContainer``` if the form is inside a modal or any other scrollable container we should pass
it here. If an element is not provided the default value will be the ``window`` object. When a field is invalid the
the ```formScrollableContainer``` will be scrolled to the first invalid input.

___
#### HTML
Configurating the validation is done through HTML data attributes.

Preferably one input should have one container.
```html
<div class="someContainer">
    <label for="someInput">Some Label</label>
    <input type="text" name="someInput" id="someInput" class="someClass"/>
</div>
```
Note that the input has the class ```someClass```. This class would be passed to the FormValidator constructor as ```formFieldClassNames```

All data attributes are optional except data pairs, which must co-exist.
For example, if a field has a data attribute for minimum length, it must have a minimum length message attribute as well.

Inputs that have the class name ```hidden``` will not be validated. This can be useful when you have inputs that are shown/hidden depending on some user action.
#### Data attributes
`data-required` - `true`,`false`

`data-required-text` - Text to display if the input is not set but is required.

`data-select-empty-value` - Value which is considered "empty" for the `select` element, for example `-1`.

`data-validation-strategy` - `onlyLetters`, `uppercase`, `lowercase`, `email`, (can also pass custom regex as value).

if using regex, the regex that u input into the data-validation-strategy should omit the wrapping `/ /` for example the regexp `/^hello/` should be passed as `^hello`.

`data-validation-strategy-message` - Text to display if the strategy fails.

`data-max-len` - Max length for text fields.

`data-max-len-message` - Text to display if the value length of an input is `>` than `data-max-len`.

`data-min-len` - Min length for text fields.

`data-min-len-message ` - Text to display if the value length of an input is `<` than `data-min-len`.

`data-exact-len` - Exact length for text fields.

`data-exact-len-message` - Text to display if the value length of the input is not equal to `data-exact-len`.

`data-max-num` - Max value for number fields.

`data-max-num-message` - Text to display if the value of the input is `>` than `data-max-num`.

`data-min-num` - Min value for number fields.

`data-min-num-message` - Text to display if the value of the input is `<` than `data-min-num`.

`data-match-input-id` - The id of the input that the current input should match.

`data-match-input-message` - Text to display if the value of the input does not match the value of the input with the id `data-match-input-id`.

`data-before-date` - Date that the input should be before.

`data-before-date-message` - Text to display if the value of the input is not before `data-before-date`.

`data-after-date` - Date that the input should be after.

`data-after-date-message` - Text to display if the value of the input is not after `data-after-date`.

`data-before-other-date` - Date that the input should be before in comparison to another input. It should be an id of another input.

`data-before-other-date-message` - Text to display if the value of the input is not before the value of the input with the id `data-before-other-date`.

`data-after-other-date` - Date that the input should be after in comparison to another input. It should be an id of another input.

`data-after-other-date-message` - Text to display if the value of the input is not after the value of the input with the id `data-after-other-date`.

`data-before-date-time` - Date and time that the input should be before.

`data-before-date-time-message` - Text to display if the value of the input is not before `data-before-date-time`.

`data-after-date-time` - Date and time that the input should be after.

`data-after-date-time-message` - Text to display if the value of the input is not after `data-after-date-time`.

`data-before-other-date-time` - Date and time that the input should be before in comparison to another input. It should be an id of another input.

`data-before-other-date-time-message` - Text to display if the value of the input is not before the value of the input with the id `data-before-other-date-time`.

`data-after-other-date-time` - Date and time that the input should be after in comparison to another input. It should be an id of another input.

`data-after-other-date-time-message` - Text to display if the value of the input is not after the value of the input with the id `data-after-other-date-time`.

`data-before-time` - Time that the input should be before.

`data-before-time-message` - Text to display if the value of the input is not before `data-before-time`.

`data-after-time` - Time that the input should be after.

`data-after-time-message` - Text to display if the value of the input is not after `data-after-time`.

`data-before-other-time` - Time that the input should be before in comparison to another input. It should be an id of another input.

`data-before-other-time-message` - Text to display if the value of the input is not before the value of the input with the id `data-before-other-time`.

`data-after-other-time` - Time that the input should be after in comparison to another input. It should be an id of another input.

`data-after-other-time-message` - Text to display if the value of the input is not after the value of the input with the id `data-after-other-time`.

`data-apply-only-when-populated` - `true`, `false`. If set to `true`, the validation will be applied only when the input is populated.
#### Validation order
Validations are executed in an order, which means only one validation can fail at a time.

This will be a feature in the future, when you will be able to choose to print all failed validations, or a single one.


#### Example
We want to validate an input which is a text field. The input can be empty (it is not required). The maximum length is 16 characters.
```html
<div class="someContainer">
    <label for="someInput">Some Label</label>
    <input type="text" data-max-len="16"
           data-max-len-message="The maximum number of characters is 16"
           name="someInput" id="someInput" class="someClass"/>
</div>
```

We want to validate an input which is of type number. It is a required field. The maximum number is 14, and the minimum is 6.
```html
<div class="someContainer">
    <label for="someInput">Some Label</label>
    <input data-required="true"
           data-required-text="This field is required"
           data-max-num="14"
           data-max-len-message="The maximum value must not exceed 14"
           data-min-num="6"
           data-min-num-message="The minimum value must not be below 6"
           type="number" name="someInput" id="someInput" class="someClass"/>
</div>
```

We want to validate an input that is required, can only contain letters, and must not be longer than 8 characters.
```html
<div class="someContainer">
    <label for="someInput">Some Label</label>
    <input type="text" data-required="true"
           data-required-text="This field is required"
           data-validation-strategy="onlyLetters"
           data-validation-strategy-message="Only letters are allowed"
           data-max-len="8"
           data-max-len-message="The maximum number of characters must not exceed 8"
           name="someInput" id="someInput" class="someClass"/>
</div>
```

The Form Validator instance also has the eventEmitter property which can be used to listen to events.
```javascript
import {events} from './FormValidator/events.js';

const formValidator = new FormValidator({
    form: form,
    formFieldClassNames: 'yourInputClassName',
    formScrollableContainer: document.getElementById('yourScrollableContainer')
});
formValidator.eventEmitter.on(events.invalidFormSubmitted, () => {
    console.log('Form is invalid');
});
```
Event list:
- `invalidFormSubmitted` - emitted when the form is submitted and is invalid.


If you want to ignore the validation of a specific input, you can add the class FORM_VALIDATOR_IGNORE_CLASS_NAME which is formValidatorIgnore
```html


Don't forget to destroy the observer when you are done with it.
```javascript
formValidator.destroy();
```

