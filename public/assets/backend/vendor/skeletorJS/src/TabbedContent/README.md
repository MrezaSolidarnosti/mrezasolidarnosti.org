# TabbedContent Component

## Description

TabbedContent is a JavaScript component that allows you to create tabbed interfaces easily. It enables users to switch between different content sections by clicking on tabs.

## Usage
```javascript
const container = document.getElementById('yourTabbedContentContainer');
const tabbedContent = new TabbedContent(container);
tabbedContent.init();
```

```html
<div id="tabbedContentContainer" class="tabs">
    <div class="tab" data-target-index="1">Tab 1</div>
    <div class="tab" data-target-index="2">Tab 2</div>
    <div class="tabContent" data-target-index="1">Content 1</div>
    <div class="tabContent" data-target-index="2">Content 2</div>
</div>
```

## Constructor

#### `TabbedContent(container: HTMLElement)`

Creates a new instance of the TabbedContent component.

- `container`: The container element that wraps the tabbed content.
- `dynamicTabs = {}`: An object that contains the dynamic tabs to be added to the tabbed content. The object should have the following structure:
    ```javascript
    {
        tabText: 'string',
        appendNumberToTabText: 'boolean',
        tabContent: HTMLElement
    }
    ```
- `tabText`: The text to be displayed on the tab.
- `appendNumberToTabText`: A boolean that determines whether to append a number to the tab text (number of the tab).
- `tabContent`: The content to be displayed when the tab is clicked.

## Methods

#### `init()`

Initializes the TabbedContent component. Must be called after instantiation.

#### `showTabContent(targetIndex: string)`

Displays the tab content associated with the specified target index.

- `targetIndex`: The index of the tab content to be shown.

#### `getTabbedContentWithClassNameInside(className: string)`

Returns tabbed content that contains an element with the specified class name.

- `className`: The class name to search for within the tab content.

#### `destroy()`

Destroys the TabbedContent instance and removes event listeners.


## You can listen to the following events:

- `tabAdded`
- `tabRemoved`
- `beforeTabRemoved`

```js
    import {events} from "./TabbedContent/events";;
    tabbedContent.eventEmitter.on(events.tabAdded, (data) => {
        console.log('Tab added', data.tabContent, data.index);
    });

  tabbedContent.eventEmitter.on(events.beforeTabRemoved, (data) => {
    console.log('Before tab removed', data.tabContent, data.index);
  });

    tabbedContent.eventEmitter.on(events.tabRemoved, (data) => {
        console.log('Tab removed', data.tabContent, data.index);
    });
```
`data` contains the tab content that was added and the tab index