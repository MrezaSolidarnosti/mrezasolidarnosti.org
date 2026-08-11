# Loader Class

The `Loader` class provides methods for creating and managing loader elements in a web application.

## Usage

1. **Create a new instance of `Loader`:**

    ```javascript
    const loader = new Loader(config);
    ```

    - `config` (optional): An object containing customization options for the loader. If not provided, default options will be used.
    
    ### Default config:
    ```javascript
    {
       size: '50px',
       thickness: '6px',
       trackColor: '#f3f3f3',
       innerTrackColor: '#4e73df'
    }
    ```

2. **Start the loader by calling the `start()` method:**

    ```javascript
    loader.start(container, hideElementSelectors);
    ```

    - `container`: The HTML element to which the loader will be appended.
    - `hideElementSelectors` (optional): An array of CSS selectors for elements that should be hidden while the loader is active.

3. **Stop the loader by calling the `stop()` method:**

    ```javascript
    loader.stop(container, showElementsSelectors);
    ```

    - `container` (optional): The HTML element from which the loader will be removed. If not provided, the loader will be removed from its parent element.
    - `showElementsSelectors` (optional): An array of CSS selectors for elements that should be shown after the loader is stopped.

4. **Destroy the loader instance when it's no longer needed:**

    ```javascript
    loader.destroy();
    ```

## Methods

- `start(container, hideElementSelectors = null)`: Starts the loader and appends it to the specified container.
- `stop(container = null, showElementsSelectors = null)`: Stops the loader and removes it from the container.
- `destroy()`: Cleans up resources associated with the loader instance.

## Example

```javascript
const loader = new Loader();

// Start the loader
const container = document.getElementById('loaderContainer');
const hideElementsSelectors = ['.content'];
loader.start(container, hideElementsSelectors);

// Stop the loader
const showElementsSelectors = ['.content'];
loader.stop(container, showElementsSelectors);

You can use the same loader as long as you want. Just call the start and stop methods as needed.

// Destroy the loader instance when it's no longer needed
loader.destroy();
