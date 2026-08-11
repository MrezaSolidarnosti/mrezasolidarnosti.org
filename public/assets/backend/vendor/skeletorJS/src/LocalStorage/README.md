# LocalStorage Class

The `LocalStorage` class provides methods for storing, retrieving, and removing data from the browser's local storage.

## Usage



1. **Store data in the local storage using the `set()` method:**

    ```javascript
    // Without JSON stringification
    LocalStorage.set('myKey', 'myValue');

    // With JSON stringification
    const dataObject = { name: 'John', age: 30 };
    LocalStorage.set('userData', dataObject, true);
    ```

2. **Retrieve data from the local storage using the `get()` method:**

    ```javascript
    // Without JSON parsing
    const storedValue = LocalStorage.get('myKey');

    // With JSON parsing
    const userData = LocalStorage.get('userData', true);
    ```

3. **Remove data from the local storage using the `remove()` method:**

    ```javascript
    LocalStorage.remove('myKey');
    ```

## Methods

- `set(key, value, jsonStringifyValue = false)`: Stores data in the local storage.
- `get(key, parseJson = false)`: Retrieves data from the local storage.
- `remove(key)`: Removes data from the local storage.

## Example

```javascript
import LocalStorage from "./path/to/LocalStorage.js";


// Store data
LocalStorage.set('myKey', 'myValue');
const userData = { name: 'John', age: 30 };
LocalStorage.set('userData', userData, true);

// Retrieve data
const storedValue = LocalStorage.get('myKey');
const parsedUserData = LocalStorage.get('userData', true);

// Remove data
LocalStorage.remove('myKey');