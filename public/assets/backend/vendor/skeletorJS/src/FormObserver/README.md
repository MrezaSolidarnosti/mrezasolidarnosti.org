# FormObserver Class

The `FormObserver` class allows you to observe changes in form elements and track the number of elements that have been modified.

## Usage
```javascript
const formObserver = new FormObserver(document.getElementById('form'));
formObserver.observe();
```
You have access to the following methods:
- `formObserver.observe()`: Start observing the form elements.
- `formObserver.isModified()`: Returns `true` if any form element has been modified, `false` otherwise.
- `formObserver.reset()`: Reset the form elements to their original state.
- `formObserver.getModifiedElements()`: Returns an array of modified form elements.
- `formObserver.getUnmodifiedElements()`: Returns an array of unmodified form elements.
- `formObserver.getModifiedElementsCount()`: Returns the number of modified form elements.
- `formObserver.getUnmodifiedElementsCount()`: Returns the number of unmodified form elements.
- `formObserver.getModifiedElementsPercentage()`: Returns the percentage of modified form elements.
- `formObserver.getUnmodifiedElementsPercentage()`: Returns the percentage of unmodified form elements.
- `formObserver.resetNumberOfChangedElements()`: Reset the number of modified form elements to 0.
- `formObserver.destroy()`: Clean up the observer.

If you want to ignore some form elements, you can set the `FORM_OBSERVER_IGNORE_CLASS_NAME` to them which is `formObserverIgnore`.

Don't forget to destroy the observer when you are done with it.
```javascript
formObserver.destroy();
```