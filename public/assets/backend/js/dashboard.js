import {theme as themeConfig} from "./theme.js";
import {modes} from "../../../vendor/skeletorjs/src/Theme/modes.js";
import MediaLibrary from "../../../vendor/skeletorjs/src/MediaLibrary/MediaLibrary.js";
import Navigation from "../../../vendor/skeletorjs/src/Navigation/Navigation.js";
import ToTop from "../../../vendor/skeletorjs/src/ToTop/ToTop.js";

const navigation = new Navigation({theme:themeConfig, defaultTheme: modes.dark, isOpenOnInit: true});
navigation.init();

const toTop = new ToTop(document.getElementById('main') ?? null);
toTop.init();


window.mediaLibrary = new MediaLibrary();
window.mediaLibrary.init();