# EventEmitter Class

The `EventEmitter` class provides a simple event handling mechanism in JavaScript.

## Usage

1. **Create a new instance of `EventEmitter`:**

    ```javascript
    const emitter = new EventEmitter();
    ```

2. **Register event listeners using the `on()` method:**

    ```javascript
    emitter.on(eventName, callback);
    ```

    - `eventName`: The name of the event to listen for.
    - `callback`: The function to be called when the event occurs.

3. **Remove event listeners using the `remove()` method:**

    ```javascript
    emitter.remove(eventName, callback);
    ```

    - `eventName`: The name of the event from which to remove the listener.
    - `callback` (optional): The specific callback function to remove. If not provided, all listeners for the specified event will be removed.

4. **Emit events using the `emit()` method:**

    ```javascript
    emitter.emit(eventName, data);
    ```

    - `eventName`: The name of the event to emit.
    - `data` (optional): Any data to pass to the event listeners.

## Methods

- `on(eventName, callback)`: Registers an event listener for the specified event.
- `remove(eventName, callback = null)`: Removes an event listener for the specified event. If no callback is provided, all listeners for the event are removed.
- `emit(eventName, data)`: Emits an event with the specified name and optional data.
- `destroy()`: Cleans up resources associated with the emitter instance.

## Example

```javascript
const emitter = new EventEmitter();

// Register event listener
const eventName = 'exampleEvent';
const callback = (data) => {
    console.log('Event data:', data);
};
emitter.on(eventName, callback);

// Emit event
const eventData = { message: 'Hello, world!' };
emitter.emit(eventName, eventData);

// Remove event listener
emitter.remove(eventName, callback);

// Don't forget to destroy the emitter instance when it's no longer needed
emitter.destroy();
```