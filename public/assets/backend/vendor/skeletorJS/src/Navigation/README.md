# Navigation Class

```html
<nav id="navigation">
    <div id="toggleNavigation">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
            <path d="M278.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L210.7 256 73.4 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/>
        </svg>
    </div>
    <div id="navigationSearch">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
            <path d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"/>
        </svg>
        <input aria-label="Search" id="navigationSearchInput" type="text" placeholder="Search...">
    </div>
    <div class="line"></div>
    <div id="items">
            <span id="noResultsSearchNavigation">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                    <path d="M38.8 5.1C28.4-3.1 13.3-1.2 5.1 9.2S-1.2 34.7 9.2 42.9l592 464c10.4 8.2 25.5 6.3 33.7-4.1s6.3-25.5-4.1-33.7L525.6 386.7c39.6-40.6 66.4-86.1 79.9-118.4c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C465.5 68.8 400.8 32 320 32c-68.2 0-125 26.3-169.3 60.8L38.8 5.1zM223.1 149.5C248.6 126.2 282.7 112 320 112c79.5 0 144 64.5 144 144c0 24.9-6.3 48.3-17.4 68.7L408 294.5c8.4-19.3 10.6-41.4 4.8-63.3c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3c0 10.2-2.4 19.8-6.6 28.3l-90.3-70.8zM373 389.9c-16.4 6.5-34.3 10.1-53 10.1c-79.5 0-144-64.5-144-144c0-6.9 .5-13.6 1.4-20.2L83.1 161.5C60.3 191.2 44 220.8 34.5 243.7c-3.3 7.9-3.3 16.7 0 24.6c14.9 35.7 46.2 87.7 93 131.1C174.5 443.2 239.2 480 320 480c47.8 0 89.9-12.9 126.2-32.5L373 389.9z"/>
                </svg>
                      <span>No results</span>
            </span>
        <div class="item">
            <span class="tooltip">Users</span>
            <span class="itemAnchor">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                        <path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.8-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352H378.7C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7H154.7c-14.7 0-26.7-11.9-26.7-26.7z"/>
                    </svg>
                    Users
                    <svg class="subItemsIndicator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/>
                    </svg>
                </span>
            <div class="subItemsContainer">
                <div class="subItems">
                    <div class="subItem" data-href="#userList">User List</div>
                    <div class="subItem" data-href="#addUser">Add User</div>
                </div>
            </div>
        </div>
        <div class="item" data-href="#analytics">
            <span class="tooltip">Analytics</span>
            <span class="itemAnchor">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                        <path d="M160 80c0-26.5 21.5-48 48-48h32c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H208c-26.5 0-48-21.5-48-48V80zM0 272c0-26.5 21.5-48 48-48H80c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V272zM368 96h32c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H368c-26.5 0-48-21.5-48-48V144c0-26.5 21.5-48 48-48z"/>
                    </svg>
                    Analytics
                </span>
        </div>
        <div class="item">
            <span class="tooltip">Projects</span>
            <span class="itemAnchor">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M448 160H320V128H448v32zM48 64C21.5 64 0 85.5 0 112v64c0 26.5 21.5 48 48 48H464c26.5 0 48-21.5 48-48V112c0-26.5-21.5-48-48-48H48zM448 352v32H192V352H448zM48 288c-26.5 0-48 21.5-48 48v64c0 26.5 21.5 48 48 48H464c26.5 0 48-21.5 48-48V336c0-26.5-21.5-48-48-48H48z"/>
                    </svg>
                    Projects
                    <svg class="subItemsIndicator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/>
                    </svg>
                </span>
            <div class="subItemsContainer">
                <div class="subItems">
                    <div class="subItem" data-href="#projectList">Project List</div>
                    <div class="subItem" data-href="#clients">Clients</div>
                    <div class="subItem" data-href="#tasks">Tasks</div>
                    <div class="subItem" data-href="#tags">Tags</div>
                </div>
            </div>
        </div>
    </div>
    <div class="line"></div>
    <div id="navigationSettings" class="item">
        <span class="tooltip">Settings</span>
        <div class="itemAnchor">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                <path d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z"/>
            </svg>
            Settings
            <svg class="subItemsIndicator" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                <path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/>
            </svg>
        </div>
        <div class="subItemsContainer" id="modeSelectMainContainer">
            <div class="subItems">
                <div class="subItem" id="modeSelectContainer">
                    <span>Mode</span>
                    <input type="checkbox" id="modeToggleInput"/>
                    <label for="modeToggleInput">
                        Toggle
                    </label>
                </div>
            </div>
        </div>
    </div>
    <div class="line evergreen"></div>
    <div id="navigationUser">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
            <path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/>
        </svg>
        <div id="navigationUserInfo">
            <div id="navigationUserInfoInner">
                <span id="navigationUserName">Zeleni Admin</span>
                <span id="navigationUserEmail">test@example.com</span>
            </div>
            <a href="/login/logout/" title="Sign Out">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path d="M288 32c0-17.7-14.3-32-32-32s-32 14.3-32 32V256c0 17.7 14.3 32 32 32s32-14.3 32-32V32zM143.5 120.6c13.6-11.3 15.4-31.5 4.1-45.1s-31.5-15.4-45.1-4.1C49.7 115.4 16 181.8 16 256c0 132.5 107.5 240 240 240s240-107.5 240-240c0-74.2-33.8-140.6-86.6-184.6c-13.6-11.3-33.8-9.4-45.1 4.1s-9.4 33.8 4.1 45.1c38.9 32.3 63.5 81 63.5 135.4c0 97.2-78.8 176-176 176s-176-78.8-176-176c0-54.4 24.7-103.1 63.5-135.4z"/>
                </svg>
            </a>

        </div>
    </div>
</nav>
```

