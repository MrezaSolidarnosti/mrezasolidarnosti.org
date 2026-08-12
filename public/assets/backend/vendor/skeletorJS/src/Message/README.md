# Message Module

This module provides functionality to display messages of different types (info, success, warning, error) in a web application.

## Usage

## Spawn method:
```
Message.spawn({
    message = '',
    type = Message.TYPES.INFO,
    view = {
      type: Message.VIEW_TYPES.STATIC,
      container: null,
      prepend: false,
    },
    ephemeralTimeout = null,
})
```

### `message`
The message to be displayed.

### `type`
The type of message to be displayed. The available types are defined in the `Message.TYPES` property.


### `view`
An object that defines how the message will be displayed. The object has the following properties:

- `type`: The type of view to be used. The available types are defined in the `Message.VIEW_TYPES` property.
- `container`: The container element where the message will be displayed.
- `prepend`: A boolean value that indicates whether the message should be prepended to the container.

### `ephemeralTimeout`
The time in milliseconds after which the message will be removed. If this property is not provided, the message will not be removed automatically.

### `Message.removeMessages(container, type = null)`
This method removes all messages of the specified type from the container.

### `Message.VIEW_TYPES`
- `STATIC`: The message is displayed in a static container.
- `NOTIFICATION`: The message is displayed in a container and the messages are position absolute.

### `Message.TYPES`

This property provides the available types of messages:

- `INFO`: Represents an informational message.
- `SUCCESS`: Represents a success message.
- `WARNING`: Represents a warning message.
- `ERROR`: Represents an error message.

## Example

```javascript
// Select a container element where messages will be displayed
const messageContainer = document.getElementById("messageContainer");

// Display an informational message
Messages.spawn({
    message: "This is an informational message",
    type: Messages.TYPES.INFO,
    view: {
        type: Messages.VIEW_TYPES.STATIC,
        container: messageContainer,
        prepend: false,
    },
    ephemeralTimeout: 5000,
});

// Display a success message
Messages.spawn({
    message: "This is a success message",
    type: Messages.TYPES.SUCCESS,
    view: {
        type: Messages.VIEW_TYPES.STATIC,
        container: messageContainer,
        prepend: false,
    },
    ephemeralTimeout: 5000,
});

// Display a warning message
Messages.spawn({
    message: "This is a warning message",
    type: Messages.TYPES.WARNING,
    view: {
        type: Messages.VIEW_TYPES.STATIC,
        container: messageContainer,
        prepend: false,
    }
});

// Display an error message
Messages.spawn({
    message: "This is an error message",
    type: Messages.TYPES.ERROR,
    view: {
        type: Messages.VIEW_TYPES.STATIC,
        container: messageContainer,
        prepend: false,
    }
});

// Remove all messages of a specific type
Messages.removeMessages(messageContainer, Messages.TYPES.INFO);

// Remove all messages
Messages.removeMessages(messageContainer);


```