Elements with the class `item` and `subItem` are clickable. When clicked, the `data-href` attribute of the clicked element is used to navigate to the corresponding page. 

If the `item` element has subItems it should not have a `data-href` attribute. Instead, the `subItem` elements should have the `data-href` attribute.

The element with the class `tooltip` is used to display a tooltip when the user hovers over the element.

If you wish to add custom behavior to an item, you can add `data-custom-behavior` attribute to the item element. This will prevent the default behavior of the navigation.

```js
const navigation = new Navigation({theme: theme});
navigation.init();
```

Theme is an object that contains schemes for the navigation. The scheme object has two properties: `light` and `dark`. 
```js
 scheme: {
       dark: {
           "colorPrimary-100": "#322bf0",
           "colorPrimary-200": "#5b43f3",
           "colorPrimary-300": "#775af5",
           "colorPrimary-400": "#8f70f8",
           "colorPrimary-500": "#a587fa",
           "colorPrimary-600": "#b99ffb",
           "colorSurface-100": "#121212",
           "colorSurface-200": "#282828",
           "colorSurface-300": "#3f3f3f",
           "colorSurface-400": "#575757",
           "colorSurface-500": "#717171",
           "colorSurface-600": "#8b8b8b",
           "colorSurface-0": "#ffffff",
           "colorSurfaceMixed-200": "#2f2b3a",
           "colorSuccess": "#4fc46d",
           "colorError": "#ff3d3d",
           "colorWarning": "#f0c929",
           "colorInfo": "#a587fa",
           "colorOnBorders": "#282828",
           "sceme": "dark",
       },
       light: {
           "colorPrimary-100": "#322bf0",
           "colorPrimary-200": "#5b43f3",
           "colorPrimary-300": "#775af5",
           "colorPrimary-400": "#8f70f8",
           "colorPrimary-500": "#a587fa",
           "colorPrimary-600": "#b99ffb",
           "colorSurface-100": "#ffffff",
           "colorSurface-200": "#f5f5f5",
           "colorSurface-300": "#e0e0e0",
           "colorSurface-400": "#c6c6c6",
           "colorSurface-500": "#a8a8a8",
           "colorSurface-600": "#8a8a8a",
           "colorSurface-0": "#121212",
           "colorSurfaceMixed-200": "#DDDDDD",
           "colorSuccess": "#4fc46d",
           "colorError": "#ff3d3d",
           "colorWarning": "#f0c929",
           "colorInfo": "#a587fa",
           "colorOnBorders": "#c6c6c6",
           "scheme": "light"
         }
   }
```
The theme object is optional. If not provided, the default theme will be used. It is used in conjunction with the ModeSelection component to change the theme of the navigation.

The `init` method initializes the navigation. It sets up the event listeners for the navigation elements.

The `destroy` method should be called if the navigation is no longer needed.